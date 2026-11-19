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
                return esc(d.entidad_fantasia || d.entidad_nombre || '');
            } },
            { data: 'comprobante_tipo', className: 'text-center' },
            {
                data: 'f_emision',
                className: 'text-center',
                render: function (data, type) {
                    if (type === 'sort' || type === 'filter') return data || '';
                    if (type === 'export' || !data) return data || '';
                    return Fmt.fecha(data);
                }
            },
            { data: 'condicion_pago' },
            { data: 'importe_total', className: 'text-end', render: function (d) { return '$' + Fmt.numero(Number(d || 0), { minimumFractionDigits: 2 }); } },
            { data: 'CAE', className: 'text-center', defaultContent: '', render: function (d, type, row) {
                if (type !== 'display') return d || '';
                if (!d) return '<span class="text-muted">-</span>';
                var vto = row.f_vto_CAE ? Fmt.fecha(row.f_vto_CAE) : '';
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
            url: window.APP_ASSETS_URL + 'js/datatables-i18n-2.1.8/es-ES.json',
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

    function moneda(v) { return Fmt.numero(Number(v || 0), { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

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
                var emisionLabel = {
                    'WSFE': 'Web Service',
                    'RCEL': 'En Línea (RCEL)',
                    'FISCAL': 'Controlador Fiscal',
                    'MOVIL': 'Móvil',
                    'TALONARIO': 'Talonario',
                    'NO_FISCAL': 'Interno'
                }[r.sistema_emision] || r.sistema_emision || '';
                var fiscalPad = r.codigo_fiscal ? 'PV ' + String(r.codigo_fiscal).padStart(4, '0') : '';
                var subTxt = [fiscalPad, emisionLabel].filter(Boolean).join(' - ');
                var label = r.nombre + (subTxt ? ' (' + subTxt + ')' : '');
                html += '<option value="' + r.punto_venta_id + '" data-sucursal-id="' + r.sucursal_id + '" data-sucursal="' + (r.sucursal_nombre || '') + '" data-sistema-emision="' + (r.sistema_emision || 'WSFE') + '">' + label + '</option>';
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
            $('#condicionClienteInfo').html(badgeFiscal + ' &nbsp;|&nbsp; <strong>Precios:</strong> ' + esc(c.lista_precio_nombre || '-') + ' &nbsp;|&nbsp; <strong>Pago:</strong> ' + esc(c.condicion_pago || 'contado') + (c.canal ? ' &nbsp;|&nbsp; <strong>Canal:</strong> ' + esc(c.canal) : ''));
            return;
        }
        $('#condicionClienteInfo').html(badgeFiscal + ' &nbsp;|&nbsp; <strong>Lista De Precios:</strong> ' + (c.lista_precio_nombre || '-') + ' &nbsp;|&nbsp; <strong>Descuento:</strong> ' + descuentoGeneralCliente + '%' + (c.canal ? ' &nbsp;|&nbsp; <strong>Canal:</strong> ' + esc(c.canal) : ''));
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
            if (fecha) { var f = new Date(fecha + 'T00:00:00'); f.setDate(f.getDate() + dias); $('#factura_f_vto').val(Fmt.isoLocal(f)); }
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
    // Dos tablas con las mismas columnas: "A facturar" (cantidad > 0 y productos manuales) y
    // "Pendientes" (líneas de remito en 0, paginadas). La fila pasa de una a otra al cambiar la
    // cantidad (actualizarVisibilidadRemitos); data-orden conserva el orden original.
    var ordenFilasFactura = 0;
    // Las dos tablas tienen las mismas celdas; el CSS (ventas_facturas.php) oculta en "Pendientes" las
    // editables (.col-edit) y en "A facturar" el botón Agregar (.col-agregar).
    function tablaItemsFactura(tbodyId, clase) {
        return '<div class="table-responsive"><table class="table table-sm table-hover mb-0 factura-items ' + clase + '"><thead class="table-light"><tr>'
            + '<th>Remito</th><th>Código</th><th>Detalle</th><th class="text-end">Pend.</th><th class="text-end col-edit">Cantidad</th>'
            + '<th class="text-end col-edit">Precio lista</th><th class="text-end col-edit">Desc. %</th><th class="text-end">Precio neto</th>'
            + '<th class="text-end col-edit">Total neto</th><th class="col-edit"></th><th class="col-agregar"></th>'
            + '</tr></thead><tbody id="' + tbodyId + '"></tbody></table></div>';
    }
    function agregarFilaFactura(html) {
        var fila = $(html).attr('data-orden', ordenFilasFactura++);
        $('#tbodyItemsElegidos').append(fila);
        return fila;
    }
    function renderRemitos(rows) {
        var autoRemitoId = remitoParaAutoFacturar;
        ordenFilasFactura = 0;
        $('#contenedorRemitosFactura').html(
            '<div class="small fw-semibold text-primary mb-1"><i class="fas fa-check-circle me-1"></i>A facturar <span class="badge bg-primary ms-1" id="cantItemsElegidos">0</span></div>'
            + tablaItemsFactura('tbodyItemsElegidos', 'factura-items-elegidos')
            // Nota de crédito: ítems del comprobante asociado (renderItemsAsociado)
            + '<div id="itemsAsociadoFactura"></div>'
            // Pendientes plegados: se despliegan para elegir qué sumar (no en notas de crédito / débito)
            + '<details class="factura-pendientes mt-2' + (notaTipo ? ' d-none' : '') + '" id="detallesPendientesFactura"><summary class="small fw-semibold text-muted">'
            + '<i class="fas fa-truck me-1"></i>Pendientes de remitos del cliente <span class="badge bg-secondary ms-1" id="cantItemsPendientes">0</span>'
            + '<span class="fw-normal ms-2 factura-pendientes-hint">Ver y agregar</span>'
            + ' <span class="ui-ayuda" tabindex="0" role="button" data-bs-toggle="tooltip" data-bs-placement="bottom" aria-label="Ayuda" title="&quot;Agregar&quot; suma la línea con todo lo pendiente y pasa a &quot;A facturar&quot;; ahí se ajusta la cantidad. Con cantidad 0 vuelve acá."><i class="bi bi-question-circle"></i></span></summary>'
            + '<div class="mt-1">' + tablaItemsFactura('tbodyItemsPendientes', 'factura-items-pendientes')
            + '<div id="paginacionRemitosFactura" class="d-flex justify-content-end mt-2"></div></div></details>');
        (rows || []).forEach(function (r) {
            // Deep-link desde ventas_remitos.php: precarga (cantidad = pendiente) solo las
            // líneas del remito puntual que se vino a facturar, dejando el resto del cliente
            // visible pero sin marcar, por si también quiere sumarlas a la misma factura.
            if (autoRemitoId && Number(r.venta_remito_id) === Number(autoRemitoId)) {
                r = $.extend({}, r, { cantidad_propia: r.cantidad_pendiente_facturar });
            }
            $('#tbodyItemsPendientes').append($(filaItemFactura(r, false)).attr('data-orden', ordenFilasFactura++));
        });
        if (window.UiSimple && UiSimple.tooltips) UiSimple.tooltips('#contenedorRemitosFactura');
        inicializarPaginacionRemitos();
        renderItemsAsociado();
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
        var cantidadMax = manual ? (parseFloat(item.cantidad_max) || 999999) : (parseFloat(item.cantidad_pendiente_facturar || item.cantidad) || 0);
        var cantidadPendiente = parseFloat(item.cantidad_pendiente_facturar) || 0;
        var precio = parseFloat(item.precio_unitario_bruto || item.precio_unitario) || 0;
        // Mismo cálculo que el servidor (bruto * pct / 100): los productos manuales solo traen
        // descuento_general_pct (no un descuento_general ya calculado, que solo viene de remitos),
        // así que ese monto plano no sirve para mostrar la fila — recalculamos siempre desde el %.
        var descuentoPct = parseFloat(item.descuento_general_pct) || 0;
        var descuento = precio * descuentoPct / 100;
        var neto = Math.max(0, precio - descuento);
        var valorInicial = cantidadPropia > 0 ? cantidadPropia.toFixed(2) : '0.00';
        var nroRemito = manual ? '-' : esc(item.comprobante_nro || '-') + (item.consumidor_final_nombre ? ' <small class="text-muted">' + esc(item.consumidor_final_nombre) + '</small>' : '');
        var nombre = (item.producto_nombre || 'Producto manual') + (manual ? (item.etiqueta || ' (Manual)') : '');
        // Venta por presentación: la línea va en unidades, de a presentaciones enteras (el servidor lo valida)
        var esPres = VentaPres.esPres(item);
        if (esPres) nombre += ' · ' + item.presentacion_nombre + ' (' + Fmt.numero(parseFloat(item.presentacion_factor), 0) + ' u.)';
        var presAttrs = esPres ? ' data-pres-id="' + item.presentacion_id + '" data-pres-factor="' + item.presentacion_factor + '"' : '';
        var leyenda = item.leyenda || '';
        // Una línea por ítem: la leyenda va plegada detrás del ícono (resaltado si tiene texto)
        return '<tr><td>' + nroRemito + '</td><td>' + esc(item.producto_codigo || item.producto_id) + '</td>'
            + '<td class="col-detalle"><div class="d-flex align-items-center gap-1"><span class="text-truncate factura-item-nombre" title="' + esc(nombre) + '">' + esc(nombre) + '</span>'
            + '<button type="button" class="btn btn-link btn-sm p-0 ms-auto col-edit btn-leyenda-item ' + (leyenda ? 'text-primary' : 'text-muted') + '" title="' + (leyenda ? esc(leyenda) : 'Agregar leyenda (se imprime debajo del producto)') + '"><i class="fas fa-comment-dots"></i></button></div>'
            + '<textarea class="form-control form-control-sm mt-1 factura-leyenda d-none" rows="2" maxlength="1000" placeholder="Leyenda del ítem (opcional, se imprime debajo del producto)">' + esc(leyenda) + '</textarea></td>'
            + '<td class="text-end cantidad-pendiente">' + (manual ? '-' : Fmt.numero(cantidadPendiente, 2)) + '</td><td class="text-end col-edit"><div class="factura-cantidad-stepper"><button type="button" class="btn btn-sm btn-outline-secondary btn-cantidad-menos" tabindex="-1">&minus;</button><input type="number" class="form-control form-control-sm factura-cantidad no-spinner" data-key="' + key + '" data-remito-detalle-id="' + (manual ? '' : item.venta_remito_detalle_id) + '" data-producto-id="' + item.producto_id + '" data-pedido-detalle-id="' + (item.venta_pedido_detalle_id || '') + '" data-precio="' + precio + '" data-descuento-pct="' + descuentoPct + '" data-precio-neto="' + neto + '" data-iva-id="' + (item.iva_alicuota_id || 1) + '" data-iva="' + (item.iva_porcentaje || 0) + '"' + presAttrs + ' value="' + valorInicial + '" min="0" max="' + cantidadMax + '" step="' + (esPres ? item.presentacion_factor : 1) + '"><button type="button" class="btn btn-sm btn-outline-secondary btn-cantidad-mas" tabindex="-1">+</button></div></td>'
            + '<td class="text-end col-edit"><input type="number" class="form-control form-control-sm text-end factura-precio no-spinner" value="' + precio.toFixed(2) + '" min="0" step="0.01"></td>'
            // El importe del descuento va en el tooltip (factura-descuento-renglon), no en una segunda línea
            + '<td class="text-end col-edit"><div class="input-group input-group-sm flex-nowrap factura-descuento-renglon" title="Descuento $' + moneda(descuento) + '"><input type="number" class="form-control form-control-sm text-end factura-descuento-pct no-spinner" value="' + descuentoPct.toFixed(2) + '" min="0" max="100" step="0.01"><span class="input-group-text">%</span></div></td>'
            + '<td class="text-end factura-neto-renglon">$' + moneda(neto) + '</td><td class="text-end fw-bold total-neto-renglon col-edit">$0.00</td>'
            + '<td class="text-center col-edit"><button type="button" class="btn btn-sm btn-outline-danger btn-quitar-item" title="' + (manual ? 'Quitar el producto de la factura' : 'Quitar la línea de la factura (vuelve a quedar pendiente en el remito)') + '"><i class="fas fa-trash"></i></button></td>'
            + '<td class="text-end col-agregar"><button type="button" class="btn btn-sm btn-outline-primary btn-agregar-pendiente" title="Sumar a la factura con todo lo pendiente"><i class="fas fa-plus me-1"></i>Agregar</button></td></tr>';
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
            detalles.push({ key: key, producto_id: el.data('producto-id'), venta_remito_detalle_id: el.data('remito-detalle-id') || null, venta_pedido_detalle_id: el.data('pedido-detalle-id') || null, cantidad: valor, precio_unitario: parseFloat(el.data('precio')) || 0, descuento_general_pct: parseFloat(el.data('descuento-pct')) || 0, precio_unitario_neto: parseFloat(el.data('precio-neto')) || 0, iva_alicuota_id: parseInt(el.data('iva-id')) || 1, iva_porcentaje: parseFloat(el.data('iva')) || 0, leyenda: el.closest('tr').find('.factura-leyenda').val() || '' });
            // Venta por presentación: la línea lleva su presentación (el servidor recalcula desde las cajas)
            var presIdFila = parseInt(el.data('pres-id'), 10) || 0;
            if (presIdFila) {
                var nuevo = detalles[detalles.length - 1], factorFila = parseFloat(el.data('pres-factor')) || 1;
                nuevo.presentacion_id = presIdFila;
                nuevo.presentacion_factor = factorFila;
                nuevo.presentacion_cantidad = valor / factorFila;
                nuevo.presentacion_precio = nuevo.precio_unitario * factorFila;
            }
        }
        actualizarTotalNetoFila(el.closest('tr'));
        actualizarVisibilidadRemitos();
        renderDetalle();
    });
    // Leyenda del ítem: se guarda en su línea de `detalles` si ya está incluida (cantidad > 0).
    $(document).on('input', '.factura-leyenda', function () {
        var key = $(this).closest('tr').find('.factura-cantidad').data('key');
        var texto = this.value;
        detalles.forEach(function (d) { if (d.key === key) d.leyenda = texto; });
        $(this).closest('td').find('.btn-leyenda-item').toggleClass('text-primary', texto.trim() !== '').toggleClass('text-muted', texto.trim() === '')
            .attr('title', texto.trim() !== '' ? texto : 'Agregar leyenda (se imprime debajo del producto)');
    });
    // El ícono despliega / pliega la leyenda de la fila
    $(document).on('click', '.btn-leyenda-item', function () {
        var area = $(this).closest('td').find('.factura-leyenda').toggleClass('d-none');
        if (!area.hasClass('d-none')) area.trigger('focus');
    });
    // "Agregar" de un pendiente: la cantidad pasa a todo lo pendiente y la fila sube a "A facturar"
    $(document).on('click', '.btn-agregar-pendiente', function () {
        if (facturaSoloLectura) return;
        var input = $(this).closest('tr').find('.factura-cantidad');
        input.val(parseFloat(input.attr('max')) || 0).trigger('change');
    });
    // Precio y % de descuento editables en cualquier línea (de remito o manual) mientras la factura
    // está en Borrador: actualizan los data-* de la cantidad, que son los que arman `detalles`, y
    // recalculan descuento y neto de la fila.
    function recalcularPrecioFila(row) {
        var input = row.find('.factura-cantidad');
        var precio = Math.max(0, parseFloat(row.find('.factura-precio').val()) || 0);
        var pct = Math.max(0, Math.min(100, parseFloat(row.find('.factura-descuento-pct').val()) || 0));
        row.find('.factura-precio').val(precio.toFixed(2));
        row.find('.factura-descuento-pct').val(pct.toFixed(2));
        var descuento = precio * pct / 100;
        var neto = Math.max(0, precio - descuento);
        input.data('precio', precio).data('descuento-pct', pct).data('precio-neto', neto);
        row.find('.factura-descuento-renglon').attr('title', 'Descuento $' + moneda(descuento));
        row.find('.factura-neto-renglon').text('$' + moneda(neto));
        input.trigger('change');
    }
    $(document).on('change', '.factura-precio, .factura-descuento-pct', function () { recalcularPrecioFila($(this).closest('tr')); });
    // Quitar una línea: la manual se borra de la tabla; la de remito queda como candidata (cantidad 0),
    // así se puede volver a sumar. Se guarda recién con "Guardar" (el servidor reemplaza el detalle).
    $(document).on('click', '.btn-quitar-item', function () {
        if (facturaSoloLectura) return;
        var row = $(this).closest('tr');
        var input = row.find('.factura-cantidad');
        var key = String(input.data('key'));
        if (key.charAt(0) === 'm') {
            detalles = detalles.filter(function (d) { return String(d.key) !== key; });
            row.remove();
            actualizarVisibilidadRemitos();
            renderItemsAsociado();
            renderDetalle();
        } else {
            input.val('0.00').trigger('change');
        }
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
    // Ubica cada fila en su tabla: "A facturar" las de cantidad > 0 y las manuales (aunque estén en 0,
    // no vienen de un remito); "Pendientes" el resto, paginadas. Las elegidas se ven siempre.
    var paginaRemitos = 1;
    function actualizarVisibilidadRemitos() {
        var porPagina = 10;
        var elegidas = $('#tbodyItemsElegidos'), pendientes = $('#tbodyItemsPendientes');
        if (!elegidas.length) return;
        var filas = $('#contenedorRemitosFactura tbody tr').not('.fila-vacia');
        var porOrden = function (a, b) { return (parseInt($(a).attr('data-orden'), 10) || 0) - (parseInt($(b).attr('data-orden'), 10) || 0); };
        var aElegidas = [], aPendientes = [];
        filas.each(function () {
            var input = $(this).find('.factura-cantidad');
            var manual = String(input.data('key')).charAt(0) === 'm';
            ((parseFloat(input.val()) || 0) > 0 || manual ? aElegidas : aPendientes).push(this);
        });
        $('#contenedorRemitosFactura .fila-vacia').remove();
        // Solo se mueven las que cambiaron de tabla (mover la fila del input con foco lo desenfoca)
        if (aElegidas.some(function (f) { return f.parentNode !== elegidas[0]; })) elegidas.append(aElegidas.sort(porOrden));
        if (aPendientes.some(function (f) { return f.parentNode !== pendientes[0]; })) pendientes.append(aPendientes.sort(porOrden));
        $(aElegidas).show();
        if (!aElegidas.length) elegidas.append('<tr class="fila-vacia"><td colspan="11" class="text-muted small text-center py-2">Todavía no hay ítems. Agregá líneas de los pendientes o un producto.</td></tr>');
        if (!aPendientes.length) pendientes.append('<tr class="fila-vacia"><td colspan="11" class="text-muted small text-center py-2">No hay remitos pendientes de facturar.</td></tr>');
        $('#cantItemsElegidos').text(aElegidas.length);
        $('#cantItemsPendientes').text(aPendientes.length);
        var candidatas = $(aPendientes);
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
    $('#factura_f_emision').val(Fmt.hoyISO());
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
        // Venta por presentación: el precio de la presentación elegida (la caja entera)
        if (p._oferta) $('#factura_producto_precio').val((parseFloat(p._oferta.precio) || 0).toFixed(2));
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
            // Venta por presentación: una opción por forma de venta (unidad / caja); sin ofertas, como siempre
            var expandidos = [];
            productosEncontrados.forEach(function (p) {
                var ofs = VentaPres.ofertas(p);
                if (!ofs.length) { expandidos.push(p); return; }
                ofs.forEach(function (of) { expandidos.push($.extend({}, p, { _oferta: of })); });
            });
            productosEncontrados = expandidos;
            $('#factura_productos_resultados').html(productosEncontrados.map(function (p, i) {
                return '<button type="button" class="list-group-item list-group-item-action" data-index="' + i + '">'
                    + '<strong>' + $('<div>').text(p.producto_codigo || '').html() + '</strong> - ' + $('<div>').text(p.producto_nombre || '').html()
                    + (p._oferta ? ' <span class="badge bg-secondary">' + esc(VentaPres.textoOferta(p._oferta)) + '</span>' : '')
                    + (p.en_lista === false && !(p._oferta && p._oferta.presentacion_id) ? ' <span class="badge bg-warning text-dark ms-1" title="No tiene precio vigente en la lista del cliente: entra con precio 0">Sin precio en la lista</span>' : '')
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
        // Venta por presentación: la cantidad y el precio son de la presentación; la línea va en unidades
        if (p._oferta && parseInt(p._oferta.presentacion_id, 10) > 0) {
            var fPres = parseFloat(p._oferta.factor) || 1;
            $.extend(item, { presentacion_id: parseInt(p._oferta.presentacion_id, 10), presentacion_nombre: p._oferta.nombre, presentacion_factor: fPres,
                cantidad: qty * fPres, cantidad_propia: qty * fPres, precio_unitario: item.precio_unitario / fPres });
        }
        var fila = agregarFilaFactura(filaItemFactura(item, true));
        inicializarPaginacionRemitos();
        fila.find('.factura-cantidad').trigger('change');
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
        if (prvFactura && !facturaSoloLectura) prvFactura.programar();
    }
    // ========== REGLAS DE PRECIO DE VENTA (escalas y promociones; doc 22, paso 4e) ==========
    // Con window.PRV_ACTIVO, los productos manuales (sin remito ni pedido) toman el descuento automático que calcula
    // el servidor; el manual que el usuario tipeó encima se conserva. Las líneas de remito traen su descuento.
    var prvFactura = (window.PrvPantalla && window.PRV_ACTIVO) ? PrvPantalla.crear({
        url: 'ventas_facturas_ajax.php',
        contexto: function () { return { empresa_idx: EMPRESA_ID, entidad_id: $('#factura_entidad_id').val() || 0, sucursal_id: $('#factura_sucursal_id').val() || 0, fecha: $('#factura_f_emision').val() || '' }; },
        lineas: function () {
            return detalles.filter(function (d) { return !d.venta_remito_detalle_id && !d.venta_pedido_detalle_id && !d.presentacion_id; })
                .map(function (d) { return { producto_id: d.producto_id, presentacion_id: 0, cantidad: parseFloat(d.cantidad) || 0, ref: d.key }; });
        },
        aplicar: function (key, res) {
            var input = $('.factura-cantidad').filter(function () { return String($(this).data('key')) === String(key); }).first();
            if (!input.length) return;
            var row = input.closest('tr');
            var actual = parseFloat(row.find('.factura-descuento-pct').val()) || 0;
            var pct = PrvPantalla.nuevoPct(actual, input.data('prv-auto'), res.auto_pct);
            input.data('prv-auto', parseFloat(res.auto_pct) || 0);
            row.find('.factura-descuento-pct').attr('title', PrvPantalla.etiqueta(res));
            if (Math.abs(pct - actual) > 0.005) {
                row.find('.factura-descuento-pct').val(pct.toFixed(2));
                recalcularPrecioFila(row);
            }
        }
    }) : null;


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
    // Con NC / ND no se ofrecen remitos pendientes; con NC, los ítems del comprobante asociado (renderItemsAsociado).
    var notaTipo = '';          // '' factura | 'NC' | 'ND'
    var itemsAsociado = [];     // ítems del comprobante asociado a la NC (vfItemsComprobanteAsociado)
    function aplicarModoNota(tipoNota) {
        notaTipo = tipoNota || '';
        $('#detallesPendientesFactura').toggleClass('d-none', !!notaTipo);
        if (notaTipo) {
            // Las líneas de remito no van en una nota: vuelven a pendientes (ocultos)
            $('#tbodyItemsElegidos .factura-cantidad').filter(function () { return String($(this).data('key')).charAt(0) === 'r' && (parseFloat(this.value) || 0) > 0; })
                .val('0.00').trigger('change');
        }
        if (notaTipo !== 'NC') itemsAsociado = [];
        renderItemsAsociado();
    }
    function cargarItemsAsociado(entidad) {
        var comp = $('#factura_comprobante_asociado_id').val();
        if (entidad === undefined || typeof entidad === 'object') entidad = entidadIdConsulta();
        itemsAsociado = [];
        if (notaTipo !== 'NC' || !comp) { renderItemsAsociado(); return; }
        $.getJSON('ventas_facturas_ajax.php', { accion: 'items_asociado', comprobante_id: comp, entidad_id: entidad || 0, excluir_factura_id: facturaEditId || 0, empresa_idx: EMPRESA_ID }, function (res) {
            itemsAsociado = (res && res.success) ? (res.data || []) : [];
            renderItemsAsociado();
        });
    }
    // Lista de los ítems del comprobante asociado con "Agregar": la línea pasa a "A facturar" con lo disponible
    // (se puede bajar la cantidad o el precio). Sin ítems elegidos, la NC puede ser un descuento general:
    // se agrega un concepto con el buscador de productos.
    function renderItemsAsociado() {
        var cont = $('#itemsAsociadoFactura');
        if (!cont.length) return;
        if (notaTipo !== 'NC') { cont.empty(); return; }
        var ayuda = ' <span class="ui-ayuda" tabindex="0" role="button" data-bs-toggle="tooltip" data-bs-placement="bottom" aria-label="Ayuda" title="Agregá los ítems que devuelve o acredita. Si es un descuento general, no elijas ninguno: agregá un concepto con el buscador de productos."><i class="bi bi-question-circle"></i></span>';
        if (!$('#factura_comprobante_asociado_id').val()) {
            cont.html('<div class="small text-muted mt-2"><i class="fas fa-info-circle me-1"></i>Elegí el comprobante asociado para ver sus ítems.' + ayuda + '</div>');
        } else {
            var elegidos = {};
            $('#tbodyItemsElegidos .factura-cantidad').each(function () { elegidos[String($(this).data('producto-id'))] = true; });
            var filas = itemsAsociado.map(function (it, i) {
                var disp = parseFloat(it.cantidad_disponible) || 0;
                var ya = !!elegidos[String(it.producto_id)];
                return '<tr><td>' + esc(it.producto_codigo || it.producto_id) + '</td><td class="col-detalle"><span class="text-truncate d-block" title="' + esc(it.producto_nombre || '') + '">' + esc(it.producto_nombre || '') + '</span></td>'
                    + '<td class="text-end">' + Fmt.numero(parseFloat(it.cantidad) || 0, 2) + '</td><td class="text-end">' + Fmt.numero(disp, 2) + '</td>'
                    + '<td class="text-end">$' + moneda(parseFloat(it.precio_unitario) || 0) + '</td><td class="text-end">' + Fmt.numero(parseFloat(it.descuento_general_pct) || 0, 2) + ' %</td>'
                    + '<td class="text-end">' + (ya ? '<span class="small text-success"><i class="fas fa-check me-1"></i>Agregado</span>'
                        : '<button type="button" class="btn btn-sm btn-outline-primary btn-agregar-item-asociado" data-i="' + i + '"' + (disp > 0 ? '' : ' disabled title="Ya acreditado en otra nota de crédito"') + '><i class="fas fa-plus me-1"></i>Agregar</button>') + '</td></tr>';
            }).join('');
            cont.html('<details class="factura-pendientes mt-2" open><summary class="small fw-semibold text-muted"><i class="fas fa-file-invoice me-1"></i>Ítems del comprobante asociado <span class="badge bg-secondary ms-1">' + itemsAsociado.length + '</span>' + ayuda + '</summary>'
                + '<div class="table-responsive mt-1"><table class="table table-sm table-hover mb-0 factura-items factura-items-asociado"><thead class="table-light"><tr><th>Código</th><th>Detalle</th><th class="text-end">Facturado</th><th class="text-end">Disponible</th><th class="text-end">Precio lista</th><th class="text-end">Desc.</th><th></th></tr></thead><tbody>'
                + (filas || '<tr><td colspan="7" class="text-muted small text-center py-2">El comprobante no tiene ítems.</td></tr>') + '</tbody></table></div></details>');
        }
        if (window.UiSimple && UiSimple.tooltips) UiSimple.tooltips(cont[0]);
    }
    $(document).on('click', '.btn-agregar-item-asociado', function () {
        if (facturaSoloLectura) return;
        var it = itemsAsociado[parseInt($(this).data('i'), 10)];
        if (!it) return;
        var disp = parseFloat(it.cantidad_disponible) || 0;
        var fila = agregarFilaFactura(filaItemFactura({ key: 'fd' + it.venta_factura_detalle_id, producto_id: it.producto_id, producto_codigo: it.producto_codigo,
            producto_nombre: it.producto_nombre, etiqueta: '', cantidad_propia: disp, cantidad_max: disp, precio_unitario: parseFloat(it.precio_unitario) || 0,
            descuento_general_pct: parseFloat(it.descuento_general_pct) || 0, iva_alicuota_id: it.iva_alicuota_id || 1, iva_porcentaje: parseFloat(it.porcentaje_iva) || 0,
            leyenda: it.leyenda || '' }, true));
        fila.find('.factura-cantidad').trigger('change');
        renderItemsAsociado();
    });
    $(document).on('change', '#factura_comprobante_asociado_id', cargarItemsAsociado);

    function actualizarComprobanteAsociado(seleccionado, entidadForzada) {
        var tipo = $('#factura_comprobante_tipo_id').val();
        var entidad = entidadForzada !== undefined ? entidadForzada : entidadIdConsulta();
        var wrap = $('#factura_asociado_wrap'), sel = $('#factura_comprobante_asociado_id');
        var sinAsociado = function () { wrap.addClass('d-none'); sel.html('<option value="">Sin comprobante asociado</option>'); };
        if (!tipo) { sinAsociado(); aplicarModoNota(''); return; }
        // Consumidor final (entidad 0): igual se consulta si el tipo es nota, pero sin comprobantes para asociar
        $.getJSON('ventas_facturas_ajax.php', { accion: 'comprobantes_asociables', entidad_id: entidad || 0, comprobante_tipo_id: tipo, empresa_idx: EMPRESA_ID }, function (res) {
            if (!res || !res.success || !res.es_nota) { sinAsociado(); aplicarModoNota(''); return; }
            aplicarModoNota(res.es_credito ? 'NC' : 'ND');
            if (!entidad || String(entidad) === '0') { sinAsociado(); return; }
            var html = '<option value="">Sin comprobante asociado</option>';
            (res.data || []).forEach(function (c) {
                var nro = String(c.comprobante_pv).padStart(4, '0') + '-' + String(c.comprobante_nro).padStart(8, '0');
                var s = String(c.comprobante_id) === String(seleccionado || '') ? ' selected' : '';
                html += '<option value="' + c.comprobante_id + '"' + s + '>' + esc(c.comprobante_tipo) + ' ' + nro + ' · ' + esc(Fmt.fecha(c.f_emision))
                      + ' · total $' + moneda(c.importe_total) + ' · saldo $' + moneda(c.importe_pendiente) + '</option>';
            });
            sel.html(html).prop('disabled', $('#factura_comprobante_tipo_id').prop('disabled'));
            wrap.removeClass('d-none');
            cargarItemsAsociado(entidad);
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
        $('#btnAgregarProductoFactura, .factura-cantidad, .factura-precio, .factura-descuento-pct, .btn-cantidad-mas, .btn-cantidad-menos, .btn-quitar-item, .btn-agregar-pendiente').prop('disabled', !habilitar);
        $('#btnGuardarFactura').toggle(habilitar);
    }

    // Deja el modal listo para un alta nueva (formulario en blanco, cards en su estado normal).
    // La separación de cargarClientes() la maneja cada llamador: el botón normal solo necesita
    // poblar el combo, pero el deep-link desde un remito necesita saber cuándo terminó para
    // poder preseleccionar el cliente correcto.
    function abrirFacturaNueva() {
        facturaEditId = null;
        remitoParaAutoFacturar = null;
        notaTipo = ''; itemsAsociado = [];
        facturaSoloLectura = false;
        percepcionesFactura = []; percepcionesAvisos = [];
        habilitarFormulario(true);
        $('#modalFacturaCliente .modal-title').html('<i class="fas fa-file-invoice-dollar me-2"></i>Nueva Factura De Cliente');
        $('#formFacturaCliente')[0].reset();
        $('#factura_cae_info').empty();
        $('#factura_asociado_wrap').addClass('d-none');
        $('#factura_comprobante_asociado_id').html('<option value="">Sin comprobante asociado</option>');
        mostrarCampoConsumidorFinal(false);
        $('#factura_f_emision').val(Fmt.hoyISO());
        sincronizarVencimiento();
        descuentoGeneralCliente = 0;
        $('#contenedorRemitosFactura').html('<div class="text-muted small">Seleccione un cliente.</div>');
        $('#cardAgregarProductoManual').show();
        $('#tituloCardRemitosFactura').text('Ítems De La Factura');
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
    // Recargar: el ícono gira y el botón queda deshabilitado hasta que llegan los datos (sin eso no se
    // notaba que había recargado); delegado por si el encabezado de la card se vuelve a armar.
    $(document).on('click', '#btnRecargar', function () {
        var btn = $(this);
        if (btn.prop('disabled')) return;
        btn.prop('disabled', true).find('i').addClass('fa-spin');
        tabla.one('xhr.dt', function (e, settings, json, xhr) {
            btn.prop('disabled', false).find('i').removeClass('fa-spin');
            if (!json && xhr && xhr.status !== 200) Swal.fire('Error', 'No se pudo actualizar el listado (HTTP ' + xhr.status + ').', 'error');
        });
        tabla.ajax.reload(null, false);
    });
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
            html += '<tr><td>' + (d.remito_comprobante_nro || '-') + '</td><td>' + (d.producto_codigo || d.producto_id) + '</td><td>' + (d.producto_nombre || '') + (d.leyenda ? '<small class="d-block text-muted" style="white-space: pre-line;">' + esc(d.leyenda) + '</small>' : '') + '</td>'
                + '<td class="text-end">' + Fmt.numero((parseFloat(d.cantidad) || 0), 2) + '</td>'
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
            notaTipo = ''; itemsAsociado = [];
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
                $('#factura_cae_info').html(f.CAE ? '<i class="fas fa-shield-alt me-1 text-success"></i>CAE <strong>' + esc(f.CAE) + '</strong>' + (f.f_vto_CAE ? ' · vence ' + esc(Fmt.fecha(f.f_vto_CAE)) : '') : '');
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
                $('#tituloCardRemitosFactura').text('Ítems De La Factura');
                $.getJSON('ventas_facturas_ajax.php', { accion: 'remitos_pendientes', empresa_idx: EMPRESA_ID, entidad_id: f.entidad_id || 0, entidad_sucursal_id: f.entidad_sucursal_id || 0, excluir_factura_id: facturaEditId }, function (pendientes) {
                    // El precio de una línea de remito se puede cambiar en la factura: al reabrirla,
                    // el precio y descuento guardados pisan los del remito.
                    var guardadoPorRemito = {};
                    (f.detalles || []).forEach(function (d) { if (d.venta_remito_detalle_id) guardadoPorRemito[d.venta_remito_detalle_id] = d; });
                    (pendientes || []).forEach(function (r) {
                        var d = guardadoPorRemito[r.venta_remito_detalle_id];
                        if (d) { r.precio_unitario_bruto = parseFloat(d.precio_unitario) || 0; r.descuento_general_pct = parseFloat(d.descuento_general_pct) || 0; r.leyenda = d.leyenda || ''; }
                    });
                    renderRemitos(pendientes);
                    (f.detalles || []).forEach(function (d) {
                        if (!d.venta_remito_detalle_id) {
                            var item = { key: d.venta_factura_detalle_id, producto_id: d.producto_id, producto_codigo: d.producto_codigo, producto_nombre: d.producto_nombre, cantidad_propia: parseFloat(d.cantidad) || 0, precio_unitario: parseFloat(d.precio_unitario) || 0, descuento_general_pct: parseFloat(d.descuento_general_pct) || 0, iva_alicuota_id: d.iva_alicuota_id || 1, iva_porcentaje: parseFloat(d.porcentaje_iva) || 0, leyenda: d.leyenda || '' };
                            agregarFilaFactura(filaItemFactura(item, true));
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

    // ===== Cobro de contado a consumidor final =====
    // Al aprobar una factura sin cliente se emite su recibo de contado: antes se piden los medios
    // de pago (efectivo, transferencia, cheque...), que tienen que sumar el total de la factura.
    var contadoInfo = null;

    function recalcularContado() {
        var total = totalMediosCobro($('#tablaContadoMedios'));
        var dif = Math.round(((contadoInfo ? contadoInfo.total : 0) - total) * 100) / 100;
        $('#contadoTotalMedios').text('$' + moneda(total));
        if (dif > 0.001) $('#contadoDiferencia').attr('class', 'text-danger').text('Falta cobrar $' + moneda(dif));
        else if (dif < -0.001) $('#contadoDiferencia').attr('class', 'text-danger').text('Supera el total en $' + moneda(-dif));
        else $('#contadoDiferencia').attr('class', 'text-success').text('Cubre el total de la factura.');
    }

    function pedirMediosContado(info, comprobanteInfo) {
        contadoInfo = info;
        return Swal.fire({
            title: 'Cobro de contado',
            html: '<p class="mb-2">' + esc(comprobanteInfo) + ' a consumidor final por <strong>$' + moneda(info.total) + '</strong>. ¿Cómo pagó?</p>'
                + '<div class="table-responsive text-start"><table class="table table-sm align-middle mb-2" id="tablaContadoMedios">'
                + '<thead><tr><th style="width:28%">Medio</th><th>Datos</th><th style="width:18%" class="text-end">Importe</th><th style="width:40px"></th></tr></thead><tbody></tbody></table></div>'
                + '<div class="d-flex justify-content-between align-items-start">'
                + '<button type="button" class="btn btn-sm btn-outline-primary" id="btnContadoAgregarMedio"><i class="fas fa-plus me-1"></i>Agregar medio</button>'
                + '<div class="text-end"><div>Total medios: <strong id="contadoTotalMedios">$0,00</strong></div><small id="contadoDiferencia"></small></div></div>',
            width: 960, showCancelButton: true, confirmButtonText: 'Aprobar y cobrar', cancelButtonText: 'Cancelar',
            reverseButtons: true, allowOutsideClick: false, focusConfirm: false,
            didOpen: function () {
                var tabla = $('#tablaContadoMedios');
                var tr = agregarFilaCobro(info.total, tabla);
                var efectivo = tr.find('.cobro-medio option[data-tipo="EFECTIVO"]').first();
                if (efectivo.length) { tr.find('.cobro-medio').val(efectivo.val()); renderCamposMedioCobro(tr); }
                $('#btnContadoAgregarMedio').on('click', function () {
                    agregarFilaCobro(Math.max(0, Math.round((info.total - totalMediosCobro(tabla)) * 100) / 100), tabla);
                });
            },
            preConfirm: function () {
                var tabla = $('#tablaContadoMedios');
                var r = recopilarMediosCobro(tabla);
                if (r.error) { Swal.showValidationMessage(r.error); return false; }
                if (Math.abs(totalMediosCobro(tabla) - info.total) > 0.001) {
                    Swal.showValidationMessage('Los medios tienen que sumar el total de la factura ($' + moneda(info.total) + ').');
                    return false;
                }
                return r.medios;
            }
        });
    }

    function ejecutarAccionFactura(id, accionJs, comprobanteInfo, cerrarModal) {
        if (accionJs !== 'aprobada') { enviarAccionFactura(id, accionJs, comprobanteInfo, cerrarModal, null); return; }
        $.getJSON('ventas_facturas_ajax.php', { accion: 'cobro_contado_info', venta_factura_id: id, empresa_idx: EMPRESA_ID, pagina_idx: PAGINA_ID }, function (res) {
            if (!res || !res.success) { Swal.fire('Error', (res && (res.message || res.error)) || 'No se pudo leer la factura.', 'error'); return; }
            if (!res.data.requiere) { enviarAccionFactura(id, accionJs, comprobanteInfo, cerrarModal, null); return; }
            // El modal de la factura retiene el foco: se cierra antes de pedir los medios
            if (cerrarModal) bootstrap.Modal.getInstance(document.getElementById('modalFacturaCliente')).hide();
            cargarCobroMaestros(function () {
                pedirMediosContado(res.data, comprobanteInfo).then(function (r) {
                    if (r.isConfirmed && r.value) enviarAccionFactura(id, accionJs, comprobanteInfo, false, r.value);
                });
            });
        }).fail(function () { Swal.fire('Error', 'Error al comunicarse con el servidor.', 'error'); });
    }

    function enviarAccionFactura(id, accionJs, comprobanteInfo, cerrarModal, mediosContado) {
        // Aprobar una factura fiscal puede pedir el CAE a ARCA (si está habilitado en Parámetros de
        // Empresa): puede tardar varios segundos.
        var esArca = accionJs === 'aprobada';
        if (esArca) Swal.fire({ title: 'Aprobando...', text: comprobanteInfo + ': si corresponde, se solicita el CAE a ARCA. No cierre la ventana.', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
        var datos = { accion: 'ejecutar_accion', venta_factura_id: id, accion_js: accionJs, empresa_idx: EMPRESA_ID, pagina_idx: PAGINA_ID };
        if (mediosContado) datos.medios_contado = JSON.stringify(mediosContado);
        $.ajax({ url: 'ventas_facturas_ajax.php', type: 'POST', dataType: 'json', timeout: esArca ? 200000 : 0, data: datos }).done(function (res) {
            if (res && res.success) {
                if (cerrarModal) bootstrap.Modal.getInstance(document.getElementById('modalFacturaCliente')).hide();
                tabla.ajax.reload(null, false);
                if (res.notas_credito_disponibles) ofrecerNotasCredito(id, res.notas_credito_disponibles);
                else if (res.cae) Swal.fire({ icon: 'success', title: 'Factura autorizada por ARCA', text: res.message });
                else Swal.fire({ icon: 'success', title: '¡Listo!', text: res.message || (comprobanteInfo + ' actualizada.'), toast: true, position: 'top-end', showConfirmButton: false, timer: 4000 });
            } else {
                // El rechazo de ARCA viene en renglones (último autorizado, esta factura, motivo)
                Swal.fire({ icon: 'error', title: esArca ? 'No se pudo aprobar la factura' : 'Error', text: (res && res.error) || 'No se pudo ejecutar la acción.', width: esArca ? 640 : undefined, didOpen: function (p) { var h = p.querySelector('.swal2-html-container'); if (h) { h.style.whiteSpace = 'pre-line'; if (h.textContent.indexOf('\n') >= 0) h.style.textAlign = 'left'; } } });
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
                $('#asientoFechaSpan').text(a.f_asiento ? Fmt.fecha(a.f_asiento) : '-');
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
                $('#cobro_f_emision').val(Fmt.hoyISO());
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
                + '<td>' + esc(r.f_imputacion ? Fmt.fecha(r.f_imputacion) : '') + '</td>'
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

    // tabla: #tablaCobroMedios (solapa Medios de Pago, por defecto) o #tablaContadoMedios (cobro de
    // contado a consumidor final al aprobar, sin retenciones).
    function esTablaContado(tabla) { return tabla.is('#tablaContadoMedios'); }

    function agregarFilaCobro(importe, tabla) {
        tabla = tabla || $('#tablaCobroMedios');
        var contado = esTablaContado(tabla);
        var opciones = (cobroMaestros.medios_pago || []).filter(function (m) {
            return !contado || m.medio_pago_tipo !== 'RETENCION';
        }).map(function (m) {
            return '<option value="' + m.medio_pago_id + '" data-tipo="' + esc(m.medio_pago_tipo) + '" data-impuesto="' + (parseInt(m.impuesto_tipo_id, 10) || '') + '">' + esc(m.medio_pago) + '</option>';
        }).join('');
        var tr = $('<tr>'
            + '<td><select class="form-select form-select-sm cobro-medio">' + opciones + '</select></td>'
            + '<td class="cobro-campos"></td>'
            + '<td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm text-end cobro-importe" value="' + (importe > 0 ? Number(importe).toFixed(2) : '') + '"></td>'
            + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger cobro-quitar" title="Quitar"><i class="fas fa-times"></i></button></td>'
            + '</tr>');
        tabla.find('tbody').append(tr);
        renderCamposMedioCobro(tr);
        recalcularMedios(tabla);
        return tr;
    }

    function recalcularMedios(tabla) {
        if (esTablaContado(tabla)) recalcularContado(); else recalcularTotalCobro();
    }

    function opcionesCuentas(tipo, sucursalId) {
        return (cobroMaestros.cuentas_tesoreria || []).filter(function (c) {
            return c.cuenta_tipo === tipo && (tipo !== 'CAJA' || !c.sucursal_id || parseInt(c.sucursal_id, 10) === sucursalId);
        }).map(function (c) { return '<option value="' + c.tesoreria_cuenta_id + '">' + esc(c.tesoreria_cuenta) + '</option>'; }).join('');
    }

    function renderCamposMedioCobro(tr) {
        var tipo = tr.find('.cobro-medio option:selected').data('tipo');
        // Caja de efectivo: la de la sucursal del recibo (en el cobro de contado, la de la factura)
        var sucursalId = esTablaContado(tr.closest('table'))
            ? (contadoInfo ? contadoInfo.sucursal_id : 0)
            : (parseInt($('#cobro_punto_venta_id option:selected').data('sucursal-id'), 10) || 0);
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
            // El impuesto lo fija el medio (Retención IVA / Ganancias / IIBB); IIBB pide la provincia
            var impMedio = parseInt(tr.find('.cobro-medio option:selected').data('impuesto'), 10) || 0;
            var imps = (cobroMaestros.impuestos_retencion || []).map(function (i) {
                return '<option value="' + i.impuesto_tipo_id + '" data-iibb="' + (parseInt(i.es_iibb, 10) || 0) + '"' + (parseInt(i.impuesto_tipo_id, 10) === impMedio ? ' selected' : '') + '>' + esc(i.impuesto_tipo) + '</option>';
            }).join('');
            var jurs = (cobroMaestros.jurisdicciones || []).map(function (j) { return '<option value="' + j.jurisdiccion_id + '">' + esc(j.jurisdiccion_nombre) + '</option>'; }).join('');
            html = '<div class="row g-1"><div class="col-sm-3"><select class="form-select form-select-sm cobro-ret-tipo"' + (impMedio ? ' disabled' : '') + '><option value="">-- Impuesto --</option>' + imps + '</select></div>'
                + '<div class="col-sm-3 cobro-ret-jur-col d-none"><select class="form-select form-select-sm cobro-ret-jur"><option value="">-- Provincia --</option>' + jurs + '</select></div>'
                + '<div class="col-sm-2"><input type="text" class="form-control form-control-sm cobro-ret-cert" placeholder="N° Certificado"></div>'
                + '<div class="col-sm-2"><input type="number" step="0.01" class="form-control form-control-sm cobro-ret-base" placeholder="Base Imp."></div>'
                + '<div class="col-sm-2"><input type="number" step="0.01" class="form-control form-control-sm cobro-ret-ali" placeholder="%"></div></div>';
        }
        tr.find('.cobro-campos').html(html);
        if (tipo === 'RETENCION') mostrarJurisdiccionRetencion(tr);
    }

    function mostrarJurisdiccionRetencion(tr) {
        var esIibb = parseInt(tr.find('.cobro-ret-tipo option:selected').data('iibb'), 10) === 1;
        tr.find('.cobro-ret-jur-col').toggleClass('d-none', !esIibb);
        if (!esIibb) tr.find('.cobro-ret-jur').val('');
    }

    function totalMediosCobro(tabla) {
        var t = 0;
        (tabla || $('#tablaCobroMedios')).find('.cobro-importe').each(function () { t += parseFloat(this.value) || 0; });
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
    $(document).on('change', '#tablaCobroMedios .cobro-medio, #tablaContadoMedios .cobro-medio', function () { renderCamposMedioCobro($(this).closest('tr')); });
    $(document).on('change', '#tablaCobroMedios .cobro-ret-tipo', function () { mostrarJurisdiccionRetencion($(this).closest('tr')); });
    $(document).on('input', '#tablaCobroMedios .cobro-importe, #tablaContadoMedios .cobro-importe', function () { recalcularMedios($(this).closest('table')); });
    $(document).on('click', '#tablaCobroMedios .cobro-quitar, #tablaContadoMedios .cobro-quitar', function () {
        var tabla = $(this).closest('table');
        $(this).closest('tr').remove();
        recalcularMedios(tabla);
    });

    function recopilarMediosCobro(tabla) {
        var medios = [], error = null;
        (tabla || $('#tablaCobroMedios')).find('tbody tr').each(function (i) {
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
                if (parseInt(tr.find('.cobro-ret-tipo option:selected').data('iibb'), 10) === 1) {
                    m.jurisdiccion_id = parseInt(tr.find('.cobro-ret-jur').val(), 10) || 0;
                    if (!m.jurisdiccion_id) { error = fila + 'seleccione la provincia de la retención de Ingresos Brutos.'; return false; }
                }
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
    if (FACTURA_ID_INICIAL) cargarFacturaParaEditar(FACTURA_ID_INICIAL, FACTURA_ACCION_INICIAL !== 'editar', FACTURA_ACCION_INICIAL === 'cobrar' ? 'pagos' : undefined);
});
