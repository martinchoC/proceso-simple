$(document).ready(function () {
    var empresa_idx = EMPRESA_ID;
    var pagina_idx = PAGINA_ID;

    var tabla;
    var currentPage = 0;
    var currentOrder = [[1, 'asc']];

    function escapeHtml(texto) {
        return $('<div>').text(texto || '').html();
    }

    // ========== DASHBOARD ==========
    function statTile(label, value, colorClass) {
        return '<div class="col-6 col-lg-3">' +
            '<div class="card stat-tile h-100"><div class="card-body py-2 px-3">' +
            '<div class="stat-tile-label">' + escapeHtml(label) + '</div>' +
            '<div class="stat-tile-value ' + (colorClass || '') + '">' + value + '</div>' +
            '</div></div></div>';
    }

    // Barras horizontales simples en HTML/CSS puro (ver skill dataviz: un solo
    // hue secuencial, marca <=16px con extremos redondeados, tooltip nativo).
    // opciones: {labelKey, valueKey, formatValue(d), pctKey (opcional; si no
    // se pasa, el ancho se calcula contra el máximo del propio set)}
    function renderBarChart($container, datos, opciones) {
        if (!datos || datos.length === 0) {
            $container.html('<p class="text-muted small mb-0 py-3 text-center">Sin datos para mostrar</p>');
            return;
        }
        var max = opciones.pctKey ? 100 : Math.max.apply(null, datos.map(function (d) { return d[opciones.valueKey] || 0; }));
        var html = '';
        datos.forEach(function (d) {
            var valor = d[opciones.valueKey] || 0;
            var pct = opciones.pctKey ? (d[opciones.pctKey] || 0) : (max > 0 ? (valor / max * 100) : 0);
            var valorTexto = opciones.formatValue ? opciones.formatValue(d) : String(valor);
            var nombre = d[opciones.labelKey] || '';
            html += '<div class="viz-bar-row" title="' + escapeHtml(nombre + ': ' + valorTexto) + '">' +
                '<div class="viz-bar-label">' + escapeHtml(nombre) + '</div>' +
                '<div class="viz-bar-track"><div class="viz-bar-fill" style="width:' + Math.max(pct, 2) + '%"></div></div>' +
                '<div class="viz-bar-value">' + escapeHtml(valorTexto) + '</div>' +
                '</div>';
        });
        $container.html(html);
    }

    function formatMiles(n) {
        return (n || 0).toLocaleString('es-AR');
    }

    // Misma fila de KPIs en los dos lugares que la muestran (Listado y
    // Dashboard) — un solo renderer para que nunca se desincronicen.
    function renderKpiRow($container, r) {
        r = r || {};
        var pct = r.porcentaje || 0;
        var colorPct = pct >= 75 ? 'text-success' : (pct >= 40 ? 'text-warning' : 'text-danger');
        $container.html(
            statTile('Productos activos', formatMiles(r.total_productos)) +
            statTile('Con proveedor vinculado', formatMiles(r.con_vinculo), 'text-success') +
            statTile('Sin vincular', formatMiles(r.sin_vinculo), 'text-muted') +
            statTile('Correlación', pct + '%', colorPct)
        );
    }

    function cargarDashboard() {
        $.get('productos_proveedores_ajax.php', {
            accion: 'obtener_dashboard',
            empresa_idx: empresa_idx
        }, function (data) {
            if (!data) return;

            renderKpiRow($('#dashboardKpis'), data.resumen);

            renderBarChart($('#chartTopProveedores'), data.top_proveedores, {
                labelKey: 'entidad_nombre',
                valueKey: 'cantidad',
                formatValue: function (d) { return formatMiles(d.cantidad); }
            });

            renderBarChart($('#chartCorrelacionMarca'), data.correlacion_marca, {
                labelKey: 'marca_nombre',
                valueKey: 'porcentaje',
                pctKey: 'porcentaje',
                formatValue: function (d) { return d.porcentaje + '%'; }
            });

            $('#badgeSinVinculos').text(data.total_proveedores_sin_vinculos || 0);
            var filasHtml = '';
            (data.proveedores_sin_vinculos || []).forEach(function (p) {
                filasHtml += '<tr><td>' + escapeHtml(p.entidad_nombre) + '</td><td>' + escapeHtml(p.cuit || '') + '</td></tr>';
            });
            $('#tablaProveedoresSinVinculos tbody').html(
                filasHtml || '<tr><td colspan="2" class="text-center text-muted py-3">Todos los proveedores activos tienen al menos un producto vinculado</td></tr>'
            );
        }, 'json').fail(function (xhr) {
            $('#dashboardKpis').html('<div class="col-12"><div class="alert alert-danger mb-0">No se pudo cargar el dashboard.</div></div>');
            console.error(xhr.responseText);
        });
    }

    $('#tab-dashboard-btn').on('shown.bs.tab', function () {
        cargarDashboard();
    });

    // ========== COMBOS DE MARCA / MODELO / SUBMODELO (cascada) ==========
    function cargarComboMarcas() {
        $.get('productos_proveedores_ajax.php', { accion: 'obtener_marcas' }, function (data) {
            var options = '<option value="">Todas las marcas</option>';
            if (data && data.length > 0) {
                data.forEach(function (item) {
                    options += '<option value="' + item.marca_id + '">' + escapeHtml(item.marca_nombre) + '</option>';
                });
            }
            $('#filtroMarca').html(options);
        }, 'json');
    }

    $('#filtroMarca').on('change', function () {
        var marcaId = $(this).val();
        $('#filtroModelo').html('<option value="">Todos los modelos</option>').prop('disabled', !marcaId);
        $('#filtroSubmodelo').html('<option value="">Todos los submodelos</option>').prop('disabled', true);

        if (marcaId) {
            $.get('productos_proveedores_ajax.php', { accion: 'obtener_modelos', marca_id: marcaId }, function (data) {
                var options = '<option value="">Todos los modelos</option>';
                if (data && data.length > 0) {
                    data.forEach(function (item) {
                        options += '<option value="' + item.modelo_id + '">' + escapeHtml(item.modelo_nombre) + '</option>';
                    });
                }
                $('#filtroModelo').html(options).prop('disabled', false);
            }, 'json');
        }
        tabla.ajax.reload();
    });

    $('#filtroModelo').on('change', function () {
        var modeloId = $(this).val();
        $('#filtroSubmodelo').html('<option value="">Todos los submodelos</option>').prop('disabled', !modeloId);

        if (modeloId) {
            $.get('productos_proveedores_ajax.php', { accion: 'obtener_submodelos', modelo_id: modeloId }, function (data) {
                var options = '<option value="">Todos los submodelos</option>';
                if (data && data.length > 0) {
                    data.forEach(function (item) {
                        options += '<option value="' + item.submodelo_id + '">' + escapeHtml(item.submodelo_nombre) + '</option>';
                    });
                }
                $('#filtroSubmodelo').html(options).prop('disabled', false);
            }, 'json');
        }
        tabla.ajax.reload();
    });

    $('#filtroSubmodelo').on('change', function () {
        tabla.ajax.reload();
    });

    // ========== COMBO DE PROVEEDORES ==========
    function cargarComboProveedores() {
        $.get('productos_proveedores_ajax.php', {
            accion: 'obtener_proveedores',
            empresa_idx: empresa_idx
        }, function (data) {
            var options = '<option value="">Todos los proveedores</option>';
            var optionsModal = '<option value="">Seleccione un proveedor…</option>';
            if (data && data.length > 0) {
                data.forEach(function (item) {
                    options += '<option value="' + item.entidad_id + '">' + escapeHtml(item.entidad_nombre) + '</option>';
                    optionsModal += '<option value="' + item.entidad_id + '">' + escapeHtml(item.entidad_nombre) + '</option>';
                });
            }
            $('#filtroProveedor').html(options);
            $('#vinculo_entidad_id').html(optionsModal);
        }, 'json');
    }

    // ========== RENDER DE CHIPS DE VÍNCULOS ==========
    function renderVinculos(vinculos, row) {
        if (!vinculos || vinculos.length === 0) {
            return '<span class="text-muted small">Sin proveedores vinculados</span>';
        }
        var html = '';
        vinculos.forEach(function (v) {
            html += '<span class="chip-vinculo">' +
                '<span>' + escapeHtml(v.entidad_nombre) + ':</span>' +
                '<span class="chip-codigo">' + escapeHtml(v.codigo_proveedor || '(sin código)') + '</span>' +
                '<i class="fas fa-pen chip-accion chip-editar" title="Editar código" ' +
                'data-id="' + v.producto_proveedor_id + '" data-codigo="' + escapeHtml(v.codigo_proveedor) + '" ' +
                'data-nombre="' + escapeHtml(v.entidad_nombre) + '"></i>' +
                '<i class="fas fa-times chip-accion chip-quitar" title="Quitar vínculo" ' +
                'data-id="' + v.producto_proveedor_id + '" ' +
                'data-nombre="' + escapeHtml(v.entidad_nombre) + '"></i>' +
                '</span>';
        });
        return html;
    }

    // ========== DATATABLE ==========
    function inicializarDataTable() {
        tabla = $('#tablaProductosProveedores').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: 'productos_proveedores_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                    d.filtro_texto = $('#filtroTexto').val();
                    d.filtro_proveedor_id = $('#filtroProveedor').val();
                    d.filtro_vinculo = $('#filtroVinculo').val();
                    d.filtro_marca = $('#filtroMarca').val();
                    d.filtro_modelo = $('#filtroModelo').val();
                    d.filtro_submodelo = $('#filtroSubmodelo').val();
                }
            },
            dom: '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-12 col-md-5"li><"col-sm-12 col-md-7"p>>' +
                '<"clear">',
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            searching: false,
            order: currentOrder,
            columns: [
                {
                    data: 'producto_codigo',
                    className: 'text-center fw-medium'
                },
                {
                    data: 'producto_nombre',
                    className: 'text-start',
                    render: function (data, type, row) {
                        if (type !== 'display') return data;
                        var html = '<span>' + escapeHtml(data) + '</span>';
                        if (row.compatibilidad_texto) {
                            html += '<span class="compatibilidad-texto"><i class="fas fa-car-side me-1"></i>' + escapeHtml(row.compatibilidad_texto) + '</span>';
                        }
                        return html;
                    }
                },
                {
                    data: 'cantidad_vinculos',
                    orderable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (type !== 'display') return data || 0;
                        return '<span class="badge bg-secondary">' + (data || 0) + '</span>';
                    }
                },
                {
                    data: 'vinculos',
                    orderable: false,
                    className: 'text-start',
                    render: function (data, type, row) {
                        if (type !== 'display') return row.cantidad_vinculos || 0;
                        return renderVinculos(data, row);
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        return '<button type="button" class="btn btn-outline-primary btn-vincular btn-agregar-vinculo" ' +
                            'data-producto-id="' + row.producto_id + '" data-producto-nombre="' + escapeHtml(row.producto_nombre) + '" ' +
                            'title="Vincular proveedor"><i class="fas fa-plus"></i></button>';
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json'
            },
            responsive: true,
            initComplete: function () {
                // Solo se instancia la extensión Buttons (para que
                // tabla.button('.buttons-excel'/'.buttons-pdf').trigger()
                // funcione desde el toolbar de arriba) — sin agregar su UI
                // propia al wrapper, para no duplicar los botones de Excel/PDF.
                new $.fn.dataTable.Buttons(tabla, {
                    buttons: ['excelHtml5', 'pdfHtml5']
                });

                $(tabla.table().container()).on('page.dt', function () {
                    currentPage = tabla.page();
                });
                $(tabla.table().container()).on('order.dt', function () {
                    currentOrder = tabla.order();
                });
            }
        });
    }

    function recargarTabla() {
        if (tabla) tabla.ajax.reload(null, false);
        // Si la pestaña Dashboard está activa, refresca también sus datos
        // para que no queden desactualizados tras un alta/edición/baja de vínculo.
        if ($('#tab-dashboard').hasClass('active')) {
            cargarDashboard();
        }
    }

    // ========== FILTROS ==========
    var filtroTimeout;
    $('#filtroTexto').on('keyup', function () {
        clearTimeout(filtroTimeout);
        filtroTimeout = setTimeout(function () {
            tabla.ajax.reload();
        }, 400);
    });
    $('#filtroProveedor, #filtroVinculo').on('change', function () {
        tabla.ajax.reload();
    });

    $('#btnRecargar').on('click', function () {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        recargarTabla();
        setTimeout(function () { btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>'); }, 500);
    });
    $('#btnExportarExcel').on('click', function (e) {
        e.preventDefault();
        if (tabla) tabla.button('.buttons-excel').trigger();
    });
    $('#btnExportarPDF').on('click', function (e) {
        e.preventDefault();
        if (tabla) tabla.button('.buttons-pdf').trigger();
    });

    // ========== MODAL: AGREGAR VÍNCULO ==========
    $(document).on('click', '.btn-agregar-vinculo', function () {
        var productoId = $(this).data('producto-id');
        var productoNombre = $(this).data('producto-nombre');

        $('#vinculo_producto_id').val(productoId);
        $('#vinculo_producto_proveedor_id').val('');
        $('#vinculoProductoNombre').text(productoNombre);
        $('#vinculo_entidad_id').val('');
        $('#vinculo_codigo_proveedor').val('');
        $('#modalVinculoProveedorTitulo').text('Vincular proveedor');

        new bootstrap.Modal(document.getElementById('modalVinculoProveedor')).show();
    });

    $('#btnGuardarVinculo').on('click', function () {
        var productoId = $('#vinculo_producto_id').val();
        var entidadId = $('#vinculo_entidad_id').val();
        var codigo = $('#vinculo_codigo_proveedor').val();

        if (!entidadId) {
            Swal.fire({ icon: 'warning', title: 'Falta el proveedor', text: 'Seleccioná un proveedor para vincular.' });
            return;
        }

        var btn = $(this);
        var originalText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.post('productos_proveedores_ajax.php', {
            accion: 'agregar',
            empresa_idx: empresa_idx,
            producto_id: productoId,
            entidad_id: entidadId,
            codigo_proveedor: codigo
        }, function (res) {
            btn.prop('disabled', false).html(originalText);
            if (res.success) {
                var modalEl = document.getElementById('modalVinculoProveedor');
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                recargarTabla();
                Swal.fire({
                    icon: 'success', title: '¡Vinculado!', text: res.message,
                    showConfirmButton: false, timer: 1500, toast: true, position: 'top-end'
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo vincular el proveedor' });
            }
        }, 'json').fail(function (xhr) {
            btn.prop('disabled', false).html(originalText);
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo comunicar con el servidor' });
            console.error(xhr.responseText);
        });
    });

    // ========== EDITAR CÓDIGO (inline, vía SweetAlert) ==========
    $(document).on('click', '.chip-editar', function () {
        var id = $(this).data('id');
        var codigoActual = $(this).data('codigo') || '';
        var nombreProveedor = $(this).data('nombre');

        Swal.fire({
            title: 'Editar código de ' + nombreProveedor,
            input: 'text',
            inputValue: codigoActual,
            inputAttributes: { maxlength: 50 },
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.post('productos_proveedores_ajax.php', {
                accion: 'editar',
                empresa_idx: empresa_idx,
                producto_proveedor_id: id,
                codigo_proveedor: result.value
            }, function (res) {
                if (res.success) {
                    recargarTabla();
                    Swal.fire({
                        icon: 'success', title: '¡Actualizado!', showConfirmButton: false,
                        timer: 1200, toast: true, position: 'top-end'
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo actualizar el código' });
                }
            }, 'json');
        });
    });

    // ========== QUITAR VÍNCULO (baja rápida) ==========
    $(document).on('click', '.chip-quitar', function () {
        var id = $(this).data('id');
        var nombreProveedor = $(this).data('nombre');

        Swal.fire({
            title: '¿Quitar vínculo?',
            html: '¿Quitar el vínculo con <strong>' + nombreProveedor + '</strong>?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, quitar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.post('productos_proveedores_ajax.php', {
                accion: 'ejecutar_accion',
                empresa_idx: empresa_idx,
                pagina_idx: pagina_idx,
                producto_proveedor_id: id,
                accion_js: 'inactivar'
            }, function (res) {
                if (res.success) {
                    recargarTabla();
                    Swal.fire({
                        icon: 'success', title: '¡Quitado!', showConfirmButton: false,
                        timer: 1200, toast: true, position: 'top-end'
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo quitar el vínculo' });
                }
            }, 'json');
        });
    });

    // ========== INICIALIZACIÓN ==========
    inicializarDataTable();
    cargarComboProveedores();
    cargarComboMarcas();

    $('[title]').tooltip({ trigger: 'hover', placement: 'top' });
});
