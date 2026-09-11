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
                    className: 'text-center fw-bold',
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
                    data: 'boca_nombre',
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

    // Tipos de comprobante: mismo patrón que "Agregar Producto" en ventas_pedidos
    // (buscador arriba, resultados con botón "+", lista de agregados abajo con
    // sus propias acciones), pero sin la complejidad de tags/ida-y-vuelta al
    // servidor por letra: el catálogo de una empresa es chico y ya viaja completo
    // en 'obtener_comprobantes_tipos', así que el filtro se hace en el cliente.
    var catalogoComprobantesTipos = [];
    var comprobantesSeleccionados = []; // [{comprobante_tipo_id, requiere_afip}]
    var paginaComprobantesSeleccionados = 0;
    var COMPROBANTES_POR_PAGINA = 12;

    // 'habilitados' es un objeto {comprobante_tipo_id: requiere_afip} con lo
    // que ya tenía marcado el PV (vacío para "Nuevo").
    function cargarComprobantesTipos(habilitados, callback) {
        habilitados = habilitados || {};
        $.get('puntos_venta_ajax.php', {
            accion: 'obtener_comprobantes_tipos',
            empresa_idx: empresa_idx
        }, function(data) {
            catalogoComprobantesTipos = data || [];
            comprobantesSeleccionados = [];
            catalogoComprobantesTipos.forEach(function (item) {
                var estaHabilitado = Object.prototype.hasOwnProperty.call(habilitados, item.comprobante_tipo_id)
                    || Object.prototype.hasOwnProperty.call(habilitados, String(item.comprobante_tipo_id));
                if (estaHabilitado) {
                    var requiereAfip = (habilitados[item.comprobante_tipo_id] ?? habilitados[String(item.comprobante_tipo_id)]) == 1;
                    comprobantesSeleccionados.push({
                        comprobante_tipo_id: item.comprobante_tipo_id,
                        requiere_afip: requiereAfip ? 1 : 0
                    });
                }
            });

            $('#busqueda_comprobante_tipo').val('');
            $('#resultados_busqueda_comprobantes').empty();
            paginaComprobantesSeleccionados = 0;
            renderizarComprobantesSeleccionados();
            if (callback) callback();
        }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
            console.error("Error cargando tipos de comprobante:", textStatus, errorThrown);
            $('#tablaComprobantesSeleccionados tbody').html('<tr><td colspan="5" class="text-center text-danger small py-2">Error al cargar</td></tr>');
            if (callback) callback();
        });
    }

    function comprobanteYaSeleccionado(comprobanteTipoId) {
        return comprobantesSeleccionados.some(function (c) { return c.comprobante_tipo_id == comprobanteTipoId; });
    }

    function renderizarResultadosComprobantes() {
        var q = ($('#busqueda_comprobante_tipo').val() || '').trim().toLowerCase();
        var cont = $('#resultados_busqueda_comprobantes');

        if (!q) {
            cont.empty();
            return;
        }

        var coincidencias = catalogoComprobantesTipos.filter(function (item) {
            if (comprobanteYaSeleccionado(item.comprobante_tipo_id)) return false;
            var texto = ((item.comprobante_tipo || '') + ' ' + (item.codigo || '') + ' ' + (item.letra || '') + ' ' + (item.comprobante_subgrupo || '')).toLowerCase();
            return texto.indexOf(q) !== -1;
        });

        if (coincidencias.length === 0) {
            cont.html('<div class="text-center text-muted small p-2"><i class="fas fa-circle-info me-1"></i>No se encontraron tipos de comprobante para ese filtro.</div>');
            return;
        }

        var html = `<table class="table table-sm table-bordered table-hover mb-0" style="width: auto; max-width: 600px;">
            <thead class="table-light">
                <tr>
                    <th>Subgrupo / Tipo</th>
                    <th class="text-center" width="70">Código</th>
                    <th class="text-center" width="60">Letra</th>
                    <th class="text-center" width="70">Acción</th>
                </tr>
            </thead>
            <tbody>`;

        coincidencias.forEach(function (item) {
            html += `<tr>
                <td>
                    <div class="small text-muted">${item.comprobante_subgrupo || ''}</div>
                    <div>${item.comprobante_tipo || ''}</div>
                </td>
                <td class="text-center">${item.codigo || ''}</td>
                <td class="text-center">${item.letra || ''}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-success btn-agregar-comprobante"
                        data-id="${item.comprobante_tipo_id}" title="Agregar">
                        <i class="fas fa-plus"></i>
                    </button>
                </td>
            </tr>`;
        });

        html += '</tbody></table>';
        cont.html(html);
    }

    $(document).on('input', '#busqueda_comprobante_tipo', function () {
        renderizarResultadosComprobantes();
    });

    $(document).on('click', '.btn-agregar-comprobante', function () {
        var comprobanteTipoId = parseInt($(this).data('id'));
        if (comprobanteYaSeleccionado(comprobanteTipoId)) return;

        comprobantesSeleccionados.push({ comprobante_tipo_id: comprobanteTipoId, requiere_afip: 1 });
        paginaComprobantesSeleccionados = Math.ceil(comprobantesSeleccionados.length / COMPROBANTES_POR_PAGINA) - 1;
        renderizarComprobantesSeleccionados();
        renderizarResultadosComprobantes(); // saca el que se acaba de agregar de los resultados
    });

    function renderizarComprobantesSeleccionados() {
        var tbody = $('#tablaComprobantesSeleccionados tbody');

        $('#badgeComprobantesCount').text(comprobantesSeleccionados.length);

        if (comprobantesSeleccionados.length === 0) {
            tbody.html('<tr><td colspan="5" class="text-center text-muted small py-2">Sin tipos de comprobante habilitados</td></tr>');
            $('#paginacionComprobantesSeleccionados').empty();
            paginaComprobantesSeleccionados = 0;
            return;
        }

        var totalPaginas = Math.ceil(comprobantesSeleccionados.length / COMPROBANTES_POR_PAGINA);
        if (paginaComprobantesSeleccionados >= totalPaginas) paginaComprobantesSeleccionados = totalPaginas - 1;
        if (paginaComprobantesSeleccionados < 0) paginaComprobantesSeleccionados = 0;

        var inicio = paginaComprobantesSeleccionados * COMPROBANTES_POR_PAGINA;
        var paginaActual = comprobantesSeleccionados.slice(inicio, inicio + COMPROBANTES_POR_PAGINA);

        var html = '';
        paginaActual.forEach(function (sel) {
            var item = catalogoComprobantesTipos.find(function (c) { return c.comprobante_tipo_id == sel.comprobante_tipo_id; }) || {};
            html += `<tr data-comprobante-id="${sel.comprobante_tipo_id}">
                <td>
                    <div class="small text-muted">${item.comprobante_subgrupo || ''}</div>
                    <div>${item.comprobante_tipo || ''}</div>
                </td>
                <td class="text-center">${item.codigo || ''}</td>
                <td class="text-center">${item.letra || ''}</td>
                <td class="text-center">
                    <input type="checkbox" class="form-check-input check-requiere-afip" ${sel.requiere_afip ? 'checked' : ''}>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-comprobante" title="Quitar">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
        });
        tbody.html(html);

        renderizarPaginacionComprobantes(totalPaginas);
    }

    function renderizarPaginacionComprobantes(totalPaginas) {
        var cont = $('#paginacionComprobantesSeleccionados');

        if (totalPaginas <= 1) {
            cont.empty();
            return;
        }

        var html = '<ul class="pagination pagination-sm mb-0">';
        html += `<li class="page-item ${paginaComprobantesSeleccionados === 0 ? 'disabled' : ''}">
            <button type="button" class="page-link" data-pagina="${paginaComprobantesSeleccionados - 1}">&laquo;</button>
        </li>`;
        for (var i = 0; i < totalPaginas; i++) {
            html += `<li class="page-item ${i === paginaComprobantesSeleccionados ? 'active' : ''}">
                <button type="button" class="page-link" data-pagina="${i}">${i + 1}</button>
            </li>`;
        }
        html += `<li class="page-item ${paginaComprobantesSeleccionados === totalPaginas - 1 ? 'disabled' : ''}">
            <button type="button" class="page-link" data-pagina="${paginaComprobantesSeleccionados + 1}">&raquo;</button>
        </li>`;
        html += '</ul>';
        cont.html(html);
    }

    $(document).on('click', '#paginacionComprobantesSeleccionados .page-link', function () {
        var pagina = parseInt($(this).data('pagina'));
        if (isNaN(pagina) || pagina < 0) return;
        paginaComprobantesSeleccionados = pagina;
        renderizarComprobantesSeleccionados();
    });

    $(document).on('change', '.check-requiere-afip', function () {
        var comprobanteTipoId = parseInt($(this).closest('tr').data('comprobante-id'));
        var sel = comprobantesSeleccionados.find(function (c) { return c.comprobante_tipo_id == comprobanteTipoId; });
        if (sel) sel.requiere_afip = $(this).is(':checked') ? 1 : 0;
    });

    $(document).on('click', '.btn-quitar-comprobante', function () {
        var comprobanteTipoId = parseInt($(this).closest('tr').data('comprobante-id'));
        comprobantesSeleccionados = comprobantesSeleccionados.filter(function (c) { return c.comprobante_tipo_id != comprobanteTipoId; });
        renderizarComprobantesSeleccionados();
        renderizarResultadosComprobantes(); // el que se quitó puede volver a aparecer si matchea el filtro activo
    });

    // Junta la selección actual en el formato que espera el backend
    function obtenerComprobantesSeleccionados() {
        return comprobantesSeleccionados;
    }

    // Combo encadenado: al elegir sucursal, cargar sus bocas (comerciales y de depósito).
    // Es obligatorio elegir una boca, no hay opción de "PV general".
    function cargarBocasPorSucursal(sucursalId, selectedId, callback) {
        if (!sucursalId) {
            $('#boca_id').html('<option value="">Seleccione una sucursal primero</option>').prop('disabled', true);
            if (callback) callback();
            return;
        }
        $('#boca_id').prop('disabled', true).html('<option value="">Cargando...</option>');
        $.get('puntos_venta_ajax.php', {
            accion: 'obtener_bocas_por_sucursal',
            sucursal_id: sucursalId,
            empresa_idx: empresa_idx
        }, function(data) {
            let options = '<option value="">Seleccionar boca</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    var etiqueta = item.boca_nombre + (item.es_deposito == 1 ? ' (depósito)' : ' (comercial)');
                    options += `<option value="${item.boca_id}">${etiqueta}</option>`;
                });
            } else {
                options = '<option value="">Esta sucursal no tiene bocas cargadas</option>';
            }
            $('#boca_id').html(options).prop('disabled', false);
            if (selectedId) {
                $('#boca_id').val(selectedId);
            }
            if (callback) callback();
        }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
            console.error("Error cargando bocas:", textStatus, errorThrown);
            $('#boca_id').html('<option value="">Error al cargar</option>').prop('disabled', true);
            if (callback) callback();
        });
    }

    $(document).on('change', '#sucursal_id', function () {
        cargarBocasPorSucursal($(this).val(), null);
    });

    // ========== FUNCIONES DEL MODAL ==========
    function resetModal() {
        $('#formPuntoVenta')[0].reset();
        $('#punto_venta_id').val('');
        $('#es_web').prop('checked', false);
        $('#formPuntoVenta').removeClass('was-validated');
        $('#boca_id').html('<option value="">Seleccione una sucursal primero</option>').prop('disabled', true);
        $('#busqueda_comprobante_tipo').val('');
        $('#resultados_busqueda_comprobantes').empty();
        $('#tablaComprobantesSeleccionados tbody').html('<tr><td colspan="5" class="text-center text-muted small py-2">Cargando...</td></tr>');
        $('#paginacionComprobantesSeleccionados').empty();
        paginaComprobantesSeleccionados = 0;

        // El modal siempre abre en la solapa "Datos", aunque la vez anterior
        // se haya quedado en "Comprobantes"
        var tabDatosBtn = document.getElementById('tab-datos-btn');
        if (tabDatosBtn) {
            var tabDatos = new bootstrap.Tab(tabDatosBtn);
            tabDatos.show();
        }
    }

    $(document).on('click', '#btnNuevo', function () {
        resetModal();
        $('#modalLabel').text('Nuevo Punto de Venta');
        cargarCombosFormulario();
        cargarComprobantesTipos({});

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
                cargarComprobantesTipos(res.comprobantes_habilitados || {});

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
                        cargarBocasPorSucursal(res.sucursal_id, res.boca_id);
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
            // Los campos obligatorios (sucursal, boca, nombre) viven en la solapa
            // "Datos"; si el usuario está parado en "Comprobantes" al guardar,
            // el error queda invisible si no volvemos a esa solapa.
            var tabDatosBtn = document.getElementById('tab-datos-btn');
            if (tabDatosBtn) {
                new bootstrap.Tab(tabDatosBtn).show();
            }
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
        formData.append('boca_id', $('#boca_id').val() || '');
        formData.append('nombre', $('#nombre').val() || '');
        formData.append('descripcion', $('#descripcion').val() || '');
        formData.append('codigo_fiscal', $('#codigo_fiscal').val() || '');
        formData.append('es_web', $('#es_web').is(':checked') ? 1 : 0);
        formData.append('comprobantes', JSON.stringify(obtenerComprobantesSeleccionados()));

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