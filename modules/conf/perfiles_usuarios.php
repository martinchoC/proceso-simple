<?php
// Configuración de la página
$pageTitle = "Gestión de Perfiles";
$currentPage = 'perfiles';
$modudo_idx = 1;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Asignación de Usuarios a Perfiles</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Asignación de Usuarios</li>
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
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Filtros</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="selectModulo">Módulo:</label>
                                            <select class="form-control" id="selectModulo">
                                                <option value="">Seleccione un módulo</option>
                                            </select>
                                        </div>
                                        <div class="form-group mt-3">
                                            <label for="selectPerfil">Perfil:</label>
                                            <select class="form-control" id="selectPerfil" disabled>
                                                <option value="">Seleccione un perfil</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Usuarios Asignados 
                                            <span id="tituloFiltro" class="text-muted small"></span>
                                        </h3>
                                        <div class="card-tools">
                                            <button class="btn btn-sm btn-primary" id="btnAgregarAsignacion">
                                                <i class="fas fa-plus"></i> Agregar Asignación
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div id="infoSeleccion" class="alert alert-info d-none">
                                            <i class="fas fa-info-circle"></i> 
                                            <span id="textoInfo">Seleccione un módulo y perfil para gestionar las asignaciones de usuarios</span>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Usuario</th>
                                                        <th>Nombre</th>
                                                        <th>Email</th>
                                                        <th>Fecha Inicio</th>
                                                        <th>Fecha Fin</th>
                                                        <th>Estado</th>
                                                        <th>Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="tablaAsignaciones">
                                                    <tr>
                                                        <td colspan="7" class="text-center">Seleccione un perfil para ver las asignaciones</td>
                                                    </tr>
                                                </tbody>
                                            </table>    
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>

<!-- Modal para agregar/editar asignación -->
<div class="modal fade" id="modalAsignacionUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAsignacionUsuarioTitle">Agregar Asignación de Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formAsignacionUsuario">
                    <input type="hidden" id="usuario_perfil_id" name="usuario_perfil_id" value="">
                    <input type="hidden" id="perfil_id" name="perfil_id" value="">
                    <div class="mb-3">
                        <label for="selectUsuario" class="form-label">Usuario</label>
                        <select class="form-control" id="selectUsuario" name="usuario_id" >
                            <option value="">Seleccione un usuario</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="fechaInicio" class="form-label">Fecha Inicio</label>
                                <input type="date" class="form-control" id="fechaInicio" name="fecha_inicio" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="fechaFin" class="form-label">Fecha Fin</label>
                                <input type="date" class="form-control" id="fechaFin" name="fecha_fin" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="validarSolapamiento" checked>
                            <label class="form-check-label" for="validarSolapamiento">
                                Validar que no existan asignaciones solapadas
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarAsignacion">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Cargar módulos al iniciar
    cargarModulos();
    
    // Evento cuando cambia el módulo seleccionado
    $('#selectModulo').change(function() {
        var modulo_id = $(this).val();
        $('#selectPerfil').prop('disabled', !modulo_id);
        
        if (modulo_id) {
            cargarPerfilesPorModulo(modulo_id);
            cargarAsignacionesPorModulo(modulo_id); // ← Nueva función
            actualizarInfoSeleccion();
        } else {
            $('#selectPerfil').empty().append('<option value="">Seleccione un perfil</option>');
            $('#tablaAsignaciones').html('<tr><td colspan="7" class="text-center">Seleccione un módulo para ver las asignaciones</td></tr>');
            actualizarInfoSeleccion();
        }
    });
    
    // Evento cuando cambia el perfil seleccionado
    $('#selectPerfil').change(function() {
        var perfil_id = $(this).val();
        var modulo_id = $('#selectModulo').val();
        $('#perfil_id').val(perfil_id);
        actualizarInfoSeleccion();
        
        if (perfil_id) {
            // Si hay perfil específico, cargar solo ese perfil
            cargarAsignacionesUsuarioPerfil(perfil_id);
        } else if (modulo_id) {
            // Si no hay perfil pero sí módulo, cargar todo el módulo
            cargarAsignacionesPorModulo(modulo_id);
        } else {
            $('#tablaAsignaciones').html('<tr><td colspan="7" class="text-center">Seleccione un módulo para ver las asignaciones</td></tr>');
        }
    });
    
    // Función para actualizar la información de selección
    function actualizarInfoSeleccion() {
        var modulo = $('#selectModulo option:selected').text();
        var perfil = $('#selectPerfil option:selected').text();
        
        if (modulo && modulo !== "Seleccione un módulo") {
            var texto = `Módulo: <strong>${modulo}</strong>`;
            if (perfil && perfil !== "Seleccione un perfil") {
                texto += ` | Perfil: <strong>${perfil}</strong>`;
            } else {
                texto += ` | <em>Mostrando todos los perfiles</em>`;
            }
            $('#textoInfo').html(texto);
            $('#infoSeleccion').removeClass('d-none');
        } else {
            $('#infoSeleccion').addClass('d-none');
        }
    }
    
    // Función para cargar asignaciones por módulo (todos los perfiles)
    function cargarAsignacionesPorModulo(modulo_id) {
        $.get('perfiles_usuarios_ajax.php', {
            accion: 'obtener_asignaciones_por_modulo',
            modulo_id: modulo_id
        }, function(res) {
            var tbody = $('#tablaAsignaciones');
            tbody.empty();
            
            if (res && res.length > 0) {
                // Agrupar por perfil para mejor visualización
                var asignacionesPorPerfil = {};
                
                $.each(res, function(i, asignacion) {
                    if (!asignacionesPorPerfil[asignacion.perfil_id]) {
                        asignacionesPorPerfil[asignacion.perfil_id] = {
                            perfil_nombre: asignacion.perfil_nombre,
                            asignaciones: []
                        };
                    }
                    asignacionesPorPerfil[asignacion.perfil_id].asignaciones.push(asignacion);
                });
                
                // Mostrar en la tabla
                $.each(asignacionesPorPerfil, function(perfil_id, grupo) {
                    // Fila de encabezado del perfil
                    tbody.append(`
                        <tr class="table-primary">
                            <td colspan="7" class="fw-bold">
                                <i class="fas fa-users"></i> Perfil: ${grupo.perfil_nombre}
                            </td>
                        </tr>
                    `);
                    
                    // Asignaciones de este perfil
                    $.each(grupo.asignaciones, function(i, asignacion) {
                        var hoy = new Date();
                        var fechaFin = new Date(asignacion.fecha_fin_formateada.split('/').reverse().join('-'));
                        var estado = hoy <= fechaFin ? 
                            '<span class="badge bg-success">Vigente</span>' : 
                            '<span class="badge bg-secondary">Expirada</span>';
                        
                        var acciones = `
                            <button class="btn btn-sm btn-warning btn-editar-asignacion" 
                                    data-id="${asignacion.usuario_perfil_id}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger btn-eliminar-asignacion" 
                                    data-id="${asignacion.usuario_perfil_id}">
                                <i class="fas fa-trash"></i>
                            </button>
                        `;
                        
                        tbody.append(`
                            <tr>                            
                                <td>${asignacion.usuario}</td>
                                <td>${asignacion.usuario_nombre}</td>
                                <td>${asignacion.email}</td>
                                <td>${asignacion.fecha_inicio_formateada}</td>
                                <td>${asignacion.fecha_fin_formateada}</td>
                                <td>${estado}</td>
                                <td>${acciones}</td>
                            </tr>
                        `);
                    });
                });
                
                // Agregar eventos a los botones
                $('.btn-editar-asignacion').click(function() {
                    var id = $(this).data('id');
                    abrirModalAsignacion(id);
                });
                
                $('.btn-eliminar-asignacion').click(function() {
                    var id = $(this).data('id');
                    eliminarAsignacion(id);
                });
                
            } else {
                tbody.append('<tr><td colspan="7" class="text-center">No hay asignaciones para este módulo</td></tr>');
            }
        }, 'json');
    }
    
    // Función para cargar asignaciones por perfil específico (existente)
    function cargarAsignacionesUsuarioPerfil(perfil_id) {
        $.get('perfiles_usuarios_ajax.php', {
            accion: 'obtener_asignaciones_usuario_perfil',
            perfil_id: perfil_id
        }, function(res) {
            var tbody = $('#tablaAsignaciones');
            tbody.empty();
            
            if (res && res.length > 0) {
                $.each(res, function(i, asignacion) {
                    var hoy = new Date();
                    var fechaFin = new Date(asignacion.fecha_fin_formateada.split('/').reverse().join('-'));
                    var estado = hoy <= fechaFin ? 
                        '<span class="badge bg-success">Vigente</span>' : 
                        '<span class="badge bg-secondary">Expirada</span>';
                    
                    var acciones = `
                        <button class="btn btn-sm btn-warning btn-editar-asignacion" 
                                data-id="${asignacion.usuario_perfil_id}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger btn-eliminar-asignacion" 
                                data-id="${asignacion.usuario_perfil_id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    `;
                    
                    tbody.append(`
                        <tr>                            
                            <td>${asignacion.usuario}</td>
                            <td>${asignacion.usuario_nombre}</td>
                            <td>${asignacion.email}</td>
                            <td>${asignacion.fecha_inicio_formateada}</td>
                            <td>${asignacion.fecha_fin_formateada}</td>
                            <td>${estado}</td>
                            <td>${acciones}</td>
                        </tr>
                    `);
                });
                
                // Agregar eventos a los botones
                $('.btn-editar-asignacion').click(function() {
                    var id = $(this).data('id');
                    abrirModalAsignacion(id);
                });
                
                $('.btn-eliminar-asignacion').click(function() {
                    var id = $(this).data('id');
                    eliminarAsignacion(id);
                });
                
            } else {
                tbody.append('<tr><td colspan="7" class="text-center">No hay asignaciones para este perfil</td></tr>');
            }
        }, 'json');
    }
    
    // Evento cuando cambia el módulo seleccionado
    $('#selectModulo').change(function() {
        var modulo_id = $(this).val();
        $('#selectPerfil').prop('disabled', !modulo_id);
        
        if (modulo_id) {
            cargarPerfilesPorModulo(modulo_id);
            actualizarInfoSeleccion();
        } else {
            $('#selectPerfil').empty().append('<option value="">Seleccione un perfil</option>');
            $('#tablaAsignaciones').html('<tr><td colspan="7" class="text-center">Seleccione un perfil para ver las asignaciones</td></tr>');
            actualizarInfoSeleccion();
        }
    });
    
    // Evento cuando cambia el perfil seleccionado
    $('#selectPerfil').change(function() {
        var perfil_id = $(this).val();
        $('#perfil_id').val(perfil_id);
        actualizarInfoSeleccion();
        
        if (perfil_id) {
            cargarAsignacionesUsuarioPerfil(perfil_id);
        } else {
            $('#tablaAsignaciones').html('<tr><td colspan="7" class="text-center">Seleccione un perfil para ver las asignaciones</td></tr>');
        }
    });
    
    // Función para actualizar la información de selección
    function actualizarInfoSeleccion() {
        var modulo = $('#selectModulo option:selected').text();
        var perfil = $('#selectPerfil option:selected').text();
        
        if (modulo && modulo !== "Seleccione un módulo" && perfil && perfil !== "Seleccione un perfil") {
            $('#textoInfo').html(`Módulo: <strong>${modulo}</strong> | Perfil: <strong>${perfil}</strong>`);
            $('#infoSeleccion').removeClass('d-none');
        } else {
            $('#infoSeleccion').addClass('d-none');
        }
    }
    
    // Función para cargar los módulos
    function cargarModulos() {
        $.get('perfiles_usuarios_ajax.php', {accion: 'obtener_modulos'}, function(res) {
            if (res && res.length > 0) {
                $('#selectModulo').empty().append('<option value="">Seleccione un módulo</option>');
                $.each(res, function(i, modulo) {
                    $('#selectModulo').append($('<option>', {
                        value: modulo.modulo_id,
                        text: modulo.modulo
                    }));
                });
            }
        }, 'json');
    }
    
    // Función para cargar perfiles por módulo
    function cargarPerfilesPorModulo(modulo_id) {
        $.get('perfiles_usuarios_ajax.php', {accion: 'obtener_perfiles_por_modulo', modulo_id: modulo_id}, function(res) {
            $('#selectPerfil').empty().append('<option value="">Seleccione un perfil</option>');
            
            if (res && res.length > 0) {
                $.each(res, function(i, perfil) {
                    $('#selectPerfil').append($('<option>', {
                        value: perfil.perfil_id,
                        text: perfil.perfil_nombre
                    }));
                });
            } else {
                $('#selectPerfil').append('<option value="">No hay perfiles para este módulo</option>');
            }
        }, 'json');
    }
    
    // Abrir modal para agregar asignación
    $('#btnAgregarAsignacion').click(function() {
        var modulo_id = $('#selectModulo').val();
        if (!modulo_id) {
            Swal.fire({
                icon: 'warning',
                title: 'Seleccione un módulo',
                text: 'Debe seleccionar un módulo primero'
            });
            return;
        }
        
        var perfil_id = $('#selectPerfil').val();
        if (!perfil_id) {
            Swal.fire({
                icon: 'warning',
                title: 'Seleccione un perfil',
                text: 'Debe seleccionar un perfil para agregar una asignación'
            });
            return;
        }
        
        abrirModalAsignacion();
    });
    
    // Función para abrir el modal de asignación
    function abrirModalAsignacion(usuario_perfil_id = null) {
        $('#modalAsignacionUsuarioTitle').text(usuario_perfil_id ? 'Editar Asignación' : 'Agregar Asignación');
        $('#formAsignacionUsuario')[0].reset();
        $('#usuario_perfil_id').val(usuario_perfil_id || '');
        $('#perfil_id').val($('#selectPerfil').val());
        $('#validarSolapamiento').prop('checked', true);
        
        // Cargar usuarios si no se han cargado aún
        if ($('#selectUsuario option').length <= 1) {
            cargarUsuarios();
        }
        
        // Si es edición, cargar los datos
        if (usuario_perfil_id) {
            cargarDatosAsignacion(usuario_perfil_id);
        } else {
            $('#selectUsuario').prop('disabled', false);
        }
        
        $('#modalAsignacionUsuario').modal('show');
    }
    
    // Cargar datos de asignación para edición
    function cargarDatosAsignacion(usuario_perfil_id) {
        $.get('perfiles_usuarios_ajax.php', {
            accion: 'obtener_asignacion_por_id',
            usuario_perfil_id: usuario_perfil_id
        }, function(res) {
            if (res && res.resultado && res.datos) {
                var asignacion = res.datos;
                $('#selectUsuario').val(asignacion.usuario_id);
                
                // Convertir fechas de DD/MM/YYYY a YYYY-MM-DD para los inputs type="date"
                function convertirFechaParaInput(fecha) {
                    if (!fecha) return '';
                    
                    if (fecha.includes('/')) {
                        var partes = fecha.split('/');
                        if (partes.length === 3) {
                            return partes[2] + '-' + partes[1].padStart(2, '0') + '-' + partes[0].padStart(2, '0');
                        }
                    }
                    
                    return fecha;
                }
                
                $('#fechaInicio').val(convertirFechaParaInput(asignacion.fecha_inicio_formateada || asignacion.fecha_inicio));
                $('#fechaFin').val(convertirFechaParaInput(asignacion.fecha_fin_formateada || asignacion.fecha_fin));
                
                // Deshabilitar selección de usuario en edición
                $('#selectUsuario').prop('disabled', true);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: res.mensaje || 'No se pudieron cargar los datos de la asignación'
                });
            }
        }, 'json').fail(function(xhr, status, error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al cargar los datos: ' + error
            });
        });
    }
    
    // Cargar usuarios para el select
    function cargarUsuarios() {
        $.get('perfiles_usuarios_ajax.php', {accion: 'obtener_usuarios'}, function(res) {
            if (res && res.length > 0) {
                $('#selectUsuario').empty().append('<option value="">Seleccione un usuario</option>');
                $.each(res, function(i, usuario) {
                    $('#selectUsuario').append($('<option>', {
                        value: usuario.usuario_id,
                        text: usuario.usuario_nombre + ' (' + usuario.email + ')'
                    }));
                });
            }
        }, 'json');
    }
    
    // Cargar asignaciones de usuario por perfil
    function cargarAsignacionesUsuarioPerfil(perfil_id) {
        $.get('perfiles_usuarios_ajax.php', {
            accion: 'obtener_asignaciones_usuario_perfil',
            perfil_id: perfil_id
        }, function(res) {
            var tbody = $('#tablaAsignaciones');
            tbody.empty();
            
            if (res && res.length > 0) {
                $.each(res, function(i, asignacion) {
                    var hoy = new Date();
                    var fechaFin = new Date(asignacion.fecha_fin_formateada.split('/').reverse().join('-'));
                    var estado = hoy <= fechaFin ? 
                        '<span class="badge bg-success">Vigente</span>' : 
                        '<span class="badge bg-secondary">Expirada</span>';
                    
                    var acciones = `
                        <button class="btn btn-sm btn-warning btn-editar-asignacion" 
                                data-id="${asignacion.usuario_perfil_id}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger btn-eliminar-asignacion" 
                                data-id="${asignacion.usuario_perfil_id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    `;
                    
                    tbody.append(`
                        <tr>                            
                            <td>${asignacion.usuario}</td>
                            <td>${asignacion.usuario_nombre}</td>
                            <td>${asignacion.email}</td>
                            <td>${asignacion.fecha_inicio_formateada}</td>
                            <td>${asignacion.fecha_fin_formateada}</td>
                            <td>${estado}</td>
                            <td>${acciones}</td>
                        </tr>
                    `);
                });
                
                // Agregar eventos a los botones
                $('.btn-editar-asignacion').click(function() {
                    var id = $(this).data('id');
                    abrirModalAsignacion(id);
                });
                
                $('.btn-eliminar-asignacion').click(function() {
                    var id = $(this).data('id');
                    eliminarAsignacion(id);
                });
                
            } else {
                tbody.append('<tr><td colspan="7" class="text-center">No hay asignaciones para este perfil</td></tr>');
            }
        }, 'json');
    } 
    
    // Guardar asignación
    $('#btnGuardarAsignacion').click(function() {
        // Validar que se haya seleccionado un perfil
        var perfil_id = $('#perfil_id').val();
        if (!perfil_id) {
                Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se ha seleccionado un perfil válido'
            });
            return;
        }
        
        var formData = new FormData($('#formAsignacionUsuario')[0]);
         // SOLUCIÓN: Si el select está deshabilitado (modo edición), agregar manualmente el usuario_id
        if ($('#selectUsuario').prop('disabled')) {
            var usuario_id = $('#selectUsuario').val();
            if (usuario_id) {
                formData.append('usuario_id', usuario_id);
            }
        }
        formData.append('validar_solapamiento', $('#validarSolapamiento').is(':checked'));
        formData.append('usuario_creacion', 1);
        formData.append('usuario_actualizacion', 1);
        
        // Determinar la acción
        var accion = $('#usuario_perfil_id').val() ? 'actualizar_asignacion_usuario_perfil' : 'asignar_usuario_perfil';
        formData.append('accion', accion);
        
        // Validar campos obligatorios
        if (!formData.get('usuario_id') || !formData.get('fecha_inicio') || !formData.get('fecha_fin')) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Todos los campos son obligatorios'
            });
            return;
        }
        
        // Validar que la fecha de inicio sea anterior a la fecha de fin
        if (new Date(formData.get('fecha_inicio')) > new Date(formData.get('fecha_fin'))) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'La fecha de inicio debe ser anterior a la fecha de fin'
            });
            return;
        }
        
        // Mostrar carga
        Swal.fire({
            title: 'Guardando...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        $.ajax({
            url: 'perfiles_usuarios_ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                Swal.close();
                if (res.resultado) {
                    $('#modalAsignacionUsuario').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: res.mensaje || 'Operación realizada correctamente',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    cargarAsignacionesUsuarioPerfil(perfil_id);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.mensaje || 'No se pudo completar la operación'
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error en la comunicación con el servidor: ' + error
                });
            }
        });
    });
    
    // Eliminar asignación
    function eliminarAsignacion(usuario_perfil_id) {
        Swal.fire({
            title: '¿Está seguro?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Mostrar carga
                Swal.fire({
                    title: 'Eliminando...',
                    text: 'Por favor espere',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                $.post('perfiles_usuarios_ajax.php', {
                    accion: 'eliminar_asignacion_usuario_perfil',
                    usuario_perfil_id: usuario_perfil_id
                }, function(res) {
                    Swal.close();
                    if (res.resultado) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Eliminado',
                            text: res.mensaje || 'Asignación eliminada correctamente',
                            showConfirmButton: false,
                            timer: 1500
                        });
                        // Recargar la tabla
                        cargarAsignacionesUsuarioPerfil($('#selectPerfil').val());
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: res.mensaje || 'No se pudo eliminar la asignación'
                        });
                    }
                }, 'json').fail(function(xhr, status, error) {
                    Swal.close();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error de conexión: ' + error
                    });
                });
            }
        });
    }
});

</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>