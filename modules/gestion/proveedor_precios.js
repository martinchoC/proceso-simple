$(function () {
    // Mismo criterio que ventas_pedidos.js: estas constantes las define el
    // bloque <script> inline de proveedor_precios.php, cargadas por PHP
    // desde $_GET['pagina_id'] / $_GET['empresa_id'] — no hay default
    // hardcodeado acá, si la URL no las trae, PAGINA_ID llega en 0.
    var empresa_idx = EMPRESA_ID;
    var pagina_idx = PAGINA_ID;

    var proveedorActualId = '';
    var proveedorActualNombre = '';
    var tabla = null;
    var listasPreciosActivas = []; // [{lista_precio_id, lista_precio_nombre}], se fija la primera vez que se carga la tabla
    var productoSeleccionado = null; // { producto_proveedor_id, producto_nombre, producto_codigo }
    var loteImportacion = []; // filas crudas leídas del Excel, antes de trocear

    var TAMANIO_LOTE = 300; // mismo tamaño de lote que listas_precios_productos.php

    inicializar();

    function inicializar() {
        cargarProveedores();
        cargarMonedas();

        $('#filtro_proveedor').on('change', function () {
            proveedorActualId = $(this).val();
            proveedorActualNombre = $(this).find(':selected').text();

            var haySeleccion = !!proveedorActualId;
            $('#btnAgregarPrecio, #btnImportarExcel, #btnActualizarCostosMasivo').prop('disabled', !haySeleccion);

            if (haySeleccion) {
                listasPreciosActivas = []; // fuerza reconstruir columnas para el proveedor nuevo
                cargarYMostrarTabla();
            }
        });

        $('#btnAgregarPrecio').on('click', abrirModalAgregar);
        $('#btnActualizarCostosMasivo').on('click', actualizarCostosMasivo);
        $('#btnGuardarPrecioProveedor').on('click', guardarPrecioProveedor);
        $('#btnImportarExcel').on('click', function () {
            resetModalImportacion();
            $('#modalImportarExcel').modal('show');
        });
        $('#inputArchivoExcel').on('change', leerArchivoExcel);
        $('#btnProcesarImportacion').on('click', procesarImportacionEnLotes);

        $('#buscadorProductoProveedor').on('keyup', debounce(buscarProductosProveedor, 300));

        $(document).on('click', '.btn-ver-historial', function () {
            abrirModalHistorial($(this).data('id'));
        });

        $(document).on('click', '.btn-estado-accion', function () {
            var id = $(this).data('id');
            var accionJs = $(this).data('accion');
            ejecutarAccionEstado(id, accionJs);
        });

        $(document).on('click', '.btn-editar-precio', function () {
            abrirModalEditar($(this).data('row'));
        });

        $(document).on('click', '.btn-actualizar-costo', function () {
            actualizarCostoProducto($(this).data('row'));
        });
    }

    function cargarProveedores() {
        $.get('proveedor_precios_ajax.php', { accion: 'obtener_proveedores', empresa_idx: empresa_idx }, function (data) {
            if (!Array.isArray(data)) {
                console.error('obtener_proveedores no devolvió un array. Respuesta cruda:', data);
                Swal.fire('Error cargando proveedores', (data && data.message) ? data.message : 'Respuesta inesperada del servidor (ver consola).', 'error');
                return;
            }

            var options = '<option value="">Seleccione un proveedor…</option>';
            data.forEach(function (p) {
                options += '<option value="' + p.entidad_id + '">' + escapeHtml(p.entidad_nombre) + '</option>';
            });
            $('#filtro_proveedor').html(options);

            if (!data.length) {
                console.warn('obtener_proveedores devolvió 0 proveedores para empresa_idx=' + empresa_idx +
                    '. Revisar que existan filas en gestion__entidades con es_proveedor=1, tabla_estado_registro_id=1 y ese empresa_id.');
            }
        }, 'json').fail(function (xhr) {
            console.error('Error HTTP cargando proveedores:', xhr.status, xhr.responseText);
            Swal.fire('Error cargando proveedores', 'Ver consola (F12) para el detalle.', 'error');
        });
    }

    function cargarMonedas() {
        $.get('proveedor_precios_ajax.php', { accion: 'obtener_monedas', empresa_idx: empresa_idx }, function (data) {
            var lista = Array.isArray(data) ? data : [];
            var options = '';
            lista.forEach(function (m) {
                options += '<option value="' + m.moneda_id + '">' + escapeHtml(m.moneda) + '</option>';
            });
            $('#moneda_id').html(options || '<option value="1">Pesos</option>');
        }, 'json').fail(function () {
            $('#moneda_id').html('<option value="1">Pesos</option>');
        });
    }

    // Trae {listas_precios, filas} del servidor. Se usa tanto para la carga
    // inicial (que además define las columnas dinámicas) como para cualquier
    // recarga posterior (guardar, importar, cambiar estado, botón Recargar).
    function cargarYMostrarTabla(callback) {
        $.get({
            url: 'proveedor_precios_ajax.php',
            dataType: 'json',
            data: { accion: 'listar', empresa_idx: empresa_idx, pagina_idx: pagina_idx, entidad_id: proveedorActualId }
        }).done(function (res) {
            var filas = (res && Array.isArray(res.filas)) ? res.filas : [];
            var listas = (res && Array.isArray(res.listas_precios)) ? res.listas_precios : [];

            if (!tabla || JSON.stringify(listas) !== JSON.stringify(listasPreciosActivas)) {
                listasPreciosActivas = listas;
                inicializarDataTable(filas);
            } else {
                tabla.clear().rows.add(filas).draw();
            }

            if (typeof callback === 'function') callback();
        }).fail(function (xhr) {
            console.error('Error cargando precios de proveedor:', xhr.status, xhr.responseText);
            Swal.fire('Error', 'No se pudo cargar el listado de precios.', 'error');
        });
    }

    function inicializarDataTable(filasIniciales) {
        if ($.fn.DataTable.isDataTable('#tablaProveedorPrecios')) {
            $('#tablaProveedorPrecios').DataTable().destroy();
            $('#tablaProveedorPrecios thead').empty();
        }

        // Cabecera de 2 filas: fila superior con un grupo por lista de
        // precios (colspan=2, coloreado), fila inferior con las subcolumnas
        // Actual / Debería ser. El resto de las columnas usa rowspan=2 para
        // no partirse. Paleta rotativa: no hay un color propio por lista en
        // gestion__listas_precios, así que se asigna por índice.
        var PALETA_LISTAS = ['#dc3545', '#198754', '#0d6efd', '#fd7e14', '#6f42c1', '#20c997', '#d63384', '#6c757d'];

        var theadFila1 = '<tr>' +
            '<th rowspan="2">Cód. Prov.</th><th rowspan="2">Código</th><th rowspan="2">Producto</th><th rowspan="2">Descripción</th>' +
            '<th rowspan="2" class="text-end">Precio Lista</th><th rowspan="2" class="text-end">Desc. %</th>' +
            '<th rowspan="2" class="text-end">Costo Neto Compra</th><th rowspan="2" class="text-end">Último Costo</th>' +
            '<th rowspan="2">Vigente desde</th>';
        var theadFila2 = '<tr>';
        listasPreciosActivas.forEach(function (lp, i) {
            var color = PALETA_LISTAS[i % PALETA_LISTAS.length];
            theadFila1 += '<th colspan="2" class="text-center text-white" style="background-color:' + color + ';">' + escapeHtml(lp.lista_precio_nombre) + '</th>';
            theadFila2 += '<th class="text-end">Actual</th><th class="text-end">Debería ser</th>';
        });
        theadFila1 += '<th rowspan="2" width="180" class="text-center">Acciones</th></tr>';
        theadFila2 += '</tr>';
        $('#tablaProveedorPrecios thead').html(theadFila1 + theadFila2);

        var columnasBase = [
            { data: 'codigo_proveedor', defaultContent: '' },
            { data: 'producto_codigo', defaultContent: '' },
            { data: 'producto_nombre' },
            { data: 'producto_descripcion', defaultContent: '', render: function (d) { return d ? escapeHtml(d) : ''; } },
            { data: 'precio_lista', className: 'text-end', render: function (d) { return formatearMoneda(d); } },
            { data: 'descuento_general_pct', className: 'text-end', render: function (d) { return (parseFloat(d) || 0).toFixed(2) + ' %'; } },
            { data: 'costo_neto_compra', className: 'text-end', render: function (d) { return '<strong>' + formatearMoneda(d) + '</strong>'; } },
            {
                // Último costo que ya está registrado en gestion__productos_costos,
                // para comparar contra la columna anterior ANTES de tocar
                // "Actualizar costo". null = el producto todavía no tiene costo cargado.
                data: 'costo_actual_registrado',
                className: 'text-end',
                render: function (d) { return (d === null || d === undefined) ? '<span class="text-muted">Sin costo</span>' : formatearMoneda(d); }
            },
            { data: 'f_vigencia_desde' }
        ];

        // Un par de columnas (Actual / Debería ser) por cada lista de precios
        // activa. "Debería ser" NO considera promociones, acumulación ni
        // prioridad (confirmado): toma costo_neto_compra y le aplica el
        // porcentaje de la regla vigente que matchee por producto, categoría
        // o proveedor — ver resolverReglaAplicable() en el _model.php.
        listasPreciosActivas.forEach(function (lp) {
            var lid = lp.lista_precio_id;
            columnasBase.push({
                data: 'precios_por_lista.' + lid + '.precio_actual',
                className: 'text-end',
                render: function (d) { return (d === null || d === undefined) ? '<span class="text-muted">Sin precio</span>' : formatearMoneda(d); }
            });
            columnasBase.push({
                data: null,
                className: 'text-end',
                render: function (row) {
                    var porLista = (row.precios_por_lista && row.precios_por_lista[lid]) || {};
                    var actual = porLista.precio_actual;
                    var deberiaSer = porLista.precio_deberia_ser;
                    if (deberiaSer === null || deberiaSer === undefined) {
                        return '<span class="text-muted">Sin regla</span>';
                    }
                    // Resalta si el precio "debería ser" difiere del actual, para
                    // que salte a la vista sin tener que comparar columna por columna.
                    // Umbral de 0.1: diferencias menores (redondeos) no se marcan
                    // como "distinto" — solo salta a la vista si realmente cambia.
                    var difiere = actual !== null && actual !== undefined && Math.abs(actual - deberiaSer) > 0.1;
                    var clase = difiere ? 'text-danger fw-bold' : '';
                    return '<span class="' + clase + '">' + formatearMoneda(deberiaSer) + '</span>';
                }
            });
        });

        columnasBase.push({
            data: null,
            orderable: false,
            render: function (row) {
                var rowJson = encodeURIComponent(JSON.stringify(row));
                var html = '<div class="btn-group">';
                html += '<button class="btn btn-sm btn-outline-secondary btn-editar-precio" data-row="' + rowJson + '" title="Editar precio"><i class="fas fa-pen"></i></button>';
                html += '<button class="btn btn-sm btn-outline-info btn-ver-historial" data-id="' + row.producto_proveedor_id + '" title="Ver historial"><i class="fas fa-history"></i></button>';
                html += '<button class="btn btn-sm btn-outline-success btn-actualizar-costo" data-row="' + rowJson + '" title="Actualizar costo del producto"><i class="fas fa-sync-alt"></i></button>';

                (row.botones || []).forEach(function (btn) {
                    html += '<button class="btn btn-sm ' + (btn.color_clase || 'btn-outline-primary') + ' btn-estado-accion" ' +
                        'data-id="' + row.producto_proveedor_precio_id + '" data-accion="' + btn.accion_js + '" title="' + escapeHtml(btn.descripcion || btn.nombre_funcion) + '">' +
                        '<i class="' + (btn.icono_clase || 'fas fa-cog') + '"></i></button>';
                });

                html += '</div>';
                return html;
            }
        });

        tabla = $('#tablaProveedorPrecios').DataTable({
            data: filasIniciales || [],
            // Mismo patrón que tablaVentasPedidos: dom + initComplete inyectando
            // el "Mostrar N registros" / buscador en el card-header, y una
            // instancia oculta de Buttons (excel/pdf/csv/print) que los botones
            // manuales del toolbar disparan por su clase. stateSave queda afuera
            // acá: como la tabla se reconstruye entera cuando cambian las
            // columnas (cambio de proveedor), guardar estado de columnas viejas
            // no tiene sentido.
            dom: '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>' +
                '<"clear">',
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            order: [[1, 'asc']],
            responsive: true,
            scrollX: true,
            initComplete: function () {
                setTimeout(function () {
                    if ($('#tablaProveedorPrecios_length').html().trim() === '') {
                        var selectHtml = '<label>Mostrar <select name="tablaProveedorPrecios_length" aria-controls="tablaProveedorPrecios" class="form-select form-select-sm"><option value="10" selected>10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option><option value="-1">Todos</option></select> registros</label>';
                        $('#tablaProveedorPrecios_length').html(selectHtml);
                        $('#tablaProveedorPrecios_length select').on('change', function () {
                            tabla.page.len($(this).val()).draw();
                        });
                    }
                    if ($('#tablaProveedorPrecios_filter').html().trim() === '') {
                        var filterHtml = '<label>Buscar:<input type="search" class="form-control form-control-sm" aria-controls="tablaProveedorPrecios"></label>';
                        $('#tablaProveedorPrecios_filter').html(filterHtml);
                        $('#tablaProveedorPrecios_filter input').on('keyup', function () {
                            tabla.search($(this).val()).draw();
                        });
                    }
                }, 100);

                // Instancia de Buttons oculta (no se agrega al DOM visible):
                // solo la usamos para que los botones del card-header
                // (#btnExportarExcel, etc.) la disparen por clase.
                new $.fn.dataTable.Buttons(tabla, {
                    buttons: ['excelHtml5', 'pdfHtml5', 'csvHtml5', 'print']
                });
            },
            columns: columnasBase,
            language: { url: '//cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json' }
        });

        inicializarToolbarTabla();
    }

    function inicializarToolbarTabla() {
        $('#btnRecargar').off('click').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true).find('i').addClass('fa-spin');
            cargarYMostrarTabla(function () {
                $btn.prop('disabled', false).find('i').removeClass('fa-spin');
            });
        });

        $('#btnExportarExcel').off('click').on('click', function () { tabla.button('.buttons-excel').trigger(); });
        $('#btnExportarPDF').off('click').on('click', function () { tabla.button('.buttons-pdf').trigger(); });
        $('#btnExportarCSV').off('click').on('click', function () { tabla.button('.buttons-csv').trigger(); });
        $('#btnExportarPrint').off('click').on('click', function () { tabla.button('.buttons-print').trigger(); });
    }

    /* ============================ Alta / edición manual ============================ */


    function abrirModalAgregar() {
        $('#producto_proveedor_id').val('');
        $('#producto_proveedor_precio_id').val('');
        $('#precio_lista').val('');
        $('#f_vigencia_desde').val(new Date().toISOString().slice(0, 10));
        productoSeleccionado = null;

        $('#bloqueBuscarProducto').removeClass('d-none');
        $('#bloqueProductoSeleccionado').addClass('d-none');
        $('#buscadorProductoProveedor').val('');
        $('#resultadosBuscadorProducto').empty();

        $('#modalPrecioProveedorTitulo').text('Nuevo Precio — ' + proveedorActualNombre);
        $('#modalPrecioProveedor').modal('show');
    }

    function abrirModalEditar(rowEncoded) {
        var row = JSON.parse(decodeURIComponent(rowEncoded));

        $('#producto_proveedor_id').val(row.producto_proveedor_id);
        $('#producto_proveedor_precio_id').val(row.producto_proveedor_precio_id);
        $('#precio_lista').val(row.precio_lista);
        $('#moneda_id').val(row.moneda_id);
        $('#f_vigencia_desde').val(new Date().toISOString().slice(0, 10));

        productoSeleccionado = row;
        $('#bloqueBuscarProducto').addClass('d-none');
        $('#bloqueProductoSeleccionado').removeClass('d-none');
        $('#productoSeleccionadoNombre').text(row.producto_nombre);
        $('#productoSeleccionadoCodigo').text(' (' + row.codigo_proveedor + ')');

        $('#modalPrecioProveedorTitulo').text('Editar Precio — ' + proveedorActualNombre);
        $('#modalPrecioProveedor').modal('show');
    }

    function buscarProductosProveedor() {
        var q = $('#buscadorProductoProveedor').val().trim();
        if (q.length < 2 || !proveedorActualId) {
            $('#resultadosBuscadorProducto').empty();
            return;
        }

        $.get('proveedor_precios_ajax.php', { accion: 'buscar_productos_sin_precio', empresa_idx: empresa_idx, entidad_id: proveedorActualId, q: q }, function (data) {
            var html = '';
            if (!data || !data.length) {
                html = '<div class="text-muted small p-2">Sin resultados (o el producto ya tiene precio cargado).</div>';
            } else {
                data.forEach(function (p) {
                    html += '<button type="button" class="list-group-item list-group-item-action btn-seleccionar-producto" ' +
                        'data-producto-proveedor-id="' + p.producto_proveedor_id + '" ' +
                        'data-nombre="' + escapeHtml(p.producto_nombre) + '" data-codigo="' + escapeHtml(p.codigo_proveedor || '') + '">' +
                        '<strong>' + escapeHtml(p.producto_nombre) + '</strong> ' +
                        '<span class="text-muted small">Cód. proveedor: ' + escapeHtml(p.codigo_proveedor || '-') + '</span>' +
                        '</button>';
                });
            }
            $('#resultadosBuscadorProducto').html(html);
        }, 'json');
    }

    $(document).on('click', '.btn-seleccionar-producto', function () {
        var $btn = $(this);
        productoSeleccionado = {
            producto_proveedor_id: $btn.data('producto-proveedor-id'),
            producto_nombre: $btn.data('nombre'),
            codigo_proveedor: $btn.data('codigo')
        };
        $('#producto_proveedor_id').val(productoSeleccionado.producto_proveedor_id);

        $('#bloqueBuscarProducto').addClass('d-none');
        $('#bloqueProductoSeleccionado').removeClass('d-none');
        $('#productoSeleccionadoNombre').text(productoSeleccionado.producto_nombre);
        $('#productoSeleccionadoCodigo').text(' (' + productoSeleccionado.codigo_proveedor + ')');
    });

    function guardarPrecioProveedor() {
        var producto_proveedor_id = $('#producto_proveedor_id').val();
        var precio_lista = parseFloat($('#precio_lista').val());

        if (!producto_proveedor_id) {
            return Swal.fire('Atención', 'Debe seleccionar un producto del proveedor.', 'warning');
        }
        if (isNaN(precio_lista) || precio_lista < 0) {
            return Swal.fire('Atención', 'El precio de lista debe ser un número mayor o igual a 0.', 'warning');
        }

        var accion = $('#producto_proveedor_precio_id').val() ? 'editar' : 'agregar';

        $.post('proveedor_precios_ajax.php', {
            accion: accion,
            empresa_idx: empresa_idx,
            pagina_idx: pagina_idx,
            producto_proveedor_id: producto_proveedor_id,
            entidad_id: proveedorActualId,
            precio_lista: precio_lista,
            moneda_id: $('#moneda_id').val(),
            f_vigencia_desde: $('#f_vigencia_desde').val()
        }, function (res) {
            if (res.success) {
                $('#modalPrecioProveedor').modal('hide');
                cargarYMostrarTabla();
                Swal.fire({ icon: 'success', title: res.message, timer: 1500, showConfirmButton: false });
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json').fail(function () {
            Swal.fire('Error', 'No se pudo guardar el precio.', 'error');
        });
    }

    /* ============================ Estados (habilitar/inhabilitar) ============================ */

    function ejecutarAccionEstado(id, accionJs) {
        Swal.fire({
            title: '¿Confirmar acción?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, continuar'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.post('proveedor_precios_ajax.php', {
                accion: 'ejecutar_accion',
                empresa_idx: empresa_idx,
                pagina_idx: pagina_idx,
                producto_proveedor_precio_id: id,
                accion_js: accionJs
            }, function (res) {
                if (res.success) {
                    cargarYMostrarTabla();
                    Swal.fire({ icon: 'success', title: res.message, timer: 1200, showConfirmButton: false });
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json');
        });
    }

    /* ============================ Historial ============================ */

    function abrirModalHistorial(productoProveedorId) {
        $.get('proveedor_precios_ajax.php', { accion: 'obtener_historial', producto_proveedor_id: productoProveedorId }, function (data) {
            var $tbody = $('#tablaHistorialPrecio tbody').empty();
            if (!data || !data.length) {
                $tbody.append('<tr><td colspan="6" class="text-center text-muted">Sin historial todavía.</td></tr>');
            } else {
                data.forEach(function (h) {
                    $tbody.append(
                        '<tr>' +
                        '<td>' + h.f_vigencia_desde + '</td>' +
                        '<td>' + (h.f_vigencia_hasta || '-') + '</td>' +
                        '<td>' + formatearMoneda(h.precio_lista) + '</td>' +
                        '<td>' + (h.simbolo || '') + '</td>' +
                        '<td>' + h.origen_carga + '</td>' +
                        '<td>' + (h.archivo_importacion || '-') + '</td>' +
                        '</tr>'
                    );
                });
            }
            $('#modalHistorialPrecio').modal('show');
        }, 'json');
    }

    /* ============================ Actualizar costo del producto ============================ */

    function actualizarCostoProducto(rowEncoded) {
        var row = JSON.parse(decodeURIComponent(rowEncoded));

        Swal.fire({
            title: 'Actualizar costo del producto',
            html: 'Se va a fijar el costo de <strong>' + escapeHtml(row.producto_nombre) + '</strong> en ' +
                  '<strong>' + formatearMoneda(row.costo_neto_compra) + '</strong> (precio de lista menos el descuento de compra vigente).' +
                  '<br><small class="text-muted">Esto NO recalcula automáticamente la lista de precios de venta al cliente — eso queda pendiente hasta definir el motor de reglas.</small>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, actualizar costo'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.post('proveedor_precios_ajax.php', {
                accion: 'actualizar_costo',
                empresa_idx: empresa_idx,
                producto_id: row.producto_id,
                entidad_id: proveedorActualId,
                costo_neto_compra: row.costo_neto_compra,
                moneda_id: row.moneda_id
            }, function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: res.message, timer: 1500, showConfirmButton: false });
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json');
        });
    }

    function actualizarCostosMasivo() {
        if (!proveedorActualId) return;

        Swal.fire({
            title: 'Actualizar costos de TODOS los productos',
            html: 'Se va a recalcular y fijar el costo (precio de lista menos el descuento de compra vigente) de <strong>todos los productos activos</strong> de <strong>' + escapeHtml(proveedorActualNombre) + '</strong>.' +
                  '<br><small class="text-muted">Esto NO recalcula la lista de precios de venta al cliente.</small>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, actualizar todos'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            Swal.fire({ title: 'Actualizando…', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

            $.post({
                url: 'proveedor_precios_ajax.php',
                dataType: 'json',
                data: {
                    accion: 'actualizar_costos_masivo',
                    empresa_idx: empresa_idx,
                    entidad_id: proveedorActualId
                }
            }, function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: res.message });
                    cargarYMostrarTabla();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json').fail(function () {
                Swal.fire('Error', 'No se pudo completar la actualización masiva.', 'error');
            });
        });
    }

    /* ============================ Importación de Excel ============================ */

    function resetModalImportacion() {
        loteImportacion = [];
        $('#inputArchivoExcel').val('');
        $('#progressImportacion').addClass('d-none').find('.progress-bar').css('width', '0%');
        $('#resumenImportacion').empty();
    }

    function leerArchivoExcel(e) {
        var file = e.target.files[0];
        if (!file) return;

        var reader = new FileReader();
        reader.onload = function (ev) {
            // cellDates:true para que una celda con formato Fecha llegue como
            // objeto Date de JS en vez de como número de serie de Excel — sin
            // esto, f_vigencia_desde llegaría como "45932" en vez de una fecha.
            var workbook = XLSX.read(new Uint8Array(ev.target.result), { type: 'array', cellDates: true });
            var hoja = workbook.Sheets[workbook.SheetNames[0]];
            var filas = XLSX.utils.sheet_to_json(hoja, { defval: '' });

            loteImportacion = filas.map(function (f) {
                return {
                    codigo_proveedor: String(f.codigo_proveedor ?? f.CODIGO_PROVEEDOR ?? '').trim(),
                    descripcion: String(f.descripcion ?? f.DESCRIPCION ?? '').trim(),
                    precio: parseFloat(f.precio ?? f.PRECIO ?? 0) || 0,
                    f_vigencia_desde: normalizarFechaExcel(f.f_vigencia_desde ?? f.F_VIGENCIA_DESDE ?? f.vigencia_desde ?? f.VIGENCIA_DESDE ?? '')
                };
            }).filter(function (f) { return f.codigo_proveedor !== ''; });

            var sinFecha = loteImportacion.filter(function (f) { return !f.f_vigencia_desde; }).length;
            var aviso = sinFecha
                ? ' <span class="text-warning">' + sinFecha + ' fila(s) sin fecha de vigencia válida — se van a cargar con la fecha de hoy.</span>'
                : '';
            $('#resumenImportacion').html('<div class="alert alert-info">Se detectaron <strong>' + loteImportacion.length + '</strong> filas con código válido.' + aviso + '</div>');
        };
        reader.readAsArrayBuffer(file);
    }

    function procesarImportacionEnLotes() {
        if (!loteImportacion.length) {
            return Swal.fire('Atención', 'Cargue primero un archivo Excel.', 'warning');
        }
        if (!proveedorActualId) {
            return Swal.fire('Atención', 'Seleccione un proveedor.', 'warning');
        }

        var archivoNombre = $('#inputArchivoExcel')[0].files[0].name;
        var totalLotes = Math.ceil(loteImportacion.length / TAMANIO_LOTE);
        var loteActual = 0;

        var acumulado = { procesados: 0, sin_cambios: 0, errores_count: 0, no_vinculados: [] };

        $('#progressImportacion').removeClass('d-none');
        $('#btnProcesarImportacion').prop('disabled', true);

        procesarSiguienteLote();

        function procesarSiguienteLote() {
            var desde = loteActual * TAMANIO_LOTE;
            var items = loteImportacion.slice(desde, desde + TAMANIO_LOTE);

            if (!items.length) {
                return finalizarImportacion();
            }

            $.post('proveedor_precios_ajax.php', {
                accion: 'importar_lote',
                empresa_idx: empresa_idx,
                pagina_idx: pagina_idx,
                entidad_id: proveedorActualId,
                archivo_nombre: archivoNombre,
                items: JSON.stringify(items)
            }, function (res) {
                if (res.success) {
                    acumulado.procesados += res.procesados;
                    acumulado.sin_cambios += res.sin_cambios;
                    acumulado.errores_count += res.errores_count;
                    acumulado.no_vinculados = acumulado.no_vinculados.concat(res.no_vinculados || []);
                } else {
                    acumulado.errores_count += items.length;
                }

                loteActual++;
                var pct = Math.round((loteActual / totalLotes) * 100);
                $('#progressImportacion .progress-bar').css('width', pct + '%').text(pct + '%');

                procesarSiguienteLote();
            }, 'json').fail(function () {
                acumulado.errores_count += items.length;
                loteActual++;
                procesarSiguienteLote();
            });
        }

        function finalizarImportacion() {
            $('#btnProcesarImportacion').prop('disabled', false);

            var html = '<div class="alert alert-success">Procesados: ' + acumulado.procesados +
                ' — Sin cambios: ' + acumulado.sin_cambios +
                ' — Errores: ' + acumulado.errores_count + '</div>';

            if (acumulado.no_vinculados.length) {
                html += '<div class="alert alert-warning"><strong>' + acumulado.no_vinculados.length + '</strong> códigos no vinculados a ningún producto de este proveedor:</div>';
                html += '<div style="max-height:200px; overflow-y:auto;"><table class="table table-sm"><thead><tr><th>Código</th><th>Descripción</th><th>Precio</th></tr></thead><tbody>';
                acumulado.no_vinculados.forEach(function (n) {
                    html += '<tr><td>' + escapeHtml(n.codigo_proveedor) + '</td><td>' + escapeHtml(n.descripcion) + '</td><td>' + formatearMoneda(n.precio) + '</td></tr>';
                });
                html += '</tbody></table></div>';
            }

            $('#resumenImportacion').html(html);
            cargarYMostrarTabla();
        }
    }

    /* ============================ Utilidades ============================ */

    function normalizarFechaExcel(valor) {
        if (!valor) return '';
        if (valor instanceof Date && !isNaN(valor)) {
            return valor.toISOString().slice(0, 10);
        }
        var texto = String(valor).trim();
        // Acepta 'YYYY-MM-DD' directo; si viene 'DD/MM/YYYY' (texto plano, celda
        // sin formato Fecha) lo reordena.
        if (/^\d{4}-\d{2}-\d{2}$/.test(texto)) return texto;
        var m = texto.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (m) return m[3] + '-' + m[2].padStart(2, '0') + '-' + m[1].padStart(2, '0');
        return '';
    }

    function formatearMoneda(valor) {
        return (parseFloat(valor) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(str) {
        return $('<div>').text(str || '').html();
    }

    function debounce(fn, wait) {
        var t;
        return function () {
            clearTimeout(t);
            var args = arguments;
            t = setTimeout(function () { fn.apply(null, args); }, wait);
        };
    }
});
