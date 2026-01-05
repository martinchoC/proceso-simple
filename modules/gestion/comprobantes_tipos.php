<?php
// Configuración de la página


$pageTitle = "Tipos de Comprobantes";
$currentPage = 'comprobantes_tipos';
$modudo_idx = 2;
$pagina_idx = 45; // ID de página para tipos de comprobantes

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-file-invoice me-2"></i>Tipos de Comprobantes
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
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Recargar tabla">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Exportar datos">
                                                <i class="fas fa-file-export"></i> Exportar
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item" href="#" id="btnExportarExcel"><i class="fas fa-file-excel text-success"></i> Excel</a></li>
                                                <li><a class="dropdown-item" href="#" id="btnExportarPDF"><i class="fas fa-file-pdf text-danger"></i> PDF</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item" href="#" id="btnExportarCSV"><i class="fas fa-file-csv text-primary"></i> CSV</a></li>
                                                <li><a class="dropdown-item" href="#" id="btnExportarPrint"><i class="fas fa-print text-secondary"></i> Imprimir</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <div class="card-body">
                                        <!-- Filtros -->
                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <label for="filterGrupo" class="form-label">Filtrar por Grupo</label>
                                                <select class="form-select form-select-sm" id="filterGrupo">
                                                    <option value="">Todos los grupos</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="filterEstado" class="form-label">Filtrar por Estado</label>
                                                <select class="form-select form-select-sm" id="filterEstado">
                                                    <option value="">Todos los estados</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="filterBusqueda" class="form-label">Búsqueda general</label>
                                                <input type="text" class="form-control form-control-sm" id="filterBusqueda" placeholder="Buscar...">
                                            </div>
                                        </div>

                                        <!-- DataTable -->
                                        <table id="tablaComprobantesTipos" class="table table-striped table-bordered" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="80">ID</th>
                                                    <th width="100">Código</th>
                                                    <th width="150">Tipo</th>
                                                    <th width="120">Grupo</th>
                                                    <th width="120">Comprobante Fiscal</th>
                                                    <th width="100">Signo</th>
                                                    <th width="100">Letra</th>
                                                    <th width="150" class="text-center">Impactos</th>
                                                    <th width="80">Orden</th>
                                                    <th width="120">Estado</th>
                                                    <th width="200" class="text-center">Acciones</th>
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
            <div class="modal fade" id="modalComprobanteTipo" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Tipo de Comprobante</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formComprobanteTipo" class="needs-validation" novalidate>
                                <input type="hidden" id="comprobante_tipo_id" name="comprobante_tipo_id" />
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="codigo" class="form-label">Código *</label>
                                        <input type="text" class="form-control" id="codigo" name="codigo" 
                                               maxlength="10" required>
                                        <div class="invalid-feedback">El código es obligatorio</div>
                                        <div class="form-text">Máximo 10 caracteres</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="comprobante_tipo" class="form-label">Tipo de Comprobante *</label>
                                        <input type="text" class="form-control" id="comprobante_tipo" name="comprobante_tipo" 
                                               maxlength="100" required>
                                        <div class="invalid-feedback">El tipo de comprobante es obligatorio</div>
                                        <div class="form-text">Máximo 100 caracteres</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="comprobante_grupo_id" class="form-label">Grupo *</label>
                                        <select class="form-select" id="comprobante_grupo_id" name="comprobante_grupo_id" required>
                                            <option value="">Seleccionar grupo...</option>
                                        </select>
                                        <div class="invalid-feedback">Debe seleccionar un grupo</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="comprobante_fiscal_id" class="form-label">Comprobante Fiscal *</label>
                                        <select class="form-select" id="comprobante_fiscal_id" name="comprobante_fiscal_id" required>
                                            <option value="">Seleccionar comprobante fiscal...</option>
                                        </select>
                                        <div class="invalid-feedback">Debe seleccionar un comprobante fiscal</div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="letra" class="form-label">Letra</label>
                                        <input type="text" class="form-control" id="letra" name="letra" 
                                               maxlength="1">
                                        <div class="form-text">Máximo 1 caracter (A, B, C, etc.)</div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="signo" class="form-label">Signo *</label>
                                        <select class="form-select" id="signo" name="signo" required>
                                            <option value="+">Positivo (+)</option>
                                            <option value="-">Negativo (-)</option>
                                            <option value="+/-">Positivo/Negativo (+/-)</option>
                                        </select>
                                        <div class="invalid-feedback">Debe seleccionar un signo</div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="orden" class="form-label">Orden</label>
                                        <input type="number" class="form-control" id="orden" name="orden" 
                                               min="1" max="999" value="1">
                                        <div class="form-text">1-999, menor = más arriba</div>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Impactos</label>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="impacta_stock" name="impacta_stock" value="1">
                                                    <label class="form-check-label" for="impacta_stock">Impacta en Stock</label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="impacta_contabilidad" name="impacta_contabilidad" value="1">
                                                    <label class="form-check-label" for="impacta_contabilidad">Impacta en Contabilidad</label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="impacta_ctacte" name="impacta_ctacte" value="1">
                                                    <label class="form-check-label" for="impacta_ctacte">Impacta en Cta. Cte.</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label for="comentario" class="form-label">Comentario</label>
                                        <textarea class="form-control" id="comentario" name="comentario" 
                                                  maxlength="255" rows="2"></textarea>
                                        <div class="form-text">Máximo 255 caracteres</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="estado_registro_id" class="form-label">Estado</label>
                                        <select class="form-select" id="estado_registro_id" name="estado_registro_id">
                                            <option value="">Seleccionar estado...</option>
                                        </select>
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

    <!-- Estilos personalizados para botones de exportación -->
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
        .impacto-badge {
            font-size: 0.7rem;
            padding: 2px 6px;
            margin: 1px;
        }
    </style>

    <script>
    $(document).ready(function(){
        // Variables de contexto MULTIEMPRESA
        const empresa_idx = 2;
        const pagina_idx = <?php echo $pagina_idx; ?>; // 50
        
        // Variables para mantener el estado
        let currentFilters = {
            grupo: '',
            estado: '',
            busqueda: ''
        };
        
        // Cargar grupos de comprobantes
        function cargarGrupos(selectedId = null) {
            $.get('comprobantes_tipos_ajax.php', {
                accion: 'obtener_grupos_activos',
                empresa_idx: empresa_idx
            }, function(grupos){
                var select = $('#comprobante_grupo_id');
                var filterSelect = $('#filterGrupo');
                
                // Select del formulario
                select.empty();
                select.append('<option value="">Seleccionar grupo...</option>');
                
                // Select del filtro
                filterSelect.empty();
                filterSelect.append('<option value="">Todos los grupos</option>');
                
                $.each(grupos, function(index, grupo){
                    var optionText = grupo.comprobante_grupo;
                    
                    // Para el formulario
                    var selected = (selectedId && grupo.comprobante_grupo_id == selectedId) ? 'selected' : '';
                    select.append(`<option value="${grupo.comprobante_grupo_id}" ${selected}>${optionText}</option>`);
                    
                    // Para el filtro
                    filterSelect.append(`<option value="${grupo.comprobante_grupo_id}">${optionText}</option>`);
                });
            }, 'json');
        }
        
        // Cargar comprobantes fiscales
        function cargarComprobantesFiscales(selectedId = null) {
            $.get('comprobantes_tipos_ajax.php', {
                accion: 'obtener_comprobantes_fiscales'
            }, function(comprobantes){
                var select = $('#comprobante_fiscal_id');
                
                select.empty();
                select.append('<option value="">Seleccionar comprobante fiscal...</option>');
                
                $.each(comprobantes, function(index, comprobante){
                    var selected = (selectedId && comprobante.comprobante_fiscal_id == selectedId) ? 'selected' : '';
                    var optionText = comprobante.codigo ? 
                        `${comprobante.codigo.toString().padStart(3, '0')} - ${comprobante.comprobante_fiscal}` :
                        comprobante.comprobante_fiscal;
                    
                    select.append(`<option value="${comprobante.comprobante_fiscal_id}" ${selected}>${optionText}</option>`);
                });
            }, 'json');
        }
        
        // Cargar estados para el select
        function cargarEstados(selectedId = null) {
            $.get('comprobantes_tipos_ajax.php', {
                accion: 'obtener_estados_registro'
            }, function(estados){
                var select = $('#estado_registro_id');
                var filterSelect = $('#filterEstado');
                
                // Select del formulario
                select.empty();
                select.append('<option value="">Seleccionar estado...</option>');
                
                // Select del filtro
                filterSelect.empty();
                filterSelect.append('<option value="">Todos los estados</option>');
                
                $.each(estados, function(index, estado){
                    // Para el formulario
                    var selected = (selectedId && estado.estado_registro_id == selectedId) ? 'selected' : '';
                    select.append(`<option value="${estado.estado_registro_id}" ${selected}>${estado.estado_registro}</option>`);
                    
                    // Para el filtro
                    filterSelect.append(`<option value="${estado.estado_registro_id}">${estado.estado_registro}</option>`);
                });
            }, 'json');
        }
        
        // Configuración de DataTable
        var tabla;
        var currentPage = 0;
        var currentOrder = [[0, 'desc']];
        var currentSearch = '';
        
        function inicializarDataTable() {
            // Destruir DataTable existente si hay uno
            if ($.fn.DataTable.isDataTable('#tablaComprobantesTipos')) {
                $('#tablaComprobantesTipos').DataTable().destroy();
                $('#tablaComprobantesTipos tbody').empty();
            }
            
            tabla = $('#tablaComprobantesTipos').DataTable({
                ajax: {
                    url: 'comprobantes_tipos_ajax.php',
                    type: 'GET',
                    data: function(d) {
                        return {
                            accion: 'listar',
                            empresa_idx: empresa_idx,
                            pagina_idx: pagina_idx,
                            filter_grupo: currentFilters.grupo,
                            filter_estado: currentFilters.estado,
                            filter_busqueda: currentFilters.busqueda
                        };
                    },
                    dataSrc: ''
                },
                stateSave: true,
                stateSaveParams: function(settings, data) {
                    data.page = currentPage;
                    data.order = currentOrder;
                    data.search = currentSearch;
                },
                stateLoadParams: function(settings, data) {
                    if (data.page !== undefined) currentPage = data.page;
                    if (data.order !== undefined && data.order.length > 0) currentOrder = data.order;
                    if (data.search && data.search.search !== undefined) {
                        currentSearch = data.search.search || '';
                    } else if (typeof data.search === 'string') {
                        currentSearch = data.search;
                    }
                },
                stateSaveCallback: function(settings, data) {
                    localStorage.setItem('DataTables_' + settings.sInstance, JSON.stringify(data));
                },
                stateLoadCallback: function(settings) {
                    var savedData = localStorage.getItem('DataTables_' + settings.sInstance);
                    if (savedData) {
                        var data = JSON.parse(savedData);
                        if (data.search && data.search.search === '-1') {
                            data.search.search = '';
                        }
                        return data;
                    }
                    return null;
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
                            columns: [0, 1, 2, 3, 4, 5, 6, 8, 9],
                            orthogonal: 'export'
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
                            columns: [0, 1, 2, 3, 4, 5, 6, 8, 9],
                            orthogonal: 'export'
                        }
                    },
                    {
                        extend: 'csvHtml5',
                        text: '<i class="fas fa-file-csv"></i> CSV',
                        className: 'btn btn-primary btn-sm',
                        title: 'Tipos_de_Comprobantes',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 8, 9]
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Imprimir',
                        className: 'btn btn-secondary btn-sm',
                        title: 'Tipos de Comprobantes',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 8, 9],
                            stripHtml: false
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
                        className: 'text-center',
                        render: function(data) {
                            return `<span class="badge bg-primary">${data}</span>`;
                        }
                    },
                    { 
                        data: 'comprobante_tipo',
                        render: function(data) {
                            return `<div class="fw-medium">${data}</div>`;
                        }
                    },
                    { 
                        data: 'grupo_info',
                        render: function(data) {
                            if (!data) return '<span class="text-muted fst-italic">Sin grupo</span>';
                            return `<div class="fw-medium">${data.comprobante_grupo}</div>`;
                        }
                    },
                    { 
                        data: 'comprobante_fiscal_info',
                        render: function(data) {
                            if (!data) return '<span class="text-muted fst-italic">Sin comprobante</span>';
                            var codigo = data.codigo ? ` (${data.codigo.toString().padStart(3, '0')})` : '';
                            return `<div class="fw-medium">${data.comprobante_fiscal}${codigo}</div>`;
                        }
                    },
                    { 
                        data: 'signo',
                        className: 'text-center',
                        render: function(data) {
                            var signoMap = {
                                '+': '<span class="badge bg-success">+</span>',
                                '-': '<span class="badge bg-danger">-</span>',
                                '+/-': '<span class="badge bg-warning">+/-</span>'
                            };
                            return signoMap[data] || '<span class="badge bg-secondary">?</span>';
                        }
                    },
                    { 
                        data: 'letra',
                        className: 'text-center',
                        render: function(data) {
                            return data ? `<span class="badge bg-info">${data}</span>` : '';
                        }
                    },
                    {
                        data: null,
                        className: 'text-center',
                        render: function(data) {
                            var impactos = '';
                            if (data.impacta_stock == 1) {
                                impactos += '<span class="badge bg-success impacto-badge">STOCK</span> ';
                            }
                            if (data.impacta_contabilidad == 1) {
                                impactos += '<span class="badge bg-primary impacto-badge">CONTAB</span> ';
                            }
                            if (data.impacta_ctacte == 1) {
                                impactos += '<span class="badge bg-warning impacto-badge">CTA CTE</span>';
                            }
                            return impactos || '<span class="text-muted">Sin impactos</span>';
                        }
                    },
                    { 
                        data: 'orden',
                        className: 'text-center',
                        render: function(data) {
                            return `<span class="badge bg-secondary">${data || 1}</span>`;
                        }
                    },
                    { 
                        data: 'estado_info',
                        className: 'text-center',
                        render: function(data) {
                            if (!data || !data.estado_registro) {
                                return '<span class="badge bg-secondary">Sin estado</span>';
                            }
                            
                            var estado = data.estado_registro;
                            var colorClass = data.bg_clase || 'bg-secondary';
                            var textClass = data.text_clase || 'text-white';
                            
                            return `<span class="badge ${colorClass} ${textClass}">${estado}</span>`;
                        }
                    },
                    {
                        data: 'botones',
                        orderable: false,
                        searchable: false,
                        className: "text-center",
                        width: '200px',
                        render: function(data) {
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
                                           data-id="${data.comprobante_tipo_id}" 
                                           data-accion="${accionJs}"
                                           data-confirmable="${esConfirmable}"
                                           data-tipo="${data.comprobante_tipo}">
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
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json',
                    buttons: {
                        excel: 'Excel',
                        pdf: 'PDF',
                        csv: 'CSV',
                        print: 'Imprimir'
                    }
                },
                order: currentOrder,
                responsive: true,
                createdRow: function(row, data) {
                    if (data.estado_info && data.estado_info.codigo_estandar === 'INACTIVO') {
                        $(row).addClass('table-secondary');
                    } else if (data.estado_info && data.estado_info.codigo_estandar === 'BLOQUEADO') {
                        $(row).addClass('table-warning');
                    }
                },
                initComplete: function() {
                    // Mover botones de exportación
                    var buttons = new $.fn.dataTable.Buttons(tabla, {
                        buttons: ['excelHtml5', 'pdfHtml5', 'csvHtml5', 'print']
                    }).container().appendTo($('#tablaComprobantesTipos_wrapper .col-md-6:eq(1)'));
                    
                    // Guardar estado al cambiar página
                    $(tabla.table().container()).on('page.dt', function(e) {
                        currentPage = tabla.page();
                    });
                    
                    // Guardar estado al ordenar
                    $(tabla.table().container()).on('order.dt', function(e, settings, details) {
                        currentOrder = tabla.order();
                    });
                    
                    // Guardar estado al buscar
                    $(tabla.table().container()).on('search.dt', function(e, settings) {
                        currentSearch = tabla.search();
                    });
                    
                    // Limpiar bug del "-1"
                    setTimeout(function() {
                        var searchInput = $('.dataTables_filter input');
                        if (searchInput.val() === '-1' || searchInput.val() === '') {
                            searchInput.val('');
                            currentSearch = '';
                        }
                    }, 100);
                }
            });
            
            inicializarEventos();
        }

        // Función para inicializar eventos
        function inicializarEventos() {
            // Botón recargar
            $('#btnRecargar').off('click').on('click', function(){
                var btn = $(this);
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                
                var savedState = {
                    page: tabla.page(),
                    order: tabla.order(),
                    search: tabla.search()
                };
                
                tabla.ajax.reload(function(json){
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
            }, function(botonAgregar){
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
        $(document).on('click', '#btnNuevo', function(){
            resetModal();
            $('#modalLabel').text('Nuevo Tipo de Comprobante');
            cargarGrupos();
            cargarComprobantesFiscales();
            cargarEstados();
            
            var modal = new bootstrap.Modal(document.getElementById('modalComprobanteTipo'));
            modal.show();
            $('#codigo').focus();
        });

        // Manejadores de filtros
        $('#filterGrupo').change(function() {
            currentFilters.grupo = $(this).val();
            tabla.ajax.reload();
        });

        $('#filterEstado').change(function() {
            currentFilters.estado = $(this).val();
            tabla.ajax.reload();
        });

        $('#filterBusqueda').on('keyup', function() {
            currentFilters.busqueda = $(this).val();
            clearTimeout($(this).data('timeout'));
            
            $(this).data('timeout', setTimeout(function() {
                tabla.ajax.reload();
            }, 500));
        });

        // Manejador para botones de acción dinámicos
        $(document).on('click', '.btn-accion', function(){
            var comprobanteTipoId = $(this).data('id');
            var accionJs = $(this).data('accion');
            var confirmable = $(this).data('confirmable');
            var tipo = $(this).data('tipo');
            
            if (accionJs === 'editar') {
                cargarComprobanteTipoParaEditar(comprobanteTipoId);
            } else if (confirmable == 1) {
                Swal.fire({
                    title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                    html: `¿Está seguro de <strong>${accionJs}</strong> el tipo de comprobante <strong>"${tipo}"</strong>?`,
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
                        ejecutarAccion(comprobanteTipoId, accionJs, tipo);
                    }
                });
            } else {
                ejecutarAccion(comprobanteTipoId, accionJs, tipo);
            }
        });

        // Función para ejecutar cualquier acción del backend
        function ejecutarAccion(comprobanteTipoId, accionJs, tipo) {
            var savedState = {
                page: tabla.page(),
                order: tabla.order(),
                search: tabla.search()
            };
            
            $.post('comprobantes_tipos_ajax.php', {
                accion: 'ejecutar_accion',
                comprobante_tipo_id: comprobanteTipoId,
                accion_js: accionJs,
                empresa_idx: empresa_idx,
                pagina_idx: pagina_idx
            }, function(res){
                if (res.success) {
                    tabla.ajax.reload(function(json){
                        if (savedState.page !== undefined) {
                            tabla.page(savedState.page).draw('page');
                        }
                        if (savedState.search && savedState.search !== '') {
                            tabla.search(savedState.search).draw();
                        }
                        
                        Swal.fire({
                            icon: "success",
                            title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                            text: res.message || `Tipo de comprobante "${tipo}" actualizado correctamente`,
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
                        text: res.error || `Error al ${accionJs} el tipo de comprobante`,
                        confirmButtonText: "Entendido"
                    });
                }
            }, 'json');
        }

        // Función para cargar tipo de comprobante en modal de edición
        function cargarComprobanteTipoParaEditar(comprobanteTipoId) {
            $.get('comprobantes_tipos_ajax.php', {
                accion: 'obtener', 
                comprobante_tipo_id: comprobanteTipoId,
                empresa_idx: empresa_idx
            }, function(res){
                if(res && res.comprobante_tipo_id){
                    resetModal();
                    
                    $('#comprobante_tipo_id').val(res.comprobante_tipo_id);
                    $('#codigo').val(res.codigo || '');
                    $('#comprobante_tipo').val(res.comprobante_tipo || '');
                    $('#letra').val(res.letra || '');
                    $('#signo').val(res.signo || '+');
                    $('#orden').val(res.orden || 1);
                    $('#comentario').val(res.comentario || '');
                    
                    // Checkboxes de impactos
                    $('#impacta_stock').prop('checked', res.impacta_stock == 1);
                    $('#impacta_contabilidad').prop('checked', res.impacta_contabilidad == 1);
                    $('#impacta_ctacte').prop('checked', res.impacta_ctacte == 1);
                    
                    cargarGrupos(res.comprobante_grupo_id);
                    cargarComprobantesFiscales(res.comprobante_fiscal_id);
                    cargarEstados(res.tabla_estado_registro_id);
                    
                    $('#modalLabel').text('Editar Tipo de Comprobante');
                    
                    var modal = new bootstrap.Modal(document.getElementById('modalComprobanteTipo'));
                    modal.show();
                    
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Error al obtener datos del tipo de comprobante",
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
            
            // Resetear checkboxes a unchecked
            $('#impacta_stock').prop('checked', false);
            $('#impacta_contabilidad').prop('checked', false);
            $('#impacta_ctacte').prop('checked', false);
            
            // Resetear selects a valor por defecto
            $('#signo').val('+');
            $('#orden').val('1');
        }

        // Validación del formulario
        $('#btnGuardar').click(function(){
            var form = document.getElementById('formComprobanteTipo');
            
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return false;
            }
            
            var id = $('#comprobante_tipo_id').val();
            var accionBackend = id ? 'editar' : 'agregar';
            var codigo = $('#codigo').val().trim();
            var comprobanteTipo = $('#comprobante_tipo').val().trim();
            var comprobanteGrupoId = $('#comprobante_grupo_id').val();
            var comprobanteFiscalId = $('#comprobante_fiscal_id').val();
            var letra = $('#letra').val().trim();
            var signo = $('#signo').val();
            var orden = $('#orden').val() || 1;
            
            if (!codigo) {
                $('#codigo').addClass('is-invalid');
                return false;
            }
            
            if (!comprobanteTipo) {
                $('#comprobante_tipo').addClass('is-invalid');
                return false;
            }
            
            if (!comprobanteGrupoId) {
                $('#comprobante_grupo_id').addClass('is-invalid');
                return false;
            }
            
            if (!comprobanteFiscalId) {
                $('#comprobante_fiscal_id').addClass('is-invalid');
                return false;
            }
            
            if (codigo.length > 10) {
                $('#codigo').addClass('is-invalid');
                return false;
            }
            
            if (comprobanteTipo.length > 100) {
                $('#comprobante_tipo').addClass('is-invalid');
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
                    comprobante_grupo_id: comprobanteGrupoId,
                    comprobante_fiscal_id: comprobanteFiscalId,
                    letra: letra,
                    signo: signo,
                    orden: orden,
                    impacta_stock: $('#impacta_stock').is(':checked') ? 1 : 0,
                    impacta_contabilidad: $('#impacta_contabilidad').is(':checked') ? 1 : 0,
                    impacta_ctacte: $('#impacta_ctacte').is(':checked') ? 1 : 0,
                    comentario: $('#comentario').val().trim(),
                    estado_registro_id: $('#estado_registro_id').val() || 1,
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                },
                success: function(res){
                    if(res.resultado){
                        tabla.ajax.reload(function(json){
                            if (savedState.page !== undefined) {
                                tabla.page(savedState.page).draw('page');
                            }
                            if (savedState.search && savedState.search !== '') {
                                tabla.search(savedState.search).draw();
                            }
                            
                            if (id) {
                                tabla.rows().every(function(rowIdx, tableLoop, rowLoop) {
                                    var data = this.data();
                                    if (data.comprobante_tipo_id == id) {
                                        $(this.node()).addClass('table-success');
                                        setTimeout(function() {
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
                error: function() {
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

        // Manejadores para los botones del dropdown de exportación
        $('#btnExportarExcel').click(function(e) {
            e.preventDefault();
            $('.buttons-excel').click();
        });
        
        $('#btnExportarPDF').click(function(e) {
            e.preventDefault();
            $('.buttons-pdf').click();
        });
        
        $('#btnExportarCSV').click(function(e) {
            e.preventDefault();
            $('.buttons-csv').click();
        });
        
        $('#btnExportarPrint').click(function(e) {
            e.preventDefault();
            $('.buttons-print').click();
        });

        // Inicializar
        inicializarDataTable();
        cargarGrupos();
        cargarEstados();
        cargarBotonAgregar();
        
        // Agregar tooltips
        $('[title]').tooltip({
            trigger: 'hover',
            placement: 'top'
        });
        
        // Limpiar localStorage si tiene el bug del "-1"
        $(window).on('load', function() {
            setTimeout(function() {
                var savedData = localStorage.getItem('DataTables_tablaComprobantesTipos');
                if (savedData) {
                    var data = JSON.parse(savedData);
                    if (data.search && (data.search.search === '-1' || data.search.search === '')) {
                        data.search.search = '';
                        localStorage.setItem('DataTables_tablaComprobantesTipos', JSON.stringify(data));
                    }
                }
            }, 500);
        });
    });
    </script>
    
    <!-- Librerías necesarias para DataTables Buttons -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>