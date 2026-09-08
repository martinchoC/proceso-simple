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
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                                    id="btnRecargar" title="Recargar tabla">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-success dropdown-toggle"
                                                    data-bs-toggle="dropdown" aria-expanded="false"
                                                    title="Exportar datos">
                                                    <i class="fas fa-file-export"></i> Exportar
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="#" id="btnExportarExcel"><i
                                                                class="fas fa-file-excel text-success"></i> Excel</a>
                                                    </li>
                                                    <li><a class="dropdown-item" href="#" id="btnExportarPDF"><i
                                                                class="fas fa-file-pdf text-danger"></i> PDF</a></li>
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li><a class="dropdown-item" href="#" id="btnExportarCSV"><i
                                                                class="fas fa-file-csv text-primary"></i> CSV</a></li>
                                                    <li><a class="dropdown-item" href="#" id="btnExportarPrint"><i
                                                                class="fas fa-print text-secondary"></i> Imprimir</a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <!-- Filtros -->
                                        <div class="row mb-3">
                                            <div class="col-md-3">
                                                <label for="filtroTipo" class="form-label">Filtrar por Tipo</label>
                                                <select class="form-select form-select-sm" id="filtroTipo">
                                                    <option value="">Todos</option>
                                                    <option value="grupo">Grupos</option>
                                                    <option value="subgrupo">Subgrupos</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label for="filtroGrupoPadre" class="form-label">Filtrar por Grupo</label>
                                                <select class="form-select form-select-sm" id="filtroGrupoPadre">
                                                    <option value="">Todos los grupos</option>
                                                    <!-- Se llena dinámicamente -->
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label for="filtroEstadoGrupo" class="form-label">Filtrar por Estado</label>
                                                <select class="form-select form-select-sm" id="filtroEstadoGrupo">
                                                    <option value="">Todos los estados</option>
                                                    <!-- Se llena dinámicamente -->
                                                </select>
                                            </div>
                                            <div class="col-md-3 d-flex align-items-end">
                                                <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="btnLimpiarFiltrosGrupos">
                                                    <i class="fas fa-filter-circle-xmark me-1"></i>Limpiar Filtros
                                                </button>
                                            </div>
                                        </div>

                                        <!-- DataTable -->
                                        <table id="tablaGruposSubgrupos" class="table table-striped table-bordered"
                                            style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="100">Tipo</th>
                                                    <th>Nombre</th>
                                                    <th width="180">Grupo</th>
                                                    <th width="180">Tabla Asociada</th>
                                                    <th width="80">Orden</th>
                                                    <th width="120">Estado</th>
                                                    <th width="180" class="text-center">Acciones</th>
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
                                    <label for="tabla_id" class="form-label">Tabla Asociada</label>
                                    <select class="form-select" id="tabla_id" name="tabla_id">
                                        <option value="0">Sin tabla asociada</option>
                                        <!-- Se llena dinámicamente: tablas sin subgrupo asociado + la actual -->
                                    </select>
                                    <small class="text-muted">Sólo se listan las tablas que todavía no tienen un
                                        subgrupo asociado</small>
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
                    
                    $('#contenedor-botones').html(htmlBotones);
                }, 'json');
            }

            // Cargar el desplegable de tablas disponibles para asociar a un subgrupo
            // (tablas sin subgrupo asociado + la tabla actual del subgrupo, si se está editando)
            function cargarTablasDisponibles(comprobanteSubgrupoId, tablaSeleccionadaId) {
                return $.get('comprobantes_grupos_ajax.php', {
                    accion: 'obtener_tablas_disponibles',
                    empresa_idx: empresa_idx,
                    comprobante_subgrupo_id: comprobanteSubgrupoId || 0
                }, function (data) {
                    $('#tabla_id').empty().append('<option value="0">Sin tabla asociada</option>');
                    $.each(data, function (index, tabla) {
                        $('#tabla_id').append('<option value="' + tabla.tabla_id + '">' + tabla.tabla_nombre + '</option>');
                    });
                    if (tablaSeleccionadaId) {
                        $('#tabla_id').val(tablaSeleccionadaId);
                    }
                }, 'json');
            }

            // Instancia del DataTable
            var tablaDT;

            // Determina clase de badge según código de estado estándar
            function badgeClasePorEstado(codigoEstandar) {
                if (codigoEstandar === 'ACTIVO') return 'bg-success';
                if (codigoEstandar === 'BLOQUEADO') return 'bg-warning';
                if (codigoEstandar === 'INACTIVO') return 'bg-secondary';
                return 'bg-secondary';
            }

            // Aplana grupos + subgrupos (jerarquía) en filas de tabla, agrupando visualmente
            // cada grupo justo antes de sus propios subgrupos.
            function aplanarJerarquia(grupos, subgruposPorGrupo) {
                var filas = [];

                grupos.forEach(function (grupo) {
                    filas.push({
                        id: grupo.comprobante_grupo_id,
                        tipo: 'grupo',
                        nombre: grupo.comprobante_grupo,
                        grupo_id: grupo.comprobante_grupo_id,
                        grupo_nombre: '—',
                        tabla_nombre: null,
                        orden: grupo.orden || 0,
                        estado_info: grupo.estado_info,
                        botones: grupo.botones || []
                    });

                    var hijos = (subgruposPorGrupo[grupo.comprobante_grupo_id] || []).slice();
                    hijos.sort((a, b) => (a.orden || 0) - (b.orden || 0) || a.comprobante_subgrupo.localeCompare(b.comprobante_subgrupo));

                    hijos.forEach(function (subgrupo) {
                        filas.push({
                            id: subgrupo.comprobante_subgrupo_id,
                            tipo: 'subgrupo',
                            nombre: subgrupo.comprobante_subgrupo,
                            grupo_id: grupo.comprobante_grupo_id,
                            grupo_nombre: grupo.comprobante_grupo,
                            tabla_nombre: subgrupo.tabla_asociada ? subgrupo.tabla_asociada.tabla_nombre : null,
                            orden: subgrupo.orden || 0,
                            estado_info: subgrupo.estado_info,
                            botones: subgrupo.botones || []
                        });
                    });
                });

                return filas;
            }

            // Arma el HTML de los botones de acción de una fila (comunes a grupo/subgrupo,
            // más "Agregar Subgrupo" cuando la fila es un grupo)
            function renderAcciones(fila) {
                var html = '<div class="btn-group">';

                (fila.botones || []).forEach(function (boton) {
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
                        <button type="button" class="btn btn-sm ${claseBoton}"
                                title="${titulo}"
                                data-id="${fila.id}"
                                data-tipo="${fila.tipo}"
                                data-accion="${boton.accion_js}"
                                data-confirmable="${boton.es_confirmable || 0}"
                                data-nombre="${fila.nombre}">
                            ${icono}
                        </button>
                    `;
                });

                if (fila.tipo === 'grupo') {
                    html += `
                        <button type="button" class="btn btn-sm btn-success btn-agregar-subgrupo"
                                title="Agregar Subgrupo"
                                data-grupo-id="${fila.id}"
                                data-grupo-nombre="${fila.nombre}">
                            <i class="fas fa-plus-circle"></i>
                        </button>
                    `;
                }

                html += '</div>';
                return html;
            }

            // Poblar los filtros de Grupo y Estado a partir de los datos ya cargados
            function poblarFiltros(filas) {
                var gruposUnicos = {};
                var estadosUnicos = {};

                filas.forEach(function (fila) {
                    if (fila.tipo === 'grupo') {
                        gruposUnicos[fila.id] = fila.nombre;
                    }
                    if (fila.estado_info) {
                        estadosUnicos[fila.estado_info.codigo_estandar] = fila.estado_info.estado_registro;
                    }
                });

                var selGrupo = $('#filtroGrupoPadre').val();
                $('#filtroGrupoPadre').empty().append('<option value="">Todos los grupos</option>');
                $.each(gruposUnicos, function (id, nombre) {
                    $('#filtroGrupoPadre').append('<option value="' + id + '">' + nombre + '</option>');
                });
                $('#filtroGrupoPadre').val(selGrupo);

                var selEstado = $('#filtroEstadoGrupo').val();
                $('#filtroEstadoGrupo').empty().append('<option value="">Todos los estados</option>');
                $.each(estadosUnicos, function (codigo, nombre) {
                    $('#filtroEstadoGrupo').append('<option value="' + codigo + '">' + nombre + '</option>');
                });
                $('#filtroEstadoGrupo').val(selEstado);
            }

            // Inicializar el DataTable (una sola vez)
            function inicializarDataTable() {
                tablaDT = $('#tablaGruposSubgrupos').DataTable({
                    data: [],
                    columns: [
                        {
                            data: 'tipo',
                            render: function (data) {
                                return data === 'grupo'
                                    ? '<span class="badge bg-warning text-dark"><i class="fas fa-folder me-1"></i>Grupo</span>'
                                    : '<span class="badge bg-info text-dark"><i class="fas fa-folder-open me-1"></i>Subgrupo</span>';
                            }
                        },
                        {
                            data: 'nombre',
                            render: function (data, type, fila) {
                                return fila.tipo === 'grupo' ? '<strong>' + data + '</strong>' : data;
                            }
                        },
                        { data: 'grupo_nombre' },
                        {
                            data: 'tabla_nombre',
                            render: function (data) {
                                return data ? data : '<span class="text-muted">—</span>';
                            }
                        },
                        { data: 'orden' },
                        {
                            data: 'estado_info',
                            render: function (data) {
                                var badge = badgeClasePorEstado(data ? data.codigo_estandar : null);
                                var texto = data ? data.estado_registro : 'Sin estado';
                                return '<span class="badge ' + badge + '">' + texto + '</span>';
                            }
                        },
                        {
                            data: null,
                            orderable: false,
                            className: 'text-center',
                            render: function (data, type, fila) {
                                return renderAcciones(fila);
                            }
                        }
                    ],
                    order: [],
                    pageLength: 25,
                    language: {
                        emptyTable: 'No hay grupos ni subgrupos de comprobantes registrados',
                        zeroRecords: 'No se encontraron registros con los filtros aplicados',
                        search: 'Buscar:',
                        lengthMenu: 'Mostrar _MENU_ registros',
                        info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                        infoEmpty: 'Sin registros',
                        paginate: { previous: 'Anterior', next: 'Siguiente' }
                    },
                    dom: 'Bfrtip',
                    buttons: ['excel', 'pdf', 'csv', 'print']
                });

                // Filtro personalizado: Tipo / Grupo / Estado
                $.fn.dataTable.ext.search.push(function (settings, searchData, index, rowData) {
                    if (settings.nTable.id !== 'tablaGruposSubgrupos') return true;

                    var fTipo = $('#filtroTipo').val();
                    var fGrupo = $('#filtroGrupoPadre').val();
                    var fEstado = $('#filtroEstadoGrupo').val();

                    if (fTipo && rowData.tipo !== fTipo) return false;
                    if (fGrupo && String(rowData.grupo_id) !== String(fGrupo)) return false;
                    if (fEstado && (!rowData.estado_info || rowData.estado_info.codigo_estandar !== fEstado)) return false;

                    return true;
                });

                $('#filtroTipo, #filtroGrupoPadre, #filtroEstadoGrupo').on('change', function () {
                    tablaDT.draw();
                });

                $('#btnLimpiarFiltrosGrupos').on('click', function () {
                    $('#filtroTipo').val('');
                    $('#filtroGrupoPadre').val('');
                    $('#filtroEstadoGrupo').val('');
                    tablaDT.draw();
                });
            }

            // Cargar jerarquía completa y volcarla en el DataTable
            function cargarJerarquia() {
                $.get('comprobantes_grupos_ajax.php', {
                    accion: 'listar_jerarquia',
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx,
                    pagina_subgrupos_idx: pagina_subgrupos_idx
                }, function (res) {
                    if (res && res.grupos) {
                        gruposData = res.grupos;
                        subgruposData = res.subgrupos || {};

                        gruposData.sort((a, b) => (a.orden || 0) - (b.orden || 0) || a.comprobante_grupo.localeCompare(b.comprobante_grupo));

                        var filas = aplanarJerarquia(gruposData, subgruposData);
                        poblarFiltros(filas);

                        tablaDT.clear();
                        tablaDT.rows.add(filas);
                        tablaDT.draw();
                    } else {
                        tablaDT.clear().draw();
                        Swal.fire({
                            icon: "warning",
                            title: "Sin datos",
                            text: "No se encontraron grupos de comprobantes"
                        });
                    }
                }, 'json').fail(function () {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Error al cargar los datos"
                    });
                });
            }

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
                cargarTablasDisponibles(0, null);

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
                        cargarTablasDisponibles(res.comprobante_subgrupo_id, res.tabla_id || 0);
                        
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
                var tablaId = $('#tabla_id').val() || 0;

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
                        pagina_idx: pagina_subgrupos_idx,
                        tabla_id: tablaId
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
                $('#tabla_id').empty().append('<option value="0">Sin tabla asociada</option>');
                $('#formComprobanteSubgrupo').removeClass('was-validated');
            }

            // ===========================================
            // INICIALIZACIÓN
            // ===========================================

            // Manejadores para los botones del dropdown de exportación
            $('#btnExportarExcel').click(function (e) {
                e.preventDefault();
                $('.buttons-excel').click();
            });
            $('#btnExportarPDF').click(function (e) {
                e.preventDefault();
                $('.buttons-pdf').click();
            });
            $('#btnExportarCSV').click(function (e) {
                e.preventDefault();
                $('.buttons-csv').click();
            });
            $('#btnExportarPrint').click(function (e) {
                e.preventDefault();
                $('.buttons-print').click();
            });

            cargarBotonesPrincipales();
            inicializarDataTable();
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
        .dt-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .dt-button-collection .dropdown-menu {
            margin-top: 5px;
        }
        .dataTables_wrapper .dt-buttons {
            display: none; /* se usa el dropdown propio del card-header en su lugar */
        }
        .dropdown-menu .dropdown-item i {
            width: 20px;
            text-align: center;
            margin-right: 8px;
        }
        .btn-accion-arbre {
            padding: 2px 6px;
            font-size: 12px;
            margin-right: 3px;
        }
    </style>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>

</html>