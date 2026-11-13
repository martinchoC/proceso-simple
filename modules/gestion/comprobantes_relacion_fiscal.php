<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Relación Fiscal - Comprobantes";
$currentPage = 'comprobantes_relacion_fiscal';
$modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : 2;
$pagina_id = isset($_GET['pagina_id']) ? intval($_GET['pagina_id']) : 110;
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
    <?php ui_encabezado('Relación fiscal de comprobantes','fas fa-balance-scale', 'Matriz de Emisión Fiscal: Condición Emisor vs Receptor', ['Tablas']); ?>

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
                                        <table id="tablaRelacionFiscal" class="table table-striped table-bordered"
                                            style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="70" class="text-center">ID</th>
                                                    <th width="220">Condición Emisor</th>
                                                    <th width="220">Condición Receptor</th>
                                                    <th width="240">Comprobante Fiscal</th>
                                                    <th width="120" class="text-center">Discrimina IVA</th>
                                                    <th width="110" class="text-center">Estado</th>
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

            <!-- Modal para crear/editar relación fiscal -->
            <div class="modal fade" id="modalRelacionFiscal" tabindex="-1" aria-labelledby="modalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">
                                <i class="fas fa-balance-scale me-2"></i>Relación Fiscal Comprobante
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formRelacionFiscal" class="needs-validation" novalidate>
                                <input type="hidden" id="relacion_fiscal_id" name="relacion_fiscal_id" />
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="condicion_fiscal_emisor_id" class="form-label fw-bold">Condición Fiscal Emisor * <?= ui_ayuda('Condición impositiva de la empresa que emite') ?></label>
                                        <select class="form-select select2-catalog" id="condicion_fiscal_emisor_id" 
                                            name="condicion_fiscal_emisor_id" required>
                                            <option value="">Seleccione condición del emisor...</option>
                                        </select>
                                        <div class="invalid-feedback">Debe seleccionar la condición fiscal del emisor</div>
                                        
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="condicion_fiscal_receptor_id" class="form-label fw-bold">Condición Fiscal Receptor * <?= ui_ayuda('Condición impositiva del cliente / proveedor receptor') ?></label>
                                        <select class="form-select select2-catalog" id="condicion_fiscal_receptor_id" 
                                            name="condicion_fiscal_receptor_id" required>
                                            <option value="">Seleccione condición del receptor...</option>
                                        </select>
                                        <div class="invalid-feedback">Debe seleccionar la condición fiscal del receptor</div>
                                        
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label for="comprobante_fiscal_id" class="form-label fw-bold">Comprobante Fiscal Resultante * <?= ui_ayuda('Letra y tipo legal (A, B, C, E, etc.)') ?></label>
                                        <select class="form-select select2-catalog" id="comprobante_fiscal_id" 
                                            name="comprobante_fiscal_id" required>
                                            <option value="">Seleccione tipo de comprobante...</option>
                                        </select>
                                        <div class="invalid-feedback">Debe seleccionar el comprobante fiscal</div>
                                        
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-bold">Discriminación de IVA</label>
                                        <div class="border rounded p-2 bg-light d-flex align-items-center mt-1">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" id="discrimina_iva" name="discrimina_iva" value="1">
                                                <label class="form-check-label fw-bold ms-1" for="discrimina_iva" id="lblDiscriminaIva">
                                                    No discrimina IVA
                                                </label>
                                            </div>
                                        </div>
                                        <small class="text-muted d-block mt-1">¿Desglosa IVA neto y alícuotas?</small>
                                    </div>
                                </div>

                                <div class="alert alert-info py-2 px-3 mt-2 mb-0">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <small>El cruce entre <strong>Emisor</strong> y <strong>Receptor</strong> debe ser único. Esta regla determina automáticamente la letra del comprobante en facturación.</small>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="btnGuardar">
                                <i class="fas fa-save me-1"></i>Guardar Regla
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
        .dataTables_wrapper .dt-buttons {
            float: right;
            margin-top: 5px;
        }
        .dropdown-menu .dropdown-item i {
            width: 20px;
            text-align: center;
            margin-right: 8px;
        }
        .badge-condicion {
            font-size: 0.85em;
            padding: 4px 8px;
        }
    </style>

    <script>
        $(document).ready(function () {
            const pagina_idx = <?php echo $pagina_idx; ?>;
            var tabla;
            var currentPage = 0;
            var currentOrder = [[1, 'asc']];
            var currentSearch = '';
            var catalogosCargados = false;
            var listaCondiciones = [];
            var listaComprobantes = [];

            // Cargar catálogos para selects
            function cargarCatalogos(callback) {
                $.ajax({
                    url: 'comprobantes_relacion_fiscal_ajax.php',
                    type: 'GET',
                    data: { accion: 'obtener_catalogos', pagina_idx: pagina_idx },
                    dataType: 'json',
                    success: function (data) {
                        listaCondiciones = data.condiciones || [];
                        listaComprobantes = data.comprobantes || [];
                        poblarSelects();
                        catalogosCargados = true;
                        if (typeof callback === 'function') callback();
                    },
                    error: function (xhr, status, error) {
                        console.error('Error al cargar catálogos:', error);
                    }
                });
            }

            function poblarSelects() {
                var selEmisor = $('#condicion_fiscal_emisor_id');
                var selReceptor = $('#condicion_fiscal_receptor_id');
                var selComp = $('#comprobante_fiscal_id');

                selEmisor.empty().append('<option value="">Seleccione condición del emisor...</option>');
                selReceptor.empty().append('<option value="">Seleccione condición del receptor...</option>');
                selComp.empty().append('<option value="">Seleccione tipo de comprobante...</option>');

                $.each(listaCondiciones, function (i, c) {
                    var optText = c.condicion_fiscal_codigo + ' - ' + c.condicion_fiscal;
                    selEmisor.append($('<option>', { value: c.condicion_fiscal_id, text: optText }));
                    selReceptor.append($('<option>', { value: c.condicion_fiscal_id, text: optText }));
                });

                $.each(listaComprobantes, function (i, cp) {
                    var letraBadge = cp.letra ? ' [' + cp.letra + ']' : '';
                    var optText = cp.codigo + ' - ' + cp.comprobante_fiscal + letraBadge;
                    selComp.append($('<option>', { value: cp.comprobante_fiscal_id, text: optText }));
                });

                inicializarSelect2();
            }

            function inicializarSelect2() {
                if (typeof $.fn.select2 === 'function') {
                    $('#modalRelacionFiscal .select2-catalog').select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        dropdownParent: $('#modalRelacionFiscal')
                    });
                }
            }

            // Actualizar etiqueta del switch discrimina IVA
            $('#discrimina_iva').on('change', function () {
                if ($(this).is(':checked')) {
                    $('#lblDiscriminaIva').html('<span class="text-success"><i class="fas fa-check-circle me-1"></i>Sí discrimina IVA</span>');
                } else {
                    $('#lblDiscriminaIva').html('<span class="text-muted"><i class="fas fa-times-circle me-1"></i>No discrimina IVA</span>');
                }
            });

            // Inicializar DataTable
            function inicializarDataTable() {
                if ($.fn.DataTable.isDataTable('#tablaRelacionFiscal')) {
                    $('#tablaRelacionFiscal').DataTable().destroy();
                    $('#tablaRelacionFiscal tbody').empty();
                }

                tabla = $('#tablaRelacionFiscal').DataTable({
                    ajax: {
                        url: 'comprobantes_relacion_fiscal_ajax.php',
                        type: 'GET',
                        data: { accion: 'listar', pagina_idx: pagina_idx },
                        dataSrc: ''
                    },
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                    dom: '<"row align-items-center mb-2"<"col-12 col-md-6"><"col-12 col-md-6 text-end"f>>' +
                         '<"row"<"col-sm-12"tr>>' +
                         '<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>',
                    buttons: [
                        { extend: 'excelHtml5', text: '<i class="fas fa-file-excel"></i> Excel', className: 'btn btn-success btn-sm', title: 'Relacion_Fiscal_Comprobantes' },
                        { extend: 'pdfHtml5', text: '<i class="fas fa-file-pdf"></i> PDF', className: 'btn btn-danger btn-sm', title: 'Relacion_Fiscal_Comprobantes', orientation: 'landscape' },
                        { extend: 'csvHtml5', text: '<i class="fas fa-file-csv"></i> CSV', className: 'btn btn-primary btn-sm', title: 'Relacion_Fiscal_Comprobantes' },
                        { extend: 'print', text: '<i class="fas fa-print"></i> Imprimir', className: 'btn btn-secondary btn-sm', title: 'Relacion Fiscal Comprobantes' }
                    ],
                    columns: [
                        { data: 'relacion_fiscal_id', className: 'ui-detalle' },
                        {
                            data: 'emisor_nombre',
                            render: function (data, type, row) {
                                if (type === 'export') return (row.emisor_codigo || '') + ' - ' + (data || '');
                                var badge = row.emisor_codigo ? `<span class="badge bg-primary me-1">${row.emisor_codigo}</span>` : '';
                                return `<div class="fw-medium">${badge}${data || 'Sin emisor'}</div>`;
                            }
                        },
                        {
                            data: 'receptor_nombre',
                            render: function (data, type, row) {
                                if (type === 'export') return (row.receptor_codigo || '') + ' - ' + (data || '');
                                var badge = row.receptor_codigo ? `<span class="badge bg-info text-dark me-1">${row.receptor_codigo}</span>` : '';
                                return `<div class="fw-medium">${badge}${data || 'Sin receptor'}</div>`;
                            }
                        },
                        {
                            data: 'comprobante_fiscal',
                            render: function (data, type, row) {
                                if (type === 'export') return (row.letra ? '[' + row.letra + '] ' : '') + (data || '') + (row.comprobante_codigo ? ' (' + row.comprobante_codigo + ')' : '');
                                var letra = row.letra ? `<span class="badge bg-dark me-1">${row.letra}</span>` : '';
                                var codigo = row.comprobante_codigo ? ` <small class="text-muted">(${row.comprobante_codigo})</small>` : '';
                                return `<div class="fw-medium">${letra}${data || 'Sin comprobante'}${codigo}</div>`;
                            }
                        },
                        {
                            data: 'discrimina_iva',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (type === 'export') return data == 1 ? 'Sí' : 'No';
                                if (data == 1) {
                                    return '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Sí</span>';
                                } else {
                                    return '<span class="badge bg-secondary"><i class="fas fa-times me-1"></i>No</span>';
                                }
                            }
                        },
                        {
                            data: 'estado_info',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (!data || !data.estado_registro) return '<span class="badge bg-secondary">Sin estado</span>';
                                var badgeClass = data.bg_clase ? data.bg_clase.replace('bg-', '') : (row.tabla_estado_registro_id == 1 ? 'success' : 'secondary');
                                return `<span class="badge bg-${badgeClass}">${data.estado_registro}</span>`;
                            }
                        },
                        {
                            data: 'botones',
                            orderable: false,
                            searchable: false,
                            className: "text-center",
                            width: '160px',
                            render: function (data, type, row) {
                                if (type === 'export') return '';
                                if (!data || data.length === 0) return '<span class="text-muted small">Sin acciones</span>';

                                var html = '<div class="btn-group" role="group">';
                                data.forEach(boton => {
                                    var claseBoton = 'btn btn-sm me-1 ' + (boton.bg_clase && boton.text_clase ? boton.bg_clase + ' ' + boton.text_clase : (boton.color_clase || 'btn-outline-primary'));
                                    var titulo = boton.descripcion || boton.nombre_funcion;
                                    var icono = boton.icono_clase ? `<i class="${boton.icono_clase}"></i>` : '';
                                    html += `<button type="button" class="btn ${claseBoton} btn-accion" 
                                                title="${titulo}" 
                                                data-id="${row.relacion_fiscal_id}" 
                                                data-accion="${boton.accion_js}"
                                                data-confirmable="${boton.es_confirmable || 0}">
                                                ${icono}
                                            </button>`;
                                });
                                html += '</div>';
                                return html;
                            }
                        }
                    ],
                    language: {
                        url: '<?= asset_local("js/datatables-es-ES.json") ?>'
                    },
                    order: [[1, 'asc'], [2, 'asc']],
                    responsive: true,
                    createdRow: function (row, data, dataIndex) {
                        if (data.tabla_estado_registro_id != 1) {
                            $(row).addClass('table-secondary');
                        }
                    },
                    initComplete: function () {
                        new $.fn.dataTable.Buttons(tabla, {
                            buttons: ['excelHtml5', 'pdfHtml5', 'csvHtml5', 'print']
                        }).container().appendTo($('#tablaRelacionFiscal_wrapper .col-md-6:eq(1)'));
                    }
                });
            }

            // Cargar botón Agregar desde conf__paginas_funciones
            function cargarBotonAgregar() {
                $.get('comprobantes_relacion_fiscal_ajax.php', {
                    accion: 'obtener_boton_agregar',
                    pagina_idx: pagina_idx
                }, function (boton) {
                    if (boton && boton.nombre_funcion) {
                        var icono = boton.icono_clase ? `<i class="${boton.icono_clase} me-1"></i>` : '<i class="fas fa-plus me-1"></i>';
                        var colorClase = boton.color_clase || 'btn-primary';
                        $('#contenedor-boton-agregar').html(
                            `<button type="button" class="btn ${colorClase}" id="btnNuevo">
                                ${icono}${boton.nombre_funcion}
                            </button>`
                        );
                    } else {
                        $('#contenedor-boton-agregar').html(
                            '<button type="button" class="btn btn-primary" id="btnNuevo"><i class="fas fa-plus me-1"></i>Agregar Relación Fiscal</button>'
                        );
                    }
                }, 'json');
            }

            // Manejador botón Nuevo
            $(document).on('click', '#btnNuevo', function () {
                resetModal();
                $('#modalLabel').html('<i class="fas fa-plus-circle me-2"></i>Nueva Relación Fiscal');
                var modal = new bootstrap.Modal(document.getElementById('modalRelacionFiscal'));
                modal.show();
            });

            // Manejador botones de acción
            $(document).on('click', '.btn-accion', function () {
                var id = $(this).data('id');
                var accionJs = $(this).data('accion');
                var confirmable = $(this).data('confirmable');

                if (accionJs === 'editar') {
                    cargarParaEditar(id);
                } else if (confirmable == 1) {
                    Swal.fire({
                        title: `¿Confirmar ${accionJs}?`,
                        text: `¿Está seguro de que desea ${accionJs} esta relación fiscal?`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, continuar',
                        cancelButtonText: 'Cancelar',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            ejecutarAccion(id, accionJs);
                        }
                    });
                } else {
                    ejecutarAccion(id, accionJs);
                }
            });

            function ejecutarAccion(id, accionJs) {
                $.post('comprobantes_relacion_fiscal_ajax.php', {
                    accion: 'ejecutar_accion',
                    relacion_fiscal_id: id,
                    accion_js: accionJs,
                    pagina_idx: pagina_idx
                }, function (res) {
                    if (res.success) {
                        tabla.ajax.reload(null, false);
                        Swal.fire({
                            icon: 'success',
                            title: '¡Operación exitosa!',
                            text: res.message || 'Estado actualizado',
                            timer: 1500,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: res.error || 'No se pudo completar la acción'
                        });
                    }
                }, 'json');
            }

            function cargarParaEditar(id) {
                $.get('comprobantes_relacion_fiscal_ajax.php', {
                    accion: 'obtener',
                    relacion_fiscal_id: id
                }, function (res) {
                    if (res && res.relacion_fiscal_id) {
                        resetModal();
                        $('#relacion_fiscal_id').val(res.relacion_fiscal_id);
                        $('#condicion_fiscal_emisor_id').val(res.condicion_fiscal_emisor_id).trigger('change');
                        $('#condicion_fiscal_receptor_id').val(res.condicion_fiscal_receptor_id).trigger('change');
                        $('#comprobante_fiscal_id').val(res.comprobante_fiscal_id).trigger('change');
                        $('#discrimina_iva').prop('checked', res.discrimina_iva == 1).trigger('change');

                        $('#modalLabel').html('<i class="fas fa-edit me-2"></i>Editar Relación Fiscal');
                        var modal = new bootstrap.Modal(document.getElementById('modalRelacionFiscal'));
                        modal.show();
                    } else {
                        Swal.fire('Error', 'No se pudo obtener el registro', 'error');
                    }
                }, 'json');
            }

            function resetModal() {
                $('#formRelacionFiscal')[0].reset();
                $('#relacion_fiscal_id').val('');
                $('#formRelacionFiscal').removeClass('was-validated');
                $('#condicion_fiscal_emisor_id').val('').trigger('change');
                $('#condicion_fiscal_receptor_id').val('').trigger('change');
                $('#comprobante_fiscal_id').val('').trigger('change');
                $('#discrimina_iva').prop('checked', false).trigger('change');
            }

            // Guardar Formulario
            $('#btnGuardar').on('click', function () {
                var form = document.getElementById('formRelacionFiscal');
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return;
                }

                var id = $('#relacion_fiscal_id').val();
                var accionBackend = id ? 'editar' : 'agregar';
                var btn = $(this);
                var originalHtml = btn.html();
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

                $.ajax({
                    url: 'comprobantes_relacion_fiscal_ajax.php',
                    type: 'POST',
                    data: {
                        accion: accionBackend,
                        relacion_fiscal_id: id,
                        condicion_fiscal_emisor_id: $('#condicion_fiscal_emisor_id').val(),
                        condicion_fiscal_receptor_id: $('#condicion_fiscal_receptor_id').val(),
                        comprobante_fiscal_id: $('#comprobante_fiscal_id').val(),
                        discrimina_iva: $('#discrimina_iva').is(':checked') ? 1 : 0,
                        pagina_idx: pagina_idx
                    },
                    dataType: 'json',
                    success: function (res) {
                        btn.prop('disabled', false).html(originalHtml);
                        if (res.resultado) {
                            tabla.ajax.reload(null, false);
                            var modalEl = document.getElementById('modalRelacionFiscal');
                            var modal = bootstrap.Modal.getInstance(modalEl);
                            if (modal) modal.hide();

                            Swal.fire({
                                icon: 'success',
                                title: '¡Guardado!',
                                text: res.mensaje || 'Relación fiscal guardada con éxito',
                                timer: 1500,
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Atención',
                                text: res.error || 'No se pudo guardar la relación fiscal'
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        btn.prop('disabled', false).html(originalHtml);
                        console.error('Error al guardar:', error);
                        Swal.fire('Error de conexión', 'No se pudo comunicar con el servidor', 'error');
                    }
                });
            });

            // Botón recargar
            $('#btnRecargar').on('click', function () {
                var btn = $(this);
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                tabla.ajax.reload(function () {
                    btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
                }, false);
            });

            // Exportaciones
            $('#btnExportarExcel').click(function (e) { e.preventDefault(); $('.buttons-excel').click(); });
            $('#btnExportarPDF').click(function (e) { e.preventDefault(); $('.buttons-pdf').click(); });
            $('#btnExportarCSV').click(function (e) { e.preventDefault(); $('.buttons-csv').click(); });
            $('#btnExportarPrint').click(function (e) { e.preventDefault(); $('.buttons-print').click(); });

            // Inicializar página
            cargarCatalogos(function () {
                inicializarDataTable();
            });
            cargarBotonAgregar();
        });
    </script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
