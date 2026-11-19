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
    var tablaControl = null; // DataTable de la solapa Control
    var tablaArmarLista = null; // DataTable de la vista previa de "Armar lista desde precios"
    var armarListaExcluidos = {}; // producto_proveedor_id destildados en la vista previa
    var armarListaBaseCalculada = null; // base con la que se calculó la vista previa vigente
    var tablaImportaciones = null; // DataTable de la solapa Importaciones (historial de archivos cargados)
    var tablaDetalleImportacion = null; // DataTable del detalle fila por fila de una importación

    var TAMANIO_LOTE = 300; // mismo tamaño de lote que listas_precios_productos.php

    inicializar();

    function inicializar() {
        cargarProveedores();
        cargarMonedas();

        $('#filtro_proveedor').on('change', function () {
            proveedorActualId = $(this).val();
            proveedorActualNombre = $(this).find(':selected').text();

            var haySeleccion = !!proveedorActualId;
            $('#btnAgregarPrecio, #btnImportarExcel, #btnArmarListaDesdePrecios, #btnActualizarCostosMasivo, #btnActualizarSoloCostosMasivo, #btnActualizarSoloPreciosMasivo, #btnAplicarPorcentajeCostos').prop('disabled', !haySeleccion);

            // Cambiar de proveedor (o pasar a "todos") fuerza reconstruir
            // columnas — puede cambiar si mostramos o no la columna Proveedor.
            listasPreciosActivas = [];
            intentarCargarTabla();
            cargarControl();
            cargarImportaciones();
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
        $('#btnArmarListaDesdePrecios').on('click', abrirModalArmarLista);
        $('#btnArmarListaCalcular').on('click', calcularArmarLista);
        $('#armarListaBase').on('change', function () {
            // Cambió la base: la vista previa vigente ya no corresponde.
            armarListaBaseCalculada = null;
            $('#btnArmarListaGenerar').prop('disabled', true);
        });
        $('#btnArmarListaGenerar').on('click', generarArmarLista);
        $('#armarListaTodos').on('change', function () {
            var tildar = $(this).is(':checked');
            armarListaExcluidos = {};
            if (!tildar && tablaArmarLista) {
                tablaArmarLista.rows().data().each(function (r) { armarListaExcluidos[r.producto_proveedor_id] = true; });
            }
            if (tablaArmarLista) tablaArmarLista.rows().invalidate('data').draw(false);
            actualizarResumenArmarLista();
        });
        $(document).on('change', '.chk-armar-lista', function () {
            var id = $(this).data('id');
            if ($(this).is(':checked')) { delete armarListaExcluidos[id]; } else { armarListaExcluidos[id] = true; }
            actualizarResumenArmarLista();
        });
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

        $('#tab-dashboard-btn').on('shown.bs.tab', cargarDashboard);
        $('#tab-control-btn').on('shown.bs.tab', function () {
            cargarControl();
            if (tablaControl) tablaControl.columns.adjust();
        });

        $('#tab-importaciones-btn').on('shown.bs.tab', cargarImportaciones);
        $(document).on('click', '.btn-ver-importacion', function (e) {
            e.preventDefault();
            var id = parseInt($(this).data('id'), 10);
            // Desde el modal de importación o el de historial: se cierra ese
            // primero (Bootstrap no apila bien dos modales).
            var $modal = $(this).closest('.modal');
            if ($modal.length) {
                $modal.one('hidden.bs.modal', function () { abrirDetalleImportacion(id); }).modal('hide');
            } else {
                abrirDetalleImportacion(id);
            }
        });
        $('#filtroResultadoImportacion').on('change', filtrarDetalleImportacion);
        $('#btnExportarDetalleImportacion').on('click', function () {
            if (tablaDetalleImportacion) tablaDetalleImportacion.button('.buttons-excel').trigger();
        });

        $(document).on('click', '.btn-cargar-precio-control', function () {
            abrirModalAgregarConProducto(JSON.parse(decodeURIComponent($(this).data('row'))));
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
                    '. Revisar que existan entidades activas de esa empresa con el rol Proveedor (gestion__entidades_roles).');
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
        // gestion_normal: la fila de compatibilidad vehicular está oculta; no se piden marcas de vehículo.
        if (!$('#filaFiltrosCompatibilidad').hasClass('d-none')) $.get({ url: 'proveedor_precios_ajax.php', dataType: 'json', data: { accion: 'obtener_marcas' } })
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

            // Cualquier recarga del listado (guardar, cambiar estado, importar,
            // masivos, botón Recargar) también refresca el dashboard si es la
            // pestaña visible en este momento — para no dejarlo con datos viejos.
            if (estaTabDashboardActiva()) {
                cargarDashboard();
            }
        }).fail(function (xhr) {
            console.error('Error cargando precios de proveedor:', xhr.status, xhr.responseText);
            Swal.fire('Error', 'No se pudo cargar el listado de precios.', 'error');
        });
    }

    function estaTabDashboardActiva() {
        return $('#tab-dashboard').hasClass('active');
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
            '<th rowspan="2">Cód. Prov.</th><th rowspan="2">Código</th><th rowspan="2" class="col-producto">Producto</th>' +
            '<th rowspan="2" class="text-end">Precio Lista</th><th rowspan="2" class="text-end">Desc. %</th>' +
            '<th rowspan="2" class="text-end">Costo Neto</th>' +
            '<th rowspan="2">Vigente desde</th>';
        var theadFila2 = '<tr>';
        listasPreciosActivas.forEach(function (lp, i) {
            var color = PALETA_LISTAS[i % PALETA_LISTAS.length];
            theadFila1 += '<th colspan="3" class="text-center text-white" style="background-color:' + color + ';">' + escapeHtml(lp.lista_precio_nombre) + '</th>';
            theadFila2 += '<th class="text-end">Actual</th><th class="text-end">Debería ser</th><th class="text-end">% Var.</th>';
        });
        // Acciones apiladas en 2 filas (ver render más abajo) — se achica el
        // ancho de la columna respecto de antes, ahora que no necesita entrar
        // todo en una sola fila de botones.
        theadFila1 += '<th rowspan="2" width="110" class="text-center">Acciones</th></tr>';
        theadFila2 += '</tr>';
        $('#tablaProveedorPrecios thead').html(theadFila1 + theadFila2);

        var columnasBase = [
            {
                // Proveedor de ESTA fila (ppp.entidad_id) — necesaria sobre todo
                // en la vista sin proveedor elegido, donde una búsqueda por
                // código/marca/modelo puede traer el mismo producto ofrecido
                // por varios proveedores distintos.
                // La estrella marca al proveedor principal del producto: el
                // único cuya lista actualiza el costo y los precios de venta.
                data: 'entidad_nombre',
                render: function (nombre, type, row) {
                    if (type === 'export') return nombre || '';
                    if (!nombre) return '<span class="text-muted">-</span>';
                    var html = escapeHtml(nombre);
                    if (parseInt(row.es_principal) === 1) {
                        html = '<i class="fas fa-star text-warning me-1" title="Proveedor principal del producto"></i>' + html;
                    }
                    return html;
                }
            },
            { data: 'codigo_proveedor', defaultContent: '' },
            { data: 'producto_codigo', defaultContent: '' },
            {
                // Debajo del nombre, la compatibilidad (marca, modelo,
                // submodelo y años) en gris — una línea por combinación REAL
                // (mismo criterio que productos.php: nunca se mezclan campos
                // sueltos, cada línea es una fila real de
                // gestion__productos_compatibilidad).
                // Columna ancha (min-width en proveedor_precios.php) y de las
                // últimas en ocultarse cuando la pantalla no alcanza.
                data: 'producto_nombre',
                className: 'col-producto',
                responsivePriority: 1,
                render: function (nombre, type, row) {
                    if (type === 'export') return nombre;
                    return renderNombreConCompatibilidad(nombre, row.compatibilidades_detalle);
                }
            },
            { data: 'precio_lista', className: 'text-end', render: function (d) { return formatearMoneda(d); } },
            { data: 'descuento_general_pct', className: 'text-end', render: function (d) { return Fmt.numero((parseFloat(d) || 0), 2) + ' %'; } },
            {
                // Costo neto de compra "debería ser" (según el precio de lista
                // actual del proveedor).
                data: null,
                className: 'text-end',
                render: function (row) {
                    var neto = row.costo_neto_compra;
                    var registrado = row.costo_actual_registrado;
                    return '<strong class="' + claseColorVariacion(registrado, neto) + '">' + formatearMoneda(neto) + '</strong>';
                }
            },
            {
                data: 'f_vigencia_desde',
                render: function (d) { return formatearFecha(d); }
            }
        ];

        // Un trío de columnas (Actual / Debería ser / % Var.) por cada lista
        // de precios activa. "Debería ser" NO considera promociones,
        // acumulación ni prioridad (confirmado): toma costo_neto_compra y le
        // aplica el porcentaje de la regla vigente que matchee por producto,
        // categoría o proveedor — ver resolverReglaAplicable() en el _model.php.
        listasPreciosActivas.forEach(function (lp) {
            var lid = lp.lista_precio_id;
            columnasBase.push({
                // Coloreado igual que "Debería ser" — misma comparación; el %
                // en sí va en la columna "% Var." de esta lista.
                data: 'precios_por_lista.' + lid + '.precio_actual',
                className: 'text-end',
                render: function (d, type, row) {
                    if (d === null || d === undefined) return '<span class="text-muted">Sin precio</span>';
                    var porLista = (row.precios_por_lista && row.precios_por_lista[lid]) || {};
                    return '<span class="' + claseColorVariacion(d, porLista.precio_deberia_ser) + '">' + formatearMoneda(d) + '</span>';
                }
            });
            columnasBase.push({
                data: null,
                className: 'text-end',
                render: function (row) {
                    var porLista = (row.precios_por_lista && row.precios_por_lista[lid]) || {};
                    var deberiaSer = porLista.precio_deberia_ser;
                    if (deberiaSer === null || deberiaSer === undefined) {
                        return '<span class="text-muted">Sin regla</span>';
                    }
                    return '<span class="' + claseColorVariacion(porLista.precio_actual, deberiaSer) + '">' + formatearMoneda(deberiaSer) + '</span>';
                }
            });
            columnasBase.push({
                // % de variación contra el precio actual — así dice CUÁNTO
                // cambió, sin comparar columna por columna.
                data: null,
                className: 'text-end',
                render: function (row) {
                    var porLista = (row.precios_por_lista && row.precios_por_lista[lid]) || {};
                    return renderCeldaPorcentaje(porLista.precio_actual, porLista.precio_deberia_ser);
                }
            });
        });

        columnasBase.push({
            // Apiladas en 2 filas para no obligar a la columna a ser tan
            // ancha: arriba las 4 acciones fijas (historial + actualizar
            // costo/precio), abajo los botones dinámicos del motor de
            // estados (editar/inhabilitar/confirmar/etc, si hay alguno).
            data: null,
            orderable: false,
            render: function (row) {
                var rowJson = encodeURIComponent(JSON.stringify(row));
                // Solo la lista del proveedor principal actualiza costo y precios de venta.
                var esPrincipal = parseInt(row.es_principal) === 1;
                var attrCosto = esPrincipal ? '' : ' disabled';
                var tituloNoPrincipal = 'No es el proveedor principal del producto (se cambia en Productos > Proveedores)';
                var filaFija = '<div class="btn-group btn-group-sm">' +
                    '<button class="btn btn-outline-info btn-ver-historial" data-id="' + row.producto_proveedor_id + '" title="Ver historial"><i class="fas fa-history"></i></button>' +
                    '<button class="btn btn-outline-success btn-actualizar-costo" data-row="' + rowJson + '" title="' + (esPrincipal ? 'Actualizar costo' : tituloNoPrincipal) + '"' + attrCosto + '><i class="fas fa-sync-alt"></i></button>' +
                    '<button class="btn btn-outline-primary btn-actualizar-precio-venta" data-row="' + rowJson + '" title="' + (esPrincipal ? 'Actualizar precio de venta (con el costo actual)' : tituloNoPrincipal) + '"' + attrCosto + '><i class="fas fa-tags"></i></button>' +
                    '<button class="btn btn-outline-warning btn-actualizar-costo-y-precio" data-row="' + rowJson + '" title="' + (esPrincipal ? 'Actualizar costo y precio de venta' : tituloNoPrincipal) + '"' + attrCosto + '><i class="fas fa-bolt"></i></button>' +
                    '</div>';

                // Botones que vienen del motor de estados (conf__paginas_funciones,
                // pagina_id=92) — sin hardcodear "Editar" acá: si la función que
                // viene de la tabla es accion_js === 'editar' (no es una transición
                // de estado, abre el formulario), usa abrirModalEditar() con la fila
                // completa; el resto sigue yendo por ejecutarAccionEstado() como
                // siempre.
                var botonesEstado = '';
                (row.botones || []).forEach(function (btn) {
                    if (btn.accion_js === 'editar') {
                        botonesEstado += '<button class="btn ' + (btn.color_clase || 'btn-outline-secondary') + ' btn-editar-precio" ' +
                            'data-row="' + rowJson + '" title="' + escapeHtml(btn.descripcion || btn.nombre_funcion) + '">' +
                            '<i class="' + (btn.icono_clase || 'fas fa-pen') + '"></i></button>';
                        return;
                    }
                    botonesEstado += '<button class="btn ' + (btn.color_clase || 'btn-outline-primary') + ' btn-estado-accion" ' +
                        'data-id="' + row.producto_proveedor_precio_id + '" data-accion="' + btn.accion_js + '" title="' + escapeHtml(btn.descripcion || btn.nombre_funcion) + '">' +
                        '<i class="' + (btn.icono_clase || 'fas fa-cog') + '"></i></button>';
                });
                var filaEstado = botonesEstado ? '<div class="btn-group btn-group-sm mt-1">' + botonesEstado + '</div>' : '';

                return '<div class="d-flex flex-column align-items-center">' + filaFija + filaEstado + '</div>';
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
                '<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>' +
                '<"clear">',
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            order: [[1, 'asc']],
            // Cabecera de 2 filas (colspan=3 por lista + rowspan=2): DataTables Responsive
            // oculta subcolumnas sueltas sin achicar el colspan del grupo, desfasando los
            // títulos en pantallas chicas. Se usa .table-responsive para scroll horizontal.
            responsive: false,
            autoWidth: false,
            initComplete: function () {
                // Instancia de Buttons oculta (no se agrega al DOM visible):
                // solo la usamos para que los botones del card-header
                // (#btnExportarExcel, etc.) la disparen por clase.
                new $.fn.dataTable.Buttons(tabla, {
                    buttons: ['excelHtml5', 'pdfHtml5', 'csvHtml5', 'print']
                });
            },
            columns: columnasBase,
            language: { url: window.APP_ASSETS_URL + 'js/datatables-i18n-2.1.8/es-ES.json' }
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
        $('#f_vigencia_desde').val(Fmt.hoyISO());
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
        $('#f_vigencia_desde').val(Fmt.hoyISO());

        productoSeleccionado = row;
        $('#bloqueBuscarProducto').addClass('d-none');
        $('#bloqueProductoSeleccionado').removeClass('d-none');
        $('#productoSeleccionadoNombre').text(row.producto_nombre);
        $('#productoSeleccionadoCodigo').text(' (' + row.codigo_proveedor + ')');

        $('#modalPrecioProveedorTitulo').text('Editar Precio — ' + (row.entidad_nombre || proveedorActualNombre));
        $('#modalPrecioProveedor').modal('show');
    }

    // Desde la solapa Control: el producto ya está vinculado a este
    // proveedor, así que se saltea el buscador y va directo al precio.
    function abrirModalAgregarConProducto(p) {
        abrirModalAgregar();
        productoSeleccionado = p;
        $('#producto_id').val(p.producto_id);
        $('#bloqueBuscarProducto').addClass('d-none');
        $('#bloqueProductoSeleccionado').removeClass('d-none');
        $('#productoSeleccionadoNombre').text(p.producto_nombre);
        $('#productoSeleccionadoCodigo').text(p.codigo_proveedor ? ' (' + p.codigo_proveedor + ')' : '');
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
                cargarControl();
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
                        '<td>' + (h.importacion_id
                            ? '<a href="#" class="btn-ver-importacion" data-id="' + h.importacion_id + '">' + escapeHtml(h.archivo_importacion || ('Importación N° ' + h.importacion_id)) + '</a>'
                            : escapeHtml(h.archivo_importacion || '-')) + '</td>' +
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
            html: descripcionHtml + '<br><small class="text-muted">Se saltean los productos donde este no es el proveedor principal.</small>',
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
                  '<br><small class="text-muted">Los productos cuyo costo ya está al día se saltean (no se tocan ni costo ni precios), ' +
                  'y también los productos donde este no es el proveedor principal.</small>',
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
            // blankrows:false + __rowNum__ (fila real de la hoja, base 0): el
            // historial de la importación guarda el número de fila tal como lo
            // ve el usuario en Excel, para poder ir a corregirla.
            var filas = XLSX.utils.sheet_to_json(hoja, { defval: '', blankrows: false });

            // Se mandan TODAS las filas con algún dato (también las que no se
            // pueden cargar: sin código, precio inválido, código repetido) para
            // que queden en el historial y se vean los errores del archivo.
            loteImportacion = filas.map(function (f) {
                var precioCrudo = f.precio ?? f.PRECIO ?? '';
                return {
                    fila_nro: (f.__rowNum__ !== undefined ? f.__rowNum__ + 1 : null),
                    codigo_proveedor: String(f.codigo_proveedor ?? f.CODIGO_PROVEEDOR ?? '').trim(),
                    descripcion: String(f.descripcion ?? f.DESCRIPCION ?? '').trim(),
                    precio_texto: String(precioCrudo).trim(),
                    precio: leerPrecioExcel(precioCrudo),
                    f_vigencia_desde: normalizarFechaExcel(f.f_vigencia_desde ?? f.F_VIGENCIA_DESDE ?? f.vigencia_desde ?? f.VIGENCIA_DESDE ?? '')
                };
            }).filter(function (f) { return f.codigo_proveedor !== '' || f.descripcion !== '' || f.precio_texto !== ''; });

            // Código repetido en el archivo: vale la última fila (como antes);
            // las anteriores se marcan y quedan en el historial como DUPLICADO.
            var ultimaPorCodigo = {};
            loteImportacion.forEach(function (f, i) {
                if (f.codigo_proveedor !== '') ultimaPorCodigo[normalizarCodigo(f.codigo_proveedor)] = i;
            });
            var duplicadas = 0;
            loteImportacion.forEach(function (f, i) {
                if (f.codigo_proveedor === '') return;
                var ultima = ultimaPorCodigo[normalizarCodigo(f.codigo_proveedor)];
                if (ultima !== i) {
                    f.duplicado = 1;
                    f.fila_vale = loteImportacion[ultima].fila_nro;
                    duplicadas++;
                }
            });

            var esValida = function (f) { return f.codigo_proveedor !== '' && !f.duplicado && f.precio !== null && f.precio > 0; };
            var validas = loteImportacion.filter(esValida);
            var sinCodigo = loteImportacion.filter(function (f) { return f.codigo_proveedor === ''; }).length;
            var precioInvalido = loteImportacion.filter(function (f) { return f.codigo_proveedor !== '' && !f.duplicado && !esValida(f); }).length;
            var sinFecha = validas.filter(function (f) { return !f.f_vigencia_desde; }).length;

            var avisos = [];
            if (sinCodigo) avisos.push(sinCodigo + ' sin código');
            if (precioInvalido) avisos.push(precioInvalido + ' con precio vacío, no numérico o en cero');
            if (duplicadas) avisos.push(duplicadas + ' con el código repetido (vale la última)');
            var html = '<div class="alert alert-info mb-2">Se detectaron <strong>' + loteImportacion.length + '</strong> filas, <strong>' +
                validas.length + '</strong> para cargar.' +
                (sinFecha ? ' <span class="text-warning">' + sinFecha + ' sin fecha de vigencia válida: se cargan con la fecha de hoy.</span>' : '') +
                '</div>';
            if (avisos.length) {
                html += '<div class="alert alert-warning mb-0">No se van a cargar: ' + avisos.join(', ') +
                    '. Quedan en el historial de la importación con su número de fila.</div>';
            }
            $('#resumenImportacion').html(html);
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

        var archivo = $('#inputArchivoExcel')[0].files[0];
        var archivoNombre = archivo.name;
        var totalLotes = Math.ceil(loteImportacion.length / TAMANIO_LOTE);
        var loteActual = 0;
        var importacionId = 0;
        var lotesFallidos = 0;
        var avisoPrevia = '';

        var acumulado = { procesados: 0, sin_cambios: 0, errores_count: 0, filas_error: 0, codigos_multiples: 0, no_vinculados: [] };

        $('#progressImportacion').removeClass('d-none');
        $('#btnProcesarImportacion').prop('disabled', true);

        // Primero se sube el archivo original y se abre la importación en el
        // historial; si eso falla no se carga nada.
        var fd = new FormData();
        fd.append('accion', 'importacion_iniciar');
        fd.append('empresa_idx', empresa_idx);
        fd.append('entidad_id', proveedorActualId);
        fd.append('archivo_nombre', archivoNombre);
        fd.append('filas_archivo', loteImportacion.length);
        fd.append('archivo', archivo);
        $.ajax({ url: 'proveedor_precios_ajax.php', type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
            .done(function (res) {
                if (!res || !res.success) {
                    $('#btnProcesarImportacion').prop('disabled', false);
                    $('#progressImportacion').addClass('d-none');
                    return Swal.fire('No se pudo importar', (res && res.message) || 'No se pudo registrar la importación.', 'error');
                }
                importacionId = res.data.importacion_id;
                if (res.data.previa) {
                    avisoPrevia = '<div class="alert alert-secondary mb-2"><i class="fas fa-info-circle me-1"></i>Este mismo archivo ya se había importado para este proveedor el ' +
                        escapeHtml(Fmt.fechaHora(res.data.previa.fecha_inicio)) + ' (importación N° ' + res.data.previa.importacion_id + ').</div>';
                }
                procesarSiguienteLote();
            })
            .fail(function (xhr) {
                console.error('Error HTTP en importacion_iniciar:', xhr.status, xhr.responseText);
                $('#btnProcesarImportacion').prop('disabled', false);
                $('#progressImportacion').addClass('d-none');
                Swal.fire('No se pudo importar', 'No se pudo subir el archivo.', 'error');
            });

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
                importacion_id: importacionId,
                items: JSON.stringify(items)
            }, function (res) {
                if (res.success) {
                    acumulado.procesados += res.procesados;
                    acumulado.sin_cambios += res.sin_cambios;
                    acumulado.errores_count += res.errores_count;
                    acumulado.filas_error += res.filas_error || 0;
                    acumulado.codigos_multiples += res.codigos_multiples || 0;
                    acumulado.no_vinculados = acumulado.no_vinculados.concat(res.no_vinculados || []);
                } else {
                    acumulado.errores_count += items.length;
                    lotesFallidos++;
                }

                loteActual++;
                var pct = Math.round((loteActual / totalLotes) * 100);
                $('#progressImportacion .progress-bar').css('width', pct + '%').text(pct + '%');

                procesarSiguienteLote();
            }, 'json').fail(function () {
                acumulado.errores_count += items.length;
                lotesFallidos++;
                loteActual++;
                procesarSiguienteLote();
            });
        }

        function finalizarImportacion() {
            $('#btnProcesarImportacion').prop('disabled', false);

            // Los contadores son por producto vinculado: un código del
            // proveedor que aplica a 2 productos suma 2.
            var html = '<div class="alert alert-success">Productos actualizados: ' + acumulado.procesados +
                ' — Sin cambios: ' + acumulado.sin_cambios +
                ' — Errores al grabar: ' + acumulado.errores_count +
                (acumulado.filas_error > acumulado.errores_count ? ' — Filas del archivo con error: ' + (acumulado.filas_error - acumulado.errores_count) : '') +
                (acumulado.codigos_multiples ? '<br><small>' + acumulado.codigos_multiples + ' código(s) del archivo aplicado(s) a más de un producto.</small>' : '') +
                '</div>';

            if (acumulado.no_vinculados.length) {
                html += '<div class="alert alert-warning"><strong>' + acumulado.no_vinculados.length + '</strong> códigos no vinculados a ningún producto de este proveedor:</div>';
                html += '<div style="max-height:200px; overflow-y:auto;"><table class="table table-sm"><thead><tr><th>Código</th><th>Descripción</th><th>Precio</th></tr></thead><tbody>';
                acumulado.no_vinculados.forEach(function (n) {
                    html += '<tr><td>' + escapeHtml(n.codigo_proveedor) + '</td><td>' + escapeHtml(n.descripcion) + '</td><td>' + formatearMoneda(n.precio) + '</td></tr>';
                });
                html += '</tbody></table></div>';
            }

            html += '<div id="resumenControlImportacion" class="mt-3 text-muted small"><i class="fas fa-spinner fa-spin me-1"></i>Controlando productos vinculados que no vinieron en el archivo…</div>';
            html += '<div class="mt-2"><button type="button" class="btn btn-sm btn-outline-secondary btn-ver-importacion" data-id="' + importacionId + '">' +
                '<i class="fas fa-history me-1"></i>Ver el detalle fila por fila</button></div>';

            $('#resumenImportacion').html(avisoPrevia + html);
            cargarYMostrarTabla();
            cargarControl();
            controlarImportacion(acumulado.no_vinculados, importacionId, lotesFallidos);
        }
    }

    // Una vez procesados TODOS los lotes: productos vinculados a este
    // proveedor cuyo código no vino en el archivo. Si el proveedor le cambió
    // el código a un producto, aparece acá (código viejo) y también en "no
    // vinculados" (código nuevo) — se sugiere el par por similitud de
    // descripción/código para que el usuario lo corrija en Productos-Proveedores.
    function controlarImportacion(noVinculados, importacionId, lotesFallidos) {
        var codigos = loteImportacion.filter(function (f) { return f.codigo_proveedor !== ''; })
            .map(function (f) { return f.codigo_proveedor; });

        $.post('proveedor_precios_ajax.php', {
            accion: 'controlar_importacion',
            empresa_idx: empresa_idx,
            entidad_id: proveedorActualId,
            importacion_id: importacionId || 0,
            lotes_fallidos: lotesFallidos || 0,
            codigos: JSON.stringify(codigos)
        }, function (res) {
            cargarImportaciones();
            if (!res || !res.success) {
                $('#resumenControlImportacion').html('<div class="alert alert-danger">No se pudo hacer el control: ' + escapeHtml((res && res.message) || 'respuesta inesperada') + '</div>');
                return;
            }
            pintarControlImportacion(res.data || [], noVinculados || []);
        }, 'json').fail(function (xhr) {
            console.error('Error HTTP en controlar_importacion:', xhr.status, xhr.responseText);
            $('#resumenControlImportacion').html('<div class="alert alert-danger">No se pudo hacer el control de productos vinculados.</div>');
        });
    }

    function pintarControlImportacion(faltantes, noVinculados) {
        var $cont = $('#resumenControlImportacion').removeClass('text-muted small');

        if (!faltantes.length) {
            $cont.html('<div class="alert alert-success mb-0"><i class="fas fa-check me-1"></i>Todos los productos vinculados a este proveedor vinieron en el archivo.</div>');
            return;
        }

        var candidatos = noVinculados.map(function (n) {
            return { n: n, tokens: tokenizar(n.descripcion), codigo: normalizarCodigo(n.codigo_proveedor) };
        });
        var conSugerencia = 0;

        var filas = faltantes.map(function (f) {
            var sug = sugerirCodigoNuevo(f, candidatos);
            if (sug) conSugerencia++;
            var celdaSug = sug
                ? '<strong class="text-primary">' + escapeHtml(sug.codigo_proveedor) + '</strong><br><small class="text-muted">' + escapeHtml(sug.descripcion) + ' — ' + formatearMoneda(sug.precio) + '</small>'
                : '<span class="text-muted">-</span>';
            return '<tr>' +
                '<td>' + (f.codigo_proveedor ? escapeHtml(f.codigo_proveedor) : '<span class="badge bg-secondary">Sin código</span>') + '</td>' +
                '<td>' + escapeHtml(f.producto_codigo || '') + '</td>' +
                '<td>' + escapeHtml(f.producto_nombre) + '</td>' +
                '<td class="text-end">' + (f.precio_lista !== null ? formatearMoneda(f.precio_lista) : '<span class="text-muted">Sin precio</span>') + '</td>' +
                '<td>' + celdaSug + '</td>' +
                '</tr>';
        });

        var html = '<div class="alert alert-warning mb-2"><strong>' + faltantes.length + '</strong> producto(s) vinculado(s) a este proveedor no vinieron en el archivo' +
            (conSugerencia ? ' — <strong>' + conSugerencia + '</strong> con un posible código nuevo entre los no vinculados' : '') +
            '. Si el proveedor cambió el código, corregilo en Productos-Proveedores y volvé a importar. Sus precios no se modificaron.</div>';
        html += '<div style="max-height:260px; overflow-y:auto;"><table class="table table-sm"><thead><tr>' +
            '<th>Cód. Prov. actual</th><th>Código</th><th>Producto</th><th class="text-end">Precio actual</th><th>Posible código nuevo</th>' +
            '</tr></thead><tbody>' + filas.join('') + '</tbody></table></div>';

        $cont.html(html);
    }

    // Mejor candidato entre los códigos no vinculados del archivo: similitud
    // de palabras (Jaccard) entre el nombre del producto y la descripción
    // del Excel, más un plus si un código contiene al otro (p. ej.
    // "AB123" → "AB123-N"). Por debajo del umbral no se sugiere nada.
    function sugerirCodigoNuevo(faltante, candidatos) {
        var tokensProducto = tokenizar(faltante.producto_nombre);
        var codigoActual = normalizarCodigo(faltante.codigo_proveedor);
        var mejor = null, mejorScore = 0;

        candidatos.forEach(function (c) {
            var score = similitudJaccard(tokensProducto, c.tokens);
            if (codigoActual.length >= 3 && c.codigo.length >= 3 &&
                (c.codigo.indexOf(codigoActual) !== -1 || codigoActual.indexOf(c.codigo) !== -1)) {
                score += 0.3;
            }
            if (score > mejorScore) {
                mejorScore = score;
                mejor = c.n;
            }
        });

        return mejorScore >= 0.4 ? mejor : null;
    }

    function tokenizar(texto) {
        var set = {};
        String(texto || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
            .split(/[^a-z0-9]+/)
            .forEach(function (t) { if (t.length > 1) set[t] = true; });
        return set;
    }

    function similitudJaccard(a, b) {
        var interseccion = 0, union = 0, k;
        for (k in a) { union++; if (b[k]) interseccion++; }
        for (k in b) { if (!a[k]) union++; }
        return union ? interseccion / union : 0;
    }

    function normalizarCodigo(codigo) {
        return String(codigo || '').trim().toUpperCase();
    }

    /* ============================ Solapa Control ============================ */

    // Vinculados a este proveedor sin precio en su lista. Se refresca al
    // cambiar de proveedor (también para el badge de la solapa, aunque no
    // esté visible), al guardar un precio y al terminar una importación.
    // ------------------------------------------------------------------
    // "Armar lista desde precios": vista previa + carga de los productos
    // vinculados a este proveedor que no tienen precio en su lista.
    // ------------------------------------------------------------------
    function abrirModalArmarLista() {
        if (!proveedorActualId) return;
        $('#armarListaProveedor').text(proveedorActualNombre);
        $('#armarListaResumen').html('').removeData('datos');
        $('#btnArmarListaGenerar').prop('disabled', true);
        armarListaBaseCalculada = null;
        armarListaExcluidos = {};
        $('#armarListaTodos').prop('checked', true);
        if (tablaArmarLista) tablaArmarLista.clear().draw();

        $.get('proveedor_precios_ajax.php', { accion: 'obtener_listas_precios', empresa_idx: empresa_idx }, function (res) {
            var $sel = $('#armarListaBase').empty();
            var listas = (res && res.success && Array.isArray(res.data)) ? res.data : [];
            var porDefecto = null;
            listas.forEach(function (lp) {
                if (parseInt(lp.lista_precio_origen_id, 10) === 3) return; // MANUAL: no hay regla para deshacer
                $sel.append($('<option>').val(lp.lista_precio_id).text('Precio de venta — lista ' + lp.lista_precio_nombre));
                // Por defecto la lista que parte del costo (un solo paso de regla).
                if (porDefecto === null && parseInt(lp.lista_precio_origen_id, 10) === 1) porDefecto = lp.lista_precio_id;
            });
            $sel.append($('<option>').val('COSTO').text('Costo registrado del producto'));
            if (porDefecto !== null) $sel.val(porDefecto);
            $('#modalArmarLista').modal('show');
        }, 'json').fail(function () {
            Swal.fire('Error', 'No se pudieron cargar las listas de precios.', 'error');
        });
    }

    function calcularArmarLista() {
        var base = $('#armarListaBase').val();
        if (!proveedorActualId || !base) return;
        var $btn = $('#btnArmarListaCalcular').prop('disabled', true);
        $('#armarListaResumen').html('<span class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i>Calculando…</span>');

        $.get({
            url: 'proveedor_precios_ajax.php',
            dataType: 'json',
            data: { accion: 'armar_lista_calcular', empresa_idx: empresa_idx, entidad_id: proveedorActualId, base: base }
        }).done(function (res) {
            if (!res || !res.success) {
                $('#armarListaResumen').html('');
                Swal.fire('Atención', (res && res.message) || 'No se pudo calcular.', 'warning');
                return;
            }
            armarListaBaseCalculada = base;
            armarListaExcluidos = {};
            $('#armarListaTodos').prop('checked', true);
            pintarTablaArmarLista(res.data.filas || []);
            actualizarResumenArmarLista(res.data);
            if (!res.data.total && res.message) {
                Swal.fire({ icon: 'info', title: res.message });
            }
        }).fail(function (xhr) {
            console.error('Error calculando armado de lista:', xhr.status, xhr.responseText);
            $('#armarListaResumen').html('');
            Swal.fire('Error', 'No se pudo calcular la vista previa.', 'error');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    }

    function pintarTablaArmarLista(filas) {
        if (tablaArmarLista) {
            tablaArmarLista.clear().rows.add(filas).draw();
            return;
        }
        var importe = function (d, type) { return d === null ? '' : (type === 'display' ? formatearMoneda(d) : d); };
        tablaArmarLista = $('#tablaArmarLista').DataTable({
            data: filas,
            pageLength: 10,
            order: [[3, 'asc']],
            columns: [
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function (row, type) {
                        if (type !== 'display' || row.precio_lista_propuesto === null) return '';
                        var tildado = !armarListaExcluidos[row.producto_proveedor_id];
                        return '<input type="checkbox" class="chk-armar-lista" data-id="' + row.producto_proveedor_id + '"' + (tildado ? ' checked' : '') + '>';
                    }
                },
                { data: 'codigo_proveedor', defaultContent: '', render: function (d, type) { return type === 'display' ? escapeHtml(d) : (d || ''); } },
                { data: 'producto_codigo', defaultContent: '', render: function (d, type) { return type === 'display' ? escapeHtml(d) : (d || ''); } },
                { data: 'producto_nombre', render: function (d, type) { return type === 'display' ? escapeHtml(d) : d; } },
                { data: 'precio_base', className: 'text-end', render: importe },
                { data: 'costo_estimado', className: 'text-end', render: importe },
                { data: 'precio_lista_propuesto', className: 'text-end fw-bold', render: importe },
                { data: 'motivo', render: function (d, type) { return type === 'display' && d ? '<span class="badge bg-secondary">' + escapeHtml(d) + '</span>' : (d || ''); } }
            ]
        });
    }

    function idsArmarListaSeleccionados() {
        var ids = [];
        if (!tablaArmarLista) return ids;
        tablaArmarLista.rows().data().each(function (r) {
            if (r.precio_lista_propuesto !== null && !armarListaExcluidos[r.producto_proveedor_id]) ids.push(r.producto_proveedor_id);
        });
        return ids;
    }

    function actualizarResumenArmarLista(datos) {
        if (datos) {
            $('#armarListaResumen').data('datos', datos);
        } else {
            datos = $('#armarListaResumen').data('datos') || {};
        }
        var seleccionados = idsArmarListaSeleccionados().length;
        $('#armarListaResumen').html(
            (datos.total || 0) + ' sin precio · ' + (datos.calculables || 0) + ' calculables · ' +
            '<strong>' + seleccionados + ' a cargar</strong>' +
            '<br><span class="text-muted">Descuento de compra vigente: ' + Fmt.numero((parseFloat(datos.descuento_pct) || 0), 2) + ' %</span>'
        );
        $('#btnArmarListaGenerar').prop('disabled', !seleccionados || armarListaBaseCalculada === null);
    }

    function generarArmarLista() {
        var ids = idsArmarListaSeleccionados();
        if (!proveedorActualId || !ids.length || armarListaBaseCalculada === null) return;

        Swal.fire({
            title: 'Cargar ' + ids.length + ' precio(s) en la lista del proveedor',
            html: 'Se cargan con origen <strong>ESTIMADO</strong> para <strong>' + escapeHtml(proveedorActualNombre) + '</strong>. ' +
                  'No se modifican costos ni listas de venta.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, cargar'
        }).then(function (result) {
            if (!result.isConfirmed) return;
            Swal.fire({ title: 'Cargando…', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

            $.post('proveedor_precios_ajax.php', {
                accion: 'armar_lista_generar',
                empresa_idx: empresa_idx,
                pagina_idx: pagina_idx,
                entidad_id: proveedorActualId,
                base: armarListaBaseCalculada,
                producto_proveedor_ids: JSON.stringify(ids)
            }, function (res) {
                if (res && res.success) {
                    $('#modalArmarLista').modal('hide');
                    Swal.fire({ icon: 'success', title: res.message });
                    intentarCargarTabla();
                    cargarControl();
                } else {
                    Swal.fire('Error', (res && res.message) || 'No se pudo cargar la lista.', 'error');
                }
            }, 'json').fail(function () {
                Swal.fire('Error', 'No se pudo completar la carga.', 'error');
            });
        });
    }

    function cargarControl() {
        if (!proveedorActualId) {
            $('#avisoControlSinProveedor').removeClass('d-none');
            $('#contenidoControl').addClass('d-none');
            $('#badgeControl').addClass('d-none');
            return;
        }
        var entidadPedida = proveedorActualId;

        $.get({
            url: 'proveedor_precios_ajax.php',
            dataType: 'json',
            data: { accion: 'listar_control', empresa_idx: empresa_idx, entidad_id: entidadPedida }
        }).done(function (res) {
            // Si el usuario cambió de proveedor mientras volvía la respuesta, se descarta.
            if (entidadPedida !== proveedorActualId) return;
            if (!res || !res.success) {
                Swal.fire('Error', (res && res.message) || 'No se pudo cargar el control.', 'error');
                return;
            }
            var filas = res.data || [];
            $('#badgeControl').text(filas.length).toggleClass('d-none', !filas.length);
            $('#avisoControlSinProveedor').addClass('d-none');
            $('#contenidoControl').removeClass('d-none');
            pintarTablaControl(filas);
        }).fail(function (xhr) {
            console.error('Error cargando control:', xhr.status, xhr.responseText);
            Swal.fire('Error', 'No se pudo cargar el control de productos sin precio.', 'error');
        });
    }

    function pintarTablaControl(filas) {
        if (tablaControl) {
            tablaControl.clear().rows.add(filas).draw();
            return;
        }
        tablaControl = $('#tablaControlSinPrecio').DataTable({
            data: filas,
            pageLength: 10,
            order: [[2, 'asc']],
            columns: [
                {
                    data: 'codigo_proveedor',
                    render: function (d, type) {
                        if (type !== 'display') return d || '';
                        return d ? escapeHtml(d) : '<span class="badge bg-secondary">Sin código</span>';
                    }
                },
                { data: 'producto_codigo', defaultContent: '', render: function (d, type) { return type === 'display' ? escapeHtml(d) : d; } },
                { data: 'producto_nombre', render: function (d, type) { return type === 'display' ? escapeHtml(d) : d; } },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function (row) {
                        return '<button class="btn btn-sm btn-outline-primary btn-cargar-precio-control" data-row="' + encodeURIComponent(JSON.stringify(row)) + '" title="Cargar precio">' +
                            '<i class="fas fa-dollar-sign"></i></button>';
                    }
                }
            ],
            language: { url: window.APP_ASSETS_URL + 'js/datatables-i18n-2.1.8/es-ES.json' }
        });
    }

    /* ============================ Compatibilidad ============================ */

    // Debajo del nombre del producto, la compatibilidad (marca, modelo,
    // submodelo y años) en gris — una línea por combinación REAL (mismo
    // criterio que productos.php: nunca se mezclan campos sueltos, cada
    // línea es una fila real de gestion__productos_compatibilidad).
    // Se usa tanto en la grilla de Listado como en las tablas del Dashboard.
    function renderNombreConCompatibilidad(nombre, detalle) {
        var base = escapeHtml(nombre);
        if (!detalle || !detalle.length) return base;
        var lineas = detalle.map(function (c) {
            var partes = [c.marca, c.modelo];
            if (c.submodelo) partes.push(c.submodelo);
            partes.push('(' + c.anios + ')');
            return escapeHtml(partes.filter(Boolean).join(' '));
        }).join('<br>');
        return base + '<br><small class="text-muted">' + lineas + '</small>';
    }

    /* ============================ Variación Actual vs Debería ser ============================ */

    // Mismos umbrales que resuelve server-side (obtenerDashboardPreciosProveedor):
    // <=3% sin variación (no se marca, es ruido de redondeo), 3-10% leve,
    // 10-25% moderada, >25% importante. Devuelve el nombre de color Bootstrap
    // (sin prefijo bg-/text-) para poder aplicarlo tanto al punto como al texto.
    function severidadVariacion(pctAbs) {
        if (pctAbs <= 10) return 'primary';
        if (pctAbs <= 25) return 'warning';
        return 'danger';
    }

    // Calcula la variación % entre "actual" y "debería ser". Devuelve null si
    // falta algún valor (nada que comparar) — el llamador decide qué hacer.
    function calcularVariacion(actual, deberiaSer) {
        if (actual === null || actual === undefined || !actual || deberiaSer === null || deberiaSer === undefined) {
            return null;
        }
        var pct = ((deberiaSer - actual) / actual) * 100;
        var pctAbs = Math.abs(pct);
        return { pct: pct, pctAbs: pctAbs, severidad: severidadVariacion(pctAbs) };
    }

    // Clase de color para el VALOR que se está evaluando (Costo Neto de
    // Compra, Último Costo, Actual, Debería ser) — sin indicador aparte: el
    // número en sí lleva el color de la severidad. Vacío si no hay variación
    // significativa (<=3%, ruido de redondeo) o falta algún dato.
    function claseColorVariacion(actual, deberiaSer) {
        var v = calcularVariacion(actual, deberiaSer);
        return (v && v.pctAbs > 3) ? 'text-' + v.severidad : '';
    }

    // Celda de la columna "% Var.": el % de variación solo, coloreado según
    // severidad (sin indicador/punto aparte — la columna en sí ya es el
    // indicador).
    function renderCeldaPorcentaje(actual, deberiaSer) {
        var v = calcularVariacion(actual, deberiaSer);
        if (!v) return '<span class="text-muted">-</span>';

        var signo = v.pct >= 0 ? '+' : '';
        var texto = signo + v.pct.toFixed(1) + '%';
        if (v.pctAbs <= 3) return '<span class="text-muted">' + texto + '</span>';
        return '<span class="text-' + v.severidad + ' fw-bold">' + texto + '</span>';
    }

    function cargarDashboard() {
        var hayFiltro = !!($('#filtroCodigo').val() || $('#filtroMarca').val() || $('#filtroModelo').val() || $('#filtroSubmodelo').val());

        if (!proveedorActualId && !hayFiltro) {
            $('#avisoDashboardSinBusqueda').removeClass('d-none');
            $('#contenidoDashboard').addClass('d-none');
            return;
        }
        $('#avisoDashboardSinBusqueda').addClass('d-none');

        $.get({
            url: 'proveedor_precios_ajax.php',
            dataType: 'json',
            data: {
                accion: 'obtener_dashboard',
                empresa_idx: empresa_idx,
                entidad_id: proveedorActualId,
                filtro_codigo: $('#filtroCodigo').val() || '',
                filtro_marca: $('#filtroMarca').val() || '',
                filtro_modelo: $('#filtroModelo').val() || '',
                filtro_submodelo: $('#filtroSubmodelo').val() || ''
            }
        }).done(function (dash) {
            // Defensa en profundidad: el guard de arriba (hayFiltro/proveedorActualId)
            // ya evita este caso en el uso normal, pero si el backend igual
            // responde "requiere_filtro" (p. ej. otro consumidor del endpoint
            // cambió el guard), no lo mostramos como "0 en todo".
            if (dash && dash.requiere_filtro) {
                $('#avisoDashboardSinBusqueda').removeClass('d-none');
                $('#contenidoDashboard').addClass('d-none');
                return;
            }
            pintarDashboard(dash);
            $('#contenidoDashboard').removeClass('d-none');
        }).fail(function (xhr) {
            console.error('Error cargando dashboard:', xhr.status, xhr.responseText);
            Swal.fire('Error', 'No se pudo cargar el dashboard.', 'error');
        });
    }

    function pintarDashboard(dash) {
        $('#dash_lista_general_nombre').text(dash.lista_general_nombre ? '(' + dash.lista_general_nombre + ')' : '');
        pintarTablaVariaciones('#dash_tabla_top_costo', dash.top_variaciones_costo, 'costo_actual', 'costo_neto_compra');
        pintarTablaVariaciones('#dash_tabla_top_lista_general', dash.top_variaciones_lista_general, 'precio_actual', 'precio_deberia_ser');
    }

    // Tabla de "mayores variaciones": mismo layout para costo y para la
    // lista general, solo cambian los nombres de campo del par actual/debería-ser.
    function pintarTablaVariaciones(selectorTabla, filas, campoActual, campoDeberiaSer) {
        var $tbody = $(selectorTabla + ' tbody').empty();
        if (!filas || !filas.length) {
            $tbody.append('<tr><td colspan="5" class="text-center text-muted">Sin variaciones para mostrar.</td></tr>');
            return;
        }
        filas.forEach(function (f) {
            var actual = f[campoActual];
            var deberiaSer = f[campoDeberiaSer];
            // "Debería ser" coloreado igual que la columna Variación (misma
            // comparación) — sin indicador aparte, el color del texto ya lo dice.
            var claseValor = claseColorVariacion(actual, deberiaSer);

            $tbody.append(
                '<tr>' +
                '<td>' + renderNombreConCompatibilidad(f.producto_nombre, f.compatibilidades_detalle) + '</td>' +
                '<td>' + escapeHtml(f.entidad_nombre || '-') + '</td>' +
                '<td class="text-end">' + formatearMoneda(actual) + '</td>' +
                '<td class="text-end ' + claseValor + '">' + formatearMoneda(deberiaSer) + '</td>' +
                '<td class="text-center">' + renderCeldaPorcentaje(actual, deberiaSer) + '</td>' +
                '</tr>'
            );
        });
    }

    /* ============================ Utilidades ============================ */

    /* ============================ Historial de importaciones ============================ */

    // Precio de una celda del Excel: número si la celda es numérica; si es
    // texto, "1234.56" se toma con punto decimal (como antes) y cualquier otro
    // texto ("1.234,56", "$ 1.234,56") con el formato de números de la
    // empresa. Vacío o no numérico → null (queda como PRECIO_INVALIDO).
    function leerPrecioExcel(valor) {
        if (typeof valor === 'number') return isFinite(valor) ? valor : null;
        var texto = String(valor ?? '').trim();
        if (texto === '') return null;
        var n = /^-?\d+(\.\d+)?$/.test(texto) ? parseFloat(texto) : Fmt.parseNumero(texto);
        return isFinite(n) ? n : null;
    }

    var RESULTADOS_IMPORTACION = {
        NUEVO: { texto: 'Precio nuevo', color: 'success' },
        ACTUALIZADO: { texto: 'Actualizado', color: 'primary' },
        SIN_CAMBIOS: { texto: 'Sin cambios', color: 'secondary' },
        NO_VINCULADO: { texto: 'Código no vinculado', color: 'warning' },
        SIN_CODIGO: { texto: 'Sin código', color: 'danger' },
        PRECIO_INVALIDO: { texto: 'Precio inválido', color: 'danger' },
        DUPLICADO: { texto: 'Código repetido', color: 'danger' },
        ERROR: { texto: 'Error al grabar', color: 'danger' },
        FALTANTE: { texto: 'No vino en el archivo', color: 'info' }
    };

    function badgeResultado(codigo) {
        var r = RESULTADOS_IMPORTACION[codigo] || { texto: codigo, color: 'secondary' };
        var texto = r.color === 'warning' || r.color === 'info' ? ' text-dark' : '';
        return '<span class="badge bg-' + r.color + texto + '">' + escapeHtml(r.texto) + '</span>';
    }

    function urlArchivoImportacion(id) {
        return 'proveedor_precios_ajax.php?accion=importacion_archivo&empresa_idx=' + encodeURIComponent(empresa_idx) + '&importacion_id=' + encodeURIComponent(id);
    }

    // Solapa Importaciones: del proveedor elegido o, sin proveedor, de todos.
    function cargarImportaciones() {
        if (!$('#tab-importaciones').hasClass('active') && !tablaImportaciones) return;
        var entidadPedida = proveedorActualId;
        $.get({
            url: 'proveedor_precios_ajax.php',
            dataType: 'json',
            data: { accion: 'importaciones_listar', empresa_idx: empresa_idx, entidad_id: entidadPedida || 0 }
        }).done(function (res) {
            if (entidadPedida !== proveedorActualId) return;
            if (!res || !res.success) {
                Swal.fire('Error', (res && res.message) || 'No se pudo cargar el historial de importaciones.', 'error');
                return;
            }
            pintarTablaImportaciones(res.data || []);
        }).fail(function (xhr) {
            console.error('Error cargando importaciones:', xhr.status, xhr.responseText);
            Swal.fire('Error', 'No se pudo cargar el historial de importaciones.', 'error');
        });
    }

    function pintarTablaImportaciones(filas) {
        if (tablaImportaciones) {
            tablaImportaciones.clear().rows.add(filas).draw();
            return;
        }
        var numero = function (d) { return parseInt(d, 10) || 0; };
        var celdaContador = function (color) {
            return function (d, type) {
                var n = numero(d);
                if (type !== 'display') return n;
                return n ? '<span class="text-' + color + ' fw-semibold">' + n + '</span>' : '<span class="text-muted">0</span>';
            };
        };
        tablaImportaciones = $('#tablaImportaciones').DataTable({
            data: filas,
            pageLength: 10,
            order: [[0, 'desc']],
            columns: [
                { data: 'fecha_inicio', render: function (d, type) { return type === 'display' ? escapeHtml(Fmt.fechaHora(d)) : d; } },
                { data: 'entidad_nombre', defaultContent: '' },
                {
                    data: 'archivo_nombre',
                    render: function (d, type, row) {
                        if (type !== 'display') return d || '';
                        var nombre = escapeHtml(d || '(sin nombre)');
                        return parseInt(row.tiene_archivo, 10)
                            ? '<a href="' + urlArchivoImportacion(row.importacion_id) + '" title="Descargar el archivo tal como se cargó"><i class="fas fa-download me-1"></i>' + nombre + '</a>'
                            : nombre;
                    }
                },
                { data: 'usuario_nombre', defaultContent: '', className: 'ui-detalle' },
                { data: 'filas_archivo', className: 'text-end', render: function (d) { return numero(d); } },
                {
                    data: null, className: 'text-end',
                    render: function (row, type) {
                        var n = numero(row.nuevos) + numero(row.actualizados);
                        if (type !== 'display') return n;
                        return '<span title="Nuevos: ' + numero(row.nuevos) + ' — Actualizados: ' + numero(row.actualizados) + '">' + n + '</span>';
                    }
                },
                { data: 'sin_cambios', className: 'text-end', render: function (d) { return numero(d); } },
                { data: 'no_vinculados', className: 'text-end', render: celdaContador('warning') },
                { data: 'filas_error', className: 'text-end', render: celdaContador('danger') },
                { data: 'faltantes', className: 'text-end', render: celdaContador('info') },
                {
                    data: 'estado',
                    render: function (d, type, row) {
                        var interrumpida = d === 'EN_CURSO' && parseInt(row.interrumpida, 10) === 1;
                        var texto = interrumpida ? 'Interrumpida' : (d === 'EN_CURSO' ? 'En curso' : (d === 'CON_ERRORES' ? 'Con errores' : 'Finalizada'));
                        if (type !== 'display') return texto;
                        var color = interrumpida || d === 'CON_ERRORES' ? 'danger' : (d === 'EN_CURSO' ? 'warning text-dark' : 'success');
                        var extra = parseInt(row.lotes_fallidos, 10) ? ' title="' + numero(row.lotes_fallidos) + ' lote(s) no llegaron al servidor"' : '';
                        return '<span class="badge bg-' + color + '"' + extra + '>' + texto + '</span>';
                    }
                },
                {
                    data: null, orderable: false, className: 'text-center',
                    render: function (row) {
                        return '<button type="button" class="btn btn-sm btn-outline-secondary btn-ver-importacion" data-id="' + row.importacion_id + '" title="Ver el resultado fila por fila"><i class="fas fa-list"></i></button>';
                    }
                }
            ],
            language: { url: window.APP_ASSETS_URL + 'js/datatables-i18n-2.1.8/es-ES.json' }
        });
    }

    function abrirDetalleImportacion(importacionId) {
        if (!importacionId) return;
        var cab = tablaImportaciones ? tablaImportaciones.rows().data().toArray().filter(function (r) { return parseInt(r.importacion_id, 10) === importacionId; })[0] : null;
        $.get({
            url: 'proveedor_precios_ajax.php',
            dataType: 'json',
            data: { accion: 'importacion_detalle', empresa_idx: empresa_idx, importacion_id: importacionId }
        }).done(function (res) {
            if (!res || !res.success) {
                Swal.fire('Error', (res && res.message) || 'No se pudo cargar el detalle.', 'error');
                return;
            }
            var filas = res.data || [];
            var titulo = 'Importación N° ' + importacionId;
            if (cab) titulo += ' — ' + (cab.archivo_nombre || '') + ' (' + Fmt.fechaHora(cab.fecha_inicio) + ')';
            $('#modalDetalleImportacionTitulo').text(titulo);
            $('#linkArchivoImportacion').attr('href', urlArchivoImportacion(importacionId)).toggleClass('d-none', !!cab && !parseInt(cab.tiene_archivo, 10));

            // Resumen por resultado (y opciones del filtro con su cantidad).
            var cuenta = {};
            filas.forEach(function (f) { cuenta[f.resultado] = (cuenta[f.resultado] || 0) + 1; });
            var $filtro = $('#filtroResultadoImportacion').empty()
                .append('<option value="">Todos (' + filas.length + ')</option>')
                .append('<option value="__errores">Solo errores del archivo (' + ['SIN_CODIGO', 'PRECIO_INVALIDO', 'DUPLICADO', 'ERROR', 'NO_VINCULADO'].reduce(function (t, k) { return t + (cuenta[k] || 0); }, 0) + ')</option>');
            Object.keys(RESULTADOS_IMPORTACION).forEach(function (k) {
                if (cuenta[k]) $filtro.append('<option value="' + k + '">' + RESULTADOS_IMPORTACION[k].texto + ' (' + cuenta[k] + ')</option>');
            });
            $('#resumenDetalleImportacion').html(Object.keys(RESULTADOS_IMPORTACION).filter(function (k) { return cuenta[k]; })
                .map(function (k) { return badgeResultado(k) + ' ' + cuenta[k]; }).join('<span class="mx-2"></span>'));

            pintarDetalleImportacion(filas);
            $('#modalDetalleImportacion').modal('show');
        }).fail(function (xhr) {
            console.error('Error en importacion_detalle:', xhr.status, xhr.responseText);
            Swal.fire('Error', 'No se pudo cargar el detalle de la importación.', 'error');
        });
    }

    function pintarDetalleImportacion(filas) {
        if (tablaDetalleImportacion) {
            tablaDetalleImportacion.search('').columns().search('');
            tablaDetalleImportacion.clear().rows.add(filas).draw();
            return;
        }
        var importe = function (d, type) {
            if (d === null || d === undefined || d === '') return type === 'display' ? '<span class="text-muted">-</span>' : '';
            return type === 'display' ? formatearMoneda(d) : parseFloat(d);
        };
        tablaDetalleImportacion = $('#tablaDetalleImportacion').DataTable({
            data: filas,
            pageLength: 25,
            order: [],
            autoWidth: false,
            columns: [
                { data: 'fila_nro', className: 'text-end', render: function (d, type) { return d === null ? (type === 'display' ? '<span class="text-muted">-</span>' : 999999999) : parseInt(d, 10); } },
                { data: 'codigo_proveedor', render: function (d, type) { return type === 'display' ? (d ? escapeHtml(d) : '<span class="text-muted">(vacío)</span>') : (d || ''); } },
                { data: 'descripcion', render: function (d, type) { return type === 'display' ? escapeHtml(d) : (d || ''); } },
                {
                    data: 'producto_nombre',
                    render: function (d, type, row) {
                        if (!d) return type === 'display' ? '<span class="text-muted">-</span>' : '';
                        var txt = (row.producto_codigo ? row.producto_codigo + ' — ' : '') + d;
                        return type === 'display' ? escapeHtml(txt) : txt;
                    }
                },
                {
                    data: 'precio_texto', className: 'text-end',
                    render: function (d, type, row) {
                        if (row.precio !== null && row.precio !== undefined) return importe(row.precio, type);
                        return type === 'display' ? (d ? '<span class="text-danger">' + escapeHtml(d) + '</span>' : '<span class="text-muted">(vacío)</span>') : (d || '');
                    }
                },
                { data: 'precio_anterior', className: 'text-end', render: importe },
                { data: 'f_vigencia_desde', render: function (d, type) { return d ? (type === 'display' ? escapeHtml(Fmt.fecha(d)) : d) : ''; } },
                {
                    data: 'resultado',
                    render: function (d, type) { return type === 'display' ? badgeResultado(d) : ((RESULTADOS_IMPORTACION[d] || {}).texto || d); }
                },
                { data: 'mensaje', render: function (d, type) { return type === 'display' ? '<small>' + escapeHtml(d) + '</small>' : (d || ''); } },
                { data: 'resultado', visible: false, searchable: true }
            ],
            initComplete: function () {
                new $.fn.dataTable.Buttons(tablaDetalleImportacion, {
                    buttons: [{ extend: 'excelHtml5', title: function () { return $('#modalDetalleImportacionTitulo').text(); }, exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8] } }]
                });
            },
            language: { url: window.APP_ASSETS_URL + 'js/datatables-i18n-2.1.8/es-ES.json' }
        });
    }

    function filtrarDetalleImportacion() {
        if (!tablaDetalleImportacion) return;
        var v = $('#filtroResultadoImportacion').val();
        var col = tablaDetalleImportacion.column(9);
        if (v === '__errores') {
            col.search('^(SIN_CODIGO|PRECIO_INVALIDO|DUPLICADO|ERROR|NO_VINCULADO)$', true, false);
        } else if (v) {
            col.search('^' + v + '$', true, false);
        } else {
            col.search('');
        }
        tablaDetalleImportacion.draw();
    }

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
        return Fmt.fecha(valor);
    }

    function formatearMoneda(valor) {
        return Fmt.numero((parseFloat(valor) || 0), { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
