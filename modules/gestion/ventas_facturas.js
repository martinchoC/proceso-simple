$(function () {
    $('#factura_total').closest('strong').remove();
    if (!$('#factura_totales').length) {
        $('#contenedorDetalleFactura').after('<div id="factura_totales" class="border-top mt-3 pt-2"></div>');
    }
    $('<style>.factura-remitos-scroll{max-height:420px;overflow-y:auto;font-size:.78rem}.factura-remitos-scroll th,.factura-remitos-scroll td{padding:.25rem .4rem;vertical-align:middle}</style>').appendTo('head');
    if (!$('#factura_entidad_sucursal_id').length) {
        $('#formFacturaCliente').prepend('<input type="hidden" id="factura_entidad_sucursal_id">');
    }
    var detalles = [];
    var productosEncontrados = [];
    // % de descuento general del cliente activo (gestion__entidades_condiciones_clientes),
    // usado como default al agregar un producto manual.
    var descuentoGeneralCliente = 0;
    // Distinto de null mientras el modal está editando una factura existente (en vez de dar de alta una nueva).
    var facturaEditId = null;
    // venta_remito_id a preseleccionar (cantidad = pendiente) apenas se rendericen sus líneas —
    // usado por el deep-link ?remito_id= que manda ventas_remitos.php. Se consume una sola vez.
    var remitoParaAutoFacturar = null;
    var tabla = $('#tablaFacturasCliente').DataTable({
        ajax: { url: 'ventas_facturas_ajax.php', data: { accion: 'listar', empresa_idx: EMPRESA_ID, pagina_idx: PAGINA_ID }, dataSrc: '' },
        columns: [
            { data: 'comprobante_nro', className: 'text-center' },
            { data: null, render: function (d) { return (d.entidad_nombre || '') + (d.entidad_fantasia ? '<small class="d-block text-muted">' + d.entidad_fantasia + '</small>' : ''); } },
            { data: 'comprobante_tipo' }, { data: 'f_emision' }, { data: 'condicion_pago' },
            { data: 'importe_total', className: 'text-end', render: function (d) { return '$' + Number(d || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 }); } },
            { data: 'estado_registro' },
            {
                data: 'botones', orderable: false, searchable: false, className: 'text-center',
                render: function (data, type, row) {
                    if (type !== 'display') return '';
                    if (!data || !data.length) return '<span class="text-muted small">Sin acciones</span>';
                    var html = '';
                    data.forEach(function (b) {
                        var clase = 'btn-sm me-1 ';
                        if (b.bg_clase && b.text_clase) clase += b.bg_clase + ' ' + b.text_clase;
                        else if (b.color_clase) clase += b.color_clase;
                        else clase += 'btn-outline-primary';
                        var icono = b.icono_clase ? '<i class="' + b.icono_clase + '"></i>' : b.nombre_funcion;
                        var comprobanteInfo = (row.comprobante_tipo || 'Factura') + ' -' + (row.comprobante_nro || row.venta_factura_id);
                        html += '<button type="button" class="btn ' + clase + ' btn-accion-factura" title="' + (b.descripcion || b.nombre_funcion) + '" data-id="' + row.venta_factura_id + '" data-accion="' + b.accion_js + '" data-confirmable="' + (b.es_confirmable || 0) + '" data-comprobante="' + comprobanteInfo + '">' + icono + '</button>';
                    });
                    return '<div class="btn-group" role="group">' + html + '</div>';
                }
            }
        ], order: [[3, 'desc']], language: { url: '//cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json' }
    });

    function moneda(v) { return Number(v || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function cargarBotonAgregar() {
        $.getJSON('ventas_facturas_ajax.php', { accion: 'obtener_boton_agregar', pagina_idx: PAGINA_ID }, function (b) {
            if (!b) return;
            $('#btnNuevaFactura').html('<i class="' + (b.icono_clase || 'fas fa-plus') + ' me-1"></i>' + (b.nombre_funcion || 'Nueva Factura'));
            var clase = (b.bg_clase && b.text_clase) ? (b.bg_clase + ' ' + b.text_clase) : (b.color_clase || 'btn-primary');
            $('#btnNuevaFactura').attr('class', 'btn btn-sm ' + clase);
        });
    }

    function cargarClientes(cb) {
        $.getJSON('ventas_facturas_ajax.php', { accion: 'clientes', empresa_idx: EMPRESA_ID }, function (rows) {
            var html = '<option value="">Seleccionar cliente</option>';
            (rows || []).forEach(function (r) {
                var texto = r.entidad_nombre + (r.entidad_fantasia ? ' - ' + r.entidad_fantasia : '') + (r.sucursal_nombre ? ' / ' + r.sucursal_nombre : ' / Sin Sucursal');
                html += '<option value="' + r.entidad_id + '" data-sucursal-id="' + (r.entidad_sucursal_id || '') + '">' + texto + '</option>';
            });
            $('#factura_entidad_id').html(html);
            if (typeof cb === 'function') cb();
        });
    }
    function cargarPuntosVenta(cb) {
        $.getJSON('ventas_facturas_ajax.php', { accion: 'puntos_venta', empresa_idx: EMPRESA_ID }, function (rows) {
            var html = '<option value="">Seleccionar punto de venta</option>';
            (rows || []).forEach(function (r) {
                html += '<option value="' + r.punto_venta_id + '" data-sucursal-id="' + r.sucursal_id + '" data-sucursal="' + (r.sucursal_nombre || '') + '">' + r.nombre + (r.codigo_fiscal ? ' (' + r.codigo_fiscal + ')' : '') + '</option>';
            });
            $('#factura_punto_venta_id').html(html);
            if (typeof cb === 'function') cb();
        });
    }
    function cargarTiposPorPuntoVenta() {
        var pv = $('#factura_punto_venta_id').val();
        var option = $('#factura_punto_venta_id option:selected');
        $('#factura_sucursal_id').val(option.data('sucursal-id') || '');
        if (!pv) {
            $('#factura_comprobante_tipo_id').html('<option value="">Seleccionar PV</option>');
            return;
        }
        $.getJSON('ventas_facturas_ajax.php', { accion: 'tipos_por_punto_venta', empresa_idx: EMPRESA_ID, punto_venta_id: pv }, function (rows) {
            var html = '<option value="">Seleccionar tipo</option>';
            (rows || []).forEach(function (r) { html += '<option value="' + r.comprobante_tipo_id + '" data-fiscal="' + (r.comprobante_fiscal_id || 0) + '">' + r.comprobante_tipo + (r.letra ? ' (' + r.letra + ')' : '') + '</option>'; });
            $('#factura_comprobante_tipo_id').html(html);
        });
    }
    // Variante usada al reabrir una factura existente (editar/visualizar): además de poblar
    // el combo de tipos, deja seleccionado el tipo que ya tenía la factura.
    function cargarPuntoVentaYTipoParaEdicion(f) {
        $('#factura_punto_venta_id').val(f.punto_venta_id);
        var option = $('#factura_punto_venta_id option:selected');
        $('#factura_sucursal_id').val(option.data('sucursal-id') || '');
        if (!f.punto_venta_id) return;
        $.getJSON('ventas_facturas_ajax.php', { accion: 'tipos_por_punto_venta', empresa_idx: EMPRESA_ID, punto_venta_id: f.punto_venta_id }, function (rows) {
            var html = '<option value="">Seleccionar tipo</option>';
            (rows || []).forEach(function (r) {
                var sel = (String(r.comprobante_tipo_id) === String(f.comprobante_tipo_id)) ? ' selected' : '';
                html += '<option value="' + r.comprobante_tipo_id + '" data-fiscal="' + (r.comprobante_fiscal_id || 0) + '"' + sel + '>' + r.comprobante_tipo + (r.letra ? ' (' + r.letra + ')' : '') + '</option>';
            });
            $('#factura_comprobante_tipo_id').html(html);
            renderDetalle();
        });
    }
    // El vencimiento nunca puede ser anterior a la emisión: el min del input lo bloquea en el
    // navegador, y si la fecha cargada queda desactualizada (o todavía no hay ninguna) se
    // reacomoda a la fecha de emisión — mismo día como piso, después se puede correr para adelante.
    function sincronizarVencimiento() {
        var fEmision = $('#factura_f_emision').val();
        $('#factura_f_vto').attr('min', fEmision || '');
        var fVto = $('#factura_f_vto').val();
        if (fEmision && (!fVto || fVto < fEmision)) $('#factura_f_vto').val(fEmision);
    }
    // Pinta "Lista De Precios: ... / Descuento: ...%" en el cuadro de condiciones del cliente.
    // Único punto que escribe en #condicionClienteInfo — no lo pisen otros flujos (edición,
    // visualización) con mensajes de otro tipo.
    function mostrarCondicionCliente(c) {
        descuentoGeneralCliente = parseFloat(c && c.cliente_descuento_general) || 0;
        $('#factura_producto_descuento_pct').val(descuentoGeneralCliente.toFixed(2));
        if (!c) { $('#condicionClienteInfo').text('El cliente no tiene condiciones comerciales vigentes.'); return; }
        $('#condicionClienteInfo').html('<strong>Lista De Precios:</strong> ' + (c.lista_precio_nombre || '-') + ' &nbsp; <strong>Descuento:</strong> ' + descuentoGeneralCliente + '%');
    }
    function cargarCliente() {
        var id = $('#factura_entidad_id').val();
        var sucursalClienteId = $('#factura_entidad_id option:selected').data('sucursal-id') || 0;
        detalles = []; renderDetalle();
        sincronizarVencimiento();
        if (!id) { descuentoGeneralCliente = 0; $('#condicionClienteInfo').text('Seleccione un cliente para cargar sus condiciones comerciales.'); return; }
        $.getJSON('ventas_facturas_ajax.php', { accion: 'condicion_cliente', entidad_id: id, empresa_idx: EMPRESA_ID }, function (c) {
            mostrarCondicionCliente(c);
            if (!c) return;
            $('#factura_condicion_pago_id').html('<option value="' + c.condicion_pago_id + '">' + (c.condicion_pago || 'Sin condición') + '</option>');
            var dias = parseInt(c.dias_primer_vencimiento || 0, 10), fecha = $('#factura_f_emision').val();
            if (fecha) { var f = new Date(fecha + 'T00:00:00'); f.setDate(f.getDate() + dias); $('#factura_f_vto').val(f.toISOString().slice(0, 10)); }
        });
        $('#factura_entidad_sucursal_id').val(sucursalClienteId);
        $.getJSON('ventas_facturas_ajax.php', { accion: 'remitos_pendientes', empresa_idx: EMPRESA_ID, entidad_id: id, entidad_sucursal_id: sucursalClienteId, excluir_factura_id: facturaEditId || 0 }, renderRemitos);
    }
    // Siempre arma la tabla completa (con <tbody>), aunque no haya remitos pendientes: si se
    // reemplaza el contenedor por un simple <div> de texto en ese caso, cualquier agregado
    // posterior de un producto manual (acá o al reabrir una factura para editar) apunta a un
    // tbody que no existe y el .append() no inserta nada — la fila (y su cantidad) desaparece
    // en silencio. La fila "sin pendientes" se saca sola apenas entra la primera línea manual.
    function renderRemitos(rows) {
        var autoRemitoId = remitoParaAutoFacturar;
        var html = '<div class="table-responsive factura-remitos-scroll"><table class="table table-sm table-bordered mb-0"><thead><tr><th>Nro. Remito</th><th>Código</th><th>Detalle</th><th class="text-end">Cantidad Pendiente</th><th class="text-end">Cantidad</th><th class="text-end">Precio De Lista</th><th class="text-end">Descuento</th><th class="text-end">Precio Neto</th><th class="text-end">Total Neto</th></tr></thead><tbody>';
        (rows || []).forEach(function (r) {
            // Deep-link desde ventas_remitos.php: precarga (cantidad = pendiente) solo las
            // líneas del remito puntual que se vino a facturar, dejando el resto del cliente
            // visible pero sin marcar, por si también quiere sumarlas a la misma factura.
            if (autoRemitoId && Number(r.venta_remito_id) === Number(autoRemitoId)) {
                r = $.extend({}, r, { cantidad_propia: r.cantidad_pendiente_facturar });
            }
            html += filaItemFactura(r, false);
        });
        if (!rows || !rows.length) {
            html += '<tr class="fila-sin-pendientes"><td colspan="9" class="text-muted small text-center py-2">No hay remitos pendientes de facturar.</td></tr>';
        }
        $('#contenedorRemitosFactura').html(html + '</tbody></table></div><div id="paginacionRemitosFactura" class="d-flex justify-content-end mt-2"></div>');
        inicializarPaginacionRemitos();
        if (autoRemitoId) {
            $('#contenedorRemitosFactura .factura-cantidad').each(function () { if (parseFloat(this.value) > 0) $(this).trigger('change'); });
            remitoParaAutoFacturar = null;
        }
    }
    // manual: true para las líneas agregadas a mano (fuera de un remito). No hay checkbox: la
    // línea entra a la factura simplemente con que la "Cantidad" sea mayor a 0 — item.cantidad_propia,
    // cuando viene > 0, precarga esa cantidad (lo que la factura que se está editando ya tenía
    // asignado en esa línea, sea de remito o manual).
    function filaItemFactura(item, manual) {
        var key = manual ? 'm' + item.key : 'r' + item.venta_remito_detalle_id;
        var cantidadPropia = parseFloat(item.cantidad_propia) || 0;
        // Un producto manual no viene de un remito, así que no hay "pendiente" que lo limite —
        // usar item.cantidad (la cantidad con la que se agregó la primera vez) como tope dejaba
        // el campo trabado en ese valor para siempre, sin poder subirlo después.
        var cantidadMax = manual ? 999999 : (parseFloat(item.cantidad_pendiente_facturar || item.cantidad) || 0);
        var cantidadPendiente = parseFloat(item.cantidad_pendiente_facturar) || 0;
        var precio = parseFloat(item.precio_unitario_bruto || item.precio_unitario) || 0;
        // Mismo cálculo que el servidor (bruto * pct / 100): los productos manuales solo traen
        // descuento_general_pct (no un descuento_general ya calculado, que solo viene de remitos),
        // así que ese monto plano no sirve para mostrar la fila — recalculamos siempre desde el %.
        var descuentoPct = parseFloat(item.descuento_general_pct) || 0;
        var descuento = precio * descuentoPct / 100;
        var neto = parseFloat(item.precio_unitario_neto) || Math.max(0, precio - descuento);
        var valorInicial = cantidadPropia > 0 ? cantidadPropia.toFixed(2) : '0.00';
        return '<tr><td>' + (manual ? '-' : (item.comprobante_nro || '-')) + '</td><td>' + (item.producto_codigo || item.producto_id) + '</td><td>' + (item.producto_nombre || 'Producto manual') + (manual ? ' <small class="text-muted">(Manual)</small>' : '') + '</td><td class="text-end cantidad-pendiente">' + (manual ? '-' : cantidadPendiente.toFixed(2)) + '</td><td class="text-end"><div class="factura-cantidad-stepper"><button type="button" class="btn btn-sm btn-outline-secondary btn-cantidad-menos" tabindex="-1">&minus;</button><input type="number" class="form-control form-control-sm factura-cantidad no-spinner" data-key="' + key + '" data-remito-detalle-id="' + (manual ? '' : item.venta_remito_detalle_id) + '" data-producto-id="' + item.producto_id + '" data-pedido-detalle-id="' + (item.venta_pedido_detalle_id || '') + '" data-precio="' + precio + '" data-descuento-pct="' + descuentoPct + '" data-precio-neto="' + neto + '" data-iva-id="' + (item.iva_alicuota_id || 1) + '" data-iva="' + (item.iva_porcentaje || 0) + '" value="' + valorInicial + '" min="0" max="' + cantidadMax + '" step="1"><button type="button" class="btn btn-sm btn-outline-secondary btn-cantidad-mas" tabindex="-1">+</button></div></td><td class="text-end">$' + moneda(precio) + '</td><td class="text-end">$' + moneda(descuento) + '</td><td class="text-end">$' + moneda(neto) + '</td><td class="text-end fw-bold total-neto-renglon">$0.00</td></tr>';
    }
    // Única fuente de verdad de qué entra a la factura: la cantidad tipeada. Cantidad > 0 =
    // la línea se agrega/actualiza en `detalles`; 0 = se saca. Sin click de checkbox de por medio.
    $(document).on('change', '.factura-cantidad', function () {
        var el = $(this);
        var valor = Math.max(0, Math.min(parseFloat(this.max) || 0, parseFloat(this.value) || 0));
        this.value = valor.toFixed(2);
        var key = el.data('key');
        detalles = detalles.filter(function (d) { return d.key !== key; });
        if (valor > 0) {
            detalles.push({ key: key, producto_id: el.data('producto-id'), venta_remito_detalle_id: el.data('remito-detalle-id') || null, venta_pedido_detalle_id: el.data('pedido-detalle-id') || null, cantidad: valor, precio_unitario: parseFloat(el.data('precio')) || 0, descuento_general_pct: parseFloat(el.data('descuento-pct')) || 0, precio_unitario_neto: parseFloat(el.data('precio-neto')) || 0, iva_alicuota_id: parseInt(el.data('iva-id')) || 1, iva_porcentaje: parseFloat(el.data('iva')) || 0 });
        }
        actualizarTotalNetoFila(el.closest('tr'));
        actualizarVisibilidadRemitos();
        renderDetalle();
    });
    // Botones +/- del stepper: mismo patrón visual que ventas_pedidos, sin las flechas nativas
    // del input number (ocultas vía .no-spinner).
    $(document).on('click', '.btn-cantidad-mas, .btn-cantidad-menos', function () {
        var input = $(this).closest('.factura-cantidad-stepper').find('.factura-cantidad');
        if (input.prop('disabled')) return;
        var max = parseFloat(input.attr('max')) || 0;
        var valor = (parseFloat(input.val()) || 0) + ($(this).hasClass('btn-cantidad-mas') ? 1 : -1);
        input.val(Math.max(0, Math.min(max, valor)).toFixed(2)).trigger('change');
    });
    function actualizarTotalNetoFila(row) {
        var input = row.find('.factura-cantidad');
        var cantidad = parseFloat(input.val()) || 0;
        var neto = parseFloat(input.data('precio-neto')) || 0;
        row.find('.total-neto-renglon').text('$' + moneda(cantidad * neto));
    }
    // La paginación solo aplica a las filas "candidatas" (cantidad en 0, todavía sin elegir).
    // Las que ya están incluidas en la factura (cantidad > 0, vengan de un remito o cargadas a
    // mano) se muestran siempre, sin importar la página — si no, una línea con datos cargados
    // podía quedar oculta (display:none) en una página que el usuario no está mirando, y parecía
    // que "no traía" la cantidad cuando en realidad estaba ahí, tapada.
    var paginaRemitos = 1;
    function actualizarVisibilidadRemitos() {
        var porPagina = 10;
        var todas = $('#contenedorRemitosFactura tbody tr');
        todas.filter('.fila-sin-pendientes').show();
        var incluidas = todas.not('.fila-sin-pendientes').filter(function () {
            var input = $(this).find('.factura-cantidad');
            return input.length > 0 && (parseFloat(input.val()) || 0) > 0;
        });
        incluidas.show();
        var candidatas = todas.not('.fila-sin-pendientes').not(incluidas);
        var totalPaginas = Math.max(1, Math.ceil(candidatas.length / porPagina));
        if (paginaRemitos > totalPaginas) paginaRemitos = totalPaginas;
        candidatas.hide().slice((paginaRemitos - 1) * porPagina, paginaRemitos * porPagina).show();
        var html = '';
        for (var i = 1; i <= totalPaginas; i++) html += '<button type="button" class="btn btn-sm ' + (i === paginaRemitos ? 'btn-primary' : 'btn-outline-primary') + ' btn-pagina-remito" data-pagina="' + i + '">' + i + '</button> ';
        $('#paginacionRemitosFactura').html(html);
    }
    function inicializarPaginacionRemitos() {
        paginaRemitos = 1;
        $(document).off('click.facturaPag', '.btn-pagina-remito').on('click.facturaPag', '.btn-pagina-remito', function () { paginaRemitos = parseInt($(this).data('pagina'), 10); actualizarVisibilidadRemitos(); });
        actualizarVisibilidadRemitos();
    }
    $('#factura_f_emision').val(new Date().toISOString().slice(0, 10));
    $('#factura_entidad_id').on('change', cargarCliente);
    $('#factura_f_emision').on('change', cargarCliente);
    $('#factura_punto_venta_id').on('change', cargarTiposPorPuntoVenta);
    var indiceActivoProducto = -1;
    function resaltarProductoActivo() {
        $('#factura_productos_resultados .list-group-item').removeClass('active').each(function (i) {
            if (i === indiceActivoProducto) { $(this).addClass('active'); this.scrollIntoView({ block: 'nearest' }); }
        });
    }
    function seleccionarProductoEncontrado(p) {
        if (!p) return;
        $('#factura_producto_busqueda').val(p.producto_codigo + ' - ' + p.producto_nombre).data('producto', p);
        $('#factura_producto_precio').val((parseFloat(p.precio_unitario) || 0).toFixed(2));
        $('#factura_productos_resultados').empty();
        indiceActivoProducto = -1;
    }
    $('#factura_producto_busqueda').on('input', function () {
        indiceActivoProducto = -1;
        var q = this.value, id = $('#factura_entidad_id').val(); if (q.length < 2 || !id) return $('#factura_productos_resultados').empty();
        $.getJSON('ventas_facturas_ajax.php', { accion: 'buscar_productos', empresa_idx: EMPRESA_ID, entidad_id: id, q: q }, function (rows) { productosEncontrados = rows || []; $('#factura_productos_resultados').html(productosEncontrados.map(function (p, i) { return '<button type="button" class="list-group-item list-group-item-action" data-index="' + i + '">' + p.producto_codigo + ' - ' + p.producto_nombre + '</button>'; }).join('')); });
    });
    // Navegación por teclado del autocompletar: flechas para recorrer los resultados,
    // Enter para elegir el resaltado (o el primero si todavía no se tocó ninguna flecha),
    // Escape para cerrar la lista.
    $('#factura_producto_busqueda').on('keydown', function (e) {
        var items = $('#factura_productos_resultados .list-group-item');
        if (!items.length) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            indiceActivoProducto = Math.min(items.length - 1, indiceActivoProducto + 1);
            resaltarProductoActivo();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            indiceActivoProducto = Math.max(0, indiceActivoProducto - 1);
            resaltarProductoActivo();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            seleccionarProductoEncontrado(productosEncontrados[indiceActivoProducto >= 0 ? indiceActivoProducto : 0]);
        } else if (e.key === 'Escape') {
            $('#factura_productos_resultados').empty();
            indiceActivoProducto = -1;
        }
    });
    $(document).on('click', '#factura_productos_resultados .list-group-item', function () { seleccionarProductoEncontrado(productosEncontrados[$(this).data('index')]); });
    $('#btnAgregarProductoFactura').on('click', function () {
        var p = $('#factura_producto_busqueda').data('producto'), qty = parseFloat($('#factura_producto_cantidad').val()) || 0;
        if (!p || qty <= 0) return;
        var descuentoPct = parseFloat($('#factura_producto_descuento_pct').val()) || 0;
        var item = { key: Date.now(), producto_id: p.producto_id, producto_codigo: p.producto_codigo, producto_nombre: p.producto_nombre, cantidad: qty, cantidad_propia: qty, precio_unitario: parseFloat($('#factura_producto_precio').val()) || 0, descuento_general_pct: descuentoPct, iva_alicuota_id: p.iva_alicuota_id || 1, iva_porcentaje: p.iva_porcentaje || 0 };
        $('#contenedorRemitosFactura .fila-sin-pendientes').remove();
        $('#contenedorRemitosFactura tbody').append(filaItemFactura(item, true));
        inicializarPaginacionRemitos();
        $('#contenedorRemitosFactura tbody tr:last .factura-cantidad').trigger('change');
        $('#factura_producto_busqueda').val('').removeData('producto');
        $('#factura_producto_descuento_pct').val(descuentoGeneralCliente.toFixed(2));
        // Foco de vuelta al buscador para poder tipear el siguiente producto sin tocar el mouse.
        $('#factura_producto_busqueda').trigger('focus');
    });
    // Misma fórmula que vfCalcularDetallesFactura() en el servidor: descuento = bruto * pct / 100,
    // SIEMPRE recalculado sobre la cantidad elegida (nunca un monto de descuento fijo, que no
    // escala si se factura menos cantidad que la del remito y desincroniza este preview del total
    // que realmente queda guardado — y que se ve después en el datatable principal).
    function renderDetalle() {
        var bruto = 0, descuento = 0, neto = 0, iva = 0;
        detalles.forEach(function (d) { var b = d.cantidad * d.precio_unitario; var desc = b * (d.descuento_general_pct || 0) / 100; var n = b - desc; var fiscal = parseInt($('#factura_comprobante_tipo_id option:selected').data('fiscal') || 0, 10) > 0; bruto += b; descuento += desc; neto += n; iva += fiscal ? n * (d.iva_porcentaje || 0) / 100 : 0; });
        var total = neto + iva;
        $('#factura_total').text(moneda(total));
        $('#factura_totales').html(
            '<div class="factura-totales-box">' +
                '<div class="factura-totales-item"><span class="factura-totales-label">Bruto</span><span class="factura-totales-valor">$' + moneda(bruto) + '</span></div>' +
                '<div class="factura-totales-item"><span class="factura-totales-label">Descuento</span><span class="factura-totales-valor">$' + moneda(descuento) + '</span></div>' +
                '<div class="factura-totales-item"><span class="factura-totales-label">Neto</span><span class="factura-totales-valor">$' + moneda(neto) + '</span></div>' +
                '<div class="factura-totales-item"><span class="factura-totales-label">IVA</span><span class="factura-totales-valor">$' + moneda(iva) + '</span></div>' +
                '<div class="factura-totales-final"><span class="factura-totales-label">Total A Pagar</span><span class="factura-totales-valor">$' + moneda(total) + '</span></div>' +
            '</div>'
        );
    }
    $('#factura_comprobante_tipo_id').on('change', renderDetalle);

    function habilitarFormulario(habilitar) {
        $('#formFacturaCliente').find('input, select, textarea').prop('disabled', !habilitar);
        $('#btnAgregarProductoFactura, .factura-cantidad, .btn-cantidad-mas, .btn-cantidad-menos').prop('disabled', !habilitar);
        $('#btnGuardarFactura').toggle(habilitar);
    }

    // Deja el modal listo para un alta nueva (formulario en blanco, cards en su estado normal).
    // La separación de cargarClientes() la maneja cada llamador: el botón normal solo necesita
    // poblar el combo, pero el deep-link desde un remito necesita saber cuándo terminó para
    // poder preseleccionar el cliente correcto.
    function abrirFacturaNueva() {
        facturaEditId = null;
        remitoParaAutoFacturar = null;
        habilitarFormulario(true);
        $('#modalFacturaCliente .modal-title').html('<i class="fas fa-file-invoice-dollar me-2"></i>Nueva Factura De Cliente');
        $('#formFacturaCliente')[0].reset();
        $('#factura_f_emision').val(new Date().toISOString().slice(0, 10));
        sincronizarVencimiento();
        descuentoGeneralCliente = 0;
        $('#contenedorRemitosFactura').html('<div class="text-muted small">Seleccione un cliente.</div>');
        $('#cardAgregarProductoManual').show();
        $('#tituloCardRemitosFactura').text('Remitos Pendientes De Facturar');
        $('#modalFacturaAccionesExtra').empty();
        detalles = []; renderDetalle();
        cargarPuntosVenta();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalFacturaCliente')).show();
    }
    $('#btnNuevaFactura').on('click', function () {
        abrirFacturaNueva();
        cargarClientes();
    });

    // Deep-link desde ventas_remitos.php (botón "Facturado"/"Facturado Parcial" de un remito en
    // Pend. de Facturación o Facturación Parcial): abre "Nueva Factura" con el cliente de ese
    // remito ya elegido y sus líneas pendientes precargadas, listas para revisar y guardar.
    function abrirFacturaDesdeRemito(remitoId) {
        $.getJSON('ventas_facturas_ajax.php', { accion: 'remito_info', venta_remito_id: remitoId, empresa_idx: EMPRESA_ID }, function (res) {
            if (!res || !res.success || !res.data) {
                Swal.fire('Error', (res && res.error) || 'No se pudo cargar el remito.', 'error');
                return;
            }
            var info = res.data;
            abrirFacturaNueva();
            remitoParaAutoFacturar = info.venta_remito_id;
            cargarClientes(function () {
                // El combo puede tener varias opciones para el mismo entidad_id (una por
                // sucursal) — matchear por los dos, no alcanza con .val(entidad_id) solo.
                var opcion = $('#factura_entidad_id option').filter(function () {
                    return String($(this).val()) === String(info.entidad_id) && String($(this).data('sucursal-id') || '') === String(info.entidad_sucursal_id || '');
                });
                if (opcion.length) opcion.prop('selected', true);
                else $('#factura_entidad_id').val(info.entidad_id);
                cargarCliente();
            });
        });
    }
    $('#btnRecargar').on('click', function () { tabla.ajax.reload(null, false); });
    $('#btnGuardarFactura').on('click', function () {
        var btn = $(this), esEdicion = !!facturaEditId;
        var fEmision = $('#factura_f_emision').val(), fVto = $('#factura_f_vto').val();
        if (!fVto) { Swal.fire('Falta la fecha de vencimiento', 'Ingresá la fecha de vencimiento de la factura.', 'warning'); return; }
        if (fEmision && fVto < fEmision) { Swal.fire('Fecha de vencimiento inválida', 'El vencimiento no puede ser anterior a la fecha de emisión.', 'warning'); return; }
        var payload = { accion: esEdicion ? 'editar' : 'agregar', empresa_idx: EMPRESA_ID, pagina_idx: PAGINA_ID, entidad_id: $('#factura_entidad_id').val(), entidad_sucursal_id: $('#factura_entidad_sucursal_id').val(), condicion_pago_id: $('#factura_condicion_pago_id').val(), f_emision: fEmision, f_vto: fVto, sucursal_id: $('#factura_sucursal_id').val(), punto_venta_id: $('#factura_punto_venta_id').val(), comprobante_tipo_id: $('#factura_comprobante_tipo_id').val(), comprobante_nro: 0, moneda_id: 1, observaciones: $('#factura_observaciones').val(), detalles: JSON.stringify(detalles) };
        if (esEdicion) payload.venta_factura_id = facturaEditId;
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
        $.ajax({ url: 'ventas_facturas_ajax.php', type: 'POST', data: payload, dataType: 'json' }).done(function (res) { if (!res || !res.success) { Swal.fire('Error', (res && res.error) || 'No se pudo guardar la factura.', 'error'); return; } Swal.fire({ icon: 'success', title: esEdicion ? 'Factura Actualizada' : 'Factura Guardada', text: res.message || 'La factura se guardó correctamente.', confirmButtonText: 'Aceptar' }).then(function () { bootstrap.Modal.getInstance(document.getElementById('modalFacturaCliente')).hide(); tabla.ajax.reload(null, false); }); }).fail(function (xhr) { Swal.fire('Error', 'No se pudo guardar la factura: ' + (xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'error de servidor.'), 'error'); }).always(function () { btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Factura'); });
    });

    // Reabre el modal con los datos de una factura existente, en modo edición o solo lectura.
    // Solo se puede editar mientras conf__paginas_funciones tenga configurada una transición
    // 'editar' para el estado actual (hoy, solo en Borrador) — vfEditarVentaFactura() lo valida
    // igual del lado del servidor.
    // Tabla simple de solo lectura con lo que quedó cargado en la factura — se usa en modo
    // "visualizar" en vez del selector de remitos pendientes / agregado manual, que solo
    // tienen sentido mientras la factura todavía se puede modificar (Borrador).
    function renderProductosIncluidos(detallesFactura) {
        if (!detallesFactura || !detallesFactura.length) {
            return '<div class="text-muted small text-center p-3 border rounded bg-light">No hay productos cargados.</div>';
        }
        var html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead><tr><th>Nro. Remito</th><th>Código</th><th>Producto</th><th class="text-end">Cantidad</th><th class="text-end">Precio Unit.</th><th class="text-end">Descuento</th><th class="text-end">IVA</th><th class="text-end">Total Línea</th></tr></thead><tbody>';
        detallesFactura.forEach(function (d) {
            html += '<tr><td>' + (d.remito_comprobante_nro || '-') + '</td><td>' + (d.producto_codigo || d.producto_id) + '</td><td>' + (d.producto_nombre || '') + '</td>'
                + '<td class="text-end">' + (parseFloat(d.cantidad) || 0).toFixed(2) + '</td>'
                + '<td class="text-end">$' + moneda(d.precio_unitario) + '</td>'
                + '<td class="text-end">$' + moneda(d.descuento_general) + '</td>'
                + '<td class="text-end">$' + moneda(d.importe_iva) + '</td>'
                + '<td class="text-end fw-bold">$' + moneda(d.importe_linea) + '</td></tr>';
        });
        return html + '</tbody></table></div>';
    }

    function cargarFacturaParaEditar(id, soloVisualizar) {
        $.getJSON('ventas_facturas_ajax.php', { accion: 'obtener', venta_factura_id: id, empresa_idx: EMPRESA_ID, pagina_idx: PAGINA_ID }, function (res) {
            if (!res || !res.success || !res.data) { Swal.fire('Error', (res && res.error) || 'No se pudo obtener la factura.', 'error'); return; }
            var f = res.data;
            var readonly = !!soloVisualizar;
            facturaEditId = f.venta_factura_id;
            $('#formFacturaCliente')[0].reset();
            detalles = [];
            habilitarFormulario(true);

            cargarPuntosVenta(function () { cargarPuntoVentaYTipoParaEdicion(f); });

            $.getJSON('ventas_facturas_ajax.php', { accion: 'clientes', empresa_idx: EMPRESA_ID }, function (rows) {
                var html = '<option value="">Seleccionar cliente</option>';
                (rows || []).forEach(function (r) {
                    var sel = (String(r.entidad_id) === String(f.entidad_id) && String(r.entidad_sucursal_id || '') === String(f.entidad_sucursal_id || '')) ? ' selected' : '';
                    var texto = r.entidad_nombre + (r.entidad_fantasia ? ' - ' + r.entidad_fantasia : '') + (r.sucursal_nombre ? ' / ' + r.sucursal_nombre : ' / Sin Sucursal');
                    html += '<option value="' + r.entidad_id + '" data-sucursal-id="' + (r.entidad_sucursal_id || '') + '"' + sel + '>' + texto + '</option>';
                });
                $('#factura_entidad_id').html(html);
                $('#factura_entidad_sucursal_id').val(f.entidad_sucursal_id || '');
                $('#factura_condicion_pago_id').html('<option value="' + (f.condicion_pago_id || '') + '">' + (f.condicion_pago || 'Sin condición') + '</option>');
                $('#factura_f_emision').val(f.f_emision);
                $('#factura_f_vto').attr('min', f.f_emision || '').val(f.f_vto || f.f_emision || '');
                $('#factura_observaciones').val(f.observaciones || '');
                $('#factura_comprobante_nro').val(f.comprobante_nro > 0 ? f.comprobante_nro : 'Se asigna al confirmar');
                $.getJSON('ventas_facturas_ajax.php', { accion: 'condicion_cliente', entidad_id: f.entidad_id, empresa_idx: EMPRESA_ID }, mostrarCondicionCliente);

                function finalizarApertura() {
                    habilitarFormulario(!readonly);
                    $('#modalFacturaCliente .modal-title').html('<i class="fas fa-file-invoice-dollar me-2"></i>' + (readonly ? 'Ver Factura' : 'Editar Factura') + ' ' + (f.comprobante_tipo || '') + ' -' + (f.comprobante_nro || f.venta_factura_id));
                    renderAccionesExtraModal(f.botones, readonly ? 'visualizar' : 'editar');
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalFacturaCliente')).show();
                }

                if (readonly) {
                    // Ya no se puede tocar el detalle acá: mostrar solo lo que efectivamente
                    // quedó facturado, sin el selector de remitos pendientes ni el agregado manual.
                    $('#cardAgregarProductoManual').hide();
                    $('#tituloCardRemitosFactura').text('Productos Incluidos');
                    $('#contenedorRemitosFactura').html(renderProductosIncluidos(f.detalles));
                    detalles = (f.detalles || []).map(function (d) {
                        return { key: d.venta_factura_detalle_id, cantidad: parseFloat(d.cantidad) || 0, precio_unitario: parseFloat(d.precio_unitario) || 0, descuento_general: parseFloat(d.descuento_general) || 0, descuento_general_pct: parseFloat(d.descuento_general_pct) || 0, iva_porcentaje: parseFloat(d.porcentaje_iva) || 0 };
                    });
                    renderDetalle();
                    finalizarApertura();
                    return;
                }

                $('#cardAgregarProductoManual').show();
                $('#tituloCardRemitosFactura').text('Remitos Pendientes De Facturar');
                $.getJSON('ventas_facturas_ajax.php', { accion: 'remitos_pendientes', empresa_idx: EMPRESA_ID, entidad_id: f.entidad_id, entidad_sucursal_id: f.entidad_sucursal_id || 0, excluir_factura_id: facturaEditId }, function (pendientes) {
                    renderRemitos(pendientes);
                    (f.detalles || []).forEach(function (d) {
                        if (!d.venta_remito_detalle_id) {
                            var item = { key: d.venta_factura_detalle_id, producto_id: d.producto_id, producto_codigo: d.producto_codigo, producto_nombre: d.producto_nombre, cantidad_propia: parseFloat(d.cantidad) || 0, precio_unitario: parseFloat(d.precio_unitario) || 0, descuento_general_pct: parseFloat(d.descuento_general_pct) || 0, iva_alicuota_id: d.iva_alicuota_id || 1, iva_porcentaje: parseFloat(d.porcentaje_iva) || 0 };
                            $('#contenedorRemitosFactura .fila-sin-pendientes').remove();
                            $('#contenedorRemitosFactura tbody').append(filaItemFactura(item, true));
                        }
                    });
                    inicializarPaginacionRemitos();
                    $('#contenedorRemitosFactura .factura-cantidad').each(function () { if (parseFloat(this.value) > 0) $(this).trigger('change'); });
                    finalizarApertura();
                });
            });
        });
    }

    // Las mismas acciones que aparecen en la fila del datatable principal (Confirmar, Aprobada,
    // Eliminar, Imprimir, etc.), disponibles también sin salir del modal — salvo la acción que
    // se acaba de ejecutar para llegar acá (editar/visualizar), que no tiene sentido repetir.
    function renderAccionesExtraModal(botones, accionOrigen) {
        var cont = $('#modalFacturaAccionesExtra');
        var lista = (botones || []).filter(function (b) { return b.accion_js !== accionOrigen; });
        if (!lista.length) { cont.empty(); return; }
        var html = '';
        lista.forEach(function (b) {
            var clase = 'btn-sm ';
            if (b.bg_clase && b.text_clase) clase += b.bg_clase + ' ' + b.text_clase;
            else if (b.color_clase) clase += b.color_clase;
            else clase += 'btn-outline-primary';
            var icono = b.icono_clase ? '<i class="' + b.icono_clase + '"></i> ' : '';
            html += '<button type="button" class="btn ' + clase + ' btn-accion-factura-modal" title="' + (b.descripcion || b.nombre_funcion) + '" data-accion="' + b.accion_js + '" data-confirmable="' + (b.es_confirmable || 0) + '">' + icono + b.nombre_funcion + '</button>';
        });
        cont.html(html);
    }

    $(document).on('click', '.btn-accion-factura-modal', function () {
        var id = facturaEditId, accionJs = $(this).data('accion'), confirmable = $(this).data('confirmable');
        if (!id) return;
        if (accionJs === 'editar') { cargarFacturaParaEditar(id, false); return; }
        if (accionJs === 'visualizar') { cargarFacturaParaEditar(id, true); return; }
        if (accionJs === 'imprimir') {
            Swal.fire({ icon: 'info', title: 'Impresión pendiente', text: 'La emisión del comprobante impreso/PDF todavía no está implementada (depende de la integración con ARCA/AFIP).' });
            return;
        }
        if (confirmable == 1) {
            Swal.fire({
                title: '¿' + accionJs.charAt(0).toUpperCase() + accionJs.slice(1) + '?',
                text: '¿Está seguro de ' + accionJs + ' esta factura?',
                icon: 'question', showCancelButton: true, confirmButtonColor: '#3085d6', cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, ' + accionJs, cancelButtonText: 'Cancelar', reverseButtons: true, allowOutsideClick: false
            }).then(function (result) { if (result.isConfirmed) ejecutarAccionFactura(id, accionJs, 'Factura #' + id, true); });
        } else {
            ejecutarAccionFactura(id, accionJs, 'Factura #' + id, true);
        }
    });

    function ejecutarAccionFactura(id, accionJs, comprobanteInfo, cerrarModal) {
        $.post('ventas_facturas_ajax.php', { accion: 'ejecutar_accion', venta_factura_id: id, accion_js: accionJs, empresa_idx: EMPRESA_ID, pagina_idx: PAGINA_ID }, function (res) {
            if (res && res.success) {
                if (cerrarModal) bootstrap.Modal.getInstance(document.getElementById('modalFacturaCliente')).hide();
                tabla.ajax.reload(null, false);
                Swal.fire({ icon: 'success', title: '¡Listo!', text: res.message || (comprobanteInfo + ' actualizada.'), toast: true, position: 'top-end', showConfirmButton: false, timer: 2500 });
            } else {
                Swal.fire('Error', (res && res.error) || 'No se pudo ejecutar la acción.', 'error');
            }
        }, 'json').fail(function () { Swal.fire('Error', 'No se pudo ejecutar la acción.', 'error'); });
    }

    $(document).on('click', '.btn-accion-factura', function () {
        var id = $(this).data('id');
        var accionJs = $(this).data('accion');
        var confirmable = $(this).data('confirmable');
        var comprobanteInfo = $(this).data('comprobante') || ('Factura #' + id);

        if (accionJs === 'editar') { cargarFacturaParaEditar(id, false); return; }
        if (accionJs === 'visualizar') { cargarFacturaParaEditar(id, true); return; }
        if (accionJs === 'imprimir') {
            Swal.fire({ icon: 'info', title: 'Impresión pendiente', text: 'La emisión del comprobante impreso/PDF todavía no está implementada (depende de la integración con ARCA/AFIP).' });
            return;
        }

        if (confirmable == 1) {
            Swal.fire({
                title: '¿' + accionJs.charAt(0).toUpperCase() + accionJs.slice(1) + '?',
                html: '¿Está seguro de <strong>' + accionJs + '</strong> la factura<br><strong>' + comprobanteInfo + '</strong>?',
                icon: 'question', showCancelButton: true, confirmButtonColor: '#3085d6', cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, ' + accionJs, cancelButtonText: 'Cancelar', reverseButtons: true, allowOutsideClick: false
            }).then(function (result) { if (result.isConfirmed) ejecutarAccionFactura(id, accionJs, comprobanteInfo); });
        } else {
            ejecutarAccionFactura(id, accionJs, comprobanteInfo);
        }
    });

    cargarClientes();
    cargarBotonAgregar();
    if (REMITO_ID_INICIAL) abrirFacturaDesdeRemito(REMITO_ID_INICIAL);
    if (FACTURA_ID_INICIAL) cargarFacturaParaEditar(FACTURA_ID_INICIAL, FACTURA_ACCION_INICIAL === 'visualizar');
});
