<?php
// Configuración de la página
$pageTitle = "Gestión de Funciones por Perfil de Empresa";
$currentPage = 'empresas_perfiles_funciones';
$modudo_idx = 1;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Gestión de Funciones por Perfil</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Funciones por Perfil</li>
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
                                        <div class="card-tools">
                                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="selectModulo">Módulo:</label>
                                            <select class="form-control" id="selectModulo">
                                                <option value="">Seleccione un módulo</option>
                                            </select>
                                        </div>
                                        <div class="form-group mt-3">
                                            <label for="selectEmpresa">Empresa:</label>
                                            <select class="form-control" id="selectEmpresa">
                                                <option value="">Todas las empresas</option>
                                            </select>
                                        </div>
                                        <div class="form-group mt-3">
                                            <label for="selectModoVista">Modo de Vista:</label>
                                            <select class="form-control" id="selectModoVista">
                                                <option value="individual">Vista Individual</option>
                                                <option value="comparativa">Vista Comparativa</option>
                                            </select>
                                        </div>
                                        <div class="form-group mt-3" id="grupoPerfilIndividual">
                                            <label for="selectPerfilIndividual">Perfil:</label>
                                            <select class="form-control" id="selectPerfilIndividual" disabled>
                                                <option value="">Seleccione un perfil</option>
                                            </select>
                                        </div>
                                        <div class="form-group mt-3 d-none" id="grupoPerfilesComparar">
                                            <label for="selectPerfilesComparar">Perfiles a Comparar:</label>
                                            <select class="form-control" id="selectPerfilesComparar" multiple style="height: 120px;">
                                                <option value="" disabled>Seleccione perfiles...</option>
                                            </select>
                                            <small class="text-muted">Ctrl+Click para seleccionar múltiples</small>
                                        </div>
                                        <div class="mt-3">
                                            <button class="btn btn-sm btn-primary w-100" id="btnMostrar">
                                                <i class="fas fa-eye"></i> Mostrar
                                            </button>
                                        </div>
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-info w-100" id="btnHeredarTodas">
                                                <i class="fas fa-copy"></i> Heredar Todas
                                            </button>
                                        </div>
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-success w-100" id="btnSeleccionarTodas">
                                                <i class="fas fa-check-square"></i> Seleccionar Todas
                                            </button>
                                        </div>
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-warning w-100" id="btnDeseleccionarTodas">
                                                <i class="far fa-square"></i> Deseleccionar Todas
                                            </button>
                                        </div>
                                        <div class="mt-2 d-none" id="grupoGuardarIndividual">
                                            <button class="btn btn-sm btn-primary w-100" id="btnGuardarIndividual">
                                                <i class="fas fa-save"></i> Guardar Cambios
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mt-3" id="cardInfoSeleccion" style="display: none;">
                                    <div class="card-header">
                                        <h3 class="card-title">Información</h3>
                                        <div class="card-tools">
                                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div id="infoSeleccionDetalle">
                                            <!-- Información se cargará aquí -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title" id="tituloVista">Funciones del Módulo
                                            <span id="subtituloVista" class="text-muted small"></span>
                                        </h3>
                                        <div class="card-tools">
                                            <button class="btn btn-sm btn-outline-secondary" id="btnToggleDescipciones">
                                                <i class="fas fa-align-left"></i> Descripciones
                                            </button>
                                            <button class="btn btn-sm btn-outline-success ms-1" id="btnExpandirTodo">
                                                <i class="fas fa-expand"></i> Expandir
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning ms-1" id="btnContraerTodo">
                                                <i class="fas fa-compress"></i> Contraer
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div id="infoSeleccion" class="alert alert-info d-none">
                                            <i class="fas fa-info-circle"></i> 
                                            <span id="textoInfo">Seleccione un módulo y un perfil para comenzar</span>
                                        </div>
                                        
                                        <div class="alert mb-3 d-none" id="alertModoVista">
                                            <!-- Se llenará dinámicamente según el modo -->
                                        </div>
                                        
                                        <div class="mb-3">
                                            <input type="text" class="form-control" id="filtroFunciones" placeholder="Buscar función...">
                                        </div>
                                        
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-hover" id="tablaVista">
                                                <thead id="theadVista">
                                                    <!-- Se llenará dinámicamente según el modo -->
                                                </thead>
                                                <tbody id="tbodyVista">
                                                    <tr>
                                                        <td colspan="6" class="text-center">Seleccione un módulo y un perfil para comenzar</td>
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

<!-- Modal para acción rápida -->
<div class="modal fade" id="modalAccionRapida" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Acción Rápida</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formAccionRapida">
                    <input type="hidden" id="funcion_id_accion" name="funcion_id">
                    <input type="hidden" id="perfil_id_accion" name="perfil_id">
                    <input type="hidden" id="empresa_perfil_funcion_id_accion" name="empresa_perfil_funcion_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Función:</label>
                        <p class="form-control-static fw-bold" id="funcionNombreAccion"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Perfil:</label>
                        <p class="form-control-static" id="perfilNombreAccion"></p>
                    </div>
                    <div class="mb-3">
                        <label for="selectAccionRapida" class="form-label">Acción:</label>
                        <select class="form-control" id="selectAccionRapida" name="accion">
                            <option value="asignar">Asignar (Activa)</option>
                            <option value="desactivar">Desactivar</option>
                            <option value="eliminar">Eliminar</option>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            <strong>Asignar:</strong> La función estará disponible.<br>
                            <strong>Desactivar:</strong> La función estará asignada pero no disponible.<br>
                            <strong>Eliminar:</strong> La función no estará asignada.
                        </small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnEjecutarAccion">Ejecutar</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Variables globales
    var perfilesSeleccionados = [];
    var moduloIdActual = null;
    var empresaIdActual = null;
    var mostrarDescripciones = true;
    var datosPerfiles = {}; // Almacena datos de cada perfil
    var modoVistaActual = 'individual'; // 'individual' o 'comparativa'
    
    // Cargar módulos y empresas al iniciar
    cargarModulos();
    cargarEmpresas();
    
    // Evento cuando cambia el modo de vista
    $('#selectModoVista').change(function() {
        modoVistaActual = $(this).val();
        
        if (modoVistaActual === 'individual') {
            $('#grupoPerfilIndividual').removeClass('d-none');
            $('#grupoPerfilesComparar').addClass('d-none');
            $('#grupoGuardarIndividual').removeClass('d-none');
            $('#btnMostrar').html('<i class="fas fa-eye"></i> Mostrar Perfil');
        } else {
            $('#grupoPerfilIndividual').addClass('d-none');
            $('#grupoPerfilesComparar').removeClass('d-none');
            $('#grupoGuardarIndividual').addClass('d-none');
            $('#btnMostrar').html('<i class="fas fa-exchange-alt"></i> Comparar Perfiles');
        }
        
        actualizarInfoSeleccion();
    });
    
    // Evento cuando cambia el módulo
    $('#selectModulo').change(function() {
        moduloIdActual = $(this).val();
        var empresa_id = $('#selectEmpresa').val();
        
        if (moduloIdActual) {
            cargarPerfilesPorModulo(moduloIdActual, empresa_id);
            actualizarInfoSeleccion();
        } else {
            resetearPaneles();
        }
    });
    
    // Evento cuando cambia la empresa
    $('#selectEmpresa').change(function() {
        var modulo_id = $('#selectModulo').val();
        var empresa_id = $(this).val();
        
        if (modulo_id) {
            cargarPerfilesPorModulo(modulo_id, empresa_id);
            actualizarInfoSeleccion();
        }
    });
    
    // Botón para mostrar/comparar
    $('#btnMostrar').click(function() {
        if (modoVistaActual === 'individual') {
            var perfilId = $('#selectPerfilIndividual').val();
            
            if (!perfilId) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Seleccione un perfil',
                    text: 'Debe seleccionar un perfil para ver sus funciones'
                });
                return;
            }
            
            perfilesSeleccionados = [perfilId];
            mostrarVistaIndividual();
            
        } else {
            var perfiles = $('#selectPerfilesComparar').val();
            
            if (!perfiles || perfiles.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Seleccione perfiles',
                    text: 'Debe seleccionar al menos un perfil para comparar'
                });
                return;
            }
            
            perfilesSeleccionados = perfiles;
            mostrarVistaComparativa();
        }
    });
    
    // Toggle descripciones
    $('#btnToggleDescipciones').click(function() {
        mostrarDescripciones = !mostrarDescripciones;
        if (mostrarDescripciones) {
            $(this).html('<i class="fas fa-align-left"></i> Descripciones');
            $('.descripcion-funcion').show();
        } else {
            $(this).html('<i class="fas fa-eye-slash"></i> Ocultar');
            $('.descripcion-funcion').hide();
        }
    });
    
    // Expandir todo
    $('#btnExpandirTodo').click(function() {
        $('.pagina-principal').removeClass('d-none');
        $('.pagina-hija').removeClass('d-none');
        $('.funcion-fila').removeClass('d-none');
    });
    
    // Contraer todo
    $('#btnContraerTodo').click(function() {
        $('.pagina-hija').addClass('d-none');
        $('.funcion-fila').addClass('d-none');
    });
    
    // Seleccionar todas las funciones (modo individual)
    $('#btnSeleccionarTodas').click(function() {
        $('.check-funcion').prop('checked', true);
        $('#checkAllFunciones').prop('checked', true);
    });
    
    // Deseleccionar todas las funciones (modo individual)
    $('#btnDeseleccionarTodas').click(function() {
        $('.check-funcion').prop('checked', false);
        $('#checkAllFunciones').prop('checked', false);
    });
    
    // Guardar cambios (modo individual)
    $('#btnGuardarIndividual').click(function() {
        guardarCambiosIndividual();
    });
    
    // Función para cargar módulos
    function cargarModulos() {
        $.get('empresas_perfiles_funciones_ajax.php', {accion: 'obtener_modulos'}, function(res) {
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
    
    // Función para cargar empresas
    function cargarEmpresas() {
        $.get('empresas_perfiles_funciones_ajax.php', {accion: 'obtener_empresas'}, function(res) {
            if (res && res.length > 0) {
                $('#selectEmpresa').empty().append('<option value="">Todas las empresas</option>');
                $.each(res, function(i, empresa) {
                    $('#selectEmpresa').append($('<option>', {
                        value: empresa.empresa_id,
                        text: empresa.empresa_nombre
                    }));
                });
            }
        }, 'json');
    }
    
    // Función para cargar perfiles por módulo
    function cargarPerfilesPorModulo(modulo_id, empresa_id = null) {
        var params = {
            accion: 'obtener_empresas_perfiles_por_modulo',
            modulo_id: modulo_id
        };
        
        if (empresa_id) {
            params.empresa_id = empresa_id;
        }
        
        $.get('empresas_perfiles_funciones_ajax.php', params, function(res) {
            // Limpiar selects
            $('#selectPerfilIndividual').empty().append('<option value="">Seleccione un perfil</option>');
            $('#selectPerfilesComparar').empty();
            
            if (res && res.length > 0) {
                // Guardar datos de perfiles para uso posterior
                datosPerfiles = {};
                
                $.each(res, function(i, perfil) {
                    var texto = perfil.empresa_perfil_nombre;
                    if (perfil.empresa_nombre) {
                        texto += ' (' + perfil.empresa_nombre + ')';
                    }
                    if (perfil.perfil_base) {
                        texto += ' - Base: ' + perfil.perfil_base;
                    }
                    
                    // Guardar datos completos del perfil
                    datosPerfiles[perfil.empresa_perfil_id] = perfil;
                    
                    // Agregar al select individual
                    $('#selectPerfilIndividual').append($('<option>', {
                        value: perfil.empresa_perfil_id,
                        text: texto
                    }));
                    
                    // Agregar al select múltiple
                    $('#selectPerfilesComparar').append($('<option>', {
                        value: perfil.empresa_perfil_id,
                        text: texto
                    }));
                });
                
                $('#selectPerfilIndividual').prop('disabled', false);
                $('#selectPerfilesComparar').prop('disabled', false);
                
            } else {
                $('#selectPerfilIndividual').append('<option value="">No hay perfiles disponibles</option>');
                $('#selectPerfilesComparar').append('<option value="" disabled>No hay perfiles disponibles</option>');
                $('#selectPerfilIndividual').prop('disabled', true);
                $('#selectPerfilesComparar').prop('disabled', true);
            }
        }, 'json');
    }
    
    // Función para actualizar la información de selección
    function actualizarInfoSeleccion() {
        var modulo = $('#selectModulo option:selected').text();
        var empresa = $('#selectEmpresa option:selected').text();
        
        if (modulo && modulo !== "Seleccione un módulo") {
            var texto = `Módulo: <strong>${modulo}</strong>`;
            
            if (empresa && empresa !== "Todas las empresas") {
                texto += ` | Empresa: <strong>${empresa}</strong>`;
            }
            
            $('#textoInfo').html(texto);
            $('#infoSeleccion').removeClass('d-none');
            
            // Actualizar información en el panel lateral
            var infoHtml = `<p><strong>Módulo:</strong> ${modulo}</p>`;
            if (empresa && empresa !== "Todas las empresas") {
                infoHtml += `<p><strong>Empresa:</strong> ${empresa}</p>`;
            }
            infoHtml += `<p><strong>Modo:</strong> ${modoVistaActual === 'individual' ? 'Vista Individual' : 'Vista Comparativa'}</p>`;
            
            $('#infoSeleccionDetalle').html(infoHtml);
            $('#cardInfoSeleccion').show();
            
        } else {
            $('#infoSeleccion').addClass('d-none');
            $('#cardInfoSeleccion').hide();
        }
    }
    
    // ==================== VISTA INDIVIDUAL ====================
    
    // Función para mostrar vista individual
    function mostrarVistaIndividual() {
        if (!moduloIdActual || perfilesSeleccionados.length === 0) {
            $('#tbodyVista').html('<tr><td colspan="6" class="text-center">Seleccione un módulo y un perfil</td></tr>');
            return;
        }
        
        var perfilId = perfilesSeleccionados[0];
        var perfil = datosPerfiles[perfilId];
        
        if (!perfil) {
            $('#tbodyVista').html('<tr><td colspan="6" class="text-center">Perfil no encontrado</td></tr>');
            return;
        }
        
        // Actualizar subtítulo
        $('#subtituloVista').text(`- ${perfil.empresa_perfil_nombre} (${perfil.empresa_nombre})`);
        $('#tituloVista').text('Funciones del Perfil');
        
        // Actualizar alerta
        $('#alertModoVista')
            .removeClass('d-none alert-warning alert-info')
            .addClass('alert-info')
            .html(`
                <i class="fas fa-user"></i> 
                <strong>Vista Individual:</strong> Mostrando funciones para el perfil <strong>${perfil.empresa_perfil_nombre}</strong> 
                de la empresa <strong>${perfil.empresa_nombre}</strong>. 
                Use los checkboxes para asignar/desasignar funciones.
            `);
        
        // Obtener estructura del módulo y asignaciones del perfil
        $.get('empresas_perfiles_funciones_ajax.php', {
            accion: 'obtener_estructura_completa_por_empresa_perfil',
            empresa_perfil_id: perfilId
        }, function(estructura) {
            if (!estructura || estructura.length === 0) {
                $('#tbodyVista').html('<tr><td colspan="6" class="text-center">No hay páginas ni funciones en este módulo</td></tr>');
                return;
            }
            
            // Construir encabezados para vista individual
            construirEncabezadosIndividual();
            
            // Construir cuerpo de tabla
            construirCuerpoIndividual(estructura, perfil);
            
            // Agregar filtro de búsqueda
            agregarFiltroBusqueda();
            
            // Actualizar check all
            actualizarCheckAll('#checkAllFunciones', '.check-funcion');
            
        }, 'json');
    }
    
    // Función para construir encabezados individuales
    function construirEncabezadosIndividual() {
        var thead = $('#theadVista');
        thead.empty();
        
        var html = '<tr>';
        html += '<th width="40"><input type="checkbox" id="checkAllFunciones"></th>';
        html += '<th>Página / Función</th>';
        html += '<th width="25%">Descripción</th>';
        html += '<th width="15%">Acción</th>';
        html += '<th width="100">Estado</th>';
        html += '<th width="80">Editar</th>';
        html += '</tr>';
        
        thead.html(html);
    }
    
    // Función para construir cuerpo individual
    function construirCuerpoIndividual(estructura, perfil) {
        var tbody = $('#tbodyVista');
        tbody.empty();
        
        // Organizar páginas por jerarquía
        var paginasPrincipales = estructura.filter(function(pagina) {
            return pagina.padre_id == 0;
        });
        
        var paginasSecundarias = estructura.filter(function(pagina) {
            return pagina.padre_id > 0;
        });
        
        // Mostrar páginas principales
        $.each(paginasPrincipales, function(i, pagina) {
            // Fila de encabezado de la página principal
            tbody.append(`
                <tr class="table-primary pagina-principal">
                    <td colspan="6" class="fw-bold">
                        <i class="fas fa-folder"></i> ${pagina.pagina_nombre}
                        <small class="text-muted">${pagina.ruta ? '(' + pagina.ruta + ')' : ''}</small>
                        <button class="btn btn-sm btn-outline-primary btn-toggle ms-2" data-target="pagina-${pagina.pagina_id}">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </td>
                </tr>
            `);
            
            // Funciones de esta página principal
            if (pagina.funciones && pagina.funciones.length > 0) {
                $.each(pagina.funciones, function(j, funcion) {
                    mostrarFuncionIndividual(funcion, tbody, perfil, false, pagina.pagina_id);
                });
            }
            
            // Mostrar páginas secundarias de esta página principal
            var paginasHijas = paginasSecundarias.filter(function(paginaSec) {
                return paginaSec.padre_id == pagina.pagina_id;
            });
            
            if (paginasHijas.length > 0) {
                tbody.append(`
                    <tr class="pagina-hija pagina-${pagina.pagina_id}">
                        <td colspan="6" class="bg-light">
                            <small><i>Páginas hijas:</i></small>
                        </td>
                    </tr>
                `);
            }
            
            $.each(paginasHijas, function(k, paginaHija) {
                // Fila de encabezado de página hija
                tbody.append(`
                    <tr class="table-secondary pagina-hija pagina-${pagina.pagina_id}">
                        <td colspan="6" class="fw-bold ps-4">
                            <i class="fas fa-file-alt"></i> ${paginaHija.pagina_nombre}
                            <small class="text-muted">${paginaHija.ruta ? '(' + paginaHija.ruta + ')' : ''}</small>
                            <button class="btn btn-sm btn-outline-secondary btn-toggle ms-2" data-target="pagina-${paginaHija.pagina_id}">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </td>
                    </tr>
                `);
                
                // Funciones de esta página hija
                if (paginaHija.funciones && paginaHija.funciones.length > 0) {
                    $.each(paginaHija.funciones, function(l, funcion) {
                        mostrarFuncionIndividual(funcion, tbody, perfil, true, paginaHija.pagina_id);
                    });
                }
            });
        });
        
        // Agregar eventos a los botones de toggle
        $('.btn-toggle').click(function() {
            var target = $(this).data('target');
            var icon = $(this).find('i');
            var filas = $(`.pagina-${target.replace('pagina-', '')}`);
            
            if (filas.hasClass('d-none')) {
                filas.removeClass('d-none');
                icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
            } else {
                filas.addClass('d-none');
                icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
            }
        });
        
        // Agregar eventos a los botones de editar
        $('.btn-editar-individual').click(function() {
            var funcionId = $(this).data('funcion-id');
            var perfilId = $(this).data('perfil-id');
            var estadoActual = $(this).data('estado');
            var empresaPerfilFuncionId = $(this).data('empresa-perfil-funcion-id');
            
            abrirModalAccionRapida(funcionId, perfilId, estadoActual, empresaPerfilFuncionId);
        });
    }
    
    // Función para mostrar una función en vista individual
    function mostrarFuncionIndividual(funcion, tbody, perfil, esHija = false, paginaId) {
        var indentacion = esHija ? 'ps-5' : 'ps-3';
        var claseFila = `funcion-fila pagina-${paginaId}`;
        var checked = funcion.asignada ? 'checked' : '';
        var estado = '';
        var acciones = '';
        
        if (funcion.asignada) {
            estado = funcion.asignado == 1 ? 
                '<span class="badge bg-success">Activa</span>' : 
                '<span class="badge bg-warning">Inactiva</span>';
            
            acciones = `
                <button class="btn btn-sm btn-warning btn-editar-individual" 
                        data-funcion-id="${funcion.pagina_funcion_id}"
                        data-perfil-id="${perfil.empresa_perfil_id}"
                        data-estado="${funcion.asignado}"
                        data-empresa-perfil-funcion-id="${funcion.empresa_perfil_funcion_id}">
                    <i class="fas fa-edit"></i>
                </button>
            `;
        } else {
            estado = '<span class="badge bg-secondary">No asignada</span>';
            acciones = '';
        }
        
        var html = `<tr class="${claseFila}">`;
        html += `<td><input type="checkbox" class="check-funcion" value="${funcion.pagina_funcion_id}" ${checked}></td>`;
        html += `<td class="${indentacion}"><i class="fas fa-cog"></i> ${funcion.nombre_funcion}</td>`;
        html += `<td class="descripcion-funcion"><small>${funcion.descripcion || ''}</small></td>`;
        html += `<td><small class="text-muted">${funcion.accion_js || ''}</small></td>`;
        html += `<td>${estado}</td>`;
        html += `<td>${acciones}</td>`;
        html += '</tr>';
        
        tbody.append(html);
    }
    
    // Función para guardar cambios individuales
    function guardarCambiosIndividual() {
        if (perfilesSeleccionados.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Error',
                text: 'No hay perfil seleccionado'
            });
            return;
        }
        
        var perfilId = perfilesSeleccionados[0];
        var perfil = datosPerfiles[perfilId];
        
        if (!perfil) {
            Swal.fire({
                icon: 'warning',
                title: 'Error',
                text: 'Perfil no encontrado'
            });
            return;
        }
        
        var funcionesAsignar = [];
        var funcionesEliminar = [];
        
        // Obtener funciones actualmente asignadas (checkboxes marcados al cargar)
        $('.check-funcion').each(function() {
            var checkbox = $(this);
            var funcionId = checkbox.val();
            var estabaMarcado = checkbox.prop('defaultChecked');
            var estaMarcado = checkbox.is(':checked');
            
            if (estaMarcado && !estabaMarcado) {
                // Se marcó una función no asignada
                funcionesAsignar.push(funcionId);
            } else if (!estaMarcado && estabaMarcado) {
                // Se desmarcó una función asignada
                funcionesEliminar.push(funcionId);
            }
        });
        
        if (funcionesAsignar.length === 0 && funcionesEliminar.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Sin cambios',
                text: 'No hay cambios para guardar'
            });
            return;
        }
        
        var totalCambios = funcionesAsignar.length + funcionesEliminar.length;
        
        Swal.fire({
            title: 'Guardando cambios',
            text: `¿Desea guardar ${totalCambios} cambio(s)?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, guardar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Procesando...',
                    text: 'Por favor espere',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Procesar cambios
                var procesadas = 0;
                var errores = [];
                
                function procesarSiguiente() {
                    if (procesadas >= totalCambios) {
                        Swal.close();
                        if (errores.length === 0) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Éxito',
                                text: 'Cambios guardados correctamente',
                                showConfirmButton: false,
                                timer: 1500
                            });
                            // Recargar vista
                            mostrarVistaIndividual();
                        } else {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Advertencia',
                                text: `Se guardaron ${totalCambios - errores.length} de ${totalCambios} cambios. ${errores.length} errores.`
                            });
                        }
                        return;
                    }
                    
                    var index = procesadas;
                    var esAsignacion = index < funcionesAsignar.length;
                    var funcionId = esAsignacion ? 
                        funcionesAsignar[index] : funcionesEliminar[index - funcionesAsignar.length];
                    
                    if (esAsignacion) {
                        // Asignar función
                        $.post('empresas_perfiles_funciones_ajax.php', {
                            accion: 'asignar_pagina_funcion_empresa_perfil',
                            empresa_id: perfil.empresa_id,
                            empresa_perfil_id: perfilId,
                            pagina_funcion_id: funcionId,
                            asignado: 1
                        }, function(res) {
                            if (!res.resultado) {
                                errores.push(funcionId);
                            }
                            procesadas++;
                            procesarSiguiente();
                        }, 'json');
                    } else {
                        // Eliminar función - obtener ID de asignación
                        $.get('empresas_perfiles_funciones_ajax.php', {
                            accion: 'obtener_paginas_funciones_por_empresa_perfil',
                            empresa_perfil_id: perfilId
                        }, function(funciones) {
                            var empresaPerfilFuncionId = null;
                            if (funciones && funciones.length > 0) {
                                $.each(funciones, function(i, f) {
                                    if (f.pagina_funcion_id == funcionId && f.asignada) {
                                        empresaPerfilFuncionId = f.empresa_perfil_funcion_id;
                                        return false;
                                    }
                                });
                            }
                            
                            if (empresaPerfilFuncionId) {
                                $.post('empresas_perfiles_funciones_ajax.php', {
                                    accion: 'eliminar_pagina_funcion_empresa_perfil',
                                    empresa_perfil_funcion_id: empresaPerfilFuncionId
                                }, function(res) {
                                    if (!res.resultado) {
                                        errores.push(funcionId);
                                    }
                                    procesadas++;
                                    procesarSiguiente();
                                }, 'json');
                            } else {
                                errores.push(funcionId);
                                procesadas++;
                                procesarSiguiente();
                            }
                        }, 'json');
                    }
                }
                
                procesarSiguiente();
            }
        });
    }
    
    // ==================== VISTA COMPARATIVA ====================
    
    // Función para mostrar vista comparativa
    function mostrarVistaComparativa() {
        if (!moduloIdActual || perfilesSeleccionados.length === 0) {
            $('#tbodyVista').html('<tr><td colspan="6" class="text-center">Seleccione un módulo y al menos un perfil</td></tr>');
            return;
        }
        
        // Actualizar subtítulo
        var nombresPerfiles = [];
        $.each(perfilesSeleccionados, function(i, perfilId) {
            var perfil = datosPerfiles[perfilId];
            if (perfil) {
                nombresPerfiles.push(perfil.empresa_perfil_nombre);
            }
        });
        
        $('#subtituloVista').text(`- Comparando ${nombresPerfiles.join(' vs ')}`);
        $('#tituloVista').text('Comparativa de Funciones');
        
        // Actualizar alerta
        var textoComparativa = perfilesSeleccionados.length === 1 ? 
            'Mostrando funciones para 1 perfil.' :
            `Comparando ${perfilesSeleccionados.length} perfiles.`;
        
        $('#alertModoVista')
            .removeClass('d-none alert-warning alert-info')
            .addClass('alert-warning')
            .html(`
                <i class="fas fa-exchange-alt"></i> 
                <strong>Vista Comparativa:</strong> ${textoComparativa} 
                Haga clic en cualquier botón de estado para cambiar la asignación.
            `);
        
        // Obtener estructura del módulo
        $.get('empresas_perfiles_funciones_ajax.php', {
            accion: 'obtener_estructura_paginas_por_modulo',
            modulo_id: moduloIdActual
        }, function(estructura) {
            if (!estructura || estructura.length === 0) {
                $('#tbodyVista').html('<tr><td colspan="4" class="text-center">No hay páginas ni funciones en este módulo</td></tr>');
                return;
            }
            
            // Obtener datos de asignaciones para cada perfil
            var promesasPerfiles = [];
            var datosAsignaciones = {};
            
            $.each(perfilesSeleccionados, function(i, perfilId) {
                var promesa = $.get('empresas_perfiles_funciones_ajax.php', {
                    accion: 'obtener_estructura_completa_por_empresa_perfil',
                    empresa_perfil_id: perfilId
                }).done(function(data) {
                    datosAsignaciones[perfilId] = data;
                });
                promesasPerfiles.push(promesa);
            });
            
            // Cuando todas las promesas se completen
            $.when.apply($, promesasPerfiles).done(function() {
                // Construir encabezados de tabla dinámicamente
                construirEncabezadosComparativa();
                
                // Construir cuerpo de tabla
                construirCuerpoComparativa(estructura, datosAsignaciones);
                
                // Agregar filtro de búsqueda
                agregarFiltroBusqueda();
            });
        }, 'json');
    }
    
    // Función para construir encabezados comparativa
    function construirEncabezadosComparativa() {
        var thead = $('#theadVista');
        thead.empty();
        
        // Fila principal
        var html = '<tr>';
        html += '<th width="40">#</th>';
        html += '<th>Página / Función</th>';
        html += '<th width="25%">Descripción</th>';
        html += '<th width="15%">Acción</th>';
        
        // Agregar columnas para cada perfil
        $.each(perfilesSeleccionados, function(i, perfilId) {
            var perfil = datosPerfiles[perfilId];
            var titulo = perfil.empresa_perfil_nombre;
            var subtitulo = perfil.empresa_nombre;
            
            html += `<th width="120" class="text-center">
                        <div class="fw-bold">${titulo}</div>
                        <small class="text-muted">${subtitulo}</small>
                     </th>`;
        });
        
        html += '</tr>';
        thead.html(html);
    }
    
    // Función para construir cuerpo comparativa
    function construirCuerpoComparativa(estructura, datosAsignaciones) {
        var tbody = $('#tbodyVista');
        tbody.empty();
        
        // Organizar páginas por jerarquía
        var paginasPrincipales = estructura.filter(function(pagina) {
            return pagina.padre_id == 0;
        });
        
        var paginasSecundarias = estructura.filter(function(pagina) {
            return pagina.padre_id > 0;
        });
        
        var contador = 1;
        
        // Mostrar páginas principales
        $.each(paginasPrincipales, function(i, pagina) {
            // Fila de encabezado de la página principal
            tbody.append(`
                <tr class="table-primary pagina-principal">
                    <td colspan="${4 + perfilesSeleccionados.length}" class="fw-bold">
                        <i class="fas fa-folder"></i> ${pagina.pagina_nombre}
                        <small class="text-muted">${pagina.ruta ? '(' + pagina.ruta + ')' : ''}</small>
                        <button class="btn btn-sm btn-outline-primary btn-toggle ms-2" data-target="pagina-${pagina.pagina_id}">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </td>
                </tr>
            `);
            
            // Funciones de esta página principal
            if (pagina.funciones && pagina.funciones.length > 0) {
                $.each(pagina.funciones, function(j, funcion) {
                    mostrarFuncionComparativa(funcion, contador++, tbody, datosAsignaciones, false, pagina.pagina_id);
                });
            }
            
            // Mostrar páginas secundarias de esta página principal
            var paginasHijas = paginasSecundarias.filter(function(paginaSec) {
                return paginaSec.padre_id == pagina.pagina_id;
            });
            
            if (paginasHijas.length > 0) {
                tbody.append(`
                    <tr class="pagina-hija pagina-${pagina.pagina_id}">
                        <td colspan="${4 + perfilesSeleccionados.length}" class="bg-light">
                            <small><i>Páginas hijas:</i></small>
                        </td>
                    </tr>
                `);
            }
            
            $.each(paginasHijas, function(k, paginaHija) {
                // Fila de encabezado de página hija
                tbody.append(`
                    <tr class="table-secondary pagina-hija pagina-${pagina.pagina_id}">
                        <td colspan="${4 + perfilesSeleccionados.length}" class="fw-bold ps-4">
                            <i class="fas fa-file-alt"></i> ${paginaHija.pagina_nombre}
                            <small class="text-muted">${paginaHija.ruta ? '(' + paginaHija.ruta + ')' : ''}</small>
                            <button class="btn btn-sm btn-outline-secondary btn-toggle ms-2" data-target="pagina-${paginaHija.pagina_id}">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </td>
                    </tr>
                `);
                
                // Funciones de esta página hija
                if (paginaHija.funciones && paginaHija.funciones.length > 0) {
                    $.each(paginaHija.funciones, function(l, funcion) {
                        mostrarFuncionComparativa(funcion, contador++, tbody, datosAsignaciones, true, paginaHija.pagina_id);
                    });
                }
            });
        });
        
        // Agregar eventos a los botones de toggle
        $('.btn-toggle').click(function() {
            var target = $(this).data('target');
            var icon = $(this).find('i');
            var filas = $(`.pagina-${target.replace('pagina-', '')}`);
            
            if (filas.hasClass('d-none')) {
                filas.removeClass('d-none');
                icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
            } else {
                filas.addClass('d-none');
                icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
            }
        });
        
        // Agregar eventos a los botones de acción
        $('.btn-accion-perfil').click(function() {
            var funcionId = $(this).data('funcion-id');
            var perfilId = $(this).data('perfil-id');
            var estadoActual = $(this).data('estado');
            var empresaPerfilFuncionId = $(this).data('empresa-perfil-funcion-id');
            
            abrirModalAccionRapida(funcionId, perfilId, estadoActual, empresaPerfilFuncionId);
        });
    }
    
    // Función para mostrar función en comparativa
    function mostrarFuncionComparativa(funcion, numero, tbody, datosAsignaciones, esHija = false, paginaId) {
        var indentacion = esHija ? 'ps-5' : 'ps-3';
        var claseFila = `funcion-fila pagina-${paginaId}`;
        
        // Construir fila
        var html = `<tr class="${claseFila}">`;
        html += `<td>${numero}</td>`;
        html += `<td class="${indentacion}"><i class="fas fa-cog"></i> ${funcion.nombre_funcion}</td>`;
        html += `<td class="descripcion-funcion"><small>${funcion.descripcion || ''}</small></td>`;
        html += `<td><small class="text-muted">${funcion.accion_js || ''}</small></td>`;
        
        // Agregar columnas para cada perfil
        $.each(perfilesSeleccionados, function(i, perfilId) {
            var estado = obtenerEstadoFuncion(funcion.pagina_funcion_id, perfilId, datosAsignaciones[perfilId]);
            html += `<td class="text-center">${estado}</td>`;
        });
        
        html += '</tr>';
        tbody.append(html);
    }
    
    // Función para obtener estado de función en comparativa
    function obtenerEstadoFuncion(funcionId, perfilId, datosPerfil) {
        // Buscar la función en los datos del perfil
        var funcionEnPerfil = null;
        
        if (datosPerfil && datosPerfil.length > 0) {
            $.each(datosPerfil, function(i, pagina) {
                if (pagina.funciones && pagina.funciones.length > 0) {
                    $.each(pagina.funciones, function(j, funcion) {
                        if (funcion.pagina_funcion_id == funcionId) {
                            funcionEnPerfil = funcion;
                            return false;
                        }
                    });
                    if (funcionEnPerfil) return false;
                }
            });
        }
        
        var perfil = datosPerfiles[perfilId];
        
        if (funcionEnPerfil && funcionEnPerfil.asignada) {
            var estado = funcionEnPerfil.asignado == 1 ? 'success' : 'warning';
            var texto = funcionEnPerfil.asignado == 1 ? 'Activa' : 'Inactiva';
            
            return `
                <button class="btn btn-sm btn-${estado} btn-accion-perfil" 
                        data-funcion-id="${funcionId}"
                        data-perfil-id="${perfilId}"
                        data-estado="${funcionEnPerfil.asignado}"
                        data-empresa-perfil-funcion-id="${funcionEnPerfil.empresa_perfil_funcion_id}"
                        title="${perfil.empresa_perfil_nombre}: ${texto}">
                    ${texto}
                </button>
            `;
        } else {
            return `
                <button class="btn btn-sm btn-outline-secondary btn-accion-perfil" 
                        data-funcion-id="${funcionId}"
                        data-perfil-id="${perfilId}"
                        data-estado="0"
                        data-empresa-perfil-funcion-id=""
                        title="${perfil.empresa_perfil_nombre}: No asignada">
                    No Asignada
                </button>
            `;
        }
    }
    
    // ==================== FUNCIONES COMPARTIDAS ====================
    
    // Función para agregar filtro de búsqueda
    function agregarFiltroBusqueda() {
        $('#filtroFunciones').off('keyup').on('keyup', function() {
            var valor = $(this).val().toLowerCase();
            $('.funcion-fila').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(valor) > -1);
            });
        });
    }
    
    // Función para actualizar check all
    function actualizarCheckAll(checkAllSelector, checkSelector) {
        $(checkAllSelector).off('change').on('change', function() {
            $(checkSelector).prop('checked', $(this).prop('checked'));
        });
        
        $(checkSelector).off('change').on('change', function() {
            var total = $(checkSelector).length;
            var checked = $(checkSelector + ':checked').length;
            $(checkAllSelector).prop('checked', total > 0 && total === checked);
        });
    }
    
    // Función para resetear paneles
    function resetearPaneles() {
        $('#tbodyVista').html('<tr><td colspan="6" class="text-center">Seleccione un módulo y un perfil para comenzar</td></tr>');
        $('#cardInfoSeleccion').hide();
        $('#infoSeleccion').addClass('d-none');
        $('#subtituloVista').text('');
        $('#alertModoVista').addClass('d-none');
        $('#theadVista').empty();
        perfilesSeleccionados = [];
    }
    
    // Abrir modal para acción rápida
    function abrirModalAccionRapida(funcionId, perfilId, estadoActual, empresaPerfilFuncionId) {
        // Buscar información de la función
        var nombreFuncion = '';
        var perfil = datosPerfiles[perfilId];
        var perfilNombre = perfil ? perfil.empresa_perfil_nombre : '';
        
        // Buscar en todas las filas para obtener el nombre de la función
        $(`.btn-accion-perfil[data-funcion-id="${funcionId}"], .btn-editar-individual[data-funcion-id="${funcionId}"]`).each(function() {
            var fila = $(this).closest('tr');
            var nombre = fila.find('td:nth-child(2)').text().replace('', '').trim();
            if (nombre) {
                nombreFuncion = nombre;
                return false;
            }
        });
        
        $('#funcion_id_accion').val(funcionId);
        $('#perfil_id_accion').val(perfilId);
        $('#empresa_perfil_funcion_id_accion').val(empresaPerfilFuncionId);
        $('#funcionNombreAccion').text(nombreFuncion);
        $('#perfilNombreAccion').text(perfilNombre);
        
        // Configurar opción seleccionada según estado actual
        var select = $('#selectAccionRapida');
        select.val('asignar'); // Por defecto
        
        if (estadoActual == '1') {
            select.val('desactivar');
        } else if (estadoActual == '0' && empresaPerfilFuncionId) {
            select.val('eliminar');
        }
        
        $('#modalAccionRapida').modal('show');
    }
    
    // Ejecutar acción
    $('#btnEjecutarAccion').click(function() {
        var funcionId = $('#funcion_id_accion').val();
        var perfilId = $('#perfil_id_accion').val();
        var empresaPerfilFuncionId = $('#empresa_perfil_funcion_id_accion').val();
        var accion = $('#selectAccionRapida').val();
        var perfil = datosPerfiles[perfilId];
        var empresaId = perfil ? perfil.empresa_id : 0;
        
        Swal.fire({
            title: 'Ejecutando acción...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        if (accion === 'asignar') {
            // Asignar función
            $.post('empresas_perfiles_funciones_ajax.php', {
                accion: 'asignar_pagina_funcion_empresa_perfil',
                empresa_id: empresaId,
                empresa_perfil_id: perfilId,
                pagina_funcion_id: funcionId,
                asignado: 1
            }, function(res) {
                Swal.close();
                if (res.resultado) {
                    $('#modalAccionRapida').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: 'Función asignada correctamente',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    // Recargar la vista actual
                    if (modoVistaActual === 'individual') {
                        mostrarVistaIndividual();
                    } else {
                        mostrarVistaComparativa();
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.mensaje || 'No se pudo asignar la función'
                    });
                }
            }, 'json');
            
        } else if (accion === 'desactivar') {
            // Desactivar función (actualizar a inactiva)
            $.post('empresas_perfiles_funciones_ajax.php', {
                accion: 'actualizar_asignacion_pagina_funcion',
                empresa_perfil_funcion_id: empresaPerfilFuncionId,
                asignado: 0
            }, function(res) {
                Swal.close();
                if (res.resultado) {
                    $('#modalAccionRapida').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: 'Función desactivada correctamente',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    // Recargar la vista actual
                    if (modoVistaActual === 'individual') {
                        mostrarVistaIndividual();
                    } else {
                        mostrarVistaComparativa();
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.mensaje || 'No se pudo desactivar la función'
                    });
                }
            }, 'json');
            
        } else if (accion === 'eliminar') {
            // Eliminar asignación
            $.post('empresas_perfiles_funciones_ajax.php', {
                accion: 'eliminar_pagina_funcion_empresa_perfil',
                empresa_perfil_funcion_id: empresaPerfilFuncionId
            }, function(res) {
                Swal.close();
                if (res.resultado) {
                    $('#modalAccionRapida').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: 'Función eliminada correctamente',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    // Recargar la vista actual
                    if (modoVistaActual === 'individual') {
                        mostrarVistaIndividual();
                    } else {
                        mostrarVistaComparativa();
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.mensaje || 'No se pudo eliminar la función'
                    });
                }
            }, 'json');
        }
    });
    
    // Heredar todas las funciones
    $('#btnHeredarTodas').click(function() {
        if (perfilesSeleccionados.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Seleccione perfiles',
                text: 'Debe seleccionar al menos un perfil primero'
            });
            return;
        }
        
        Swal.fire({
            title: 'Heredar todas las funciones',
            text: `¿Desea asignar todas las funciones del módulo a ${perfilesSeleccionados.length} perfil(es)?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, heredar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Heredando funciones...',
                    text: 'Por favor espere',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                var perfilesProcesados = 0;
                var totalFuncionesHeredadas = 0;
                
                function procesarSiguientePerfil() {
                    if (perfilesProcesados >= perfilesSeleccionados.length) {
                        Swal.close();
                        Swal.fire({
                            icon: 'success',
                            title: 'Éxito',
                            text: `Se heredaron funciones para ${perfilesSeleccionados.length} perfil(es).`,
                            showConfirmButton: false,
                            timer: 2000
                        });
                        // Recargar la vista actual
                        if (modoVistaActual === 'individual') {
                            mostrarVistaIndividual();
                        } else {
                            mostrarVistaComparativa();
                        }
                        return;
                    }
                    
                    var perfilId = perfilesSeleccionados[perfilesProcesados];
                    var perfil = datosPerfiles[perfilId];
                    
                    $.post('empresas_perfiles_funciones_ajax.php', {
                        accion: 'heredar_paginas_funciones_desde_perfil_base',
                        empresa_perfil_id: perfilId,
                        perfil_id_base: 0,
                        empresa_id: perfil.empresa_id
                    }, function(res) {
                        if (res.resultado) {
                            totalFuncionesHeredadas += res.contador || 0;
                        }
                        perfilesProcesados++;
                        procesarSiguientePerfil();
                    }, 'json');
                }
                
                procesarSiguientePerfil();
            }
        });
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>