$(document).ready(function () {
    const empresa_idx = EMPRESA_ID;
    const pagina_idx = PAGINA_ID;

    var tabla;
    var currentPage = 0;
    var currentOrder = [[4, 'desc']];
    var currentSearch = '';

    // Estado del formulario
    var detalles = [];
    var clienteActualId = null;
    var clienteSucursalActualId = null;
    var pedidosPendientesCliente = []; // respuesta cruda de obtener_pedidos_pendientes_cliente

    // ========== CANTIDAD YA COMPROMETIDA EN ESTE BORRADOR PARA UNA LÍNEA DE PEDIDO ==========
    // Nota importante (simplificación deliberada, ver aviso al final de la entrega):
    // al EDITAR un remito ya guardado, el pendiente que devuelve el servidor todavía
    // incluye lo que este mismo remito reservó la vez anterior (recién se libera al
    // guardar). Por eso, durante la edición, el pendiente que se muestra acá puede verse
    // más bajo de lo real para esas líneas puntuales. Es un comportamiento conservador
    // (nunca deja pasarse del pendiente real) y no bloquea nada: si hace falta sumar más
    // cantidad a una línea ya incluida, se elimina del detalle y se vuelve a cargar.
    function cantidadYaComprometida(ventaPedidoDetalleId) {
        return detalles
            .filter(function (d) { return d.venta_pedido_detalle_id == ventaPedidoDetalleId; })
            .reduce(function (acc, d) { return acc + (parseFloat(d.cantidad) || 0); }, 0);
    }

    // ========== FUNCIONES DE DATATABLE ==========
    function inicializarDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaVentasRemitos')) {
            $('#tablaVentasRemitos').DataTable().destroy();
            $('#tablaVentasRemitos tbody').empty();
        }

        tabla = $('#tablaVentasRemitos').DataTable({
            ajax: {
                url: 'ventas_remitos_ajax.php',
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

                if (data.search && data.search.search !== undefined) {
                    var searchValue = data.search.search;
                    currentSearch = (searchValue === '-1' || searchValue === '') ? '' : searchValue;
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
                            if (col.search && col.search.search === '-1') col.search.search = '';
                        });
                    }
                    return data;
                }
                return null;
            },
            dom: '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>' +
                '<"clear">',
            pageLength: 50,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],

            columns: [
                {
                    data: 'comprobante_tipo',
                    className: 'text-center',
                    render: function (data) { return `<span>${data || ''}</span>`; }
                },
                {
                    data: 'sucursal_nombre',
                    className: 'text-center',
                    render: function (data) { return `<span>${data || ''}</span>`; }
                },
                {
                    data: 'deposito_nombre',
                    className: 'text-center',
                    render: function (data) { return `<span>${data || ''}</span>`; }
                },
                {
                    data: 'punto_venta_nombre',
                    className: 'text-center',
                    render: function (data) { return `<span>${data || ''}</span>`; }
                },
                {
                    data: null,
                    className: 'text-center',
                    render: function (data, type, row) {
                        var numero = row.comprobante_nro || '';
                        return type === 'export' ? numero : `<span>${numero}</span>`;
                    }
                },
                {
                    data: null,
                    render: function (data, type, row) {
                        if (type === 'export') return data.entidad_nombre || '';
                        return `<div>${data.entidad_nombre || ''}</div>
                                <small class="text-muted">${data.entidad_fantasia || ''}</small>`;
                    }
                },
                {
                    data: 'f_emision',
                    className: 'text-center',
                    render: function (data, type) {
                        if (type === 'export' || !data) return data || '';
                        var parts = data.split('-');
                        return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : data;
                    }
                },
                {
                    data: 'total',
                    className: 'text-end',
                    render: function (data, type) {
                        var valor = parseFloat(data) || 0;
                        if (type === 'export') return valor.toFixed(2);
                        if (type === 'sort' || type === 'filter') return valor;
                        return `<span class="text-primary">$${valor.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>`;
                    }
                },
                {
                    data: 'estado_info',
                    className: 'text-center',
                    render: function (data, type) {
                        if (type === 'export') return (data && data.estado_registro) ? data.estado_registro : '';
                        return `<span>${(data && data.estado_registro) ? data.estado_registro : ''}</span>`;
                    }
                },
                {
                    data: 'botones',
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    width: '250px',
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
                                var comprobanteInfo = `${row.comprobante_tipo || ''} -${row.comprobante_nro || ''}`;
                                var clienteInfo = row.entidad_nombre || row.entidad_fantasia || '';

                                var botonHtml = `<button type="button" class="btn ${claseBoton} btn-accion"
                                                title="${titulo}"
                                                data-id="${row.venta_remito_id}"
                                                data-accion="${accionJs}"
                                                data-confirmable="${esConfirmable}"
                                                data-comprobante="${comprobanteInfo}"
                                                data-cliente="${clienteInfo}">
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
            language: { url: '//cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json' },
            order: currentOrder,
            responsive: true,
            createdRow: function (row, data) {
                if (data.estado_info && data.estado_info.codigo_estandar === 'CONFIRMADO') {
                    $(row).addClass('table-success');
                } else if (data.estado_info && (data.estado_info.codigo_estandar === 'CANCELADO' || data.estado_info.codigo_estandar === 'ANULADO')) {
                    $(row).addClass('table-danger');
                } else if (data.estado_info && data.estado_info.codigo_estandar === 'PENDIENTE') {
                    $(row).addClass('table-warning');
                }
            },
            initComplete: function () {
                setTimeout(function () {
                    $('#tablaVentasRemitos_length').addClass('dataTables_length_custom');
                    $('#tablaVentasRemitos_filter').addClass('dataTables_filter_custom');

                    if ($('#tablaVentasRemitos_length').html().trim() === '') {
                        var selectHtml = '<label>Mostrar <select name="tablaVentasRemitos_length" aria-controls="tablaVentasRemitos" class="form-select form-select-sm"><option value="10">10</option><option value="25">25</option><option value="50" selected="">50</option><option value="100">100</option><option value="-1">Todos</option></select> registros</label>';
                        $('#tablaVentasRemitos_length').html(selectHtml);
                        $('#tablaVentasRemitos_length select').on('change', function () {
                            tabla.page.len($(this).val()).draw();
                        });
                    }

                    if ($('#tablaVentasRemitos_filter').html().trim() === '') {
                        var filterHtml = '<label>Buscar:<input type="search" class="form-control form-control-sm" placeholder="" aria-controls="tablaVentasRemitos"></label>';
                        $('#tablaVentasRemitos_filter').html(filterHtml);
                        $('#tablaVentasRemitos_filter input').on('keyup', function () {
                            tabla.search($(this).val()).draw();
                        });
                    }
                }, 100);

                $('#filtro_cliente').on('keyup', function () {
                    tabla.column(5).search(this.value).draw();
                });
                $('#filtro_estado').on('keyup', function () {
                    tabla.column(8).search(this.value).draw();
                });

                new $.fn.dataTable.Buttons(tabla, {
                    buttons: ['excelHtml5', 'pdfHtml5', 'csvHtml5', 'print']
                }).container().appendTo($('#tablaVentasRemitos_wrapper .col-md-6:eq(1)'));

                $(tabla.table().container()).on('page.dt', function () { currentPage = tabla.page(); });
                $(tabla.table().container()).on('order.dt', function () { currentOrder = tabla.order(); });
                $(tabla.table().container()).on('search.dt', function () { currentSearch = tabla.search(); });
            }
        });

        inicializarEventos();
    }

    function inicializarEventos() {
        $('#btnRecargar').off('click').on('click', function () {
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            var savedState = { page: tabla.page(), order: tabla.order(), search: tabla.search() };
            tabla.ajax.reload(function () {
                if (savedState.page !== undefined) tabla.page(savedState.page).draw('page');
                if (savedState.search) tabla.search(savedState.search).draw();
                btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
            }, false);
        });

        $('#btnExportarExcel').on('click', function () { tabla.button('.buttons-excel').trigger(); });
        $('#btnExportarPDF').on('click', function () { tabla.button('.buttons-pdf').trigger(); });
        $('#btnExportarCSV').on('click', function () { tabla.button('.buttons-csv').trigger(); });
        $('#btnExportarPrint').on('click', function () { tabla.button('.buttons-print').trigger(); });
    }

    function cargarBotonAgregar() {
        $.get('ventas_remitos_ajax.php', { accion: 'obtener_boton_agregar', pagina_idx: pagina_idx }, function (botonAgregar) {
            if (botonAgregar && botonAgregar.nombre_funcion) {
                var icono = botonAgregar.icono_clase ? `<i class="${botonAgregar.icono_clase} me-1"></i>` : '';
                var colorClase = 'btn-primary';
                if (botonAgregar.bg_clase && botonAgregar.text_clase) {
                    colorClase = botonAgregar.bg_clase + ' ' + botonAgregar.text_clase;
                } else if (botonAgregar.color_clase) {
                    colorClase = botonAgregar.color_clase;
                }
                $('#contenedor-boton-agregar').html(
                    `<button type="button" class="btn ${colorClase}" id="btnNuevo">${icono}${botonAgregar.nombre_funcion}</button>`
                );
            } else {
                $('#contenedor-boton-agregar').html(
                    '<button type="button" class="btn btn-primary" id="btnNuevo"><i class="fas fa-plus me-1"></i>Nuevo Remito</button>'
                );
            }
        }, 'json').fail(function () {
            $('#contenedor-boton-agregar').html(
                '<button type="button" class="btn btn-primary" id="btnNuevo"><i class="fas fa-plus me-1"></i>Nuevo Remito</button>'
            );
        });
    }

    function cargarPuntosVenta(sucursalId, callback) {
        if (!sucursalId) {
            $('#punto_venta_id').html('<option value="">Primero seleccione sucursal</option>').prop('disabled', true);
            if (callback) callback();
            return;
        }

        $.ajax({
            url: 'ventas_remitos_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_puntos_venta', sucursal_id: sucursalId, empresa_idx: empresa_idx },
            dataType: 'json',
            success: function (data) {
                var options = '<option value="">Seleccionar punto de venta</option>';
                if (data && data.length > 0) {
                    data.forEach(function (item) {
                        options += `<option value="${item.punto_venta_id}">${item.punto_venta_nombre} (${item.punto_venta_codigo})</option>`;
                    });
                    $('#punto_venta_id').prop('disabled', false);
                } else {
                    options = '<option value="">No hay puntos de venta disponibles</option>';
                    $('#punto_venta_id').prop('disabled', true);
                }
                $('#punto_venta_id').html(options);
                if (callback) callback();
            },
            error: function () {
                $('#punto_venta_id').html('<option value="">Error al cargar</option>').prop('disabled', true);
                if (callback) callback();
            }
        });
    }

    function cargarClientesYSucursales() {
        $.ajax({
            url: 'ventas_remitos_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_clientes_con_sucursales', empresa_idx: empresa_idx },
            dataType: 'json',
            success: function (data) {
                var options = '<option value="">Seleccionar cliente o sucursal</option>';
                if (data && data.length > 0) {
                    data.forEach(function (item) {
                        if (item.sucursales && item.sucursales.length > 0) {
                            item.sucursales.forEach(function (sucursal) {
                                options += `<option value="S-${sucursal.sucursal_id}" data-entidad-id="${item.entidad_id}" data-sucursal-id="${sucursal.sucursal_id}">${item.entidad_nombre} - ${sucursal.sucursal_nombre}</option>`;
                            });
                        } else {
                            options += `<option value="P-${item.entidad_id}" data-entidad-id="${item.entidad_id}" data-sucursal-id="">${item.entidad_nombre}</option>`;
                        }
                    });
                } else {
                    options = '<option value="">No hay clientes disponibles</option>';
                }
                $('#entidad_combo').html(options);
            },
            error: function () {
                $('#entidad_combo').html('<option value="">Error al cargar</option>');
            }
        });
    }

    $('#sucursal_id').on('change', function () {
        cargarPuntosVenta($(this).val());
    });

    $('#entidad_combo').on('change', function () {
        var selectedOption = $(this).find('option:selected');
        var entidadId = selectedOption.data('entidad-id');
        var sucursalId = selectedOption.data('sucursal-id');
        var valorCombo = $(this).val();
        var clienteAnteriorId = clienteActualId;

        if (valorCombo) {
            var tipo = valorCombo.split('-')[0];

            $('#entidad_id').val(entidadId);
            $('#entidad_sucursal_id').val(tipo === 'S' ? sucursalId : '');
            clienteActualId = parseInt(entidadId);
            clienteSucursalActualId = tipo === 'S' ? parseInt(sucursalId) : null;
        } else {
            $('#entidad_id').val('');
            $('#entidad_sucursal_id').val('');
            clienteActualId = null;
            clienteSucursalActualId = null;
        }

        if (clienteAnteriorId !== clienteActualId) {
            if (detalles.length > 0) {
                detalles = [];
                renderizarDetalles();
                actualizarTotales();
                Swal.fire({
                    icon: 'info',
                    title: 'Cliente cambiado',
                    text: 'Se limpiaron los productos cargados: correspondían a los pedidos del cliente anterior.',
                    showConfirmButton: false,
                    timer: 2200,
                    toast: true,
                    position: 'top-end'
                });
            }
            cargarPedidosPendientes(clienteActualId);
            resetBusquedaProductoLibre();
        }
    });

    // ========== PEDIDOS PENDIENTES DEL CLIENTE ==========
    function cargarPedidosPendientes(entidadId) {
        if (!entidadId) {
            pedidosPendientesCliente = [];
            renderizarPendientes();
            return;
        }

        $.ajax({
            url: 'ventas_remitos_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_pedidos_pendientes_cliente', entidad_id: entidadId, empresa_idx: empresa_idx },
            dataType: 'json',
            success: function (data) {
                pedidosPendientesCliente = data || [];
                renderizarPendientes();
            },
            error: function () {
                pedidosPendientesCliente = [];
                renderizarPendientes();
                $('#contenedor-pendientes').html(
                    '<div class="text-danger small p-2"><i class="fas fa-triangle-exclamation me-1"></i>Error al consultar los pedidos pendientes del cliente.</div>'
                );
            }
        });
    }

    function renderizarPendientes() {
        var cont = $('#contenedor-pendientes');

        if (!clienteActualId) {
            cont.html('<div class="text-center text-muted small p-2"><i class="fas fa-arrow-up me-1"></i>Seleccione un cliente en la solapa "Datos del Remito"</div>');
            return;
        }

        if (!pedidosPendientesCliente || pedidosPendientesCliente.length === 0) {
            cont.html('<div class="text-center text-muted small p-2"><i class="fas fa-circle-check me-1"></i>Este cliente no tiene pedidos con entregas pendientes.</div>');
            return;
        }

        var html = `<table class="table table-sm table-bordered table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Pedido</th>
                    <th>Fecha</th>
                    <th>Código</th>
                    <th>Producto</th>
                    <th class="text-center">IVA</th>
                    <th class="text-end">Pendiente</th>
                    <th class="text-center" width="110">Cant. a remitir</th>
                    <th class="text-center" width="90">Acción</th>
                </tr>
            </thead>
            <tbody>`;

        pedidosPendientesCliente.forEach(function (pedido) {
            var numeroPedido = pedido.comprobante_nro > 0
                ? `${pedido.comprobante_tipo || 'Pedido'} #${pedido.comprobante_nro}`
                : `${pedido.comprobante_tipo || 'Pedido'} (sin numerar)`;

            pedido.detalles.forEach(function (linea) {
                var yaComprometido = cantidadYaComprometida(linea.venta_pedido_detalle_id);
                var pendienteEfectivo = Math.max(0, linea.pendiente - yaComprometido);
                var agotado = pendienteEfectivo <= 0.0001;
                var ivaPorcentaje = parseFloat(linea.iva_porcentaje || 0);

                html += `<tr class="pendiente-fila ${agotado ? 'pendiente-agotada' : ''}">
                    <td>${numeroPedido}</td>
                    <td>${formatFecha(pedido.f_emision)}</td>
                    <td>${linea.producto_codigo || ''}</td>
                    <td>${linea.producto_nombre || ''}</td>
                    <td class="text-center">${ivaPorcentaje.toFixed(2)}%</td>
                    <td class="text-end">${formatMoneda(pendienteEfectivo)}</td>
                    <td>
                        <input type="number" class="form-control form-control-sm no-spinner input-cantidad-pendiente"
                            value="${pendienteEfectivo.toFixed(2)}" step="0.01" min="0.01"
                            max="${pendienteEfectivo}" ${agotado ? 'disabled' : ''}>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-success btn-agregar-pendiente" ${agotado ? 'disabled' : ''}
                            data-vpd-id="${linea.venta_pedido_detalle_id}"
                            data-producto-id="${linea.producto_id}"
                            data-codigo="${linea.producto_codigo || ''}"
                            data-nombre="${linea.producto_nombre || ''}"
                            data-precio="${linea.precio_unitario_neto}"
                            data-precio-bruto="${linea.precio_unitario_bruto || 0}"
                            data-descuento-pct="${linea.descuento_general_pct || 0}"
                            data-iva-id="${linea.iva_alicuota_id || ''}"
                            data-iva="${ivaPorcentaje}"
                            data-pedido-id="${pedido.venta_pedido_id}"
                            data-pedido-nro="${pedido.comprobante_nro}"
                            data-pedido-tipo="${pedido.comprobante_tipo || ''}"
                            data-pendiente="${pendienteEfectivo}"
                            title="${agotado ? 'Ya incluido en este remito' : 'Agregar al remito'}">
                            <i class="fas fa-${agotado ? 'check' : 'plus'}"></i>
                        </button>
                    </td>
                </tr>`;
            });
        });

        html += '</tbody></table>';
        cont.html(html);
    }

    $(document).on('click', '.btn-agregar-pendiente', function () {
        var btn = $(this);
        var fila = btn.closest('tr');
        var cantidad = parseFloat(fila.find('.input-cantidad-pendiente').val());
        var pendienteEfectivo = parseFloat(btn.data('pendiente'));

        if (!cantidad || cantidad <= 0) {
            Swal.fire({ icon: 'warning', title: 'Cantidad inválida', text: 'Ingrese una cantidad mayor a 0', confirmButtonText: 'Entendido' });
            return;
        }
        if (cantidad > pendienteEfectivo + 0.0001) {
            Swal.fire({ icon: 'warning', title: 'Cantidad excede el pendiente', text: `El máximo disponible es ${formatMoneda(pendienteEfectivo)}`, confirmButtonText: 'Entendido' });
            return;
        }

        var vpdId = parseInt(btn.data('vpd-id'));
        var existente = detalles.find(function (d) { return d.venta_pedido_detalle_id == vpdId; });

        var mensajeToast = 'Línea agregada';

        if (existente) {
            // Ya hay una línea de este mismo renglón de pedido: se suma la cantidad
            // en lugar de crear una línea duplicada (precio, descuento e IVA no cambian).
            existente.cantidad = (parseFloat(existente.cantidad) || 0) + cantidad;
            existente.importe_linea = existente.cantidad * existente.precio_unitario_neto;
            existente.iva_importe = existente.importe_linea * (existente.iva_porcentaje / 100);
            mensajeToast = 'Cantidad sumada a la línea existente';
        } else {
            var precioBruto = parseFloat(btn.data('precio-bruto')) || 0;
            var descuentoPct = parseFloat(btn.data('descuento-pct')) || 0;
            var descuentoGeneral = precioBruto * (descuentoPct / 100);
            var precioNeto = precioBruto - descuentoGeneral;
            var ivaPct = parseFloat(btn.data('iva')) || 0;
            var importeNeto = cantidad * precioNeto;
            var ivaImporte = importeNeto * (ivaPct / 100);

            detalles.push({
                detalle_idx: 'temp_' + new Date().getTime() + '_' + Math.random(),
                venta_remito_detalle_id: 0,
                venta_pedido_detalle_id: vpdId,
                producto_id: parseInt(btn.data('producto-id')),
                producto_codigo: btn.data('codigo'),
                producto_nombre: btn.data('nombre'),
                cantidad: cantidad,
                precio_unitario_bruto: precioBruto,
                descuento_general_pct: descuentoPct,
                descuento_general: descuentoGeneral,
                precio_unitario_neto: precioNeto,
                importe_linea: importeNeto,
                iva_alicuota_id: btn.data('iva-id') || null,
                iva_porcentaje: ivaPct,
                iva_importe: ivaImporte,
                origen_pedido_nro: btn.data('pedido-nro'),
                origen_pedido_tipo: btn.data('pedido-tipo')
            });
        }

        renderizarDetalles();
        actualizarTotales();
        renderizarPendientes();

        Swal.fire({ icon: 'success', title: mensajeToast, showConfirmButton: false, timer: 1000, toast: true, position: 'top-end' });
    });

    // ========== BÚSQUEDA DE PRODUCTO SIN PEDIDO (tags + estilo "carrito") ==========
    // El filtro funciona igual que el buscador por etiquetas del ABM de productos:
    // cada palabra que se escribe se convierte en un "tag" dentro del propio campo de
    // texto al presionar espacio (se combinan todas como filtro). Los resultados
    // aparecen debajo del campo (no en un desplegable flotante); cada producto tiene su
    // propia cantidad y su propio botón de agregar, igual que en "Pedidos pendientes".
    var tagsProductoLibre = [];
    var tagsProductoLibreInput = $('#busqueda_producto');
    var tagsProductoLibreContainer = $('#busqueda_producto_container');
    var ultimosResultadosBusqueda = [];

    function renderizarResultadosVacio(mensajeHtml) {
        $('#resultados_busqueda').html(`<div class="text-center text-muted small p-2">${mensajeHtml}</div>`);
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function renderizarResultadosBusqueda(productos) {
        ultimosResultadosBusqueda = productos || [];
        var cont = $('#resultados_busqueda');

        if (ultimosResultadosBusqueda.length === 0) {
            renderizarResultadosVacio('<i class="fas fa-circle-info me-1"></i>No se encontraron productos para ese filtro.');
            return;
        }

        var html = `<table class="table table-sm table-bordered table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th class="text-center">IVA</th>
                    <th class="text-end">Precio Ref.</th>
                    <th class="text-center" width="110">Cantidad</th>
                    <th class="text-center" width="90">Acción</th>
                </tr>
            </thead>
            <tbody>`;

        ultimosResultadosBusqueda.forEach(function (item, index) {
            var ivaPorcentaje = parseFloat(item.iva_porcentaje || 0);
            var precio = parseFloat(item.precio_neto || 0);

            html += `<tr class="resultado-libre-fila">
                <td>${item.producto_codigo || ''}</td>
                <td>${item.producto_nombre || ''}
                    ${item.compatibilidad_busqueda ? `<small class="text-muted d-block">Compat.: ${item.compatibilidad_busqueda}</small>` : ''}
                </td>
                <td class="text-center">${ivaPorcentaje.toFixed(2)}%</td>
                <td class="text-end">$${formatMoneda(precio)}</td>
                <td>
                    <input type="number" class="form-control form-control-sm no-spinner input-cantidad-libre"
                        value="1.00" step="0.01" min="0.01">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-success btn-agregar-libre"
                        data-index="${index}"
                        data-id="${item.producto_id}"
                        data-codigo="${item.producto_codigo}"
                        data-nombre="${item.producto_nombre}"
                        data-precio="${precio}"
                        data-precio-bruto="${parseFloat(item.precio_final || 0)}"
                        data-descuento-pct="${parseFloat(item.descuento_general_pct || 0)}"
                        data-iva-id="${item.iva_alicuota_id || ''}"
                        data-iva="${ivaPorcentaje}"
                        title="Agregar al remito">
                        <i class="fas fa-plus"></i>
                    </button>
                </td>
            </tr>`;
        });

        html += '</tbody></table>';
        cont.html(html);
    }

    function inicializarBuscadorTagsProductoLibre() {
        tagsProductoLibreContainer.on('click', function (e) {
            if (e.target === this || $(e.target).is('#busqueda_producto_container')) tagsProductoLibreInput.focus();
        });

        // Usar 'input' además de 'keydown' por si el espacio llega de otra forma
        // (teclado virtual, autocompletado), igual que en el buscador de productos.
        tagsProductoLibreInput.on('input', function () {
            var value = $(this).val().trim();
            if (value.includes(' ')) {
                var palabras = value.split(/\s+/);
                palabras.forEach(function (palabra) {
                    if (palabra.length > 0) agregarTagProductoLibre(palabra);
                });
                $(this).val('');
                ejecutarBusquedaProductoLibre();
            }
        });

        tagsProductoLibreInput.on('keydown', function (e) {
            var value = $(this).val().trim();
            if (e.key === ' ' || e.key === 'Space') {
                e.preventDefault();
                if (value.length > 0) {
                    agregarTagProductoLibre(value);
                    $(this).val('');
                    ejecutarBusquedaProductoLibre();
                }
            } else if (e.key === 'Backspace' && value === '' && tagsProductoLibre.length > 0) {
                eliminarTagProductoLibre(tagsProductoLibre.length - 1);
                ejecutarBusquedaProductoLibre();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (value.length > 0) {
                    agregarTagProductoLibre(value);
                    $(this).val('');
                    ejecutarBusquedaProductoLibre();
                }
            } else if (e.key === 'Escape') {
                $(this).blur();
            }
        });

        tagsProductoLibreInput.on('paste', function () {
            setTimeout(function () {
                var value = tagsProductoLibreInput.val().trim();
                if (value) {
                    var palabras = value.split(/\s+/);
                    palabras.forEach(function (palabra) {
                        if (palabra.length > 0) agregarTagProductoLibre(palabra);
                    });
                    tagsProductoLibreInput.val('');
                    ejecutarBusquedaProductoLibre();
                }
            }, 10);
        });
    }

    function agregarTagProductoLibre(texto) {
        texto = texto.trim();
        if (!texto) return;
        var duplicado = tagsProductoLibre.some(function (tag) { return tag.toLowerCase() === texto.toLowerCase(); });
        if (duplicado) { tagsProductoLibreInput.val(''); return; }
        tagsProductoLibre.push(texto);
        renderizarTagsProductoLibre();
        tagsProductoLibreInput.val('');
        tagsProductoLibreInput.focus();
    }

    function eliminarTagProductoLibre(index) {
        if (index >= 0 && index < tagsProductoLibre.length) {
            tagsProductoLibre.splice(index, 1);
            renderizarTagsProductoLibre();
        }
    }

    function limpiarTagsProductoLibre() {
        tagsProductoLibre = [];
        renderizarTagsProductoLibre();
    }

    function renderizarTagsProductoLibre() {
        tagsProductoLibreContainer.find('.tag-item').remove();
        tagsProductoLibre.forEach(function (tag, index) {
            var tagHtml = `
                <span class="tag-item" data-index="${index}">
                    <span class="tag-text">${escapeHtml(tag)}</span>
                    <span class="tag-remove" data-index="${index}" title="Eliminar"><i class="fas fa-times"></i></span>
                </span>
            `;
            tagsProductoLibreContainer.find('#busqueda_producto').before(tagHtml);
        });
        tagsProductoLibreContainer.find('.tag-remove').off('click').on('click', function (e) {
            e.stopPropagation();
            var index = parseInt($(this).data('index'));
            eliminarTagProductoLibre(index);
            ejecutarBusquedaProductoLibre();
        });
    }

    function ejecutarBusquedaProductoLibre() {
        if (!clienteActualId) {
            renderizarResultadosVacio('<i class="fas fa-arrow-up me-1"></i>Seleccione un cliente en la solapa "Datos del Remito"');
            return;
        }
        if (tagsProductoLibre.length === 0) {
            $('#resultados_busqueda').empty();
            return;
        }

        var q = tagsProductoLibre.join(' ');
        $.ajax({
            url: 'ventas_remitos_ajax.php',
            type: 'GET',
            data: { accion: 'buscar_productos_cliente', entidad_id: clienteActualId, q: q, empresa_idx: empresa_idx },
            dataType: 'json',
            success: function (res) {
                if (res && res.error === 'sin_lista_precios') {
                    renderizarResultadosVacio(
                        `<span class="text-danger"><i class="fas fa-triangle-exclamation me-1"></i>
                        Este cliente no tiene una lista de precios vigente asignada. No se pueden
                        buscar ni agregar productos sin pedido hasta configurarla.</span>`
                    );
                    return;
                }
                renderizarResultadosBusqueda((res && res.productos) ? res.productos : []);
            },
            error: function () {
                renderizarResultadosVacio(
                    '<span class="text-danger"><i class="fas fa-triangle-exclamation me-1"></i>Error del servidor al buscar productos.</span>'
                );
            }
        });
    }

    function resetBusquedaProductoLibre() {
        tagsProductoLibre = [];
        renderizarTagsProductoLibre();
        tagsProductoLibreInput.val('');
        if (clienteActualId) {
            $('#resultados_busqueda').empty();
        } else {
            renderizarResultadosVacio('<i class="fas fa-arrow-up me-1"></i>Seleccione un cliente en la solapa "Datos del Remito"');
        }
    }

    $('#btnLimpiarTagsProductoLibre').on('click', function () {
        limpiarTagsProductoLibre();
        resetBusquedaProductoLibre();
        tagsProductoLibreInput.focus();
    });

    inicializarBuscadorTagsProductoLibre();

    $(document).on('click', '.btn-agregar-libre', function () {
        var btn = $(this);
        var fila = btn.closest('tr');
        var cantidad = parseFloat(fila.find('.input-cantidad-libre').val());

        if (!clienteActualId) {
            Swal.fire({ icon: 'warning', title: 'Seleccione cliente', text: 'Debe seleccionar un cliente primero', confirmButtonText: 'Entendido' });
            return;
        }
        if (!cantidad || cantidad <= 0) {
            Swal.fire({ icon: 'warning', title: 'Cantidad inválida', text: 'La cantidad debe ser mayor a 0', confirmButtonText: 'Entendido' });
            return;
        }

        var productoId = parseInt(btn.data('id'));
        var existente = detalles.find(function (d) {
            return !d.venta_pedido_detalle_id && d.producto_id == productoId;
        });

        var mensajeToast = 'Línea agregada';

        if (existente) {
            // Ya hay una línea "sin pedido" para este producto: se suma la cantidad
            // en lugar de crear una línea duplicada (precio, descuento e IVA no cambian).
            existente.cantidad = (parseFloat(existente.cantidad) || 0) + cantidad;
            existente.importe_linea = existente.cantidad * existente.precio_unitario_neto;
            existente.iva_importe = existente.importe_linea * (existente.iva_porcentaje / 100);
            mensajeToast = 'Cantidad sumada a la línea existente';
        } else {
            var precioBruto = parseFloat(btn.data('precio-bruto')) || 0;
            var descuentoPct = parseFloat(btn.data('descuento-pct')) || 0;
            var descuentoGeneral = precioBruto * (descuentoPct / 100);
            var precioNeto = precioBruto - descuentoGeneral;
            var ivaPct = parseFloat(btn.data('iva')) || 0;
            var importeNeto = cantidad * precioNeto;
            var ivaImporte = importeNeto * (ivaPct / 100);

            detalles.push({
                detalle_idx: 'temp_' + new Date().getTime() + '_' + Math.random(),
                venta_remito_detalle_id: 0,
                venta_pedido_detalle_id: null,
                producto_id: productoId,
                producto_codigo: btn.data('codigo'),
                producto_nombre: btn.data('nombre'),
                cantidad: cantidad,
                precio_unitario_bruto: precioBruto,
                descuento_general_pct: descuentoPct,
                descuento_general: descuentoGeneral,
                precio_unitario_neto: precioNeto,
                importe_linea: importeNeto,
                iva_alicuota_id: btn.data('iva-id') || null,
                iva_porcentaje: ivaPct,
                iva_importe: ivaImporte,
                origen_pedido_nro: null,
                origen_pedido_tipo: null
            });
        }

        renderizarDetalles();
        actualizarTotales();

        // Se deja la lista de resultados como está (estilo carrito: se puede seguir
        // agregando el mismo u otros productos), solo se reinicia la cantidad de la fila.
        fila.find('.input-cantidad-libre').val('1.00');

        Swal.fire({ icon: 'success', title: mensajeToast, showConfirmButton: false, timer: 1000, toast: true, position: 'top-end' });
    });

    // ========== TABLA DE DETALLE DEL REMITO ==========
    function renderizarDetalles() {
        var cont = $('#contenedor-detalles');

        if (detalles.length === 0) {
            cont.html(`
                <div class="detalles-vacio">
                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                    <p class="mb-0 fw-bold">No hay productos agregados al remito</p>
                    <small class="text-muted">Agregá líneas desde los pedidos pendientes o cargá un producto sin pedido</small>
                </div>`);
            return;
        }

        var html = `<table class="table table-sm table-bordered table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Origen</th>
                    <th>Código</th>
                    <th>Producto</th>
                    <th class="text-center">Cantidad</th>
                    <th class="text-end">P. Unit. Bruto</th>
                    <th class="text-center">Desc. Gral.</th>
                    <th class="text-end">P. Unit. Neto</th>
                    <th class="text-end">Importe Neto</th>
                    <th class="text-center">IVA</th>
                    <th class="text-end">Importe IVA</th>
                    <th class="text-end">Total</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>`;

        detalles.forEach(function (detalle) {
            var origen = detalle.venta_pedido_detalle_id
                ? `<span class="badge bg-warning text-dark">${detalle.origen_pedido_tipo || 'Pedido'} ${detalle.origen_pedido_nro > 0 ? '#' + detalle.origen_pedido_nro : ''}</span>`
                : `<span class="badge bg-secondary">Sin pedido</span>`;
            var iva = (detalle.iva_porcentaje || detalle.iva_porcentaje === 0) ? `${parseFloat(detalle.iva_porcentaje).toFixed(2)}%` : '-';
            var descuentoPct = parseFloat(detalle.descuento_general_pct || 0);
            var importeIva = parseFloat(detalle.iva_importe || 0);
            var totalLinea = parseFloat(detalle.importe_linea || 0) + importeIva;

            html += `<tr data-idx="${detalle.detalle_idx}">
                <td>${origen}</td>
                <td>${detalle.producto_codigo || ''}</td>
                <td>${detalle.producto_nombre || ''}</td>
                <td class="text-center">${formatMoneda(detalle.cantidad)}</td>
                <td class="text-end">$${formatMoneda(detalle.precio_unitario_bruto)}</td>
                <td class="text-center">${descuentoPct.toFixed(2)}%</td>
                <td class="text-end">$${formatMoneda(detalle.precio_unitario_neto)}</td>
                <td class="text-end">$${formatMoneda(detalle.importe_linea)}</td>
                <td class="text-center">${iva}</td>
                <td class="text-end">$${formatMoneda(importeIva)}</td>
                <td class="text-end fw-bold text-success">$${formatMoneda(totalLinea)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger btn-eliminar-detalle" data-idx="${detalle.detalle_idx}" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
        });

        html += '</tbody></table>';
        cont.html(html);
    }

    $(document).on('click', '.btn-eliminar-detalle', function () {
        var idx = $(this).data('idx');
        Swal.fire({
            title: '¿Eliminar producto?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                detalles = detalles.filter(function (item) { return item.detalle_idx != idx; });
                renderizarDetalles();
                actualizarTotales();
                renderizarPendientes();
                Swal.fire({ icon: 'success', title: 'Eliminado', showConfirmButton: false, timer: 1200, toast: true, position: 'top-end' });
            }
        });
    });

    function formatMoneda(valor) {
        return (parseFloat(valor) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatFecha(fecha) {
        if (!fecha) return '';
        var parts = fecha.split('-');
        return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : fecha;
    }

    function actualizarTotales() {
        var subtotalBruto = 0, totalDescuento = 0, totalNeto = 0, totalIva = 0;

        detalles.forEach(function (d) {
            var cantidad = parseFloat(d.cantidad) || 0;
            var bruto = parseFloat(d.precio_unitario_bruto) || 0;
            var descuentoUnit = parseFloat(d.descuento_general) || 0;
            subtotalBruto += cantidad * bruto;
            totalDescuento += cantidad * descuentoUnit;
            totalNeto += parseFloat(d.importe_linea) || 0;
            totalIva += parseFloat(d.iva_importe) || 0;
        });

        var totalGeneral = totalNeto + totalIva;

        $('#subtotal_bruto_resumen').text(formatMoneda(subtotalBruto));
        $('#descuento_resumen').text(formatMoneda(totalDescuento));
        $('#neto_resumen').text(formatMoneda(totalNeto));
        $('#iva_resumen').text(formatMoneda(totalIva));
        $('#total_display_resumen').text('$' + formatMoneda(totalGeneral));
        $('#contador-productos').text(detalles.length);
    }

    $('#btnToggleFullscreen').click(function () {
        var modalDialog = $('#modalVentaRemito .modal-dialog');
        var btnIcon = $(this).find('i');
        if (modalDialog.hasClass('modal-fullscreen')) {
            modalDialog.removeClass('modal-fullscreen');
            btnIcon.removeClass('fa-compress').addClass('fa-expand');
        } else {
            modalDialog.addClass('modal-fullscreen');
            btnIcon.removeClass('fa-expand').addClass('fa-compress');
        }
    });

    // ========== COMBOS DEL FORMULARIO ==========
    function cargarCombosFormulario() {
        $.get('ventas_remitos_ajax.php', { accion: 'obtener_sucursales_empresa', empresa_idx: empresa_idx }, function (data) {
            var options = '<option value="">Seleccionar sucursal</option>';
            if (data && data.length > 0) {
                data.forEach(function (item) {
                    options += `<option value="${item.sucursal_id}">${item.sucursal_nombre}</option>`;
                });
            }
            $('#sucursal_id').html(options);
        }, 'json');

        $.get('ventas_remitos_ajax.php', { accion: 'obtener_depositos', empresa_idx: empresa_idx }, function (data) {
            var options = '<option value="">Seleccionar depósito</option>';
            if (data && data.length > 0) {
                data.forEach(function (item) {
                    options += `<option value="${item.deposito_id}">${item.deposito_nombre}</option>`;
                });
            }
            $('#deposito_id').html(options);
        }, 'json');

        $.get('ventas_remitos_ajax.php', { accion: 'obtener_comprobantes_tipos' }, function (data) {
            if (data && data.length > 0) {
                if (data.length === 1) {
                    $('#comprobante_tipo_id').html(`<option value="${data[0].comprobante_tipo_id}" selected>${data[0].comprobante_tipo}</option>`);
                } else {
                    var options = '<option value="">Seleccionar</option>';
                    data.forEach(function (item) {
                        options += `<option value="${item.comprobante_tipo_id}">${item.comprobante_tipo}</option>`;
                    });
                    $('#comprobante_tipo_id').html(options);
                }
            }
        }, 'json');
    }

    function resetModal() {
        $('#formVentaRemito')[0].reset();
        $('#venta_remito_id').val('');
        $('#entidad_id').val('');
        $('#entidad_sucursal_id').val('');
        $('#entidad_combo').prop('disabled', false).removeAttr('title');
        $('#punto_venta_id').html('<option value="">Primero seleccione sucursal</option>').prop('disabled', true);
        $('#formVentaRemito').removeClass('was-validated');

        detalles = [];
        clienteActualId = null;
        clienteSucursalActualId = null;
        pedidosPendientesCliente = [];
        renderizarDetalles();
        actualizarTotales();
        renderizarPendientes();

        $('#entidad_combo').html('<option value="">Seleccionar cliente o sucursal</option>');
        resetBusquedaProductoLibre();

        $('.btn-secondary[data-bs-dismiss="modal"]').show();
        $('.btn-eliminar-detalle, .btn-agregar-pendiente, .btn-agregar-libre, #btnLimpiarTagsProductoLibre').show().prop('disabled', false);
        $('#busqueda_producto').prop('disabled', false);
    }

    $(document).on('click', '#btnNuevo', function () {
        resetModal();
        $('#modalLabel').text('Nuevo Remito de Venta');
        cargarCombosFormulario();
        cargarClientesYSucursales();

        $('#f_emision').val(new Date().toISOString().split('T')[0]);

        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalVentaRemito'), { backdrop: 'static', keyboard: false });
        modal.show();
    });

    function poblarDetallesDesdeRespuesta(res) {
        detalles = (res.detalles || []).map(function (d, index) {
            return {
                detalle_idx: index,
                venta_remito_detalle_id: d.venta_remito_detalle_id,
                venta_pedido_detalle_id: d.venta_pedido_detalle_id || null,
                producto_id: d.producto_id,
                producto_codigo: d.producto_codigo,
                producto_nombre: d.producto_nombre,
                cantidad: parseFloat(d.cantidad),
                precio_unitario_bruto: parseFloat(d.precio_unitario_bruto || 0),
                descuento_general_pct: parseFloat(d.descuento_general_pct || 0),
                descuento_general: parseFloat(d.descuento_general || 0),
                precio_unitario_neto: parseFloat(d.precio_unitario_neto || 0),
                importe_linea: parseFloat(d.importe_linea || 0),
                iva_alicuota_id: d.iva_alicuota_id,
                iva_porcentaje: parseFloat(d.iva_porcentaje || 0),
                iva_importe: parseFloat(d.iva_importe || 0),
                origen_pedido_nro: d.pedido_comprobante_nro,
                origen_pedido_tipo: null
            };
        });
        renderizarDetalles();
        actualizarTotales();
    }

    function cargarRemitoComun(remitoId, soloVisualizar) {
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

                resetModal();
                cargarCombosFormulario();
                cargarClientesYSucursales();

                $('#venta_remito_id').val(res.venta_remito_id);
                $('#comprobante_nro').val(res.comprobante_nro);
                $('#f_emision').val(res.f_emision);
                $('#observaciones').val(res.observaciones);
                $('#modalLabel').text(soloVisualizar ? 'Visualizar Remito de Venta' : 'Editar Remito de Venta');

                poblarDetallesDesdeRespuesta(res);

                setTimeout(function () {
                    $('#comprobante_tipo_id').val(res.comprobante_tipo_id);
                    $('#deposito_id').val(res.deposito_id);

                    if (res.sucursal_id) {
                        $('#sucursal_id').val(res.sucursal_id);
                        cargarPuntosVenta(res.sucursal_id, function () {
                            if (res.comprobante_pv) $('#punto_venta_id').val(res.comprobante_pv);
                        });
                    }

                    if (res.entidad_id) {
                        clienteActualId = parseInt(res.entidad_id);
                        if (res.entidad_sucursal_id && res.entidad_sucursal_id > 0) {
                            $('#entidad_combo').val('S-' + res.entidad_sucursal_id);
                            clienteSucursalActualId = parseInt(res.entidad_sucursal_id);
                        } else {
                            $('#entidad_combo').val('P-' + res.entidad_id);
                            clienteSucursalActualId = null;
                        }
                        $('#entidad_combo').prop('disabled', true).attr('title', 'El cliente no se puede modificar una vez guardado el remito');

                        if (!soloVisualizar) {
                            cargarPedidosPendientes(clienteActualId);
                        }
                    }

                    if (soloVisualizar) {
                        $('#formVentaRemito :input').prop('disabled', true);
                        $('.btn-eliminar-detalle, .btn-agregar-pendiente, .btn-agregar-libre, #btnLimpiarTagsProductoLibre').prop('disabled', true).hide();
                        $('#busqueda_producto').prop('disabled', true);
                        $('#btnGuardar, .btn-secondary[data-bs-dismiss="modal"]').hide();
                        $('#contenedor-pendientes').html('<div class="text-muted small p-2">No aplica en modo visualización.</div>');
                        $('#resultados_busqueda').html('<div class="text-muted small p-2">No aplica en modo visualización.</div>');
                    } else {
                        $('#formVentaRemito :input').prop('disabled', false);
                        $('#entidad_combo').prop('disabled', true); // sigue fijo aunque se pueda editar el resto
                        $('#btnGuardar, .btn-secondary[data-bs-dismiss="modal"]').show();
                    }
                }, 400);

                var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalVentaRemito'), { backdrop: 'static', keyboard: false });
                modal.show();

                $('#modalVentaRemito').off('hidden.bs.modal').on('hidden.bs.modal', function () {
                    $('#formVentaRemito :input').prop('disabled', false);
                    $('.btn-eliminar-detalle, .btn-agregar-pendiente, .btn-agregar-libre, #btnLimpiarTagsProductoLibre').prop('disabled', false).show();
                    $('#busqueda_producto').prop('disabled', false);
                    $('#btnGuardar, .btn-secondary[data-bs-dismiss="modal"]').show();
                });
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error('Error en obtener remito:', textStatus, errorThrown, jqXHR.responseText);
                Swal.fire({ icon: 'error', title: 'Error al obtener el remito', text: 'Revisá la consola del navegador (F12) para ver el detalle.', confirmButtonText: 'Entendido' });
            }
        });
    }

    function cargarRemitoParaEditar(id) { cargarRemitoComun(id, false); }
    function cargarRemitoParaVisualizar(id) { cargarRemitoComun(id, true); }

    $(document).on('click', '.btn-accion', function () {
        var remitoId = $(this).data('id');
        var accionJs = $(this).data('accion');
        var confirmable = $(this).data('confirmable');
        var comprobanteInfo = $(this).data('comprobante') || 'Remito #' + remitoId;
        var clienteInfo = $(this).data('cliente') || '';

        if (accionJs === 'editar') {
            cargarRemitoParaEditar(remitoId);
        } else if (accionJs === 'visualizar') {
            cargarRemitoParaVisualizar(remitoId);
        } else if (confirmable == 1) {
            Swal.fire({
                title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                html: `¿Está seguro de <strong>${accionJs}</strong> el remito<br>
                    <strong>${comprobanteInfo}</strong>?<br>
                    <small class="text-muted">Cliente: ${clienteInfo}</small>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: `Sí, ${accionJs}`,
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) ejecutarAccion(remitoId, accionJs, comprobanteInfo);
            });
        } else {
            ejecutarAccion(remitoId, accionJs, comprobanteInfo);
        }
    });

    function ejecutarAccion(remitoId, accionJs, comprobanteInfo) {
        var savedState = { page: tabla.page(), order: tabla.order(), search: tabla.search() };

        $.post('ventas_remitos_ajax.php', {
            accion: 'ejecutar_accion',
            venta_remito_id: remitoId,
            accion_js: accionJs,
            empresa_idx: empresa_idx,
            pagina_idx: pagina_idx
        }, function (res) {
            if (res.success) {
                tabla.ajax.reload(function () {
                    if (savedState.page !== undefined) tabla.page(savedState.page).draw('page');
                    if (savedState.search) tabla.search(savedState.search).draw();

                    Swal.fire({
                        icon: 'success',
                        title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                        text: res.message || `Remito "${comprobanteInfo}" actualizado correctamente`,
                        showConfirmButton: false,
                        timer: 2000,
                        toast: true,
                        position: 'top-end'
                    });
                }, false);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.error || `Error al ${accionJs} el remito`, confirmButtonText: 'Entendido' });
            }
        }, 'json').fail(function (xhr) {
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor', confirmButtonText: 'Entendido' });
            console.error('Error en ejecutarAccion:', xhr.responseText);
        });
    }

    // ========== GUARDAR ==========
    $('#btnGuardar').click(function () {
        var form = document.getElementById('formVentaRemito');

        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        if (!clienteActualId) {
            Swal.fire({ icon: 'warning', title: 'Cliente requerido', text: 'Debe seleccionar un cliente', confirmButtonText: 'Entendido' });
            return false;
        }
        if (detalles.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Productos requeridos', text: 'Debe agregar al menos un producto al remito', confirmButtonText: 'Entendido' });
            return false;
        }

        var id = $('#venta_remito_id').val();
        var accionBackend = id ? 'editar' : 'agregar';

        var btnGuardar = $(this);
        var originalText = btnGuardar.html();
        btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        var detallesEnviar = detalles.map(function (d) {
            return {
                producto_id: d.producto_id,
                venta_pedido_detalle_id: d.venta_pedido_detalle_id,
                cantidad: d.cantidad,
                precio_unitario_bruto: d.precio_unitario_bruto,
                descuento_general_pct: d.descuento_general_pct,
                iva_alicuota_id: d.iva_alicuota_id,
                iva_porcentaje: d.iva_porcentaje
            };
        });

        var formData = new FormData();
        formData.append('accion', accionBackend);
        formData.append('empresa_idx', empresa_idx);
        formData.append('pagina_idx', pagina_idx);
        formData.append('venta_remito_id', id || '');
        formData.append('sucursal_id', $('#sucursal_id').val() || '');
        formData.append('deposito_id', $('#deposito_id').val() || '');
        formData.append('punto_venta_id', $('#punto_venta_id').val() || '');
        formData.append('comprobante_tipo_id', $('#comprobante_tipo_id').val() || '');
        formData.append('entidad_id', clienteActualId);
        formData.append('entidad_sucursal_id', clienteSucursalActualId !== null ? clienteSucursalActualId : '');
        formData.append('f_emision', $('#f_emision').val() || '');
        formData.append('observaciones', $('#observaciones').val() || '');
        formData.append('detalles', JSON.stringify(detallesEnviar));

        var savedState = { page: tabla ? tabla.page() : 0, order: tabla ? tabla.order() : [[4, 'desc']], search: tabla ? tabla.search() : '' };

        $.ajax({
            url: 'ventas_remitos_ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                btnGuardar.prop('disabled', false).html(originalText);

                if (res.resultado) {
                    if (tabla) {
                        tabla.ajax.reload(function () {
                            if (savedState.page !== undefined) tabla.page(savedState.page).draw('page');
                            if (savedState.search) tabla.search(savedState.search).draw();
                        }, false);
                    }

                    Swal.fire({
                        icon: 'success',
                        title: '¡Guardado!',
                        text: 'Remito de venta guardado correctamente',
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });

                    var modalEl = document.getElementById('modalVentaRemito');
                    var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                    modal.hide();
                    $('body').removeClass('modal-open');
                    $('.modal-backdrop').remove();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.error || 'Error al guardar los datos', confirmButtonText: 'Entendido' });
                }
            },
            error: function (xhr, status, error) {
                btnGuardar.prop('disabled', false).html(originalText);
                console.error('Error AJAX:', error, xhr.responseText);
                Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'Error al comunicarse con el servidor', confirmButtonText: 'Entendido' });
            }
        });
    });

    inicializarDataTable();
    cargarBotonAgregar();

    $('[title]').tooltip({ trigger: 'hover', placement: 'top' });
});