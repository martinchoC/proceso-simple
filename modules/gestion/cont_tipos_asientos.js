$(document).ready(function () {
    const empresa_id = 2;
    const pagina_id = 87;
    
    var tabla;
    var currentPage = 0;
    var currentOrder = [[1, 'asc']];
    var currentSearch = '';

    function inicializarDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaTiposAsientos')) {
            $('#tablaTiposAsientos').DataTable().destroy();
            $('#tablaTiposAsientos tbody').empty();
        }

        tabla = $('#tablaTiposAsientos').DataTable({
            ajax: {
                url: 'cont_tipos_asientos_ajax.php',
                type: 'GET',
                data: {
                    accion: 'listar',
                    empresa_id: empresa_id,
                    pagina_id: pagina_id
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
            dom: '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>' +
                '<"clear">',
            pageLength: 50,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            
            columns: [
                {
                    data: 'cont_tipo_asiento_id',
                    className: 'text-center'
                },
                {
                    data: 'codigo',
                    className: 'text-center',
                    render: function(data, type, row) {
                        return type === 'export' ? (data || '') : `<span class="badge bg-secondary">${data || ''}</span>`;
                    }
                },
                {
                    data: 'cont_tipo_asiento',
                    render: function(data, type, row) {
                        return type === 'export' ? (data || '') : `<strong>${data || ''}</strong>`;
                    }
                },
                {
                    data: 'descripcion',
                    render: function(data, type, row) {
                        return type === 'export' ? (data || '') : `<span>${data || '-'}</span>`;
                    }
                },
                {
                    data: 'origen',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (type === 'export') return data || '';
                        var badgeClass = data === 'automatico' ? 'bg-info' : 'bg-secondary';
                        return `<span class="badge ${badgeClass}">${data || 'manual'}</span>`;
                    }
                },
                {
                    data: 'modulo_origen',
                    className: 'text-center',
                    render: function(data, type, row) {
                        return type === 'export' ? (data || '') : `<span class="small">${data || '-'}</span>`;
                    }
                },
                {
                    data: 'estado_info',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (type === 'export') {
                            return (data && data.estado_registro) ? data.estado_registro : '';
                        }
                        var estadoTexto = (data && data.estado_registro) ? data.estado_registro : '';
                        var badgeClass = '';
                        if (estadoTexto === 'Activo') badgeClass = 'bg-success';
                        else if (estadoTexto === 'Inactivo') badgeClass = 'bg-danger';
                        else badgeClass = 'bg-secondary';
                        return `<span class="badge ${badgeClass}">${estadoTexto}</span>`;
                    }
                },
                {
                    data: 'botones',
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    width: '200px',
                    render: function(data, type, row) {
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
                                var tipoInfo = `Tipo #${row.cont_tipo_asiento_id} - ${row.cont_tipo_asiento || ''}`;

                                var botonHtml = `<button type="button" class="btn ${claseBoton} btn-accion" 
                                                title="${titulo}" 
                                                data-id="${row.cont_tipo_asiento_id}" 
                                                data-accion="${accionJs}"
                                                data-confirmable="${esConfirmable}"
                                                data-comprobante="${tipoInfo}">
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
                if (data.estado_info && data.estado_info.codigo_estandar === 'ACTIVO') {
                    $(row).addClass('table-success');
                } else if (data.estado_info && data.estado_info.codigo_estandar === 'INACTIVO') {
                    $(row).addClass('table-danger');
                }
            },
            initComplete: function () {
                this.api().columns().every(function () {
                    var column = this;
                    var header = $(column.header());
                    var input = $('<input type="text" class="column-filter" placeholder="Filtrar...">')
                        .appendTo(header)
                        .on('keyup change', function () {
                            if (column.search() !== this.value) {
                                column.search(this.value).draw();
                            }
                        });
                });

                setTimeout(function() {
                    var lengthControl = $('#tablaTiposAsientos_length').detach();
                    $('#tablaTiposAsientos_length').replaceWith(lengthControl);
                    var filterControl = $('#tablaTiposAsientos_filter').detach();
                    $('#tablaTiposAsientos_filter').replaceWith(filterControl);
                    
                    $('#tablaTiposAsientos_length').addClass('dataTables_length_custom');
                    $('#tablaTiposAsientos_filter').addClass('dataTables_filter_custom');
                    
                    if ($('#tablaTiposAsientos_length').html().trim() === '') {
                        var selectHtml = '<label>Mostrar <select name="tablaTiposAsientos_length" aria-controls="tablaTiposAsientos" class="form-select form-select-sm"><option value="10">10</option><option value="25">25</option><option value="50" selected="">50</option><option value="100">100</option><option value="-1">Todos</option></select> registros</label>';
                        $('#tablaTiposAsientos_length').html(selectHtml);
                        $('#tablaTiposAsientos_length select').on('change', function() {
                            tabla.page.len($(this).val()).draw();
                        });
                    }
                    
                    if ($('#tablaTiposAsientos_filter').html().trim() === '') {
                        var filterHtml = '<label>Buscar:<input type="search" class="form-control form-control-sm" placeholder="" aria-controls="tablaTiposAsientos"></label>';
                        $('#tablaTiposAsientos_filter').html(filterHtml);
                        $('#tablaTiposAsientos_filter input').on('keyup', function() {
                            tabla.search($(this).val()).draw();
                        });
                    }
                }, 100);

                var buttons = new $.fn.dataTable.Buttons(tabla, {
                    buttons: ['excelHtml5', 'pdfHtml5', 'csvHtml5', 'print']
                }).container().appendTo($('#tablaTiposAsientos_wrapper .col-md-6:eq(1)'));

                $(tabla.table().container()).on('page.dt', function (e) {
                    currentPage = tabla.page();
                });
                $(tabla.table().container()).on('order.dt', function (e, settings, details) {
                    currentOrder = tabla.order();
                });
                $(tabla.table().container()).on('search.dt', function (e, settings) {
                    currentSearch = tabla.search();
                });
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

        $('#btnExportarExcel').on('click', function() { tabla.button('.buttons-excel').trigger(); });
        $('#btnExportarPDF').on('click', function() { tabla.button('.buttons-pdf').trigger(); });
        $('#btnExportarCSV').on('click', function() { tabla.button('.buttons-csv').trigger(); });
        $('#btnExportarPrint').on('click', function() { tabla.button('.buttons-print').trigger(); });
    }

    function cargarBotonAgregar() {
        $.get('cont_tipos_asientos_ajax.php', {
            accion: 'obtener_boton_agregar',
            pagina_id: pagina_id
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
                    '<i class="fas fa-plus me-1"></i>Nuevo Tipo</button>'
                );
            }
        }, 'json');
    }

    $(document).on('click', '.btn-accion', function () {
        var id = $(this).data('id');
        var accionJs = $(this).data('accion');
        var confirmable = $(this).data('confirmable');
        var tipoInfo = $(this).data('comprobante') || 'Tipo #' + id;

        if (accionJs === 'editar') {
            cargarTipoParaEditar(id);
        } else if (accionJs === 'visualizar') {
            cargarTipoParaVisualizar(id);
        } else if (confirmable == 1) {
            Swal.fire({
                title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                html: `¿Está seguro de <strong>${accionJs}</strong> el tipo de asiento?<br><strong>${tipoInfo}</strong>`,
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
                    ejecutarAccion(id, accionJs, tipoInfo);
                }
            });
        } else {
            ejecutarAccion(id, accionJs, tipoInfo);
        }
    });

    function ejecutarAccion(id, accionJs, tipoInfo) {
        var savedState = {
            page: tabla.page(),
            order: tabla.order(),
            search: tabla.search()
        };

        $.post('cont_tipos_asientos_ajax.php', {
            accion: 'ejecutar_accion',
            cont_tipo_asiento_id: id,
            accion_js: accionJs,
            empresa_id: empresa_id,
            pagina_id: pagina_id
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
                        text: res.message || `Tipo actualizado correctamente`,
                        showConfirmButton: false,
                        timer: 2000,
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

    function resetModal() {
        $('#formTipoAsiento')[0].reset();
        $('#cont_tipo_asiento_id').val('');
        $('#formTipoAsiento').removeClass('was-validated');
        $('#origen').val('manual');
        $('#estado_select').val('activo');
    }

    $(document).on('click', '#btnNuevo', function () {
        resetModal();
        $('#modalLabel').text('Nuevo Tipo de Asiento Contable');
        var modal = new bootstrap.Modal(document.getElementById('modalTipoAsiento'));
        modal.show();
    });

    function cargarTipoParaEditar(id) {
        $.get('cont_tipos_asientos_ajax.php', {
            accion: 'obtener',
            cont_tipo_asiento_id: id,
            empresa_id: empresa_id
        }, function (res) {
            if (res && res.cont_tipo_asiento_id) {
                resetModal();
                
                $('#cont_tipo_asiento_id').val(res.cont_tipo_asiento_id);
                $('#codigo').val(res.codigo);
                $('#cont_tipo_asiento').val(res.cont_tipo_asiento);
                $('#descripcion').val(res.descripcion);
                $('#origen').val(res.origen || 'manual');
                $('#modulo_origen').val(res.modulo_origen || '');
                $('#estado_select').val(res.estado_actual || 'activo');
                $('#modalLabel').text('Editar Tipo de Asiento Contable');

                var modal = new bootstrap.Modal(document.getElementById('modalTipoAsiento'));
                modal.show();
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos del tipo de asiento",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    }

    function cargarTipoParaVisualizar(id) {
        $.get('cont_tipos_asientos_ajax.php', {
            accion: 'obtener',
            cont_tipo_asiento_id: id,
            empresa_id: empresa_id
        }, function (res) {
            if (res && res.cont_tipo_asiento_id) {
                resetModal();
                
                $('#cont_tipo_asiento_id').val(res.cont_tipo_asiento_id);
                $('#codigo').val(res.codigo);
                $('#cont_tipo_asiento').val(res.cont_tipo_asiento);
                $('#descripcion').val(res.descripcion);
                $('#origen').val(res.origen || 'manual');
                $('#modulo_origen').val(res.modulo_origen || '');
                $('#estado_select').val(res.estado_actual || 'activo');
                $('#modalLabel').text('Visualizar Tipo de Asiento Contable');

                $('#formTipoAsiento :input').prop('disabled', true);
                $('#btnGuardar').hide();
                $('.modal-footer .btn-secondary').hide();
                $('.btn-secondary[data-bs-dismiss="modal"]').hide();
                $('#btnToggleFullscreen').prop('disabled', false);

                var modal = new bootstrap.Modal(document.getElementById('modalTipoAsiento'));
                modal.show();

                $('#modalTipoAsiento').off('hidden.bs.modal').on('hidden.bs.modal', function () {
                    $('#formTipoAsiento :input').prop('disabled', false);
                    $('#btnGuardar').show();
                    $('.modal-footer .btn-secondary').show();
                    $('.btn-secondary[data-bs-dismiss="modal"]').show();
                });
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos del tipo de asiento",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    }

    $('#btnGuardar').click(function() {
        var form = document.getElementById('formTipoAsiento');
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        
        var id = $('#cont_tipo_asiento_id').val();
        var accionBackend = id ? 'editar' : 'agregar';
        
        var btnGuardar = $(this);
        var originalText = btnGuardar.html();
        btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
        
        var formData = new FormData();
        formData.append('accion', accionBackend);
        formData.append('empresa_id', empresa_id);
        formData.append('pagina_id', pagina_id);
        formData.append('cont_tipo_asiento_id', $('#cont_tipo_asiento_id').val() || '');
        formData.append('codigo', $('#codigo').val() || '');
        formData.append('cont_tipo_asiento', $('#cont_tipo_asiento').val() || '');
        formData.append('descripcion', $('#descripcion').val() || '');
        formData.append('origen', $('#origen').val() || 'manual');
        formData.append('modulo_origen', $('#modulo_origen').val() || '');
        formData.append('estado', $('#estado_select').val() || 'activo');

        var savedState = {
            page: tabla ? tabla.page() : 0,
            order: tabla ? tabla.order() : [[1, 'asc']],
            search: tabla ? tabla.search() : ''
        };
        
        $.ajax({
            url: 'cont_tipos_asientos_ajax.php',
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
                        text: "Tipo de asiento guardado correctamente",
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
                    
                    var modalEl = document.getElementById('modalTipoAsiento');
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

    $('#btnToggleFullscreen').click(function() {
        var modalDialog = $('#modalTipoAsiento .modal-dialog');
        var btnIcon = $(this).find('i');
        
        if (modalDialog.hasClass('modal-fullscreen')) {
            modalDialog.removeClass('modal-fullscreen');
            btnIcon.removeClass('fa-compress').addClass('fa-expand');
        } else {
            modalDialog.addClass('modal-fullscreen');
            btnIcon.removeClass('fa-expand').addClass('fa-compress');
        }
    });

    inicializarDataTable();
    cargarBotonAgregar();

    $('[title]').tooltip({
        trigger: 'hover',
        placement: 'top'
    });
});