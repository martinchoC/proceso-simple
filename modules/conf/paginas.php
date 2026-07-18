<?php
// Configuración de la página
$pageTitle = "Gestión de Paginas";
$currentPage = 'paginas';
$modudo_idx = 1;

// Definir constante para rutas
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));

// Incluir header
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>
<main class="app-main">
    <!--begin::App Content Header-->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Gestión de Páginas</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Páginas</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <!--end::App Content Header-->
    
    <!--begin::App Content-->
    <div class="app-content">
        <div class="container-fluid">
            <!-- Content Wrapper -->
            <div class="content-wrapper">
                <section class="content">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h4 class="card-title">Estructura de Páginas</h4>
                                            <div>
                                                <button class="btn btn-primary btn-sm" id="btnNuevo">
                                                    <i class="fas fa-plus"></i> Nueva Página
                                                </button>
                                                <button class="btn btn-success btn-sm" id="btnExpandirTodo">
                                                    <i class="fas fa-expand"></i> Expandir Todo
                                                </button>
                                                <button class="btn btn-warning btn-sm" id="btnColapsarTodo">
                                                    <i class="fas fa-compress"></i> Colapsar Todo
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <!-- Filtro por módulo -->
                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label>Filtrar por Módulo:</label>
                                                    <select class="form-control form-control-sm" id="filtroModulo">
                                                        <option value="">Todos los módulos</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label>Buscar página:</label>
                                                    <input type="text" class="form-control form-control-sm" id="buscarPagina" placeholder="Escriba para buscar...">
                                                </div>
                                            </div>
                                            <div class="col-md-4 d-flex align-items-end">
                                                <div class="btn-group">
                                                    <button class="btn btn-outline-info btn-sm" id="vistaArbol">
                                                        <i class="fas fa-sitemap"></i> Vista Árbol
                                                    </button>
                                                    <button class="btn btn-outline-secondary btn-sm" id="vistaTabla">
                                                        <i class="fas fa-table"></i> Vista Tabla
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Vista de Árbol -->
                                        <div id="vistaArbolContainer">
                                            <div id="arbolPaginas" class="jstree">
                                                <!-- El árbol se cargará vía AJAX -->
                                                <div class="text-center py-4">
                                                    <div class="spinner-border text-primary" role="status">
                                                        <span class="visually-hidden">Cargando...</span>
                                                    </div>
                                                    <p class="mt-2 text-muted">Cargando estructura de páginas...</p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Vista de Tabla (oculta por defecto) -->
                                        <div id="vistaTablaContainer" style="display: none;">
                                            <table id="tablapaginas" class="table table-striped table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Id</th>
                                                        <th>Módulo</th>
                                                        <th>Página</th>
                                                        <th>URL</th>
                                                        <th>Icono</th>
                                                        <th>Descripción</th>
                                                        <th>Padre</th>
                                                        <th>Tabla</th>
                                                        <th>Orden</th>
                                                        <th>Estado</th>
                                                        <th>Funciones</th>
                                                        <th>Acciones</th>
                                                    </tr>
                                                </thead>
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

<!-- Modal página -->
<div class="modal fade" id="modalpagina" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabel">Página</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formpagina">
                    <input type="hidden" id="pagina_id" name="pagina_id" />
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label>Módulo <span class="text-danger">*</span></label>
                            <select class="form-control" id="modulo_id" name="modulo_id" required>
                                <option value="">Seleccionar Módulo</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>Nombre de la Página <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="pagina" name="pagina" required/>
                        </div>
                        <div class="col-md-6">
                            <label>URL</label>
                            <input type="text" class="form-control" id="url" name="url" placeholder="ej: modulo/pagina"/>
                        </div>
                        <div class="col-md-6">
                            <label>Descripción</label>
                            <input type="text" class="form-control" id="pagina_descripcion" name="pagina_descripcion"/>
                        </div>
                        <div class="col-md-6">
                            <label>Icono</label>
                            <select class="form-control" id="icono_id" name="icono_id">
                                <option value="">Seleccionar Icono</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>Orden</label>
                            <input type="number" class="form-control" id="orden" name="orden" value="0"/>
                        </div>
                        <div class="col-md-6">
                            <label>Tabla Asociada</label>
                            <select class="form-control" id="tabla_id" name="tabla_id">
                                <option value="">Seleccionar Tabla</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>Página Padre</label>
                            <select class="form-control" id="padre_id" name="padre_id">
                                <option value="">Ninguno (página principal)</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label>Estado</label>
                            <select class="form-control" id="tabla_estado_registro_id" name="tabla_estado_registro_id">
                                <option value="1">Activo</option>
                                <option value="2">Inactivo</option>
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

<!-- Modal para copiar funciones -->
<div class="modal fade" id="modalCopiarFunciones" tabindex="-1" aria-labelledby="modalCopiarFuncionesLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCopiarFuncionesLabel">Copiar Funciones de Tipo de Tabla</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="mensajeCopiarFunciones">
                    <p>Esta página está asociada a una tabla con tipo de funciones predefinidas.</p>
                    <p>¿Desea copiar las funciones estándar para esta página?</p>
                    <div class="alert alert-info mt-3">
                        <small>Nota: Solo se agregarán las funciones nuevas. Las que ya existen no se duplicarán.</small>
                    </div>
                </div>
                <div id="listaFunciones" style="display: none;">
                    <h6>Funciones disponibles:</h6>
                    <ul id="listaFuncionesItems" class="list-group"></ul>
                </div>
            </div>
            <div class="modal-footer">
                <button id="btnCopiarFunciones" class="btn btn-primary">Sí, copiar funciones</button>
                <button id="btnNoCopiarFunciones" class="btn btn-secondary">No, dejar como está</button>
                <button id="btnCancelarCopiar" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para visualizar funciones de una página -->
<div class="modal fade" id="modalVerFunciones" tabindex="-1" aria-labelledby="modalVerFuncionesLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalVerFuncionesLabel">Funciones de la Página</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="infoPagina" class="mb-3">
                    <h6 id="nombrePagina"></h6>
                    <p id="descripcionPagina" class="text-muted"></p>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-sm" id="tablaFunciones">
                        <thead class="table-light">
                            <tr>
                                <th width="50">#</th>
                                <th>Nombre</th>
                                <th>Icono</th>
                                <th>Acción JS</th>
                                <th>Estados</th>
                                <th>Descripción</th>
                                <th width="80">Orden</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpoTablaFunciones">
                        </tbody>
                    </table>
                </div>
                
                <div id="sinFunciones" class="text-center py-5" style="display: none;">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No hay funciones asignadas</h5>
                    <p class="text-muted">Esta página no tiene funciones asignadas.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para arrastrar y reordenar en el árbol -->
<div class="modal fade" id="modalMoverPagina" tabindex="-1" aria-labelledby="modalMoverPaginaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalMoverPaginaLabel">Mover Página</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p>¿Desea mover la página <strong id="moverPaginaNombre"></strong> a la posición indicada?</p>
                <p class="text-muted small">El orden se actualizará automáticamente.</p>
            </div>
            <div class="modal-footer">
                <button id="btnConfirmarMover" class="btn btn-primary">Confirmar</button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/jstree.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/themes/default/style.min.css" />
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Variables globales
var tabla;
var arbolInstance = null;
var paginaIdParaCopiar = null;
var tablaTipoIdParaCopiar = null;
var vistaActual = 'arbol'; // 'arbol' o 'tabla'

// Función para mostrar el modal de visualizar funciones
function mostrarModalVerFunciones(pagina_id, pagina_nombre, pagina_descripcion) {
    $('#nombrePagina').text(pagina_nombre);
    $('#descripcionPagina').text(pagina_descripcion || 'Sin descripción');
    
    $('#cuerpoTablaFunciones').html(`
        <tr>
            <td colspan="7" class="text-center">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                Cargando funciones...
            </td>
        </tr>
    `);
    $('#sinFunciones').hide();
    $('#tablaFunciones').show();
    
    $.ajax({
        url: 'paginas_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerFuncionesPorPagina', pagina_id: pagina_id},
        dataType: 'json',
        success: function(funciones) {
            if(funciones && funciones.length > 0) {
                var html = '';
                $.each(funciones, function(index, funcion) {
                    var colorClass = funcion.color_clase ? funcion.color_clase : 'bg-secondary';
                    var iconoHtml = '';
                    if (funcion.icono_clase) {
                        iconoHtml = `<i class="${funcion.icono_clase}"></i>`;
                    } else if (funcion.icono_nombre) {
                        iconoHtml = `<span class="badge bg-light text-dark">${funcion.icono_nombre}</span>`;
                    }
                    
                    var estadosHtml = '';
                    if (funcion.origen_nombre && funcion.destino_nombre) {
                        estadosHtml = `
                            <div class="d-flex align-items-center">
                                <span class="badge bg-light text-dark me-1">${funcion.origen_nombre}</span>
                                <i class="fas fa-arrow-right text-muted mx-1"></i>
                                <span class="badge bg-success">${funcion.destino_nombre}</span>
                            </div>
                        `;
                    } else if (funcion.tabla_estado_registro_origen_id || funcion.tabla_estado_registro_destino_id) {
                        estadosHtml = `
                            <div class="text-muted small">
                                ${funcion.tabla_estado_registro_origen_id || '0'} → ${funcion.tabla_estado_registro_destino_id || '0'}
                            </div>
                        `;
                    }
                    
                    html += `
                    <tr>
                        <td class="text-center">${index + 1}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                ${iconoHtml ? `<div class="me-2">${iconoHtml}</div>` : ''}
                                <div>
                                    <strong>${funcion.nombre_funcion}</strong>
                                    ${funcion.color_nombre ? `<span class="badge ${colorClass} ms-2">${funcion.color_nombre}</span>` : ''}
                                </div>
                            </div>
                        </td>
                        <td class="text-center">${iconoHtml || '<span class="text-muted">-</span>'}</td>
                        <td><code class="text-primary">${funcion.accion_js || '<span class="text-muted">No definida</span>'}</code></td>
                        <td>${estadosHtml || '<span class="text-muted">-</span>'}</td>
                        <td class="small">${funcion.descripcion || '<span class="text-muted">Sin descripción</span>'}</td>
                        <td class="text-center">${funcion.orden}</td>
                    </tr>
                    `;
                });
                $('#cuerpoTablaFunciones').html(html);
            } else {
                $('#tablaFunciones').hide();
                $('#sinFunciones').show();
            }
        },
        error: function() {
            $('#cuerpoTablaFunciones').html(`
                <tr>
                    <td colspan="7" class="text-center text-danger">
                        <i class="fas fa-exclamation-triangle"></i> Error al cargar las funciones
                    </td>
                </tr>
            `);
        }
    });
    
    var modal = new bootstrap.Modal(document.getElementById('modalVerFunciones'));
    modal.show();
}

// Función para mostrar el modal de copiar funciones
function mostrarModalCopiarFunciones(pagina_id, tabla_tipo_id) {
    paginaIdParaCopiar = pagina_id;
    tablaTipoIdParaCopiar = tabla_tipo_id;
    
    $.ajax({
        url: 'paginas_ajax.php',
        type: 'GET',
        data: {accion: 'verificarFunciones', pagina_id: pagina_id},
        dataType: 'json',
        success: function(res) {
            if (res.tiene_funciones) {
                $('#mensajeCopiarFunciones p:first').html('Esta página ya tiene algunas funciones asignadas.');
                $('#mensajeCopiarFunciones p:eq(1)').html('¿Desea copiar las funciones adicionales del tipo de tabla?');
                $('.alert-info small').text('Nota: Solo se agregarán las funciones nuevas. Las existentes no se duplicarán.');
            } else {
                $('#mensajeCopiarFunciones p:first').html('Esta página está asociada a una tabla con tipo de funciones predefinidas.');
                $('#mensajeCopiarFunciones p:eq(1)').html('¿Desea copiar las funciones estándar para esta página?');
                $('.alert-info small').text('Nota: Solo se agregarán las funciones. No se crearán duplicados.');
            }
        }
    });
    
    var modal = new bootstrap.Modal(document.getElementById('modalCopiarFunciones'));
    modal.show();
    
    if (tabla_tipo_id) {
        obtenerFuncionesPorTipo(tabla_tipo_id);
    }
}

function obtenerFuncionesPorTipo(tabla_tipo_id) {
    $.ajax({
        url: 'paginas_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerFuncionesPorTipo', tabla_tipo_id: tabla_tipo_id},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var html = '<h6>Funciones disponibles:</h6><ul class="list-group">';
                $.each(res, function(index, funcion) {
                    var estados = '';
                    if (funcion.tabla_estado_registro_origen_id || funcion.tabla_estado_registro_destino_id) {
                        estados = `<br><small class="text-muted">Estados: ${funcion.tabla_estado_registro_origen_id || '0'} → ${funcion.tabla_estado_registro_destino_id || '0'}</small>`;
                    }
                    
                    html += `<li class="list-group-item">
                        <strong>${funcion.nombre_funcion}</strong>
                        ${funcion.descripcion ? `<br><small class="text-muted">${funcion.descripcion}</small>` : ''}
                        ${estados}
                    </li>`;
                });
                html += '</ul>';
                $('#listaFuncionesItems').html(html);
                $('#listaFunciones').show();
            } else {
                $('#listaFunciones').hide();
            }
        },
        error: function() {
            console.error('Error al obtener funciones');
            $('#listaFunciones').hide();
        }
    });
}

function copiarFunciones() {
    if (!paginaIdParaCopiar || !tablaTipoIdParaCopiar) {
        Swal.fire('Error', 'Datos incompletos', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Copiando funciones...',
        text: 'Por favor espere',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    $.ajax({
        url: 'paginas_ajax.php',
        type: 'GET',
        data: {
            accion: 'copiarFunciones',
            pagina_id: paginaIdParaCopiar,
            tabla_tipo_id: tablaTipoIdParaCopiar
        },
        dataType: 'json',
        timeout: 30000,
        success: function(res) {
            var modal = bootstrap.Modal.getInstance(document.getElementById('modalCopiarFunciones'));
            if (modal) {
                modal.hide();
            }
            
            if(res && res.resultado) {
                if (typeof tabla !== 'undefined' && tabla) {
                    tabla.ajax.reload(function() {
                        $.ajax({
                            url: 'paginas_ajax.php',
                            type: 'GET',
                            data: {accion: 'obtenerResultadoCopia'},
                            dataType: 'json',
                            timeout: 10000,
                            success: function(resultado) {
                                Swal.close();
                                cargarArbol();
                                
                                if (resultado) {
                                    let mensaje = '';
                                    if (resultado.nuevas > 0 && resultado.existentes > 0) {
                                        mensaje = `Se agregaron ${resultado.nuevas} funciones nuevas. ${resultado.existentes} funciones ya existían y fueron omitidas.`;
                                    } else if (resultado.nuevas > 0) {
                                        mensaje = `Se agregaron ${resultado.nuevas} funciones correctamente.`;
                                    } else if (resultado.existentes > 0) {
                                        mensaje = `No se agregaron funciones nuevas. Todas las funciones (${resultado.existentes}) ya existían en la página.`;
                                    } else {
                                        mensaje = 'No se encontraron funciones para copiar.';
                                    }
                                    
                                    Swal.fire({
                                        icon: resultado.nuevas > 0 ? "success" : "info",
                                        title: resultado.nuevas > 0 ? "Funciones copiadas" : "Sin funciones nuevas",
                                        text: mensaje,
                                        showConfirmButton: true
                                    });
                                } else {
                                    Swal.fire({
                                        icon: "success",
                                        title: "Funciones copiadas",
                                        text: "Las funciones se han procesado exitosamente",
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                }
                            },
                            error: function() {
                                Swal.close();
                                cargarArbol();
                                Swal.fire({
                                    icon: "success",
                                    title: "Funciones copiadas",
                                    text: "Las funciones se han copiado exitosamente",
                                    showConfirmButton: false,
                                    timer: 1500
                                });
                            }
                        });
                    }, false);
                } else {
                    Swal.close();
                    cargarArbol();
                    Swal.fire({
                        icon: "success",
                        title: "Funciones copiadas",
                        text: "Las funciones se han copiado exitosamente",
                        showConfirmButton: false,
                        timer: 1500
                    });
                }
            } else {
                Swal.close();
                Swal.fire('Error', res?.error || 'Error al copiar funciones', 'error');
            }
        },
        error: function() {
            Swal.close();
            var modal = bootstrap.Modal.getInstance(document.getElementById('modalCopiarFunciones'));
            if (modal) {
                modal.hide();
            }
            cargarArbol();
            Swal.fire({
                icon: "warning",
                title: "Proceso completado",
                text: "Las funciones se copiaron pero hubo un error en la respuesta",
                showConfirmButton: true
            });
        }
    });
}

// Cargar módulos para el filtro y el formulario
function cargarModulos(selectedId = null, callback = null) {
    $.ajax({
        url: 'paginas_ajax.php',
        type: 'GET',
        data: {accion: 'obtener_Modulos'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar Módulo</option>';
                var filtroOptions = '<option value="">Todos los módulos</option>';
                $.each(res, function(index, modulo) {
                    var selected = (selectedId == modulo.modulo_id) ? 'selected' : '';
                    options += `<option value="${modulo.modulo_id}" ${selected}>${modulo.modulo}</option>`;
                    filtroOptions += `<option value="${modulo.modulo_id}">${modulo.modulo}</option>`;
                });
                $('#modulo_id').html(options);
                $('#filtroModulo').html(filtroOptions);
                
                if (typeof callback === 'function') {
                    callback();
                }
            }
        },
        error: function() {
            $('#modulo_id').html('<option value="">Error al cargar Módulos</option>');
            $('#filtroModulo').html('<option value="">Error al cargar Módulos</option>');
        }
    });
}

function cargarPaginasPadre(selectedId = null, moduloId = null) {
    $.ajax({
        url: 'paginas_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerPadre', modulo_id: moduloId},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Ninguno (página principal)</option>';
                var currentPageId = $('#pagina_id').val();
                $.each(res, function(index, pagina) {
                    if (!currentPageId || pagina.pagina_id != currentPageId) {
                        var selected = (selectedId == pagina.pagina_id) ? 'selected' : '';
                        options += `<option value="${pagina.pagina_id}" ${selected}>${'&nbsp;'.repeat(pagina.nivel * 4)}${pagina.pagina}</option>`;
                    }
                });
                $('#padre_id').html(options);
            }
        },
        error: function() {
            $('#padre_id').html('<option value="">Error al cargar páginas padre</option>');
        }
    });
}

function cargarTablas(selectedId = null) {
    $.ajax({
        url: 'paginas_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerTablas'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar tabla</option>';
                $.each(res, function(index, tabla) {
                    var selected = (selectedId == tabla.tabla_id) ? 'selected' : '';
                    options += `<option value="${tabla.tabla_id}" ${selected}>${tabla.tabla_nombre}</option>`;
                });
                $('#tabla_id').html(options);
            }
        },
        error: function() {
            $('#tabla_id').html('<option value="">Error al cargar tablas</option>');
        }
    });
}

function cargarIconos(selectedId = null) {
    $.ajax({
        url: 'paginas_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerIconos'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar Icono</option>';
                $.each(res, function(index, icono) {
                    var selected = (selectedId == icono.icono_id) ? 'selected' : '';
                    var iconPreview = icono.icono_clase ? `<i class="${icono.icono_clase}"></i> ` : '';
                    options += `<option value="${icono.icono_id}" ${selected}>${iconPreview}${icono.icono_nombre}</option>`;
                });
                $('#icono_id').html(options);
            }
        },
        error: function() {
            $('#icono_id').html('<option value="">Error al cargar iconos</option>');
        }
    });
}

// Función para cargar el árbol de páginas (reemplazar la existente)
function cargarArbol(filtroModulo = null, textoBusqueda = null) {
    console.log('Cargando árbol con filtro:', filtroModulo, 'búsqueda:', textoBusqueda);
    
    $.ajax({
        url: 'paginas_ajax.php',
        type: 'GET',
        data: {
            accion: 'obtenerArbol',
            modulo_id: filtroModulo || '',
            busqueda: textoBusqueda || ''
        },
        dataType: 'json',
        success: function(data) {
            console.log('Datos del árbol recibidos:', data);
            
            if ($('#arbolPaginas').length) {
                var container = $('#arbolPaginas');
                
                // Si ya existe una instancia de jstree, destruirla
                if (container.hasClass('jstree')) {
                    container.jstree('destroy');
                    container.empty();
                }
                
                // Si no hay datos, mostrar mensaje
                if (!data || data.length === 0) {
                    container.html('<div class="text-center py-4 text-muted">No hay páginas disponibles</div>');
                    return;
                }
                
                // Inicializar jstree
                container.jstree({
                    core: {
                        data: data,
                        animation: 200,
                        check_callback: true,
                        themes: {
                            name: 'default',
                            responsive: true,
                            variant: 'large'
                        },
                        multiple: false
                    },
                    plugins: ['dnd', 'search', 'state', 'types', 'contextmenu'],
                    dnd: {
                        is_draggable: function(node) {
                            return node.type !== 'modulo';
                        },
                        drop_finish: function(data) {
                            var node = data.node;
                            var parent = data.parent;
                            var position = data.position;
                            
                            if (node && parent && node.type !== 'modulo') {
                                actualizarOrdenArbol(node.id, parent, position);
                            }
                        }
                    },
                    search: {
                        case_insensitive: true,
                        show_only_matches: true
                    },
                    types: {
                        modulo: {
                            icon: 'fas fa-folder-open text-warning',
                            valid_children: ['activo', 'inactivo', 'pagina']
                        },
                        pagina: {
                            icon: 'fas fa-file-alt',
                            valid_children: ['activo', 'inactivo', 'pagina']
                        },
                        activo: {
                            icon: 'fas fa-file-alt text-success',
                            valid_children: ['activo', 'inactivo', 'pagina']
                        },
                        inactivo: {
                            icon: 'fas fa-file-alt text-danger',
                            valid_children: ['activo', 'inactivo', 'pagina']
                        }
                    },
                    contextmenu: {
                        items: function(node) {
                            var items = {};
                            
                            if (node.type === 'modulo') {
                                var moduloId = node.id.replace('modulo_', '');
                                items = {
                                    addPage: {
                                        label: 'Agregar página',
                                        action: function() {
                                            $('#modulo_id').val(moduloId);
                                            $('#padre_id').val('');
                                            $('#pagina_id').val('');
                                            $('#formpagina')[0].reset();
                                            $('#modalLabel').text('Nueva Página');
                                            var modal = new bootstrap.Modal(document.getElementById('modalpagina'));
                                            modal.show();
                                        },
                                        icon: 'fas fa-plus'
                                    }
                                };
                            } else if (node.type === 'pagina' || node.type === 'activo' || node.type === 'inactivo') {
                                var paginaId = node.id.replace('pagina_', '');
                                items = {
                                    addChild: {
                                        label: 'Agregar subpágina',
                                        action: function() {
                                            $('#pagina_id').val('');
                                            $('#padre_id').val(paginaId);
                                            $('#formpagina')[0].reset();
                                            $('#modalLabel').text('Nueva Subpágina');
                                            var modal = new bootstrap.Modal(document.getElementById('modalpagina'));
                                            modal.show();
                                        },
                                        icon: 'fas fa-plus'
                                    },
                                    edit: {
                                        label: 'Editar',
                                        action: function() {
                                            editarPagina(paginaId);
                                        },
                                        icon: 'fas fa-edit'
                                    },
                                    viewFunctions: {
                                        label: 'Ver funciones',
                                        action: function() {
                                            var nombre = node.text.replace(/<[^>]*>/g, '').trim();
                                            var descripcion = node.data ? node.data.descripcion : '';
                                            mostrarModalVerFunciones(paginaId, nombre, descripcion);
                                        },
                                        icon: 'fas fa-eye'
                                    },
                                    delete: {
                                        label: 'Eliminar',
                                        action: function() {
                                            eliminarPagina(paginaId);
                                        },
                                        icon: 'fas fa-trash'
                                    }
                                };
                            }
                            
                            return items;
                        }
                    }
                });
                
                // Evento cuando se selecciona un nodo (doble click o click)
                container.on('select_node.jstree', function(e, data) {
                    console.log('Nodo seleccionado:', data.node);
                    var node = data.node;
                    
                    if (node.type === 'pagina' || node.type === 'activo' || node.type === 'inactivo') {
                        var paginaId = node.id.replace('pagina_', '');
                        console.log('Editando página ID:', paginaId);
                        editarPagina(paginaId);
                    }
                });
                
                // Evento cuando se cambia un nodo (movido)
                container.on('move_node.jstree', function(e, data) {
                    var node = data.node;
                    var parent = data.parent;
                    var position = data.position;
                    
                    if (node && parent && node.type !== 'modulo') {
                        actualizarOrdenArbol(node.id, parent, position);
                    }
                });
                
                // Abrir el primer nivel por defecto
                container.on('loaded.jstree', function() {
                    container.jstree('open_all');
                });
                
                arbolInstance = container;
            }
        },
        error: function(xhr, status, error) {
            console.error('Error cargando árbol:', error);
            console.error('Respuesta:', xhr.responseText);
            $('#arbolPaginas').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> Error al cargar la estructura de páginas: ${error}
                    <br><small>Revise la consola para más detalles</small>
                </div>
            `);
        }
    });
}

// Función para actualizar el orden en el árbol
function actualizarOrdenArbol(nodeId, parentId, position) {
    var paginaId = nodeId.replace('pagina_', '');
    var parentPaginaId = null;
    
    if (parentId && parentId !== '#') {
        parentPaginaId = parentId.replace('pagina_', '');
        if (parentId.startsWith('modulo_')) {
            // Es un módulo, no se puede establecer como padre
            parentPaginaId = null;
        }
    }
    
    $.ajax({
        url: 'paginas_ajax.php',
        type: 'POST',
        data: {
            accion: 'actualizarOrden',
            pagina_id: paginaId,
            padre_id: parentPaginaId,
            posicion: position
        },
        dataType: 'json',
        success: function(res) {
            if (res.resultado) {
                // Recargar el árbol para reflejar los cambios
                cargarArbol();
                if (typeof tabla !== 'undefined' && tabla) {
                    tabla.ajax.reload(null, false);
                }
            } else {
                Swal.fire('Error', res.error || 'Error al actualizar el orden', 'error');
                cargarArbol();
            }
        },
        error: function() {
            Swal.fire('Error', 'Error de conexión al actualizar el orden', 'error');
            cargarArbol();
        }
    });
}

// Función para editar una página
function editarPagina(paginaId) {
    $.get('paginas_ajax.php', {accion: 'obtener', pagina_id: paginaId}, function(res){
        if(res){
            $('#pagina_id').val(res.pagina_id);
            $('#pagina').val(res.pagina);
            $('#url').val(res.url);
            $('#pagina_descripcion').val(res.pagina_descripcion);
            $('#orden').val(res.orden);
            $('#tabla_estado_registro_id').val(res.tabla_estado_registro_id || 1);
            
            cargarModulos(res.modulo_id, function() {
                cargarTablas(res.tabla_id);
                cargarIconos(res.icono_id);
                cargarPaginasPadre(res.padre_id, res.modulo_id);
            });
            
            $('#modalLabel').text('Editar Página');
            var modal = new bootstrap.Modal(document.getElementById('modalpagina'));
            modal.show();
        } else {
            alert('Error al obtener datos');
        }
    }, 'json');
}

// Función para eliminar una página
function eliminarPagina(paginaId) {
    Swal.fire({
        title: "¿Estás seguro?",
        text: "¡No podrás revertir esto! Se eliminarán también las subpáginas.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar"
    }).then((result) => {
        if (result.isConfirmed) {
            $.get('paginas_ajax.php', {accion: 'eliminar', pagina_id: paginaId}, function(res){
                if(res.resultado){
                    cargarArbol();
                    if (typeof tabla !== 'undefined' && tabla) {
                        tabla.ajax.reload();
                    }
                    Swal.fire({
                        icon: "success",
                        title: "¡Eliminado!",
                        text: "La página ha sido eliminada.",
                        showConfirmButton: false,
                        timer: 1000
                    });
                } else {
                    Swal.fire('Error', 'Error al eliminar la página', 'error');
                }
            }, 'json');
        }
    });
}

$(document).ready(function(){
    // Inicializar selects
    cargarModulos();
    cargarTablas();
    cargarIconos();
    cargarPaginasPadre();
    
    // Cargar el árbol inicial
    cargarArbol();
    
    // Configurar DataTable para vista de tabla
    tabla = $('#tablapaginas').DataTable({
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
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions: { columns: ':visible' }
            }
        ],
        initComplete: function() {
            $('.dt-buttons').appendTo($('.dataTables_filter'));
            $('.dataTables_filter').css({
                'display': 'flex',
                'align-items': 'center',
                'gap': '10px'
            });
            $('.dataTables_filter input').addClass('form-control form-control-sm');
        },
        ajax: {
            url: 'paginas_ajax.php',
            type: 'GET',
            data: {accion: 'listar'},
            dataSrc: ''
        },
        language: {
            "search": "Buscar:",
            "searchPlaceholder": "Busqueda general...",
            "lengthMenu": "Mostrar _MENU_ registros por página",
            "zeroRecords": "No se encontraron resultados",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
            "infoEmpty": "Mostrando 0 a 0 de 0 registros",
            "infoFiltered": "(filtrado de _MAX_ registros totales)",
            "paginate": {
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            }
        },
        columns: [
            { data: 'pagina_id' },
            { data: 'modulo' },
            { data: 'pagina' },
            { data: 'url' },
            { 
                data: 'icono_clase',
                className: "text-center",
                render: function(data) {
                    return data ? `<div class="text-center"><i class="${data}" style="font-size: 1.2em;"></i></div>` : '<div class="text-center"><span class="text-muted">-</span></div>';
                }
            },
            { data: 'pagina_descripcion' },
            { data: 'padre_nombre' },
            { data: 'tabla_nombre' },
            { data: 'orden' },
            { 
                data: 'tabla_estado_registro_id',
                render: function(data) {
                    return data == 1 ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-danger">Inactivo</span>';
                }
            },
            { 
                data: 'tiene_funciones',
                className: "text-center",
                render: function(data, type, row) {
                    if (data > 0) {
                        return `<button class="btn btn-sm btn-outline-info btnVerFunciones" 
                                data-pagina-id="${row.pagina_id}"
                                data-pagina-nombre="${row.pagina}"
                                data-pagina-descripcion="${row.pagina_descripcion || ''}">
                            <i class="fas fa-eye me-1"></i> Ver (${data})
                        </button>`;
                    } else {
                        return '<span class="badge bg-warning"><i class="fas fa-times"></i> Sin funciones</span>';
                    }
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: "text-center",
                render: function(data, type, row){
                    return `
                        <button class="btn btn-sm btn-primary btnEditar me-1" title="Editar">
                            <i class="fa fa-pencil-alt"></i>
                        </button>
                        <button class="btn btn-sm btn-info btnCopiarFunciones me-1" title="Copiar Funciones">
                            <i class="fa fa-copy"></i>
                        </button>
                        <button class="btn btn-sm btn-danger btnEliminar" title="Eliminar">
                            <i class="fa fa-trash"></i>
                        </button>
                    `;
                }
            }
        ]
    });
    
    // Cambiar entre vista árbol y tabla
    $('#vistaArbol').click(function() {
        vistaActual = 'arbol';
        $('#vistaArbolContainer').show();
        $('#vistaTablaContainer').hide();
        $(this).removeClass('btn-outline-secondary').addClass('btn-outline-info');
        $('#vistaTabla').removeClass('btn-outline-info').addClass('btn-outline-secondary');
        cargarArbol($('#filtroModulo').val(), $('#buscarPagina').val());
    });
    
    $('#vistaTabla').click(function() {
        vistaActual = 'tabla';
        $('#vistaArbolContainer').hide();
        $('#vistaTablaContainer').show();
        $(this).removeClass('btn-outline-secondary').addClass('btn-outline-info');
        $('#vistaArbol').removeClass('btn-outline-info').addClass('btn-outline-secondary');
        tabla.ajax.reload();
    });
    
    // Filtro por módulo para el árbol
    $('#filtroModulo').change(function() {
        if (vistaActual === 'arbol') {
            cargarArbol($(this).val(), $('#buscarPagina').val());
        } else {
            tabla.ajax.reload();
        }
    });
    
    // Búsqueda en el árbol
    $('#buscarPagina').on('keyup', function() {
        if (vistaActual === 'arbol') {
            var texto = $(this).val();
            if (texto.length > 2) {
                cargarArbol($('#filtroModulo').val(), texto);
            } else if (texto.length === 0) {
                cargarArbol($('#filtroModulo').val());
            }
        }
    });
    
    // Botón expandir todo
    $('#btnExpandirTodo').click(function() {
        if (arbolInstance) {
            arbolInstance.jstree('open_all');
        }
    });
    
    // Botón colapsar todo
    $('#btnColapsarTodo').click(function() {
        if (arbolInstance) {
            arbolInstance.jstree('close_all');
        }
    });
    
    // Botón nueva página
    $('#btnNuevo').click(function(){
        $('#formpagina')[0].reset();
        $('#pagina_id').val('');
        $('#modalLabel').text('Nueva Página');
        
        cargarModulos();
        cargarTablas();
        cargarIconos();
        cargarPaginasPadre();
        
        var modal = new bootstrap.Modal(document.getElementById('modalpagina'));
        modal.show();
    });
    
    // Eventos de la tabla
    $('#tablapaginas tbody').on('click', '.btnVerFunciones', function(){
        var pagina_id = $(this).data('pagina-id');
        var pagina_nombre = $(this).data('pagina-nombre');
        var pagina_descripcion = $(this).data('pagina-descripcion');
        mostrarModalVerFunciones(pagina_id, pagina_nombre, pagina_descripcion);
    });
    
    $('#tablapaginas tbody').on('click', '.btnEditar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        editarPagina(data.pagina_id);
    });
    
    $('#tablapaginas tbody').on('click', '.btnCopiarFunciones', function(){
        var data = tabla.row($(this).parents('tr')).data();
        
        if (!data.tabla_id) {
            Swal.fire('Información', 'Esta página no tiene una tabla asociada', 'info');
            return;
        }
        
        Swal.fire({
            title: 'Verificando...',
            text: 'Obteniendo información de la tabla',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        $.ajax({
            url: 'paginas_ajax.php',
            type: 'GET',
            data: {accion: 'obtenerTablaTipo', tabla_id: data.tabla_id},
            dataType: 'json',
            success: function(res) {
                Swal.close();
                
                if(res.tabla_tipo_id) {
                    $.ajax({
                        url: 'paginas_ajax.php',
                        type: 'GET',
                        data: {accion: 'obtenerFuncionesPorTipo', tabla_tipo_id: res.tabla_tipo_id},
                        dataType: 'json',
                        success: function(funciones) {
                            if (funciones && funciones.length > 0) {
                                paginaIdParaCopiar = data.pagina_id;
                                tablaTipoIdParaCopiar = res.tabla_tipo_id;
                                
                                $.ajax({
                                    url: 'paginas_ajax.php',
                                    type: 'GET',
                                    data: {accion: 'verificarFunciones', pagina_id: data.pagina_id},
                                    dataType: 'json',
                                    success: function(resFunciones) {
                                        if (resFunciones.tiene_funciones) {
                                            $('#mensajeCopiarFunciones p:first').html('Esta página ya tiene algunas funciones asignadas.');
                                            $('#mensajeCopiarFunciones p:eq(1)').html('¿Desea copiar las funciones adicionales del tipo de tabla?');
                                            $('.alert-info small').text('Nota: Solo se agregarán las funciones nuevas. Las existentes no se duplicarán.');
                                        } else {
                                            $('#mensajeCopiarFunciones p:first').html('Esta página está asociada a una tabla con tipo de funciones predefinidas.');
                                            $('#mensajeCopiarFunciones p:eq(1)').html('¿Desea copiar las funciones estándar para esta página?');
                                            $('.alert-info small').text('Nota: Se copiarán todas las funciones del tipo de tabla.');
                                        }
                                        
                                        var listaHtml = '<h6>Funciones disponibles:</h6><ul class="list-group">';
                                        $.each(funciones, function(index, funcion) {
                                            listaHtml += `<li class="list-group-item">
                                                <strong>${funcion.nombre_funcion}</strong>
                                                ${funcion.descripcion ? `<br><small class="text-muted">${funcion.descripcion}</small>` : ''}
                                            </li>`;
                                        });
                                        listaHtml += '</ul>';
                                        $('#listaFuncionesItems').html(listaHtml);
                                        $('#listaFunciones').show();
                                        
                                        var modal = new bootstrap.Modal(document.getElementById('modalCopiarFunciones'));
                                        modal.show();
                                    },
                                    error: function() {
                                        Swal.fire('Error', 'Error al verificar funciones existentes', 'error');
                                    }
                                });
                            } else {
                                Swal.fire('Información', 'No hay funciones predefinidas para este tipo de tabla', 'info');
                            }
                        },
                        error: function() {
                            Swal.close();
                            Swal.fire('Error', 'Error al obtener funciones del tipo', 'error');
                        }
                    });
                } else {
                    Swal.fire('Información', 'La tabla asociada no tiene tipo definido', 'info');
                }
            },
            error: function() {
                Swal.close();
                Swal.fire('Error', 'Error al obtener información de la tabla', 'error');
            }
        });
    });
    
    $('#tablapaginas tbody').on('click', '.btnEliminar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        eliminarPagina(data.pagina_id);
    });
    
    // Evento para cambiar módulo y actualizar páginas padre
    $('#modulo_id').change(function() {
        var moduloId = $(this).val();
        cargarPaginasPadre(null, moduloId);
    });
    
    // Guardar página
    $('#btnGuardar').click(function(){
        if ($('#pagina').val().trim() === '' || $('#modulo_id').val() === '') {
            $('#formpagina').addClass('was-validated');
            Swal.fire('Error', 'Nombre de página y módulo son obligatorios', 'error');
            return false;
        }
        
        var id = $('#pagina_id').val();
        var accion = id ? 'editar' : 'agregar';
        var formData = {
            accion: accion,
            pagina_id: id,
            pagina: $('#pagina').val(),
            url: $('#url').val(),
            pagina_descripcion: $('#pagina_descripcion').val(),
            orden: $('#orden').val(),
            tabla_id: $('#tabla_id').val(),
            icono_id: $('#icono_id').val(),
            padre_id: $('#padre_id').val() || null,
            modulo_id: $('#modulo_id').val(),
            tabla_estado_registro_id: $('#tabla_estado_registro_id').val() || 1
        };
        
        $.ajax({
            url: 'paginas_ajax.php',
            type: 'GET',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if(res.resultado) {
                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalpagina'));
                    modal.hide();
                    
                    $('#formpagina')[0].reset();
                    $('#formpagina').removeClass('was-validated');
                    
                    cargarArbol($('#filtroModulo').val(), $('#buscarPagina').val());
                    
                    if (typeof tabla !== 'undefined' && tabla) {
                        tabla.ajax.reload(null, false);
                    }
                    
                    if (res.tabla_tipo_id && !res.tiene_funciones) {
                        mostrarModalCopiarFunciones(res.pagina_id || id, res.tabla_tipo_id);
                    } else {
                        Swal.fire({
                            icon: "success",
                            title: "¡Operación exitosa!",
                            text: res.mensaje || "Los datos se han guardado correctamente",
                            showConfirmButton: false,
                            timer: 1500
                        });
                    }
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
    
    // Eventos para el modal de copiar funciones
    $('#btnCopiarFunciones').click(function(){
        copiarFunciones();
    });
    
    $('#btnNoCopiarFunciones').click(function(){
        var modal = bootstrap.Modal.getInstance(document.getElementById('modalCopiarFunciones'));
        modal.hide();
        cargarArbol($('#filtroModulo').val(), $('#buscarPagina').val());
        if (typeof tabla !== 'undefined' && tabla) {
            tabla.ajax.reload(null, false);
        }
        Swal.fire({
            icon: "info",
            title: "Funciones no copiadas",
            text: "Puede agregar funciones manualmente más tarde",
            showConfirmButton: false,
            timer: 1500
        });
    });
});

</script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>