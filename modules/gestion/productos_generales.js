$(document).ready(function () {
    var empresa_idx = EMPRESA_ID;
    var pagina_idx = PAGINA_ID;
    var AJAX_URL = 'productos_generales_ajax.php';

    var tabla = null;
    var combos = null;       // {tipos, categorias, unidades, iva_alicuotas, cuentas}
    var tiposPorId = {};
    var tags = [];           // etiquetas del buscador de texto
    var tagsInput = $('#buscadorTagsInput');
    var tagsContainer = $('#buscadorTagsContainer');
    var timeoutCodigo = null;

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

    function renderSiNo(d, type) {
        return type === 'display' ? badgeSiNo(d) : (d == 1 ? 'Sí' : 'No');
    }

    function llenarSelect($select, filas, campoId, textoFn, textoVacio) {
        $select.empty();
        if (textoVacio !== null) $select.append('<option value="">' + escapeHtml(textoVacio) + '</option>');
        filas.forEach(function (f) {
            $select.append('<option value="' + f[campoId] + '">' + escapeHtml(textoFn(f)) + '</option>');
        });
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
                $('#contenedor-boton-agregar').html('<button type="button" class="btn btn-primary" id="btnNuevo"><i class="fas fa-plus me-1"></i>Agregar Producto</button>');
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

    // Todos los combos del formulario (y el filtro de tipo) en una sola llamada
    function cargarCombos(callback) {
        if (combos !== null) {
            if (typeof callback === 'function') callback();
            return;
        }
        $.get(AJAX_URL, { accion: 'obtener_combos', empresa_idx: empresa_idx }, function (res) {
            if (!res || !res.success) {
                Swal.fire({ icon: 'error', title: 'Error', text: (res && res.message) || 'Error al cargar los datos del formulario' });
                return;
            }
            combos = res.data;
            tiposPorId = {};
            combos.tipos.forEach(function (t) { tiposPorId[t.producto_tipo_id] = t; });

            llenarSelect($('#producto_tipo_id'), combos.tipos, 'producto_tipo_id', function (t) { return t.producto_tipo; }, 'Seleccionar tipo...');
            llenarSelect($('#producto_categoria_id'), combos.categorias, 'producto_categoria_id', function (c) { return c.producto_categoria_nombre; }, 'Seleccionar categoría...');
            llenarSelect($('#unidad_medida_id'), combos.unidades, 'unidad_medida_id', function (u) {
                return u.unidad_nombre + (u.unidad_abreviatura ? ' (' + u.unidad_abreviatura + ')' : '');
            }, '(Sin unidad)');
            llenarSelect($('#iva_alicuota_id'), combos.iva_alicuotas, 'iva_alicuota_id', function (a) { return a.iva_alicuota; }, 'Seleccionar alícuota...');
            $('.select-cuenta').each(function () {
                llenarSelect($(this), combos.cuentas, 'cont_cuenta_id', function (c) { return c.codigo + ' - ' + c.nombre; }, '(Sin cuenta)');
            });

            var filtroTipo = $('#filtroTipo').val();
            llenarSelect($('#filtroTipo'), combos.tipos, 'producto_tipo_id', function (t) { return t.producto_tipo; }, 'Todos los tipos');
            $('#filtroTipo').val(filtroTipo);

            if (typeof callback === 'function') callback();
        }, 'json').fail(function () {
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'Error al cargar los datos del formulario' });
        });
    }

    // ========== DATATABLE (server-side: el catálogo tiene miles de productos) ==========

    function inicializarDataTable() {
        var exportCols = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9];

        tabla = $('#tablaProductosGenerales').DataTable({
            processing: true,
            serverSide: true,
            searchDelay: 400,
            ajax: {
                url: AJAX_URL,
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                    d.filtro_estado = $('#filtroEstado').val();
                    d.filtro_tipo = $('#filtroTipo').val();
                    d.filtro_servicio = $('#filtroServicio').val();
                    d.filtro_codigo = $('#filtroCodigo').val().trim();
                },
                dataSrc: function (res) {
                    if (!res || !res.success) {
                        Swal.fire({ icon: 'error', title: 'Error', text: (res && res.message) || 'Error al cargar los productos' });
                        return [];
                    }
                    return res.data || [];
                }
            },
            dom: '<"row"<"col-sm-12"tr>>' +
                '<"row align-items-center mt-2"<"col-sm-12 col-md-4"l><"col-sm-12 col-md-4"i><"col-sm-12 col-md-4"p>>',
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            buttons: [
                { extend: 'excelHtml5', title: 'Productos y Servicios', exportOptions: { columns: exportCols } },
                { extend: 'pdfHtml5', title: 'Productos y Servicios', orientation: 'landscape', pageSize: 'A4', exportOptions: { columns: exportCols } },
                { extend: 'csvHtml5', title: 'Productos y Servicios', exportOptions: { columns: exportCols } },
                { extend: 'print', title: 'Listado de Productos y Servicios', exportOptions: { columns: exportCols } }
            ],
            columns: [
                { data: 'producto_codigo', className: 'fw-medium', responsivePriority: 2, render: function (d) { return escapeHtml(d || ''); } },
                {
                    data: 'producto_nombre', responsivePriority: 1, render: function (d, type, row) {
                        if (type !== 'display') return d || '';
                        var html = escapeHtml(d || '');
                        if (row.es_servicio == 1) html += ' <span class="badge bg-info ms-1">Servicio</span>';
                        if (row.codigo_barras) html += '<br><small class="text-muted"><i class="fas fa-barcode me-1"></i>' + escapeHtml(row.codigo_barras) + '</small>';
                        return html;
                    }
                },
                { data: 'producto_tipo', render: function (d) { return escapeHtml(d || '-'); } },
                { data: 'producto_categoria_nombre', render: function (d) { return escapeHtml(d || '-'); } },
                { data: 'unidad_abreviatura', className: 'text-center', render: function (d) { return escapeHtml(d || '-'); } },
                { data: 'iva_alicuota', className: 'text-center', render: function (d) { return escapeHtml(d || '-'); } },
                { data: 'controla_stock', className: 'text-center', render: renderSiNo },
                { data: 'aplica_compra', className: 'text-center', render: renderSiNo },
                { data: 'aplica_venta', className: 'text-center', render: renderSiNo },
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
                                ' data-id="' + row.producto_id + '"' +
                                ' data-accion="' + escapeHtml(boton.accion_js) + '"' +
                                ' data-nombre-funcion="' + escapeHtml(boton.nombre_funcion) + '"' +
                                ' data-confirmable="' + (boton.es_confirmable || 0) + '"' +
                                ' data-nombre="' + escapeHtml(row.producto_nombre) + '">' +
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

    // ========== BUSCADOR DE TEXTO POR ETIQUETAS (mismo comportamiento que productos.php) ==========

    function inicializarBuscadorTags() {
        tagsContainer.on('click', function (e) {
            if (e.target === this) tagsInput.focus();
        });

        // El espacio confirma la etiqueta, salvo que haya una comilla doble sin
        // cerrar (se está armando una frase, ej. "disco rigido"). Enter siempre confirma.
        tagsInput.on('keydown', function (e) {
            var valorActual = $(this).val();
            var value = valorActual.trim();
            var comillaAbierta = ((valorActual.match(/"/g) || []).length % 2) === 1;

            if ((e.key === ' ' && !comillaAbierta) || e.key === 'Enter') {
                e.preventDefault();
                if (value.length > 0) {
                    agregarTag(value);
                    ejecutarBusquedaTags();
                }
            } else if (e.key === 'Backspace' && value === '' && tags.length > 0) {
                eliminarTag(tags.length - 1);
                ejecutarBusquedaTags();
            } else if (e.key === 'Escape') {
                $(this).blur();
            }
        });

        // Al pegar, el texto queda en una sola línea esperando confirmación
        tagsInput.on('paste', function () {
            setTimeout(function () {
                tagsInput.val(tagsInput.val().replace(/\s+/g, ' ').trim());
            }, 10);
        });

        tagsContainer.on('click', '.tag-remove', function (e) {
            e.stopPropagation();
            eliminarTag(parseInt($(this).data('index'), 10));
            ejecutarBusquedaTags();
        });
    }

    function agregarTag(texto) {
        // Las comillas se sacan: el término viaja entrecomillado al backend cuando tiene espacios
        texto = String(texto).replace(/"/g, '').replace(/\s+/g, ' ').trim();
        tagsInput.val('');
        if (!texto) return;
        var duplicado = tags.some(function (tag) { return tag.toLowerCase() === texto.toLowerCase(); });
        if (duplicado) return;
        tags.push(texto);
        renderizarTags();
        tagsInput.focus();
    }

    function eliminarTag(index) {
        if (index >= 0 && index < tags.length) {
            tags.splice(index, 1);
            renderizarTags();
        }
    }

    function renderizarTags() {
        tagsContainer.find('.tag-item').remove();
        tags.forEach(function (tag, index) {
            tagsInput.before(
                '<span class="tag-item">' +
                '<span class="tag-text">' + escapeHtml(tag) + '</span>' +
                '<span class="tag-remove" data-index="' + index + '" title="Eliminar"><i class="fas fa-times"></i></span>' +
                '</span>');
        });
    }

    // Etiquetas de una palabra van sueltas; las compuestas, entre comillas (el modelo las busca como frase)
    function serializarTags(lista) {
        return lista.map(function (tag) {
            return tag.indexOf(' ') >= 0 ? '"' + tag + '"' : tag;
        }).join(' ');
    }

    function ejecutarBusquedaTags() {
        if (!tabla) return;
        if (tags.length > 0) {
            tagsContainer.attr('title', 'Buscando ' + tags.length + ' palabra(s): ' + tags.join(', '));
        } else {
            tagsContainer.removeAttr('title');
        }
        tabla.search(serializarTags(tags)).draw();
    }

    // Botón X en rojo cuando hay filtros activos
    function actualizarIndicadorFiltros() {
        var totalFiltros = (tags.length > 0 ? 1 : 0) + ($('#filtroCodigo').val().trim() ? 1 : 0) +
            ($('#filtroTipo').val() ? 1 : 0) + ($('#filtroServicio').val() !== '' ? 1 : 0);

        if (totalFiltros > 0) {
            $('#btnLimpiarTags').removeClass('btn-outline-secondary').addClass('btn-danger')
                .attr('title', 'Limpiar todos los filtros (' + totalFiltros + ' activo' + (totalFiltros > 1 ? 's' : '') + ')');
        } else {
            $('#btnLimpiarTags').removeClass('btn-danger').addClass('btn-outline-secondary')
                .attr('title', 'Limpiar todos los filtros');
        }
    }

    // ========== ACCIONES DE ESTADO ==========

    function ejecutarAccion(id, accionJs, nombreFuncion, nombre, esConfirmable) {
        if (esConfirmable != 1) {
            enviarAccionBackend(id, accionJs, nombreFuncion, nombre);
            return;
        }

        Swal.fire({
            title: '¿' + nombreFuncion + '?',
            html: '¿Está seguro de <strong>' + escapeHtml(nombreFuncion.toLowerCase()) + '</strong> <strong>"' + escapeHtml(nombre) + '"</strong>?',
            icon: 'question', showCancelButton: true, confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33', confirmButtonText: 'Sí, ' + nombreFuncion.toLowerCase(), cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (result.isConfirmed) enviarAccionBackend(id, accionJs, nombreFuncion, nombre);
        });
    }

    function enviarAccionBackend(id, accionJs, nombreFuncion, nombre) {
        $.post(AJAX_URL, {
            accion: 'ejecutar_accion',
            producto_id: id,
            accion_js: accionJs,
            empresa_idx: empresa_idx,
            pagina_idx: pagina_idx
        }, function (res) {
            if (res.success) {
                tabla.ajax.reload(function () {
                    Swal.fire({ icon: 'success', title: '¡' + nombreFuncion + '!', text: res.message || '"' + nombre + '" actualizado correctamente', showConfirmButton: false, timer: 1500, toast: true, position: 'top-end' });
                }, false);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Error al ejecutar la acción' });
            }
        }, 'json').fail(function () {
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'Error al comunicarse con el servidor' });
        });
    }

    // ========== MODAL ALTA / EDICIÓN ==========

    function esAlta() {
        return !$('#producto_id').val();
    }

    // Servicio ⇒ no controla stock; sin control de stock ⇒ sin cuenta de CMV
    function aplicarReglasStock() {
        var esServicio = $('#es_servicio').is(':checked');
        if (esServicio) $('#controla_stock').prop('checked', false);
        $('#controla_stock').prop('disabled', esServicio);

        var controlaStock = $('#controla_stock').is(':checked');
        if (!controlaStock) $('#cont_cuenta_cmv_id').val('');
        $('#cont_cuenta_cmv_id').prop('disabled', !controlaStock);
    }

    // En el alta, elegir el tipo propone el control de stock y las cuentas por defecto del tipo
    function aplicarDefaultsTipo() {
        if (!esAlta()) return;
        var tipo = tiposPorId[$('#producto_tipo_id').val()];
        if (!tipo) return;

        if (!$('#es_servicio').is(':checked')) {
            $('#controla_stock').prop('checked', tipo.maneja_stock == 1);
        }
        $('#cont_cuenta_compra_id').val(tipo.cont_cuenta_compra_default_id || '');
        $('#cont_cuenta_venta_id').val(tipo.cont_cuenta_venta_default_id || '');
        $('#cont_cuenta_cmv_id').val(tipo.cont_cuenta_cmv_default_id || '');
        aplicarReglasStock();
    }

    function resetModal() {
        var form = document.getElementById('formProductoGeneral');
        form.reset();
        form.classList.remove('was-validated');
        $('#producto_id').val('');
        $('#formProductoGeneral select').val('');
        $('#controla_stock, #aplica_compra, #aplica_venta').prop('checked', true);
        $('#es_servicio').prop('checked', false);
        aplicarReglasStock();
        bootstrap.Tab.getOrCreateInstance(document.getElementById('tab-general-btn')).show();
    }

    function mostrarModalNuevo() {
        cargarCombos(function () {
            resetModal();
            // Mismos defaults que productos.php: tipo 1 e IVA 1 (21%) si existen
            if (tiposPorId[1]) $('#producto_tipo_id').val('1');
            if ($('#iva_alicuota_id option[value="1"]').length) $('#iva_alicuota_id').val('1');
            aplicarDefaultsTipo();
            $('#ayudaCuentas').show();
            $('#modalLabel').html('<i class="fas fa-cubes me-2"></i>Nuevo Producto / Servicio');
            new bootstrap.Modal(document.getElementById('modalProductoGeneral')).show();
        });
    }

    function cargarParaEditar(id) {
        cargarCombos(function () {
            $.get(AJAX_URL, { accion: 'obtener', producto_id: id, empresa_idx: empresa_idx }, function (res) {
                if (!res || !res.success || !res.data) {
                    Swal.fire({ icon: 'error', title: 'Error', text: (res && res.message) || 'Error al obtener datos del producto' });
                    return;
                }
                var p = res.data;
                resetModal();
                $('#producto_id').val(p.producto_id);
                ['producto_codigo', 'producto_nombre', 'codigo_barras', 'producto_descripcion', 'lado', 'material',
                    'color', 'peso', 'dimensiones', 'garantia', 'impuesto_interno_porcentaje', 'impuesto_interno_fijo',
                    'producto_tipo_id', 'producto_categoria_id', 'unidad_medida_id', 'iva_alicuota_id',
                    'cont_cuenta_compra_id', 'cont_cuenta_venta_id', 'cont_cuenta_cmv_id'
                ].forEach(function (campo) {
                    $('#' + campo).val(p[campo] !== null && p[campo] !== undefined ? p[campo] : '');
                });
                ['es_servicio', 'controla_stock', 'aplica_compra', 'aplica_venta'].forEach(function (campo) {
                    $('#' + campo).prop('checked', p[campo] == 1);
                });
                aplicarReglasStock();
                $('#ayudaCuentas').hide();
                $('#modalLabel').html('<i class="fas fa-cubes me-2"></i>Editar Producto / Servicio');
                new bootstrap.Modal(document.getElementById('modalProductoGeneral')).show();
            }, 'json');
        });
    }

    function guardar() {
        var form = document.getElementById('formProductoGeneral');
        ['producto_codigo', 'producto_nombre'].forEach(function (campo) {
            $('#' + campo).val($('#' + campo).val().trim());
        });

        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            // Llevar al usuario a la pestaña del primer campo inválido
            var $invalido = $(form).find(':invalid').first();
            var paneId = $invalido.closest('.tab-pane').attr('id');
            if (paneId) bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#' + paneId + '"]')).show();
            return;
        }
        if (!$('#aplica_compra').is(':checked') && !$('#aplica_venta').is(':checked')) {
            Swal.fire({ icon: 'warning', title: 'Revisar', text: 'El producto tiene que poder comprarse, venderse o ambas cosas' });
            return;
        }

        var id = $('#producto_id').val();
        var $btn = $('#btnGuardar').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        var data = {
            accion: id ? 'editar' : 'agregar',
            producto_id: id,
            empresa_idx: empresa_idx,
            pagina_idx: pagina_idx
        };
        ['producto_codigo', 'producto_nombre', 'codigo_barras', 'producto_descripcion', 'lado', 'material',
            'color', 'peso', 'dimensiones', 'garantia', 'impuesto_interno_porcentaje', 'impuesto_interno_fijo',
            'producto_tipo_id', 'producto_categoria_id', 'unidad_medida_id', 'iva_alicuota_id',
            'cont_cuenta_compra_id', 'cont_cuenta_venta_id', 'cont_cuenta_cmv_id'
        ].forEach(function (campo) {
            data[campo] = $('#' + campo).val() || '';
        });
        ['es_servicio', 'controla_stock', 'aplica_compra', 'aplica_venta'].forEach(function (campo) {
            data[campo] = $('#' + campo).is(':checked') ? 1 : 0;
        });

        $.ajax({
            url: AJAX_URL,
            type: 'POST',
            dataType: 'json',
            data: data,
            success: function (res) {
                $btn.prop('disabled', false).html('Guardar');
                if (res.success) {
                    bootstrap.Modal.getInstance(document.getElementById('modalProductoGeneral')).hide();
                    tabla.ajax.reload(function () {
                        Swal.fire({ icon: 'success', title: '¡Guardado!', text: res.message || 'Producto guardado correctamente', showConfirmButton: false, timer: 1500, toast: true, position: 'top-end' });
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
        ejecutarAccion(id, accionJs, $b.data('nombre-funcion') || accionJs, $b.data('nombre'), $b.data('confirmable'));
    });

    $('#producto_tipo_id').on('change', aplicarDefaultsTipo);
    $('#es_servicio, #controla_stock').on('change', aplicarReglasStock);

    $('#btnGuardar').on('click', guardar);

    $('#formProductoGeneral').on('submit', function (e) {
        e.preventDefault();
        guardar();
    });

    $('#filtroEstado, #filtroTipo, #filtroServicio').on('change', function () {
        tabla.ajax.reload();
    });

    // Filtro de código con debounce (igual que productos.php)
    $('#filtroCodigo').on('keyup', function () {
        clearTimeout(timeoutCodigo);
        $('#codigoSearchIcon').toggle($(this).val().trim().length > 0);
        timeoutCodigo = setTimeout(function () { tabla.ajax.reload(); }, 400);
    });

    // La X del buscador limpia los filtros de texto, tipo y producto/servicio
    // (el de estado se mantiene, igual que en productos.php)
    $('#btnLimpiarTags').on('click', function () {
        $('#filtroCodigo').val('');
        $('#codigoSearchIcon').hide();
        $('#filtroTipo').val('');
        $('#filtroServicio').val('');
        tags = [];
        renderizarTags();
        ejecutarBusquedaTags();
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
    cargarCombos();
    inicializarBuscadorTags();
    cargarFiltroEstado(function () {
        inicializarDataTable();
        tabla.on('draw.dt', actualizarIndicadorFiltros);
    });
});
