$(document).ready(function () {
    var empresa_idx = EMPRESA_ID;
    var pagina_idx = PAGINA_ID;
    // productos_consulta.php reutiliza esta pantalla en solo lectura con su propio endpoint
    var AJAX_URL = window.PRODUCTOS_AJAX_URL || 'productos_ajax.php';
    var SOLO_LECTURA = !!window.PRODUCTOS_SOLO_LECTURA;

    var tabla = null;
    var combos = null;       // {tipos, categorias, unidades, iva_alicuotas, cuentas}
    var tiposPorId = {};
    var tags = [];           // etiquetas del buscador de texto
    var tagsInput = $('#buscadorTagsInput');
    var tagsContainer = $('#buscadorTagsContainer');
    var timeoutCodigo = null;
    // Presentaciones (caja, pack, pallet): solo si la empresa las usa (la sección está en el modal)
    var PRES = window.ProdPres && window.ProdPres.activo() ? window.ProdPres : null;
    if (PRES) PRES.init({ ajaxUrl: AJAX_URL, empresaIdx: empresa_idx });

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
        if (SOLO_LECTURA) return;
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
        }, 'json');
    }

    // ========== DATATABLES ==========

    function inicializarTabla() {
        tabla = $('#tablaProductos').DataTable({
            processing: true,
            serverSide: true,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            order: [[1, 'asc']],
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
                    d.filtro_codigo = $('#filtroCodigo').val();
                    d.search.value = tags.join(' ');
                }
            },
            columns: [
                {
                    data: 'producto_codigo',
                    render: function (data) {
                        return '<strong class="text-primary">' + escapeHtml(data) + '</strong>';
                    }
                },
                {
                    data: 'producto_nombre',
                    render: function (data, type, row) {
                        var extra = row.codigo_barras ? ' <small class="text-muted"><i class="fas fa-barcode ms-1 me-1"></i>' + escapeHtml(row.codigo_barras) + '</small>' : '';
                        var badgeServ = row.es_servicio == 1 ? ' <span class="badge bg-info text-dark" style="font-size:0.7rem">Servicio</span>' : '';
                        return escapeHtml(data) + extra + badgeServ;
                    }
                },
                { data: 'producto_tipo', defaultContent: '<span class="text-muted">-</span>', render: escapeHtml },
                { data: 'producto_categoria_nombre', defaultContent: '<span class="text-muted">-</span>', render: escapeHtml },
                { data: 'unidad_abreviatura', defaultContent: '<span class="text-muted">-</span>', render: escapeHtml },
                {
                    data: 'iva_alicuota',
                    className: 'text-end',
                    defaultContent: '-',
                    render: escapeHtml
                },
                {
                    data: 'controla_stock',
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (row.es_servicio == 1) return '<span class="badge bg-secondary">N/A</span>';
                        return renderSiNo(data, type);
                    }
                },
                { data: 'aplica_compra', className: 'text-center', render: renderSiNo },
                { data: 'aplica_venta', className: 'text-center', render: renderSiNo },
                {
                    data: 'estado_registro',
                    className: 'text-center',
                    render: function (data, type, row) {
                        var cls = row.tabla_estado_registro_id == 1 ? 'bg-success' : 'bg-secondary';
                        return '<span class="badge ' + cls + '">' + escapeHtml(data || 'Activo') + '</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        var html = '<div class="btn-group btn-group-sm" role="group">';
                        (row.botones || []).forEach(function (btn) {
                            var icon = btn.icono_clase ? '<i class="' + btn.icono_clase + '"></i>' : '';
                            var color = btn.bg_clase && btn.text_clase ? btn.bg_clase + ' ' + btn.text_clase : (btn.color_clase || 'btn-outline-primary');
                            html += '<button type="button" class="btn ' + color + ' btn-accion" ' +
                                'data-id="' + row.producto_id + '" ' +
                                'data-accion="' + escapeHtml(btn.accion_js) + '" ' +
                                'data-confirmable="' + (btn.es_confirmable || 0) + '" ' +
                                'title="' + escapeHtml(btn.descripcion || btn.nombre_funcion) + '">' +
                                icon + '</button>';
                        });
                        html += '</div>';
                        return html;
                    }
                }
            ]
        });
    }

    // ========== BUSCADOR DE TAGS ==========

    function renderTags() {
        tagsContainer.find('.tag-badge').remove();
        tags.forEach(function (tag, idx) {
            var badge = $('<span class="badge bg-secondary me-1 tag-badge text-wrap text-break d-inline-flex align-items-center" style="font-size: 0.85rem; padding: 0.35em 0.65em;">' +
                escapeHtml(tag) +
                '<i class="fas fa-times ms-2 tag-remove" data-index="' + idx + '" style="cursor: pointer;"></i></span>');
            tagsInput.before(badge);
        });
    }

    function addTag(text) {
        text = text.trim();
        if (text && tags.indexOf(text) === -1) {
            tags.push(text);
            renderTags();
            if (tabla) tabla.ajax.reload();
        }
    }

    tagsInput.on('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addTag($(this).val());
            $(this).val('');
        } else if (e.key === 'Backspace' && $(this).val() === '' && tags.length > 0) {
            tags.pop();
            renderTags();
            if (tabla) tabla.ajax.reload();
        }
    });

    tagsContainer.on('click', '.tag-remove', function () {
        var idx = $(this).data('index');
        tags.splice(idx, 1);
        renderTags();
        if (tabla) tabla.ajax.reload();
    });

    tagsContainer.on('click', function (e) {
        if (e.target === this) tagsInput.focus();
    });

    $('#btnBuscadorLimpiar').on('click', function () {
        tags = [];
        tagsInput.val('');
        renderTags();
        if (tabla) tabla.ajax.reload();
    });

    // Filtro por código con rebote
    $('#filtroCodigo').on('input', function () {
        clearTimeout(timeoutCodigo);
        timeoutCodigo = setTimeout(function () {
            if (tabla) tabla.ajax.reload();
        }, 300);
    });

    // Filtros de combos
    $('#filtroEstado, #filtroTipo, #filtroServicio').on('change', function () {
        if (tabla) tabla.ajax.reload();
    });

    // ========== FORMULARIO ABM (MODAL) ==========

    function resetFormulario() {
        $('#formProducto')[0].reset();
        $('#producto_id').val('');
        $('#empresa_id').val(empresa_idx);
        $('#formProducto').removeClass('was-validated');

        // Defaults para alta
        $('#es_servicio').prop('checked', false).trigger('change');
        $('#controla_stock').prop('checked', true);
        $('#aplica_compra').prop('checked', true);
        $('#aplica_venta').prop('checked', true);
        actualizarVisibilidadNiveles();
        cargarNivelesStock(0);
        if (PRES) PRES.limpiar();

        $('#modalProductoTitulo').text('Nuevo Producto / Servicio');
    }

    // ========== STOCK MÍNIMO / MÁXIMO POR BOCA ==========
    // Una fila por boca (con acceso del usuario). Se graba junto con el producto
    // (niveles_stock en agregar / editar); vacío = no se controla en esa boca.
    // Solo para productos que controlan stock.
    var nivelesStockToken = 0;

    function cargarNivelesStock(productoId) {
        var token = ++nivelesStockToken;
        var $tbody = $('#tablaNivelesStock tbody');
        $tbody.html('<tr><td colspan="4" class="text-center text-muted small py-2">Cargando bocas...</td></tr>');
        $.get(AJAX_URL, { accion: 'obtener_niveles_stock', producto_id: productoId || 0, empresa_idx: empresa_idx }, function (res) {
            if (token !== nivelesStockToken) return; // llegó una respuesta vieja
            var filas = (res && res.success && Array.isArray(res.data)) ? res.data : [];
            if (!filas.length) {
                $tbody.html('<tr><td colspan="4" class="text-center text-muted small py-2">No hay bocas que manejen stock a las que tenga acceso</td></tr>');
                return;
            }
            $tbody.html(filas.map(function (f) {
                return '<tr data-boca-id="' + f.boca_id + '" data-stock="' + f.stock_actual + '">' +
                    '<td>' +
                        '<div class="nivel-boca">' + escapeHtml(f.boca_nombre) +
                            (f.alerta_abierta == 1 ? ' <i class="bi bi-bell-fill text-warning ms-1" title="Tiene una alerta de reposición abierta"></i>' : '') + '</div>' +
                        '<div class="nivel-sucursal"><i class="fas fa-store me-1"></i>' + escapeHtml(f.sucursal_nombre) + '</div>' +
                    '</td>' +
                    '<td class="text-center"><span class="nivel-stock"></span></td>' +
                    '<td><div class="input-group input-group-sm">' +
                        '<span class="input-group-text">mín</span>' +
                        '<input type="number" step="any" min="0" class="form-control nivel-minimo" value="' + (f.stock_minimo === null ? '' : f.stock_minimo) + '" placeholder="sin control">' +
                    '</div></td>' +
                    '<td><div class="input-group input-group-sm">' +
                        '<span class="input-group-text">máx</span>' +
                        '<input type="number" step="any" min="0" class="form-control nivel-maximo" value="' + (f.stock_maximo === null ? '' : f.stock_maximo) + '" placeholder="sin tope">' +
                    '</div></td>' +
                    '</tr>';
            }).join(''));
            $tbody.find('tr[data-boca-id]').each(function () { pintarNivelStock($(this)); });
        }, 'json').fail(function () {
            if (token === nivelesStockToken) $tbody.html('<tr><td colspan="4" class="text-center text-danger small py-2">No se pudieron cargar las bocas</td></tr>');
        });
    }

    // Etiqueta de stock según el mínimo que está escrito (se actualiza mientras se tipea).
    var fmtNivelStock = Fmt.numberFormat({ maximumFractionDigits: 4 });
    function pintarNivelStock($tr) {
        var stock = parseFloat($tr.data('stock')) || 0;
        var minTxt = $tr.find('.nivel-minimo').val().trim();
        var $badge = $tr.find('.nivel-stock').removeClass('ok bajo sin-control');
        var bajo = minTxt !== '' && stock < parseFloat(minTxt);
        $tr.toggleClass('nivel-bajo', bajo);
        if (minTxt === '') {
            $badge.addClass('sin-control').attr('title', 'No se controla en esta boca');
            $badge.html(fmtNivelStock.format(stock));
        } else if (bajo) {
            $badge.addClass('bajo').attr('title', 'Por debajo del mínimo: faltan ' + fmtNivelStock.format(parseFloat(minTxt) - stock));
            $badge.html('<i class="bi bi-arrow-down-short"></i>' + fmtNivelStock.format(stock));
        } else {
            $badge.addClass('ok').attr('title', 'En nivel');
            $badge.html('<i class="bi bi-check2"></i>' + fmtNivelStock.format(stock));
        }
    }
    $(document).on('input', '#tablaNivelesStock .nivel-minimo', function () {
        pintarNivelStock($(this).closest('tr'));
    });

    // Devuelve el array a enviar, o un string con el error de validación.
    function recolectarNivelesStock() {
        var niveles = [];
        var error = null;
        $('#tablaNivelesStock tbody tr[data-boca-id]').each(function () {
            var $tr = $(this);
            var min = $tr.find('.nivel-minimo').val().trim();
            var max = $tr.find('.nivel-maximo').val().trim();
            $tr.find('input').removeClass('is-invalid');
            if (min === '' && max !== '') { error = 'Para cargar un máximo hay que cargar también el mínimo.'; $tr.find('.nivel-minimo').addClass('is-invalid'); }
            else if (min !== '' && parseFloat(min) < 0) { error = 'El stock mínimo no puede ser negativo.'; $tr.find('.nivel-minimo').addClass('is-invalid'); }
            else if (min !== '' && max !== '' && parseFloat(max) < parseFloat(min)) { error = 'El stock máximo no puede ser menor que el mínimo.'; $tr.find('.nivel-maximo').addClass('is-invalid'); }
            niveles.push({ boca_id: $tr.data('boca-id'), stock_minimo: min, stock_maximo: max });
        });
        return error || niveles;
    }

    function actualizarVisibilidadNiveles() {
        $('#seccionNivelesStock').toggleClass('d-none', !$('#controla_stock').is(':checked'));
    }
    $('#controla_stock').on('change', actualizarVisibilidadNiveles);

    // Al tildar "Es Servicio", inhabilita y saca el tilde de "Controla Stock"
    $('#es_servicio').on('change', function () {
        var esServ = $(this).is(':checked');
        if (esServ) {
            $('#alertaEsServicio').removeClass('d-none');
            $('#group_controla_stock').addClass('opacity-50');
            $('#controla_stock').prop('checked', false).prop('disabled', true);
        } else {
            $('#alertaEsServicio').addClass('d-none');
            $('#group_controla_stock').removeClass('opacity-50');
            $('#controla_stock').prop('disabled', false).prop('checked', true);
        }
        actualizarVisibilidadNiveles();
    });

    // Si el usuario cambia el tipo, se autocompleta la alícuota si la tiene por defecto
    $('#producto_tipo_id').on('change', function () {
        var tid = $(this).val();
        if (tid && tiposPorId[tid]) {
            var t = tiposPorId[tid];
            if (t.iva_alicuota_id && !$('#iva_alicuota_id').val()) {
                $('#iva_alicuota_id').val(t.iva_alicuota_id);
            }
            if (t.es_servicio !== undefined && !$('#producto_id').val()) {
                $('#es_servicio').prop('checked', t.es_servicio == 1).trigger('change');
            }
        }
    });

    // Botón "+ Agregar Producto" del header
    $(document).on('click', '#btnNuevo', function () {
        cargarCombos(function () {
            resetFormulario();
            $('#modalProducto').modal('show');
        });
    });

    // Editar producto
    function abrirEdicion(id, soloVer) {
        cargarCombos(function () {
            resetFormulario();
            Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

            $.get(AJAX_URL, { accion: 'obtener', producto_id: id, empresa_idx: empresa_idx }, function (res) {
                Swal.close();
                if (!res || !res.success || !res.data) {
                    Swal.fire({ icon: 'error', title: 'Error', text: (res && res.message) || 'No se pudo cargar el producto.' });
                    return;
                }
                var p = res.data;
                $('#producto_id').val(p.producto_id);
                $('#modalProductoTitulo').text('Editar Producto: ' + p.producto_codigo);

                $('#producto_codigo').val(p.producto_codigo);
                $('#producto_nombre').val(p.producto_nombre);
                $('#codigo_barras').val(p.codigo_barras || '');
                $('#producto_descripcion').val(p.producto_descripcion || '');

                $('#producto_tipo_id').val(p.producto_tipo_id);
                $('#producto_categoria_id').val(p.producto_categoria_id);
                $('#unidad_medida_id').val(p.unidad_medida_id || '');
                $('#iva_alicuota_id').val(p.iva_alicuota_id);
                if (PRES) PRES.cargar(p.producto_id, { soloLectura: !!soloVer || SOLO_LECTURA });

                $('#impuesto_interno_porcentaje').val(p.impuesto_interno_porcentaje || '');
                $('#impuesto_interno_fijo').val(p.impuesto_interno_fijo || '');

                $('#es_servicio').prop('checked', p.es_servicio == 1).trigger('change');
                if (p.es_servicio != 1) {
                    $('#controla_stock').prop('checked', p.controla_stock == 1);
                }
                actualizarVisibilidadNiveles();
                cargarNivelesStock(p.producto_id);
                $('#aplica_compra').prop('checked', p.aplica_compra == 1);
                $('#aplica_venta').prop('checked', p.aplica_venta == 1);

                $('#lado').val(p.lado || '');
                $('#material').val(p.material || '');
                $('#color').val(p.color || '');
                $('#peso').val(p.peso || '');
                $('#dimensiones').val(p.dimensiones || '');
                $('#garantia').val(p.garantia || '');

                $('#cont_cuenta_compra_id').val(p.cont_cuenta_compra_id || '');
                $('#cont_cuenta_venta_id').val(p.cont_cuenta_venta_id || '');
                $('#cont_cuenta_cmv_id').val(p.cont_cuenta_cmv_id || '');

                if (soloVer) {
                    // Solo ver: el formulario queda bloqueado y sin "Guardar" hasta cerrar el modal
                    $('#modalProductoTitulo').text('Producto: ' + p.producto_codigo);
                    $('#formProducto :input').prop('disabled', true);
                    $('#btnGuardarProducto').hide();
                    $('#modalProducto').one('hidden.bs.modal', function () {
                        $('#formProducto :input').prop('disabled', false);
                        $('#btnGuardarProducto').show();
                    });
                }
                $('#modalProducto').modal('show');
            }, 'json').fail(function () {
                Swal.close();
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error de red al obtener el producto.' });
            });
        });
    }

    // Guardar (Alta o Edición)
    $('#btnGuardarProducto').on('click', function () {
        var $form = $('#formProducto');
        if (!$form[0].checkValidity()) {
            $form.addClass('was-validated');
            return;
        }

        var isEdit = $('#producto_id').val() !== '';
        var accion = isEdit ? 'editar' : 'agregar';
        var formData = $form.serializeArray();
        formData.push({ name: 'accion', value: accion });
        formData.push({ name: 'empresa_idx', value: empresa_idx });
        formData.push({ name: 'pagina_idx', value: pagina_idx });

        // Los niveles solo viajan si el producto controla stock (el backend rechaza mínimos en los que no).
        if ($('#controla_stock').is(':checked')) {
            var nivelesStock = recolectarNivelesStock();
            if (typeof nivelesStock === 'string') {
                Swal.fire({ icon: 'warning', title: 'Stock mínimo / máximo', text: nivelesStock });
                return;
            }
            formData.push({ name: 'niveles_stock', value: JSON.stringify(nivelesStock) });
        }

        if (PRES) {
            var presentaciones = PRES.recolectar();
            if (typeof presentaciones === 'string') {
                Swal.fire({ icon: 'warning', title: 'Presentaciones', text: presentaciones });
                return;
            }
            formData.push({ name: 'presentaciones', value: JSON.stringify(presentaciones) });
        }

        $('#btnGuardarProducto').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.post(AJAX_URL, formData, function (res) {
            $('#btnGuardarProducto').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Producto');
            if (res && res.success) {
                if (res.aviso) {
                    // El producto se guardó pero los niveles no: el modal queda abierto para corregir.
                    if (!isEdit && res.producto_id) $('#producto_id').val(res.producto_id);
                    // Si se grabaron, se recargan con sus ids (si no, quedan como están para reintentar)
                    if (PRES && res.presentaciones_guardadas) PRES.cargar($('#producto_id').val());
                    if (tabla) tabla.ajax.reload(null, false);
                    Swal.fire({ icon: 'warning', title: 'Producto guardado', text: res.aviso });
                    return;
                }
                $('#modalProducto').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: '¡Guardado!',
                    text: res.message || 'El producto se guardó correctamente.',
                    timer: 1800,
                    showConfirmButton: false
                });
                if (tabla) tabla.ajax.reload(null, false);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'No se pudo guardar',
                    text: (res && res.message) || 'Ocurrió un error al procesar el guardado.'
                });
            }
        }, 'json').fail(function (xhr) {
            $('#btnGuardarProducto').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Producto');
            var msg = 'Error de comunicación con el servidor.';
            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            Swal.fire({ icon: 'error', title: 'Error', text: msg });
        });
    });

    // Executar acciones dinámicas de botones por fila (editar / inactivar / habilitar)
    $(document).on('click', '.btn-accion', function () {
        var id = $(this).data('id');
        var accion = $(this).data('accion');
        var confirmable = $(this).data('confirmable');

        if (accion === 'editar') {
            abrirEdicion(id);
            return;
        }
        if (accion === 'visualizar') {
            abrirEdicion(id, true);
            return;
        }

        function ejecutar() {
            $.post(AJAX_URL, {
                accion: 'ejecutar_accion',
                producto_id: id,
                accion_js: accion,
                empresa_idx: empresa_idx,
                pagina_idx: pagina_idx
            }, function (res) {
                if (res && res.success) {
                    Swal.fire({ icon: 'success', title: 'Actualizado', text: res.message, timer: 1500, showConfirmButton: false });
                    if (tabla) tabla.ajax.reload(null, false);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: (res && res.message) || 'No se pudo ejecutar la acción.' });
                }
            }, 'json').fail(function () {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error de red al ejecutar la acción.' });
            });
        }

        if (confirmable == 1) {
            Swal.fire({
                title: '¿Confirmar cambio?',
                text: '¿Está seguro de ejecutar esta acción sobre el producto?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'Cancelar'
            }).then(function (result) {
                if (result.isConfirmed) ejecutar();
            });
        } else {
            ejecutar();
        }
    });

    // ========== INICIALIZACIÓN DE LA PÁGINA ==========

    cargarBotonAgregar();
    cargarFiltroEstado(function () {
        inicializarTabla();
    });
});
