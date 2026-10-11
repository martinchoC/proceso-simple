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
    // Percepciones IIBB: las calcula el servidor (calcular_percepciones) mientras se edita; en solo
    // lectura se muestran las grabadas en el comprobante.
    var percepcionesFactura = [];
    var percepcionesAvisos = [];
    var facturaSoloLectura = false;
    var timerPercepciones = null;
    function esc(t) { return $('<div>').text(t == null ? '' : t).html(); }
    // Consumidor final: opción "CF" del combo de cliente. Hacia el servidor viaja como
    // entidad_id = 0 en las consultas (condición, remitos pendientes, productos) y como
    // entidad_id vacío + consumidor_final_nombre al guardar.
    function esConsumidorFinal() { return $('#factura_entidad_id').val() === 'CF'; }
    function entidadIdConsulta() { return esConsumidorFinal() ? 0 : $('#factura_entidad_id').val(); }
    function mostrarCampoConsumidorFinal(mostrar) {
        $('#grupo_factura_consumidor_final').toggle(mostrar);
        if (!mostrar) $('#factura_consumidor_final_nombre').val('');
    }
    var tabla = $('#tablaFacturasCliente').DataTable({
        ajax: { url: 'ventas_facturas_ajax.php', data: { accion: 'listar', empresa_idx: EMPRESA_ID, pagina_idx: PAGINA_ID }, dataSrc: '' },
        columns: [
            { data: 'comprobante_nro', className: 'text-center', render: function (d, type, row) {
                if (type !== 'display' || parseInt(d, 10) > 0) return d;
                // Las fiscales se numeran recién con el CAE de ARCA
                return '<span class="badge bg-secondary-subtle text-secondary border">' + (parseInt(row.tabla_estado_registro_id, 10) === 4 ? 'Pend. CAE' : 'Sin número') + '</span>';
            } },
            { data: null, render: function (d) {
                if (!d.entidad_id) return esc(d.consumidor_final_nombre || '') + '<small class="d-block text-muted"><i class="fas fa-user me-1"></i>Consumidor Final</small>';
                return (d.entidad_nombre || '') + (d.entidad_fantasia ? '<small class="d-block text-muted">' + d.entidad_fantasia + '</small>' : '');
            } },
            { data: 'comprobante_tipo', className: 'text-center' },
            {
                data: 'f_emision',
                className: 'text-center',
                render: function (data, type) {
                    if (type === 'sort' || type === 'filter') return data || '';
                    if (type === 'export' || !data) return data || '';
                    var parts = (data || '').split('-');
                    return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : data;
                }
            },
            { data: 'condicion_pago' },
            { data: 'importe_total', className: 'text-end', render: function (d) { return '$' + Number(d || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 }); } },
            { data: 'CAE', className: 'text-center', defaultContent: '', render: function (d, type, row) {
                if (type !== 'display') return d || '';
                if (!d) return '<span class="text-muted">-</span>';
                var vto = row.f_vto_CAE ? row.f_vto_CAE.split('-').reverse().join('/') : '';
                return '<span title="' + (vto ? 'Vence ' + vto : '') + '">' + esc(d) + '</span>';
            } },
            { data: 'estado_registro', className: 'text-center' },
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
        ],
        order: [[3, 'desc']],
        dom: '<"row"<"col-sm-12"tr>>' +
             '<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>' +
             '<"clear">',
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
        language: {
            url: '//cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json',
            lengthMenu: 'Mostrar _MENU_ registros',
            info: 'Página _PAGE_ de _PAGES_',
            infoEmpty: 'Página 0 de 0',
            infoFiltered: '(filtrado de _MAX_ registros)',
            paginate: {
                first: 'Primero',
                previous: 'Anterior',
                next: 'Siguiente',
                last: 'Último'
            }
        }
    });

    $('#filtro_facturas').on('keyup', function () {
        tabla.search(this.value).draw();
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
            var html = '<option value="">Seleccionar cliente</option>' + '<option value="CF" data-sucursal-id="">Consumidor Final (precio de mostrador)</option>';
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
    function cargarTiposPorPuntoVenta(cb) {
        var pv = $('#factura_punto_venta_id').val();
        var option = $('#factura_punto_venta_id option:selected');
        $('#factura_sucursal_id').val(option.data('sucursal-id') || '');
        if (!pv) {
            $('#factura_comprobante_tipo_id').html('<option value="">Seleccionar PV</option>');
            if (typeof cb === 'function') cb();
            return;
        }
        var entidadVal = esConsumidorFinal() ? 'CF' : ($('#factura_entidad_id').val() || '');
        $.getJSON('ventas_facturas_ajax.php', {
            accion: 'tipos_por_punto_venta',
            empresa_idx: EMPRESA_ID,
            pagina_idx: PAGINA_ID,
            punto_venta_id: pv,
            entidad_id: entidadVal
        }, function (rows) {
            var valorPrevio = $('#factura_comprobante_tipo_id').val();
            var tipoSugerido = null;
            var html = '<option value="">Seleccionar tipo</option>';
            (rows || []).forEach(function (r) {
                if (r.es_sugerido) tipoSugerido = r.comprobante_tipo_id;
                var sel = (String(r.comprobante_tipo_id) === String(valorPrevio)) ? ' selected' : '';
                html += '<option value="' + r.comprobante_tipo_id + '" data-fiscal="' + (r.comprobante_fiscal_id || 0) + '" data-discrimina-iva="' + (r.discrimina_iva ?? '') + '"' + sel + '>' + r.comprobante_tipo + (r.letra ? ' (' + r.letra + ')' : '') + '</option>';
            });
            $('#factura_comprobante_tipo_id').html(html);
            if (!$('#factura_comprobante_tipo_id').val() && tipoSugerido) {
                $('#factura_comprobante_tipo_id').val(tipoSugerido);
            } else if (!$('#factura_comprobante_tipo_id').val() && rows && rows.length === 1) {
                $('#factura_comprobante_tipo_id').val(rows[0].comprobante_tipo_id);
            }
            renderDetalle();
            if (typeof cb === 'function') cb();
        });
    }
    // Variante usada al reabrir una factura existente (editar/visualizar): además de poblar
    // el combo de tipos, deja seleccionado el tipo que ya tenía la factura.
    function cargarPuntoVentaYTipoParaEdicion(f) {
        $('#factura_punto_venta_id').val(f.punto_venta_id);
        var option = $('#factura_punto_venta_id option:selected');
        $('#factura_sucursal_id').val(option.data('sucursal-id') || '');
        if (!f.punto_venta_id) return;
        var entidadVal = f.entidad_id ? f.entidad_id : 'CF';
        $.getJSON('ventas_facturas_ajax.php', {
            accion: 'tipos_por_punto_venta',
            empresa_idx: EMPRESA_ID,
            pagina_idx: PAGINA_ID,
            punto_venta_id: f.punto_venta_id,
            entidad_id: entidadVal
        }, function (rows) {
            var html = '<option value="">Seleccionar tipo</option>';
            (rows || []).forEach(function (r) {
                var sel = (String(r.comprobante_tipo_id) === String(f.comprobante_tipo_id)) ? ' selected' : '';
                html += '<option value="' + r.comprobante_tipo_id + '" data-fiscal="' + (r.comprobante_fiscal_id || 0) + '" data-discrimina-iva="' + (r.discrimina_iva ?? '') + '"' + sel + '>' + r.comprobante_tipo + (r.letra ? ' (' + r.letra + ')' : '') + '</option>';
            });
            $('#factura_comprobante_tipo_id').html(html);
            renderDetalle();
            actualizarComprobanteAsociado(f.comprobante_asociado_id, f.entidad_id || 0);
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
        var condFiscal = c && c.condicion_fiscal ? c.condicion_fiscal : (esConsumidorFinal() ? 'Consumidor Final' : 'Sin condición');
        var badgeFiscal = '<strong>Condición Fiscal:</strong> ' + esc(condFiscal);
        if (!c && esConsumidorFinal()) {
            $('#condicionClienteInfo').html(badgeFiscal + ' &nbsp;|&nbsp; La empresa no tiene activa la lista de precios "Mostrador" (código MOST), que es la que se aplica a consumidores finales.');
            return;
        }
        if (!c) {
            $('#condicionClienteInfo').html(badgeFiscal + ' &nbsp;|&nbsp; El cliente no tiene condiciones comerciales vigentes.');
            return;
        }
        if (c.es_consumidor_final) {
            $('#condicionClienteInfo').html(badgeFiscal + ' &nbsp;|&nbsp; <strong>Precios:</strong> ' + esc(c.lista_precio_nombre || '-') + ' &nbsp;|&nbsp; <strong>Pago:</strong> ' + esc(c.condicion_pago || 'contado'));
            return;
        }
        $('#condicionClienteInfo').html(badgeFiscal + ' &nbsp;|&nbsp; <strong>Lista De Precios:</strong> ' + (c.lista_precio_nombre || '-') + ' &nbsp;|&nbsp; <strong>Descuento:</strong> ' + descuentoGeneralCliente + '%');
    }
    function cargarCliente() {
        var id = $('#factura_entidad_id').val();
        var sucursalClienteId = $('#factura_entidad_id option:selected').data('sucursal-id') || 0;
        detalles = []; renderDetalle();
        sincronizarVencimiento();
        $('#grupo_factura_consumidor_final').toggle(id === 'CF');
        if (!id) {
            descuentoGeneralCliente = 0;
            $('#condicionClienteInfo').text('Seleccione un cliente para cargar sus condiciones comerciales.');
            if ($('#factura_punto_venta_id').val()) cargarTiposPorPuntoVenta();
            return;
        }
        $.getJSON('ventas_facturas_ajax.php', { accion: 'condicion_cliente', entidad_id: entidadIdConsulta(), empresa_idx: EMPRESA_ID }, function (c) {
            mostrarCondicionCliente(c);
            if (!c) return;
            $('#factura_condicion_pago_id').html('<option value="' + c.condicion_pago_id + '">' + (c.condicion_pago || 'Sin condición') + '</option>');
            var dias = parseInt(c.dias_primer_vencimiento || 0, 10), fecha = $('#factura_f_emision').val();
            if (fecha) { var f = new Date(fecha + 'T00:00:00'); f.setDate(f.getDate() + dias); $('#factura_f_vto').val(f.toISOString().slice(0, 10)); }
        });
        $('#factura_entidad_sucursal_id').val(sucursalClienteId);
        $.getJSON('ventas_facturas_ajax.php', { accion: 'remitos_pendientes', empresa_idx: EMPRESA_ID, entidad_id: entidadIdConsulta(), entidad_sucursal_id: sucursalClienteId, excluir_factura_id: facturaEditId || 0 }, renderRemitos);
        if ($('#factura_punto_venta_id').val()) {
            cargarTiposPorPuntoVenta();
        }
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
        var neto = Math.max(0, precio - descuento);
        var valorInicial = cantidadPropia > 0 ? cantidadPropia.toFixed(2) : '0.00';
        var nroRemito = manual ? '-' : (item.comprobante_nro || '-') + (item.consumidor_final_nombre ? '<small class="d-block text-muted">' + esc(item.consumidor_final_nombre) + '</small>' : '');
        return '<tr><td>' + nroRemito + '</td><td>' + (item.producto_codigo || item.producto_id) + '</td><td>' + (item.producto_nombre || 'Producto manual') + (manual ? ' <small class="text-muted">(Manual)</small>' : '') + '</td><td class="text-end cantidad-pendiente">' + (manual ? '-' : cantidadPendiente.toFixed(2)) + '</td><td class="text-end"><div class="factura-cantidad-stepper"><button type="button" class="btn btn-sm btn-outline-secondary btn-cantidad-menos" tabindex="-1">&minus;</button><input type="number" class="form-control form-control-sm factura-cantidad no-spinner" data-key="' + key + '" data-remito-detalle-id="' + (manual ? '' : item.venta_remito_detalle_id) + '" data-producto-id="' + item.producto_id + '" data-pedido-detalle-id="' + (item.venta_pedido_detalle_id || '') + '" data-precio="' + precio + '" data-descuento-pct="' + descuentoPct + '" data-precio-neto="' + neto + '" data-iva-id="' + (item.iva_alicuota_id || 1) + '" data-iva="' + (item.iva_porcentaje || 0) + '" value="' + valorInicial + '" min="0" max="' + cantidadMax + '" step="1"><button type="button" class="btn btn-sm btn-outline-secondary btn-cantidad-mas" tabindex="-1">+</button></div></td><td class="text-end"><input type="number" class="form-control form-control-sm text-end factura-precio no-spinner" value="' + precio.toFixed(2) + '" min="0" step="0.01"></td><td class="text-end factura-descuento-renglon">$' + moneda(descuento) + '</td><td class="text-end factura-neto-renglon">$' + moneda(neto) + '</td><td class="text-end fw-bold total-neto-renglon">$0.00</td></tr>';
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
    // Precio editable en cualquier línea (de remito o manual): actualiza los data-* de la
    // cantidad, que son los que arman `detalles`, y recalcula descuento y neto de la fila.
    $(document).on('change', '.factura-precio', function () {
        var row = $(this).closest('tr');
        var input = row.find('.factura-cantidad');
        var precio = Math.max(0, parseFloat(this.value) || 0);
        this.value = precio.toFixed(2);
        var descuento = precio * (parseFloat(input.data('descuento-pct')) || 0) / 100;
        var neto = Math.max(0, precio - descuento);
        input.data('precio', precio).data('precio-neto', neto);
        row.find('.factura-descuento-renglon').text('$' + moneda(descuento));
        row.find('.factura-neto-renglon').text('$' + moneda(neto));
        input.trigger('change');
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
    $('#factura_entidad_id').on('change', function () { mostrarCampoConsumidorFinal(esConsumidorFinal()); cargarCliente(); });
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
        var input = this, q = this.value, id = $('#factura_entidad_id').val(); if (q.length < 2 || !id) return $('#factura_productos_resultados').empty();
        $.getJSON('ventas_facturas_ajax.php', { accion: 'buscar_productos', empresa_idx: EMPRESA_ID, entidad_id: entidadIdConsulta(), q: q }, function (rows) {
            // Se dispara una búsqueda por tecla: si llega tarde la respuesta de un texto
            // anterior, se descarta para no pisar los resultados del texto actual.
            if (input.value !== q) return;
            productosEncontrados = rows || [];
            $('#factura_productos_resultados').html(productosEncontrados.map(function (p, i) {
                return '<button type="button" class="list-group-item list-group-item-action" data-index="' + i + '">'
                    + '<strong>' + $('<div>').text(p.producto_codigo || '').html() + '</strong> - ' + $('<div>').text(p.producto_nombre || '').html()
                    + (p.compatibilidad_texto ? '<small class="d-block text-muted"><i class="fas fa-car-side me-1"></i>' + $('<div>').text(p.compatibilidad_texto).html() + '</small>' : '')
                    + '</button>';
            }).join(''));
        });
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
        ultimosTotales = { bruto: bruto, descuento: descuento, neto: neto, iva: iva };
        pintarTotales();
        if (!facturaSoloLectura) pedirPercepciones();
    }
    var ultimosTotales = { bruto: 0, descuento: 0, neto: 0, iva: 0 };
    function pintarTotales() {
        var t = ultimosTotales, percepcion = 0, filasPerc = '';
        (percepcionesFactura || []).forEach(function (p) {
            var imp = parseFloat(p.importe) || 0;
            percepcion += imp;
            if (imp > 0) filasPerc += '<div class="factura-totales-item"><span class="factura-totales-label">' + esc(p.leyenda || ('Percepción IIBB ' + (p.jurisdiccion_nombre || ''))) + ' ' + moneda(p.alicuota) + '%</span><span class="factura-totales-valor">$' + moneda(imp) + '</span></div>';
        });
        var total = t.neto + t.iva + percepcion;
        $('#factura_total').text(moneda(total));
        $('#factura_totales').html(
            '<div class="factura-totales-box">' +
                '<div class="factura-totales-item"><span class="factura-totales-label">Bruto</span><span class="factura-totales-valor">$' + moneda(t.bruto) + '</span></div>' +
                '<div class="factura-totales-item"><span class="factura-totales-label">Descuento</span><span class="factura-totales-valor">$' + moneda(t.descuento) + '</span></div>' +
                '<div class="factura-totales-item"><span class="factura-totales-label">Neto</span><span class="factura-totales-valor">$' + moneda(t.neto) + '</span></div>' +
                '<div class="factura-totales-item"><span class="factura-totales-label">IVA</span><span class="factura-totales-valor">$' + moneda(t.iva) + '</span></div>' +
                filasPerc +
                '<div class="factura-totales-final"><span class="factura-totales-label">Total A Pagar</span><span class="factura-totales-valor">$' + moneda(total) + '</span></div>' +
            '</div>' +
            ((percepcionesAvisos || []).length ? '<div class="small text-warning mt-1"><i class="fas fa-exclamation-triangle me-1"></i>' + percepcionesAvisos.map(esc).join('<br>') + '</div>' : '')
        );
    }
    // Vista previa de percepciones IIBB (no graba): se pide con un pequeño retardo para no llamar al servidor en cada tecla.
    function pedirPercepciones() {
        clearTimeout(timerPercepciones);
        timerPercepciones = setTimeout(function () {
            if (!detalles.length || esConsumidorFinal() || !$('#factura_entidad_id').val() || !$('#factura_comprobante_tipo_id').val()) {
                percepcionesFactura = []; percepcionesAvisos = []; pintarTotales(); return;
            }
            $.ajax({ url: 'ventas_facturas_ajax.php', type: 'POST', dataType: 'json', data: {
                accion: 'calcular_percepciones', empresa_idx: EMPRESA_ID, pagina_idx: PAGINA_ID,
                entidad_id: $('#factura_entidad_id').val(), entidad_sucursal_id: $('#factura_entidad_sucursal_id').val(),
                sucursal_id: $('#factura_sucursal_id').val(), comprobante_tipo_id: $('#factura_comprobante_tipo_id').val(),
                comprobante_asociado_id: $('#factura_comprobante_asociado_id').val() || '', f_emision: $('#factura_f_emision').val(),
                destino_bien_uso: $('#factura_destino_bien_uso').is(':checked') ? 1 : 0, detalles: JSON.stringify(detalles)
            } }).done(function (r) {
                percepcionesFactura = (r && r.percepciones) || [];
                percepcionesAvisos = (r && r.avisos) || [];
                pintarTotales();
            });
        }, 400);
    }
    $('#factura_comprobante_tipo_id').on('change', renderDetalle);
    $(document).on('change', '#factura_destino_bien_uso, #factura_f_emision, #factura_entidad_sucursal_id, #factura_comprobante_asociado_id', function () { if (!facturaSoloLectura) pedirPercepciones(); });

    // ---------- Notas de crédito / débito: comprobante asociado ----------
    // El combo aparece solo para tipos NC/ND (es_nota) y con un cliente elegido (no consumidor final).
    function actualizarComprobanteAsociado(seleccionado, entidadForzada) {
        var tipo = $('#factura_comprobante_tipo_id').val();
        var entidad = entidadForzada !== undefined ? entidadForzada : entidadIdConsulta();
        var wrap = $('#factura_asociado_wrap'), sel = $('#factura_comprobante_asociado_id');
        if (!tipo || !entidad || String(entidad) === '0') { wrap.addClass('d-none'); sel.html('<option value="">Sin comprobante asociado</option>'); return; }
        $.getJSON('ventas_facturas_ajax.php', { accion: 'comprobantes_asociables', entidad_id: entidad, comprobante_tipo_id: tipo, empresa_idx: EMPRESA_ID }, function (res) {
            if (!res || !res.success || !res.es_nota) { wrap.addClass('d-none'); sel.html('<option value="">Sin comprobante asociado</option>'); return; }
            var html = '<option value="">Sin comprobante asociado</option>';
            (res.data || []).forEach(function (c) {
                var nro = String(c.comprobante_pv).padStart(4, '0') + '-' + String(c.comprobante_nro).padStart(8, '0');
                var s = String(c.comprobante_id) === String(seleccionado || '') ? ' selected' : '';
                html += '<option value="' + c.comprobante_id + '"' + s + '>' + esc(c.comprobante_tipo) + ' ' + nro + ' · ' + esc((c.f_emision || '').split('-').reverse().join('/'))
                      + ' · total $' + moneda(c.importe_total) + ' · saldo $' + moneda(c.importe_pendiente) + '</option>';
            });
            sel.html(html).prop('disabled', $('#factura_comprobante_tipo_id').prop('disabled'));
            wrap.removeClass('d-none');
        });
    }
    $('#factura_comprobante_tipo_id, #factura_entidad_id').on('change', function () { actualizarComprobanteAsociado(); });

    // Al confirmar una factura/ND con notas de crédito con saldo del cliente: ofrecer imputarlas (recibo en $0)
    function ofrecerNotasCredito(facturaId, disp) {
        if (!disp || !disp.notas || !disp.notas.length) return;
        var filas = disp.notas.map(function (n, i) {
            var nro = String(n.comprobante_pv).padStart(4, '0') + '-' + String(n.comprobante_nro).padStart(8, '0');
            return '<div class="form-check text-start"><input class="form-check-input nc-aplicar" type="checkbox" checked id="nc_ap_' + i + '" value="' + n.comprobante_id + '">'
                 + '<label class="form-check-label" for="nc_ap_' + i + '">' + esc(n.comprobante_tipo) + ' ' + nro + ' · saldo $' + moneda(n.importe_pendiente) + '</label></div>';
        }).join('');
        Swal.fire({
            icon: 'question',
            title: 'El cliente tiene notas de crédito con saldo',
            html: '<p class="mb-2">Saldo de la factura: <strong>$' + moneda(disp.saldo) + '</strong>. ¿Imputar estas notas de crédito? Se genera un recibo en $0.</p>' + filas,
            showCancelButton: true, confirmButtonText: 'Imputar', cancelButtonText: 'Ahora no',
            preConfirm: function () {
                var apl = $('.nc-aplicar:checked').map(function () { return { nc_comprobante_id: parseInt(this.value, 10), importe: 0 }; }).get();
                if (!apl.length) { Swal.showValidationMessage('Marcá al menos una nota de crédito'); return false; }
                return $.post('ventas_facturas_ajax.php', { accion: 'aplicar_notas_credito', venta_factura_id: facturaId, empresa_idx: EMPRESA_ID, aplicaciones: JSON.stringify(apl) }, null, 'json')
                    .then(function (r) { if (!r || !r.success) { Swal.showValidationMessage((r && r.error) || 'No se pudo imputar'); return false; } return r; },
                          function () { Swal.showValidationMessage('No se pudo conectar con el servidor'); return false; });
            }
        }).then(function (result) {
            if (result.isConfirmed) {
                tabla.ajax.reload(null, false);
                Swal.fire({ icon: 'success', title: 'Notas de crédito imputadas', text: result.value.message });
            }
        });
    }

    function habilitarFormulario(habilitar) {
        $('#formFacturaCliente').find('input, select, textarea').prop('disabled', !habilitar);
        $('#btnAgregarProductoFactura, .factura-cantidad, .factura-precio, .btn-cantidad-mas, .btn-cantidad-menos').prop('disabled', !habilitar);
        $('#btnGuardarFactura').toggle(habilitar);
    }

    // Deja el modal listo para un alta nueva (formulario en blanco, cards en su estado normal).
    // La separación de cargarClientes() la maneja cada llamador: el botón normal solo necesita
    // poblar el combo, pero el deep-link desde un remito necesita saber cuándo terminó para
    // poder preseleccionar el cliente correcto.
    function abrirFacturaNueva() {
        facturaEditId = null;
        remitoParaAutoFacturar = null;
        facturaSoloLectura = false;
        percepcionesFactura = []; percepcionesAvisos = [];
        habilitarFormulario(true);
        $('#modalFacturaCliente .modal-title').html('<i class="fas fa-file-invoice-dollar me-2"></i>Nueva Factura De Cliente');
        $('#formFacturaCliente')[0].reset();
        $('#factura_cae_info').empty();
        $('#factura_asociado_wrap').addClass('d-none');
        $('#factura_comprobante_asociado_id').html('<option value="">Sin comprobante asociado</option>');
        mostrarCampoConsumidorFinal(false);
        $('#factura_f_emision').val(new Date().toISOString().slice(0, 10));
        sincronizarVencimiento();
        descuentoGeneralCliente = 0;
        $('#contenedorRemitosFactura').html('<div class="text-muted small">Seleccione un cliente.</div>');
        $('#cardAgregarProductoManual').show();
        $('#tituloCardRemitosFactura').text('Remitos Pendientes De Facturar');
        $('#modalFacturaAccionesExtra').empty();
        detalles = []; renderDetalle();
        cargarPuntosVenta();
        if (document.getElementById('tab-factura-btn')) {
            bootstrap.Tab.getOrCreateInstance(document.getElementById('tab-factura-btn')).show();
        }
        $('#badgeAsientoEstado').addClass('d-none');
        $('#contenedorAsientoDetalle').hide();
        $('#contenedorAsientoVacio').show();
        $('#btnGenerarAsientoManual').hide();
        resetCobro('Guarde y confirme la factura para registrar cobros.');
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
                if (!info.entidad_id) {
                    $('#factura_entidad_id').val('CF');
                    mostrarCampoConsumidorFinal(true);
                    $('#factura_consumidor_final_nombre').val(info.consumidor_final_nombre || '');
                    cargarCliente();
                    return;
                }
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
        var consumidorFinalNombre = esConsumidorFinal() ? $.trim($('#factura_consumidor_final_nombre').val()) : '';
        if (esConsumidorFinal() && !consumidorFinalNombre) { Swal.fire('Falta el nombre', 'Indicá el nombre del consumidor final.', 'warning'); return; }
        var payload = { accion: esEdicion ? 'editar' : 'agregar', empresa_idx: EMPRESA_ID, pagina_idx: PAGINA_ID, entidad_id: esConsumidorFinal() ? '' : $('#factura_entidad_id').val(), entidad_sucursal_id: esConsumidorFinal() ? '' : $('#factura_entidad_sucursal_id').val(), consumidor_final_nombre: consumidorFinalNombre, condicion_pago_id: $('#factura_condicion_pago_id').val(), f_emision: fEmision, f_vto: fVto, sucursal_id: $('#factura_sucursal_id').val(), punto_venta_id: $('#factura_punto_venta_id').val(), comprobante_tipo_id: $('#factura_comprobante_tipo_id').val(), comprobante_asociado_id: $('#factura_comprobante_asociado_id').val() || '', comprobante_nro: 0, observaciones: $('#factura_observaciones').val(), destino_bien_uso: $('#factura_destino_bien_uso').is(':checked') ? 1 : 0, detalles: JSON.stringify(detalles) };
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

    // tabInicial = 'pagos' abre directo la solapa "Medios de Pago" (acción Cobrar).
    function cargarFacturaParaEditar(id, soloVisualizar, tabInicial) {
        $.getJSON('ventas_facturas_ajax.php', { accion: 'obtener', venta_factura_id: id, empresa_idx: EMPRESA_ID, pagina_idx: PAGINA_ID }, function (res) {
            if (!res || !res.success || !res.data) { Swal.fire('Error', (res && res.error) || 'No se pudo obtener la factura.', 'error'); return; }
            var f = res.data;
            var readonly = !!soloVisualizar;
            facturaSoloLectura = readonly;
            percepcionesFactura = f.percepciones || []; percepcionesAvisos = [];
            facturaEditId = f.venta_factura_id;
            $('#formFacturaCliente')[0].reset();
            detalles = [];
            habilitarFormulario(true);

            cargarPuntosVenta(function () { cargarPuntoVentaYTipoParaEdicion(f); });

            $.getJSON('ventas_facturas_ajax.php', { accion: 'clientes', empresa_idx: EMPRESA_ID }, function (rows) {
                var html = '<option value="">Seleccionar cliente</option>' + '<option value="CF" data-sucursal-id=""' + (f.entidad_id ? '' : ' selected') + '>Consumidor Final (precio de mostrador)</option>';
                (rows || []).forEach(function (r) {
                    var sel = (String(r.entidad_id) === String(f.entidad_id) && String(r.entidad_sucursal_id || '') === String(f.entidad_sucursal_id || '')) ? ' selected' : '';
                    var texto = r.entidad_nombre + (r.entidad_fantasia ? ' - ' + r.entidad_fantasia : '') + (r.sucursal_nombre ? ' / ' + r.sucursal_nombre : ' / Sin Sucursal');
                    html += '<option value="' + r.entidad_id + '" data-sucursal-id="' + (r.entidad_sucursal_id || '') + '"' + sel + '>' + texto + '</option>';
                });
                $('#factura_entidad_id').html(html);
                $('#factura_entidad_sucursal_id').val(f.entidad_sucursal_id || '');
                mostrarCampoConsumidorFinal(!f.entidad_id);
                $('#factura_consumidor_final_nombre').val(f.consumidor_final_nombre || '');
                $('#factura_condicion_pago_id').html('<option value="' + (f.condicion_pago_id || '') + '">' + (f.condicion_pago || 'Sin condición') + '</option>');
                $('#factura_f_emision').val(f.f_emision);
                $('#factura_f_vto').attr('min', f.f_emision || '').val(f.f_vto || f.f_emision || '');
                $('#factura_observaciones').val(f.observaciones || '');
                $('#factura_destino_bien_uso').prop('checked', parseInt(f.destino_bien_uso || 0, 10) === 1);
                $('#factura_comprobante_nro').val(f.comprobante_nro > 0 ? f.comprobante_nro : (parseInt(f.tabla_estado_registro_id, 10) === 4 ? 'Lo asigna ARCA con el CAE' : 'Se asigna al confirmar'));
                $('#factura_cae_info').html(f.CAE ? '<i class="fas fa-shield-alt me-1 text-success"></i>CAE <strong>' + esc(f.CAE) + '</strong>' + (f.f_vto_CAE ? ' · vence ' + esc(f.f_vto_CAE.split('-').reverse().join('/')) : '') : '');
                $.getJSON('ventas_facturas_ajax.php', { accion: 'condicion_cliente', entidad_id: f.entidad_id || 0, empresa_idx: EMPRESA_ID }, mostrarCondicionCliente);

                function finalizarApertura() {
                    habilitarFormulario(!readonly);
                    $('#modalFacturaCliente .modal-title').html('<i class="fas fa-file-invoice-dollar me-2"></i>' + (readonly ? 'Ver Factura' : 'Editar Factura') + ' ' + (f.comprobante_tipo || '') + ' -' + (f.comprobante_nro || f.venta_factura_id));
                    renderAccionesExtraModal(f.botones, readonly ? 'visualizar' : 'editar');
                    var tabBtn = document.getElementById(tabInicial === 'pagos' ? 'tab-pagos-btn' : 'tab-factura-btn');
                    if (tabBtn) bootstrap.Tab.getOrCreateInstance(tabBtn).show();
                    cargarAsientoContable(f.venta_factura_id, readonly);
                    cargarCobroFactura(f.venta_factura_id);
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
                $.getJSON('ventas_facturas_ajax.php', { accion: 'remitos_pendientes', empresa_idx: EMPRESA_ID, entidad_id: f.entidad_id || 0, entidad_sucursal_id: f.entidad_sucursal_id || 0, excluir_factura_id: facturaEditId }, function (pendientes) {
                    // El precio de una línea de remito se puede cambiar en la factura: al reabrirla,
                    // el precio y descuento guardados pisan los del remito.
                    var guardadoPorRemito = {};
                    (f.detalles || []).forEach(function (d) { if (d.venta_remito_detalle_id) guardadoPorRemito[d.venta_remito_detalle_id] = d; });
                    (pendientes || []).forEach(function (r) {
                        var d = guardadoPorRemito[r.venta_remito_detalle_id];
                        if (d) { r.precio_unitario_bruto = parseFloat(d.precio_unitario) || 0; r.descuento_general_pct = parseFloat(d.descuento_general_pct) || 0; }
                    });
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
        if (accionJs === 'cobrar') { bootstrap.Tab.getOrCreateInstance(document.getElementById('tab-pagos-btn')).show(); return; }
        if (accionJs === 'imprimir') { imprimirFactura(id); return; }
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

    // Comprobante imprimible (formato ARCA, con QR + CAE si es fiscal autorizado) en otra pestaña.
    function imprimirFactura(id) {
        window.open('ventas_facturas_print.php?venta_factura_id=' + encodeURIComponent(id) + '&empresa_idx=' + EMPRESA_ID, '_blank');
    }

    function ejecutarAccionFactura(id, accionJs, comprobanteInfo, cerrarModal) {
        // Aprobar una factura fiscal puede pedir el CAE a ARCA (si está habilitado en Parámetros de
        // Empresa): puede tardar varios segundos.
        var esArca = accionJs === 'aprobada';
        if (esArca) Swal.fire({ title: 'Aprobando...', text: comprobanteInfo + ': si corresponde, se solicita el CAE a ARCA. No cierre la ventana.', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
        $.ajax({ url: 'ventas_facturas_ajax.php', type: 'POST', dataType: 'json', timeout: esArca ? 200000 : 0, data: { accion: 'ejecutar_accion', venta_factura_id: id, accion_js: accionJs, empresa_idx: EMPRESA_ID, pagina_idx: PAGINA_ID } }).done(function (res) {
            if (res && res.success) {
                if (cerrarModal) bootstrap.Modal.getInstance(document.getElementById('modalFacturaCliente')).hide();
                tabla.ajax.reload(null, false);
                if (res.notas_credito_disponibles) ofrecerNotasCredito(id, res.notas_credito_disponibles);
                else if (res.cae) Swal.fire({ icon: 'success', title: 'Factura autorizada por ARCA', text: res.message });
                else Swal.fire({ icon: 'success', title: '¡Listo!', text: res.message || (comprobanteInfo + ' actualizada.'), toast: true, position: 'top-end', showConfirmButton: false, timer: 4000 });
            } else {
                Swal.fire(esArca ? 'No se pudo aprobar la factura' : 'Error', (res && res.error) || 'No se pudo ejecutar la acción.', 'error');
            }
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'No se pudo ejecutar la acción.';
            if (esArca) msg += ' Si ARCA llegó a autorizarla, al volver a aprobar se recupera el CAE sin duplicarlo.';
            Swal.fire('Error', msg, 'error');
        });
    }

    $(document).on('click', '.btn-accion-factura', function () {
        var id = $(this).data('id');
        var accionJs = $(this).data('accion');
        var confirmable = $(this).data('confirmable');
        var comprobanteInfo = $(this).data('comprobante') || ('Factura #' + id);

        if (accionJs === 'editar') { cargarFacturaParaEditar(id, false); return; }
        if (accionJs === 'visualizar') { cargarFacturaParaEditar(id, true); return; }
        if (accionJs === 'cobrar') { cargarFacturaParaEditar(id, true, 'pagos'); return; }
        if (accionJs === 'imprimir') { imprimirFactura(id); return; }

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

    function cargarAsientoContable(facturaId, readonly) {
        $('#badgeAsientoEstado').addClass('d-none');
        $('#btnGenerarAsientoManual').hide();
        $.getJSON('ventas_facturas_ajax.php', { accion: 'obtener_asiento', venta_factura_id: facturaId, empresa_idx: EMPRESA_ID }, function (res) {
            if (res && res.success && res.data) {
                var a = res.data;
                $('#badgeAsientoEstado').removeClass('d-none').text('Asiento #' + a.cont_asiento_id);
                $('#asientoIdSpan').text(a.cont_asiento_id);
                $('#asientoTipoSpan').text(a.cont_tipo_asiento || 'Automático');
                $('#asientoEstadoSpan').text(a.estado_registro || 'Registrado');
                $('#asientoFechaSpan').text(a.f_asiento ? a.f_asiento.split('-').reverse().join('/') : '-');
                $('#asientoDescripcionSpan').text(a.descripcion || '');

                var filasHtml = '';
                (a.detalles || []).forEach(function (d) {
                    var debe = parseFloat(d.debe) || 0;
                    var haber = parseFloat(d.haber) || 0;
                    filasHtml += '<tr>' +
                        '<td class="font-monospace text-muted">' + (d.cuenta_codigo || '-') + '</td>' +
                        '<td class="fw-semibold">' + (d.cuenta_nombre || ('Cuenta ' + d.cuenta_id)) + '</td>' +
                        '<td class="text-end font-monospace ' + (debe > 0 ? 'fw-bold text-success' : 'text-muted') + '">' + (debe > 0 ? '$' + moneda(debe) : '-') + '</td>' +
                        '<td class="text-end font-monospace ' + (haber > 0 ? 'fw-bold text-danger' : 'text-muted') + '">' + (haber > 0 ? '$' + moneda(haber) : '-') + '</td>' +
                        '<td class="small text-muted">' + (d.descripcion || '') + '</td>' +
                    '</tr>';
                });
                $('#tablaAsientoDetalles tbody').html(filasHtml);
                var totDebe = parseFloat(a.total_debe) || 0;
                var totHaber = parseFloat(a.total_haber) || 0;
                $('#asientoTotalDebe').text('$' + moneda(totDebe));
                $('#asientoTotalHaber').text('$' + moneda(totHaber));
                var diff = Math.abs(totDebe - totHaber);
                if (diff < 0.01) {
                    $('#asientoBalanceBadge').html('<span class="badge bg-success"><i class="fas fa-check me-1"></i>Balanceado</span>');
                } else {
                    $('#asientoBalanceBadge').html('<span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>Desbalanceado: $' + moneda(diff) + '</span>');
                }

                $('#contenedorAsientoVacio').hide();
                $('#contenedorAsientoDetalle').show();
            } else {
                $('#contenedorAsientoDetalle').hide();
                $('#contenedorAsientoVacio').show();
                if (readonly) {
                    $('#btnGenerarAsientoManual').show();
                }
            }
        });
    }

    $(document).on('click', '#btnGenerarAsientoManual, #btnRefrescarAsiento', function () {
        if (!facturaEditId) return;
        var btn = $(this);
        btn.prop('disabled', true);
        $.post('ventas_facturas_ajax.php', {
            accion: 'generar_asiento_contable',
            venta_factura_id: facturaEditId,
            empresa_idx: EMPRESA_ID
        }, function (res) {
            btn.prop('disabled', false);
            if (res && res.success) {
                Swal.fire({ icon: 'success', title: 'Asiento Contable', text: res.message || 'Asiento generado correctamente.', toast: true, position: 'top-end', showConfirmButton: false, timer: 2500 });
                cargarAsientoContable(facturaEditId, true);
            } else {
                Swal.fire('Error', (res && res.error) || 'No se pudo generar el asiento contable.', 'error');
            }
        }, 'json').fail(function () {
            btn.prop('disabled', false);
            Swal.fire('Error', 'Error al comunicarse con el servidor.', 'error');
        });
    });

    // ===== Solapa "Medios de Pago": recibo (RCX) con punto de venta imputado a la factura =====
    // Mismos medios y campos que cobranzas.js; el servidor arma y confirma la cobranza
    // (vfEmitirReciboFactura) y la factura pasa a Cancelado parcial / total según el saldo.
    var cobroMaestros = null;
    var cobroInfo = null;

    function cargarCobroMaestros(cb) {
        if (cobroMaestros) { cb(); return; }
        $.getJSON('ventas_facturas_ajax.php', { accion: 'cobro_maestros', empresa_idx: EMPRESA_ID }, function (res) {
            if (!res || !res.success) { Swal.fire('Error', (res && (res.message || res.error)) || 'No se pudieron cargar los medios de pago.', 'error'); return; }
            cobroMaestros = res.data;
            cb();
        });
    }

    function resetCobro(motivo) {
        cobroInfo = null;
        $('#cobroTotalFactura, #cobroCobrado, #cobroSaldo').text('$0,00');
        $('#badgeEstadoPago').text('Pendiente de Pago');
        $('#cobroFormulario').hide();
        $('#tablaCobroMedios tbody').empty();
        $('#cobroMotivo').text(motivo || '').toggle(!!motivo);
        $('#tablaCobrosAplicados tbody').html('<tr><td colspan="5" class="text-muted text-center">Sin cobros aplicados.</td></tr>');
    }

    function cargarCobroFactura(facturaId) {
        resetCobro('');
        $.getJSON('ventas_facturas_ajax.php', { accion: 'cobro_factura', venta_factura_id: facturaId, empresa_idx: EMPRESA_ID, pagina_idx: PAGINA_ID }, function (res) {
            if (!res || !res.success) { resetCobro((res && (res.message || res.error)) || 'No se pudo obtener el estado de cobro de la factura.'); return; }
            var c = cobroInfo = res.data;
            $('#cobroTotalFactura').text('$' + moneda(c.importe_total));
            $('#cobroCobrado').text('$' + moneda(c.cobrado));
            $('#cobroSaldo').text('$' + moneda(c.saldo));
            $('#badgeEstadoPago').text(c.saldo <= 0.001 ? 'Cobrada' : (c.cobrado > 0.001 ? 'Cobro Parcial' : 'Pendiente de Pago'));
            renderCobrosAplicados(c.imputaciones);
            $('#cobroMotivo').text(c.motivo || '').toggle(!c.puede_cobrar && !!c.motivo);
            if (!c.puede_cobrar) return;
            cargarCobroMaestros(function () {
                var pvs = cobroMaestros.puntos_venta || [];
                if (!pvs.length) { $('#cobroMotivo').text('No hay puntos de venta habilitados para emitir recibos (o el usuario no tiene acceso a ninguno).').show(); return; }
                // Por defecto, un PV de recibos de la misma sucursal que la factura.
                var pvDefecto = pvs.filter(function (p) { return parseInt(p.sucursal_id, 10) === c.sucursal_id; })[0] || pvs[0];
                $('#cobro_punto_venta_id').html(pvs.map(function (p) {
                    return '<option value="' + p.punto_venta_id + '" data-sucursal-id="' + (p.sucursal_id || '') + '"' + (p === pvDefecto ? ' selected' : '') + '>'
                        + esc((p.sucursal_nombre ? p.sucursal_nombre + ' - ' : '') + (p.boca_nombre ? p.boca_nombre + ' - ' : '') + p.nombre)
                        + (p.codigo_fiscal ? ' (' + esc(p.codigo_fiscal) + ')' : '') + '</option>';
                }).join(''));
                $('#cobro_f_emision').val(new Date().toISOString().slice(0, 10));
                $('#cobro_observaciones').val('');
                cargarTiposRecibo();
                agregarFilaCobro(c.saldo);
                $('#cobroFormulario').show();
            });
        });
    }

    function renderCobrosAplicados(rows) {
        if (!rows || !rows.length) return;
        $('#tablaCobrosAplicados tbody').html(rows.map(function (r) {
            var nro = r.comprobante_nro > 0 ? String(r.comprobante_pv).padStart(4, '0') + '-' + String(r.comprobante_nro).padStart(8, '0') : 'Borrador';
            var anulada = r.codigo_estandar === 'ANULADO' || r.codigo_estandar === 'ELIMINADO';
            return '<tr' + (anulada ? ' class="text-muted text-decoration-line-through"' : '') + '>'
                + '<td>' + esc((r.comprobante_tipo || r.comprobante_codigo || '') + ' ' + nro) + '</td>'
                + '<td>' + esc(r.f_imputacion ? r.f_imputacion.split('-').reverse().join('/') : '') + '</td>'
                + '<td>' + esc(r.medios || '-') + '</td>'
                + '<td class="text-end">$' + moneda(r.importe_imputado) + '</td>'
                + '<td>' + esc(r.estado_registro || '') + '</td></tr>';
        }).join(''));
    }

    function cargarTiposRecibo() {
        var pv = $('#cobro_punto_venta_id').val();
        $('#cobro_comprobante_tipo_id').html('<option value="">Cargando...</option>');
        $.getJSON('ventas_facturas_ajax.php', { accion: 'cobro_tipos_por_punto_venta', punto_venta_id: pv, empresa_idx: EMPRESA_ID }, function (res) {
            var tipos = (res && res.success) ? res.data : [];
            $('#cobro_comprobante_tipo_id').html(tipos.length ? tipos.map(function (t) {
                return '<option value="' + t.comprobante_tipo_id + '">' + esc(t.comprobante_tipo) + '</option>';
            }).join('') : '<option value="">Sin tipos de recibo en este punto de venta</option>');
        });
        // La caja de efectivo depende de la sucursal del punto de venta
        $('#tablaCobroMedios tbody tr').each(function () { renderCamposMedioCobro($(this)); });
    }
    $('#cobro_punto_venta_id').on('change', cargarTiposRecibo);

    function agregarFilaCobro(importe) {
        var opciones = (cobroMaestros.medios_pago || []).map(function (m) {
            return '<option value="' + m.medio_pago_id + '" data-tipo="' + esc(m.medio_pago_tipo) + '">' + esc(m.medio_pago) + '</option>';
        }).join('');
        var tr = $('<tr>'
            + '<td><select class="form-select form-select-sm cobro-medio">' + opciones + '</select></td>'
            + '<td class="cobro-campos"></td>'
            + '<td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm text-end cobro-importe" value="' + (importe > 0 ? Number(importe).toFixed(2) : '') + '"></td>'
            + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger cobro-quitar" title="Quitar"><i class="fas fa-times"></i></button></td>'
            + '</tr>');
        $('#tablaCobroMedios tbody').append(tr);
        renderCamposMedioCobro(tr);
        recalcularTotalCobro();
    }

    function opcionesCuentas(tipo, sucursalId) {
        return (cobroMaestros.cuentas_tesoreria || []).filter(function (c) {
            return c.cuenta_tipo === tipo && (tipo !== 'CAJA' || !c.sucursal_id || parseInt(c.sucursal_id, 10) === sucursalId);
        }).map(function (c) { return '<option value="' + c.tesoreria_cuenta_id + '">' + esc(c.tesoreria_cuenta) + '</option>'; }).join('');
    }

    function renderCamposMedioCobro(tr) {
        var tipo = tr.find('.cobro-medio option:selected').data('tipo');
        var sucursalId = parseInt($('#cobro_punto_venta_id option:selected').data('sucursal-id'), 10) || 0;
        var html = '';
        if (tipo === 'EFECTIVO') {
            var cajas = opcionesCuentas('CAJA', sucursalId);
            html = '<select class="form-select form-select-sm cobro-cuenta">' + (cajas || '<option value="">Sin caja en la sucursal del punto de venta</option>') + '</select>';
        } else if (tipo === 'TRANSFERENCIA') {
            html = '<div class="row g-1"><div class="col-sm-6"><select class="form-select form-select-sm cobro-cuenta">' + opcionesCuentas('BANCO', sucursalId) + '</select></div>'
                + '<div class="col-sm-6"><input type="text" class="form-control form-control-sm cobro-nro-operacion" placeholder="N° Operación"></div></div>';
        } else if (tipo === 'CHEQUE_TERCERO') {
            var bancos = (cobroMaestros.bancos || []).map(function (b) { return '<option value="' + b.banco_id + '">' + esc(b.banco) + '</option>'; }).join('');
            html = '<div class="row g-1"><div class="col-sm-4"><select class="form-select form-select-sm cobro-cheque-banco"><option value="">-- Banco --</option>' + bancos + '</select></div>'
                + '<div class="col-sm-2"><input type="text" class="form-control form-control-sm cobro-cheque-numero" placeholder="N° Cheque"></div>'
                + '<div class="col-sm-3"><input type="date" class="form-control form-control-sm cobro-cheque-fpago" title="Fecha de pago"></div>'
                + '<div class="col-sm-3"><input type="text" class="form-control form-control-sm cobro-cheque-librador" placeholder="Librador"></div></div>';
        } else if (tipo === 'RETENCION') {
            var imps = (cobroMaestros.impuestos_retencion || []).map(function (i) { return '<option value="' + i.impuesto_tipo_id + '">' + esc(i.impuesto_tipo) + '</option>'; }).join('');
            html = '<div class="row g-1"><div class="col-sm-4"><select class="form-select form-select-sm cobro-ret-tipo"><option value="">-- Impuesto --</option>' + imps + '</select></div>'
                + '<div class="col-sm-3"><input type="text" class="form-control form-control-sm cobro-ret-cert" placeholder="N° Certificado"></div>'
                + '<div class="col-sm-3"><input type="number" step="0.01" class="form-control form-control-sm cobro-ret-base" placeholder="Base Imp."></div>'
                + '<div class="col-sm-2"><input type="number" step="0.01" class="form-control form-control-sm cobro-ret-ali" placeholder="%"></div></div>';
        }
        tr.find('.cobro-campos').html(html);
    }

    function totalMediosCobro() {
        var t = 0;
        $('#tablaCobroMedios .cobro-importe').each(function () { t += parseFloat(this.value) || 0; });
        return Math.round(t * 100) / 100;
    }

    function recalcularTotalCobro() {
        var total = totalMediosCobro();
        var saldo = cobroInfo ? cobroInfo.saldo : 0;
        var dif = Math.round((saldo - total) * 100) / 100;
        $('#cobroTotalMedios').text('$' + moneda(total));
        if (dif > 0.001) $('#cobroDiferencia').attr('class', 'text-muted').text('Queda pendiente: $' + moneda(dif));
        else if (dif < -0.001) $('#cobroDiferencia').attr('class', 'text-danger fw-semibold').text('Supera el saldo en $' + moneda(-dif) + ' (los anticipos se cargan desde Cobranzas)');
        else $('#cobroDiferencia').attr('class', 'text-success').text('Cancela el total del saldo.');
    }

    $('#btnCobroAgregarMedio').on('click', function () {
        agregarFilaCobro(Math.max(0, Math.round(((cobroInfo ? cobroInfo.saldo : 0) - totalMediosCobro()) * 100) / 100));
    });
    $('#btnCobroCompletarSaldo').on('click', function () {
        var ultima = $('#tablaCobroMedios .cobro-importe').last();
        if (!ultima.length) return;
        var resto = (cobroInfo ? cobroInfo.saldo : 0) - (totalMediosCobro() - (parseFloat(ultima.val()) || 0));
        ultima.val(Math.max(0, resto).toFixed(2));
        recalcularTotalCobro();
    });
    $(document).on('change', '#tablaCobroMedios .cobro-medio', function () { renderCamposMedioCobro($(this).closest('tr')); });
    $(document).on('input', '#tablaCobroMedios .cobro-importe', recalcularTotalCobro);
    $(document).on('click', '#tablaCobroMedios .cobro-quitar', function () { $(this).closest('tr').remove(); recalcularTotalCobro(); });

    function recopilarMediosCobro() {
        var medios = [], error = null;
        $('#tablaCobroMedios tbody tr').each(function (i) {
            var tr = $(this), tipo = tr.find('.cobro-medio option:selected').data('tipo');
            var m = { medio_pago_id: parseInt(tr.find('.cobro-medio').val(), 10) || 0, importe: parseFloat(tr.find('.cobro-importe').val()) || 0 };
            var fila = 'Fila ' + (i + 1) + ': ';
            if (m.importe <= 0) { error = fila + 'el importe debe ser mayor a cero.'; return false; }
            if (tipo === 'EFECTIVO' || tipo === 'TRANSFERENCIA') {
                m.tesoreria_cuenta_id = parseInt(tr.find('.cobro-cuenta').val(), 10) || null;
                if (!m.tesoreria_cuenta_id) { error = fila + 'seleccione la ' + (tipo === 'EFECTIVO' ? 'caja' : 'cuenta bancaria') + '.'; return false; }
                if (tipo === 'TRANSFERENCIA') m.nro_operacion = $.trim(tr.find('.cobro-nro-operacion').val());
            } else if (tipo === 'CHEQUE_TERCERO') {
                m.cheque = { banco_id: parseInt(tr.find('.cobro-cheque-banco').val(), 10) || 0, numero: $.trim(tr.find('.cobro-cheque-numero').val()), f_pago: tr.find('.cobro-cheque-fpago').val(), librador_nombre: $.trim(tr.find('.cobro-cheque-librador').val()) };
                if (!m.cheque.banco_id || !m.cheque.numero || !m.cheque.f_pago) { error = fila + 'complete banco, número y fecha de pago del cheque.'; return false; }
            } else if (tipo === 'RETENCION') {
                m.impuesto_tipo_id = parseInt(tr.find('.cobro-ret-tipo').val(), 10) || 0;
                m.retencion_certificado_nro = $.trim(tr.find('.cobro-ret-cert').val());
                m.retencion_base_imponible = parseFloat(tr.find('.cobro-ret-base').val()) || 0;
                m.retencion_alicuota = parseFloat(tr.find('.cobro-ret-ali').val()) || 0;
                if (!m.impuesto_tipo_id || !m.retencion_certificado_nro) { error = fila + 'complete el impuesto y el número de certificado.'; return false; }
            }
            medios.push(m);
        });
        if (!error && !medios.length) error = 'Agregue al menos un medio de pago.';
        return error ? { error: error } : { medios: medios };
    }

    $('#btnEmitirRecibo').on('click', function () {
        if (!cobroInfo || !facturaEditId) return;
        var r = recopilarMediosCobro();
        if (r.error) { Swal.fire('Atención', r.error, 'warning'); return; }
        var total = totalMediosCobro();
        if (total > cobroInfo.saldo + 0.001) { Swal.fire('Atención', 'El total de los medios supera el saldo de la factura. Los anticipos se registran desde Cobranzas.', 'warning'); return; }
        if (!$('#cobro_comprobante_tipo_id').val()) { Swal.fire('Atención', 'El punto de venta elegido no tiene tipos de recibo habilitados.', 'warning'); return; }
        Swal.fire({
            title: '¿Emitir recibo?',
            html: 'Se emitirá un recibo por <strong>$' + moneda(total) + '</strong> imputado a<br><strong>' + esc(cobroInfo.comprobante) + '</strong>.<br><small class="text-muted">Genera los movimientos de tesorería y el asiento contable.</small>',
            icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, emitir', cancelButtonText: 'Cancelar', reverseButtons: true, allowOutsideClick: false
        }).then(function (result) {
            if (!result.isConfirmed) return;
            var btn = $('#btnEmitirRecibo').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Emitiendo...');
            $.post('ventas_facturas_ajax.php', {
                accion: 'emitir_recibo', empresa_idx: EMPRESA_ID, pagina_idx: PAGINA_ID,
                venta_factura_id: facturaEditId,
                comprobante_pv: $('#cobro_punto_venta_id').val(),
                comprobante_tipo_id: $('#cobro_comprobante_tipo_id').val(),
                f_emision: $('#cobro_f_emision').val(),
                observaciones: $('#cobro_observaciones').val(),
                medios: JSON.stringify(r.medios)
            }, function (res) {
                if (!res || !res.success) { Swal.fire('Error', (res && (res.message || res.error)) || 'No se pudo emitir el recibo.', 'error'); return; }
                Swal.fire({ icon: 'success', title: 'Recibo emitido', text: res.message || 'El recibo se emitió correctamente.' });
                tabla.ajax.reload(null, false);
                // Recarga la factura: cambia su estado (Cancelado parcial / total) y sus botones.
                cargarFacturaParaEditar(facturaEditId, true, 'pagos');
            }, 'json').fail(function (xhr) {
                Swal.fire('Error', 'No se pudo emitir el recibo: ' + ((xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'error de servidor.'), 'error');
            }).always(function () { btn.prop('disabled', false).html('<i class="fas fa-receipt me-1"></i>Emitir Recibo'); });
        });
    });

    cargarClientes();
    cargarBotonAgregar();
    if (REMITO_ID_INICIAL) abrirFacturaDesdeRemito(REMITO_ID_INICIAL);
    if (FACTURA_ID_INICIAL) cargarFacturaParaEditar(FACTURA_ID_INICIAL, FACTURA_ACCION_INICIAL === 'visualizar');
});
