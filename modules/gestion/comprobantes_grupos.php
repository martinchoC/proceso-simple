<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Gestión de Grupos y Subgrupos de Comprobantes";
$currentPage = 'comprobantes_grupos';
$modudo_idx = 2; // Ajustar según tu sistema
$pagina_idx = 46; // ID de página para grupos de comprobantes

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-sitemap me-2"></i>Gestión de Grupos y Subgrupos de Comprobantes
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa - Vista Jerárquica</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Gestión</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Grupos y Subgrupos</li>
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
                                        <div id="contenedor-botones" class="d-inline">
                                            <!-- Botones se cargarán dinámicamente -->
                                        </div>
                                        <div class="float-end">
                                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                                id="btnRecargar">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <!-- Lista jerárquica manual -->
                                        <div id="arbolComprobantes" class="list-group">
                                            <!-- Los grupos y subgrupos se cargarán aquí -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Modal para crear/editar grupo de comprobantes -->
            <div class="modal fade" id="modalComprobanteGrupo" tabindex="-1" aria-labelledby="modalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Grupo de Comprobante</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formComprobanteGrupo" class="needs-validation" novalidate>
                                <input type="hidden" id="comprobante_grupo_id" name="comprobante_grupo_id" />
                                <div class="mb-3">
                                    <label for="comprobante_grupo" class="form-label">Nombre del Grupo *</label>
                                    <input type="text" class="form-control" id="comprobante_grupo"
                                        name="comprobante_grupo" maxlength="50" required>
                                    <div class="invalid-feedback">El nombre del grupo es obligatorio</div>
                                    <small class="text-muted">Máximo 50 caracteres (ej: FACTURA, NOTA DE CRÉDITO,
                                        TICKET, etc.)</small>
                                </div>
                                <div class="mb-3">
                                    <label for="orden_grupo" class="form-label">Orden</label>
                                    <input type="number" class="form-control" id="orden_grupo"
                                        name="orden_grupo" min="0" max="999" value="0">
                                    <small class="text-muted">Número para ordenar los grupos (menor número = primero)</small>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="btnGuardarGrupo">
                                <i class="fas fa-save me-1"></i>Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para crear/editar subgrupo -->
            <div class="modal fade" id="modalComprobanteSubgrupo" tabindex="-1" aria-labelledby="modalSubgrupoLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalSubgrupoLabel">Subgrupo de Comprobante</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formComprobanteSubgrupo" class="needs-validation" novalidate>
                                <input type="hidden" id="comprobante_subgrupo_id" name="comprobante_subgrupo_id" />
                                <input type="hidden" id="grupo_padre_id" name="grupo_padre_id" />
                                <div class="mb-3">
                                    <label for="comprobante_subgrupo" class="form-label">Nombre del Subgrupo *</label>
                                    <input type="text" class="form-control" id="comprobante_subgrupo"
                                        name="comprobante_subgrupo" maxlength="50" required>
                                    <div class="invalid-feedback">El nombre del subgrupo es obligatorio</div>
                                    <small class="text-muted">Máximo 50 caracteres</small>
                                </div>
                                <div class="mb-3">
                                    <label for="orden_subgrupo" class="form-label">Orden</label>
                                    <input type="number" class="form-control" id="orden_subgrupo"
                                        name="orden_subgrupo" min="0" max="999" value="0">
                                    <small class="text-muted">Número para ordenar los subgrupos dentro del grupo (menor número = primero)</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Grupo Padre</label>
                                    <div class="form-control bg-light">
                                        <span id="nombre_grupo_padre" class="fw-bold text-primary">Seleccionar grupo primero</span>
                                    </div>
                                    <small class="text-muted">El subgrupo se asociará al grupo seleccionado previamente</small>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="btnGuardarSubgrupo">
                                <i class="fas fa-save me-1"></i>Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            // Variables de contexto MULTIEMPRESA
            const empresa_idx = 2;
            const pagina_idx = <?php echo $pagina_idx; ?>;
            const pagina_subgrupos_idx = 47; // Página para subgrupos

            // Variables globales
            let grupoSeleccionadoId = null;
            let grupoSeleccionadoNombre = '';
            let gruposData = [];
            let subgruposData = {};

            // ===========================================
            // FUNCIONES DE INICIALIZACIÓN
            // ===========================================

            // Cargar botones principales
            function cargarBotonesPrincipales() {
                $.get('comprobantes_grupos_ajax.php', {
                    accion: 'obtener_boton_agregar',
                    pagina_idx: pagina_idx
                }, function (botonAgregar) {
                    var htmlBotones = '';
                    
                    if (botonAgregar && botonAgregar.nombre_funcion) {
                        var icono = botonAgregar.icono_clase ? `<i class="${botonAgregar.icono_clase} me-1"></i>` : '';
                        var colorClase = 'btn-primary';
                        
                        if (botonAgregar.bg_clase && botonAgregar.text_clase) {
                            colorClase = botonAgregar.bg_clase + ' ' + botonAgregar.text_clase;
                        } else if (botonAgregar.color_clase) {
                            colorClase = botonAgregar.color_clase;
                        }
                        
                        htmlBotones += `
                            <button type="button" class="btn ${colorClase} me-2" id="btnNuevoGrupo">
                                ${icono}${botonAgregar.nombre_funcion}
                            </button>
                        `;
                    } else {
                        htmlBotones += `
                            <button type="button" class="btn btn-primary me-2" id="btnNuevoGrupo">
                                <i class="fas fa-plus me-1"></i>Agregar Grupo
                            </button>
                        `;
                    }
                    
                    // Botón para expandir/colapsar todos
                    htmlBotones += `
                        <button type="button" class="btn btn-outline-info me-2" id="btnExpandirTodo">
                            <i class="fas fa-expand-alt me-1"></i>Expandir Todo
                        </button>
                        <button type="button" class="btn btn-outline-secondary me-2" id="btnColapsarTodo">
                            <i class="fas fa-compress-alt me-1"></i>Colapsar Todo
                        </button>
                    `;
                    
                    $('#contenedor-botones').html(htmlBotones);
                }, 'json');
            }

            // Cargar jerarquía completa
            function cargarJerarquia() {
                $('#arbolComprobantes').html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2">Cargando jerarquía...</p></div>');
                
                $.get('comprobantes_grupos_ajax.php', {
                    accion: 'listar_jerarquia',
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx,
                    pagina_subgrupos_idx: pagina_subgrupos_idx
                }, function (res) {
                    if (res && res.grupos) {
                        gruposData = res.grupos;
                        subgruposData = res.subgrupos || {};
                        
                        // Ordenar grupos por orden
                        gruposData.sort((a, b) => (a.orden || 0) - (b.orden || 0) || a.comprobante_grupo.localeCompare(b.comprobante_grupo));
                        
                        renderizarJerarquia();
                    } else {
                        $('#arbolComprobantes').html('<div class="alert alert-warning">No se encontraron grupos de comprobantes</div>');
                    }
                }, 'json').fail(function() {
                    $('#arbolComprobantes').html('<div class="alert alert-danger">Error al cargar los datos</div>');
                });
            }

            // Renderizar la jerarquía en el DOM
            function renderizarJerarquia() {
                var html = '';
                
                gruposData.forEach(function(grupo) {
                    // Determinar clase CSS según estado
                    var estadoClase = '';
                    if (grupo.estado_info && grupo.estado_info.codigo_estandar === 'INACTIVO') {
                        estadoClase = 'inactivo';
                    } else if (grupo.estado_info && grupo.estado_info.codigo_estandar === 'BLOQUEADO') {
                        estadoClase = 'bloqueado';
                    }
                    
                    // Determinar color del badge de estado
                    var badgeClase = 'bg-secondary';
                    if (grupo.estado_info && grupo.estado_info.codigo_estandar === 'ACTIVO') {
                        badgeClase = 'bg-success';
                    } else if (grupo.estado_info && grupo.estado_info.codigo_estandar === 'INACTIVO') {
                        badgeClase = 'bg-secondary';
                    } else if (grupo.estado_info && grupo.estado_info.codigo_estandar === 'BLOQUEADO') {
                        badgeClase = 'bg-warning';
                    }
                    
                    // Verificar si el grupo tiene subgrupos
                    var tieneSubgrupos = subgruposData[grupo.comprobante_grupo_id] && 
                                       subgruposData[grupo.comprobante_grupo_id].length > 0;
                    
                    // Grupo
                    html += `
                        <div class="list-group-item list-group-item-action list-group-item-grupo ${estadoClase}" 
                             data-id="${grupo.comprobante_grupo_id}" 
                             data-tipo="grupo">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    ${tieneSubgrupos ? `
                                    <button class="btn btn-sm btn-outline-secondary btn-expander-arbre me-2" 
                                            data-id="${grupo.comprobante_grupo_id}"
                                            data-expanded="true">
                                        <i class="fas fa-minus-circle fa-xs"></i>
                                    </button>
                                    ` : '<span class="me-4"></span>'}
                                    <i class="fas fa-folder text-warning me-2"></i>
                                    <div>
                                        <strong>${grupo.comprobante_grupo}</strong>
                                        <div class="text-muted small">
                                            ID: ${grupo.comprobante_grupo_id} | Orden: ${grupo.orden || 0}
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <span class="badge ${badgeClase} badge-estado me-3">
                                        ${grupo.estado_info ? grupo.estado_info.estado_registro : 'Sin estado'}
                                    </span>
                                    <div class="btn-group">
                    `;
                    
                    // Botones de acción del grupo
                    if (grupo.botones && grupo.botones.length > 0) {
                        grupo.botones.forEach(function(boton) {
                            var claseBoton = 'btn-accion-arbre ';
                            if (boton.bg_clase && boton.text_clase) {
                                claseBoton += boton.bg_clase + ' ' + boton.text_clase;
                            } else if (boton.color_clase) {
                                claseBoton += boton.color_clase;
                            } else {
                                claseBoton += 'btn-outline-primary';
                            }
                            
                            var icono = boton.icono_clase ? `<i class="${boton.icono_clase}"></i>` : '';
                            var titulo = boton.descripcion || boton.nombre_funcion;
                            
                            html += `
                                <button type="button" class="btn ${claseBoton}" 
                                        title="${titulo}"
                                        data-id="${grupo.comprobante_grupo_id}"
                                        data-tipo="grupo"
                                        data-accion="${boton.accion_js}"
                                        data-confirmable="${boton.es_confirmable || 0}"
                                        data-nombre="${grupo.comprobante_grupo}">
                                    ${icono}
                                </button>
                            `;
                        });
                    }
                    
                    // Botón para agregar subgrupo
                    html += `
                                        <button type="button" class="btn btn-success btn-sm btn-agregar-subgrupo" 
                                                title="Agregar Subgrupo"
                                                data-grupo-id="${grupo.comprobante_grupo_id}"
                                                data-grupo-nombre="${grupo.comprobante_grupo}">
                                            <i class="fas fa-plus-circle"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Subgrupos (si tiene y están expandidos)
                    if (tieneSubgrupos) {
                        // Ordenar subgrupos
                        var subgruposOrdenados = subgruposData[grupo.comprobante_grupo_id].sort((a, b) => 
                            (a.orden || 0) - (b.orden || 0) || a.comprobante_subgrupo.localeCompare(b.comprobante_subgrupo)
                        );
                        
                        subgruposOrdenados.forEach(function(subgrupo) {
                            // Determinar clase CSS según estado
                            var subEstadoClase = '';
                            if (subgrupo.estado_info && subgrupo.estado_info.codigo_estandar === 'INACTIVO') {
                                subEstadoClase = 'inactivo';
                            } else if (subgrupo.estado_info && subgrupo.estado_info.codigo_estandar === 'BLOQUEADO') {
                                subEstadoClase = 'bloqueado';
                            }
                            
                            // Determinar color del badge de estado
                            var subBadgeClase = 'bg-secondary';
                            if (subgrupo.estado_info && subgrupo.estado_info.codigo_estandar === 'ACTIVO') {
                                subBadgeClase = 'bg-success';
                            } else if (subgrupo.estado_info && subgrupo.estado_info.codigo_estandar === 'INACTIVO') {
                                subBadgeClase = 'bg-secondary';
                            } else if (subgrupo.estado_info && subgrupo.estado_info.codigo_estandar === 'BLOQUEADO') {
                                subBadgeClase = 'bg-warning';
                            }
                            
                            html += `
                                <div class="list-group-item list-group-item-action list-group-item-subgrupo ${subEstadoClase} grupo-${grupo.comprobante_grupo_id}" 
                                     data-id="${subgrupo.comprobante_subgrupo_id}" 
                                     data-tipo="subgrupo"
                                     data-grupo-padre="${grupo.comprobante_grupo_id}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <span class="me-4"></span> <!-- Espacio para alinear con el botón expander -->
                                            <i class="fas fa-folder-open text-info me-2"></i>
                                            <div>
                                                ${subgrupo.comprobante_subgrupo}
                                                <div class="text-muted small">
                                                    ID: ${subgrupo.comprobante_subgrupo_id} | Orden: ${subgrupo.orden || 0}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <span class="badge ${subBadgeClase} badge-estado me-3">
                                                ${subgrupo.estado_info ? subgrupo.estado_info.estado_registro : 'Sin estado'}
                                            </span>
                                            <div class="btn-group">
                            `;
                            
                            // Botones de acción del subgrupo
                            if (subgrupo.botones && subgrupo.botones.length > 0) {
                                subgrupo.botones.forEach(function(boton) {
                                    var claseBoton = 'btn-accion-arbre ';
                                    if (boton.bg_clase && boton.text_clase) {
                                        claseBoton += boton.bg_clase + ' ' + boton.text_clase;
                                    } else if (boton.color_clase) {
                                        claseBoton += boton.color_clase;
                                    } else {
                                        claseBoton += 'btn-outline-primary';
                                    }
                                    
                                    var icono = boton.icono_clase ? `<i class="${boton.icono_clase}"></i>` : '';
                                    var titulo = boton.descripcion || boton.nombre_funcion;
                                    
                                    html += `
                                        <button type="button" class="btn ${claseBoton}" 
                                                title="${titulo}"
                                                data-id="${subgrupo.comprobante_subgrupo_id}"
                                                data-tipo="subgrupo"
                                                data-accion="${boton.accion_js}"
                                                data-confirmable="${boton.es_confirmable || 0}"
                                                data-nombre="${subgrupo.comprobante_subgrupo}">
                                            ${icono}
                                        </button>
                                    `;
                                });
                            }
                            
                            html += `
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                    }
                });
                
                $('#arbolComprobantes').html(html || '<div class="alert alert-info">No hay grupos de comprobantes registrados</div>');
            }

            // ===========================================
            // MANEJO DE EXPANSIÓN/COLAPSO
            // ===========================================

            $(document).on('click', '.btn-expander-arbre', function() {
                var btn = $(this);
                var grupoId = btn.data('id');
                var expanded = btn.data('expanded') === true;
                var icon = btn.find('i');
                
                if (expanded) {
                    // Colapsar
                    $(`.grupo-${grupoId}`).slideUp(200);
                    icon.removeClass('fa-minus-circle').addClass('fa-plus-circle');
                    btn.data('expanded', false);
                } else {
                    // Expandir
                    $(`.grupo-${grupoId}`).slideDown(200);
                    icon.removeClass('fa-plus-circle').addClass('fa-minus-circle');
                    btn.data('expanded', true);
                }
            });

            // Expandir todos
            $('#btnExpandirTodo').click(function() {
                $('.btn-expander-arbre').each(function() {
                    var btn = $(this);
                    if (!btn.data('expanded')) {
                        btn.trigger('click');
                    }
                });
            });

            // Colapsar todos
            $('#btnColapsarTodo').click(function() {
                $('.btn-expander-arbre').each(function() {
                    var btn = $(this);
                    if (btn.data('expanded')) {
                        btn.trigger('click');
                    }
                });
            });

            // ===========================================
            // MANEJO DE GRUPOS
            // ===========================================

            $(document).on('click', '#btnNuevoGrupo', function () {
                resetModalGrupo();
                $('#modalLabel').text('Nuevo Grupo de Comprobante');
                $('#orden_grupo').val(0);

                var modal = new bootstrap.Modal(document.getElementById('modalComprobanteGrupo'));
                modal.show();
                $('#comprobante_grupo').focus();
            });

            // ===========================================
            // MANEJO DE SUBGRUPOS
            // ===========================================

            $(document).on('click', '.btn-agregar-subgrupo', function () {
                grupoSeleccionadoId = $(this).data('grupo-id');
                grupoSeleccionadoNombre = $(this).data('grupo-nombre');
                
                resetModalSubgrupo();
                $('#modalSubgrupoLabel').text('Nuevo Subgrupo');
                $('#orden_subgrupo').val(0);
                $('#grupo_padre_id').val(grupoSeleccionadoId);
                $('#nombre_grupo_padre').text(grupoSeleccionadoNombre);

                var modal = new bootstrap.Modal(document.getElementById('modalComprobanteSubgrupo'));
                modal.show();
                $('#comprobante_subgrupo').focus();
            });

            // ===========================================
            // MANEJO DE ACCIONES
            // ===========================================

            $(document).on('click', '.btn-accion-arbre', function () {
                var registroId = $(this).data('id');
                var tipo = $(this).data('tipo');
                var accionJs = $(this).data('accion');
                var confirmable = $(this).data('confirmable');
                var nombreRegistro = $(this).data('nombre');

                if (accionJs === 'editar') {
                    if (tipo === 'grupo') {
                        cargarGrupoParaEditar(registroId);
                    } else if (tipo === 'subgrupo') {
                        cargarSubgrupoParaEditar(registroId);
                    }
                } else if (confirmable == 1) {
                    Swal.fire({
                        title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                        html: `¿Está seguro de <strong>${accionJs}</strong> el ${tipo} <strong>"${nombreRegistro}"</strong>?`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: `Sí, ${accionJs}`,
                        cancelButtonText: 'Cancelar',
                        reverseButtons: true,
                        allowOutsideClick: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            ejecutarAccion(registroId, tipo, accionJs, nombreRegistro);
                        }
                    });
                } else {
                    ejecutarAccion(registroId, tipo, accionJs, nombreRegistro);
                }
            });

            function ejecutarAccion(registroId, tipo, accionJs, nombreRegistro) {
                var endpoint = tipo === 'grupo' ? 'ejecutar_accion_grupo' : 'ejecutar_accion_subgrupo';
                var pagina = tipo === 'grupo' ? pagina_idx : pagina_subgrupos_idx;
                
                $.post('comprobantes_grupos_ajax.php', {
                    accion: endpoint,
                    comprobante_grupo_id: tipo === 'grupo' ? registroId : null,
                    comprobante_subgrupo_id: tipo === 'subgrupo' ? registroId : null,
                    accion_js: accionJs,
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina
                }, function (res) {
                    if (res.success) {
                        cargarJerarquia(); // Recargar la jerarquía completa
                        Swal.fire({
                            icon: "success",
                            title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                            text: res.message || `${tipo === 'grupo' ? 'Grupo' : 'Subgrupo'} "${nombreRegistro}" actualizado correctamente`,
                            showConfirmButton: false,
                            timer: 1500,
                            toast: true,
                            position: 'top-end'
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || `Error al ${accionJs} el ${tipo}`,
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            // ===========================================
            // FUNCIONES DE EDICIÓN
            // ===========================================

            function cargarGrupoParaEditar(grupoId) {
                $.get('comprobantes_grupos_ajax.php', {
                    accion: 'obtener_grupo',
                    comprobante_grupo_id: grupoId,
                    empresa_idx: empresa_idx
                }, function (res) {
                    if (res && res.comprobante_grupo_id) {
                        resetModalGrupo();
                        $('#comprobante_grupo_id').val(res.comprobante_grupo_id);
                        $('#comprobante_grupo').val(res.comprobante_grupo);
                        $('#orden_grupo').val(res.orden || 0);
                        $('#modalLabel').text('Editar Grupo de Comprobante');

                        var modal = new bootstrap.Modal(document.getElementById('modalComprobanteGrupo'));
                        modal.show();

                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "Error al obtener datos del grupo",
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            function cargarSubgrupoParaEditar(subgrupoId) {
                $.get('comprobantes_grupos_ajax.php', {
                    accion: 'obtener_subgrupo',
                    comprobante_subgrupo_id: subgrupoId,
                    empresa_idx: empresa_idx
                }, function (res) {
                    if (res && res.comprobante_subgrupo_id) {
                        resetModalSubgrupo();
                        $('#comprobante_subgrupo_id').val(res.comprobante_subgrupo_id);
                        $('#comprobante_subgrupo').val(res.comprobante_subgrupo);
                        $('#orden_subgrupo').val(res.orden || 0);
                        $('#grupo_padre_id').val(res.comprobante_grupo_id);
                        $('#modalSubgrupoLabel').text('Editar Subgrupo de Comprobante');
                        
                        // Obtener nombre del grupo padre
                        $.get('comprobantes_grupos_ajax.php', {
                            accion: 'obtener_grupo',
                            comprobante_grupo_id: res.comprobante_grupo_id,
                            empresa_idx: empresa_idx
                        }, function(grupoRes) {
                            if (grupoRes && grupoRes.comprobante_grupo) {
                                $('#nombre_grupo_padre').text(grupoRes.comprobante_grupo);
                            }
                        });

                        var modal = new bootstrap.Modal(document.getElementById('modalComprobanteSubgrupo'));
                        modal.show();

                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "Error al obtener datos del subgrupo",
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            // ===========================================
            // FUNCIONES DE GUARDADO
            // ===========================================

            $('#btnGuardarGrupo').click(function () {
                var form = document.getElementById('formComprobanteGrupo');

                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var id = $('#comprobante_grupo_id').val();
                var accionBackend = id ? 'editar_grupo' : 'agregar_grupo';
                var grupoNombre = $('#comprobante_grupo').val().trim();
                var orden = $('#orden_grupo').val() || 0;

                if (!grupoNombre) {
                    $('#comprobante_grupo').addClass('is-invalid');
                    return false;
                }

                var btnGuardar = $(this);
                var originalText = btnGuardar.html();
                btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

                $.ajax({
                    url: 'comprobantes_grupos_ajax.php',
                    type: 'POST',
                    data: {
                        accion: accionBackend,
                        comprobante_grupo_id: id,
                        comprobante_grupo: grupoNombre,
                        orden: orden,
                        empresa_idx: empresa_idx,
                        pagina_idx: pagina_idx
                    },
                    success: function (res) {
                        if (res.resultado) {
                            cargarJerarquia();
                            var modalEl = document.getElementById('modalComprobanteGrupo');
                            var modal = bootstrap.Modal.getInstance(modalEl);

                            btnGuardar.prop('disabled', false).html(originalText);

                            Swal.fire({
                                icon: "success",
                                title: "¡Guardado!",
                                text: "Grupo de comprobante guardado correctamente",
                                showConfirmButton: false,
                                timer: 1500,
                                toast: true,
                                position: 'top-end'
                            });
                            modal.hide();
                        } else {
                            btnGuardar.prop('disabled', false).html(originalText);
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: res.error || "Error al guardar los datos",
                                confirmButtonText: "Entendido"
                            });
                        }
                    },
                    error: function () {
                        btnGuardar.prop('disabled', false).html(originalText);
                        Swal.fire({
                            icon: "error",
                            title: "Error de conexión",
                            text: "Error al comunicarse con el servidor",
                            confirmButtonText: "Entendido"
                        });
                    }
                });
            });

            $('#btnGuardarSubgrupo').click(function () {
                var form = document.getElementById('formComprobanteSubgrupo');

                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var id = $('#comprobante_subgrupo_id').val();
                var accionBackend = id ? 'editar_subgrupo' : 'agregar_subgrupo';
                var subgrupoNombre = $('#comprobante_subgrupo').val().trim();
                var grupoPadreId = $('#grupo_padre_id').val();
                var orden = $('#orden_subgrupo').val() || 0;

                if (!subgrupoNombre || !grupoPadreId) {
                    if (!subgrupoNombre) $('#comprobante_subgrupo').addClass('is-invalid');
                    return false;
                }

                var btnGuardar = $(this);
                var originalText = btnGuardar.html();
                btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

                $.ajax({
                    url: 'comprobantes_grupos_ajax.php',
                    type: 'POST',
                    data: {
                        accion: accionBackend,
                        comprobante_subgrupo_id: id,
                        comprobante_grupo_id: grupoPadreId,
                        comprobante_subgrupo: subgrupoNombre,
                        orden: orden,
                        empresa_idx: empresa_idx,
                        pagina_idx: pagina_subgrupos_idx
                    },
                    success: function (res) {
                        if (res.resultado) {
                            cargarJerarquia();
                            var modalEl = document.getElementById('modalComprobanteSubgrupo');
                            var modal = bootstrap.Modal.getInstance(modalEl);

                            btnGuardar.prop('disabled', false).html(originalText);

                            Swal.fire({
                                icon: "success",
                                title: "¡Guardado!",
                                text: "Subgrupo de comprobante guardado correctamente",
                                showConfirmButton: false,
                                timer: 1500,
                                toast: true,
                                position: 'top-end'
                            });
                            modal.hide();
                        } else {
                            btnGuardar.prop('disabled', false).html(originalText);
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: res.error || "Error al guardar los datos",
                                confirmButtonText: "Entendido"
                            });
                        }
                    },
                    error: function () {
                        btnGuardar.prop('disabled', false).html(originalText);
                        Swal.fire({
                            icon: "error",
                            title: "Error de conexión",
                            text: "Error al comunicarse con el servidor",
                            confirmButtonText: "Entendido"
                        });
                    }
                });
            });

            // ===========================================
            // FUNCIONES DE RESET
            // ===========================================

            function resetModalGrupo() {
                $('#formComprobanteGrupo')[0].reset();
                $('#comprobante_grupo_id').val('');
                $('#formComprobanteGrupo').removeClass('was-validated');
            }

            function resetModalSubgrupo() {
                $('#formComprobanteSubgrupo')[0].reset();
                $('#comprobante_subgrupo_id').val('');
                $('#grupo_padre_id').val('');
                $('#nombre_grupo_padre').text('Seleccionar grupo primero');
                $('#formComprobanteSubgrupo').removeClass('was-validated');
            }

            // ===========================================
            // INICIALIZACIÓN
            // ===========================================

            cargarBotonesPrincipales();
            cargarJerarquia();

            // Botón recargar
            $('#btnRecargar').click(function () {
                var btn = $(this);
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                cargarJerarquia();
                setTimeout(() => {
                    btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
                }, 500);
            });
        });
    </script>
    
    <style>
        .list-group-item-grupo {
            background-color: #f8f9fa;
            border-left: 4px solid #ffc107;
            font-weight: bold;
            padding-left: 15px;
        }
        
        .list-group-item-subgrupo {
            background-color: white;
            border-left: 4px solid #0dcaf0;
            padding-left: 40px;
        }
        
        .list-group-item.inactivo {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .list-group-item.bloqueado {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .badge-estado {
            min-width: 80px;
        }
        
        .btn-accion-arbre {
            padding: 2px 6px;
            font-size: 12px;
            margin-right: 3px;
        }
        
        .btn-expander-arbre {
            width: 24px;
            height: 24px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
        }
        
        .nombre-celda {
            min-width: 300px;
        }
        
        .btn-agregar-subgrupo {
            margin-left: 5px;
        }
        
        /* Resaltar fila al pasar el mouse */
        .list-group-item-action:hover {
            background-color: rgba(0, 123, 255, 0.1) !important;
        }
    </style>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>

</html>