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
        <!--begin::Container-->
        <div class="container-fluid">
        <!--begin::Row-->
        <div class="row">
            <div class="col-sm-6"><h3 class="mb-0">paginas</h3></div>
            <div class="col-sm-6">
            <ol class="breadcrumb float-sm-end">
                <li class="breadcrumb-item"><a href="#">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">paginas</li>
            </ol>
            </div>
        </div>
        <!--end::Row-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::App Content Header-->
    <!--begin::App Content-->
    <div class="app-content">
        <!--begin::Container-->
        <div class="container-fluid">
<!-- Content Wrapper -->
        <div class="content-wrapper">
        
            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">                      
                    <div class="row">
                        <div class="col-12">
                            <div class="card">                                
                                <div class="card-body">
                                    <button class="btn btn-primary mb-3" id="btnNuevo">Nueva pagina</button>
                                    <table id="tablapaginas" class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Id</th>
                                                <th>Modulo</th>
                                                <th>Pagina</th>
                                                <th>URL</th>
                                                <th>Icono</th>
                                                <th>Descripcion</th>
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
            </section>
        </div>

<!-- Modal página -->
<div class="modal fade" id="modalpagina" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">pagina</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form id="formpagina">
            <input type="hidden" id="pagina_id" name="pagina_id" />
             
            <div class="row g-3">
                
                <div class="col-md-6">
                    <label>Nombre</label>
                    <input type="text" class="form-control" id="pagina" name="pagina" required/>                    
                </div>
                <div class="col-md-6">
                    <label>Modulo</label>
                    <select class="form-control" id="modulo_id" name="modulo_id" required>
                        <option value="">Seleccionar Modulo</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Url</label>
                    <input type="text" class="form-control" id="url" name="url" />
                </div>
                <div class="col-md-6">
                    <label>Descripcion</label>
                    <input type="text" class="form-control" id="pagina_descripcion" name="pagina_descripcion"/>
                </div>
                 
                <div class="col-md-6">
                    <label>Icono</label>
                    <select class="form-control" id="icono_id" name="icono_id">
                        <option value="">Seleccionar Icono</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
                </div> 
                <div class="col-md-6">
                    <label>Orden</label>
                    <input type="text" class="form-control" id="orden" name="orden" />
                </div>
                <div class="col-md-6">
                    <label>Tabla</label>
                    <select class="form-control" id="tabla_id" name="tabla_id">
                        <option value="">Seleccionar Tabla</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
                </div>   
               <div class="col-md-6">
                <label>Padre</label>
                <select class="form-control" id="padre_id" name="padre_id">
                    <option value="">Ninguno (página principal)</option>
                    <!-- Options will be populated by JavaScript -->
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
                    <!-- Las funciones se cargarán aquí -->
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

<script>
// Variables globales para el modal de copiar funciones
var paginaIdParaCopiar = null;
var tablaTipoIdParaCopiar = null;
var tabla;

// Función para mostrar el modal de visualizar funciones
function mostrarModalVerFunciones(pagina_id, pagina_nombre, pagina_descripcion) {
    // Configurar información de la página
    $('#nombrePagina').text(pagina_nombre);
    $('#descripcionPagina').text(pagina_descripcion || 'Sin descripción');
    
    // Mostrar loading
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
    
    // Obtener funciones de la página
    $.ajax({
        url: 'paginas_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerFuncionesPorPagina', pagina_id: pagina_id},
        dataType: 'json',
        success: function(funciones) {
            if(funciones && funciones.length > 0) {
                var html = '';
                $.each(funciones, function(index, funcion) {
                    // Determinar clase de color para el badge
                    var colorClass = funcion.color_clase ? funcion.color_clase : 'bg-secondary';
                    
                    // Obtener icono si existe
                    var iconoHtml = '';
                    if (funcion.icono_clase) {
                        iconoHtml = `<i class="${funcion.icono_clase}"></i>`;
                    } else if (funcion.icono_nombre) {
                        iconoHtml = `<span class="badge bg-light text-dark">${funcion.icono_nombre}</span>`;
                    }
                    
                    // Mostrar estados de origen y destino
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
    
    // Mostrar el modal
    var modal = new bootstrap.Modal(document.getElementById('modalVerFunciones'));
    modal.show();
}

// Función para mostrar el modal de copiar funciones
// Función para mostrar el modal de copiar funciones (actualizar)
function mostrarModalCopiarFunciones(pagina_id, tabla_tipo_id) {
    paginaIdParaCopiar = pagina_id;
    tablaTipoIdParaCopiar = tabla_tipo_id;
    
    // Verificar si la página ya tiene funciones
    $.ajax({
        url: 'paginas_ajax.php',
        type: 'GET',
        data: {accion: 'verificarFunciones', pagina_id: pagina_id},
        dataType: 'json',
        success: function(res) {
            if (res.tiene_funciones) {
                // Modificar el mensaje si ya tiene funciones
                $('#mensajeCopiarFunciones p:first').html('Esta página ya tiene algunas funciones asignadas.');
                $('#mensajeCopiarFunciones p:eq(1)').html('¿Desea copiar las funciones adicionales del tipo de tabla?');
                $('.alert-info small').text('Nota: Solo se agregarán las funciones nuevas. Las existentes no se duplicarán.');
            } else {
                // Mensaje original si no tiene funciones
                $('#mensajeCopiarFunciones p:first').html('Esta página está asociada a una tabla con tipo de funciones predefinidas.');
                $('#mensajeCopiarFunciones p:eq(1)').html('¿Desea copiar las funciones estándar para esta página?');
                $('.alert-info small').text('Nota: Solo se agregarán las funciones. No se crearán duplicados.');
            }
        }
    });
    
    // Mostrar el modal
    var modal = new bootstrap.Modal(document.getElementById('modalCopiarFunciones'));
    modal.show();
    
    // Obtener y mostrar las funciones disponibles
    if (tabla_tipo_id) {
        obtenerFuncionesPorTipo(tabla_tipo_id);
    }
}

// Función para obtener funciones por tipo (mejorada)
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


// Función para copiar funciones (versión mejorada)
function copiarFunciones() {
    if (!paginaIdParaCopiar || !tablaTipoIdParaCopiar) {
        Swal.fire('Error', 'Datos incompletos', 'error');
        return;
    }
    
    console.log('Copiando funciones - Página ID:', paginaIdParaCopiar, 'Tipo ID:', tablaTipoIdParaCopiar);
    
    // Mostrar loading
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
            console.log('Respuesta de copia:', res);
            
            // Cerrar el modal de copiar funciones
            var modal = bootstrap.Modal.getInstance(document.getElementById('modalCopiarFunciones'));
            if (modal) {
                modal.hide();
            }
            
            if(res && res.resultado) {
                // Recargar la tabla y luego mostrar el resultado
                if (typeof tabla !== 'undefined' && tabla) {
                    tabla.ajax.reload(function() {
                        // Una vez recargada la tabla, obtener resultado detallado
                        $.ajax({
                            url: 'paginas_ajax.php',
                            type: 'GET',
                            data: {accion: 'obtenerResultadoCopia'},
                            dataType: 'json',
                            timeout: 10000,
                            success: function(resultado) {
                                Swal.close();
                                console.log('Resultado detallado:', resultado);
                                
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
                                        text: res.mensaje || "Las funciones se han procesado exitosamente",
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                }
                            },
                            error: function(xhr, status, error) {
                                Swal.close();
                                console.error('Error al obtener resultado:', status, error);
                                
                                Swal.fire({
                                    icon: "success",
                                    title: "Funciones copiadas",
                                    text: "Las funciones se han copiado exitosamente",
                                    showConfirmButton: false,
                                    timer: 1500
                                });
                            }
                        });
                    }, false); // false = no resetear el paginado
                } else {
                    // Si tabla no está definida, recargar la página
                    Swal.close();
                    Swal.fire({
                        icon: "success",
                        title: "Funciones copiadas",
                        text: "Las funciones se han copiado exitosamente. Recargando página...",
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        location.reload();
                    });
                }
            } else {
                Swal.close();
                console.error('Error en respuesta:', res);
                Swal.fire('Error', res?.error || 'Error al copiar funciones', 'error');
            }
        },
        error: function(xhr, status, error) {
            Swal.close();
            console.error('Error en la copia:', status, error);
            
            // Cerrar el modal
            var modal = bootstrap.Modal.getInstance(document.getElementById('modalCopiarFunciones'));
            if (modal) {
                modal.hide();
            }
            
            // Intentar recargar la tabla de todas formas
            if (typeof tabla !== 'undefined' && tabla) {
                tabla.ajax.reload(null, false);
            }
            
            Swal.fire({
                icon: "warning",
                title: "Proceso completado",
                text: "Las funciones se copiaron pero hubo un error en la respuesta",
                showConfirmButton: true
            });
        }
    });
}

// Función mejorada para cargar Modulos
function cargarModulos(selectedId = null, callback = null) {
    $.ajax({
        url: 'paginas_ajax.php',
        type: 'GET',
        data: {accion: 'obtener_Modulos'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar modulo</option>';
                $.each(res, function(index, modulo) {
                    var selected = (selectedId == modulo.modulo_id) ? 'selected' : '';
                    options += `<option value="${modulo.modulo_id}" ${selected}>${modulo.modulo}</option>`;
                });
                $('#modulo_id').html(options);
                
                // Ejecutar callback si existe
                if (typeof callback === 'function') {
                    callback();
                }
            }
        },
        error: function() {
            console.error('Error al cargar Modulos');
            $('#modulo_id').html('<option value="">Error al cargar Modulos</option>');
        }
    });
}

function cargarPaginasPadre(selectedId = null) {
    $.ajax({
        url: 'paginas_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerPadre'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Ninguno (página principal)</option>';
                $.each(res, function(index, pagina) {
                    // Excluir la página actual si estamos editando
                    var currentPageId = $('#pagina_id').val();
                    if (!currentPageId || pagina.pagina_id != currentPageId) {
                        var selected = (selectedId == pagina.pagina_id) ? 'selected' : '';
                        options += `<option value="${pagina.pagina_id}" ${selected}>${pagina.pagina}</option>`;
                    }
                });
                $('#padre_id').html(options);
            }
        },
        error: function() {
            console.error('Error al cargar páginas padre');
            $('#padre_id').html('<option value="">Error al cargar páginas padre</option>');
        }
    });
}

// Función para cargar tablas
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
            console.error('Error al cargar tablas');
            $('#tabla_id').html('<option value="">Error al cargar tablas</option>');
        }
    });
}

// Función para cargar iconos
function cargarIconos(selectedId = null) {
    $.ajax({
        url: 'paginas_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerIconos'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar Icono</option>';
                $.each(res, function(index, tabla) {
                    var selected = (selectedId == tabla.icono_id) ? 'selected' : '';
                    options += `<option value="${tabla.icono_id}" ${selected}>${tabla.icono_nombre}</option>`;
                });
                $('#icono_id').html(options);
            }
        },
        error: function() {
            console.error('Error al cargar iconos');
            $('#icono_id').html('<option value="">Error al cargar iconos</option>');
        }
    });
}

$(document).ready(function(){
    cargarModulos();
    cargarPaginasPadre();
    cargarTablas();
    cargarIconos();
    
    
    // Configuración de DataTable
    var tabla = $('#tablapaginas').DataTable({
        pageLength: 25,
        lengthMenu: [25, 50, 100, 200],
        dom: '<"row"<"col-md-6"l><"col-md-6"fB>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel"></i> Excel',
                titleAttr: 'Exportar a Excel',
                className: 'btn btn-success btn-sm me-2',
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                titleAttr: 'Exportar a PDF',
                className: 'btn btn-danger btn-sm',
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions: {
                    columns: ':visible'
                }
            }
        ],
        initComplete: function() {
            // Mover los botones al contenedor del buscador
            $('.dt-buttons').appendTo($('.dataTables_filter'));
            
            // Aplicar estilos al contenedor
            $('.dataTables_filter').css({
                'display': 'flex',
                'align-items': 'center',
                'gap': '10px'
            });
            
            // Estilo para el input de búsqueda
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
                render: function(data, type, row) {
                    if (data) {
                        return `<div class="text-center"><i class="${data}" title="${data}" style="font-size: 1.2em;"></i></div>`;
                    } else {
                        return '<div class="text-center"><span class="text-muted">-</span></div>';
                    }
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

    $('#btnNuevo').click(function(){
        $('#formpagina')[0].reset();
        $('#pagina_id').val('');
        $('#modalLabel').text('Nueva Pagina');
        var modal = new bootstrap.Modal(document.getElementById('modalpagina'));
        modal.show();
    });

    // Evento para botón de ver funciones
    $('#tablapaginas tbody').on('click', '.btnVerFunciones', function(){
        var pagina_id = $(this).data('pagina-id');
        var pagina_nombre = $(this).data('pagina-nombre');
        var pagina_descripcion = $(this).data('pagina-descripcion');
        
        mostrarModalVerFunciones(pagina_id, pagina_nombre, pagina_descripcion);
    });

    // Evento para botón de copiar funciones
    // Actualizar el evento para botón de copiar funciones
    // Actualizar el evento para botón de copiar funciones
$('#tablapaginas tbody').on('click', '.btnCopiarFunciones', function(){
    var data = tabla.row($(this).parents('tr')).data();
    
    // Verificar si tiene tabla asociada
    if (!data.tabla_id) {
        Swal.fire('Información', 'Esta página no tiene una tabla asociada', 'info');
        return;
    }
    
    // Mostrar loading
    Swal.fire({
        title: 'Verificando...',
        text: 'Obteniendo información de la tabla',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    var timeoutId = setTimeout(function() {
        Swal.close();
        Swal.fire({
            icon: 'warning',
            title: 'Tiempo de espera agotado',
            text: 'La operación está tomando más tiempo de lo normal. ¿Desea continuar esperando?',
            showCancelButton: true,
            confirmButtonText: 'Sí, esperar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // El usuario quiere seguir esperando, reiniciar el timeout
                copiarFunciones(); // Reintentar
            } else {
                // El usuario canceló, recargar la tabla
                if (typeof tabla !== 'undefined' && tabla) {
                    tabla.ajax.reload(null, false);
                }
            }
        });
    }, 15000); // 15 segundos de timeout

    
    // Obtener el tipo de tabla
    $.ajax({
        url: 'paginas_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerTablaTipo', tabla_id: data.tabla_id},
        dataType: 'json',
        success: function(res) {
            Swal.close();
            
            if(res.tabla_tipo_id) {
                // Primero verificar cuántas funciones tiene el tipo
                $.ajax({
                    url: 'paginas_ajax.php',
                    type: 'GET',
                    data: {accion: 'obtenerFuncionesPorTipo', tabla_tipo_id: res.tabla_tipo_id},
                    dataType: 'json',
                    success: function(funciones) {
                        if (funciones && funciones.length > 0) {
                            // Guardar los datos en variables globales
                            paginaIdParaCopiar = data.pagina_id;
                            tablaTipoIdParaCopiar = res.tabla_tipo_id;
                            
                            // Verificar si la página ya tiene funciones
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
                                    
                                    // Mostrar la lista de funciones disponibles
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
                                    
                                    // Mostrar el modal
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
                    error: function(xhr, status, error) {
                        Swal.close();
                        console.error('Error al obtener funciones del tipo:', error);
                        Swal.fire('Error', 'Error al obtener funciones del tipo', 'error');
                    }
                });
            } else {
                Swal.fire('Información', 'La tabla asociada no tiene tipo definido', 'info');
            }
        },
        error: function(xhr, status, error) {
            Swal.close();
            console.error('Error al obtener tipo de tabla:', error);
            Swal.fire('Error', 'Error al obtener información de la tabla', 'error');
        }
    });
});

    $('#tablapaginas tbody').on('click', '.btnEditar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        $.get('paginas_ajax.php', {accion: 'obtener', pagina_id: data.pagina_id}, function(res){
            if(res){
                $('#pagina_id').val(res.pagina_id);
                $('#pagina').val(res.pagina);
                $('#url').val(res.url);
                $('#pagina_descripcion').val(res.pagina_descripcion);
                $('#orden').val(res.orden);
                $('#tabla_id').val(res.tabla_id);
                $('#modulo_id').val(res.modulo_id);                
                $('#icono_id').val(res.icono_id);                
                $('#padre_id').val(res.padre_id);                
                $('#tabla_estado_registro_id').val(res.tabla_estado_registro_id);

                // Cargar selects con valores actuales
                cargarTablas(res.tabla_id);
                cargarIconos(res.icono_id);
                cargarPaginasPadre(res.padre_id);

                $('#modalLabel').text('Editar pagina');
                var modal = new bootstrap.Modal(document.getElementById('modalpagina'));
                modal.show();
                
            } else {
                alert('Error al obtener datos');
            }
        }, 'json');
    });

    $('#tablapaginas tbody').on('click', '.btnEliminar', function(){
        Swal.fire({
            title: "¿Estás seguro?",
            text: "¡No podrás revertir esto!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "Sí, eliminar",
            cancelButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                var data = tabla.row($(this).parents('tr')).data();
                $.get('paginas_ajax.php', {accion: 'eliminar', pagina_id: data.pagina_id}, function(res){
                    if(res.resultado){
                        tabla.ajax.reload();
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
    });

    $('#btnGuardar').click(function(){
        // Validar solo campos obligatorios
        if ($('#pagina').val().trim() === '' || $('#modulo_id').val() === '') {
            $('#formpagina').addClass('was-validated');
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
                    // Cerrar el modal
                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalpagina'));
                    modal.hide();
                    
                    // Resetear el formulario
                    $('#formpagina')[0].reset();
                    $('#formpagina').removeClass('was-validated');
                    
                    // Si es una nueva página o edición sin funciones y tiene tabla_tipo_id, mostrar modal para copiar funciones
                    if (res.tabla_tipo_id && !res.tiene_funciones) {
                        mostrarModalCopiarFunciones(res.pagina_id || id, res.tabla_tipo_id);
                    } else {
                        // Recargar la tabla
                        tabla.ajax.reload(null, false);
                        
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
        
        // Recargar la tabla
        tabla.ajax.reload(null, false);
        
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
