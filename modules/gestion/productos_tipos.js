$(document).ready(function () {
    var empresa_idx = EMPRESA_ID;
    var pagina_idx = PAGINA_ID;
    var AJAX_URL = 'productos_tipos_ajax.php';

    var tabla = null;
    var cuentasCargadas = null;

    // ========== UTILIDADES ==========

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return String(text)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function badgeSiNo(valor) {
        return valor == 1
            ? '<span class="badge bg-success">Sí</span>'
            : '<span class="badge bg-light text-muted border">No</span>';
    }

    function textoCuenta(codigo, nombre) {
        if (!codigo) return '<span class="text-muted">-</span>';
        return '<span title="' + escapeHtml(codigo + ' - ' + nombre) + '">' + escapeHtml(codigo) + ' - ' + escapeHtml(nombre) + '</span>';
    }

    // ========== CARGAS INICIALES ==========

    function cargarBotonAgregar() {
        $.get(AJAX_URL, { accion: 'obtener_boton_agregar', pagina_idx: pagina_idx }, function (res) {
            var boton = res && res.data ? res.data : null;
            if (boton && boton.nombre_funcion) {
                var icono = boton.icono_clase ? '<i class="' + boton.icono_clase + ' me-1"></i>' : '';
                var colorClase = boton.bg_clase && boton.text_clase ? boton.bg_clase + ' ' + boton.text_clase : (boton.color_clase || 'btn-primary');
                $('#contenedor-boton-agregar').html('<button type="button" class="btn ' + colorClase + '" id="btnNuevo">' + icono + escapeHtml(boton.nombre_funcion) + '</button>');
            } else {
                $('#contenedor-boton-agregar').html('<button type="button" class="btn btn-primary" id="btnNuevo"><i class="fas fa-plus me-1"></i>Agregar Tipo</button>');
            }
        }, 'json');
    }

    // El filtro se resuelve ANTES de crear la tabla para que la primera carga
    // ya salga filtrada en "Activo" (mismo criterio que productos.php).
    function cargarFiltroEstado(callback) {
        $.get(AJAX_URL, { accion: 'obtener_estados' }, function (res) {
            var $select = $('#filtroEstado');
            $select.empty().append('<option value="">Todos los estados</option>');
            ((res && res.data) || []).forEach(function (estado) {
                $select.append('<option value="' + estado.estado_registro_id + '">' + escapeHtml(estado.estado_registro) + '</option>');
            });
            $select.val('1');
            if (typeof callback === 'function') callback();
        }, 'json').fail(function () {
            if (typeof callback === 'function') callback();
        });
    }

    function cargarCuentas(callback) {
        if (cuentasCargadas !== null) {
            if (typeof callback === 'function') callback();
            return;
        }
        $.get(AJAX_URL, { accion: 'obtener_cuentas', empresa_idx: empresa_idx }, function (res) {
            cuentasCargadas = (res && res.data) || [];
            $('.select-cuenta').each(function () {
                var $select = $(this).empty().append('<option value="">(Sin cuenta)</option>');
                cuentasCargadas.forEach(function (c) {
                    $select.append('<option value="' + c.cont_cuenta_id + '">' + escapeHtml(c.codigo + ' - ' + c.nombre) + '</option>');
                });
            });
            if (typeof callback === 'function') callback();
        }, 'json');
    }

    // ========== DATATABLE ==========

    function inicializarDataTable() {
        var exportCols = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9];

        tabla = $('#tablaProductosTipos').DataTable({
            ajax: {
                url: AJAX_URL,
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                    d.filtro_estado = $('#filtroEstado').val();
                },
                dataSrc: function (res) {
                    if (!res || !res.success) {
                        Swal.fire({ icon: 'error', title: 'Error', text: (res && res.message) || 'Error al cargar los tipos de producto' });
                        return [];
                    }
                    return res.data || [];
                }
            },
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            pageLength: 25,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
            buttons: [
                { extend: 'excelHtml5', title: 'Tipos de Producto', exportOptions: { columns: exportCols } },
                { extend: 'pdfHtml5', title: 'Tipos de Producto', orientation: 'landscape', pageSize: 'A4', exportOptions: { columns: exportCols } },
                { extend: 'csvHtml5', title: 'Tipos de Producto', exportOptions: { columns: exportCols } },
                { extend: 'print', title: 'Listado de Tipos de Producto', exportOptions: { columns: exportCols } }
            ],
            columns: [
                { data: 'producto_tipo_codigo', className: 'text-center fw-medium', responsivePriority: 2, render: function (d) { return escapeHtml(d || ''); } },
                { data: 'producto_tipo', responsivePriority: 1, render: function (d) { return escapeHtml(d || ''); } },
                { data: 'maneja_stock', className: 'text-center', render: function (d, type) { return type === 'display' ? badgeSiNo(d) : (d == 1 ? 'Sí' : 'No'); } },
                { data: 'es_compuesto', className: 'text-center', render: function (d, type) { return type === 'display' ? badgeSiNo(d) : (d == 1 ? 'Sí' : 'No'); } },
                { data: 'afecta_facturacion', className: 'text-center', render: function (d, type) { return type === 'display' ? badgeSiNo(d) : (d == 1 ? 'Sí' : 'No'); } },
                {
                    data: 'cuenta_compra_codigo', render: function (d, type, row) {
                        return type === 'display' ? textoCuenta(d, row.cuenta_compra_nombre) : (d ? d + ' - ' + row.cuenta_compra_nombre : '');
                    }
                },
                {
                    data: 'cuenta_venta_codigo', render: function (d, type, row) {
                        return type === 'display' ? textoCuenta(d, row.cuenta_venta_nombre) : (d ? d + ' - ' + row.cuenta_venta_nombre : '');
                    }
                },
                {
                    data: 'cuenta_cmv_codigo', render: function (d, type, row) {
                        return type === 'display' ? textoCuenta(d, row.cuenta_cmv_nombre) : (d ? d + ' - ' + row.cuenta_cmv_nombre : '');
                    }
                },
                { data: 'total_productos', className: 'text-center' },
                {
                    data: 'estado_registro', className: 'text-center', render: function (d, type, row) {
                        if (type !== 'display') return d;
                        var clase = row.tabla_estado_registro_id == 1 ? 'bg-success' : 'bg-secondary';
                        return '<span class="badge ' + clase + '">' + escapeHtml(d) + '</span>';
                    }
                },
                {
                    data: 'botones', orderable: false, searchable: false, className: 'text-center', responsivePriority: 1,
                    render: function (data, type, row) {
                        if (type !== 'display' || !data) return '';
                        return data.map(function (boton) {
                            var nombre = boton.accion_js || boton.nombre_funcion.toLowerCase();
                            var clase = 'btn-xs me-1 ';
                            clase += nombre === 'editar' ? 'btn-outline-primary' :
                                (nombre === 'inactivar' || nombre === 'eliminar' || nombre === 'baja') ? 'btn-outline-danger' :
                                (nombre === 'habilitar' || nombre === 'activar' || nombre === 'alta') ? 'btn-outline-success' : 'btn-outline-secondary';
                            return '<button type="button" class="btn ' + clase + ' btn-accion"' +
                                ' title="' + escapeHtml(boton.descripcion || boton.nombre_funcion) + '"' +
                                ' data-id="' + row.producto_tipo_id + '"' +
                                ' data-accion="' + escapeHtml(boton.accion_js) + '"' +
                                ' data-nombre-funcion="' + escapeHtml(boton.nombre_funcion) + '"' +
                                ' data-confirmable="' + (boton.es_confirmable || 0) + '"' +
                                ' data-tipo="' + escapeHtml(row.producto_tipo) + '"' +
                                ' data-productos="' + (row.total_productos || 0) + '">' +
                                '<i class="' + (boton.icono_clase || 'fas fa-cog') + '"></i></button>';
                        }).join('') || '<span class="text-muted small">-</span>';
                    }
                }
            ],
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
            order: [[1, 'asc']],
            responsive: true,
            createdRow: function (row, data) {
                if (data.tabla_estado_registro_id != 1) $(row).addClass('table-secondary');
            }
        });
    }

    // ========== ACCIONES ==========

    function ejecutarAccion(id, accionJs, nombreFuncion, tipo, esConfirmable, totalProductos) {
        if (esConfirmable != 1) {
            enviarAccionBackend(id, accionJs, nombreFuncion, tipo);
            return;
        }

        var html = '¿Está seguro de <strong>' + escapeHtml(nombreFuncion.toLowerCase()) + '</strong> el tipo <strong>"' + escapeHtml(tipo) + '"</strong>?';
        if (accionJs === 'inactivar' && totalProductos > 0) {
            html += '<br><small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>' +
                totalProductos + ' producto(s) usan este tipo. Lo conservan, pero no se podrá elegir en productos nuevos.</small>';
        }

        Swal.fire({
            title: '¿' + nombreFuncion + '?',
            html: html,
            icon: 'question', showCancelButton: true, confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33', confirmButtonText: 'Sí, ' + nombreFuncion.toLowerCase(), cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (result.isConfirmed) enviarAccionBackend(id, accionJs, nombreFuncion, tipo);
        });
    }

    function enviarAccionBackend(id, accionJs, nombreFuncion, tipo) {
        $.post(AJAX_URL, {
            accion: 'ejecutar_accion',
            producto_tipo_id: id,
            accion_js: accionJs,
            empresa_idx: empresa_idx,
            pagina_idx: pagina_idx
        }, function (res) {
            if (res.success) {
                tabla.ajax.reload(function () {
                    Swal.fire({ icon: 'success', title: '¡' + nombreFuncion + '!', text: res.message || 'Tipo "' + tipo + '" actualizado correctamente', showConfirmButton: false, timer: 1500, toast: true, position: 'top-end' });
                }, false);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Error al ejecutar la acción' });
            }
        }, 'json').fail(function () {
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'Error al comunicarse con el servidor' });
        });
    }

    // ========== MODAL ALTA / EDICIÓN ==========

    function resetModal() {
        var form = document.getElementById('formProductoTipo');
        form.reset();
        form.classList.remove('was-validated');
        $('#producto_tipo_id').val('');
        $('#afecta_facturacion').prop('checked', true);
        $('.select-cuenta').val('');
    }

    function mostrarModalNuevo() {
        cargarCuentas(function () {
            resetModal();
            $('#modalLabel').html('<i class="fas fa-shapes me-2"></i>Nuevo Tipo de Producto');
            new bootstrap.Modal(document.getElementById('modalProductoTipo')).show();
        });
    }

    function cargarParaEditar(id) {
        cargarCuentas(function () {
            $.get(AJAX_URL, { accion: 'obtener', producto_tipo_id: id, empresa_idx: empresa_idx }, function (res) {
                if (!res || !res.success || !res.data) {
                    Swal.fire({ icon: 'error', title: 'Error', text: (res && res.message) || 'Error al obtener datos del tipo de producto' });
                    return;
                }
                var t = res.data;
                resetModal();
                $('#producto_tipo_id').val(t.producto_tipo_id);
                $('#producto_tipo_codigo').val(t.producto_tipo_codigo || '');
                $('#producto_tipo').val(t.producto_tipo || '');
                $('#maneja_stock').prop('checked', t.maneja_stock == 1);
                $('#es_compuesto').prop('checked', t.es_compuesto == 1);
                $('#afecta_facturacion').prop('checked', t.afecta_facturacion == 1);
                $('#cont_cuenta_compra_default_id').val(t.cont_cuenta_compra_default_id || '');
                $('#cont_cuenta_venta_default_id').val(t.cont_cuenta_venta_default_id || '');
                $('#cont_cuenta_cmv_default_id').val(t.cont_cuenta_cmv_default_id || '');
                $('#modalLabel').html('<i class="fas fa-shapes me-2"></i>Editar Tipo de Producto');
                new bootstrap.Modal(document.getElementById('modalProductoTipo')).show();
            }, 'json');
        });
    }

    function guardar() {
        var form = document.getElementById('formProductoTipo');
        $('#producto_tipo_codigo').val($('#producto_tipo_codigo').val().trim().toUpperCase());
        $('#producto_tipo').val($('#producto_tipo').val().trim());
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }

        var id = $('#producto_tipo_id').val();
        var $btn = $('#btnGuardar').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: AJAX_URL,
            type: 'POST',
            dataType: 'json',
            data: {
                accion: id ? 'editar' : 'agregar',
                producto_tipo_id: id,
                producto_tipo_codigo: $('#producto_tipo_codigo').val(),
                producto_tipo: $('#producto_tipo').val(),
                maneja_stock: $('#maneja_stock').is(':checked') ? 1 : 0,
                es_compuesto: $('#es_compuesto').is(':checked') ? 1 : 0,
                afecta_facturacion: $('#afecta_facturacion').is(':checked') ? 1 : 0,
                cont_cuenta_compra_default_id: $('#cont_cuenta_compra_default_id').val() || '',
                cont_cuenta_venta_default_id: $('#cont_cuenta_venta_default_id').val() || '',
                cont_cuenta_cmv_default_id: $('#cont_cuenta_cmv_default_id').val() || '',
                empresa_idx: empresa_idx,
                pagina_idx: pagina_idx
            },
            success: function (res) {
                $btn.prop('disabled', false).html('Guardar');
                if (res.success) {
                    bootstrap.Modal.getInstance(document.getElementById('modalProductoTipo')).hide();
                    tabla.ajax.reload(function () {
                        Swal.fire({ icon: 'success', title: '¡Guardado!', text: res.message || 'Tipo de producto guardado correctamente', showConfirmButton: false, timer: 1500, toast: true, position: 'top-end' });
                    }, false);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Error al guardar los datos' });
                }
            },
            error: function () {
                $btn.prop('disabled', false).html('Guardar');
                Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'Error al comunicarse con el servidor' });
            }
        });
    }

    // ========== EVENTOS ==========

    $(document).on('click', '#btnNuevo', mostrarModalNuevo);

    $(document).on('click', '.btn-accion', function () {
        var $b = $(this);
        var id = $b.data('id');
        var accionJs = $b.data('accion');
        if (accionJs === 'editar') {
            cargarParaEditar(id);
            return;
        }
        ejecutarAccion(id, accionJs, $b.data('nombre-funcion') || accionJs, $b.data('tipo'), $b.data('confirmable'), parseInt($b.data('productos'), 10) || 0);
    });

    $('#btnGuardar').on('click', guardar);

    $('#formProductoTipo').on('submit', function (e) {
        e.preventDefault();
        guardar();
    });

    $('#filtroEstado').on('change', function () {
        tabla.ajax.reload();
    });

    $('#btnRecargar').on('click', function () {
        var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        tabla.ajax.reload(function () { $btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>'); }, false);
    });

    $('.btn-exportar').on('click', function (e) {
        e.preventDefault();
        if (tabla) tabla.button(parseInt($(this).data('boton'), 10)).trigger();
    });

    // ========== INICIALIZACIÓN ==========

    cargarBotonAgregar();
    cargarFiltroEstado(inicializarDataTable);
});
