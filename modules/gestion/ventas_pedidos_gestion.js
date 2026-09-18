$(document).ready(function () {
    var empresa_idx = EMPRESA_ID;
    var pagina_idx = PAGINA_ID;
    // Página propia de ventas_remitos (88 por defecto ahí), NO confundir con
    // pagina_idx de esta página — motores de estado independientes, mismo
    // criterio ya establecido en ventas_pedidos.js.
    var REMITOS_PAGINA_IDX = 88;

    var tabla;
    var pedidoActual = null;          // datos del pedido abierto (respuesta cruda de abrir_pedido.pedido)
    var pendientesPedido = [];        // pendientes de ESTE pedido
    var pendientesOtros = [];         // pendientes de OTROS pedidos del mismo cliente
    var remitoDetallesNuevo = [];     // líneas elegidas para el remito en construcción
    var remitosPedidoActual = [];     // remitos ya generados para este pedido (respuesta cruda)
    var remitoEditandoId = null;      // si hay un remito sin numerar abierto, se agrega ahí en vez de crear uno nuevo
    var remitoLineasOriginalesPorVpd = {}; // venta_pedido_detalle_id -> cantidad que ese remito abierto ya tenía reservada

    // ---------- Utilidades ----------
    function formatMoneda(valor) {
        return (valor || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatFechaCorta(fecha) {
        if (!fecha) return '';
        var partes = fecha.split('-');
        if (partes.length !== 3) return fecha;
        return partes[2] + '/' + partes[1] + '/' + partes[0];
    }

    function escapeHtml(texto) {
        if (!texto) return '';
        return $('<div>').text(texto).html();
    }

    function renderUbicaciones(ubicaciones) {
        if (!ubicaciones || !ubicaciones.length) {
            return '<span class="text-muted small">Sin ubicación</span>';
        }
        var html = '<div class="ubicaciones-pendiente-container">';
        ubicaciones.forEach(function (u) {
            var partes = [];
            if (u.seccion) partes.push(`<span class="badge badge-ubicacion badge-seccion">${u.seccion}</span>`);
            if (u.estanteria) partes.push(`<span class="badge badge-ubicacion badge-estanteria">${u.estanteria}</span>`);
            if (u.estante) partes.push(`<span class="badge badge-ubicacion badge-estante">${u.estante}</span>`);
            if (u.posicion) partes.push(`<span class="badge badge-ubicacion badge-posicion">${u.posicion}</span>`);
            if (partes.length > 0) {
                html += `<div class="d-flex flex-wrap align-items-center gap-1">${partes.join(' ')}</div>`;
            }
        });
        html += '</div>';
        return html;
    }

    var filtroListadoSucursalId = '';
    var filtroListadoPuntoVentaId = '';

    // ---------- DataTable principal ----------
    function inicializarDataTable() {
        tabla = $('#tablaPedidosGestion').DataTable({
            ajax: {
                url: 'ventas_pedidos_gestion_ajax.php',
                type: 'GET',
                data: { accion: 'listar', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
                dataSrc: ''
            },
            dom: '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>' +
                '<"clear">',
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            columns: [
                {
                    data: 'comprobante_tipo',
                    className: 'text-center',
                    render: function (data, type) {
                        if (type === 'export') return data || '';
                        return `<span>${data || ''}</span>`;
                    }
                },
                {
                    data: 'sucursal_nombre',
                    className: 'text-center',
                    render: function (data, type) {
                        if (type === 'export') return data || '';
                        return `<span>${data || ''}</span>`;
                    }
                },
                {
                    data: 'punto_venta_nombre',
                    className: 'text-center',
                    render: function (data, type) {
                        if (type === 'export') return data || '';
                        return `<span>${data || ''}</span>`;
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    render: function (data, type, row) {
                        var numero = row.comprobante_nro || '';
                        if (type === 'export') return numero;
                        return `<span>${numero}</span>`;
                    }
                },
                {
                    data: null,
                    render: function (data, type) {
                        if (type === 'export') return data.entidad_nombre || '';
                        return `<div>${data.entidad_nombre || ''}</div>
                                <small class="text-muted">${data.entidad_fantasia || ''}</small>`;
                    }
                },
                {
                    data: 'f_emision',
                    className: 'text-center',
                    render: function (data, type) {
                        if (type !== 'display') return data;
                        return formatFechaCorta(data);
                    }
                },
                {
                    data: 'f_entrega_estimada',
                    className: 'text-center',
                    render: function (data, type) {
                        if (type !== 'display' || !data) return data || '';
                        return formatFechaCorta(data);
                    }
                },
                {
                    data: 'total',
                    className: 'text-end',
                    render: function (data, type) {
                        var valor = parseFloat(data) || 0;
                        if (type === 'export') return valor.toFixed(2);
                        if (type === 'sort' || type === 'filter') return valor;
                        return `<span class="text-primary">$${formatMoneda(valor)}</span>`;
                    }
                },
                {
                    data: 'estado_info',
                    className: 'text-center',
                    render: function (data, type) {
                        var estadoTexto = (data && data.estado_registro) ? data.estado_registro : '';
                        if (type === 'export') return estadoTexto;
                        return `<span>${estadoTexto}</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (type === 'export') return '';
                        return `<button type="button" class="btn btn-sm btn-primary btn-preparar-pedido" data-id="${row.venta_pedido_id}" title="Preparar">
                            <i class="fas fa-dolly"></i>
                        </button>`;
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json'
            },
            order: [[5, 'desc']],
            rowCallback: function (row, data) {
                if (parseInt(data.tabla_estado_registro_id, 10) === 5) {
                    $(row).addClass('fila-pendiente-preparar');
                }
            },
            initComplete: function () {
                setTimeout(function () {
                    $('#tablaPedidosGestion_length').addClass('dataTables_length_custom');
                    $('#tablaPedidosGestion_filter').addClass('dataTables_filter_custom');

                    if ($('#tablaPedidosGestion_length').html().trim() === '') {
                        var selectHtml = '<label>Mostrar <select name="tablaPedidosGestion_length" aria-controls="tablaPedidosGestion" class="form-select form-select-sm"><option value="10" selected="">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option><option value="-1">Todos</option></select> registros</label>';
                        $('#tablaPedidosGestion_length').html(selectHtml);
                        $('#tablaPedidosGestion_length select').on('change', function () {
                            tabla.page.len($(this).val()).draw();
                        });
                    }

                    if ($('#tablaPedidosGestion_filter').html().trim() === '') {
                        var filterHtml = '<label>Buscar:<input type="search" class="form-control form-control-sm" placeholder="" aria-controls="tablaPedidosGestion"></label>';
                        $('#tablaPedidosGestion_filter').html(filterHtml);
                        $('#tablaPedidosGestion_filter input').on('keyup', function () {
                            tabla.search($(this).val()).draw();
                        });
                    }
                }, 100);

                $('#filtro_cliente').on('keyup', function () {
                    tabla.column(4).search(this.value).draw();
                });

                $('#filtro_sucursal').on('change', function () {
                    filtroListadoSucursalId = $(this).val();
                    filtroListadoPuntoVentaId = '';
                    cargarFiltroPuntosVenta(filtroListadoSucursalId);
                    tabla.draw();
                });

                $('#filtro_punto_venta').on('change', function () {
                    filtroListadoPuntoVentaId = $(this).val();
                    tabla.draw();
                });

                cargarFiltroSucursales();
                cargarFiltroPuntosVenta('');

                new $.fn.dataTable.Buttons(tabla, {
                    buttons: ['excelHtml5', 'pdfHtml5', 'csvHtml5', 'print']
                }).container().appendTo($('#tablaPedidosGestion_wrapper .col-md-6:eq(1)'));
            }
        });
    }

    // Predicado custom: filtra por sucursal_id/punto_venta_id reales, no por el
    // texto ya renderizado en pantalla — mismo criterio que ventas_pedidos.js.
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex, rowData) {
        if (settings.nTable.id !== 'tablaPedidosGestion') {
            return true;
        }
        if (filtroListadoSucursalId && String(rowData.sucursal_id) !== String(filtroListadoSucursalId)) {
            return false;
        }
        if (filtroListadoPuntoVentaId && String(rowData.punto_venta_id) !== String(filtroListadoPuntoVentaId)) {
            return false;
        }
        return true;
    });

    function cargarFiltroSucursales() {
        $.get('ventas_pedidos_ajax.php', { accion: 'obtener_sucursales_empresa', empresa_idx: empresa_idx }, function (data) {
            var options = '<option value="">Todas las sucursales</option>';
            (data || []).forEach(function (item) {
                options += `<option value="${item.sucursal_id}">${item.sucursal_nombre}</option>`;
            });
            $('#filtro_sucursal').html(options);
        }, 'json');
    }

    function cargarFiltroPuntosVenta(sucursalId) {
        if (!sucursalId) {
            $('#filtro_punto_venta').html('<option value="">Todos los PV</option>');
            return;
        }
        $.get('ventas_pedidos_ajax.php', { accion: 'obtener_puntos_venta', sucursal_id: sucursalId, empresa_idx: empresa_idx }, function (data) {
            var options = '<option value="">Todos los PV</option>';
            (data || []).forEach(function (item) {
                options += `<option value="${item.punto_venta_id}">${item.punto_venta_nombre}</option>`;
            });
            $('#filtro_punto_venta').html(options);
        }, 'json');
    }

    $(document).on('click', '#btnRecargar', function () {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        tabla.ajax.reload(function () {
            btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
        }, false);
    });

    $(document).on('click', '#btnExportarExcel', function () { tabla.button('.buttons-excel').trigger(); });
    $(document).on('click', '#btnExportarPDF', function () { tabla.button('.buttons-pdf').trigger(); });
    $(document).on('click', '#btnExportarCSV', function () { tabla.button('.buttons-csv').trigger(); });
    $(document).on('click', '#btnExportarPrint', function () { tabla.button('.buttons-print').trigger(); });

    $(document).on('click', '.btn-preparar-pedido', function () {
        abrirPedidoPicking($(this).data('id'));
    });

    // ---------- Abrir pedido (picking) ----------
    function abrirPedidoPicking(pedidoId) {
        $.ajax({
            url: 'ventas_pedidos_gestion_ajax.php',
            type: 'POST',
            data: { accion: 'abrir_pedido', venta_pedido_id: pedidoId, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (!res || !res.pedido) {
                    Swal.fire({ icon: 'error', title: 'Error', text: (res && res.error) || 'No se pudo abrir el pedido', confirmButtonText: 'Entendido' });
                    return;
                }

                pedidoActual = res.pedido;
                renderizarResumenPedidoVsEnviado();
                pendientesPedido = res.pendientes_pedido || [];
                pendientesOtros = res.pendientes_otros || [];

                // Encabezado, solo lectura
                $('#picking_sucursal').text(pedidoActual.sucursal_nombre || '-');
                $('#picking_comprobante_tipo').text(pedidoActual.comprobante_tipo || '-');
                $('#picking_comprobante_nro').text(pedidoActual.comprobante_nro > 0 ? pedidoActual.comprobante_nro : 'Sin numerar');
                $('#picking_f_emision').text(formatFechaCorta(pedidoActual.f_emision));
                var estado = pedidoActual.estado_info || { estado_registro: pedidoActual.estado_registro, bg_clase: 'bg-dark', text_clase: 'text-white' };
                $('#picking_estado_badge').html(`<span class="fw-bold">${estado.estado_registro || pedidoActual.estado_registro || 'Sin estado'}</span>`);

                $('#picking_remito_f_emision').val(new Date().toISOString().split('T')[0]);
                $('#picking_remito_observaciones').val('');

                // PV: acotado a los depósitos de la sucursal del pedido — nunca libre.
                var puntosVenta = res.puntos_venta_sucursal || [];
                if (puntosVenta.length === 0) {
                    $('#picking_punto_venta_id').html('<option value="">Sin depósito activo para esta sucursal</option>').prop('disabled', true);
                    $('#picking_remito_tipo_texto').val('');
                    $('#picking_remito_comprobante_tipo_id').val('');
                    Swal.fire({
                        icon: 'warning',
                        title: 'Sin depósito para esta sucursal',
                        text: 'No hay un punto de venta de depósito activo para la sucursal de este pedido. No se puede cargar un remito hasta que se configure uno.',
                        confirmButtonText: 'Entendido'
                    });
                } else if (puntosVenta.length === 1) {
                    $('#picking_punto_venta_id').html(
                        `<option value="${puntosVenta[0].punto_venta_id}" selected>${puntosVenta[0].punto_venta_nombre}</option>`
                    ).prop('disabled', true);
                    cargarTipoComprobanteRemito(puntosVenta[0].punto_venta_id);
                } else {
                    var options = puntosVenta.map(function (pv) {
                        return `<option value="${pv.punto_venta_id}">${pv.punto_venta_nombre}</option>`;
                    }).join('');
                    $('#picking_punto_venta_id').html(options).prop('disabled', false);
                    cargarTipoComprobanteRemito(puntosVenta[0].punto_venta_id);
                }

                // Trae la lista de remitos y, si hay uno sin numerar abierto, carga
                // sus líneas al borrador en vez de arrancar uno nuevo en blanco.
                cargarRemitosDelPicking(pedidoActual.venta_pedido_id, detectarYCargarRemitoAbierto);

                var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPicking'));
                modal.show();

                // La grilla puede haber cambiado de estado (5->9) al abrir: refrescar.
                tabla.ajax.reload(null, false);
            },
            error: function () {
                Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor', confirmButtonText: 'Entendido' });
            }
        });
    }

    $(document).on('change', '#picking_punto_venta_id', function () {
        cargarTipoComprobanteRemito($(this).val());
    });

    // Tipo de comprobante del remito: se resuelve solo (es el único habilitado
    // en el sistema para remitos, según el punto de venta elegido) — no hay
    // combo, se muestra el nombre como texto de solo lectura.
    function cargarTipoComprobanteRemito(puntoVentaId) {
        if (!puntoVentaId) {
            $('#picking_remito_tipo_texto').val('');
            $('#picking_remito_comprobante_tipo_id').val('');
            return;
        }
        $.ajax({
            url: 'ventas_remitos_ajax.php',
            type: 'GET',
            data: {
                accion: 'obtener_comprobantes_tipos',
                punto_venta_id: puntoVentaId,
                pagina_idx: REMITOS_PAGINA_IDX,
                empresa_idx: empresa_idx
            },
            dataType: 'json',
            success: function (data) {
                if (data && data.length > 0) {
                    $('#picking_remito_tipo_texto').val(data[0].comprobante_tipo);
                    $('#picking_remito_comprobante_tipo_id').val(data[0].comprobante_tipo_id);
                } else {
                    $('#picking_remito_tipo_texto').val('Sin tipo habilitado');
                    $('#picking_remito_comprobante_tipo_id').val('');
                }
            },
            error: function () {
                $('#picking_remito_tipo_texto').val('Error al cargar');
                $('#picking_remito_comprobante_tipo_id').val('');
            }
        });
    }

    // ---------- Pendientes: mismo patrón +/- que ya usa ventas_pedidos.js ----------
    function cantidadYaEnBorrador(vpdId) {
        var total = 0;
        remitoDetallesNuevo.forEach(function (d) {
            if (d.venta_pedido_detalle_id == vpdId) total += parseFloat(d.cantidad) || 0;
        });
        return total;
    }

    function ajustarCantidadPicking(vpdId, delta, datosLinea) {
        var existente = remitoDetallesNuevo.find(function (d) { return d.venta_pedido_detalle_id == vpdId; });

        if (existente) {
            var nuevaCantidad = Math.round(((parseFloat(existente.cantidad) || 0) + delta) * 100) / 100;
            if (nuevaCantidad <= 0) {
                remitoDetallesNuevo = remitoDetallesNuevo.filter(function (d) { return d !== existente; });
            } else {
                existente.cantidad = nuevaCantidad;
            }
        } else {
            if (delta <= 0) return; // nada cargado todavía, no hay qué restar
            remitoDetallesNuevo.push({
                venta_pedido_detalle_id: vpdId,
                producto_id: datosLinea.producto_id,
                producto_codigo: datosLinea.producto_codigo,
                producto_nombre: datosLinea.producto_nombre,
                origen_pedido: datosLinea.origen_pedido || '',
                cantidad: delta
            });
        }

        renderizarPendientesPedido();
        renderizarPendientesOtros();
        renderizarRemitoDetalleNuevo();
    }

    $(document).on('click', '.btn-cantidad-picking-menos', function () {
        ajustarCantidadPicking(parseInt($(this).data('vpd-id')), -1);
    });

    $(document).on('click', '.btn-cantidad-picking-mas', function () {
        var btn = $(this);
        ajustarCantidadPicking(parseInt(btn.data('vpd-id')), 1, {
            producto_id: btn.data('producto-id'),
            producto_codigo: btn.data('codigo'),
            producto_nombre: btn.data('nombre'),
            origen_pedido: btn.data('origen') || ''
        });
    });

    function filasConPendienteEfectivo(lista) {
        var filas = [];
        lista.forEach(function (linea) {
            var yaEnBorrador = cantidadYaEnBorrador(linea.venta_pedido_detalle_id);
            // Si se está agregando a un remito abierto existente, su "pendiente" de
            // base ya viene descontando lo que ESE MISMO remito reservó la vez
            // anterior (todavía no se revirtió, eso pasa recién al guardar). Se
            // suma de vuelta acá para no restarlo dos veces.
            var reservadoPorEsteRemito = (remitoEditandoId && remitoLineasOriginalesPorVpd[linea.venta_pedido_detalle_id])
                ? remitoLineasOriginalesPorVpd[linea.venta_pedido_detalle_id] : 0;
            var pendienteBase = (parseFloat(linea.pendiente) || 0) + reservadoPorEsteRemito;
            var pendienteEfectivo = Math.max(0, pendienteBase - yaEnBorrador);
            if (pendienteEfectivo <= 0.0001) return;
            filas.push({ linea: linea, pendienteEfectivo: pendienteEfectivo });
        });
        return filas;
    }

    function renderizarPendientesPedido() {
        var cont = $('#contenedor-picking-pendientes-pedido');
        var filas = filasConPendienteEfectivo(pendientesPedido);

        if (filas.length === 0) {
            cont.html('<div class="text-muted small p-2">No hay líneas pendientes de este pedido.</div>');
            return;
        }

        var html = `<table class="table table-sm table-bordered table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Ubicación</th>
                    <th class="text-end">Entregado</th>
                    <th class="text-end">Pendiente</th>
                    <th class="text-center" width="110">Cantidad</th>
                </tr>
            </thead>
            <tbody>`;

        filas.forEach(function (item) {
            var linea = item.linea;
            var cantidadEnBorrador = cantidadYaEnBorrador(linea.venta_pedido_detalle_id);
            html += `<tr>
                <td>${linea.producto_codigo || ''}</td>
                <td>${linea.producto_nombre || ''}</td>
                <td>${renderUbicaciones(linea.ubicaciones_detalle)}</td>
                <td class="text-end">${formatMoneda(linea.cantidad_entregada)}</td>
                <td class="text-end">${formatMoneda(item.pendienteEfectivo)}</td>
                <td>
                    <div class="cantidad-stepper mx-auto">
                        <button type="button" class="btn-stepper btn-cantidad-picking-menos" data-vpd-id="${linea.venta_pedido_detalle_id}" tabindex="-1">&minus;</button>
                        <input type="number" class="cantidad-stepper-input" value="${cantidadEnBorrador.toFixed(2)}" step="0.01" min="0" readonly>
                        <button type="button" class="btn-stepper btn-cantidad-picking-mas"
                            data-vpd-id="${linea.venta_pedido_detalle_id}"
                            data-producto-id="${linea.producto_id}"
                            data-codigo="${linea.producto_codigo || ''}"
                            data-nombre="${linea.producto_nombre || ''}"
                            tabindex="-1">+</button>
                    </div>
                </td>
            </tr>`;
        });

        html += '</tbody></table>';
        cont.html(html);
    }

    function renderizarPendientesOtros() {
        var cont = $('#contenedor-picking-pendientes-otros');
        var filas = filasConPendienteEfectivo(pendientesOtros);

        if (filas.length === 0) {
            $('#card-picking-pendientes-otros').hide();
            return;
        }
        $('#card-picking-pendientes-otros').show();

        var html = `<table class="table table-sm table-bordered table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Pedido</th>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Ubicación</th>
                    <th class="text-end">Pendiente</th>
                    <th class="text-center" width="110">Cantidad</th>
                </tr>
            </thead>
            <tbody>`;

        filas.forEach(function (item) {
            var linea = item.linea;
            var cantidadEnBorrador = cantidadYaEnBorrador(linea.venta_pedido_detalle_id);
            var origenPedido = linea.comprobante_nro > 0 ? ('#' + linea.comprobante_nro) : ('Pedido ' + linea.venta_pedido_id);
            html += `<tr>
                <td><span class="badge bg-secondary">${origenPedido}</span></td>
                <td>${linea.producto_codigo || ''}</td>
                <td>${linea.producto_nombre || ''}</td>
                <td>${renderUbicaciones(linea.ubicaciones_detalle)}</td>
                <td class="text-end">${formatMoneda(item.pendienteEfectivo)}</td>
                <td>
                    <div class="cantidad-stepper mx-auto">
                        <button type="button" class="btn-stepper btn-cantidad-picking-menos" data-vpd-id="${linea.venta_pedido_detalle_id}" tabindex="-1">&minus;</button>
                        <input type="number" class="cantidad-stepper-input" value="${cantidadEnBorrador.toFixed(2)}" step="0.01" min="0" readonly>
                        <button type="button" class="btn-stepper btn-cantidad-picking-mas"
                            data-vpd-id="${linea.venta_pedido_detalle_id}"
                            data-producto-id="${linea.producto_id}"
                            data-codigo="${linea.producto_codigo || ''}"
                            data-nombre="${linea.producto_nombre || ''}"
                            data-origen="${origenPedido}"
                            tabindex="-1">+</button>
                    </div>
                </td>
            </tr>`;
        });

        html += '</tbody></table>';
        cont.html(html);
    }

    function renderizarRemitoDetalleNuevo() {
        var cont = $('#contenedor-picking-remito-detalle');

        if (remitoDetallesNuevo.length === 0) {
            cont.html('<div class="text-muted small text-center p-3 border rounded bg-light">Todavía no agregaste líneas a este remito.</div>');
            return;
        }

        var html = `<table class="table table-sm table-bordered mb-0">
            <thead class="table-light">
                <tr>
                    <th>Origen</th>
                    <th>Código</th>
                    <th>Producto</th>
                    <th class="text-center">Cant.</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>`;

        remitoDetallesNuevo.forEach(function (d, idx) {
            html += `<tr>
                <td>${d.origen_pedido ? `<span class="badge bg-secondary">${d.origen_pedido}</span>` : '<span class="badge bg-primary">Este pedido</span>'}</td>
                <td>${d.producto_codigo || ''}</td>
                <td>${d.producto_nombre || ''}</td>
                <td class="text-center">${formatMoneda(d.cantidad)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger btn-quitar-picking" data-idx="${idx}" title="Quitar">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
        });

        html += '</tbody></table>';
        cont.html(html);
    }

    $(document).on('click', '.btn-quitar-picking', function () {
        remitoDetallesNuevo.splice($(this).data('idx'), 1);
        renderizarPendientesPedido();
        renderizarPendientesOtros();
        renderizarRemitoDetalleNuevo();
    });

    // ---------- Remitos ya generados de este pedido ----------
    // Reutiliza el endpoint que ya existe en ventas_pedidos_ajax.php — no se
    // duplica esa consulta acá.
    function cargarRemitosDelPicking(pedidoId, callback) {
        $.ajax({
            url: 'ventas_pedidos_ajax.php',
            type: 'GET',
            data: {
                accion: 'obtener_remitos_pedido',
                venta_pedido_id: pedidoId,
                pagina_idx_remitos: REMITOS_PAGINA_IDX,
                empresa_idx: empresa_idx
            },
            dataType: 'json',
            success: function (res) {
                remitosPedidoActual = res || [];
                renderizarRemitosPicking();
                if (typeof callback === 'function') callback();
            },
            error: function () {
                remitosPedidoActual = [];
                renderizarRemitosPicking();
                if (typeof callback === 'function') callback();
            }
        });
    }

    function renderizarRemitosPicking() {
        $('#contador-picking-remitos').text(remitosPedidoActual.length);

        var cont = $('#contenedor-picking-remitos-pedido');

        if (remitosPedidoActual.length === 0) {
            cont.html('<div class="text-muted small text-center p-3">Todavía no hay remitos generados para este pedido.</div>');
            return;
        }

        var html = '';
        remitosPedidoActual.forEach(function (remito) {
            var estado = remito.estado_info || {};
            var numero = remito.comprobante_nro > 0
                ? `${remito.comprobante_tipo || 'Remito'} #${remito.comprobante_nro}`
                : `${remito.comprobante_tipo || 'Remito'} (sin numerar)`;

            var botonesHtml = '';
            (remito.botones || []).forEach(function (boton) {
                var claseBoton = 'btn-sm me-1 ';
                if (boton.bg_clase && boton.text_clase) {
                    claseBoton += boton.bg_clase + ' ' + boton.text_clase;
                } else if (boton.color_clase) {
                    claseBoton += boton.color_clase;
                } else {
                    claseBoton += 'btn-outline-primary';
                }
                var icono = boton.icono_clase ? `<i class="${boton.icono_clase}"></i>` : '';
                var titulo = boton.descripcion || boton.nombre_funcion;

                if (boton.accion_js === 'visualizar') {
                    botonesHtml += `<button type="button" class="btn ${claseBoton} btn-ver-resumen-remito-picking"
                        data-id="${remito.venta_remito_id}" title="${titulo}">${icono}</button>`;
                } else {
                    botonesHtml += `<button type="button" class="btn ${claseBoton} btn-accion-remito-picking"
                        data-id="${remito.venta_remito_id}"
                        data-accion="${boton.accion_js}"
                        data-confirmable="${boton.es_confirmable || 0}"
                        data-comprobante="${numero}"
                        title="${titulo}">${icono}</button>`;
                }
            });

            var confirmado = remito.comprobante_nro > 0;
            var collapseId = 'collapse-picking-remito-' + remito.venta_remito_id;
            var botonToggle = confirmado
                ? `<button type="button" class="btn btn-sm btn-outline-secondary me-2 btn-toggle-remito-picking"
                        data-bs-toggle="collapse" data-bs-target="#${collapseId}" title="Ver/ocultar detalle">
                        <i class="fas fa-chevron-down"></i>
                    </button>`
                : '';

            var filasDetalle = '';
            (remito.detalles || []).forEach(function (d) {
                filasDetalle += `<tr>
                    <td>${d.producto_codigo || ''}</td>
                    <td>${d.producto_nombre || ''}</td>
                    <td class="text-center">${formatMoneda(d.cantidad)}</td>
                    <td class="text-end">$${formatMoneda(d.importe_linea)}</td>
                </tr>`;
            });

            html += `<div class="border rounded mb-2 p-2">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-1 mb-1">
                    <div>
                        ${botonToggle}
                        <strong>${numero}</strong>
                        <span class="text-muted small ms-2">${formatFechaCorta(remito.f_emision)}</span>
                        <span class="ms-2">${estado.estado_registro || 'Sin estado'}</span>
                    </div>
                    <div>${botonesHtml}</div>
                </div>
                <div class="collapse${confirmado ? '' : ' show'}" id="${collapseId}">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                            <tr><th>Código</th><th>Producto</th><th class="text-center">Cant.</th><th class="text-end">Importe</th></tr>
                        </thead>
                        <tbody>${filasDetalle}</tbody>
                    </table>
                </div>
            </div>`;
        });

        cont.html(html);
    }

    $(document).on('click', '.btn-toggle-remito-picking', function () {
        $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
    });

    $(document).on('click', '.btn-ver-resumen-remito-picking', function () {
        verResumenRemitoPicking($(this).data('id'));
    });

    function verResumenRemitoPicking(remitoId) {
        $.ajax({
            url: 'ventas_remitos_ajax.php',
            type: 'GET',
            data: { accion: 'obtener', venta_remito_id: remitoId, empresa_idx: empresa_idx },
            dataType: 'json',
            success: function (res) {
                if (!res || !res.venta_remito_id) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Error al obtener datos del remito', confirmButtonText: 'Entendido' });
                    return;
                }
                var numero = res.comprobante_nro > 0
                    ? `${res.comprobante_tipo || 'Remito'} #${res.comprobante_nro}`
                    : `${res.comprobante_tipo || 'Remito'} (sin numerar)`;

                var filasDetalle = '';
                var total = 0;
                (res.detalles || []).forEach(function (d) {
                    var importe = parseFloat(d.importe_linea) || 0;
                    total += importe;
                    filasDetalle += `<tr>
                        <td>${d.producto_codigo || ''}</td>
                        <td class="text-start">${d.producto_nombre || ''}</td>
                        <td class="text-center">${formatMoneda(d.cantidad)}</td>
                        <td class="text-end">$${formatMoneda(importe)}</td>
                    </tr>`;
                });

                var html = `
                    <div class="text-start small">
                        <div class="row g-2 mb-2">
                            <div class="col-6"><strong>Punto de Venta:</strong> ${res.punto_venta_nombre || '-'}</div>
                            <div class="col-6"><strong>Fecha:</strong> ${formatFechaCorta(res.f_emision)}</div>
                            <div class="col-6"><strong>Cliente:</strong> ${res.entidad_nombre || '-'}</div>
                            <div class="col-6"><strong>Depósito:</strong> ${res.boca_nombre || '-'}</div>
                        </div>
                        ${res.observaciones ? `<div class="mb-2"><strong>Observaciones:</strong> ${escapeHtml(res.observaciones)}</div>` : ''}
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr><th>Código</th><th>Producto</th><th class="text-center">Cant.</th><th class="text-end">Importe</th></tr>
                            </thead>
                            <tbody>${filasDetalle}</tbody>
                            <tfoot>
                                <tr class="fw-bold"><td colspan="3" class="text-end">Total</td><td class="text-end">$${formatMoneda(total)}</td></tr>
                            </tfoot>
                        </table>
                    </div>`;

                Swal.fire({ title: numero, html: html, width: 650, confirmButtonText: 'Cerrar' });
            },
            error: function () {
                Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo obtener el remito', confirmButtonText: 'Entendido' });
            }
        });
    }

    $(document).on('click', '.btn-accion-remito-picking', function () {
        var remitoId = $(this).data('id');
        var accionJs = $(this).data('accion');
        var confirmable = $(this).data('confirmable');
        var comprobanteInfo = $(this).data('comprobante') || ('Remito #' + remitoId);

        function ejecutar() {
            $.post('ventas_remitos_ajax.php', {
                accion: 'ejecutar_accion',
                venta_remito_id: remitoId,
                accion_js: accionJs,
                empresa_idx: empresa_idx,
                pagina_idx: REMITOS_PAGINA_IDX
            }, function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: res.message || 'Acción ejecutada', showConfirmButton: false, timer: 1500, toast: true, position: 'top-end' });
                    abrirPedidoPicking(pedidoActual.venta_pedido_id);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.error || 'Error al ejecutar la acción', confirmButtonText: 'Entendido' });
                }
            }, 'json').fail(function () {
                Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor', confirmButtonText: 'Entendido' });
            });
        }

        if (confirmable == 1) {
            Swal.fire({
                title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                html: `¿Está seguro de <strong>${accionJs}</strong> el remito<br><strong>${comprobanteInfo}</strong>?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: `Sí, ${accionJs}`,
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                allowOutsideClick: false
            }).then(function (result) {
                if (result.isConfirmed) ejecutar();
            });
        } else {
            ejecutar();
        }
    });

    // Resumen "pedido vs enviado", en la solapa Remitos — se calcula del
    // propio detalle del pedido (cantidad vs cantidad_entregada), no hace
    // falta otra consulta.
    function renderizarResumenPedidoVsEnviado() {
        var detalles = (pedidoActual && pedidoActual.detalles) || [];
        var cantidadPedida = 0, cantidadEnviada = 0, montoPedido = 0, montoEnviado = 0;

        detalles.forEach(function (d) {
            var cant = parseFloat(d.cantidad) || 0;
            var entregada = parseFloat(d.cantidad_entregada) || 0;
            var total = parseFloat(d.total_linea) || 0;
            cantidadPedida += cant;
            cantidadEnviada += entregada;
            montoPedido += total;
            montoEnviado += cant > 0 ? (entregada / cant) * total : 0;
        });

        $('#resumen_cantidad_pedida').text(formatMoneda(cantidadPedida));
        $('#resumen_cantidad_enviada').text(formatMoneda(cantidadEnviada));
        $('#resumen_cantidad_pendiente').text(formatMoneda(Math.max(0, cantidadPedida - cantidadEnviada)));
        $('#resumen_monto_pedido').text('$' + formatMoneda(montoPedido));
        $('#resumen_monto_enviado').text('$' + formatMoneda(montoEnviado));
    }

    // Si ya hay un remito sin numerar (abierto) para este pedido, lo que se
    // agregue en esta pantalla va DENTRO de ese remito (editar), no crea uno
    // nuevo — un pedido solo debería tener un remito "en construcción" por vez.
    function detectarYCargarRemitoAbierto() {
        var remitoAbierto = remitosPedidoActual.find(function (r) { return !(r.comprobante_nro > 0); });

        if (!remitoAbierto) {
            remitoEditandoId = null;
            remitoLineasOriginalesPorVpd = {};
            remitoDetallesNuevo = [];
            $('#titulo-card-remito-picking').html('<i class="fas fa-truck me-2"></i>Remito a cargar');
            renderizarPendientesPedido();
            renderizarPendientesOtros();
            renderizarRemitoDetalleNuevo();
            return;
        }

        $.ajax({
            url: 'ventas_remitos_ajax.php',
            type: 'GET',
            data: { accion: 'obtener', venta_remito_id: remitoAbierto.venta_remito_id, empresa_idx: empresa_idx },
            dataType: 'json',
            success: function (res) {
                if (!res || !res.venta_remito_id) return;

                remitoEditandoId = res.venta_remito_id;
                remitoLineasOriginalesPorVpd = {};
                remitoDetallesNuevo = (res.detalles || []).map(function (d) {
                    if (d.venta_pedido_detalle_id) {
                        remitoLineasOriginalesPorVpd[d.venta_pedido_detalle_id] =
                            (remitoLineasOriginalesPorVpd[d.venta_pedido_detalle_id] || 0) + (parseFloat(d.cantidad) || 0);
                    }
                    return {
                        venta_pedido_detalle_id: d.venta_pedido_detalle_id,
                        producto_id: d.producto_id,
                        producto_codigo: d.producto_codigo,
                        producto_nombre: d.producto_nombre,
                        origen_pedido: '',
                        cantidad: parseFloat(d.cantidad) || 0
                    };
                });

                $('#titulo-card-remito-picking').html('<i class="fas fa-truck me-2"></i>Remito a cargar — sumando a lo que ya tenía este remito abierto');
                renderizarPendientesPedido();
                renderizarPendientesOtros();
                renderizarRemitoDetalleNuevo();
            }
        });
    }

    // ---------- Guardar remito ----------
    // Valida y guarda (agregar o editar, según corresponda) el remito en
    // construcción. onGuardado(remitoId) se llama solo si guardó bien — lo usan
    // tanto "Guardar Remito" (que ahí termina) como "Confirmar Remito" (que
    // además dispara la transición de confirmar a continuación).
    function guardarRemitoPicking(btn, onGuardado) {
        if (!$('#picking_punto_venta_id').val()) {
            Swal.fire({ icon: 'warning', title: 'Falta punto de venta', text: 'No hay un punto de venta de depósito válido para este pedido', confirmButtonText: 'Entendido' });
            return;
        }
        if (!$('#picking_remito_comprobante_tipo_id').val()) {
            Swal.fire({ icon: 'warning', title: 'Falta tipo de comprobante', text: 'No hay un tipo de comprobante de remito habilitado', confirmButtonText: 'Entendido' });
            return;
        }
        if (!$('#picking_remito_f_emision').val()) {
            Swal.fire({ icon: 'warning', title: 'Falta la fecha', text: 'La fecha de emisión es obligatoria', confirmButtonText: 'Entendido' });
            return;
        }
        if (remitoDetallesNuevo.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Sin líneas', text: 'Agregá al menos un producto al remito', confirmButtonText: 'Entendido' });
            return;
        }

        var detallesParaEnviar = remitoDetallesNuevo.map(function (d) {
            return {
                producto_id: d.producto_id,
                venta_pedido_detalle_id: d.venta_pedido_detalle_id,
                cantidad: d.cantidad
            };
        });

        var idEnEdicion = remitoEditandoId; // se captura antes de que abrirPedidoPicking lo pueda pisar

        var originalText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        var datosPost = {
            accion: idEnEdicion ? 'editar' : 'agregar',
            punto_venta_id: $('#picking_punto_venta_id').val(),
            comprobante_tipo_id: $('#picking_remito_comprobante_tipo_id').val(),
            entidad_id: pedidoActual.entidad_id,
            entidad_sucursal_id: pedidoActual.entidad_sucursal_id || '',
            f_emision: $('#picking_remito_f_emision').val(),
            observaciones: $('#picking_remito_observaciones').val(),
            detalles: JSON.stringify(detallesParaEnviar),
            empresa_idx: empresa_idx,
            pagina_idx: REMITOS_PAGINA_IDX
        };
        if (idEnEdicion) {
            datosPost.venta_remito_id = idEnEdicion;
        }

        $.ajax({
            url: 'ventas_remitos_ajax.php',
            type: 'POST',
            data: datosPost,
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html(originalText);
                if (res.resultado) {
                    var remitoId = idEnEdicion || res.venta_remito_id;
                    if (typeof onGuardado === 'function') {
                        onGuardado(remitoId);
                    }
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.error || 'Error al guardar el remito', confirmButtonText: 'Entendido' });
                }
            },
            error: function () {
                btn.prop('disabled', false).html(originalText);
                Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor', confirmButtonText: 'Entendido' });
            }
        });
    }

    function refrescarTrasGuardar() {
        remitoEditandoId = null;
        remitoLineasOriginalesPorVpd = {};
        remitoDetallesNuevo = [];
        $('#picking_remito_observaciones').val('');
        abrirPedidoPicking(pedidoActual.venta_pedido_id); // se queda en el pedido: refresca pendientes, PV y encabezado
        tabla.ajax.reload(null, false);
    }

    $(document).on('click', '#btnGuardarRemitoPicking', function () {
        var btn = $(this);
        guardarRemitoPicking(btn, function () {
            Swal.fire({ icon: 'success', title: '¡Remito guardado!', showConfirmButton: false, timer: 1500, toast: true, position: 'top-end' });
            refrescarTrasGuardar();
        });
    });

    // "Confirmar Remito" = guardar + disparar la transición de confirmar a
    // continuación. El accion_js de "confirmar" no se hardcodea (no lo
    // conocemos con certeza): se busca por el TEXTO del botón que trae
    // obtener_remitos_pedido para el estado en el que quedó este remito recién
    // guardado — ese texto es para lectura humana, mucho menos riesgoso de
    // adivinar mal que el código interno.
    $(document).on('click', '#btnConfirmarRemitoPicking', function () {
        var btn = $(this);
        guardarRemitoPicking(btn, function (remitoId) {
            $.ajax({
                url: 'ventas_pedidos_ajax.php',
                type: 'GET',
                data: {
                    accion: 'obtener_remitos_pedido',
                    venta_pedido_id: pedidoActual.venta_pedido_id,
                    pagina_idx_remitos: REMITOS_PAGINA_IDX,
                    empresa_idx: empresa_idx
                },
                dataType: 'json',
                success: function (remitos) {
                    var remito = (remitos || []).find(function (r) { return r.venta_remito_id == remitoId; });
                    var boton = remito && (remito.botones || []).find(function (b) {
                        var texto = ((b.descripcion || '') + ' ' + (b.nombre_funcion || '')).toLowerCase();
                        return texto.indexOf('confirmar') !== -1;
                    });

                    if (!boton) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Remito guardado',
                            text: 'Se guardó, pero no se encontró una acción de "confirmar" disponible para su estado actual. Confirmalo manualmente si corresponde.',
                            confirmButtonText: 'Entendido'
                        });
                        refrescarTrasGuardar();
                        return;
                    }

                    $.post('ventas_remitos_ajax.php', {
                        accion: 'ejecutar_accion',
                        venta_remito_id: remitoId,
                        accion_js: boton.accion_js,
                        empresa_idx: empresa_idx,
                        pagina_idx: REMITOS_PAGINA_IDX
                    }, function (res2) {
                        if (res2.success) {
                            Swal.fire({ icon: 'success', title: '¡Remito guardado y confirmado!', showConfirmButton: false, timer: 1500, toast: true, position: 'top-end' });
                        } else {
                            Swal.fire({ icon: 'warning', title: 'Guardado, no se pudo confirmar', text: res2.error || 'Error al confirmar', confirmButtonText: 'Entendido' });
                        }
                        refrescarTrasGuardar();
                    }, 'json').fail(function () {
                        Swal.fire({ icon: 'warning', title: 'Guardado, no se pudo confirmar', text: 'Error de conexión al intentar confirmar', confirmButtonText: 'Entendido' });
                        refrescarTrasGuardar();
                    });
                },
                error: function () {
                    Swal.fire({ icon: 'warning', title: 'Guardado, no se pudo confirmar', text: 'Error de conexión al buscar la acción de confirmar', confirmButtonText: 'Entendido' });
                    refrescarTrasGuardar();
                }
            });
        });
    });

    inicializarDataTable();
});
