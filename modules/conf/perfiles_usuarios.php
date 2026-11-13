<?php
// Configuración de la página
$pageTitle = "Gestión de Perfiles";
$currentPage = 'perfiles';
$modudo_idx = 1;
$pagina_idx = 26; // conf__paginas
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

?>
<main class="app-main">
    <?php ui_encabezado('Usuarios por perfil', 'bi bi-person-check', 'Qué perfil tiene cada usuario en cada empresa y módulo.', ['Empresas y accesos']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="content-wrapper">
                <section class="content">
                    <div class="container-fluid">                      
                        <div class="row">
                            <div class="col-md-3 col-xl-2">
                                <div class="card">
                                    <div class="card-header py-2">
                                        <h3 class="card-title fs-6">Filtros</h3>
                                    </div>
                                    <div class="card-body p-2 small">
                                        <!-- Filtros combinables: todos opcionales, se acotan entre sí -->
                                        <div class="mb-2">
                                            <label for="selectEmpresa" class="form-label mb-0">Empresa:</label>
                                            <select class="form-select form-select-sm" id="selectEmpresa">
                                                <option value="">Todas las empresas</option>
                                            </select>
                                        </div>
                                        <div class="mb-2">
                                            <label for="selectModulo" class="form-label mb-0">Módulo:</label>
                                            <select class="form-select form-select-sm" id="selectModulo">
                                                <option value="">Todos los módulos</option>
                                            </select>
                                        </div>
                                        <div class="mb-2">
                                            <label for="selectPerfil" class="form-label mb-0">Perfil:</label>
                                            <select class="form-select form-select-sm" id="selectPerfil">
                                                <option value="">Todos los perfiles</option>
                                            </select>
                                        </div>
                                        <div class="mb-2">
                                            <label for="selectUsuarioFiltro" class="form-label mb-0">Usuario:</label>
                                            <select class="form-select form-select-sm" id="selectUsuarioFiltro">
                                                <option value="">Todos los usuarios</option>
                                            </select>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="btnLimpiarFiltros">
                                            <i class="fas fa-eraser"></i> Limpiar filtros
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-9 col-xl-10">
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
                                            <span id="textoInfo"><em>Mostrando todas las asignaciones</em></span>
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
                                                        <td colspan="10" class="text-center text-muted">Cargando...</td>
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

<!-- Modal para vista detallada de permisos -->
<div class="modal fade" id="modalVistaDetallada" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalVistaDetalladaTitle">Vista Detallada de Permisos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="buscarPermisos" placeholder="Buscar en páginas o funciones...">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="btn-group" role="group">
                            <input type="radio" class="btn-check" name="filtroEstado" id="filtroTodos" autocomplete="off" checked>
                            <label class="btn btn-outline-primary" for="filtroTodos">Todos</label>
                            
                            <input type="radio" class="btn-check" name="filtroEstado" id="filtroAsignadas" autocomplete="off">
                            <label class="btn btn-outline-success" for="filtroAsignadas">Asignadas</label>
                            
                            <input type="radio" class="btn-check" name="filtroEstado" id="filtroNoAsignadas" autocomplete="off">
                            <label class="btn btn-outline-danger" for="filtroNoAsignadas">No Asignadas</label>
                        </div>
                    </div>
                </div>
                
                <!-- Resumen -->
                <div id="resumenPermisos" class="mb-4"></div>
                
                <!-- Vista detallada -->
                <div id="contenidoVistaDetallada" class="accordion"></div>
                
                <!-- Mensaje cuando no hay datos -->
                <div id="mensajeSinPermisos" class="text-center d-none">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No se encontraron permisos asignados.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="btnExportarPermisos">
                    <i class="fas fa-download"></i> Exportar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Variables para vista detallada
    var usuarioDetalleId = null;
    var usuarioDetalleNombre = '';
    var datosVistaDetallada = [];

    // Filtros combinables: empresa, módulo, perfil y usuario son opcionales y se acotan
    // entre sí en cualquier orden (cada lista se calcula con los otros filtros elegidos).
    var nroPedidoOpciones = 0;
    var nroPedidoTabla = 0;
    var perfilesInfo = {};   // empresa_perfil_id => {empresa_id, modulo_id}

    actualizarFiltros();
    recargarTabla();

    $('#selectEmpresa, #selectModulo, #selectUsuarioFiltro').change(alCambiarFiltro);

    // El perfil es de una empresa y un módulo: al elegirlo se completan los dos
    $('#selectPerfil').change(function() {
        var info = perfilesInfo[$(this).val()];
        if (info) {
            $('#selectEmpresa').val(String(info.empresa_id));
            $('#selectModulo').val(String(info.modulo_id));
        }
        alCambiarFiltro();
    });

    $('#btnLimpiarFiltros').click(function() {
        $('#selectEmpresa, #selectModulo, #selectPerfil, #selectUsuarioFiltro').val('');
        alCambiarFiltro();
    });

    function alCambiarFiltro() {
        actualizarInfoSeleccion();
        actualizarFiltros();
        recargarTabla();
    }

    function filtrosActuales() {
        return {
            empresa_id: $('#selectEmpresa').val() || '',
            modulo_id: $('#selectModulo').val() || '',
            empresa_perfil_id: $('#selectPerfil').val() || '',
            usuario_id: $('#selectUsuarioFiltro').val() || ''
        };
    }

    function esc(valor) {
        return $('<div>').text(valor == null ? '' : String(valor)).html();
    }

    // Rellena un select manteniendo la opción elegida si sigue disponible
    function llenarSelect(selector, textoVacio, items, campoValor, armarTexto, seleccionado) {
        var $select = $(selector).empty().append($('<option>', {value: '', text: textoVacio}));
        $.each(items || [], function(i, item) {
            $select.append($('<option>', {value: item[campoValor], text: armarTexto(item)}));
        });
        var sigue = seleccionado && $select.find('option').filter(function() {
            return this.value === String(seleccionado);
        }).length > 0;
        $select.val(sigue ? String(seleccionado) : '');
    }

    function actualizarFiltros() {
        var f = filtrosActuales();
        var nro = ++nroPedidoOpciones;
        $.get('perfiles_usuarios_ajax.php', $.extend({accion: 'opciones_filtros'}, f), function(res) {
            // Respuesta vieja: los filtros cambiaron mientras tanto
            if (nro !== nroPedidoOpciones || !res) {
                return;
            }
            perfilesInfo = {};
            $.each(res.perfiles || [], function(i, p) {
                perfilesInfo[p.empresa_perfil_id] = {empresa_id: p.empresa_id, modulo_id: p.modulo_id};
            });

            llenarSelect('#selectEmpresa', 'Todas las empresas', res.empresas, 'empresa_id',
                function(e) { return e.empresa; }, f.empresa_id);
            llenarSelect('#selectModulo', 'Todos los módulos', res.modulos, 'modulo_id',
                function(m) { return m.modulo; }, f.modulo_id);
            llenarSelect('#selectPerfil', 'Todos los perfiles', res.perfiles, 'empresa_perfil_id',
                function(p) {
                    var texto = p.empresa_perfil_nombre;
                    if (p.perfil_base_nombre) {
                        texto += ' (' + p.perfil_base_nombre + ')';
                    }
                    // Sin empresa elegida, aclarar de qué empresa es cada perfil
                    if (!f.empresa_id) {
                        texto += ' - ' + p.empresa;
                    }
                    return texto;
                }, f.empresa_perfil_id);
            llenarSelect('#selectUsuarioFiltro', 'Todos los usuarios', res.usuarios, 'usuario_id',
                function(u) { return u.usuario_nombre + (u.email ? ' (' + u.email + ')' : ''); }, f.usuario_id);

            actualizarInfoSeleccion();
        }, 'json').fail(function(xhr, status, error) {
            console.error('Error al cargar los filtros:', error);
        });
    }

    // Texto con los filtros aplicados
    function actualizarInfoSeleccion() {
        var partes = [];
        var etiquetas = [
            ['#selectEmpresa', 'Empresa'],
            ['#selectModulo', 'Módulo'],
            ['#selectPerfil', 'Perfil'],
            ['#selectUsuarioFiltro', 'Usuario']
        ];
        $.each(etiquetas, function(i, par) {
            if ($(par[0]).val()) {
                partes.push(par[1] + ': <strong>' + esc($(par[0]).find('option:selected').text()) + '</strong>');
            }
        });
        $('#textoInfo').html(partes.length ? partes.join(' | ') : '<em>Mostrando todas las asignaciones</em>');
        $('#infoSeleccion').removeClass('d-none');
    }

    // Tabla de asignaciones según los filtros elegidos
    function recargarTabla() {
        var f = filtrosActuales();
        var nro = ++nroPedidoTabla;
        var tbody = $('#tablaAsignaciones');
        tbody.html('<tr><td colspan="10" class="text-center text-muted">Cargando...</td></tr>');

        $.get('perfiles_usuarios_ajax.php', $.extend({accion: 'listar_asignaciones'}, f), function(res) {
            if (nro !== nroPedidoTabla) {
                return;
            }
            tbody.empty();
            if (!res || res.length === 0) {
                tbody.append('<tr><td colspan="10" class="text-center">No hay asignaciones para los filtros elegidos</td></tr>');
                return;
            }

            // Agrupar por empresa / módulo / perfil, salvo que ya se haya elegido un perfil
            var agrupar = !f.empresa_perfil_id;
            var grupoActual = null;
            var hoy = new Date();

            $.each(res, function(i, a) {
                var grupo = a.empresa_id + '_' + a.modulo_id + '_' + a.empresa_perfil_id;
                if (agrupar && grupo !== grupoActual) {
                    grupoActual = grupo;
                    tbody.append(`
                        <tr class="table-primary">
                            <td colspan="10" class="fw-bold">
                                <i class="fas fa-building"></i> ${esc(a.empresa)} |
                                <i class="fas fa-cube"></i> ${esc(a.modulo)} |
                                <i class="fas fa-users"></i> ${esc(a.empresa_perfil_nombre)}
                            </td>
                        </tr>
                    `);
                }

                var fechaFin = new Date(a.fecha_fin_formateada.split('/').reverse().join('-'));
                var estado = hoy <= fechaFin ?
                    '<span class="badge bg-success">Vigente</span>' :
                    '<span class="badge bg-secondary">Expirada</span>';

                tbody.append(`
                    <tr>
                        <td>${esc(a.usuario)}</td>
                        <td>${esc(a.usuario_nombre)}</td>
                        <td>${esc(a.email)}</td>
                        <td>${esc(a.empresa)}</td>
                        <td>${esc(a.modulo)}</td>
                        <td>${esc(a.empresa_perfil_nombre)}</td>
                        <td>${esc(a.fecha_inicio_formateada)}</td>
                        <td>${esc(a.fecha_fin_formateada)}</td>
                        <td>${estado}</td>
                        <td>
                            <button class="btn btn-sm btn-info btn-ver-detalle"
                                    data-usuario-id="${esc(a.usuario_id)}"
                                    data-usuario-nombre="${esc(a.usuario_nombre)}">
                                <i class="fas fa-eye"></i> Detalle
                            </button>
                            <button class="btn btn-sm btn-warning btn-editar-asignacion" data-id="${esc(a.usuario_perfil_id)}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger btn-eliminar-asignacion" data-id="${esc(a.usuario_perfil_id)}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });
        }, 'json').fail(function(xhr, status, error) {
            if (nro !== nroPedidoTabla) {
                return;
            }
            console.error('Error al cargar asignaciones:', error);
            tbody.html('<tr><td colspan="10" class="text-center">Error al cargar las asignaciones</td></tr>');
        });
    }

    // Botones de cada fila
    $('#tablaAsignaciones')
        .on('click', '.btn-ver-detalle', function() {
            mostrarVistaDetallada($(this).data('usuario-id'), $(this).data('usuario-nombre'));
        })
        .on('click', '.btn-editar-asignacion', function() {
            abrirModalAsignacion($(this).data('id'));
        })
        .on('click', '.btn-eliminar-asignacion', function() {
            eliminarAsignacion($(this).data('id'));
        });

    // Abrir modal para agregar asignación: el perfil define empresa y módulo
    $('#btnAgregarAsignacion').click(function() {
        if (!$('#selectPerfil').val()) {
            Swal.fire({
                icon: 'warning',
                title: 'Seleccione un perfil',
                text: 'Elija el perfil al que se asigna el usuario (la empresa y el módulo se completan solos)'
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
        $('#empresa_perfil_id').val($('#selectPerfil').val());
        $('#empresa_id_modal').val($('#selectEmpresa').val());
        $('#validarSolapamiento').prop('checked', true);
        
        // Cargar usuarios y después los datos (edición) o el usuario del filtro (alta)
        if (usuario_perfil_id) {
            cargarTodosLosUsuarios(function() {
                cargarDatosAsignacion(usuario_perfil_id);
            });
        } else {
            $('#selectUsuario').prop('disabled', false);
            cargarTodosLosUsuarios(function() {
                var usuarioFiltro = $('#selectUsuarioFiltro').val();
                if (usuarioFiltro) {
                    $('#selectUsuario').val(usuarioFiltro);
                }
            });
        }

        $('#modalAsignacionUsuario').modal('show');
    }
    
    // Cargar todos los usuarios
    function cargarTodosLosUsuarios(alTerminar) {
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
            if (alTerminar) {
                alTerminar();
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
                // El perfil y la empresa salen del registro, no de los filtros
                $('#empresa_perfil_id').val(asignacion.empresa_perfil_id);
                $('#empresa_id_modal').val(asignacion.empresa_id);

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
                    
                    // Recargar la tabla y los filtros (puede cambiar la lista de usuarios)
                    recargarTabla();
                    actualizarFiltros();
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
                        
                        // Recargar la tabla y los filtros (puede cambiar la lista de usuarios)
                        recargarTabla();
                        actualizarFiltros();
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
    
    // FUNCIONES PARA VISTA DETALLADA
    
    // Función para mostrar vista detallada
    function mostrarVistaDetallada(usuario_id, usuario_nombre) {
        usuarioDetalleId = usuario_id;
        usuarioDetalleNombre = usuario_nombre;
        
        $('#modalVistaDetalladaTitle').html(`Permisos detallados: <strong>${usuario_nombre}</strong>`);
        $('#contenidoVistaDetallada').empty();
        $('#resumenPermisos').empty();
        $('#mensajeSinPermisos').addClass('d-none');
        
        // Mostrar loading
        $('#contenidoVistaDetallada').html(`
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="mt-2">Cargando permisos detallados...</p>
            </div>
        `);
        
        $('#modalVistaDetallada').modal('show');
        
        // Cargar datos
        cargarVistaDetalladaPermisos(usuario_id);
    }
    
    // Cargar vista detallada de permisos
    function cargarVistaDetalladaPermisos(usuario_id) {
        $.get('perfiles_usuarios_ajax.php', {
            accion: 'obtener_vista_detallada_usuario',
            usuario_id: usuario_id
        }, function(res) {
            datosVistaDetallada = res;
            
            if (res && res.length > 0) {
                mostrarResumenPermisos(res);
                mostrarVistaDetalladaContenido(res);
            } else {
                $('#contenidoVistaDetallada').empty();
                $('#mensajeSinPermisos').removeClass('d-none');
            }
        }, 'json').fail(function(xhr, status, error) {
            console.error('Error al cargar vista detallada:', error);
            $('#contenidoVistaDetallada').html(`
                <div class="alert alert-danger">
                    Error al cargar los permisos detallados.
                </div>
            `);
        });
    }
    
    // Mostrar resumen de permisos
    function mostrarResumenPermisos(datos) {
        var totalPerfiles = datos.length;
        var totalFuncionesAsignadas = 0;
        var empresas = [];
        var modulos = [];
        
        $.each(datos, function(i, perfil) {
            totalFuncionesAsignadas += perfil.total_funciones;
            empresas.push(perfil.empresa);
            modulos.push(perfil.modulo);
        });
        
        // Eliminar duplicados
        empresas = [...new Set(empresas)];
        modulos = [...new Set(modulos)];
        
        var html = `
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-pie"></i> Resumen de Permisos</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="fs-2 fw-bold text-primary">${totalPerfiles}</div>
                                <div class="text-muted">Perfiles Asignados</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="fs-2 fw-bold text-success">${totalFuncionesAsignadas}</div>
                                <div class="text-muted">Funciones Asignadas</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="fs-2 fw-bold text-info">${empresas.length}</div>
                                <div class="text-muted">Empresas</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="fs-2 fw-bold text-warning">${modulos.length}</div>
                                <div class="text-muted">Módulos</div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <small class="text-muted">
                                <i class="fas fa-building"></i> Empresas: ${empresas.join(', ')}
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('#resumenPermisos').html(html);
    }
    
    // Mostrar contenido detallado
    function mostrarVistaDetalladaContenido(datos) {
        var accordionHtml = '';
        
        $.each(datos, function(i, perfil) {
            var perfilId = `perfil-${perfil.empresa_perfil_id}`;
            var funcionesAsignadas = 0;
            var funcionesTotales = 0;
            
            // Calcular estadísticas
            $.each(perfil.paginas, function(j, pagina) {
                $.each(pagina.funciones, function(k, funcion) {
                    funcionesTotales++;
                    if (funcion.asignada) funcionesAsignadas++;
                });
                
                $.each(pagina.subpaginas, function(l, subpagina) {
                    $.each(subpagina.funciones, function(m, funcion) {
                        funcionesTotales++;
                        if (funcion.asignada) funcionesAsignadas++;
                    });
                });
            });
            
            var porcentaje = funcionesTotales > 0 ? Math.round((funcionesAsignadas / funcionesTotales) * 100) : 0;
            var colorBarra = porcentaje >= 80 ? 'bg-success' : (porcentaje >= 50 ? 'bg-warning' : 'bg-danger');
            
            accordionHtml += `
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading${perfilId}">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                data-bs-target="#collapse${perfilId}" aria-expanded="false" aria-controls="collapse${perfilId}">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <strong>${perfil.empresa_perfil_nombre}</strong>
                                    <small class="text-muted ms-2">
                                        <i class="fas fa-building"></i> ${perfil.empresa} | 
                                        <i class="fas fa-cube"></i> ${perfil.modulo}
                                    </small>
                                </div>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-primary me-2">${funcionesAsignadas}/${funcionesTotales}</span>
                                    <div class="progress" style="width: 80px; height: 20px;">
                                        <div class="progress-bar ${colorBarra}" role="progressbar" 
                                             style="width: ${porcentaje}%">${porcentaje}%</div>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="collapse${perfilId}" class="accordion-collapse collapse" aria-labelledby="heading${perfilId}">
                        <div class="accordion-body">
                            ${generarHtmlPaginas(perfil.paginas)}
                        </div>
                    </div>
                </div>
            `;
        });
        
        $('#contenidoVistaDetallada').html(accordionHtml);
        
        // Inicializar filtros
        inicializarFiltrosVistaDetallada();
    }
    
    // Generar HTML para páginas
    function generarHtmlPaginas(paginas) {
        var html = '';
        
        $.each(paginas, function(i, pagina) {
            var funcionesAsignadasPagina = pagina.funciones.filter(f => f.asignada).length;
            var totalFuncionesPagina = pagina.funciones.length;
            
            html += `
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-file-alt"></i> ${pagina.pagina}
                            <small class="text-muted ms-2">${pagina.url}</small>
                            <span class="badge bg-primary float-end">
                                ${funcionesAsignadasPagina}/${totalFuncionesPagina}
                            </span>
                        </h6>
                    </div>
                    <div class="card-body">
                        ${generarHtmlFunciones(pagina.funciones, 'Página principal')}
                        
                        ${pagina.subpaginas && pagina.subpaginas.length > 0 ? `
                            <div class="mt-3">
                                <h6 class="border-bottom pb-2"><i class="fas fa-folder"></i> Subpáginas</h6>
                                ${generarHtmlSubpaginas(pagina.subpaginas)}
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        });
        
        return html;
    }
    
    // Generar HTML para subpáginas
    function generarHtmlSubpaginas(subpaginas) {
        var html = '';
        
        $.each(subpaginas, function(i, subpagina) {
            var funcionesAsignadasSub = subpagina.funciones.filter(f => f.asignada).length;
            var totalFuncionesSub = subpagina.funciones.length;
            
            html += `
                <div class="card mb-2">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0">
                            <i class="fas fa-file"></i> ${subpagina.pagina}
                            <small class="text-muted ms-2">${subpagina.url}</small>
                            <span class="badge bg-secondary float-end">
                                ${funcionesAsignadasSub}/${totalFuncionesSub}
                            </span>
                        </h6>
                    </div>
                    <div class="card-body">
                        ${generarHtmlFunciones(subpagina.funciones, 'Subpágina')}
                    </div>
                </div>
            `;
        });
        
        return html;
    }
    
    // Generar HTML para funciones
    function generarHtmlFunciones(funciones, tipo) {
        if (funciones.length === 0) {
            return `<div class="alert alert-info">No hay funciones definidas para esta ${tipo.toLowerCase()}</div>`;
        }
        
        var html = '<div class="row">';
        
        $.each(funciones, function(i, funcion) {
            var badgeClass = funcion.asignada ? 'bg-success' : 'bg-danger';
            var iconClass = funcion.asignada ? 'fa-check-circle' : 'fa-times-circle';
            var textClass = funcion.asignada ? 'text-success' : 'text-danger';
            
            html += `
                <div class="col-md-6 col-lg-4 mb-2 funcion-item" 
                     data-asignada="${funcion.asignada}" 
                     data-nombre="${funcion.nombre_funcion.toLowerCase()}">
                    <div class="card h-100 border ${funcion.asignada ? 'border-success' : 'border-danger'}">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">
                                        <i class="fas ${iconClass} ${textClass} me-1"></i>
                                        ${funcion.nombre_funcion}
                                    </h6>
                                    <p class="text-muted small mb-0">${funcion.descripcion || 'Sin descripción'}</p>
                                    ${funcion.accion_js ? `<small class="text-info"><code>${funcion.accion_js}</code></small>` : ''}
                                </div>
                                <span class="badge ${badgeClass}">${funcion.asignada ? 'Asignada' : 'No Asignada'}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        return html;
    }
    
    // Inicializar filtros de búsqueda
    function inicializarFiltrosVistaDetallada() {
        // Filtro por búsqueda de texto
        $('#buscarPermisos').on('keyup', function() {
            var searchText = $(this).val().toLowerCase();
            var filtroEstado = $('input[name="filtroEstado"]:checked').attr('id');
            
            $('.funcion-item').each(function() {
                var nombre = $(this).data('nombre');
                var asignada = $(this).data('asignada');
                var mostrar = true;
                
                // Aplicar filtro de texto
                if (searchText && nombre.indexOf(searchText) === -1) {
                    mostrar = false;
                }
                
                // Aplicar filtro de estado
                if (filtroEstado === 'filtroAsignadas' && !asignada) {
                    mostrar = false;
                } else if (filtroEstado === 'filtroNoAsignadas' && asignada) {
                    mostrar = false;
                }
                
                $(this).toggle(mostrar);
            });
            
            // Mostrar/Ocultar páginas vacías
            $('.card.mb-3').each(function() {
                var funcionesVisibles = $(this).find('.funcion-item:visible').length;
                $(this).toggle(funcionesVisibles > 0);
            });
        });
        
        // Filtro por estado
        $('input[name="filtroEstado"]').on('change', function() {
            $('#buscarPermisos').trigger('keyup');
        });
    }
    
    // Exportar permisos
    $('#btnExportarPermisos').click(function() {
        if (!datosVistaDetallada.length) return;
        
        Swal.fire({
            title: 'Exportar Permisos',
            text: 'Seleccione el formato de exportación',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'CSV',
            cancelButtonText: 'PDF',
            showDenyButton: true,
            denyButtonText: 'Excel'
        }).then((result) => {
            if (result.isConfirmed) {
                exportarCSV();
            } else if (result.isDenied) {
                exportarExcel();
            } else if (result.dismiss === Swal.DismissReason.cancel) {
                exportarPDF();
            }
        });
    });
    
    // Exportar a CSV
    function exportarCSV() {
        var csv = 'Usuario;Perfil;Empresa;Módulo;Página;Función;Estado;Descripción\n';
        
        $.each(datosVistaDetallada, function(i, perfil) {
            $.each(perfil.paginas, function(j, pagina) {
                $.each(pagina.funciones, function(k, funcion) {
                    csv += `${usuarioDetalleNombre};${perfil.empresa_perfil_nombre};${perfil.empresa};${perfil.modulo};`;
                    csv += `${pagina.pagina};${funcion.nombre_funcion};${funcion.asignada ? 'Asignada' : 'No Asignada'};`;
                    csv += `"${funcion.descripcion || ''}"\n`;
                });
                
                $.each(pagina.subpaginas, function(l, subpagina) {
                    $.each(subpagina.funciones, function(m, funcion) {
                        csv += `${usuarioDetalleNombre};${perfil.empresa_perfil_nombre};${perfil.empresa};${perfil.modulo};`;
                        csv += `${pagina.pagina} > ${subpagina.pagina};${funcion.nombre_funcion};${funcion.asignada ? 'Asignada' : 'No Asignada'};`;
                        csv += `"${funcion.descripcion || ''}"\n`;
                    });
                });
            });
        });
        
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement("a");
        var url = URL.createObjectURL(blob);
        link.setAttribute("href", url);
        link.setAttribute("download", `permisos_${usuarioDetalleNombre}_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    
    // Exportar a Excel (simplificado)
    function exportarExcel() {
        // Para una implementación completa necesitarías una librería como SheetJS
        exportarCSV(); // Por ahora exportamos como CSV que Excel puede abrir
        Swal.fire({
            icon: 'info',
            title: 'Exportado como CSV',
            text: 'El archivo CSV puede abrirse en Excel',
            timer: 2000
        });
    }
    
    // Exportar a PDF (simplificado)
    function exportarPDF() {
        Swal.fire({
            icon: 'warning',
            title: 'Función en desarrollo',
            text: 'La exportación a PDF estará disponible próximamente',
            timer: 2000
        });
    }
});
</script>
<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>