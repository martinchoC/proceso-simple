$(document).ready(function () {
    const empresa_idx = 2;
    const pagina_idx = 70;

    var tabla;
    var currentPage = 0;
    var currentOrder = [[1, 'asc']];
    var currentSearch = '';

    // ========== FUNCIONES DE DATATABLE ==========
    function inicializarDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaPuntosVenta')) {
            $('#tablaPuntosVenta').DataTable().destroy();
            $('#tablaPuntosVenta tbody').empty();
        }

        tabla = $('#tablaPuntosVenta').DataTable({
            ajax: {
                url: 'puntos_venta_ajax.php',
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
                    data: 'punto_venta_id',
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
                    data: 'nombre',
                    className: 'text-start',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return data || '';
                        }
                        return `<span class="fw-bold">${data || ''}</span>`;
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
                    data: 'codigo_fiscal',
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return data || '';
                        }
                        return `<span class="fw-medium">${data || ''}</span>`;
                    }
                },
                {
                    data: 'es_web',
                    className: 'text-center',
                    render: function (data, type, row) {
                        var esWeb = parseInt(data) === 1;

                        if (type === 'export') {
                            return esWeb ? 'Sí' : 'No';
                        }

                        if (esWeb) {
                            return '<span class="badge bg-info text-white"><i class="fas fa-globe"></i> Web</span>';
                        }
                        return '<span class="text-muted small">-</span>';
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

                                var nombreInfo = row.nombre || 'Punto #' + row.punto_venta_id;

                                var botonHtml = `<button type="button" class="btn ${claseBoton} btn-accion"
                                                title="${titulo}"
                                                data-id="${row.punto_venta_id}"
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
                }).container().appendTo($('#tablaPuntosVenta_wrapper .col-md-6:eq(1)'));

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
            if (tabla) {
                tabla.button('.buttons-excel').trigger();
            }
        });

        $('#btnExportarPDF').off('click').on('click', function(e) {
            e.preventDefault();
            if (tabla) {
                tabla.button('.buttons-pdf').trigger();
            }
        });

        $('#btnExportarCSV').off('click').on('click', function(e) {
            e.preventDefault();
            if (tabla) {
                tabla.button('.buttons-csv').trigger();
            }
        });

        $('#btnExportarPrint').off('click').on('click', function(e) {
            e.preventDefault();
            if (tabla) {
                tabla.button('.buttons-print').trigger();
            }
        });
    }

    function cargarBotonAgregar() {
        $.get('puntos_venta_ajax.php', {
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
                    '<i class="fas fa-plus me-1"></i>Nuevo Punto de Venta</button>'
                );
            }
        }, 'json');
    }

    // ========== MANEJADOR DE ACCIONES DE BOTONES ==========
    $(document).on('click', '.btn-accion', function () {
        var puntoVentaId = $(this).data('id');
        var accionJs = $(this).data('accion');
        var confirmable = $(this).data('confirmable');
        var nombreInfo = $(this).data('nombre') || 'Punto #' + puntoVentaId;

        if (accionJs === 'editar') {
            cargarPuntoVentaParaEditar(puntoVentaId);
        } else if (confirmable == 1) {
            Swal.fire({
                title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                html: `¿Está seguro de <strong>${accionJs}</strong> el punto de venta<br>
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
                    ejecutarAccion(puntoVentaId, accionJs, nombreInfo);
                }
            });
        } else {
            ejecutarAccion(puntoVentaId, accionJs, nombreInfo);
        }
    });

    // Función para ejecutar cualquier acción del backend
    function ejecutarAccion(puntoVentaId, accionJs, nombreInfo) {
        var savedState = {
            page: tabla.page(),
            order: tabla.order(),
            search: tabla.search()
        };

        $.post('puntos_venta_ajax.php', {
            accion: 'ejecutar_accion',
            punto_venta_id: puntoVentaId,
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

                    Swal.fire({
                        icon: "success",
                        title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                        text: res.message || `Punto "${nombreInfo}" actualizado correctamente`,
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
                    text: res.error || `Error al ${accionJs} el punto de venta`,
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

    // ========== FUNCIONES DE CARGA DE COMBOS ==========
    function cargarCombosFormulario() {
        // Cargar sucursales
        $.get('puntos_venta_ajax.php', {
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
            console.log("Sucursales cargadas:", data);
        }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
            console.error("Error cargando sucursales:", textStatus, errorThrown);
            console.error("Respuesta:", jqXHR.responseText);
        });
    }

    // ========== FUNCIONES DEL MODAL ==========
    function resetModal() {
        $('#formPuntoVenta')[0].reset();
        $('#punto_venta_id').val('');
        $('#es_web').prop('checked', false);
        $('#formPuntoVenta').removeClass('was-validated');
    }

    $(document).on('click', '#btnNuevo', function () {
        resetModal();
        $('#modalLabel').text('Nuevo Punto de Venta');
        cargarCombosFormulario();

        var modal = new bootstrap.Modal(document.getElementById('modalPuntoVenta'));
        modal.show();
    });

    // ========== CARGA DE PUNTO PARA EDITAR ==========
    function cargarPuntoVentaParaEditar(puntoVentaId) {
        $.get('puntos_venta_ajax.php', {
            accion: 'obtener',
            punto_venta_id: puntoVentaId,
            empresa_idx: empresa_idx
        }, function (res) {
            console.log("Punto de venta recibido:", res);

            if (res && res.punto_venta_id) {
                resetModal();

                cargarCombosFormulario();

                $('#punto_venta_id').val(res.punto_venta_id);
                $('#nombre').val(res.nombre || '');
                $('#descripcion').val(res.descripcion || '');
                $('#codigo_fiscal').val(res.codigo_fiscal || '');
                $('#es_web').prop('checked', parseInt(res.es_web) === 1);

                $('#modalLabel').text('Editar Punto de Venta');

                // Asignar valores después de que los combos se hayan cargado
                setTimeout(function() {
                    if (res.sucursal_id) {
                        console.log("Asignando sucursal_id:", res.sucursal_id);
                        $('#sucursal_id').val(res.sucursal_id);
                    }
                }, 500);

                var modal = new bootstrap.Modal(document.getElementById('modalPuntoVenta'));
                modal.show();
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos del punto de venta",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    }

    // ========== GUARDAR PUNTO DE VENTA ==========
    $('#btnGuardar').click(function() {
        var form = document.getElementById('formPuntoVenta');

        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }

        var id = $('#punto_venta_id').val();
        var accionBackend = id ? 'editar' : 'agregar';

        var btnGuardar = $(this);
        var originalText = btnGuardar.html();
        btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        // Crear FormData manualmente
        var formData = new FormData();
        formData.append('accion', accionBackend);
        formData.append('empresa_idx', empresa_idx);
        formData.append('pagina_idx', pagina_idx);
        formData.append('punto_venta_id', $('#punto_venta_id').val() || '');
        formData.append('sucursal_id', $('#sucursal_id').val() || '');
        formData.append('nombre', $('#nombre').val() || '');
        formData.append('descripcion', $('#descripcion').val() || '');
        formData.append('codigo_fiscal', $('#codigo_fiscal').val() || '');
        formData.append('es_web', $('#es_web').is(':checked') ? 1 : 0);

        // Log para depuración
        console.log("=== DATOS ENVIADOS ===");
        for (var pair of formData.entries()) {
            console.log(pair[0] + ': "' + pair[1] + '"');
        }

        var savedState = {
            page: tabla ? tabla.page() : 0,
            order: tabla ? tabla.order() : [[1, 'asc']],
            search: tabla ? tabla.search() : ''
        };

        $.ajax({
            url: 'puntos_venta_ajax.php',
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
                            if (savedState.page !== undefined) {
                                tabla.page(savedState.page).draw('page');
                            }
                            if (savedState.search && savedState.search !== '') {
                                tabla.search(savedState.search).draw();
                            }
                        }, false);
                    }

                    Swal.fire({
                        icon: "success",
                        title: "¡Guardado!",
                        text: "Punto de venta guardado correctamente",
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });

                    // Cerrar modal
                    var modalEl = document.getElementById('modalPuntoVenta');
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) {
                        modal.hide();
                    } else {
                        modal = new bootstrap.Modal(modalEl);
                        modal.hide();
                    }

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

                console.error("Error AJAX:", error);
                console.error("Respuesta:", xhr.responseText);

                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    text: "Error al comunicarse con el servidor",
                    confirmButtonText: "Entendido"
                });
            }
        });
    });

    // ========== FUNCIONES DE PANTALLA COMPLETA ==========
    $('#btnToggleFullscreen').click(function() {
        var modalDialog = $('#modalPuntoVenta .modal-dialog');
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