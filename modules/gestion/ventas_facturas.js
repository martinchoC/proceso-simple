$(function () {
    var detalles = [];
    var productosEncontrados = [];
    var tabla = $('#tablaFacturasCliente').DataTable({
        ajax: { url: 'ventas_facturas_ajax.php', data: { accion: 'listar', empresa_idx: EMPRESA_ID, pagina_idx: PAGINA_ID }, dataSrc: '' },
        columns: [
            { data: 'comprobante_nro', className: 'text-center' },
            { data: null, render: function (d) { return (d.entidad_nombre || '') + (d.entidad_fantasia ? '<small class="d-block text-muted">' + d.entidad_fantasia + '</small>' : ''); } },
            { data: 'comprobante_tipo' }, { data: 'f_emision' }, { data: 'condicion_pago' },
            { data: 'importe_total', className: 'text-end', render: function (d) { return '$' + Number(d || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 }); } },
            { data: 'estado_registro' }
        ], order: [[3, 'desc']], language: { url: '//cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json' }
    });

    function moneda(v) { return Number(v || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function cargarClientes() {
        $.getJSON('ventas_facturas_ajax.php', { accion: 'clientes', empresa_idx: EMPRESA_ID }, function (rows) {
            var html = '<option value="">Seleccionar cliente</option>';
            (rows || []).forEach(function (r) { html += '<option value="' + r.entidad_id + '">' + r.entidad_nombre + '</option>'; });
            $('#factura_entidad_id').html(html);
        });
    }
    function cargarCliente() {
        var id = $('#factura_entidad_id').val();
        detalles = []; renderDetalle();
        if (!id) { $('#condicionClienteInfo').text('Seleccione un cliente para cargar sus condiciones comerciales.'); return; }
        $.getJSON('ventas_facturas_ajax.php', { accion: 'condicion_cliente', entidad_id: id }, function (c) {
            if (!c) { $('#condicionClienteInfo').text('El cliente no tiene condiciones comerciales vigentes.'); return; }
            $('#factura_condicion_pago_id').html('<option value="' + c.condicion_pago_id + '">' + (c.condicion_pago || 'Sin condición') + '</option>');
            $('#condicionClienteInfo').html('<strong>Lista De Precios:</strong> ' + (c.lista_precio_nombre || '-') + ' &nbsp; <strong>Descuento:</strong> ' + (c.cliente_descuento_general || 0) + '%');
            var dias = parseInt(c.dias_primer_vencimiento || 0, 10), fecha = $('#factura_f_emision').val();
            if (fecha && dias) { var f = new Date(fecha + 'T00:00:00'); f.setDate(f.getDate() + dias); $('#factura_f_vto').val(f.toISOString().slice(0, 10)); }
        });
        $.getJSON('ventas_facturas_ajax.php', { accion: 'remitos_pendientes', empresa_idx: EMPRESA_ID, entidad_id: id }, renderRemitos);
    }
    function renderRemitos(rows) {
        if (!rows || !rows.length) { $('#contenedorRemitosFactura').html('<div class="text-muted small">No hay remitos pendientes de facturar.</div>'); return; }
        var grupos = {};
        rows.forEach(function (r) { (grupos[r.venta_remito_id] = grupos[r.venta_remito_id] || []).push(r); });
        var html = '';
        Object.keys(grupos).forEach(function (id) {
            var grupo = grupos[id], r0 = grupo[0];
            html += '<div class="factura-remito"><div class="fw-bold mb-1"><input type="checkbox" class="check-remito" data-remito-id="' + id + '"> ' + (r0.comprobante_tipo || 'Remito') + ' #' + (r0.comprobante_nro || 'Sin Número') + ' <small class="text-muted">' + r0.f_emision + '</small></div>';
            grupo.forEach(function (r) { html += '<label class="d-block small ms-4"><input type="checkbox" class="check-remito-detalle" data-remito-detalle-id="' + r.venta_remito_detalle_id + '" data-producto-id="' + r.producto_id + '" data-pedido-detalle-id="' + (r.venta_pedido_detalle_id || '') + '" data-cantidad="' + r.cantidad + '" data-precio="' + r.precio_unitario_neto + '" data-iva-id="' + (r.iva_alicuota_id || 1) + '" data-iva="' + (r.iva_porcentaje || 0) + '"> ' + r.producto_codigo + ' - ' + r.producto_nombre + ' (' + moneda(r.cantidad) + ')</label>'; });
            html += '</div>';
        });
        $('#contenedorRemitosFactura').html(html);
    }
    $(document).on('change', '.check-remito', function () { $(this).closest('.factura-remito').find('.check-remito-detalle').prop('checked', this.checked).trigger('change'); });
    $(document).on('change', '.check-remito-detalle', function () {
        var el = $(this), key = 'r' + el.data('remito-detalle-id');
        detalles = detalles.filter(function (d) { return d.key !== key; });
        if (el.prop('checked')) detalles.push({ key: key, producto_id: el.data('producto-id'), venta_remito_detalle_id: el.data('remito-detalle-id'), venta_pedido_detalle_id: el.data('pedido-detalle-id') || null, cantidad: parseFloat(el.data('cantidad')), precio_unitario: parseFloat(el.data('precio')) || 0, iva_alicuota_id: parseInt(el.data('iva-id')) || 1, iva_porcentaje: parseFloat(el.data('iva')) || 0 });
        renderDetalle();
    });
    $('#factura_f_emision').val(new Date().toISOString().slice(0, 10));
    $('#factura_entidad_id').on('change', cargarCliente);
    $('#factura_f_emision').on('change', cargarCliente);
    $('#factura_producto_busqueda').on('input', function () {
        var q = this.value, id = $('#factura_entidad_id').val(); if (q.length < 2 || !id) return $('#factura_productos_resultados').empty();
        $.getJSON('ventas_facturas_ajax.php', { accion: 'buscar_productos', empresa_idx: EMPRESA_ID, entidad_id: id, q: q }, function (rows) { productosEncontrados = rows || []; $('#factura_productos_resultados').html(productosEncontrados.map(function (p, i) { return '<button type="button" class="list-group-item list-group-item-action" data-index="' + i + '">' + p.producto_codigo + ' - ' + p.producto_nombre + '</button>'; }).join('')); });
    });
    $(document).on('click', '#factura_productos_resultados .list-group-item', function () { var p = productosEncontrados[$(this).data('index')]; $('#factura_producto_busqueda').val(p.producto_codigo + ' - ' + p.producto_nombre).data('producto', p); $('#factura_producto_precio').val(p.precio_unitario); $('#factura_productos_resultados').empty(); });
    $('#btnAgregarProductoFactura').on('click', function () { var p = $('#factura_producto_busqueda').data('producto'), qty = parseFloat($('#factura_producto_cantidad').val()) || 0; if (!p || qty <= 0) return; detalles.push({ key: 'm' + Date.now(), producto_id: p.producto_id, cantidad: qty, precio_unitario: parseFloat($('#factura_producto_precio').val()) || 0, iva_alicuota_id: p.iva_alicuota_id || 1, iva_porcentaje: p.iva_porcentaje || 0 }); $('#factura_producto_busqueda').val('').removeData('producto'); renderDetalle(); });
    function renderDetalle() { var html = detalles.length ? '<table class="table table-sm"><thead><tr><th>Producto</th><th class="text-end">Cantidad</th><th class="text-end">Precio</th><th class="text-end">Total</th></tr></thead><tbody>' : '<div class="text-muted small">No hay productos seleccionados.</div>'; var total = 0; detalles.forEach(function (d) { var linea = d.cantidad * d.precio_unitario * (1 + d.iva_porcentaje / 100); total += linea; html += '<tr><td>' + (d.producto_id || '') + (d.venta_remito_detalle_id ? ' <small class="text-muted">(Remito)</small>' : ' <small class="text-muted">(Manual)</small>') + '</td><td class="text-end">' + moneda(d.cantidad) + '</td><td class="text-end">$' + moneda(d.precio_unitario) + '</td><td class="text-end">$' + moneda(linea) + '</td></tr>'; }); if (detalles.length) html += '</tbody></table>'; $('#contenedorDetalleFactura').html(html); $('#factura_total').text(moneda(total)); }
    $('#btnNuevaFactura').on('click', function () { $('#formFacturaCliente')[0].reset(); $('#factura_f_emision').val(new Date().toISOString().slice(0, 10)); detalles = []; renderDetalle(); cargarClientes(); bootstrap.Modal.getOrCreateInstance(document.getElementById('modalFacturaCliente')).show(); });
    $('#btnRecargar').on('click', function () { tabla.ajax.reload(null, false); });
    $('#btnGuardarFactura').on('click', function () { var payload = { accion: 'guardar', empresa_idx: EMPRESA_ID, pagina_idx: PAGINA_ID, entidad_id: $('#factura_entidad_id').val(), entidad_sucursal_id: 0, condicion_pago_id: $('#factura_condicion_pago_id').val(), f_emision: $('#factura_f_emision').val(), f_vto: $('#factura_f_vto').val(), sucursal_id: $('#factura_sucursal_id').val(), punto_venta_id: $('#factura_punto_venta_id').val(), comprobante_tipo_id: $('#factura_comprobante_tipo_id').val(), comprobante_nro: $('#factura_comprobante_nro').val(), moneda_id: 1, observaciones: $('#factura_observaciones').val(), detalles: JSON.stringify(detalles) }; $.post('ventas_facturas_ajax.php', payload, function (res) { if (!res.success) return Swal.fire('Error', res.error, 'error'); Swal.fire('Factura Guardada', res.message, 'success'); bootstrap.Modal.getInstance(document.getElementById('modalFacturaCliente')).hide(); tabla.ajax.reload(null, false); }, 'json'); });
    cargarClientes();
});
