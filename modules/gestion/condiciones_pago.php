<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Condiciones de Pago";
$currentPage = 'condiciones_pago';
$modudo_idx = 2;
$pagina_idx = 66; // ID de página para condiciones de pago

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-credit-card me-2"></i>Condiciones de Pago
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Gestión</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Condiciones de Pago</li>
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
                                        <!-- DataTable -->
                                        <table id="tablaCondicionesPago" class="table table-striped table-bordered"
                                            style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="80">ID</th>
                                                    <th width="100">Código</th>
                                                    <th width="200">Condición de Pago</th>
                                                    <th width="120">Tipo</th>
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

            <!-- Modal para crear/editar condición de pago -->
            <div class="modal fade" id="modalCondicionPago" tabindex="-1" aria-labelledby="modalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Condición de Pago</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formCondicionPago" class="needs-validation" novalidate>
                                <input type="hidden" id="condicion_pago_id" name="condicion_pago_id" />
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="codigo" class="form-label">Código *</label>
                                        <input type="text" class="form-control" id="codigo" name="codigo" 
                                            maxlength="20" required pattern="[A-Za-z0-9\-_\.]+">
                                        <div class="invalid-feedback">El código es obligatorio (máx. 20 caracteres, solo letras, números, guiones y puntos)</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="tipo" class="form-label">Tipo *</label>
                                        <select class="form-select" id="tipo" name="tipo" required>
                                            <option value="">Seleccionar...</option>
                                            <option value="CONTADO">CONTADO</option>
                                            <option value="CUENTA_CORRIENTE">CUENTA CORRIENTE</option>
                                        </select>
                                        <div class="invalid-feedback">Debe seleccionar un tipo</div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label for="condicion_pago" class="form-label">Condición de Pago *</label>
                                        <input type="text" class="form-control" id="condicion_pago"
                                            name="condicion_pago" maxlength="100" required>
                                        <div class="invalid-feedback">El nombre de la condición de pago es obligatorio</div>
                                        <div class="form-text">Máximo 100 caracteres</div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="orden" class="form-label">Orden</label>
                                        <input type="number" class="form-control" id="orden" name="orden" 
                                            min="0" max="65535">
                                        <div class="form-text">Valor opcional para ordenar</div>
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
    </style>

    <script>
        $(document).ready(function () {
            // Variables de contexto MULTIEMPRESA
            const empresa_idx = <?php echo isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : 2; ?>;
            const pagina_idx = <?php echo $pagina_idx; ?>;

            // Variables para mantener el estado del DataTable
            var tabla;
            var currentPage = 0;
            var currentOrder = [[4, 'asc']]; // Ordenar por orden por defecto
            var currentSearch = '';

            // Función para inicializar DataTable
            function inicializarDataTable() {
                // Destruir DataTable existente si hay uno
                if ($.fn.DataTable.isDataTable('#tablaCondicionesPago')) {
                    $('#tablaCondicionesPago').DataTable().destroy();
                    $('#tablaCondicionesPago tbody').empty();
                }

                // Configuración de DataTable con botones de exportación
                tabla = $('#tablaCondicionesPago').DataTable({
                    ajax: {
                        url: 'condiciones_pago_ajax.php',
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
                            if (searchValue === '-1' || searchValue === '-1' || searchValue === '') {
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
                            title: 'Condiciones_de_Pago',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5],
                                orthogonal: 'export'
                            }
                        },
                        {
                            extend: 'pdfHtml5',
                            text: '<i class="fas fa-file-pdf"></i> PDF',
                            className: 'btn btn-danger btn-sm',
                            title: 'Condiciones de Pago',
                            orientation: 'portrait',
                            pageSize: 'A4',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5],
                                orthogonal: 'export'
                            }
                        },
                        {
                            extend: 'csvHtml5',
                            text: '<i class="fas fa-file-csv"></i> CSV',
                            className: 'btn btn-primary btn-sm',
                            title: 'Condiciones_de_Pago',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5]
                            }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> Imprimir',
                            className: 'btn btn-secondary btn-sm',
                            title: 'Condiciones de Pago',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5],
                                stripHtml: false
                            }
                        }
                    ],
                    columns: [
                        {
                            data: 'condicion_pago_id',
                            className: 'text-center fw-bold'
                        },
                        {
                            data: 'codigo',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data;
                                }
                                return `<span class="badge bg-primary">${data}</span>`;
                            }
                        },
                        {
                            data: 'condicion_pago',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data;
                                }
                                return `<div class="fw-medium">${data}</div>`;
                            }
                        },
                        {
                            data: 'tipo',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data;
                                }
                                
                                var badgeClass = data === 'CONTADO' ? 'bg-success' : 'bg-info';
                                var tipoTexto = data === 'CONTADO' ? 'CONTADO' : 'CUENTA CORRIENTE';
                                
                                return `<span class="badge ${badgeClass}">${tipoTexto}</span>`;
                            }
                        },
                        {
                            data: 'orden',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data || '0';
                                }
                                return data ? `<span class="badge bg-secondary">${data}</span>` : '<span class="text-muted">-</span>';
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
                                var badgeClass = 'bg-secondary';
                                
                                // Asignar colores según estado
                                if (data.codigo_estandar === 'ACTIVO') {
                                    badgeClass = 'bg-success';
                                } else if (data.codigo_estandar === 'INACTIVO') {
                                    badgeClass = 'bg-danger';
                                } else if (data.codigo_estandar === 'BLOQUEADO') {
                                    badgeClass = 'bg-warning text-dark';
                                }

                                if (type === 'export') {
                                    return estado;
                                }

                                return `<span class="badge ${badgeClass}">${estado}</span>`;
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
                                       data-id="${row.condicion_pago_id}" 
                                       data-accion="${accionJs}"
                                       data-confirmable="${esConfirmable}"
                                       data-condicion="${row.condicion_pago}">
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
                    createdRow: function (row, data, dataIndex) {
                        if (data.estado_info && data.estado_info.codigo_estandar === 'INACTIVO') {
                            $(row).addClass('table-secondary');
                        } else if (data.estado_info && data.estado_info.codigo_estandar === 'BLOQUEADO') {
                            $(row).addClass('table-warning');
                        }
                    },
                    initComplete: function () {
                        // Mover los botones de exportación al contenedor correcto
                        var buttons = new $.fn.dataTable.Buttons(tabla, {
                            buttons: ['excelHtml5', 'pdfHtml5', 'csvHtml5', 'print']
                        }).container().appendTo($('#tablaCondicionesPago_wrapper .col-md-6:eq(1)'));

                        // Guardar estado actual al cambiar de página
                        $(tabla.table().container()).on('page.dt', function (e) {
                            currentPage = tabla.page();
                        });

                        // Guardar estado actual al ordenar
                        $(tabla.table().container()).on('order.dt', function (e, settings, details) {
                            currentOrder = tabla.order();
                        });

                        // Guardar estado actual al buscar
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
                $.get('condiciones_pago_ajax.php', {
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
                            '<i class="fas fa-plus me-1"></i>Agregar Condición</button>'
                        );
                    }
                }, 'json');
            }

            // Manejador para botón "Agregar"
            $(document).on('click', '#btnNuevo', function () {
                resetModal();
                $('#modalLabel').text('Nueva Condición de Pago');
                
                // Poner foco en el primer campo
                var modal = new bootstrap.Modal(document.getElementById('modalCondicionPago'));
                modal.show();
                setTimeout(function() {
                    $('#codigo').focus();
                }, 500);
            });

            // Manejador para botones de acción dinámicos
            $(document).on('click', '.btn-accion', function () {
                var condicionId = $(this).data('id');
                var accionJs = $(this).data('accion');
                var confirmable = $(this).data('confirmable');
                var condicion = $(this).data('condicion');

                if (accionJs === 'editar') {
                    cargarCondicionParaEditar(condicionId);
                } else if (confirmable == 1) {
                    Swal.fire({
                        title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                        html: `¿Está seguro de <strong>${accionJs}</strong> la condición <strong>"${condicion}"</strong>?`,
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
                            ejecutarAccion(condicionId, accionJs, condicion);
                        }
                    });
                } else {
                    ejecutarAccion(condicionId, accionJs, condicion);
                }
            });

            // Función para ejecutar cualquier acción del backend
            function ejecutarAccion(condicionId, accionJs, condicion) {
                var savedState = {
                    page: tabla.page(),
                    order: tabla.order(),
                    search: tabla.search()
                };

                $.post('condiciones_pago_ajax.php', {
                    accion: 'ejecutar_accion',
                    condicion_pago_id: condicionId,
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
                                if (data.condicion_pago_id == condicionId) {
                                    $(this.node()).addClass('table-success');
                                    setTimeout(function () {
                                        $(this.node()).removeClass('table-success');
                                    }.bind(this), 2000);
                                }
                            });

                            Swal.fire({
                                icon: "success",
                                title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                                text: res.message || `Condición "${condicion}" actualizada correctamente`,
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
                            text: res.error || `Error al ${accionJs} la condición`,
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            // Función para cargar condición en modal de edición
            function cargarCondicionParaEditar(condicionId) {
                $.get('condiciones_pago_ajax.php', {
                    accion: 'obtener',
                    condicion_pago_id: condicionId,
                    empresa_idx: empresa_idx
                }, function (res) {
                    if (res && res.condicion_pago_id) {
                        resetModal();
                        $('#condicion_pago_id').val(res.condicion_pago_id);
                        $('#codigo').val(res.codigo);
                        $('#condicion_pago').val(res.condicion_pago);
                        $('#tipo').val(res.tipo);
                        $('#orden').val(res.orden || '');
                        $('#modalLabel').text('Editar Condición de Pago');

                        var modal = new bootstrap.Modal(document.getElementById('modalCondicionPago'));
                        modal.show();

                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "Error al obtener datos de la condición de pago",
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            // Función para resetear el modal
            function resetModal() {
                $('#formCondicionPago')[0].reset();
                $('#condicion_pago_id').val('');
                $('#formCondicionPago').removeClass('was-validated');
            }

            // Validación del formulario
            $('#btnGuardar').click(function () {
                var form = document.getElementById('formCondicionPago');

                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var id = $('#condicion_pago_id').val();
                var accionBackend = id ? 'editar' : 'agregar';
                var codigo = $('#codigo').val().trim();
                var condicionPago = $('#condicion_pago').val().trim();
                var tipo = $('#tipo').val();
                var orden = $('#orden').val() || 0;

                // Validaciones adicionales
                if (!codigo || codigo.length > 20) {
                    $('#codigo').addClass('is-invalid');
                    return false;
                }

                if (!condicionPago || condicionPago.length > 100) {
                    $('#condicion_pago').addClass('is-invalid');
                    return false;
                }

                if (!tipo) {
                    $('#tipo').addClass('is-invalid');
                    return false;
                }

                // Validar formato del código
                var codigoRegex = /^[A-Za-z0-9\-_\.]+$/;
                if (!codigoRegex.test(codigo)) {
                    $('#codigo').addClass('is-invalid');
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
                    url: 'condiciones_pago_ajax.php',
                    type: 'POST',
                    data: {
                        accion: accionBackend,
                        condicion_pago_id: id,
                        codigo: codigo,
                        condicion_pago: condicionPago,
                        tipo: tipo,
                        orden: orden,
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
                                        if (data.condicion_pago_id == id) {
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
                                    text: "Condición de pago guardada correctamente",
                                    showConfirmButton: false,
                                    timer: 1500,
                                    toast: true,
                                    position: 'top-end'
                                });

                                var modalEl = document.getElementById('modalCondicionPago');
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
            inicializarDataTable();
            cargarBotonAgregar();

            // Agregar tooltips a los botones
            $('[title]').tooltip({
                trigger: 'hover',
                placement: 'top'
            });

            // Limpiar localStorage si tiene el bug del "-1"
            $(window).on('load', function () {
                setTimeout(function () {
                    var savedData = localStorage.getItem('DataTables_tablaCondicionesPago');
                    if (savedData) {
                        var data = JSON.parse(savedData);
                        if (data.search) {
                            if (data.search.search === '-1' || data.search.search === '') {
                                data.search.search = '';
                                localStorage.setItem('DataTables_tablaCondicionesPago', JSON.stringify(data));
                            }
                        }
                    }
                }, 500);
            });
        });
    </script>

    <!-- Librerías necesarias para DataTables Buttons -->
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

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