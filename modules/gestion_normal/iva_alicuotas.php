<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "IVA Alícuotas";
$currentPage = 'iva_alicuotas';
$modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : 5;
$pagina_id = isset($_GET['pagina_id']) ? intval($_GET['pagina_id']) : 65; // ID de página para IVA alícuotas
$empresa_id = intval($_GET['empresa_id'] ?? $_SESSION['empresa_id'] ?? 0);
$pagina_idx = $pagina_id;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<!-- Estilos y librerías requeridas (Select2 y DataTables Buttons) -->
<link rel="stylesheet" type="text/css" href="<?= asset_local('css/datatables-buttons-2.4.1/buttons.dataTables.min.css') ?>">
<link rel="stylesheet" href="<?= asset_local('css/select2-4.1.0-rc.0/select2.min.css') ?>" />
<link rel="stylesheet" href="<?= asset_local('css/select2-bootstrap-5-theme-1.3.0/select2-bootstrap-5-theme.min.css') ?>" />

<script src="<?= asset_local('js/datatables-buttons-2.4.1/buttons.print.min.js') ?>"></script>
<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="<?= asset_local('js/select2-4.1.0-rc.0/select2.min.js') ?>"></script>
<script src="<?= asset_local('js/select2-4.1.0-rc.0/i18n/es.js') ?>"></script>


<main class="app-main">
    <?php ui_encabezado('Alícuotas de IVA','fas fa-percentage', '', ['Tablas']); ?>

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
                                        <!-- DataTable -->
                                        <table id="tablaIvaAlicuotas" class="table table-striped table-bordered"
                                            style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="70">ID</th>
                                                    <th width="90">Código</th>
                                                    <th width="180">Alicuota</th>
                                                    <th width="90">Porcentaje</th>
                                                    <th width="110">Tipo</th>
                                                    <th width="180">Cta. Compras (Crédito)</th>
                                                    <th width="180">Cta. Ventas (Débito)</th>
                                                    <th width="100">Estado</th>
                                                    <th width="160" class="text-center">Acciones</th>
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

            <!-- Modal para crear/editar IVA alícuota -->
            <div class="modal fade" id="modalIvaAlicuota" tabindex="-1" aria-labelledby="modalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">IVA Alícuota</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formIvaAlicuota" class="needs-validation" novalidate>
                                <input type="hidden" id="iva_alicuota_id" name="iva_alicuota_id" />
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="codigo" class="form-label">Código *</label>
                                        <input type="text" class="form-control" id="codigo" name="codigo" 
                                            maxlength="10" required>
                                        <div class="invalid-feedback">El código es obligatorio (máx. 10 caracteres)</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="porcentaje" class="form-label">Porcentaje (%) *</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="porcentaje" 
                                                name="porcentaje" step="0.01" min="0" max="100" 
                                                placeholder="21.00" required>
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="iva_alicuota" class="form-label">Descripción * <?= ui_ayuda('Máximo 100 caracteres') ?></label>
                                        <input type="text" class="form-control" id="iva_alicuota" 
                                            name="iva_alicuota" maxlength="100" required>
                                        <div class="invalid-feedback">La descripción es obligatoria</div>
                                        
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Campos Cuentas Contables (Compras y Ventas) -->
                                    <div class="col-md-6 mb-3">
                                        <label for="cont_cuenta_compra_id" class="form-label">Cta. Contable Compras (Crédito Fiscal) <?= ui_ayuda('Cuenta de Crédito Fiscal IVA para compras') ?></label>
                                        <select class="form-select select2-cuenta" id="cont_cuenta_compra_id" name="cont_cuenta_compra_id">
                                            <option value="">Seleccione cuenta contable...</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="cont_cuenta_venta_id" class="form-label">Cta. Contable Ventas (Débito Fiscal) <?= ui_ayuda('Cuenta de Débito Fiscal IVA para ventas') ?></label>
                                        <select class="form-select select2-cuenta" id="cont_cuenta_venta_id" name="cont_cuenta_venta_id">
                                            <option value="">Seleccione cuenta contable...</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Tipo de IVA *</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipo_iva" 
                                                id="gravado" value="gravado" checked>
                                            <label class="form-check-label" for="gravado">
                                                <i class="fas fa-check-circle text-success me-1"></i>Gravado
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipo_iva" 
                                                id="exento" value="exento">
                                            <label class="form-check-label" for="exento">
                                                <i class="fas fa-ban text-warning me-1"></i>Exento
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipo_iva" 
                                                id="no_gravado" value="no_gravado">
                                            <label class="form-check-label" for="no_gravado">
                                                <i class="fas fa-times-circle text-danger me-1"></i>No Gravado
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">Campos correspondientes</label>
                                        <div class="border rounded p-3 bg-light">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" 
                                                            id="es_gravado" name="es_gravado" checked disabled>
                                                        <label class="form-check-label" for="es_gravado">Gravado</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" 
                                                            id="es_exento" name="es_exento" disabled>
                                                        <label class="form-check-label" for="es_exento">Exento</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" 
                                                            id="es_no_gravado" name="es_no_gravado" disabled>
                                                        <label class="form-check-label" for="es_no_gravado">No Gravado</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <small class="text-muted d-block mt-2">
                                                Estos campos se actualizan automáticamente según el tipo seleccionado
                                            </small>
                                        </div>
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

        .badge-tipo {
            font-size: 0.85em;
            padding: 4px 8px;
        }

        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
    </style>

    <script>
        $(document).ready(function () {
            // Variables de contexto MULTIEMPRESA
            const empresa_idx = <?php echo isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : $empresa_id; ?>;
            const pagina_idx = <?php echo isset($_GET['pagina_id']) ? intval($_GET['pagina_id']) : $pagina_idx; ?>;

            // Variables para mantener el estado del DataTable
            var tabla;
            var currentPage = 0;
            var currentOrder = [[1, 'asc']];
            var currentSearch = '';
            
            function cargarCuentasContables() {
                $.ajax({
                    url: 'iva_alicuotas_ajax.php',
                    type: 'GET',
                    data: {
                        accion: 'listar_cuentas_contables',
                        empresa_idx: empresa_idx
                    },
                    dataType: 'json',
                    success: function(response) {
                        var selectCompra = $('#cont_cuenta_compra_id');
                        var selectVenta = $('#cont_cuenta_venta_id');
                        selectCompra.empty().append('<option value="">Seleccione cuenta contable...</option>');
                        selectVenta.empty().append('<option value="">Seleccione cuenta contable...</option>');
                        
                        if (response && response.length > 0) {
                            $.each(response, function(index, cuenta) {
                                var optionText = cuenta.codigo + ' - ' + cuenta.nombre;
                                selectCompra.append('<option value="' + cuenta.cont_cuenta_id + '">' + optionText + '</option>');
                                selectVenta.append('<option value="' + cuenta.cont_cuenta_id + '">' + optionText + '</option>');
                            });
                        } else {
                            selectCompra.append('<option value="" disabled>No hay cuentas contables disponibles</option>');
                            selectVenta.append('<option value="" disabled>No hay cuentas contables disponibles</option>');
                        }
                        
                        inicializarSelect2();
                    },
                    error: function(xhr, status, error) {
                        console.error('Error al cargar cuentas contables:', error);
                    }
                });
            }
            // Manejar cambios en los radio buttons para actualizar checkboxes
            $('input[name="tipo_iva"]').change(function() {
                var tipo = $(this).val();
                
                // Resetear todos los checkboxes
                $('#es_gravado').prop('checked', false);
                $('#es_exento').prop('checked', false);
                $('#es_no_gravado').prop('checked', false);
                
                // Activar el checkbox correspondiente
                if (tipo === 'gravado') {
                    $('#es_gravado').prop('checked', true);
                } else if (tipo === 'exento') {
                    $('#es_exento').prop('checked', true);
                } else if (tipo === 'no_gravado') {
                    $('#es_no_gravado').prop('checked', true);
                }
            });

            // Inicializar Select2 en el modal
            function inicializarSelect2() {
                if (typeof $.fn.select2 === 'function') {
                    $('#cont_cuenta_compra_id, #cont_cuenta_venta_id').each(function() {
                        if ($(this).data('select2')) {
                            $(this).select2('destroy');
                        }
                    });

                    $('#cont_cuenta_compra_id, #cont_cuenta_venta_id').select2({
                        theme: 'bootstrap-5',
                        placeholder: 'Buscar cuenta contable...',
                        allowClear: true,
                        width: '100%',
                        dropdownParent: $('#modalIvaAlicuota'),
                        language: {
                            noResults: function () {
                                return "No se encontraron cuentas contables";
                            }
                        }
                    });
                }
            }

            // Función para inicializar DataTable
            function inicializarDataTable() {
                // Destruir DataTable existente si hay uno
                if ($.fn.DataTable.isDataTable('#tablaIvaAlicuotas')) {
                    $('#tablaIvaAlicuotas').DataTable().destroy();
                    $('#tablaIvaAlicuotas tbody').empty();
                }

                // Configuración de DataTable
                tabla = $('#tablaIvaAlicuotas').DataTable({
                    ajax: {
                        url: 'iva_alicuotas_ajax.php',
                        type: 'GET',
                        data: {
                            accion: 'listar',
                            empresa_idx: empresa_idx,
                            pagina_idx: pagina_idx
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
                            if (searchValue === '-1' || searchValue === '' || searchValue === '-1') {
                                currentSearch = '';
                            } else {
                                currentSearch = searchValue;
                            }
                        } else {
                            currentSearch = '';
                        }
                        
                        data.search = { search: currentSearch };
                    },
                    stateLoadCallback: function (settings) {
                        var savedData = localStorage.getItem('DataTables_' + settings.sInstance);
                        if (savedData) {
                            var data = JSON.parse(savedData);
                            
                            if (data.search && (data.search.search === '-1' || data.search.search === '')) {
                                data.search.search = '';
                            }
                            
                            if (data.columns) {
                                $.each(data.columns, function (i, col) {
                                    if (col.search && col.search.search === '-1') {
                                        col.search.search = '';
                                    }
                                });
                            }
                            
                            return data;
                        }
                        return null;
                    },
                    dom: '<"row align-items-center mb-2"<"col-12 col-md-6"><"col-12 col-md-6 text-end"f>>' +
                '<"row"<"col-sm-12"tr>>' +
                '<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>' +
                         '<"clear">',
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                    buttons: [
                        {
                            extend: 'excelHtml5',
                            text: '<i class="fas fa-file-excel"></i> Excel',
                            className: 'btn btn-success btn-sm',
                            title: 'IVA Alícuotas',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6, 7],
                                orthogonal: 'export'
                            }
                        },
                        {
                            extend: 'pdfHtml5',
                            text: '<i class="fas fa-file-pdf"></i> PDF',
                            className: 'btn btn-danger btn-sm',
                            title: 'IVA Alícuotas',
                            orientation: 'landscape',
                            pageSize: 'A4',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6, 7],
                                orthogonal: 'export'
                            }
                        },
                        {
                            extend: 'csvHtml5',
                            text: '<i class="fas fa-file-csv"></i> CSV',
                            className: 'btn btn-primary btn-sm',
                            title: 'IVA_Alicuotas',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6, 7]
                            }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> Imprimir',
                            className: 'btn btn-secondary btn-sm',
                            title: 'IVA Alícuotas',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6, 7],
                                stripHtml: false
                            }
                        }
                    ],
                    columns: [
                        {
                            data: 'iva_alicuota_id',
                            className: 'ui-detalle'
                        },
                        {
                            data: 'codigo',
                            className: 'text-center fw-medium',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data;
                                }
                                return `<span class="badge bg-primary">${data}</span>`;
                            }
                        },
                        {
                            data: 'iva_alicuota',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data;
                                }
                                return `<div class="fw-medium">${data}</div>`;
                            }
                        },
                        {
                            data: 'porcentaje',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data + '%';
                                }
                                return `<span class="fw-bold ${data > 0 ? 'text-success' : 'text-secondary'}">${data}%</span>`;
                            }
                        },
                        {
                            data: 'tipo_iva',
                            className: 'text-center',
                            render: function (data, type, row) {
                                var badgeClass = 'secondary';
                                var icon = '';
                                
                                if (data === 'Gravado') {
                                    badgeClass = 'success';
                                    icon = '<i class="fas fa-check-circle me-1"></i>';
                                } else if (data === 'Exento') {
                                    badgeClass = 'warning';
                                    icon = '<i class="fas fa-ban me-1"></i>';
                                } else if (data === 'No Gravado') {
                                    badgeClass = 'danger';
                                    icon = '<i class="fas fa-times-circle me-1"></i>';
                                }
                                
                                if (type === 'export') {
                                    return data;
                                }
                                
                                return `<span class="badge bg-${badgeClass} badge-tipo">${icon}${data}</span>`;
                            }
                        },
                        {
                            data: 'cuenta_compra',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data;
                                }
                                if (!data || data === 'Sin asignar') {
                                    return '<span class="text-muted small">Sin asignar</span>';
                                }
                                return `<span class="badge bg-light text-dark border"><i class="fas fa-shopping-cart text-info me-1"></i>${data}</span>`;
                            }
                        },
                        {
                            data: 'cuenta_venta',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data;
                                }
                                if (!data || data === 'Sin asignar') {
                                    return '<span class="text-muted small">Sin asignar</span>';
                                }
                                return `<span class="badge bg-light text-dark border"><i class="fas fa-cash-register text-success me-1"></i>${data}</span>`;
                            }
                        },
                        {
                            data: 'estado_info',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (!data || !data.estado_registro) {
                                    if (type === 'export') {
                                        return 'Sin estado';
                                    }
                                    return '<span class="fw-medium">Sin estado</span>';
                                }

                                var estado = data.estado_registro;
                                var badgeClass = data.bg_clase ? data.bg_clase.replace('bg-', '') : 'secondary';

                                if (type === 'export') {
                                    return estado;
                                }

                                return `<span class="badge bg-${badgeClass}">${estado}</span>`;
                            }
                        },
                        {
                            data: 'botones',
                            orderable: false,
                            searchable: false,
                            className: "text-center",
                            width: '200px',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return '';
                                }

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
                                       data-id="${row.iva_alicuota_id}" 
                                       data-accion="${accionJs}"
                                       data-confirmable="${esConfirmable}"
                                       data-alicuota="${row.iva_alicuota}">
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
                        url: '<?= asset_local("js/datatables-es-ES.json") ?>',
                        buttons: {
                            excel: 'Excel',
                            pdf: 'PDF',
                            csv: 'CSV',
                            print: 'Imprimir'
                        }
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
                        // Mover los botones de exportación
                        var buttons = new $.fn.dataTable.Buttons(tabla, {
                            buttons: ['excelHtml5', 'pdfHtml5', 'csvHtml5', 'print']
                        }).container().appendTo($('#tablaIvaAlicuotas_wrapper .col-md-6:eq(1)'));

                        // Guardar estado actual
                        $(tabla.table().container()).on('page.dt', function (e) {
                            currentPage = tabla.page();
                        });

                        $(tabla.table().container()).on('order.dt', function (e, settings, details) {
                            currentOrder = tabla.order();
                        });

                        $(tabla.table().container()).on('search.dt', function (e, settings) {
                            currentSearch = tabla.search();
                        });

                        // Limpiar el campo de búsqueda si tiene "-1"
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
                    }
                });

                // Inicializar eventos después de crear la tabla
                inicializarEventos();
            }

            // Función para inicializar eventos
            function inicializarEventos() {
                // Botón recargar
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
                $.get('iva_alicuotas_ajax.php', {
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
                            '<i class="fas fa-plus me-1"></i>Agregar Alícuota</button>'
                        );
                    }
                }, 'json');
            }

            // Manejador para botón "Agregar"
            $(document).on('click', '#btnNuevo', function () {
                resetModal();
                $('#modalLabel').text('Nueva Alícuota IVA');
                
                // Establecer valores por defecto
                $('input[name="tipo_iva"][value="gravado"]').prop('checked', true).trigger('change');
                
                var modal = new bootstrap.Modal(document.getElementById('modalIvaAlicuota'));
                modal.show();
                $('#codigo').focus();
            });

            // Manejador para botones de acción dinámicos
            $(document).on('click', '.btn-accion', function () {
                var alicuotaId = $(this).data('id');
                var accionJs = $(this).data('accion');
                var confirmable = $(this).data('confirmable');
                var alicuota = $(this).data('alicuota');

                if (accionJs === 'editar') {
                    cargarAlicuotaParaEditar(alicuotaId);
                } else if (confirmable == 1) {
                    Swal.fire({
                        title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                        html: `¿Está seguro de <strong>${accionJs}</strong> la alícuota <strong>"${alicuota}"</strong>?`,
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
                            ejecutarAccion(alicuotaId, accionJs, alicuota);
                        }
                    });
                } else {
                    ejecutarAccion(alicuotaId, accionJs, alicuota);
                }
            });

            // Función para ejecutar cualquier acción del backend
            function ejecutarAccion(alicuotaId, accionJs, alicuota) {
                var savedState = {
                    page: tabla.page(),
                    order: tabla.order(),
                    search: tabla.search()
                };

                $.post('iva_alicuotas_ajax.php', {
                    accion: 'ejecutar_accion',
                    iva_alicuota_id: alicuotaId,
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

                            // Buscar el registro actualizado y resaltarlo
                            tabla.rows().every(function (rowIdx, tableLoop, rowLoop) {
                                var data = this.data();
                                if (data.iva_alicuota_id == alicuotaId) {
                                    $(this.node()).addClass('table-success');
                                    setTimeout(function () {
                                        $(this.node()).removeClass('table-success');
                                    }.bind(this), 2000);
                                }
                            });

                            Swal.fire({
                                icon: "success",
                                title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                                text: res.message || `Alícuota "${alicuota}" actualizada correctamente`,
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
                            text: res.error || `Error al ${accionJs} la alícuota`,
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            // Función para cargar alícuota en modal de edición
             function cargarAlicuotaParaEditar(alicuotaId) {
                $.get('iva_alicuotas_ajax.php', {
                    accion: 'obtener',
                    iva_alicuota_id: alicuotaId,
                    empresa_idx: empresa_idx
                }, function (res) {
                    if (res && res.iva_alicuota_id) {
                        resetModal();
                        
                        $('#iva_alicuota_id').val(res.iva_alicuota_id);
                        $('#codigo').val(res.codigo);
                        $('#iva_alicuota').val(res.iva_alicuota);
                        $('#porcentaje').val(res.porcentaje);
                        
                        // Cargar las cuentas contables seleccionadas
                        if (res.cont_cuenta_compra_id && parseInt(res.cont_cuenta_compra_id) > 0) {
                            $('#cont_cuenta_compra_id').val(res.cont_cuenta_compra_id).trigger('change');
                        } else {
                            $('#cont_cuenta_compra_id').val('').trigger('change');
                        }

                        if (res.cont_cuenta_venta_id && parseInt(res.cont_cuenta_venta_id) > 0) {
                            $('#cont_cuenta_venta_id').val(res.cont_cuenta_venta_id).trigger('change');
                        } else {
                            $('#cont_cuenta_venta_id').val('').trigger('change');
                        }
                        
                        // Determinar el tipo de IVA basado en los booleanos
                        var tipoIva = 'gravado';
                        if (res.es_exento == 1) {
                            tipoIva = 'exento';
                        } else if (res.es_no_gravado == 1) {
                            tipoIva = 'no_gravado';
                        }
                        
                        $('input[name="tipo_iva"][value="' + tipoIva + '"]').prop('checked', true).trigger('change');
                        
                        $('#modalLabel').text('Editar Alícuota IVA');

                        var modal = new bootstrap.Modal(document.getElementById('modalIvaAlicuota'));
                        modal.show();

                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "Error al obtener datos de la alícuota",
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            // Función para resetear el modal
            function resetModal() {
               $('#formIvaAlicuota')[0].reset();
                $('#iva_alicuota_id').val('');
                $('#formIvaAlicuota').removeClass('was-validated');
                
                // Limpiar select2
                $('#cont_cuenta_compra_id').val('').trigger('change');
                $('#cont_cuenta_venta_id').val('').trigger('change');
                
                // Restablecer valores por defecto
                $('#es_gravado').prop('checked', true);
                $('#es_exento').prop('checked', false);
                $('#es_no_gravado').prop('checked', false);
            }

            // Validación del formulario
           $('#btnGuardar').click(function () {
                var form = document.getElementById('formIvaAlicuota');

                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var id = $('#iva_alicuota_id').val();
                var accionBackend = id ? 'editar' : 'agregar';
                var codigo = $('#codigo').val().trim();
                var ivaAlicuota = $('#iva_alicuota').val().trim();
                var porcentaje = parseFloat($('#porcentaje').val());
                var contCuentaCompraId = $('#cont_cuenta_compra_id').val() || 0;
                var contCuentaVentaId = $('#cont_cuenta_venta_id').val() || 0;
                var tipoIva = $('input[name="tipo_iva"]:checked').val();

                // Validaciones adicionales
                if (!codigo || codigo.length > 10) {
                    $('#codigo').addClass('is-invalid');
                    Swal.fire({
                        icon: "warning",
                        title: "Validación",
                        text: "El código es obligatorio y no puede exceder los 10 caracteres",
                        confirmButtonText: "Entendido"
                    });
                    return false;
                } else {
                    $('#codigo').removeClass('is-invalid');
                }

                if (!ivaAlicuota || ivaAlicuota.length > 100) {
                    $('#iva_alicuota').addClass('is-invalid');
                    Swal.fire({
                        icon: "warning",
                        title: "Validación",
                        text: "La descripción es obligatoria y no puede exceder los 100 caracteres",
                        confirmButtonText: "Entendido"
                    });
                    return false;
                } else {
                    $('#iva_alicuota').removeClass('is-invalid');
                }

                if (isNaN(porcentaje) || porcentaje < 0 || porcentaje > 100) {
                    $('#porcentaje').addClass('is-invalid');
                    Swal.fire({
                        icon: "warning",
                        title: "Validación",
                        text: "El porcentaje debe ser un número entre 0 y 100",
                        confirmButtonText: "Entendido"
                    });
                    return false;
                } else {
                    $('#porcentaje').removeClass('is-invalid');
                }

                // Determinar valores booleanos
                var es_gravado = (tipoIva === 'gravado') ? 1 : 0;
                var es_exento = (tipoIva === 'exento') ? 1 : 0;
                var es_no_gravado = (tipoIva === 'no_gravado') ? 1 : 0;

                var btnGuardar = $(this);
                var originalText = btnGuardar.html();
                btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

                var savedState = {
                    page: tabla.page(),
                    order: tabla.order(),
                    search: tabla.search()
                };

                $.ajax({
                    url: 'iva_alicuotas_ajax.php',
                    type: 'POST',
                    data: {
                        accion: accionBackend,
                        iva_alicuota_id: id,
                        codigo: codigo,
                        iva_alicuota: ivaAlicuota,
                        porcentaje: porcentaje,
                        cont_cuenta_compra_id: contCuentaCompraId,
                        cont_cuenta_venta_id: contCuentaVentaId,
                        es_gravado: es_gravado,
                        es_exento: es_exento,
                        es_no_gravado: es_no_gravado,
                        empresa_idx: empresa_idx,
                        pagina_idx: pagina_idx
                    },
                    dataType: 'json',
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
                                        if (data.iva_alicuota_id == id) {
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
                                    text: "Alícuota IVA guardada correctamente",
                                    showConfirmButton: false,
                                    timer: 1500,
                                    toast: true,
                                    position: 'top-end'
                                });

                                var modalEl = document.getElementById('modalIvaAlicuota');
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
                    error: function(xhr, status, error) {
                        btnGuardar.prop('disabled', false).html(originalText);
                        console.error('Error:', error);
                        console.error('Response:', xhr.responseText);
                        Swal.fire({
                            icon: "error",
                            title: "Error de conexión",
                            text: "Error al comunicarse con el servidor. Por favor, intente nuevamente.",
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
            inicializarDataTable();
            cargarBotonAgregar();
            cargarCuentasContables(); 
            // Agregar tooltips
            $('[title]').tooltip({
                trigger: 'hover',
                placement: 'top'
            });

            // Limpiar localStorage si tiene el bug del "-1"
            $(window).on('load', function () {
                setTimeout(function () {
                    var savedData = localStorage.getItem('DataTables_tablaIvaAlicuotas');
                    if (savedData) {
                        var data = JSON.parse(savedData);
                        if (data.search) {
                            if (data.search.search === '-1' || data.search.search === '') {
                                data.search.search = '';
                                localStorage.setItem('DataTables_tablaIvaAlicuotas', JSON.stringify(data));
                            }
                        }
                    }
                }, 500);
            });
        });
    </script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>

</html>