$(document).ready(function () {
    const empresa_idx = window.EMPRESA_ID; // Empresa activa
    const pagina_idx = (typeof window.PAGINA_ID !== 'undefined' && window.PAGINA_ID) ? window.PAGINA_ID : 120;

    let tabla;
    let currentPage = 0;
    let currentOrder = [[10, 'asc'], [2, 'asc']]; // Orden por defecto: orden asc, medio_pago asc
    let currentSearch = '';
    let maestros = {
        tesoreria_cuentas: [],
        cont_cuentas: [],
        impuestos_tipos: [],
        tipos_medio: [],
        cuentas_tipo: []
    };

    // ========== CARGA DE MAESTROS ==========
    function cargarMaestros(callback) {
        $.getJSON('medios_pago_ajax.php', {
            accion: 'obtener_maestros',
            empresa_idx: empresa_idx
        }, function (res) {
            maestros = res || {};
            poblarCombosMaestros();
            if (typeof callback === 'function') callback();
        }).fail(function () {
            console.error('Error al cargar maestros para medios de pago');
        });
    }

    function poblarCombosMaestros() {
        // Tipos de medio
        const $selTipo = $('#medio_pago_tipo');
        $selTipo.empty().append('<option value="">-- Seleccionar Tipo --</option>');
        (maestros.tipos_medio || []).forEach(tm => {
            $selTipo.append(`<option value="${tm.codigo}" data-defecto="${tm.cuenta_defecto || ''}">${tm.nombre}</option>`);
        });

        // Tipos de cuenta de tesorería
        const $selCtaTipo = $('#tesoreria_cuenta_tipo');
        $selCtaTipo.empty().append('<option value="">-- Ninguno / Sin cuenta fija --</option>');
        (maestros.cuentas_tipo || []).forEach(ct => {
            $selCtaTipo.append(`<option value="${ct.codigo}">${ct.nombre}</option>`);
        });

        // Cuentas de Tesorería
        actualizarComboCuentasTesoreria('');

        // Cuentas Contables
        const $selCC = $('#cont_cuenta_id');
        $selCC.empty().append('<option value="">-- Ninguna / Asignar después --</option>');
        (maestros.cont_cuentas || []).forEach(cc => {
            $selCC.append(`<option value="${cc.cont_cuenta_id}">${cc.codigo} - ${cc.nombre}</option>`);
        });

        // Impuestos / Retenciones
        const $selImp = $('#impuesto_tipo_id');
        $selImp.empty().append('<option value="">-- No aplica / Ninguno --</option>');
        (maestros.impuestos_tipos || []).forEach(it => {
            const badge = it.es_retencion == 1 ? ' [Retención]' : '';
            $selImp.append(`<option value="${it.impuesto_tipo_id}">${it.impuesto_tipo}${badge}</option>`);
        });
    }

    function actualizarComboCuentasTesoreria(tipoFiltro, valorSeleccionado) {
        const $selTC = $('#tesoreria_cuenta_id');
        $selTC.empty().append('<option value="">-- Seleccionar en la operación --</option>');
        (maestros.tesoreria_cuentas || []).forEach(tc => {
            if (!tipoFiltro || tc.cuenta_tipo === tipoFiltro) {
                $selTC.append(`<option value="${tc.tesoreria_cuenta_id}">[${tc.cuenta_tipo}] ${tc.tesoreria_cuenta}</option>`);
            }
        });
        if (valorSeleccionado) {
            $selTC.val(valorSeleccionado);
        }
    }

    // Auto-ajustes inteligentes al cambiar tipo de medio
    $('#medio_pago_tipo').on('change', function () {
        const tipo = $(this).val();
        const selectedOpt = $(this).find('option:selected');
        const defectoCta = selectedOpt.data('defecto');

        if (defectoCta) {
            $('#tesoreria_cuenta_tipo').val(defectoCta).trigger('change');
        }

        // Lógica según tipo
        if (tipo === 'RETENCION') {
            $('#seccion_impuesto').slideDown(150);
            $('#aplica_cobro').prop('checked', true);
            $('#aplica_pago').prop('checked', false);
            $('#requiere_detalle').prop('checked', true);
            $('#tesoreria_cuenta_tipo').val('').trigger('change');
        } else {
            $('#seccion_impuesto').slideUp(150);
            $('#impuesto_tipo_id').val('');
            if (tipo === 'EFECTIVO') {
                $('#aplica_cobro').prop('checked', true);
                $('#aplica_pago').prop('checked', true);
                $('#requiere_detalle').prop('checked', false);
            } else if (tipo === 'CHEQUE_PROPIO') {
                $('#aplica_cobro').prop('checked', false);
                $('#aplica_pago').prop('checked', true);
                $('#requiere_detalle').prop('checked', true);
            } else if (tipo === 'CHEQUE_TERCERO') {
                $('#aplica_cobro').prop('checked', true);
                $('#aplica_pago').prop('checked', true);
                $('#requiere_detalle').prop('checked', true);
            } else if (tipo === 'TRANSFERENCIA' || tipo === 'DEPOSITO') {
                $('#aplica_cobro').prop('checked', true);
                $('#aplica_pago').prop('checked', true);
                $('#requiere_detalle').prop('checked', true);
            }
        }
    });

    $('#tesoreria_cuenta_tipo').on('change', function () {
        const tipo = $(this).val();
        actualizarComboCuentasTesoreria(tipo);
    });

    // ========== RENDERERS DE TABLA ==========
    function badgeTipo(tipo) {
        let bg = 'bg-secondary';
        let icono = 'fa-tag';
        switch (tipo) {
            case 'EFECTIVO':
                bg = 'bg-success';
                icono = 'fa-money-bill-wave';
                break;
            case 'CHEQUE_TERCERO':
                bg = 'bg-primary';
                icono = 'fa-money-check';
                break;
            case 'CHEQUE_PROPIO':
                bg = 'bg-indigo';
                icono = 'fa-money-check-alt';
                break;
            case 'TRANSFERENCIA':
                bg = 'bg-info text-dark';
                icono = 'fa-university';
                break;
            case 'DEPOSITO':
                bg = 'bg-teal';
                icono = 'fa-piggy-bank';
                break;
            case 'TARJETA_DEBITO':
            case 'TARJETA_CREDITO':
                bg = 'bg-warning text-dark';
                icono = 'fa-credit-card';
                break;
            case 'RETENCION':
                bg = 'bg-danger';
                icono = 'fa-file-invoice-dollar';
                break;
            default:
                bg = 'bg-secondary';
                icono = 'fa-receipt';
                break;
        }
        return `<span class="badge ${bg}"><i class="fas ${icono} me-1"></i>${tipo || 'N/A'}</span>`;
    }

    // ========== INICIALIZACIÓN DE DATATABLE ==========
    function inicializarDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaMediosPago')) {
            $('#tablaMediosPago').DataTable().destroy();
            $('#tablaMediosPago tbody').empty();
        }

        tabla = $('#tablaMediosPago').DataTable({
            ajax: {
                url: 'medios_pago_ajax.php',
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
                data.search = (currentSearch !== '-1' && currentSearch !== '') ? { search: currentSearch } : { search: '' };
                delete data.columns;
                return data;
            },
            stateLoadParams: function (settings, data) {
                if (data.page !== undefined) currentPage = data.page;
                if (data.order !== undefined && data.order.length > 0) currentOrder = data.order;
                currentSearch = (data.search && data.search.search && data.search.search !== '-1') ? data.search.search : '';
                data.search = { search: currentSearch };
            },
            dom: '<"row align-items-center mb-2"<"col-12 col-md-6"><"col-12 col-md-6 text-end"f>>' +
                '<"row"<"col-sm-12"tr>>' +
                '<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>' +
                '<"clear">',
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            columns: [
                {
                    data: 'medio_pago_id',
                    className: 'text-center fw-bold text-muted',
                    width: '60px'
                },
                {
                    data: 'codigo',
                    className: 'text-center',
                    render: function (data, type) {
                        if (type === 'export') return data || '';
                        return `<span class="badge bg-light text-dark border font-monospace px-2 py-1">${data || ''}</span>`;
                    }
                },
                {
                    data: 'medio_pago',
                    className: 'text-start fw-bold',
                    render: function (data, type, row) {
                        if (type === 'export') return data || '';
                        return `<span class="text-primary">${data || ''}</span>`;
                    }
                },
                {
                    data: 'medio_pago_tipo',
                    className: 'text-center',
                    render: function (data, type) {
                        if (type === 'export') return data || '';
                        return badgeTipo(data);
                    }
                },
                {
                    data: 'tesoreria_cuenta_tipo',
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            const cta = row.tesoreria_cuenta ? ` (${row.tesoreria_cuenta})` : '';
                            return (data || 'Sin cuenta') + cta;
                        }
                        if (!data) return '<span class="text-muted small">--</span>';
                        let ctaTexto = row.tesoreria_cuenta ? `<br><small class="text-secondary">${row.tesoreria_cuenta}</small>` : '';
                        return `<span class="badge bg-secondary-subtle text-secondary-emphasis border">${data}</span>${ctaTexto}`;
                    }
                },
                {
                    data: 'cont_cuenta_codigo',
                    className: 'text-start',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return data ? `${data} - ${row.cont_cuenta_nombre || ''}` : '';
                        }
                        if (!data) return '<span class="text-muted small">--</span>';
                        return `<div><strong class="font-monospace text-dark">${data}</strong><br><small class="text-muted">${row.cont_cuenta_nombre || ''}</small></div>`;
                    }
                },
                {
                    data: 'aplica_cobro',
                    className: 'text-center',
                    width: '70px',
                    render: function (data, type) {
                        const si = (data == 1);
                        if (type === 'export') return si ? 'Sí' : 'No';
                        return si ? '<span class="badge bg-success" title="Disponible en Cobranzas"><i class="fas fa-check"></i> Cobro</span>'
                                  : '<span class="badge bg-light text-muted border">No</span>';
                    }
                },
                {
                    data: 'aplica_pago',
                    className: 'text-center',
                    width: '70px',
                    render: function (data, type) {
                        const si = (data == 1);
                        if (type === 'export') return si ? 'Sí' : 'No';
                        return si ? '<span class="badge bg-primary" title="Disponible en Órdenes de Pago"><i class="fas fa-check"></i> Pago</span>'
                                  : '<span class="badge bg-light text-muted border">No</span>';
                    }
                },
                {
                    data: 'requiere_detalle',
                    className: 'text-center',
                    width: '80px',
                    render: function (data, type) {
                        const si = (data == 1);
                        if (type === 'export') return si ? 'Sí' : 'No';
                        return si ? '<span class="badge bg-info text-dark" title="Exige registrar referencia o nro de comprobante">Obligatorio</span>'
                                  : '<span class="badge bg-light text-muted border">Opcional</span>';
                    }
                },
                {
                    data: 'impuesto_tipo',
                    className: 'text-start',
                    render: function (data, type) {
                        if (type === 'export') return data || '';
                        return data ? `<span class="badge bg-danger-subtle text-danger border">${data}</span>`
                                    : '<span class="text-muted small">--</span>';
                    }
                },
                {
                    data: 'orden',
                    className: 'text-center fw-bold',
                    width: '50px'
                },
                {
                    data: 'estado_info',
                    className: 'text-center',
                    width: '90px',
                    render: function (data, type) {
                        if (!data || !data.estado_registro) {
                            return type === 'export' ? 'Sin estado' : '<span class="badge bg-secondary">Sin estado</span>';
                        }
                        if (type === 'export') return data.estado_registro;
                        const bg = data.bg_clase || 'bg-secondary';
                        const txt = data.text_clase || 'text-white';
                        return `<span class="badge ${bg} ${txt}">${data.estado_registro}</span>`;
                    }
                },
                {
                    data: 'botones',
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    width: '120px',
                    render: function (data, type, row) {
                        if (!data || data.length === 0) {
                            return '<span class="text-muted small">Sin acciones</span>';
                        }
                        let html = '';
                        data.forEach(b => {
                            let clase = 'btn-sm me-1 ';
                            if (b.bg_clase && b.text_clase) {
                                clase += b.bg_clase + ' ' + b.text_clase;
                            } else if (b.color_clase) {
                                clase += b.color_clase;
                            } else {
                                clase += 'btn-outline-primary';
                            }
                            const icono = b.icono_clase ? `<i class="${b.icono_clase}"></i>` : '';
                            html += `<button type="button" class="btn ${clase} btn-accion" 
                                        title="${b.descripcion || b.nombre_funcion}" 
                                        data-id="${row.medio_pago_id}" 
                                        data-accion="${b.accion_js}"
                                        data-confirmable="${b.es_confirmable || 0}"
                                        data-nombre="${row.medio_pago} (${row.codigo})">
                                        ${icono}
                                    </button>`;
                        });
                        return `<div class="btn-group" role="group">${html}</div>`;
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json'
            },
            order: currentOrder,
            responsive: true,
            createdRow: function (row, data) {
                if (data.estado_info && data.estado_info.codigo_estandar === 'INACTIVO') {
                    $(row).addClass('table-secondary text-muted');
                }
            },
            initComplete: function () {
                new $.fn.dataTable.Buttons(tabla, {
                    buttons: ['excelHtml5', 'pdfHtml5', 'csvHtml5', 'print']
                }).container().appendTo($('#tablaMediosPago_wrapper .col-md-6:eq(1)'));

                $(tabla.table().container()).on('page.dt', function () {
                    currentPage = tabla.page();
                });
                $(tabla.table().container()).on('order.dt', function () {
                    currentOrder = tabla.order();
                });
                $(tabla.table().container()).on('search.dt', function () {
                    currentSearch = tabla.search();
                });

                inicializarBotonesExportacion();
            }
        });

        inicializarEventos();
    }

    // ========== BOTÓN AGREGAR DINÁMICO ==========
    function cargarBotonAgregar() {
        $.getJSON('medios_pago_ajax.php', {
            accion: 'obtener_boton_agregar',
            pagina_idx: pagina_idx
        }, function (btn) {
            if (btn && btn.nombre_funcion) {
                const icono = btn.icono_clase ? `<i class="${btn.icono_clase} me-1"></i>` : '<i class="fas fa-plus me-1"></i>';
                let colorClase = 'btn-primary';
                if (btn.bg_clase && btn.text_clase) {
                    colorClase = btn.bg_clase + ' ' + btn.text_clase;
                } else if (btn.color_clase) {
                    colorClase = btn.color_clase;
                }
                $('#contenedor-boton-agregar').html(
                    `<button type="button" class="btn ${colorClase}" id="btnNuevo">
                        ${icono}${btn.nombre_funcion}
                    </button>`
                );
            } else {
                $('#contenedor-boton-agregar').html(
                    '<button type="button" class="btn btn-primary" id="btnNuevo"><i class="fas fa-plus me-1"></i>Nuevo Medio de Pago</button>'
                );
            }
        });
    }

    // ========== EVENTOS Y EXPORTACIÓN ==========
    function inicializarEventos() {
        $('#btnRecargar').off('click').on('click', function () {
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            tabla.ajax.reload(function () {
                btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
            }, false);
        });

        // Botón Nuevo
        $(document).on('click', '#btnNuevo', function () {
            abrirModalNuevo();
        });
    }

    function inicializarBotonesExportacion() {
        $('#btnExportarExcel').off('click').on('click', function (e) {
            e.preventDefault();
            if (tabla) tabla.button('.buttons-excel').trigger();
        });
        $('#btnExportarPDF').off('click').on('click', function (e) {
            e.preventDefault();
            if (tabla) tabla.button('.buttons-pdf').trigger();
        });
        $('#btnExportarCSV').off('click').on('click', function (e) {
            e.preventDefault();
            if (tabla) tabla.button('.buttons-csv').trigger();
        });
        $('#btnExportarPrint').off('click').on('click', function (e) {
            e.preventDefault();
            if (tabla) tabla.button('.buttons-print').trigger();
        });
    }

    // ========== ACCIONES DE FILA ==========
    $(document).on('click', '.btn-accion', function () {
        const id = $(this).data('id');
        const accionJs = $(this).data('accion');
        const confirmable = $(this).data('confirmable');
        const nombre = $(this).data('nombre') || 'Medio #' + id;

        if (accionJs === 'editar') {
            abrirModalEditar(id);
        } else if (accionJs === 'visualizar') {
            abrirModalVisualizar(id);
        } else if (confirmable == 1) {
            const textoAccion = accionJs.charAt(0).toUpperCase() + accionJs.slice(1);
            Swal.fire({
                title: `¿${textoAccion}?`,
                html: `¿Está seguro de <strong>${accionJs}</strong> el medio de pago<br><strong>${nombre}</strong>?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: accionJs === 'inactivar' ? '#d33' : '#3085d6',
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Sí, ${accionJs}`,
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    ejecutarTransicion(id, accionJs, nombre);
                }
            });
        } else {
            ejecutarTransicion(id, accionJs, nombre);
        }
    });

    function ejecutarTransicion(id, accionJs, nombre) {
        $.post('medios_pago_ajax.php', {
            accion: 'ejecutar_accion',
            id: id,
            accion_js: accionJs,
            empresa_idx: empresa_idx,
            pagina_idx: pagina_idx
        }, function (res) {
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Operación Exitosa',
                    text: res.message || 'Estado actualizado',
                    timer: 2000,
                    showConfirmButton: false
                });
                tabla.ajax.reload(null, false);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: res.error || 'No se pudo realizar la acción'
                });
            }
        }, 'json').fail(function () {
            Swal.fire({
                icon: 'error',
                title: 'Error de Red',
                text: 'No se pudo conectar con el servidor'
            });
        });
    }

    // ========== MODAL FORMULARIO (NUEVO / EDITAR) ==========
    function limpiarFormulario() {
        const $f = $('#formMedioPago');
        $f[0].reset();
        $f.removeClass('was-validated');
        $('#medio_pago_id').val('');
        $('#tesoreria_cuenta_tipo').val('').trigger('change');
        $('#cont_cuenta_id').val('');
        $('#impuesto_tipo_id').val('');
        $('#seccion_impuesto').hide();
        $('#aplica_cobro').prop('checked', true);
        $('#aplica_pago').prop('checked', true);
        $('#requiere_detalle').prop('checked', false);
        $('#orden').val(1);
    }

    function abrirModalNuevo() {
        limpiarFormulario();
        $('#modalLabel').html('<i class="fas fa-plus me-2 text-primary"></i>Nuevo Medio de Pago');
        $('#btnGuardarMedioPago').text('Crear Medio de Pago');
        $('#codigo').prop('readonly', false);
        $('#modalMedioPago').modal('show');
    }

    function abrirModalEditar(id) {
        limpiarFormulario();
        $('#modalLabel').html('<i class="fas fa-edit me-2 text-primary"></i>Editar Medio de Pago');
        $('#btnGuardarMedioPago').text('Guardar Cambios');

        $.getJSON('medios_pago_ajax.php', {
            accion: 'obtener_por_id',
            id: id,
            empresa_idx: empresa_idx
        }, function (d) {
            $('#medio_pago_id').val(d.medio_pago_id);
            $('#codigo').val(d.codigo);
            $('#medio_pago').val(d.medio_pago);
            $('#medio_pago_tipo').val(d.medio_pago_tipo);

            $('#tesoreria_cuenta_tipo').val(d.tesoreria_cuenta_tipo || '');
            actualizarComboCuentasTesoreria(d.tesoreria_cuenta_tipo || '', d.tesoreria_cuenta_id);

            $('#cont_cuenta_id').val(d.cont_cuenta_id || '');
            $('#impuesto_tipo_id').val(d.impuesto_tipo_id || '');
            if (d.medio_pago_tipo === 'RETENCION') {
                $('#seccion_impuesto').show();
            } else {
                $('#seccion_impuesto').hide();
            }

            $('#aplica_cobro').prop('checked', d.aplica_cobro == 1);
            $('#aplica_pago').prop('checked', d.aplica_pago == 1);
            $('#requiere_detalle').prop('checked', d.requiere_detalle == 1);
            $('#orden').val(d.orden || 1);

            $('#modalMedioPago').modal('show');
        }).fail(function () {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo cargar la información del medio de pago'
            });
        });
    }

    function abrirModalVisualizar(id) {
        $.getJSON('medios_pago_ajax.php', {
            accion: 'obtener_por_id',
            id: id,
            empresa_idx: empresa_idx
        }, function (d) {
            $('#ver_id').text(d.medio_pago_id);
            $('#ver_codigo').text(d.codigo);
            $('#ver_nombre').text(d.medio_pago);
            $('#ver_tipo').html(badgeTipo(d.medio_pago_tipo));
            $('#ver_cta_tipo').text(d.tesoreria_cuenta_tipo || 'Ninguno');
            $('#ver_tesoreria_cta').text(d.tesoreria_cuenta || 'No asignada específicamente');
            $('#ver_cont_cta').text(d.cont_cuenta_codigo ? `${d.cont_cuenta_codigo} - ${d.cont_cuenta_nombre}` : 'No asignada');
            $('#ver_impuesto').text(d.impuesto_tipo || 'No aplica');
            $('#ver_cobro').html(d.aplica_cobro == 1 ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>');
            $('#ver_pago').html(d.aplica_pago == 1 ? '<span class="badge bg-primary">Sí</span>' : '<span class="badge bg-secondary">No</span>');
            $('#ver_detalle').html(d.requiere_detalle == 1 ? '<span class="badge bg-info text-dark">Obligatorio</span>' : '<span class="badge bg-secondary">Opcional</span>');
            $('#ver_orden').text(d.orden);
            $('#ver_fecha_creacion').text(d.fecha_creacion || 'N/A');
            $('#ver_fecha_modificacion').text(d.fecha_modificacion || 'N/A');

            $('#modalVerMedioPago').modal('show');
        });
    }

    // Submit del formulario
    $('#formMedioPago').on('submit', function (e) {
        e.preventDefault();
        const form = this;

        if (!form.checkValidity()) {
            e.stopPropagation();
            $(form).addClass('was-validated');
            return;
        }

        const $btn = $('#btnGuardarMedioPago');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        const formData = $(form).serializeArray();
        formData.push({ name: 'accion', value: 'guardar' });
        formData.push({ name: 'empresa_idx', value: empresa_idx });

        $.post('medios_pago_ajax.php', formData, function (res) {
            $btn.prop('disabled', false).text('Guardar');
            if (res.success) {
                $('#modalMedioPago').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: '¡Guardado!',
                    text: res.message || 'Medio de pago guardado exitosamente',
                    timer: 2000,
                    showConfirmButton: false
                });
                tabla.ajax.reload(null, false);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'No se pudo guardar',
                    text: res.error || 'Ocurrió un error al procesar la solicitud'
                });
            }
        }, 'json').fail(function (xhr) {
            $btn.prop('disabled', false).text('Guardar');
            let msg = 'Error interno del servidor';
            try {
                const j = JSON.parse(xhr.responseText);
                if (j.error) msg = j.error;
            } catch (err) {}
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: msg
            });
        });
    });

    // ========== INICIO ==========
    cargarMaestros(function () {
        inicializarDataTable();
        cargarBotonAgregar();
    });
});
