<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Configuración de Impuestos por Subgrupo";
$currentPage = 'empresas_impuestos_config_subgrupos';
$modudo_idx = 2;
$pagina_idx = 76;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

// Obtener parámetros de filtro
$empresa_impuesto_config_id = isset($_GET['empresa_impuesto_config_id']) ? intval($_GET['empresa_impuesto_config_id']) : 0;
$empresa_id = isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : 2;

// Si no hay configuración seleccionada, mostrar error y salir
if ($empresa_impuesto_config_id <= 0) {
    echo '<div class="container mt-5">
            <div class="alert alert-danger">
                <h4><i class="fas fa-exclamation-triangle"></i> Error</h4>
                <p>Debe seleccionar una configuración de impuesto.</p>
                <p>Use el parámetro <code>?empresa_impuesto_config_id=ID</code> en la URL.</p>
                <hr>
                <p>Ejemplo: <code>empresas_impuestos_config_subgrupos.php?empresa_impuesto_config_id=1&empresa_id=2</code></p>
            </div>
          </div>';
    require_once ROOT_PATH . '/templates/adminlte/footer1.php';
    exit;
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-tags me-2"></i>Configuración de Impuestos por Subgrupo
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Gestión</a></li>
                        <li class="breadcrumb-item"><a href="#">Impuestos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Configuración por Subgrupo</li>
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
                                                    <li><a class="dropdown-item" href="#" id="btnExportarExcel"><i class="fas fa-file-excel text-success"></i> Excel</a></li>
                                                    <li><a class="dropdown-item" href="#" id="btnExportarPDF"><i class="fas fa-file-pdf text-danger"></i> PDF</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item" href="#" id="btnExportarCSV"><i class="fas fa-file-csv text-primary"></i> CSV</a></li>
                                                    <li><a class="dropdown-item" href="#" id="btnExportarPrint"><i class="fas fa-print text-secondary"></i> Imprimir</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <!-- Información de la configuración -->
                                        <div class="row mb-3">
                                            <div class="col-md-12">
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    <strong>Configuración de Impuesto:</strong>
                                                    <span id="configInfo">Cargando...</span>
                                                    <input type="hidden" id="filter_empresa_impuesto_config_id" value="<?php echo $empresa_impuesto_config_id; ?>">
                                                    <input type="hidden" id="filter_empresa_id" value="<?php echo $empresa_id; ?>">
                                                    <input type="hidden" id="filter_pagina_idx" value="<?php echo $pagina_idx; ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- DataTable -->
                                        <table id="tablaConfigSubgrupos" class="table table-striped table-bordered" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="50">ID</th>
                                                    <th width="450">Subgrupo de Comprobante</th>
                                                    <th width="100">Estado</th>
                                                    <th width="150" class="text-center">Acciones</th>
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

            <!-- Modal para crear/editar -->
            <div class="modal fade" id="modalConfigSubgrupo" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Configuración de Impuesto por Subgrupo</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formConfigSubgrupo" class="needs-validation" novalidate>
                                <input type="hidden" id="empresa_impuesto_config_subgrupo_id" name="empresa_impuesto_config_subgrupo_id" />
                                <input type="hidden" id="empresa_impuesto_config_id" name="empresa_impuesto_config_id" value="<?php echo $empresa_impuesto_config_id; ?>" />

                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="config_info_display" class="form-label">Configuración de Impuesto</label>
                                        <input type="text" class="form-control" id="config_info_display" readonly>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="comprobante_subgrupo_id" class="form-label">Subgrupo de Comprobante *</label>
                                        <select class="form-select" id="comprobante_subgrupo_id" name="comprobante_subgrupo_id" required>
                                            <option value="">Seleccione un subgrupo...</option>
                                        </select>
                                        <div class="invalid-feedback">Debe seleccionar un subgrupo de comprobante</div>
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

    <style>
        .dt-buttons .btn { margin-right: 5px; margin-bottom: 5px; }
        .dataTables_wrapper .dt-buttons { float: right; margin-top: 5px; }
        .dropdown-menu .dropdown-item i { width: 20px; text-align: center; margin-right: 8px; }
    </style>

    <script>
        $(document).ready(function () {
            const empresa_impuesto_config_id = $('#filter_empresa_impuesto_config_id').val();
            const empresa_id = $('#filter_empresa_id').val();
            const pagina_idx = $('#filter_pagina_idx').val();

            var tabla;
            var currentPage = 0;
            var currentOrder = [[0, 'asc']];
            var currentSearch = '';

            // Cargar información de la configuración
            function cargarConfiguracionInfo() {
                $.ajax({
                    url: 'empresas_impuestos_config_subgrupos_ajax.php',
                    type: 'GET',
                    data: {
                        accion: 'obtener_config_info',
                        empresa_impuesto_config_id: empresa_impuesto_config_id,
                        empresa_id: empresa_id
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response && response.success) {
                            $('#configInfo').html('<strong>' + response.info + '</strong>');
                            $('#config_info_display').val(response.info);
                        } else {
                            $('#configInfo').html('<strong class="text-danger">' + (response.error || 'Configuración no encontrada') + '</strong>');
                        }
                    },
                    error: function() {
                        $('#configInfo').html('<strong class="text-danger">Error al cargar la configuración</strong>');
                    }
                });
            }

            // Cargar subgrupos disponibles (excluyendo los ya asignados)
            function cargarSubgruposDisponibles(selectedId = null) {
                $.ajax({
                    url: 'empresas_impuestos_config_subgrupos_ajax.php',
                    type: 'GET',
                    data: {
                        accion: 'listar_subgrupos_disponibles',
                        empresa_id: empresa_id,
                        empresa_impuesto_config_id: empresa_impuesto_config_id
                    },
                    dataType: 'json',
                    success: function(response) {
                        var select = $('#comprobante_subgrupo_id');
                        select.empty();
                        select.append('<option value="">Seleccione un subgrupo...</option>');
                        
                        if (response && response.length > 0) {
                            $.each(response, function(index, subgrupo) {
                                var optionText = subgrupo.codigo ? '[' + subgrupo.codigo + '] ' : '';
                                optionText += subgrupo.comprobante_subgrupo;
                                select.append('<option value="' + subgrupo.comprobante_subgrupo_id + '">' + optionText + '</option>');
                            });
                        } else {
                            select.append('<option value="" disabled>No hay subgrupos disponibles</option>');
                        }
                        
                        if (selectedId) {
                            select.val(selectedId);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                    }
                });
            }

            // Inicializar DataTable
            function inicializarDataTable() {
                if ($.fn.DataTable.isDataTable('#tablaConfigSubgrupos')) {
                    $('#tablaConfigSubgrupos').DataTable().destroy();
                }

                tabla = $('#tablaConfigSubgrupos').DataTable({
                    ajax: {
                        url: 'empresas_impuestos_config_subgrupos_ajax.php',
                        type: 'GET',
                        data: {
                            accion: 'listar',
                            empresa_impuesto_config_id: empresa_impuesto_config_id,
                            empresa_id: empresa_id,
                            pagina_idx: pagina_idx
                        },
                        dataSrc: ''
                    },
                    stateSave: true,
                    stateSaveParams: function (settings, data) {
                        data.page = currentPage;
                        data.order = currentOrder;
                        data.search = { search: currentSearch };
                        delete data.columns;
                        return data;
                    },
                    stateLoadParams: function (settings, data) {
                        if (data.page !== undefined) currentPage = data.page;
                        if (data.order !== undefined) currentOrder = data.order;
                        currentSearch = (data.search && data.search.search) ? data.search.search : '';
                        data.search = { search: currentSearch };
                    },
                    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                         '<"row"<"col-sm-12"tr>>' +
                         '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                    pageLength: 50,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                    columns: [
                        { data: 'empresa_impuesto_config_subgrupo_id', className: 'text-center fw-bold' },
                        { 
                            data: 'subgrupo_info',
                            render: function(data, type, row) {
                                if (type === 'export') return data || '';
                                return data ? `<div>${escapeHtml(data)}</div>` : '<span class="text-muted">-</span>';
                            }
                        },
                        { 
                            data: 'estado_info',
                            className: 'text-center',
                            render: function(data, type, row) {
                                if (!data || !data.estado_registro) {
                                    return type === 'export' ? 'Sin estado' : '<span class="fw-medium">Sin estado</span>';
                                }
                                var estado = data.estado_registro;
                                var badgeClass = data.bg_clase ? data.bg_clase.replace('bg-', '') : 'secondary';
                                return type === 'export' ? estado : `<span class="badge bg-${badgeClass}">${estado}</span>`;
                            }
                        },
                        { 
                            data: 'botones',
                            orderable: false,
                            searchable: false,
                            className: "text-center",
                            width: '150px',
                            render: function(data, type, row) {
                                if (type === 'export') return '';
                                
                                if (!data || data.length === 0) {
                                    return '<span class="text-muted small">Sin acciones</span>';
                                }
                                
                                var botones = '';
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
                                    
                                    botones += `<button type="button" class="btn ${claseBoton} btn-accion" 
                                        title="${titulo}" 
                                        data-id="${row.empresa_impuesto_config_subgrupo_id}" 
                                        data-accion="${accionJs}"
                                        data-confirmable="${esConfirmable}"
                                        data-subgrupo="${escapeHtml(row.subgrupo_info || '')}">
                                        ${icono}
                                    </button>`;
                                });
                                
                                return `<div class="btn-group" role="group">${botones}</div>`;
                            }
                        }
                    ],
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
                    order: currentOrder,
                    responsive: true,
                    createdRow: function(row, data, dataIndex) {
                        if (data.estado_info && data.estado_info.codigo_estandar === 'INACTIVO') {
                            $(row).addClass('table-secondary');
                        }
                    },
                    initComplete: function() {
                        $(tabla.table().container()).on('page.dt', function(e) { currentPage = tabla.page(); });
                        $(tabla.table().container()).on('order.dt', function(e, settings, details) { currentOrder = tabla.order(); });
                        $(tabla.table().container()).on('search.dt', function(e, settings) { currentSearch = tabla.search(); });
                    }
                });
            }

            function cargarBotonAgregar() {
                $.get('empresas_impuestos_config_subgrupos_ajax.php', {
                    accion: 'obtener_boton_agregar',
                    pagina_idx: pagina_idx
                }, function(botonAgregar) {
                    if (botonAgregar && botonAgregar.nombre_funcion) {
                        var icono = botonAgregar.icono_clase ? `<i class="${botonAgregar.icono_clase} me-1"></i>` : '';
                        var colorClase = botonAgregar.bg_clase ? botonAgregar.bg_clase : 'btn-primary';
                        $('#contenedor-boton-agregar').html(
                            `<button type="button" class="btn ${colorClase}" id="btnNuevo">${icono}${botonAgregar.nombre_funcion}</button>`
                        );
                    } else {
                        $('#contenedor-boton-agregar').html(
                            '<button type="button" class="btn btn-primary" id="btnNuevo"><i class="fas fa-plus me-1"></i>Agregar Subgrupo</button>'
                        );
                    }
                }, 'json');
            }

            $(document).on('click', '#btnNuevo', function() {
                resetModal();
                $('#modalLabel').text('Nueva Configuración');
                cargarSubgruposDisponibles();
                var modal = new bootstrap.Modal(document.getElementById('modalConfigSubgrupo'));
                modal.show();
            });

            $(document).on('click', '.btn-accion', function() {
                var id = $(this).data('id');
                var accionJs = $(this).data('accion');
                var confirmable = $(this).data('confirmable');
                var subgrupo = $(this).data('subgrupo');

                if (accionJs === 'editar') {
                    cargarConfigParaEditar(id);
                } else if (confirmable == 1) {
                    Swal.fire({
                        title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                        html: `¿Está seguro de <strong>${accionJs}</strong> la configuración para el subgrupo <strong>"${subgrupo}"</strong>?`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: `Sí, ${accionJs}`,
                        cancelButtonText: 'Cancelar'
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
                var savedState = { page: tabla.page(), order: tabla.order(), search: tabla.search() };
                
                $.post('empresas_impuestos_config_subgrupos_ajax.php', {
                    accion: 'ejecutar_accion',
                    empresa_impuesto_config_subgrupo_id: id,
                    accion_js: accionJs,
                    empresa_id: empresa_id,
                    pagina_idx: pagina_idx
                }, function(res) {
                    if (res.success) {
                        tabla.ajax.reload(function() {
                            if (savedState.page !== undefined) tabla.page(savedState.page).draw('page');
                            if (savedState.search && savedState.search !== '') tabla.search(savedState.search).draw();
                        }, false);
                        Swal.fire({ icon: "success", title: "¡Éxito!", text: res.message, timer: 1500, showConfirmButton: false, toast: true, position: 'top-end' });
                    } else {
                        Swal.fire({ icon: "error", title: "Error", text: res.error });
                    }
                }, 'json');
            }

            function cargarConfigParaEditar(id) {
                $.get('empresas_impuestos_config_subgrupos_ajax.php', {
                    accion: 'obtener',
                    empresa_impuesto_config_subgrupo_id: id,
                    empresa_id: empresa_id
                }, function(res) {
                    if (res && res.empresa_impuesto_config_subgrupo_id) {
                        resetModal();
                        $('#empresa_impuesto_config_subgrupo_id').val(res.empresa_impuesto_config_subgrupo_id);
                        $('#config_info_display').val(res.configuracion_info || '');
                        cargarSubgruposDisponibles(res.comprobante_subgrupo_id);
                        $('#modalLabel').text('Editar Configuración');
                        var modal = new bootstrap.Modal(document.getElementById('modalConfigSubgrupo'));
                        modal.show();
                    } else {
                        Swal.fire({ icon: "error", title: "Error", text: "Error al obtener datos" });
                    }
                }, 'json');
            }

            function resetModal() {
                $('#formConfigSubgrupo')[0].reset();
                $('#empresa_impuesto_config_subgrupo_id').val('');
                $('#formConfigSubgrupo').removeClass('was-validated');
            }

            $('#btnGuardar').click(function() {
                var form = document.getElementById('formConfigSubgrupo');
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var id = $('#empresa_impuesto_config_subgrupo_id').val();
                var accionBackend = id ? 'editar' : 'agregar';
                var comprobante_subgrupo_id = $('#comprobante_subgrupo_id').val();

                if (!comprobante_subgrupo_id) {
                    Swal.fire({ icon: "warning", title: "Validación", text: "Debe seleccionar un subgrupo" });
                    return false;
                }

                var btnGuardar = $(this);
                var originalText = btnGuardar.html();
                btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

                var savedState = { page: tabla.page(), order: tabla.order(), search: tabla.search() };

                $.ajax({
                    url: 'empresas_impuestos_config_subgrupos_ajax.php',
                    type: 'POST',
                    data: {
                        accion: accionBackend,
                        empresa_impuesto_config_subgrupo_id: id,
                        empresa_impuesto_config_id: empresa_impuesto_config_id,
                        comprobante_subgrupo_id: comprobante_subgrupo_id,
                        empresa_id: empresa_id,
                        pagina_idx: pagina_idx
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.resultado) {
                            tabla.ajax.reload(function() {
                                if (savedState.page !== undefined) tabla.page(savedState.page).draw('page');
                                if (savedState.search && savedState.search !== '') tabla.search(savedState.search).draw();
                            }, false);
                            
                            Swal.fire({ icon: "success", title: "¡Guardado!", text: "Configuración guardada correctamente", timer: 1500, showConfirmButton: false, toast: true, position: 'top-end' });
                            
                            var modalEl = document.getElementById('modalConfigSubgrupo');
                            var modal = bootstrap.Modal.getInstance(modalEl);
                            modal.hide();
                        } else {
                            Swal.fire({ icon: "error", title: "Error", text: res.error || "Error al guardar" });
                        }
                        btnGuardar.prop('disabled', false).html(originalText);
                    },
                    error: function(xhr, status, error) {
                        btnGuardar.prop('disabled', false).html(originalText);
                        Swal.fire({ icon: "error", title: "Error de conexión", text: "Error al comunicarse con el servidor" });
                    }
                });
            });

            // Exportaciones
            $('#btnExportarExcel').click(function(e) {
                e.preventDefault();
                var data = tabla.rows().data().toArray();
                var exportData = data.map(row => ({
                    'ID': row.empresa_impuesto_config_subgrupo_id,
                    'Subgrupo de Comprobante': row.subgrupo_info || '',
                    'Estado': row.estado_info?.estado_registro || ''
                }));
                var ws = XLSX.utils.json_to_sheet(exportData);
                var wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Config_Subgrupos');
                XLSX.writeFile(wb, `Config_Subgrupos_${new Date().toISOString().slice(0,19)}.xlsx`);
            });

            $('#btnExportarPDF').click(function(e) {
                e.preventDefault();
                var printWindow = window.open('', '_blank');
                var content = '<html><head><title>Configuración de Impuestos por Subgrupo</title>';
                content += '<style>table {border-collapse: collapse; width: 100%;} th, td {border: 1px solid #ddd; padding: 8px; text-align: left;} th {background-color: #f2f2f2;}</style>';
                content += '</head><body><h2>Configuración de Impuestos por Subgrupo</h2><table border="1"><thead><tr><th>ID</th><th>Subgrupo</th><th>Estado</th></tr></thead><tbody>';
                var data = tabla.rows().data().toArray();
                data.forEach(row => {
                    content += `<tr><td>${row.empresa_impuesto_config_subgrupo_id}</td><td>${escapeHtml(row.subgrupo_info || '')}</td><td>${row.estado_info?.estado_registro || ''}</td></tr>`;
                });
                content += '</tbody></table></body></html>';
                printWindow.document.write(content);
                printWindow.document.close();
                printWindow.print();
            });

            $('#btnExportarCSV').click(function(e) {
                e.preventDefault();
                var data = tabla.rows().data().toArray();
                var csv = "ID,Subgrupo de Comprobante,Estado\n";
                data.forEach(row => {
                    csv += `"${row.empresa_impuesto_config_subgrupo_id}","${escapeCsv(row.subgrupo_info || '')}","${escapeCsv(row.estado_info?.estado_registro || '')}"\n`;
                });
                var blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
                var link = document.createElement("a");
                link.setAttribute("href", URL.createObjectURL(blob));
                link.setAttribute("download", `Config_Subgrupos_${new Date().toISOString().slice(0,19)}.csv`);
                link.click();
            });

            $('#btnExportarPrint').click(function(e) {
                e.preventDefault();
                window.print();
            });

            function escapeHtml(str) {
                if (!str) return '';
                return str.replace(/[&<>]/g, function(m) {
                    if (m === '&') return '&amp;';
                    if (m === '<') return '&lt;';
                    if (m === '>') return '&gt;';
                    return m;
                });
            }

            function escapeCsv(str) {
                if (!str) return '';
                return str.replace(/"/g, '""');
            }

            // Inicialización
            cargarConfiguracionInfo();
            inicializarDataTable();
            cargarBotonAgregar();
        });
    </script>

    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</main>

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
</body>
</html>