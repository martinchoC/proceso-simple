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
            $('#btnAgregarPrecio, #btnImportarExcel, #btnActualizarCostosMasivo, #btnActualizarSoloCostosMasivo, #btnActualizarSoloPreciosMasivo, #btnAplicarPorcentajeCostos').prop('disabled', !haySeleccion);

            // Cambiar de proveedor (o pasar a "todos") fuerza reconstruir
            // columnas — puede cambiar si mostramos o no la columna Proveedor.
            listasPreciosActivas = [];
            intentarCargarTabla();
        });

        $('#btnAgregarPrecio').on('click', abrirModalAgregar);
        $('#btnActualizarCostosMasivo').on('click', actualizarCostosMasivo);
        $('#btnActualizarSoloCostosMasivo').on('click', function () { actualizarMasivoGenerico('actualizar_solo_costos_masivo', 'Actualizar costos', 'Se va a recalcular y fijar el costo de <strong>todos los productos activos y filtrados</strong> de este proveedor. No toca las listas de precio de venta.'); });
        $('#btnActualizarSoloPreciosMasivo').on('click', function () { actualizarMasivoGenerico('actualizar_solo_precios_masivo', 'Actualizar precios de venta', 'Se va a recalcular el precio de venta de <strong>todos los productos activos</strong> de este proveedor, usando el costo YA registrado de cada uno. No toca gestion__productos_costos.'); });
        $('#btnAplicarPorcentajeCostos').on('click', aplicarPorcentajeCostos);
        $('#btnGuardarPrecioProveedor').on('click', guardarPrecioProveedor);
        $('#btnImportarExcel').on('click', function () {
            resetModalImportacion();
            $('#modalImportarExcel').modal('show');
        });
        $('#inputArchivoExcel').on('change', leerArchivoExcel);
        $('#btnProcesarImportacion').on('click', procesarImportacionEnLotes);

        inicializarFiltrosCompatibilidad();

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
            actualizarFila($(this).data('row'), 'actualizar_costo');
        });

        $(document).on('click', '.btn-actualizar-precio-venta', function () {
            actualizarFila($(this).data('row'), 'actualizar_precio_venta');
        });

        $(document).on('click', '.btn-actualizar-costo-y-precio', function () {
            actualizarFila($(this).data('row'), 'actualizar_costo_y_precio');
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

    /* ============================ Filtros de compatibilidad ============================ */

    function inicializarFiltrosCompatibilidad() {
        $.get({ url: 'proveedor_precios_ajax.php', dataType: 'json', data: { accion: 'obtener_marcas' } })
            .done(function (data) {
                var options = '<option value="">Todas las marcas</option>';
                (Array.isArray(data) ? data : []).forEach(function (m) {
                    options += '<option value="' + m.marca_id + '">' + escapeHtml(m.marca_nombre) + '</option>';
                });
                $('#filtroMarca').html(options);
            });

        $('#filtroCodigo').on('keyup', debounce(function () {
            intentarCargarTabla();
        }, 400));

        $('#filtroMarca').on('change', function () {
            var marcaId = $(this).val();
            $('#filtroModelo').html('<option value="">Todos los modelos</option>').prop('disabled', !marcaId);
            $('#filtroSubmodelo').html('<option value="">Todos los submodelos</option>').prop('disabled', true);

            if (marcaId) {
                $.get({ url: 'proveedor_precios_ajax.php', dataType: 'json', data: { accion: 'obtener_modelos', marca_id: marcaId } })
                    .done(function (data) {
                        var options = '<option value="">Todos los modelos</option>';
                        (Array.isArray(data) ? data : []).forEach(function (m) {
                            options += '<option value="' + m.modelo_id + '">' + escapeHtml(m.modelo_nombre) + '</option>';
                        });
                        $('#filtroModelo').html(options).prop('disabled', false);
                    });
            }

            intentarCargarTabla();
        });

        $('#filtroModelo').on('change', function () {
            var modeloId = $(this).val();
            $('#filtroSubmodelo').html('<option value="">Todos los submodelos</option>').prop('disabled', !modeloId);

            if (modeloId) {
                $.get({ url: 'proveedor_precios_ajax.php', dataType: 'json', data: { accion: 'obtener_submodelos', modelo_id: modeloId } })
                    .done(function (data) {
                        var options = '<option value="">Todos los submodelos</option>';
                        (Array.isArray(data) ? data : []).forEach(function (s) {
                            options += '<option value="' + s.submodelo_id + '">' + escapeHtml(s.submodelo_nombre) + '</option>';
                        });
                        $('#filtroSubmodelo').html(options).prop('disabled', false);
                    });
            }

            intentarCargarTabla();
        });

        $('#filtroSubmodelo').on('change', function () {
            intentarCargarTabla();
        });
    }

    // Punto único de entrada para disparar una búsqueda: si hay proveedor
    // elegido, siempre busca; si no hay proveedor, solo busca cuando algún
    // filtro tiene valor (para no traer el catálogo completo × cada
    // proveedor). Sin proveedor Y sin filtro, muestra el estado vacío.
    function intentarCargarTabla() {
        var hayFiltro = !!($('#filtroCodigo').val() || $('#filtroMarca').val() || $('#filtroModelo').val() || $('#filtroSubmodelo').val());

        if (!proveedorActualId && !hayFiltro) {
            mostrarEstadoVacioSinBusqueda();
            return;
        }

        cargarYMostrarTabla();
    }

    function mostrarEstadoVacioSinBusqueda() {
        if (tabla) {
            tabla.clear().draw();
        }
        $('#avisoSinBusqueda').removeClass('d-none');
    }

    // Trae {listas_precios, filas} del servidor. Se usa tanto para la carga
    // inicial (que además define las columnas dinámicas) como para cualquier
    // recarga posterior (guardar, importar, cambiar estado, botón Recargar).
    function cargarYMostrarTabla(callback) {
        $('#avisoSinBusqueda').addClass('d-none');
        $.get({
            url: 'proveedor_precios_ajax.php',
            dataType: 'json',
            data: {
                accion: 'listar',
                empresa_idx: empresa_idx,
                pagina_idx: pagina_idx,
                entidad_id: proveedorActualId,
                filtro_codigo: $('#filtroCodigo').val() || '',
                filtro_marca: $('#filtroMarca').val() || '',
                filtro_modelo: $('#filtroModelo').val() || '',
                filtro_submodelo: $('#filtroSubmodelo').val() || ''
            }
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
            '<th rowspan="2">Proveedor</th>' +
            '<th rowspan="2">Cód. Prov.</th><th rowspan="2">Código</th><th rowspan="2">Producto</th>' +
            '<th rowspan="2">Marca</th><th rowspan="2">Modelo</th><th rowspan="2">Submodelo</th>' +
            '<th rowspan="2" class="text-end">Precio Lista</th><th rowspan="2" class="text-end">Desc. %</th>' +
            '<th rowspan="2" class="text-end">Costo Neto Compra</th><th rowspan="2" class="text-end">Último Costo</th>' +
            '<th rowspan="2">Vigente desde</th>';
        var theadFila2 = '<tr>';
        listasPreciosActivas.forEach(function (lp, i) {
            var color = PALETA_LISTAS[i % PALETA_LISTAS.length];
            theadFila1 += '<th colspan="2" class="text-center text-white" style="background-color:' + color + ';">' + escapeHtml(lp.lista_precio_nombre) + '</th>';
            theadFila2 += '<th class="text-end">Actual</th><th class="text-end">Debería ser</th>';
        });
        theadFila1 += '<th rowspan="2" width="260" class="text-center">Acciones</th></tr>';
        theadFila2 += '</tr>';
        $('#tablaProveedorPrecios thead').html(theadFila1 + theadFila2);

        var columnasBase = [
            {
                // Proveedor de ESTA fila (ppp.entidad_id) — necesaria sobre todo
                // en la vista sin proveedor elegido, donde una búsqueda por
                // código/marca/modelo puede traer el mismo producto ofrecido
                // por varios proveedores distintos.
                data: 'entidad_nombre',
                defaultContent: '<span class="text-muted">-</span>'
            },
            { data: 'codigo_proveedor', defaultContent: '' },
            { data: 'producto_codigo', defaultContent: '' },
            { data: 'producto_nombre' },
            { data: 'marcas_compatibles', defaultContent: '<span class="text-muted">-</span>', render: function (d) { return d ? escapeHtml(d) : '<span class="text-muted">-</span>'; } },
            { data: 'modelos_compatibles', defaultContent: '<span class="text-muted">-</span>', render: function (d) { return d ? escapeHtml(d) : '<span class="text-muted">-</span>'; } },
            { data: 'submodelos_compatibles', defaultContent: '<span class="text-muted">-</span>', render: function (d) { return d ? escapeHtml(d) : '<span class="text-muted">-</span>'; } },
            { data: 'precio_lista', className: 'text-end', render: function (d) { return formatearMoneda(d); } },
            { data: 'descuento_general_pct', className: 'text-end', render: function (d) { return (parseFloat(d) || 0).toFixed(2) + ' %'; } },
            {
                // Costo neto de compra "debería ser" (según el precio de lista
                // actual del proveedor). Se resalta si difiere del "Último
                // Costo Registrado" (columna siguiente) — mismo umbral (0.99)
                // que usamos para "Debería ser" en las listas de venta.
                data: null,
                className: 'text-end',
                render: function (row) {
                    var neto = row.costo_neto_compra;
                    var registrado = row.costo_actual_registrado;
                    var difiere = registrado !== null && registrado !== undefined && Math.abs(registrado - neto) > 0.99;
                    return '<strong class="' + (difiere ? 'text-danger' : '') + '">' + formatearMoneda(neto) + '</strong>';
                }
            },
            {
                // Último costo que ya está registrado en gestion__productos_costos,
                // para comparar contra la columna anterior ANTES de tocar
                // "Actualizar costo". null = el producto todavía no tiene costo cargado.
                // Se resalta en rojo cuando difiere del costo neto de compra
                // recién calculado — así salta a la vista qué productos están
                // desactualizados sin tener que comparar columna por columna.
                data: null,
                className: 'text-end',
                render: function (row) {
                    var registrado = row.costo_actual_registrado;
                    if (registrado === null || registrado === undefined) {
                        return '<span class="text-muted">Sin costo</span>';
                    }
                    var difiere = Math.abs(registrado - row.costo_neto_compra) > 0.99;
                    return '<span class="' + (difiere ? 'text-danger fw-bold' : '') + '">' + formatearMoneda(registrado) + '</span>';
                }
            },
            {
                data: 'f_vigencia_desde',
                render: function (d) { return formatearFecha(d); }
            }
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
                    // Umbral de 0.99: diferencias menores (redondeos, centavos) no se marcan
                    // como "distinto" — solo salta a la vista si realmente cambia.
                    var difiere = actual !== null && actual !== undefined && Math.abs(actual - deberiaSer) > 0.99;
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
                html += '<button class="btn btn-sm btn-outline-info btn-ver-historial" data-id="' + row.producto_proveedor_id + '" title="Ver historial"><i class="fas fa-history"></i></button>';
                html += '<button class="btn btn-sm btn-outline-success btn-actualizar-costo" data-row="' + rowJson + '" title="Actualizar costo"><i class="fas fa-sync-alt"></i></button>';
                html += '<button class="btn btn-sm btn-outline-primary btn-actualizar-precio-venta" data-row="' + rowJson + '" title="Actualizar precio de venta (con el costo actual)"><i class="fas fa-tags"></i></button>';
                html += '<button class="btn btn-sm btn-outline-warning btn-actualizar-costo-y-precio" data-row="' + rowJson + '" title="Actualizar costo y precio de venta"><i class="fas fa-bolt"></i></button>';

                // Botones que vienen del motor de estados (conf__paginas_funciones,
                // pagina_id=92) — sin hardcodear "Editar" acá: si la función que
                // viene de la tabla es accion_js === 'editar' (no es una transición
                // de estado, abre el formulario), usa abrirModalEditar() con la fila
                // completa; el resto sigue yendo por ejecutarAccionEstado() como
                // siempre.
                (row.botones || []).forEach(function (btn) {
                    if (btn.accion_js === 'editar') {
                        html += '<button class="btn btn-sm ' + (btn.color_clase || 'btn-outline-secondary') + ' btn-editar-precio" ' +
                            'data-row="' + rowJson + '" title="' + escapeHtml(btn.descripcion || btn.nombre_funcion) + '">' +
                            '<i class="' + (btn.icono_clase || 'fas fa-pen') + '"></i></button>';
                        return;
                    }
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
            // Sin buscador propio de DataTables ("Buscar:") — ya filtramos
            // server-side con Código/Marca/Modelo/Submodelo, tener los dos
            // mecanismos a la vez confundía. "Mostrar N registros" pasa a
            // convivir con la paginación, abajo de la tabla, usando los
            // tokens nativos 'l'/'p' (ya vienen con estilo Bootstrap acá,
            // no hace falta reconstruirlos a mano como antes). stateSave
            // queda afuera: la tabla se reconstruye entera cuando cambian
            // las columnas (proveedor nuevo / cantidad de listas distinta).
            dom: '<"row"<"col-sm-12"tr>>' +
                '<"row mt-2"<"col-sm-12 col-md-3"l><"col-sm-12 col-md-9"p>>' +
                '<"clear">',
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            order: [[1, 'asc']],
            responsive: true,
            initComplete: function () {
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
        $('#producto_id').val('');
        $('#producto_proveedor_precio_id').val('');
        $('#entidad_id_edicion').val('');
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

        $('#producto_id').val(row.producto_id);
        $('#producto_proveedor_precio_id').val(row.producto_proveedor_precio_id);
        $('#entidad_id_edicion').val(row.entidad_id);
        $('#precio_lista').val(row.precio_lista);
        $('#moneda_id').val(row.moneda_id);
        $('#f_vigencia_desde').val(new Date().toISOString().slice(0, 10));

        productoSeleccionado = row;
        $('#bloqueBuscarProducto').addClass('d-none');
        $('#bloqueProductoSeleccionado').removeClass('d-none');
        $('#productoSeleccionadoNombre').text(row.producto_nombre);
        $('#productoSeleccionadoCodigo').text(' (' + row.codigo_proveedor + ')');

        $('#modalPrecioProveedorTitulo').text('Editar Precio — ' + (row.entidad_nombre || proveedorActualNombre));
        $('#modalPrecioProveedor').modal('show');
    }

    function buscarProductosProveedor() {
        var q = $('#buscadorProductoProveedor').val().trim();
        if (q.length < 2 || !proveedorActualId) {
            $('#resultadosBuscadorProducto').empty();
            return;
        }

        // Busca en TODO gestion__productos (antes solo entre lo ya vinculado
        // a este proveedor — un producto que el proveedor todavía no tenía
        // cargado no aparecía nunca). Cada resultado indica si ya está
        // vinculado y si ya tiene precio para este proveedor puntual.
        $.get('proveedor_precios_ajax.php', { accion: 'buscar_productos_catalogo', empresa_idx: empresa_idx, entidad_id: proveedorActualId, q: q }, function (data) {
            var html = '';
            if (!data || !data.length) {
                html = '<div class="text-muted small p-2">Sin resultados.</div>';
            } else {
                data.forEach(function (p) {
                    if (p.ya_tiene_precio) {
                        html += '<div class="list-group-item disabled">' +
                            '<strong>' + escapeHtml(p.producto_nombre) + '</strong> ' +
                            '<span class="text-muted small">Cód: ' + escapeHtml(p.producto_codigo || '-') + '</span>' +
                            '<span class="badge bg-secondary ms-2">Ya tiene precio — editalo desde la tabla</span>' +
                            '</div>';
                        return;
                    }
                    var avisoVinculo = !p.vinculo_existente
                        ? '<span class="badge bg-info ms-2">Se va a vincular a este proveedor</span>'
                        : '';
                    html += '<button type="button" class="list-group-item list-group-item-action btn-seleccionar-producto" ' +
                        'data-producto-id="' + p.producto_id + '" ' +
                        'data-nombre="' + escapeHtml(p.producto_nombre) + '" data-codigo="' + escapeHtml(p.codigo_proveedor || '') + '">' +
                        '<strong>' + escapeHtml(p.producto_nombre) + '</strong> ' +
                        '<span class="text-muted small">Cód: ' + escapeHtml(p.producto_codigo || '-') +
                        (p.codigo_proveedor ? ' — Cód. proveedor: ' + escapeHtml(p.codigo_proveedor) : '') + '</span>' +
                        avisoVinculo +
                        '</button>';
                });
            }
            $('#resultadosBuscadorProducto').html(html);
        }, 'json');
    }

    $(document).on('click', '.btn-seleccionar-producto', function () {
        var $btn = $(this);
        productoSeleccionado = {
            producto_id: $btn.data('producto-id'),
            producto_nombre: $btn.data('nombre'),
            codigo_proveedor: $btn.data('codigo')
        };
        $('#producto_id').val(productoSeleccionado.producto_id);

        $('#bloqueBuscarProducto').addClass('d-none');
        $('#bloqueProductoSeleccionado').removeClass('d-none');
        $('#productoSeleccionadoNombre').text(productoSeleccionado.producto_nombre);
        $('#productoSeleccionadoCodigo').text(productoSeleccionado.codigo_proveedor ? ' (' + productoSeleccionado.codigo_proveedor + ')' : '');
    });

    function guardarPrecioProveedor() {
        var producto_id = $('#producto_id').val();
        var precio_lista = parseFloat($('#precio_lista').val());
        // Al editar, la fila ya trae su propio proveedor (puede no ser el
        // que está elegido en el filtro principal, en la vista sin
        // proveedor); al agregar, siempre es el elegido en el filtro.
        var entidad_id = $('#entidad_id_edicion').val() || proveedorActualId;

        if (!producto_id) {
            return Swal.fire('Atención', 'Debe seleccionar un producto.', 'warning');
        }
        if (isNaN(precio_lista) || precio_lista < 0) {
            return Swal.fire('Atención', 'El precio de lista debe ser un número mayor o igual a 0.', 'warning');
        }

        var accion = $('#producto_proveedor_precio_id').val() ? 'editar' : 'agregar';

        $.post('proveedor_precios_ajax.php', {
            accion: accion,
            empresa_idx: empresa_idx,
            pagina_idx: pagina_idx,
            producto_id: producto_id,
            entidad_id: entidad_id,
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
                        '<td>' + formatearFecha(h.f_vigencia_desde) + '</td>' +
                        '<td>' + (h.f_vigencia_hasta ? formatearFecha(h.f_vigencia_hasta) : '-') + '</td>' +
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

    // tipo: 'actualizar_costo' | 'actualizar_precio_venta' | 'actualizar_costo_y_precio'
    function actualizarFila(rowEncoded, tipo) {
        var row = JSON.parse(decodeURIComponent(rowEncoded));

        var textos = {
            actualizar_costo: {
                titulo: 'Actualizar costo',
                html: 'Se va a fijar el costo de <strong>' + escapeHtml(row.producto_nombre) + '</strong> (proveedor <strong>' + escapeHtml(row.entidad_nombre || '') + '</strong>) en ' +
                      '<strong>' + formatearMoneda(row.costo_neto_compra) + '</strong> (precio de lista menos el descuento de compra vigente).' +
                      '<br><small class="text-muted">Esto NO toca las listas de precio de venta.</small>'
            },
            actualizar_precio_venta: {
                titulo: 'Actualizar precio de venta',
                html: 'Se va a recalcular el precio de venta de <strong>' + escapeHtml(row.producto_nombre) + '</strong> en cada lista con regla aplicable, ' +
                      'usando el costo YA registrado del producto (sin tocar gestion__productos_costos).' +
                      '<br><small class="text-muted">Usar cuando cambió la regla de la lista de precios, no el costo.</small>'
            },
            actualizar_costo_y_precio: {
                titulo: 'Actualizar costo y precio de venta',
                html: 'Se va a fijar el costo de <strong>' + escapeHtml(row.producto_nombre) + '</strong> en ' +
                      '<strong>' + formatearMoneda(row.costo_neto_compra) + '</strong>, y actualizar el precio de venta en cada lista con regla aplicable.' +
                      '<br><small class="text-muted">Si el costo no cambió, no se toca nada (ni costo ni listas) — para actualizar solo el precio de venta, usar el otro botón.</small>'
            }
        };
        var t = textos[tipo];

        Swal.fire({
            title: t.titulo,
            html: t.html,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, actualizar'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.post('proveedor_precios_ajax.php', {
                accion: tipo,
                empresa_idx: empresa_idx,
                producto_id: row.producto_id,
                entidad_id: row.entidad_id,
                costo_neto_compra: row.costo_neto_compra,
                moneda_id: row.moneda_id
            }, function (res) {
                if (res.success) {
                    var detalle = '';
                    if (tipo !== 'actualizar_costo') {
                        detalle = ' (' + res.precios_actualizados + ' lista(s) de precio actualizada(s)' +
                            (res.precios_sin_regla ? ', ' + res.precios_sin_regla + ' sin regla' : '') + ')';
                    }
                    Swal.fire({ icon: 'success', title: res.message + detalle, timer: 2200, showConfirmButton: false });
                    cargarYMostrarTabla();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json');
        });
    }

    // Genérica para los 2 botones masivos nuevos (solo costos / solo
    // precios de venta) — misma estructura que actualizarCostosMasivo, solo
    // cambia la acción y los textos.
    function actualizarMasivoGenerico(accion, titulo, descripcionHtml) {
        if (!proveedorActualId) return;

        Swal.fire({
            title: titulo,
            html: descripcionHtml,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, actualizar'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            Swal.fire({ title: 'Actualizando…', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

            $.post('proveedor_precios_ajax.php', {
                accion: accion,
                empresa_idx: empresa_idx,
                pagina_idx: pagina_idx,
                entidad_id: proveedorActualId
            }, function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: res.message });
                    cargarYMostrarTabla();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json').fail(function () {
                Swal.fire('Error', 'No se pudo completar la actualización.', 'error');
            });
        });
    }

    function actualizarCostosMasivo() {
        if (!proveedorActualId) return;

        Swal.fire({
            title: 'Actualizar costos y listas de precios de TODOS los productos',
            html: 'Se va a recalcular y fijar el costo (precio de lista menos el descuento de compra vigente) de <strong>todos los productos activos</strong> de <strong>' + escapeHtml(proveedorActualNombre) + '</strong>, ' +
                  'y actualizar el precio de venta en cada lista de precios con regla aplicable.' +
                  '<br><small class="text-muted">Los productos cuyo costo ya está al día se saltean (no se tocan ni costo ni precios).</small>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, actualizar todos'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            Swal.fire({ title: 'Actualizando…', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

            $.post('proveedor_precios_ajax.php', {
                accion: 'actualizar_costos_masivo',
                empresa_idx: empresa_idx,
                entidad_id: proveedorActualId
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

    // % de cambio: ajusta el PRECIO DE LISTA DEL PROVEEDOR (no el costo del
    // producto) de los productos filtrados en pantalla. El costo del
    // producto lo actualizan después los otros botones (individual o
    // masivo), mirando el costo_neto_compra ya recalculado.
    function aplicarPorcentajeCostos() {
        if (!proveedorActualId) return;

        var porcentaje = parseFloat($('#porcentajeAjusteCostos').val());
        if (isNaN(porcentaje)) {
            return Swal.fire('Atención', 'Ingresá un porcentaje válido (puede ser negativo).', 'warning');
        }

        Swal.fire({
            title: 'Aplicar ' + (porcentaje >= 0 ? '+' : '') + porcentaje + '% al precio de lista',
            html: 'Se va a ajustar el <strong>precio de lista del proveedor</strong> (no el costo del producto) de <strong>todos los productos que están filtrados</strong> en la grilla en este momento ' +
                  '(proveedor <strong>' + escapeHtml(proveedorActualNombre) + '</strong>' +
                  ($('#filtroCodigo').val() || $('#filtroMarca').val() || $('#filtroModelo').val() || $('#filtroSubmodelo').val() ? ', con los filtros de código/marca/modelo/submodelo activos' : ', sin filtros adicionales — todos sus productos') + ').' +
                  '<br><small class="text-muted">El costo del producto no se toca acá — usá "Actualizar Costos" (individual o masivo) después, si corresponde.</small>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, aplicar'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            Swal.fire({ title: 'Aplicando…', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

            $.post('proveedor_precios_ajax.php', {
                accion: 'aplicar_porcentaje_lista',
                empresa_idx: empresa_idx,
                pagina_idx: pagina_idx,
                entidad_id: proveedorActualId,
                porcentaje: porcentaje,
                filtro_codigo: $('#filtroCodigo').val() || '',
                filtro_marca: $('#filtroMarca').val() || '',
                filtro_modelo: $('#filtroModelo').val() || '',
                filtro_submodelo: $('#filtroSubmodelo').val() || ''
            }, function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: res.message });
                    cargarYMostrarTabla();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json').fail(function (xhr) {
                console.error('Error HTTP aplicando porcentaje:', xhr.status, xhr.responseText);
                Swal.fire('Error', 'No se pudo aplicar el porcentaje.', 'error');
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

    function formatearFecha(valor) {
        if (!valor) return '';
        var partes = String(valor).split('-'); // 'YYYY-MM-DD' -> 'DD/MM/YYYY'
        return partes.length === 3 ? (partes[2] + '/' + partes[1] + '/' + partes[0]) : valor;
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
