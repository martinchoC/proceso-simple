$(document).ready(function () {
    const empresa_idx = 2;          // ID de la empresa (ajustar según contexto)
    const pagina_idx = 71;          // ID de página en conf__paginas

    var tabla;
    var currentPage = 0;
    var currentOrder = [[1, 'asc']];
    var currentSearch = '';

    // ========== FUNCIONES DE DATATABLE ==========
    function inicializarDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaDepositos')) {
            $('#tablaDepositos').DataTable().destroy();
            $('#tablaDepositos tbody').empty();
        }

        tabla = $('#tablaDepositos').DataTable({
            ajax: {
                url: 'depositos_ajax.php',
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
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>' +
                '<"clear">',
            pageLength: 50,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],

            columns: [
                {
                    data: 'deposito_id',
                    className: 'text-center fw-bold'
                },
                {
                    data: 'sucursal_nombre',
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return data || '';
                        }
                        return `<span class="fw-medium">${data || ''}</span>`;
                    }
                },
                {
                    data: 'deposito_nombre',
                    className: 'text-start',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return data || '';
                        }
                        return `<span class="fw-bold">${data || ''}</span>`;
                    }
                },
                {
                    data: 'codigo',
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return data || '';
                        }
                        return `<span class="fw-medium">${data || ''}</span>`;
                    }
                },
                {
                    data: 'descripcion',
                    className: 'text-start',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return data || '';
                        }
                        return `<span>${data || ''}</span>`;
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
                        var colorClass = data.bg_clase || 'bg-secondary';
                        var textClass = data.text_clase || 'text-white';

                        if (type === 'export') {
                            return estado;
                        }

                        return `<span class="badge ${colorClass} ${textClass}">${estado}</span>`;
                    }
                },
                {
                    data: 'botones',
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    width: '150px',
                    render: function (data, type, row) {
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
                                var nombreInfo = row.deposito_nombre || 'Depósito #' + row.deposito_id;

                                var botonHtml = `<button type="button" class="btn ${claseBoton} btn-accion" 
                                                title="${titulo}" 
                                                data-id="${row.deposito_id}" 
                                                data-accion="${accionJs}"
                                                data-confirmable="${esConfirmable}"
                                                data-nombre="${nombreInfo}">
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
                url: '//cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json'
            },
            order: currentOrder,
            responsive: true,
            createdRow: function (row, data, dataIndex) {
                if (data.estado_info && data.estado_info.codigo_estandar === 'INACTIVO') {
                    $(row).addClass('table-secondary');
                }
            },
            initComplete: function () {
                var buttons = new $.fn.dataTable.Buttons(tabla, {
                    buttons: ['excelHtml5', 'pdfHtml5', 'csvHtml5', 'print']
                }).container().appendTo($('#tablaDepositos_wrapper .col-md-6:eq(1)'));

                $(tabla.table().container()).on('page.dt', function (e) {
                    currentPage = tabla.page();
                });
                $(tabla.table().container()).on('order.dt', function (e, settings, details) {
                    currentOrder = tabla.order();
                });
                $(tabla.table().container()).on('search.dt', function (e, settings) {
                    currentSearch = tabla.search();
                });

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

                inicializarBotonesExternos();
            }
        });

        inicializarEventos();
    }

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

    function inicializarBotonesExternos() {
        $('#btnExportarExcel').off('click').on('click', function(e) {
            e.preventDefault();
            if (tabla) tabla.button('.buttons-excel').trigger();
        });
        $('#btnExportarPDF').off('click').on('click', function(e) {
            e.preventDefault();
            if (tabla) tabla.button('.buttons-pdf').trigger();
        });
        $('#btnExportarCSV').off('click').on('click', function(e) {
            e.preventDefault();
            if (tabla) tabla.button('.buttons-csv').trigger();
        });
        $('#btnExportarPrint').off('click').on('click', function(e) {
            e.preventDefault();
            if (tabla) tabla.button('.buttons-print').trigger();
        });
    }

    function cargarBotonAgregar() {
        $.get('depositos_ajax.php', {
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
                    '<i class="fas fa-plus me-1"></i>Nuevo Depósito</button>'
                );
            }
        }, 'json');
    }

    // ========== MANEJADOR DE ACCIONES DE BOTONES ==========
    $(document).on('click', '.btn-accion', function () {
        var depositoId = $(this).data('id');
        var accionJs = $(this).data('accion');
        var confirmable = $(this).data('confirmable');
        var nombreInfo = $(this).data('nombre') || 'Depósito #' + depositoId;

        if (accionJs === 'editar') {
            cargarDepositoParaEditar(depositoId);
        } else if (confirmable == 1) {
            Swal.fire({
                title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                html: `¿Está seguro de <strong>${accionJs}</strong> el depósito<br>
                    <strong>${nombreInfo}</strong>?`,
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
                    ejecutarAccion(depositoId, accionJs, nombreInfo);
                }
            });
        } else {
            ejecutarAccion(depositoId, accionJs, nombreInfo);
        }
    });

    function ejecutarAccion(depositoId, accionJs, nombreInfo) {
        var savedState = {
            page: tabla.page(),
            order: tabla.order(),
            search: tabla.search()
        };

        $.post('depositos_ajax.php', {
            accion: 'ejecutar_accion',
            deposito_id: depositoId,
            accion_js: accionJs,
            empresa_idx: empresa_idx,
            pagina_idx: pagina_idx
        }, function (res) {
            if (res.success) {
                tabla.ajax.reload(function (json) {
                    if (savedState.page !== undefined) tabla.page(savedState.page).draw('page');
                    if (savedState.search && savedState.search !== '') tabla.search(savedState.search).draw();

                    Swal.fire({
                        icon: "success",
                        title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                        text: res.message || `Depósito "${nombreInfo}" actualizado correctamente`,
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
                    text: res.error || `Error al ${accionJs} el depósito`,
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json').fail(function(xhr) {
            Swal.fire({
                icon: "error",
                title: "Error de conexión",
                text: "No se pudo conectar con el servidor",
                confirmButtonText: "Entendido"
            });
            console.error('Error en ejecutarAccion:', xhr.responseText);
        });
    }

    // ========== CARGA DE COMBOS ==========
    function cargarCombosFormulario() {
        $.get('depositos_ajax.php', {
            accion: 'obtener_sucursales_empresa',
            empresa_idx: empresa_idx
        }, function(data) {
            let options = '<option value="">Seleccionar sucursal</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.sucursal_id}">${item.sucursal_nombre}</option>`;
                });
            } else {
                options = '<option value="">No hay sucursales disponibles</option>';
            }
            $('#sucursal_id').html(options);
        }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
            console.error("Error cargando sucursales:", textStatus, errorThrown);
        });
    }

    // ========== FUNCIONES DEL MODAL ==========
    function resetModal() {
        $('#formDeposito')[0].reset();
        $('#deposito_id').val('');
        $('#formDeposito').removeClass('was-validated');
        // Valores por defecto para checkboxes
        $('#permite_ingresos').prop('checked', true);
        $('#permite_egresos').prop('checked', true);
        $('#es_principal').prop('checked', false);
        $('#orden').val(1);
    }

    $(document).on('click', '#btnNuevo', function () {
        resetModal();
        $('#modalLabel').text('Nuevo Depósito');
        cargarCombosFormulario();
        var modal = new bootstrap.Modal(document.getElementById('modalDeposito'));
        modal.show();
    });

    // ========== CARGA DE DEPÓSITO PARA EDITAR ==========
    function cargarDepositoParaEditar(depositoId) {
        $.get('depositos_ajax.php', {
            accion: 'obtener',
            deposito_id: depositoId,
            empresa_idx: empresa_idx
        }, function (res) {
            if (res && res.deposito_id) {
                resetModal();
                cargarCombosFormulario();

                $('#deposito_id').val(res.deposito_id);
                $('#deposito_nombre').val(res.deposito_nombre || '');
                $('#codigo').val(res.codigo || '');
                $('#descripcion').val(res.descripcion || '');
                $('#orden').val(res.orden || 1);
                $('#permite_ingresos').prop('checked', res.permite_ingresos == 1);
                $('#permite_egresos').prop('checked', res.permite_egresos == 1);
                $('#es_principal').prop('checked', res.es_principal == 1);

                $('#modalLabel').text('Editar Depósito');

                setTimeout(function() {
                    if (res.sucursal_id) {
                        $('#sucursal_id').val(res.sucursal_id);
                    }
                }, 500);

                var modal = new bootstrap.Modal(document.getElementById('modalDeposito'));
                modal.show();
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos del depósito",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    }

    // ========== GUARDAR DEPÓSITO ==========
    $('#btnGuardar').click(function() {
        var form = document.getElementById('formDeposito');
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }

        var id = $('#deposito_id').val();
        var accionBackend = id ? 'editar' : 'agregar';

        var btnGuardar = $(this);
        var originalText = btnGuardar.html();
        btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        var formData = new FormData();
        formData.append('accion', accionBackend);
        formData.append('empresa_idx', empresa_idx);
        formData.append('pagina_idx', pagina_idx);
        formData.append('deposito_id', id);
        formData.append('sucursal_id', $('#sucursal_id').val() || '');
        formData.append('deposito_nombre', $('#deposito_nombre').val() || '');
        formData.append('codigo', $('#codigo').val() || '');
        formData.append('descripcion', $('#descripcion').val() || '');
        formData.append('orden', $('#orden').val() || 1);
        formData.append('permite_ingresos', $('#permite_ingresos').is(':checked') ? 1 : 0);
        formData.append('permite_egresos', $('#permite_egresos').is(':checked') ? 1 : 0);
        formData.append('es_principal', $('#es_principal').is(':checked') ? 1 : 0);

        var savedState = {
            page: tabla ? tabla.page() : 0,
            order: tabla ? tabla.order() : [[1, 'asc']],
            search: tabla ? tabla.search() : ''
        };

        $.ajax({
            url: 'depositos_ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                btnGuardar.prop('disabled', false).html(originalText);
                if (res.resultado) {
                    if (tabla) {
                        tabla.ajax.reload(function(json) {
                            if (savedState.page !== undefined) tabla.page(savedState.page).draw('page');
                            if (savedState.search && savedState.search !== '') tabla.search(savedState.search).draw();
                        }, false);
                    }

                    Swal.fire({
                        icon: "success",
                        title: "¡Guardado!",
                        text: "Depósito guardado correctamente",
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });

                    var modalEl = document.getElementById('modalDeposito');
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                    else new bootstrap.Modal(modalEl).hide();

                    $('body').removeClass('modal-open');
                    $('.modal-backdrop').remove();
                } else {
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
                console.error("Error AJAX:", error, xhr.responseText);
                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    text: "Error al comunicarse con el servidor",
                    confirmButtonText: "Entendido"
                });
            }
        });
    });

    // ========== PANTALLA COMPLETA ==========
    $('#btnToggleFullscreen').click(function() {
        var modalDialog = $('#modalDeposito .modal-dialog');
        var btnIcon = $(this).find('i');
        if (modalDialog.hasClass('modal-fullscreen')) {
            modalDialog.removeClass('modal-fullscreen');
            btnIcon.removeClass('fa-compress').addClass('fa-expand');
        } else {
            modalDialog.addClass('modal-fullscreen');
            btnIcon.removeClass('fa-expand').addClass('fa-compress');
        }
    });

    // ========== INICIALIZACIÓN ==========
    inicializarDataTable();
    cargarBotonAgregar();

    $('[title]').tooltip({
        trigger: 'hover',
        placement: 'top'
    });
});