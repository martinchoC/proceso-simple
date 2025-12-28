<?php
// Configuración de la página
$pageTitle = "Gestión de Perfiles";
$currentPage = 'perfiles';
$modudo_idx = 1;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

// Obtener el ID de empresa de la sesión (ajusta según tu sistema)
$empresa_id_sesion = null; // Temporal - reemplaza con tu lógica
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
                                        <!-- Select de empresa -->
                                        <div class="form-group">
                                            <label for="selectEmpresa">Empresa:</label>
                                            <select class="form-control" id="selectEmpresa">
                                                <option value="">Seleccione una empresa</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group mt-3">
                                            <label for="selectModulo">Módulo:</label>
                                            <select class="form-control" id="selectModulo" disabled>
                                                <option value="">Seleccione un módulo</option>
                                            </select>
                                        </div>
                                        <div class="form-group mt-3">
                                            <label for="selectPerfil">Perfil:</label>
                                            <select class="form-control" id="selectPerfil" disabled>
                                                <option value="">Seleccione un perfil</option>
                                            </select>
                                        </div>
                                        
                                        <!-- NUEVO: Filtro por usuario -->
                                        <hr>
                                        <div class="form-group mt-3">
                                            <label for="selectUsuarioFiltro">Ver por Usuario:</label>
                                            <select class="form-control" id="selectUsuarioFiltro">
                                                <option value="">Seleccione un usuario</option>
                                                <option value="todos">Ver todos los usuarios</option>
                                            </select>
                                        </div>
                                        <div class="form-group mt-2">
                                            <button class="btn btn-sm btn-outline-primary w-100" id="btnFiltrarUsuario">
                                                <i class="fas fa-filter"></i> Filtrar por Usuario
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title" id="tituloTabla">Usuarios Asignados 
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
                                            <span id="textoInfo">Seleccione una empresa, módulo y perfil para gestionar las asignaciones de usuarios</span>
                                        </div>
                                        
                                        <!-- NUEVO: Información de filtro por usuario -->
                                        <div id="infoUsuarioFiltro" class="alert alert-warning d-none">
                                            <i class="fas fa-user"></i> 
                                            <span id="textoUsuarioFiltro">Mostrando todas las asignaciones del usuario seleccionado</span>
                                            <button type="button" class="btn-close float-end" id="btnLimpiarFiltroUsuario" style="font-size: 0.8rem;"></button>
                                        </div>
                                        
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Usuario</th>
                                                        <th>Nombre</th>
                                                        <th>Email</th>
                                                        <th>Empresa</th>
                                                        <th>Módulo</th>
                                                        <th>Perfil</th>
                                                        <th>Fecha Inicio</th>
                                                        <th>Fecha Fin</th>
                                                        <th>Estado</th>
                                                        <th>Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="tablaAsignaciones">
                                                    <tr>
                                                        <td colspan="10" class="text-center">Seleccione una empresa para ver las asignaciones</td>
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
                    <input type="hidden" id="empresa_perfil_id" name="empresa_perfil_id" value="">
                    <input type="hidden" id="empresa_id_modal" name="empresa_id" value="">
                    
                    <div class="mb-3">
                        <label for="selectUsuario" class="form-label">Usuario</label>
                        <select class="form-control" id="selectUsuario" name="usuario_id" required>
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
    var empresaSeleccionada = <?php echo $empresa_id_sesion ? 'true' : 'false'; ?>;
    var empresaIdSesion = <?php echo $empresa_id_sesion ?: 'null'; ?>;
    var usuarioFiltroActivo = false;
    var usuarioFiltroId = null;
    var usuarioFiltroNombre = '';
    
    // Cargar empresas al iniciar
    cargarEmpresas();
    cargarUsuariosParaFiltro();
    
    // Si hay empresa de sesión, seleccionarla automáticamente
    if (empresaIdSesion) {
        setTimeout(function() {
            $('#selectEmpresa').val(empresaIdSesion).trigger('change');
        }, 500);
    }
    
    // Evento cuando cambia la empresa seleccionada
    $('#selectEmpresa').change(function() {
        var empresa_id = $(this).val();
        empresaSeleccionada = empresa_id;
        
        $('#selectModulo').prop('disabled', !empresa_id);
        $('#selectPerfil').prop('disabled', true);
        $('#selectPerfil').empty().append('<option value="">Seleccione un perfil</option>');
        
        if (empresa_id) {
            // Habilitar módulo y limpiar
            $('#selectModulo').prop('disabled', false);
            $('#selectModulo').val('');
            $('#selectModulo').trigger('change');
            
            // Si no hay filtro de usuario activo, cargar asignaciones por empresa
            if (!usuarioFiltroActivo) {
                cargarAsignacionesPorEmpresa(empresa_id);
                actualizarInfoSeleccion();
            }
        } else {
            $('#selectModulo').prop('disabled', true);
            $('#selectPerfil').prop('disabled', true);
            if (!usuarioFiltroActivo) {
                $('#tablaAsignaciones').html('<tr><td colspan="10" class="text-center">Seleccione una empresa para ver las asignaciones</td></tr>');
            }
            actualizarInfoSeleccion();
        }
    });
    
    // Evento cuando cambia el módulo seleccionado
    $('#selectModulo').change(function() {
        var modulo_id = $(this).val();
        var empresa_id = $('#selectEmpresa').val();
        
        $('#selectPerfil').prop('disabled', !modulo_id || !empresa_id);
        
        if (modulo_id && empresa_id) {
            cargarPerfilesPorModuloEmpresa(modulo_id, empresa_id);
            if (!usuarioFiltroActivo) {
                cargarAsignacionesPorModuloEmpresa(empresa_id, modulo_id);
                actualizarInfoSeleccion();
            }
        } else {
            $('#selectPerfil').empty().append('<option value="">Seleccione un perfil</option>');
            if (!usuarioFiltroActivo) {
                $('#tablaAsignaciones').html('<tr><td colspan="10" class="text-center">Seleccione una empresa y módulo para ver las asignaciones</td></tr>');
            }
            actualizarInfoSeleccion();
        }
    });
    
    // Evento cuando cambia el perfil seleccionado
    $('#selectPerfil').change(function() {
        var empresa_perfil_id = $(this).val();
        var empresa_id = $('#selectEmpresa').val();
        var modulo_id = $('#selectModulo').val();
        
        $('#empresa_perfil_id').val(empresa_perfil_id);
        actualizarInfoSeleccion();
        
        if (!usuarioFiltroActivo) {
            if (empresa_perfil_id) {
                // Si hay perfil específico, cargar solo ese perfil
                cargarAsignacionesUsuarioPerfil(empresa_perfil_id);
            } else if (empresa_id && modulo_id) {
                // Si no hay perfil pero sí empresa y módulo, cargar todo el módulo
                cargarAsignacionesPorModuloEmpresa(empresa_id, modulo_id);
            } else if (empresa_id) {
                // Si solo hay empresa, cargar todas las asignaciones de la empresa
                cargarAsignacionesPorEmpresa(empresa_id);
            } else {
                $('#tablaAsignaciones').html('<tr><td colspan="10" class="text-center">Seleccione una empresa para ver las asignaciones</td></tr>');
            }
        }
    });
    
    // NUEVO: Cargar usuarios para el filtro
    function cargarUsuariosParaFiltro() {
        $.get('perfiles_usuarios_ajax.php', {
            accion: 'obtener_todos_usuarios'
        }, function(res) {
            if (res && res.length > 0) {
                $.each(res, function(i, usuario) {
                    var texto = usuario.usuario_nombre;
                    if (usuario.email) {
                        texto += ' (' + usuario.email + ')';
                    }
                    
                    $('#selectUsuarioFiltro').append($('<option>', {
                        value: usuario.usuario_id,
                        text: texto
                    }));
                });
            }
        }, 'json').fail(function(xhr, status, error) {
            console.error('Error al cargar usuarios para filtro:', error);
        });
    }
    
    // NUEVO: Filtrar por usuario
    $('#btnFiltrarUsuario').click(function() {
        var usuario_id = $('#selectUsuarioFiltro').val();
        
        if (!usuario_id) {
            Swal.fire({
                icon: 'warning',
                title: 'Seleccione un usuario',
                text: 'Debe seleccionar un usuario para filtrar'
            });
            return;
        }
        
        if (usuario_id === 'todos') {
            // Ver todas las asignaciones de todos los usuarios
            cargarTodasAsignacionesUsuarios();
            usuarioFiltroActivo = true;
            usuarioFiltroId = null;
            usuarioFiltroNombre = 'Todos los usuarios';
        } else {
            // Filtrar por usuario específico
            var usuario_nombre = $('#selectUsuarioFiltro option:selected').text();
            cargarAsignacionesPorUsuario(usuario_id);
            usuarioFiltroActivo = true;
            usuarioFiltroId = usuario_id;
            usuarioFiltroNombre = usuario_nombre;
        }
        
        actualizarInfoUsuarioFiltro();
    });
    
    // NUEVO: Cargar todas las asignaciones de un usuario específico
    function cargarAsignacionesPorUsuario(usuario_id) {
        $.get('perfiles_usuarios_ajax.php', {
            accion: 'obtener_asignaciones_por_usuario',
            usuario_id: usuario_id
        }, function(res) {
            var tbody = $('#tablaAsignaciones');
            tbody.empty();
            
            if (res && res.length > 0) {
                // Agrupar por empresa y módulo para mejor visualización
                var asignacionesAgrupadas = {};
                
                $.each(res, function(i, asignacion) {
                    var key = asignacion.empresa_id + '_' + asignacion.modulo_id;
                    if (!asignacionesAgrupadas[key]) {
                        asignacionesAgrupadas[key] = {
                            empresa: asignacion.empresa,
                            modulo: asignacion.modulo,
                            asignaciones: []
                        };
                    }
                    asignacionesAgrupadas[key].asignaciones.push(asignacion);
                });
                
                // Mostrar en la tabla
                $.each(asignacionesAgrupadas, function(key, grupo) {
                    // Fila de encabezado de empresa y módulo
                    tbody.append(`
                        <tr class="table-info">
                            <td colspan="10" class="fw-bold">
                                <i class="fas fa-building"></i> ${grupo.empresa} | 
                                <i class="fas fa-cube"></i> ${grupo.modulo}
                            </td>
                        </tr>
                    `);
                    
                    // Asignaciones de este grupo
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
                                <td>${asignacion.empresa || ''}</td>
                                <td>${asignacion.modulo || ''}</td>
                                <td>${asignacion.empresa_perfil_nombre}</td>
                                <td>${asignacion.fecha_inicio_formateada}</td>
                                <td>${asignacion.fecha_fin_formateada}</td>
                                <td>${estado}</td>
                                <td>${acciones}</td>
                            </tr>
                        `);
                    });
                });
                
                // Agregar eventos a los botones
                $(document).off('click', '.btn-editar-asignacion').on('click', '.btn-editar-asignacion', function() {
                    var id = $(this).data('id');
                    abrirModalAsignacion(id);
                });
                
                $(document).off('click', '.btn-eliminar-asignacion').on('click', '.btn-eliminar-asignacion', function() {
                    var id = $(this).data('id');
                    eliminarAsignacion(id);
                });
                
            } else {
                tbody.append('<tr><td colspan="10" class="text-center">El usuario no tiene asignaciones de perfiles</td></tr>');
            }
        }, 'json').fail(function(xhr, status, error) {
            console.error('Error al cargar asignaciones por usuario:', error);
            $('#tablaAsignaciones').html('<tr><td colspan="10" class="text-center">Error al cargar las asignaciones</td></tr>');
        });
    }
    
    // NUEVO: Cargar todas las asignaciones de todos los usuarios
    function cargarTodasAsignacionesUsuarios() {
        $.get('perfiles_usuarios_ajax.php', {
            accion: 'obtener_todas_asignaciones'
        }, function(res) {
            var tbody = $('#tablaAsignaciones');
            tbody.empty();
            
            if (res && res.length > 0) {
                // Agrupar por usuario para mejor visualización
                var asignacionesPorUsuario = {};
                
                $.each(res, function(i, asignacion) {
                    if (!asignacionesPorUsuario[asignacion.usuario_id]) {
                        asignacionesPorUsuario[asignacion.usuario_id] = {
                            usuario_nombre: asignacion.usuario_nombre,
                            usuario: asignacion.usuario,
                            email: asignacion.email,
                            asignaciones: []
                        };
                    }
                    asignacionesPorUsuario[asignacion.usuario_id].asignaciones.push(asignacion);
                });
                
                // Mostrar en la tabla
                $.each(asignacionesPorUsuario, function(usuario_id, usuario) {
                    // Fila de encabezado del usuario
                    tbody.append(`
                        <tr class="table-primary">
                            <td colspan="10" class="fw-bold">
                                <i class="fas fa-user"></i> ${usuario.usuario_nombre} (${usuario.email})
                            </td>
                        </tr>
                    `);
                    
                    // Asignaciones de este usuario
                    $.each(usuario.asignaciones, function(i, asignacion) {
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
                                <td>${asignacion.empresa || ''}</td>
                                <td>${asignacion.modulo || ''}</td>
                                <td>${asignacion.empresa_perfil_nombre}</td>
                                <td>${asignacion.fecha_inicio_formateada}</td>
                                <td>${asignacion.fecha_fin_formateada}</td>
                                <td>${estado}</td>
                                <td>${acciones}</td>
                            </tr>
                        `);
                    });
                });
                
                // Agregar eventos a los botones
                $(document).off('click', '.btn-editar-asignacion').on('click', '.btn-editar-asignacion', function() {
                    var id = $(this).data('id');
                    abrirModalAsignacion(id);
                });
                
                $(document).off('click', '.btn-eliminar-asignacion').on('click', '.btn-eliminar-asignacion', function() {
                    var id = $(this).data('id');
                    eliminarAsignacion(id);
                });
                
            } else {
                tbody.append('<tr><td colspan="10" class="text-center">No hay asignaciones de perfiles</td></tr>');
            }
        }, 'json').fail(function(xhr, status, error) {
            console.error('Error al cargar todas las asignaciones:', error);
            $('#tablaAsignaciones').html('<tr><td colspan="10" class="text-center">Error al cargar las asignaciones</td></tr>');
        });
    }
    
    // NUEVO: Actualizar información del filtro por usuario
    function actualizarInfoUsuarioFiltro() {
        if (usuarioFiltroActivo) {
            var texto = '';
            if (usuarioFiltroId) {
                texto = `Mostrando todas las asignaciones del usuario: <strong>${usuarioFiltroNombre}</strong>`;
            } else {
                texto = `Mostrando todas las asignaciones de <strong>${usuarioFiltroNombre}</strong>`;
            }
            
            $('#textoUsuarioFiltro').html(texto);
            $('#infoUsuarioFiltro').removeClass('d-none');
            $('#infoSeleccion').addClass('d-none');
            $('#tituloTabla').html('Asignaciones por Usuario');
            
            // Deshabilitar otros filtros
            $('#selectEmpresa, #selectModulo, #selectPerfil').prop('disabled', true);
        } else {
            $('#infoUsuarioFiltro').addClass('d-none');
            $('#selectEmpresa, #selectModulo, #selectPerfil').prop('disabled', false);
            $('#tituloTabla').html('Usuarios Asignados');
        }
    }
    
    // NUEVO: Limpiar filtro por usuario
    $('#btnLimpiarFiltroUsuario').click(function() {
        usuarioFiltroActivo = false;
        usuarioFiltroId = null;
        usuarioFiltroNombre = '';
        $('#selectUsuarioFiltro').val('');
        
        // Limpiar tabla y recargar según empresa seleccionada
        var empresa_id = $('#selectEmpresa').val();
        if (empresa_id) {
            cargarAsignacionesPorEmpresa(empresa_id);
        } else {
            $('#tablaAsignaciones').html('<tr><td colspan="10" class="text-center">Seleccione una empresa para ver las asignaciones</td></tr>');
        }
        
        actualizarInfoUsuarioFiltro();
        actualizarInfoSeleccion();
    });
    
    // Función para actualizar la información de selección
    function actualizarInfoSeleccion() {
        if (!usuarioFiltroActivo) {
            var empresa = $('#selectEmpresa option:selected').text();
            var modulo = $('#selectModulo option:selected').text();
            var perfil = $('#selectPerfil option:selected').text();
            
            if (empresa && empresa !== "Seleccione una empresa") {
                var texto = `Empresa: <strong>${empresa}</strong>`;
                
                if (modulo && modulo !== "Seleccione un módulo") {
                    texto += ` | Módulo: <strong>${modulo}</strong>`;
                    
                    if (perfil && perfil !== "Seleccione un perfil") {
                        texto += ` | Perfil: <strong>${perfil}</strong>`;
                    } else {
                        texto += ` | <em>Mostrando todos los perfiles del módulo</em>`;
                    }
                } else {
                    texto += ` | <em>Mostrando todos los módulos</em>`;
                }
                
                $('#textoInfo').html(texto);
                $('#infoSeleccion').removeClass('d-none');
            } else {
                $('#infoSeleccion').addClass('d-none');
            }
        }
    }
    
    // (Las funciones restantes se mantienen igual, pero asegúrate de que respeten el filtro de usuario)
    // Función para cargar asignaciones por empresa (todos los módulos)
    function cargarAsignacionesPorEmpresa(empresa_id) {
        if (usuarioFiltroActivo) return;
        
        $.get('perfiles_usuarios_ajax.php', {
            accion: 'obtener_asignaciones_por_empresa',
            empresa_id: empresa_id
        }, function(res) {
            var tbody = $('#tablaAsignaciones');
            tbody.empty();
            
            if (res && res.length > 0) {
                // Agrupar por módulo y perfil para mejor visualización
                var asignacionesAgrupadas = {};
                
                $.each(res, function(i, asignacion) {
                    var key = asignacion.modulo_id + '_' + asignacion.empresa_perfil_id;
                    if (!asignacionesAgrupadas[key]) {
                        asignacionesAgrupadas[key] = {
                            modulo: asignacion.modulo,
                            perfil_nombre: asignacion.empresa_perfil_nombre,
                            asignaciones: []
                        };
                    }
                    asignacionesAgrupadas[key].asignaciones.push(asignacion);
                });
                
                // Mostrar en la tabla
                $.each(asignacionesAgrupadas, function(key, grupo) {
                    // Fila de encabezado del módulo y perfil
                    tbody.append(`
                        <tr class="table-primary">
                            <td colspan="10" class="fw-bold">
                                <i class="fas fa-cube"></i> ${grupo.modulo} | 
                                <i class="fas fa-users"></i> ${grupo.perfil_nombre}
                            </td>
                        </tr>
                    `);
                    
                    // Asignaciones de este grupo
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
                                <td>${asignacion.empresa || ''}</td>
                                <td>${asignacion.modulo || ''}</td>
                                <td>${asignacion.empresa_perfil_nombre}</td>
                                <td>${asignacion.fecha_inicio_formateada}</td>
                                <td>${asignacion.fecha_fin_formateada}</td>
                                <td>${estado}</td>
                                <td>${acciones}</td>
                            </tr>
                        `);
                    });
                });
                
                // Agregar eventos a los botones
                $(document).off('click', '.btn-editar-asignacion').on('click', '.btn-editar-asignacion', function() {
                    var id = $(this).data('id');
                    abrirModalAsignacion(id);
                });
                
                $(document).off('click', '.btn-eliminar-asignacion').on('click', '.btn-eliminar-asignacion', function() {
                    var id = $(this).data('id');
                    eliminarAsignacion(id);
                });
                
            } else {
                tbody.append('<tr><td colspan="10" class="text-center">No hay asignaciones para esta empresa</td></tr>');
            }
        }, 'json').fail(function(xhr, status, error) {
            console.error('Error al cargar asignaciones por empresa:', error);
            $('#tablaAsignaciones').html('<tr><td colspan="10" class="text-center">Error al cargar las asignaciones</td></tr>');
        });
    }
    
    // Función para cargar asignaciones por módulo y empresa
    function cargarAsignacionesPorModuloEmpresa(empresa_id, modulo_id) {
        if (usuarioFiltroActivo) return;
        
        $.get('perfiles_usuarios_ajax.php', {
            accion: 'obtener_asignaciones_por_modulo_empresa',
            empresa_id: empresa_id,
            modulo_id: modulo_id
        }, function(res) {
            var tbody = $('#tablaAsignaciones');
            tbody.empty();
            
            if (res && res.length > 0) {
                // Agrupar por perfil para mejor visualización
                var asignacionesPorPerfil = {};
                
                $.each(res, function(i, asignacion) {
                    if (!asignacionesPorPerfil[asignacion.empresa_perfil_id]) {
                        asignacionesPorPerfil[asignacion.empresa_perfil_id] = {
                            perfil_nombre: asignacion.empresa_perfil_nombre,
                            asignaciones: []
                        };
                    }
                    asignacionesPorPerfil[asignacion.empresa_perfil_id].asignaciones.push(asignacion);
                });
                
                // Mostrar en la tabla
                $.each(asignacionesPorPerfil, function(empresa_perfil_id, grupo) {
                    // Fila de encabezado del perfil
                    tbody.append(`
                        <tr class="table-primary">
                            <td colspan="10" class="fw-bold">
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
                                <td>${asignacion.empresa || ''}</td>
                                <td>${asignacion.modulo || ''}</td>
                                <td>${asignacion.empresa_perfil_nombre}</td>
                                <td>${asignacion.fecha_inicio_formateada}</td>
                                <td>${asignacion.fecha_fin_formateada}</td>
                                <td>${estado}</td>
                                <td>${acciones}</td>
                            </tr>
                        `);
                    });
                });
                
                // Agregar eventos a los botones
                $(document).off('click', '.btn-editar-asignacion').on('click', '.btn-editar-asignacion', function() {
                    var id = $(this).data('id');
                    abrirModalAsignacion(id);
                });
                
                $(document).off('click', '.btn-eliminar-asignacion').on('click', '.btn-eliminar-asignacion', function() {
                    var id = $(this).data('id');
                    eliminarAsignacion(id);
                });
                
            } else {
                tbody.append('<tr><td colspan="10" class="text-center">No hay asignaciones para este módulo</td></tr>');
            }
        }, 'json').fail(function(xhr, status, error) {
            console.error('Error al cargar asignaciones por módulo:', error);
            $('#tablaAsignaciones').html('<tr><td colspan="10" class="text-center">Error al cargar las asignaciones</td></tr>');
        });
    }
    
    // Función para cargar asignaciones por perfil específico
    function cargarAsignacionesUsuarioPerfil(empresa_perfil_id) {
        if (usuarioFiltroActivo) return;
        
        $.get('perfiles_usuarios_ajax.php', {
            accion: 'obtener_asignaciones_usuario_perfil',
            empresa_perfil_id: empresa_perfil_id
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
                            <td>${asignacion.empresa || ''}</td>
                            <td>${asignacion.modulo || ''}</td>
                            <td>${asignacion.empresa_perfil_nombre}</td>
                            <td>${asignacion.fecha_inicio_formateada}</td>
                            <td>${asignacion.fecha_fin_formateada}</td>
                            <td>${estado}</td>
                            <td>${acciones}</td>
                        </tr>
                    `);
                });
                
                // Agregar eventos a los botones
                $(document).off('click', '.btn-editar-asignacion').on('click', '.btn-editar-asignacion', function() {
                    var id = $(this).data('id');
                    abrirModalAsignacion(id);
                });
                
                $(document).off('click', '.btn-eliminar-asignacion').on('click', '.btn-eliminar-asignacion', function() {
                    var id = $(this).data('id');
                    eliminarAsignacion(id);
                });
                
            } else {
                tbody.append('<tr><td colspan="10" class="text-center">No hay asignaciones para este perfil</td></tr>');
            }
        }, 'json').fail(function(xhr, status, error) {
            console.error('Error al cargar asignaciones por perfil:', error);
            $('#tablaAsignaciones').html('<tr><td colspan="10" class="text-center">Error al cargar las asignaciones</td></tr>');
        });
    }
    
    // Función para cargar las empresas
    function cargarEmpresas() {
        $.get('perfiles_usuarios_ajax.php', {accion: 'obtener_empresas'}, function(res) {
            if (res && res.length > 0) {
                $('#selectEmpresa').empty().append('<option value="">Seleccione una empresa</option>');
                $.each(res, function(i, empresa) {
                    $('#selectEmpresa').append($('<option>', {
                        value: empresa.empresa_id,
                        text: empresa.empresa
                    }));
                });
                
                // Si hay empresa de sesión, seleccionarla
                if (empresaIdSesion) {
                    $('#selectEmpresa').val(empresaIdSesion);
                }
            }
        }, 'json').fail(function(xhr, status, error) {
            console.error('Error al cargar empresas:', error);
        });
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
        }, 'json').fail(function(xhr, status, error) {
            console.error('Error al cargar módulos:', error);
        });
    }
    
    // Función para cargar perfiles por módulo y empresa
    function cargarPerfilesPorModuloEmpresa(modulo_id, empresa_id) {
        $.get('perfiles_usuarios_ajax.php', {
            accion: 'obtener_perfiles_por_modulo_empresa', 
            modulo_id: modulo_id,
            empresa_id: empresa_id
        }, function(res) {
            $('#selectPerfil').empty().append('<option value="">Seleccione un perfil</option>');
            
            if (res && res.length > 0) {
                $.each(res, function(i, perfil) {
                    var texto = perfil.empresa_perfil_nombre;
                    if (perfil.perfil_base_nombre) {
                        texto += ' (' + perfil.perfil_base_nombre + ')';
                    }
                    
                    $('#selectPerfil').append($('<option>', {
                        value: perfil.empresa_perfil_id,
                        text: texto
                    }));
                });
            } else {
                $('#selectPerfil').append('<option value="">No hay perfiles para este módulo</option>');
            }
        }, 'json').fail(function(xhr, status, error) {
            console.error('Error al cargar perfiles:', error);
            $('#selectPerfil').append('<option value="">Error al cargar perfiles</option>');
        });
    }
    
    // Abrir modal para agregar asignación
    $('#btnAgregarAsignacion').click(function() {
        // Si hay filtro de usuario activo, usar ese usuario por defecto
        if (usuarioFiltroActivo && usuarioFiltroId) {
            // Pre-seleccionar el usuario del filtro
            abrirModalAsignacionConUsuario(usuarioFiltroId);
        } else {
            var empresa_id = $('#selectEmpresa').val();
            if (!empresa_id) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Seleccione una empresa',
                    text: 'Debe seleccionar una empresa primero'
                });
                return;
            }
            
            var modulo_id = $('#selectModulo').val();
            if (!modulo_id) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Seleccione un módulo',
                    text: 'Debe seleccionar un módulo primero'
                });
                return;
            }
            
            var empresa_perfil_id = $('#selectPerfil').val();
            if (!empresa_perfil_id) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Seleccione un perfil',
                    text: 'Debe seleccionar un perfil para agregar una asignación'
                });
                return;
            }
            
            abrirModalAsignacion();
        }
    });
    
    // NUEVA: Abrir modal con usuario pre-seleccionado
    function abrirModalAsignacionConUsuario(usuario_id) {
        var empresa_id = $('#selectEmpresa').val();
        var modulo_id = $('#selectModulo').val();
        var empresa_perfil_id = $('#selectPerfil').val();
        
        if (!empresa_id || !modulo_id || !empresa_perfil_id) {
            Swal.fire({
                icon: 'warning',
                title: 'Complete los filtros',
                text: 'Debe seleccionar empresa, módulo y perfil antes de agregar una asignación'
            });
            return;
        }
        
        abrirModalAsignacion();
        
        // Una vez abierto el modal, seleccionar el usuario
        setTimeout(function() {
            $('#selectUsuario').val(usuario_id);
        }, 500);
    }
    
    // Función para abrir el modal de asignación
    function abrirModalAsignacion(usuario_perfil_id = null) {
        $('#modalAsignacionUsuarioTitle').text(usuario_perfil_id ? 'Editar Asignación' : 'Agregar Asignación');
        $('#formAsignacionUsuario')[0].reset();
        $('#usuario_perfil_id').val(usuario_perfil_id || '');
        $('#empresa_perfil_id').val($('#selectPerfil').val());
        $('#empresa_id_modal').val($('#selectEmpresa').val());
        $('#validarSolapamiento').prop('checked', true);
        
        // Cargar usuarios
        cargarTodosLosUsuarios();
        
        // Si es edición, cargar los datos
        if (usuario_perfil_id) {
            cargarDatosAsignacion(usuario_perfil_id);
        } else {
            $('#selectUsuario').prop('disabled', false);
        }
        
        $('#modalAsignacionUsuario').modal('show');
    }
    
    // Cargar todos los usuarios
    function cargarTodosLosUsuarios() {
        $('#selectUsuario').html('<option value="">Cargando usuarios...</option>');
        
        $.get('perfiles_usuarios_ajax.php', {
            accion: 'obtener_todos_usuarios'
        }, function(res) {
            if (res && res.length > 0) {
                $('#selectUsuario').empty().append('<option value="">Seleccione un usuario</option>');
                $.each(res, function(i, usuario) {
                    var texto = usuario.usuario_nombre;
                    if (usuario.email) {
                        texto += ' (' + usuario.email + ')';
                    } else if (usuario.usuario) {
                        texto += ' (' + usuario.usuario + ')';
                    }
                    
                    $('#selectUsuario').append($('<option>', {
                        value: usuario.usuario_id,
                        text: texto
                    }));
                });
            } else {
                $('#selectUsuario').empty().append('<option value="">No hay usuarios registrados</option>');
            }
        }, 'json').fail(function(xhr, status, error) {
            console.error('Error al cargar todos los usuarios:', error);
            $('#selectUsuario').empty().append('<option value="">Error al cargar usuarios</option>');
        });
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
    
    // Guardar asignación
    $('#btnGuardarAsignacion').click(function() {
        // Validar que se haya seleccionado un perfil
        var empresa_perfil_id = $('#empresa_perfil_id').val();
        if (!empresa_perfil_id) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se ha seleccionado un perfil válido'
            });
            return;
        }
        
        var empresa_id = $('#empresa_id_modal').val();
        if (!empresa_id) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se ha seleccionado una empresa válida'
            });
            return;
        }
        
        var formData = new FormData($('#formAsignacionUsuario')[0]);
        
        // Si el select está deshabilitado (modo edición), agregar manualmente el usuario_id
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
                    
                    // Recargar según el filtro activo
                    if (usuarioFiltroActivo) {
                        if (usuarioFiltroId) {
                            cargarAsignacionesPorUsuario(usuarioFiltroId);
                        } else {
                            cargarTodasAsignacionesUsuarios();
                        }
                    } else {
                        var empresa_perfil_id = $('#selectPerfil').val();
                        if (empresa_perfil_id) {
                            cargarAsignacionesUsuarioPerfil(empresa_perfil_id);
                        } else {
                            var modulo_id = $('#selectModulo').val();
                            var empresa_id = $('#selectEmpresa').val();
                            if (modulo_id && empresa_id) {
                                cargarAsignacionesPorModuloEmpresa(empresa_id, modulo_id);
                            } else if (empresa_id) {
                                cargarAsignacionesPorEmpresa(empresa_id);
                            }
                        }
                    }
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
                        
                        // Recargar según el filtro activo
                        if (usuarioFiltroActivo) {
                            if (usuarioFiltroId) {
                                cargarAsignacionesPorUsuario(usuarioFiltroId);
                            } else {
                                cargarTodasAsignacionesUsuarios();
                            }
                        } else {
                            var empresa_perfil_id = $('#selectPerfil').val();
                            if (empresa_perfil_id) {
                                cargarAsignacionesUsuarioPerfil(empresa_perfil_id);
                            } else {
                                var modulo_id = $('#selectModulo').val();
                                var empresa_id = $('#selectEmpresa').val();
                                if (modulo_id && empresa_id) {
                                    cargarAsignacionesPorModuloEmpresa(empresa_id, modulo_id);
                                } else if (empresa_id) {
                                    cargarAsignacionesPorEmpresa(empresa_id);
                                }
                            }
                        }
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
    
    // Inicializar cargando módulos
    cargarModulos();
});
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>