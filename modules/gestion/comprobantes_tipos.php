<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Tipos de Comprobantes";
$currentPage = 'comprobantes_tipos';
$modudo_idx = 2;
$pagina_idx = 45; // ID de página para tipos de comprobantes

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<div class="app-content-header">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-6">
                <h3 class="mb-0">
                    <i class="fas fa-file-alt me-2"></i>Tipos de Comprobantes
                </h3>
                <small class="text-muted">Sistema Declarativo Multiempresa</small>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-end">
                    <li class="breadcrumb-item"><a href="#">Home</a></li>
                    <li class="breadcrumb-item"><a href="#">Gestión</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Tipos de Comprobantes</li>
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
                                    <div id="contenedor-boton-agregar" class="d-inline"></div>
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
                                            <label for="filtroGrupo" class="form-label">Filtrar por Grupo</label>
                                            <select class="form-select form-select-sm" id="filtroGrupo">
                                                <option value="">Todos los grupos</option>
                                                <!-- Se llena dinámicamente -->
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="filtroSubgrupo" class="form-label">Filtrar por Subgrupo</label>
                                            <select class="form-select form-select-sm" id="filtroSubgrupo">
                                                <option value="">Todos los subgrupos</option>
                                                <!-- Se llena dinámicamente -->
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="filtroSigno" class="form-label">Filtrar por Signo</label>
                                            <select class="form-select form-select-sm" id="filtroSigno">
                                                <option value="">Todos los signos</option>
                                                <option value="+">Positivo (+)</option>
                                                <option value="-">Negativo (-)</option>
                                                <option value="+/-">Ambos (+/-)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="filtroEstado" class="form-label">Filtrar por Estado</label>
                                            <select class="form-select form-select-sm" id="filtroEstado">
                                                <option value="">Todos los estados</option>
                                                <!-- Se llena dinámicamente -->
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Botones de limpiar filtros -->
                                    <div class="row mb-3">
                                        <div class="col-12">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimpiarFiltros">
                                                <i class="fas fa-filter-circle-xmark me-1"></i>Limpiar Filtros
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAplicarFiltros">
                                                <i class="fas fa-filter me-1"></i>Aplicar Filtros
                                            </button>
                                            <small class="text-muted ms-2" id="contadorResultados"></small>
                                        </div>
                                    </div>

                                    <!-- DataTable -->
                                    <table id="tablaComprobantesTipos" class="table table-striped table-bordered"
                                        style="width:100%">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="80">ID</th>
                                                <th width="100">Código</th>
                                                <th width="200">Tipo de Comprobante</th>
                                                <th width="120">Grupo</th>
                                                <th width="120">Subgrupo</th>
                                                <th width="120">Letra</th>
                                                <th width="100">Signo</th>
                                                <th width="120">Estado</th>
                                                <th width="220" class="text-center">Acciones</th>
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

        <!-- Modal para crear/editar tipo de comprobante -->
        <div class="modal fade" id="modalComprobanteTipo" tabindex="-1" aria-labelledby="modalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalLabel">Tipo de Comprobante</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <form id="formComprobanteTipo" class="needs-validation" novalidate>
                            <input type="hidden" id="comprobante_tipo_id" name="comprobante_tipo_id" />
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="codigo" class="form-label">Código *</label>
                                    <input type="text" class="form-control" id="codigo" name="codigo" 
                                        maxlength="10" required>
                                    <div class="invalid-feedback">El código es obligatorio (max 10 caracteres)</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="comprobante_tipo" class="form-label">Tipo de Comprobante *</label>
                                    <input type="text" class="form-control" id="comprobante_tipo"
                                        name="comprobante_tipo" maxlength="100" required>
                                    <div class="invalid-feedback">El nombre del tipo es obligatorio</div>
                                    <div class="form-text">Máximo 100 caracteres</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="comprobante_grupo_id" class="form-label">Grupo *</label>
                                    <select class="form-select" id="comprobante_grupo_id" name="comprobante_grupo_id" required>
                                        <option value="">Seleccionar Grupo</option>
                                        <!-- Se llena dinámicamente con AJAX -->
                                    </select>
                                    <div class="invalid-feedback">Debe seleccionar un grupo</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="comprobante_subgrupo_id" class="form-label">Subgrupo *</label>
                                    <select class="form-select" id="comprobante_subgrupo_id" name="comprobante_subgrupo_id" required>
                                        <option value="">Seleccionar Subgrupo</option>
                                        <!-- Se llena dinámicamente con AJAX -->
                                    </select>
                                    <div class="invalid-feedback">Debe seleccionar un subgrupo</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="comprobante_fiscal_id" class="form-label">Comprobante Fiscal</label>
                                    <select class="form-select" id="comprobante_fiscal_id" name="comprobante_fiscal_id">
                                        <option value="0">Sin comprobante fiscal</option>
                                        <!-- Se llena dinámicamente con AJAX -->
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="letra" class="form-label">Letra</label>
                                    <input type="text" class="form-control" id="letra" name="letra" maxlength="1">
                                    <div class="form-text">Ej: A, B, C, etc.</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="signo" class="form-label">Signo *</label>
                                    <select class="form-select" id="signo" name="signo" required>
                                        <option value="+">Positivo (+)</option>
                                        <option value="-">Negativo (-)</option>
                                        <option value="+/-">Ambos (+/-)</option>
                                    </select>
                                    <div class="invalid-feedback">Debe seleccionar un signo</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="impacta_stock" name="impacta_stock" value="1">
                                        <label class="form-check-label" for="impacta_stock">Impacta Stock</label>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="impacta_contabilidad" name="impacta_contabilidad" value="1">
                                        <label class="form-check-label" for="impacta_contabilidad">Impacta Contabilidad</label>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="impacta_ctacte" name="impacta_ctacte" value="1">
                                        <label class="form-check-label" for="impacta_ctacte">Impacta Cta. Cte.</label>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="orden" class="form-label">Orden</label>
                                    <input type="number" class="form-control" id="orden" name="orden" min="1" value="1">
                                    <div class="form-text">Orden de visualización</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="comentario" class="form-label">Comentario</label>
                                    <input type="text" class="form-control" id="comentario" name="comentario" maxlength="255">
                                    <div class="form-text">Máximo 255 caracteres</div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="btnGuardar">
                            <i class="fas fa-save me-1"></i>Guardar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Estilos personalizados -->
<style>
    .dt-buttons .btn {
        margin-right: 5px;
        margin-bottom: 5px;
    }
    .dt-button-collection .dropdown-menu {
        margin-top: 5px;
    }
    .dataTables_wrapper .dt-buttons {
        float: right;
        margin-top: 5px;
    }
    .dropdown-menu .dropdown-item i {
        width: 20px;
        text-align: center;
        margin-right: 8px;
    }
    .badge-impacto {
        font-size: 0.7em;
        margin-right: 3px;
    }
    .filter-active {
        background-color: #e8f4ff !important;
        border-left: 3px solid #0d6efd !important;
    }
    #contadorResultados {
        font-style: italic;
    }
</style>

<script>
    $(document).ready(function () {
        // Variables de contexto MULTIEMPRESA
        const empresa_idx = 2;
        const pagina_idx = <?php echo $pagina_idx; ?>;

        // Variables para mantener el estado del DataTable
        var tabla;
        var currentPage = 0;
        var currentOrder = [[1, 'asc']];
        var currentSearch = '';
        
        // Cache de datos para dropdowns
        var gruposCache = [];
        var subgruposCache = [];
        var comprobantesFiscalesCache = [];
        var estadosCache = [];

        // Variables para filtros
        var filtroGrupo = '';
        var filtroSubgrupo = '';
        var filtroSigno = '';
        var filtroEstado = '';

        // Función para cargar grupos (para filtros y modal)
        function cargarGrupos(paraFiltro = false) {
            return $.get('comprobantes_tipos_ajax.php', {
                accion: 'obtener_grupos',
                empresa_idx: empresa_idx
            }).then(function(data) {
                gruposCache = data;
                
                if (paraFiltro) {
                    // Para filtros
                    $('#filtroGrupo').empty().append('<option value="">Todos los grupos</option>');
                    $.each(data, function(index, grupo) {
                        $('#filtroGrupo').append('<option value="' + grupo.comprobante_grupo_id + '">' + grupo.comprobante_grupo + '</option>');
                    });
                } else {
                    // Para modal
                    $('#comprobante_grupo_id').empty().append('<option value="">Seleccionar Grupo</option>');
                    $.each(data, function(index, grupo) {
                        $('#comprobante_grupo_id').append('<option value="' + grupo.comprobante_grupo_id + '">' + grupo.comprobante_grupo + '</option>');
                    });
                }
                return data;
            });
        }

        // Función para cargar subgrupos basados en grupo seleccionado
        function cargarSubgrupos(grupoId, paraFiltro = false) {
            return $.get('comprobantes_tipos_ajax.php', {
                accion: 'obtener_subgrupos',
                empresa_idx: empresa_idx,
                comprobante_grupo_id: grupoId || 0
            }).then(function(data) {
                subgruposCache = data;
                
                if (paraFiltro) {
                    // Para filtros
                    $('#filtroSubgrupo').empty().append('<option value="">Todos los subgrupos</option>');
                    $.each(data, function(index, subgrupo) {
                        $('#filtroSubgrupo').append('<option value="' + subgrupo.comprobante_subgrupo_id + '">' + subgrupo.comprobante_subgrupo + '</option>');
                    });
                } else {
                    // Para modal
                    $('#comprobante_subgrupo_id').empty().append('<option value="">Seleccionar Subgrupo</option>');
                    $.each(data, function(index, subgrupo) {
                        $('#comprobante_subgrupo_id').append('<option value="' + subgrupo.comprobante_subgrupo_id + '">' + subgrupo.comprobante_subgrupo + '</option>');
                    });
                }
                return data;
            });
        }

        // Función para cargar comprobantes fiscales
        function cargarComprobantesFiscales() {
            return $.get('comprobantes_tipos_ajax.php', {
                accion: 'obtener_comprobantes_fiscales',
                empresa_idx: empresa_idx
            }).then(function(data) {
                comprobantesFiscalesCache = data;
                $('#comprobante_fiscal_id').empty().append('<option value="0">Sin comprobante fiscal</option>');
                $.each(data, function(index, fiscal) {
                    $('#comprobante_fiscal_id').append('<option value="' + fiscal.comprobante_fiscal_id + '">' + fiscal.codigo_pad + ' - ' + fiscal.comprobante_fiscal + '</option>');
                });
                return data;
            });
        }

        // Función para cargar estados (para filtros)
        function cargarEstados() {
            return $.get('comprobantes_tipos_ajax.php', {
                accion: 'obtener_estados',
                empresa_idx: empresa_idx
            }).then(function(data) {
                estadosCache = data;
                $('#filtroEstado').empty().append('<option value="">Todos los estados</option>');
                $.each(data, function(index, estado) {
                    $('#filtroEstado').append('<option value="' + estado.estado_registro_id + '">' + estado.estado_registro + '</option>');
                });
                return data;
            });
        }

        // Cuando cambia el grupo en el modal, actualizar subgrupos
        $(document).on('change', '#comprobante_grupo_id', function() {
            var grupoId = $(this).val();
            if (grupoId) {
                cargarSubgrupos(grupoId, false);
            } else {
                $('#comprobante_subgrupo_id').empty().append('<option value="">Seleccionar Subgrupo</option>');
            }
        });

        // Cuando cambia el grupo en el filtro, actualizar subgrupos del filtro
        $(document).on('change', '#filtroGrupo', function() {
            var grupoId = $(this).val();
            filtroGrupo = grupoId;
            
            // Si se selecciona un grupo, cargar sus subgrupos
            if (grupoId) {
                cargarSubgrupos(grupoId, true);
            } else {
                // Si no hay grupo seleccionado, cargar todos los subgrupos
                cargarSubgrupos(0, true);
                filtroSubgrupo = '';
                $('#filtroSubgrupo').val('');
            }
        });

        // Cuando cambia el subgrupo en el filtro
        $(document).on('change', '#filtroSubgrupo', function() {
            filtroSubgrupo = $(this).val();
        });

        // Cuando cambia el signo en el filtro
        $(document).on('change', '#filtroSigno', function() {
            filtroSigno = $(this).val();
        });

        // Cuando cambia el estado en el filtro
        $(document).on('change', '#filtroEstado', function() {
            filtroEstado = $(this).val();
        });

        // Aplicar filtros
        $('#btnAplicarFiltros').click(function() {
            aplicarFiltros();
        });

        // Limpiar filtros
        $('#btnLimpiarFiltros').click(function() {
            $('#filtroGrupo').val('');
            $('#filtroSubgrupo').val('');
            $('#filtroSigno').val('');
            $('#filtroEstado').val('');
            
            filtroGrupo = '';
            filtroSubgrupo = '';
            filtroSigno = '';
            filtroEstado = '';
            
            // Recargar todos los subgrupos
            cargarSubgrupos(0, true);
            
            aplicarFiltros();
        });

        // Aplicar filtros al DataTable
        function aplicarFiltros() {
            if (tabla) {
                // Guardar estado actual
                var savedState = {
                    page: tabla.page(),
                    order: tabla.order(),
                    search: tabla.search()
                };
                
                // Aplicar filtros
                tabla.ajax.reload(function(json) {
                    // Restaurar estado
                    if (savedState.page !== undefined) {
                        tabla.page(savedState.page).draw('page');
                    }
                    if (savedState.search && savedState.search !== '') {
                        tabla.search(savedState.search).draw();
                    }
                    
                    // Actualizar contador
                    actualizarContadorResultados();
                }, false);
            }
        }

        // Actualizar contador de resultados
        function actualizarContadorResultados() {
            if (tabla) {
                var total = tabla.rows({ search: 'applied' }).count();
                var texto = '';
                
                if (filtroGrupo || filtroSubgrupo || filtroSigno || filtroEstado) {
                    texto = 'Mostrando ' + total + ' resultado' + (total !== 1 ? 's' : '');
                    
                    // Agregar detalles de los filtros aplicados
                    var filtrosAplicados = [];
                    
                    if (filtroGrupo) {
                        var grupoNombre = $('#filtroGrupo option:selected').text();
                        filtrosAplicados.push('Grupo: ' + grupoNombre);
                    }
                    
                    if (filtroSubgrupo) {
                        var subgrupoNombre = $('#filtroSubgrupo option:selected').text();
                        filtrosAplicados.push('Subgrupo: ' + subgrupoNombre);
                    }
                    
                    if (filtroSigno) {
                        var signoNombre = $('#filtroSigno option:selected').text();
                        filtrosAplicados.push('Signo: ' + signoNombre);
                    }
                    
                    if (filtroEstado) {
                        var estadoNombre = $('#filtroEstado option:selected').text();
                        filtrosAplicados.push('Estado: ' + estadoNombre);
                    }
                    
                    if (filtrosAplicados.length > 0) {
                        texto += ' (Filtros: ' + filtrosAplicados.join(', ') + ')';
                    }
                } else {
                    texto = 'Total: ' + total + ' tipo' + (total !== 1 ? 's' : '') + ' de comprobante' + (total !== 1 ? 's' : '');
                }
                
                $('#contadorResultados').text(texto);
            }
        }

        // Función para inicializar DataTable
        function inicializarDataTable() {
            // Destruir DataTable existente si hay uno
            if ($.fn.DataTable.isDataTable('#tablaComprobantesTipos')) {
                $('#tablaComprobantesTipos').DataTable().destroy();
                $('#tablaComprobantesTipos tbody').empty();
            }

            // Configuración de DataTable con filtros personalizados
            tabla = $('#tablaComprobantesTipos').DataTable({
                ajax: {
                    url: 'comprobantes_tipos_ajax.php',
                    type: 'GET',
                    data: function(d) {
                        // Agregar parámetros de filtro a la solicitud AJAX
                        d.accion = 'listar';
                        d.empresa_idx = empresa_idx;
                        d.pagina_idx = pagina_idx;
                        d.filtro_grupo = filtroGrupo;
                        d.filtro_subgrupo = filtroSubgrupo;
                        d.filtro_signo = filtroSigno;
                        d.filtro_estado = filtroEstado;
                    },
                    dataSrc: ''
                },
                stateSave: true,
                stateSaveParams: function (settings, data) {
                    data.page = currentPage;
                    data.order = currentOrder;
                    if (currentSearch !== '-1' && currentSearch !== '') {
                        data.search = { search: currentSearch };
                    } else {
                        data.search = { search: '' };
                    }
                    delete data.columns;
                    return data;
                },
                stateLoadParams: function (settings, data) {
                    if (data.page !== undefined) currentPage = data.page;
                    if (data.order !== undefined && data.order.length > 0) currentOrder = data.order;
                    if (data.search && data.search.search !== undefined) {
                        var searchValue = data.search.search;
                        if (searchValue === '-1' || searchValue === '') {
                            currentSearch = '';
                        } else {
                            currentSearch = searchValue;
                        }
                    } else {
                        currentSearch = '';
                    }
                    data.search = { search: currentSearch };
                },
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>' +
                     '<"clear">',
                pageLength: 50,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                buttons: [
                    {
                        extend: 'excelHtml5',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn btn-success btn-sm',
                        title: 'Tipos de Comprobantes',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7],
                            orthogonal: 'export',
                            modifier: {
                                search: 'applied'
                            }
                        },
                        messageTop: function() {
                            var mensaje = 'Tipos de Comprobantes';
                            if (filtroGrupo || filtroSubgrupo || filtroSigno || filtroEstado) {
                                mensaje += ' (Filtrado)';
                            }
                            return mensaje;
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        className: 'btn btn-danger btn-sm',
                        title: 'Tipos de Comprobantes',
                        orientation: 'portrait',
                        pageSize: 'A4',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7],
                            orthogonal: 'export',
                            modifier: {
                                search: 'applied'
                            }
                        },
                        messageTop: function() {
                            var mensaje = 'Tipos de Comprobantes';
                            if (filtroGrupo || filtroSubgrupo || filtroSigno || filtroEstado) {
                                mensaje += ' (Filtrado)';
                            }
                            return mensaje;
                        }
                    },
                    {
                        extend: 'csvHtml5',
                        text: '<i class="fas fa-file-csv"></i> CSV',
                        className: 'btn btn-primary btn-sm',
                        title: 'Tipos_Comprobantes',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7],
                            modifier: {
                                search: 'applied'
                            }
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Imprimir',
                        className: 'btn btn-secondary btn-sm',
                        title: 'Tipos de Comprobantes',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7],
                            stripHtml: false,
                            modifier: {
                                search: 'applied'
                            }
                        },
                        messageTop: function() {
                            var mensaje = 'Tipos de Comprobantes';
                            if (filtroGrupo || filtroSubgrupo || filtroSigno || filtroEstado) {
                                mensaje += ' (Filtrado)';
                            }
                            return mensaje;
                        }
                    }
                ],
                columns: [
                    {
                        data: 'comprobante_tipo_id',
                        className: 'text-center fw-bold'
                    },
                    {
                        data: 'codigo',
                        className: 'text-center fw-medium'
                    },
                    {
                        data: 'comprobante_tipo',
                        render: function (data, type, row) {
                            if (type === 'export') return data;
                            var html = '<div class="fw-medium">' + data + '</div>';
                            if (row.comentario) {
                                html += '<small class="text-muted d-block">' + row.comentario + '</small>';
                            }
                            return html;
                        }
                    },
                    {
                        data: 'grupo_info',
                        render: function (data, type, row) {
                            if (type === 'export') return data?.comprobante_grupo || '';
                            return data?.comprobante_grupo ? '<span class="fw-medium">' + data.comprobante_grupo + '</span>' : '';
                        }
                    },
                    {
                        data: 'subgrupo_info',
                        render: function (data, type, row) {
                            if (type === 'export') return data?.comprobante_subgrupo || '';
                            return data?.comprobante_subgrupo ? '<span class="fw-medium">' + data.comprobante_subgrupo + '</span>' : '';
                        }
                    },
                    {
                        data: 'letra',
                        className: 'text-center',
                        render: function (data, type, row) {
                            if (type === 'export') return data || '';
                            return data ? '<span class="badge bg-info">' + data + '</span>' : '';
                        }
                    },
                    {
                        data: 'signo',
                        className: 'text-center',
                        render: function (data, type, row) {
                            if (type === 'export') return data;
                            var badgeClass = data === '+' ? 'bg-success' : data === '-' ? 'bg-danger' : 'bg-warning';
                            return '<span class="badge ' + badgeClass + '">' + data + '</span>';
                        }
                    },
                    {
                        data: 'estado_info',
                        className: 'text-center',
                        render: function (data, type, row) {
                            if (!data || !data.estado_registro) {
                                if (type === 'export') return 'Sin estado';
                                return '<span class="fw-medium">Sin estado</span>';
                            }
                            if (type === 'export') return data.estado_registro;
                            
                            var estado = data.estado_registro;
                            var estadoHtml = '<span class="fw-medium">' + estado + '</span>';
                            
                            // Agregar badges para los impactos
                            var impactosHtml = '';
                            if (row.impacta_stock == 1) {
                                impactosHtml += '<span class="badge badge-impacto bg-purple" title="Impacta Stock">S</span>';
                            }
                            if (row.impacta_contabilidad == 1) {
                                impactosHtml += '<span class="badge badge-impacto bg-blue" title="Impacta Contabilidad">C</span>';
                            }
                            if (row.impacta_ctacte == 1) {
                                impactosHtml += '<span class="badge badge-impacto bg-teal" title="Impacta Cuenta Corriente">CT</span>';
                            }
                            
                            if (impactosHtml) {
                                estadoHtml += '<div class="mt-1">' + impactosHtml + '</div>';
                            }
                            
                            return estadoHtml;
                        }
                    },
                    {
                        data: 'botones',
                        orderable: false,
                        searchable: false,
                        className: "text-center",
                        width: '220px',
                        render: function (data, type, row) {
                            if (type === 'export') return '';
                            var botones = '';
                            if (data && data.length > 0) {
                                var editarBoton = '';
                                var otrosBotones = '';
                                data.forEach(boton => {
                                    var claseBoton = 'btn-sm me-1 ';
                                    if (boton.bg_clase && boton.text_clase) {
                                        claseBoton += boton.bg_clase + ' ' + boton.text_clase;
                                    } else if (boton.color_clase) {
                                        claseBoton += boton.color_clase;
                                    } else {
                                        claseBoton += 'btn-outline-primary';
                                    }
                                    var titulo = boton.descripcion || boton.nombre_funcion;
                                    var accionJs = boton.accion_js;
                                    var icono = boton.icono_clase ? `<i class="${boton.icono_clase}"></i>` : '';
                                    var esConfirmable = boton.es_confirmable || 0;
                                    var botonHtml = `<button type="button" class="btn ${claseBoton} btn-accion" 
                                       title="${titulo}" 
                                       data-id="${row.comprobante_tipo_id}" 
                                       data-accion="${accionJs}"
                                       data-confirmable="${esConfirmable}"
                                       data-tipo="${row.comprobante_tipo}">
                                    ${icono}
                                </button>`;
                                    if (accionJs === 'editar') {
                                        editarBoton = botonHtml;
                                    } else {
                                        otrosBotones += botonHtml;
                                    }
                                });
                                botones = editarBoton + otrosBotones;
                            } else {
                                botones = '<span class="text-muted small">Sin acciones</span>';
                            }
                            return `<div class="btn-group" role="group">${botones}</div>`;
                        }
                    }
                ],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                },
                order: currentOrder,
                responsive: true,
                createdRow: function (row, data, dataIndex) {
                    if (data.estado_info && data.estado_info.codigo_estandar === 'INACTIVO') {
                        $(row).addClass('table-secondary');
                    } else if (data.estado_info && data.estado_info.codigo_estandar === 'BLOQUEADO') {
                        $(row).addClass('table-warning');
                    }
                },
                initComplete: function () {
                    var buttons = new $.fn.dataTable.Buttons(tabla, {
                        buttons: ['excelHtml5', 'pdfHtml5', 'csvHtml5', 'print']
                    }).container().appendTo($('#tablaComprobantesTipos_wrapper .col-md-6:eq(1)'));
                    $(tabla.table().container()).on('page.dt', function (e) {
                        currentPage = tabla.page();
                    });
                    $(tabla.table().container()).on('order.dt', function (e, settings, details) {
                        currentOrder = tabla.order();
                    });
                    $(tabla.table().container()).on('search.dt', function (e, settings) {
                        currentSearch = tabla.search();
                    });
                    
                    // Actualizar contador después de inicializar
                    setTimeout(function() {
                        actualizarContadorResultados();
                    }, 500);
                    
                    setTimeout(function () {
                        var searchInput = $('.dataTables_filter input');
                        if (searchInput.val() === '-1' || searchInput.val() === '') {
                            searchInput.val('');
                            currentSearch = '';
                            var savedData = localStorage.getItem('DataTables_' + tabla.settings()[0].sInstance);
                            if (savedData) {
                                var data = JSON.parse(savedData);
                                if (data.search && (data.search.search === '-1' || data.search.search === '')) {
                                    data.search.search = '';
                                    localStorage.setItem('DataTables_' + tabla.settings()[0].sInstance, JSON.stringify(data));
                                }
                            }
                        }
                    }, 100);
                },
                drawCallback: function() {
                    // Actualizar contador después de cada dibujo
                    actualizarContadorResultados();
                }
            });
            inicializarEventos();
        }

        // Función para inicializar eventos
        function inicializarEventos() {
            $('#btnRecargar').off('click').on('click', function () {
                var btn = $(this);
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                var savedState = {
                    page: tabla.page(),
                    order: tabla.order(),
                    search: tabla.search()
                };
                tabla.ajax.reload(function (json) {
                    if (savedState.page !== undefined) {
                        tabla.page(savedState.page).draw('page');
                    }
                    if (savedState.search && savedState.search !== '') {
                        tabla.search(savedState.search).draw();
                    }
                    btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
                }, false);
            });
        }

        // Cargar botón Agregar dinámicamente
        function cargarBotonAgregar() {
            $.get('comprobantes_tipos_ajax.php', {
                accion: 'obtener_boton_agregar',
                pagina_idx: pagina_idx
            }, function (botonAgregar) {
                if (botonAgregar && botonAgregar.nombre_funcion) {
                    var icono = botonAgregar.icono_clase ? `<i class="${botonAgregar.icono_clase} me-1"></i>` : '';
                    var colorClase = 'btn-primary';
                    if (botonAgregar.bg_clase && botonAgregar.text_clase) {
                        colorClase = botonAgregar.bg_clase + ' ' + botonAgregar.text_clase;
                    } else if (botonAgregar.color_clase) {
                        colorClase = botonAgregar.color_clase;
                    }
                    $('#contenedor-boton-agregar').html(
                        `<button type="button" class="btn ${colorClase}" id="btnNuevo">
                    ${icono}${botonAgregar.nombre_funcion}
                 </button>`
                    );
                } else {
                    $('#contenedor-boton-agregar').html(
                        '<button type="button" class="btn btn-primary" id="btnNuevo">' +
                        '<i class="fas fa-plus me-1"></i>Agregar Tipo</button>'
                    );
                }
            }, 'json');
        }

        // Manejador para botón "Agregar"
        $(document).on('click', '#btnNuevo', function () {
            resetModal();
            $('#modalLabel').text('Nuevo Tipo de Comprobante');
            
            // Cargar datos para los dropdowns
            Promise.all([
                cargarGrupos(false),
                cargarSubgrupos(0, false),
                cargarComprobantesFiscales()
            ]).then(function() {
                var modal = new bootstrap.Modal(document.getElementById('modalComprobanteTipo'));
                modal.show();
                $('#codigo').focus();
            });
        });

        // Manejador para botones de acción dinámicos
        $(document).on('click', '.btn-accion', function () {
            var tipoId = $(this).data('id');
            var accionJs = $(this).data('accion');
            var confirmable = $(this).data('confirmable');
            var tipo = $(this).data('tipo');
            if (accionJs === 'editar') {
                cargarTipoParaEditar(tipoId);
            } else if (confirmable == 1) {
                Swal.fire({
                    title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                    html: `¿Está seguro de <strong>${accionJs}</strong> el tipo <strong>"${tipo}"</strong>?`,
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
                        ejecutarAccion(tipoId, accionJs, tipo);
                    }
                });
            } else {
                ejecutarAccion(tipoId, accionJs, tipo);
            }
        });

        // Función para ejecutar cualquier acción del backend
        function ejecutarAccion(tipoId, accionJs, tipo) {
            var savedState = {
                page: tabla.page(),
                order: tabla.order(),
                search: tabla.search()
            };
            $.post('comprobantes_tipos_ajax.php', {
                accion: 'ejecutar_accion',
                comprobante_tipo_id: tipoId,
                accion_js: accionJs,
                empresa_idx: empresa_idx,
                pagina_idx: pagina_idx
            }, function (res) {
                if (res.success) {
                    tabla.ajax.reload(function (json) {
                        if (savedState.page !== undefined) {
                            tabla.page(savedState.page).draw('page');
                        }
                        if (savedState.search && savedState.search !== '') {
                            tabla.search(savedState.search).draw();
                        }
                        tabla.rows().every(function (rowIdx, tableLoop, rowLoop) {
                            var data = this.data();
                            if (data.comprobante_tipo_id == tipoId) {
                                $(this.node()).addClass('table-success');
                                setTimeout(function () {
                                    $(this.node()).removeClass('table-success');
                                }.bind(this), 2000);
                            }
                        });
                        Swal.fire({
                            icon: "success",
                            title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                            text: res.message || `Tipo "${tipo}" actualizado correctamente`,
                            showConfirmButton: false,
                            timer: 1500,
                            toast: true,
                            position: 'top-end'
                        });
                    }, false);
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || `Error al ${accionJs} el tipo`,
                        confirmButtonText: "Entendido"
                    });
                }
            }, 'json');
        }

        // Función para cargar tipo en modal de edición
        function cargarTipoParaEditar(tipoId) {
            $.get('comprobantes_tipos_ajax.php', {
                accion: 'obtener',
                comprobante_tipo_id: tipoId,
                empresa_idx: empresa_idx
            }, function (res) {
                if (res && res.comprobante_tipo_id) {
                    resetModal();
                    $('#comprobante_tipo_id').val(res.comprobante_tipo_id);
                    $('#codigo').val(res.codigo);
                    $('#comprobante_tipo').val(res.comprobante_tipo);
                    $('#letra').val(res.letra || '');
                    $('#signo').val(res.signo || '+');
                    $('#orden').val(res.orden || 1);
                    $('#comentario').val(res.comentario || '');
                    $('#impacta_stock').prop('checked', res.impacta_stock == 1);
                    $('#impacta_contabilidad').prop('checked', res.impacta_contabilidad == 1);
                    $('#impacta_ctacte').prop('checked', res.impacta_ctacte == 1);
                    
                    // Cargar dropdowns y luego seleccionar valores
                    Promise.all([
                        cargarGrupos(false),
                        cargarSubgrupos(res.comprobante_grupo_id, false),
                        cargarComprobantesFiscales()
                    ]).then(function() {
                        // Esperar a que los dropdowns se llenen antes de seleccionar
                        setTimeout(function() {
                            if (res.comprobante_grupo_id) {
                                $('#comprobante_grupo_id').val(res.comprobante_grupo_id);
                            }
                            if (res.comprobante_subgrupo_id) {
                                $('#comprobante_subgrupo_id').val(res.comprobante_subgrupo_id);
                            }
                            if (res.comprobante_fiscal_id) {
                                $('#comprobante_fiscal_id').val(res.comprobante_fiscal_id);
                            }
                            
                            $('#modalLabel').text('Editar Tipo de Comprobante');
                            var modal = new bootstrap.Modal(document.getElementById('modalComprobanteTipo'));
                            modal.show();
                        }, 100);
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Error al obtener datos del tipo",
                        confirmButtonText: "Entendido"
                    });
                }
            }, 'json');
        }

        // Función para resetear el modal
        function resetModal() {
            $('#formComprobanteTipo')[0].reset();
            $('#comprobante_tipo_id').val('');
            $('#formComprobanteTipo').removeClass('was-validated');
            $('#signo').val('+');
            $('#orden').val(1);
            $('#comprobante_grupo_id').empty().append('<option value="">Seleccionar Grupo</option>');
            $('#comprobante_subgrupo_id').empty().append('<option value="">Seleccionar Subgrupo</option>');
            $('#comprobante_fiscal_id').empty().append('<option value="0">Sin comprobante fiscal</option>');
        }

        // Validación del formulario
        $('#btnGuardar').click(function () {
            var form = document.getElementById('formComprobanteTipo');
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return false;
            }
            var id = $('#comprobante_tipo_id').val();
            var accionBackend = id ? 'editar' : 'agregar';
            var codigo = $('#codigo').val().trim();
            var comprobanteTipo = $('#comprobante_tipo').val().trim();
            var grupoId = $('#comprobante_grupo_id').val();
            var subgrupoId = $('#comprobante_subgrupo_id').val();
            var fiscalId = $('#comprobante_fiscal_id').val() || 0;
            var letra = $('#letra').val().trim();
            var signo = $('#signo').val();
            var orden = $('#orden').val() || 1;
            var comentario = $('#comprobante_tipo').val().trim();
            var impactaStock = $('#impacta_stock').is(':checked') ? 1 : 0;
            var impactaContabilidad = $('#impacta_contabilidad').is(':checked') ? 1 : 0;
            var impactaCtacte = $('#impacta_ctacte').is(':checked') ? 1 : 0;
            
            if (!comprobanteTipo) {
                $('#comprobante_tipo').addClass('is-invalid');
                return false;
            }
            if (comprobanteTipo.length > 100) {
                $('#comprobante_tipo').addClass('is-invalid');
                return false;
            }
            if (codigo.length > 10) {
                $('#codigo').addClass('is-invalid');
                return false;
            }
            if (!grupoId) {
                $('#comprobante_grupo_id').addClass('is-invalid');
                return false;
            }
            if (!subgrupoId) {
                $('#comprobante_subgrupo_id').addClass('is-invalid');
                return false;
            }
            if (letra.length > 1) {
                $('#letra').addClass('is-invalid');
                return false;
            }
            
            var btnGuardar = $(this);
            var originalText = btnGuardar.html();
            btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
            var savedState = {
                page: tabla.page(),
                order: tabla.order(),
                search: tabla.search()
            };
            
            $.ajax({
                url: 'comprobantes_tipos_ajax.php',
                type: 'POST',
                data: {
                    accion: accionBackend,
                    comprobante_tipo_id: id,
                    codigo: codigo,
                    comprobante_tipo: comprobanteTipo,
                    comprobante_grupo_id: grupoId,
                    comprobante_subgrupo_id: subgrupoId,
                    comprobante_fiscal_id: fiscalId,
                    letra: letra,
                    signo: signo,
                    orden: orden,
                    comentario: comentario,
                    impacta_stock: impactaStock,
                    impacta_contabilidad: impactaContabilidad,
                    impacta_ctacte: impactaCtacte,
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                },
                success: function (res) {
                    if (res.resultado) {
                        tabla.ajax.reload(function (json) {
                            if (savedState.page !== undefined) {
                                tabla.page(savedState.page).draw('page');
                            }
                            if (savedState.search && savedState.search !== '') {
                                tabla.search(savedState.search).draw();
                            }
                            if (id) {
                                tabla.rows().every(function (rowIdx, tableLoop, rowLoop) {
                                    var data = this.data();
                                    if (data.comprobante_tipo_id == id) {
                                        $(this.node()).addClass('table-success');
                                        setTimeout(function () {
                                            $(this.node()).removeClass('table-success');
                                        }.bind(this), 2000);
                                    }
                                });
                            }
                            btnGuardar.prop('disabled', false).html(originalText);
                            Swal.fire({
                                icon: "success",
                                title: "¡Guardado!",
                                text: "Tipo de comprobante guardado correctamente",
                                showConfirmButton: false,
                                timer: 1500,
                                toast: true,
                                position: 'top-end'
                            });
                            var modalEl = document.getElementById('modalComprobanteTipo');
                            var modal = bootstrap.Modal.getInstance(modalEl);
                            modal.hide();
                        }, false);
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

        // Manejadores para los botones del dropdown
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

        // Inicializar
        Promise.all([
            cargarGrupos(true),
            cargarSubgrupos(0, true),
            cargarEstados()
        ]).then(function() {
            inicializarDataTable();
            cargarBotonAgregar();
            
            // Inicializar tooltips
            $('[title]').tooltip({
                trigger: 'hover',
                placement: 'top'
            });
        });
    });
</script>

<!-- Librerías adicionales necesarias -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>