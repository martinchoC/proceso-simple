/**
 * Remito rápido desde la consulta de productos.
 *
 * El vendedor agrega productos al carrito con el botón "+" de cada fila, elige cliente
 * (o consumidor final) y genera el remito: se crea, se numera, descuenta stock y queda
 * pendiente de facturar. Todo el cálculo de precios lo hace el servidor; acá solo se
 * muestra. Endpoint: productos_consulta_remito_ajax.php
 */
(function ($) {
    'use strict';

    var URL_AJAX = 'productos_consulta_remito_ajax.php';
    // Un carrito por usuario y empresa: en una PC compartida, o al cambiar de empresa en la misma
    // pestaña, nadie ve el carrito de otro.
    var STORAGE_KEY = 'remito_rapido_carrito_v2_' + (parseInt(window.REMITO_RAPIDO_USUARIO_ID, 10) || 0)
        + '_' + (parseInt(window.EMPRESA_ID, 10) || 0);

    var carrito = []; // [{producto_id, codigo, nombre, cantidad}]
    var contexto = null; // {puntos_venta, fecha}
    var cliente = { entidad_id: 0, nombre: '' }; // entidad_id 0 = consumidor final
    var precios = {}; // producto_id -> fila de precios del servidor
    var errorPrecios = '';
    var timerCliente = null;
    var timerPrecios = null;
    var enviando = false;

    // ---------- utilidades ----------
    function esc(s) {
        return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function fmt(n) {
        return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function post(accion, data) {
        data = data || {};
        data.accion = accion;
        data.empresa_idx = window.EMPRESA_ID;
        return $.ajax({ url: URL_AJAX, method: 'POST', data: data, dataType: 'json' });
    }

    function mensajeError(xhr) {
        var j = xhr && xhr.responseJSON;
        return (j && (j.error || j.message)) || 'No se pudo completar la operación';
    }

    function aviso(icono, titulo, texto) {
        if (window.Swal) {
            Swal.fire({ icon: icono, title: titulo, text: texto || '' });
        } else {
            window.alert(titulo + (texto ? '\n' + texto : ''));
        }
    }

    function guardar() {
        try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(carrito)); } catch (e) { /* sin storage: sigue en memoria */ }
    }

    function restaurar() {
        try {
            sessionStorage.removeItem('remito_rapido_carrito_v1');   // clave vieja, compartida entre usuarios
            var raw = sessionStorage.getItem(STORAGE_KEY);
            if (raw) {
                var arr = JSON.parse(raw);
                if (Array.isArray(arr)) carrito = arr;
            }
        } catch (e) { /* ignorar */ }
    }

    // ---------- API pública (la usa el botón de cada fila) ----------
    window.RemitoRapido = {
        agregar: function (productoId, codigo, nombre) {
            productoId = parseInt(productoId, 10);
            if (!productoId) return;
            var existente = carrito.filter(function (i) { return i.producto_id === productoId; })[0];
            if (existente) {
                existente.cantidad += 1;
            } else {
                carrito.push({ producto_id: productoId, codigo: codigo || '', nombre: nombre || '', cantidad: 1 });
            }
            guardar();
            actualizarBadge();
            if (window.toastr) toastr.success((nombre || 'Producto') + ' agregado al remito');
            else pulso();
            if ($('#modalRemitoRapido').hasClass('show')) { renderItems(); pedirPrecios(); }
        }
    };

    // ---------- UI base ----------
    function construirUI() {
        if ($('#btnRemitoRapido').length) return;

        $('<button type="button" id="btnRemitoRapido" class="btn btn-primary shadow" ' +
          'title="Remito rápido" style="position:fixed;right:20px;bottom:20px;z-index:1050;border-radius:50px;padding:12px 18px;">' +
          '<i class="fas fa-file-invoice"></i> Remito <span id="badgeRemitoRapido" class="badge bg-light text-dark ms-1">0</span>' +
          '</button>').appendTo('body');

        var html =
        '<div class="modal fade" id="modalRemitoRapido" tabindex="-1" aria-hidden="true">' +
        ' <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">' +
        '  <div class="modal-header">' +
        '   <h5 class="modal-title"><i class="fas fa-file-invoice"></i> Remito de salida</h5>' +
        '   <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>' +
        '  </div>' +
        '  <div class="modal-body">' +
        '   <div id="rrPanelNuevo">' +
        '    <div class="row g-3 mb-3">' +
        '     <div class="col-md-5">' +
        '      <label class="form-label fw-bold">Cliente</label>' +
        '      <div class="form-check form-check-inline ms-2">' +
        '       <input class="form-check-input" type="checkbox" id="rrConsFinal"><label class="form-check-label" for="rrConsFinal">Consumidor final</label>' +
        '      </div>' +
        '      <div id="rrBoxCliente" class="position-relative">' +
        '       <input type="text" class="form-control" id="rrBuscarCliente" placeholder="Buscar cliente (mín. 2 letras)" autocomplete="off">' +
        '       <div id="rrListaClientes" class="list-group position-absolute w-100 shadow" style="z-index:1060;max-height:240px;overflow:auto;display:none;"></div>' +
        '      </div>' +
        '      <div id="rrBoxCF" style="display:none;"><input type="text" class="form-control" id="rrNombreCF" maxlength="150" placeholder="Nombre del consumidor final"></div>' +
        '      <div id="rrClienteElegido" class="small text-success mt-1"></div>' +
        '     </div>' +
        '     <div class="col-md-4">' +
        '      <label class="form-label fw-bold" for="rrPV">Punto de venta / depósito</label>' +
        '      <select class="form-select" id="rrPV"></select>' +
        '     </div>' +
        '     <div class="col-md-3">' +
        '      <label class="form-label fw-bold">Fecha</label>' +
        '      <input type="text" class="form-control" id="rrFecha" readonly>' +
        '     </div>' +
        '    </div>' +
        '    <div class="table-responsive">' +
        '     <table class="table table-sm table-bordered align-middle">' +
        '      <thead class="table-light"><tr><th>Código</th><th>Producto</th><th style="width:110px" class="text-end">Cantidad</th>' +
        '       <th class="text-end" style="width:120px">Precio neto</th><th class="text-end" style="width:130px">Subtotal neto</th><th style="width:40px"></th></tr></thead>' +
        '      <tbody id="rrItems"></tbody>' +
        '      <tfoot><tr><td colspan="4" class="text-end">Neto</td><td class="text-end" id="rrNeto">$ 0,00</td><td></td></tr>' +
        '       <tr><td colspan="4" class="text-end">IVA</td><td class="text-end" id="rrIva">$ 0,00</td><td></td></tr>' +
        '       <tr class="fw-bold"><td colspan="4" class="text-end">Total de referencia</td><td class="text-end" id="rrTotal">$ 0,00</td><td></td></tr></tfoot>' +
        '     </table>' +
        '    </div>' +
        '    <div id="rrErrorPrecios" class="alert alert-warning py-2" style="display:none;"></div>' +
        '    <label class="form-label" for="rrObs">Observaciones</label>' +
        '    <textarea class="form-control" id="rrObs" rows="2" maxlength="500"></textarea>' +
        '    <div class="form-text">El precio sale de la lista del cliente y no se puede modificar. El remito queda pendiente de facturar.</div>' +
        '   </div>' +
        '  </div>' +
        '  <div class="modal-footer">' +
        '   <button type="button" class="btn btn-outline-secondary me-auto" id="rrVaciar"><i class="fas fa-trash"></i> Vaciar</button>' +
        '   <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>' +
        '   <button type="button" class="btn btn-success" id="rrGenerar"><i class="fas fa-check"></i> Generar remito</button>' +
        '  </div>' +
        ' </div></div></div>';
        $('body').append(html);
    }

    function pulso() {
        $('#btnRemitoRapido').addClass('btn-success').removeClass('btn-primary');
        setTimeout(function () { $('#btnRemitoRapido').addClass('btn-primary').removeClass('btn-success'); }, 400);
    }

    function actualizarBadge() {
        $('#badgeRemitoRapido').text(carrito.length);
    }

    // ---------- carrito ----------
    function renderItems() {
        var $tb = $('#rrItems').empty();
        if (!carrito.length) {
            $tb.append('<tr><td colspan="6" class="text-center text-muted py-3">Todavía no agregaste productos. Usá el botón + de la consulta.</td></tr>');
        }
        var neto = 0, iva = 0;
        carrito.forEach(function (it) {
            var p = precios[it.producto_id];
            var precioTxt = p ? '$ ' + fmt(p.precio_neto) : '<span class="text-muted">—</span>';
            var sub = p ? p.precio_neto * it.cantidad : 0;
            if (p) {
                neto += sub;
                iva += sub * (p.iva_porcentaje / 100);
            }
            $tb.append(
                '<tr data-id="' + it.producto_id + '">' +
                '<td>' + esc(it.codigo) + '</td>' +
                '<td>' + esc(it.nombre) + (p ? '' : ' <span class="badge bg-warning text-dark">sin precio</span>') + '</td>' +
                '<td><input type="number" class="form-control form-control-sm text-end rr-cant" min="0.01" step="any" value="' + esc(it.cantidad) + '"></td>' +
                '<td class="text-end">' + precioTxt + '</td>' +
                '<td class="text-end">' + (p ? '$ ' + fmt(sub) : '') + '</td>' +
                '<td><button type="button" class="btn btn-sm btn-outline-danger rr-quitar" title="Quitar"><i class="fas fa-times"></i></button></td>' +
                '</tr>');
        });
        $('#rrNeto').text('$ ' + fmt(neto));
        $('#rrIva').text('$ ' + fmt(iva));
        $('#rrTotal').text('$ ' + fmt(neto + iva));
        if (errorPrecios) $('#rrErrorPrecios').text(errorPrecios).show(); else $('#rrErrorPrecios').hide();
    }

    function pedirPrecios() {
        clearTimeout(timerPrecios);
        timerPrecios = setTimeout(function () {
            if (!carrito.length) { precios = {}; errorPrecios = ''; renderItems(); return; }
            post('precios', {
                entidad_id: cliente.entidad_id,
                producto_ids: JSON.stringify(carrito.map(function (i) { return i.producto_id; }))
            }).done(function (r) {
                precios = {};
                errorPrecios = '';
                (r.precios || []).forEach(function (p) { precios[p.producto_id] = p; });
                renderItems();
            }).fail(function (xhr) {
                precios = {};
                errorPrecios = mensajeError(xhr);
                var j = xhr.responseJSON;
                if (j && j.error) errorPrecios = j.error;
                renderItems();
            });
        }, 200);
    }

    // ---------- clientes ----------
    function elegirCliente(id, nombre) {
        cliente = { entidad_id: id, nombre: nombre };
        $('#rrListaClientes').hide().empty();
        $('#rrBuscarCliente').val('');
        $('#rrClienteElegido').html(id ? '<i class="fas fa-check"></i> ' + esc(nombre) : '');
        pedirPrecios();
    }

    function buscarClientes(q) {
        post('buscar_clientes', { q: q }).done(function (r) {
            var $l = $('#rrListaClientes').empty();
            (r.clientes || []).forEach(function (c) {
                var extra = c.entidad_fantasia && c.entidad_fantasia !== c.entidad_nombre ? ' <small class="text-muted">(' + esc(c.entidad_fantasia) + ')</small>' : '';
                $l.append('<button type="button" class="list-group-item list-group-item-action rr-cli" data-id="' + c.entidad_id + '" data-nombre="' + esc(c.entidad_nombre) + '">' +
                    esc(c.entidad_nombre) + extra + ' <small class="text-muted">' + esc(c.condicion_fiscal) + '</small></button>');
            });
            if (!(r.clientes || []).length) $l.append('<div class="list-group-item text-muted">Sin resultados</div>');
            $l.show();
        });
    }

    // ---------- contexto (PV, fecha) ----------
    function cargarContexto() {
        if (contexto) return $.Deferred().resolve().promise();
        return post('contexto').done(function (r) {
            contexto = r;
            var $pv = $('#rrPV').empty();
            (r.puntos_venta || []).forEach(function (pv) {
                var sinTipo = !pv.comprobante_tipo_id;
                $pv.append('<option value="' + pv.punto_venta_id + '"' + (sinTipo ? ' disabled' : '') + '>' +
                    esc(pv.punto_venta_nombre) + (sinTipo ? ' (sin tipo de remito)' : '') + '</option>');
            });
            if (!(r.puntos_venta || []).length) {
                $pv.append('<option value="">No hay puntos de venta con depósito</option>');
            }
            $('#rrFecha').val(String(r.fecha || '').split('-').reverse().join('/'));
        }).fail(function (xhr) {
            aviso('error', 'No se puede armar el remito', mensajeError(xhr));
        });
    }

    // ---------- acciones ----------
    function generar() {
        if (enviando) return;
        if (!carrito.length) return aviso('warning', 'El remito está vacío');
        var esCF = $('#rrConsFinal').is(':checked');
        var nombreCF = $.trim($('#rrNombreCF').val());
        if (!esCF && !cliente.entidad_id) return aviso('warning', 'Elegí un cliente');
        if (esCF && !nombreCF) return aviso('warning', 'Indicá el nombre del consumidor final');
        var pv = parseInt($('#rrPV').val(), 10);
        if (!pv) return aviso('warning', 'Elegí el punto de venta');
        var sinPrecio = carrito.filter(function (i) { return !precios[i.producto_id]; });
        if (sinPrecio.length) return aviso('warning', 'Hay productos sin precio en la lista del cliente', sinPrecio.map(function (i) { return i.nombre; }).join(', '));

        enviando = true;
        $('#rrGenerar').prop('disabled', true);
        post('crear', {
            entidad_id: esCF ? 0 : cliente.entidad_id,
            consumidor_final_nombre: esCF ? nombreCF : '',
            punto_venta_id: pv,
            observaciones: $('#rrObs').val(),
            items: JSON.stringify(carrito.map(function (i) { return { producto_id: i.producto_id, cantidad: i.cantidad }; }))
        }).done(function (r) {
            carrito = []; precios = {}; guardar(); actualizarBadge();
            $('#rrObs').val('');
            renderItems();
            // Remito generado: se cierra el modal y queda el aviso
            var modal = bootstrap.Modal.getInstance(document.getElementById('modalRemitoRapido'));
            if (modal) modal.hide();
            aviso(r.confirmado ? 'success' : 'warning', r.confirmado ? 'Remito emitido' : 'Remito en borrador', r.message);
        }).fail(function (xhr) {
            aviso('error', 'No se generó el remito', mensajeError(xhr));
        }).always(function () {
            enviando = false;
            $('#rrGenerar').prop('disabled', false);
        });
    }

    // ---------- eventos ----------
    function eventos() {
        $(document).on('click', '.btn-remito-add', function () {
            var $b = $(this);
            window.RemitoRapido.agregar($b.data('id'), $b.data('codigo'), $b.data('nombre'));
        });

        $('#btnRemitoRapido').on('click', function () {
            cargarContexto().always(function () {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRemitoRapido')).show();
                renderItems();
                pedirPrecios();
            });
        });

        $('#rrConsFinal').on('change', function () {
            var cf = $(this).is(':checked');
            $('#rrBoxCliente').toggle(!cf);
            $('#rrBoxCF').toggle(cf);
            cliente = { entidad_id: 0, nombre: '' };
            $('#rrClienteElegido').empty();
            pedirPrecios();
        });

        $('#rrBuscarCliente').on('input', function () {
            var q = $.trim($(this).val());
            clearTimeout(timerCliente);
            if (q.length < 2) { $('#rrListaClientes').hide().empty(); return; }
            timerCliente = setTimeout(function () { buscarClientes(q); }, 300);
        });
        $(document).on('click', '.rr-cli', function () {
            elegirCliente(parseInt($(this).data('id'), 10), String($(this).attr('data-nombre')));
        });

        $(document).on('change', '.rr-cant', function () {
            var id = parseInt($(this).closest('tr').data('id'), 10);
            var v = parseFloat($(this).val());
            carrito.forEach(function (i) { if (i.producto_id === id) i.cantidad = (v > 0 ? v : 1); });
            guardar(); renderItems();
        });
        $(document).on('click', '.rr-quitar', function () {
            var id = parseInt($(this).closest('tr').data('id'), 10);
            carrito = carrito.filter(function (i) { return i.producto_id !== id; });
            guardar(); actualizarBadge(); renderItems();
        });
        $('#rrVaciar').on('click', function () {
            carrito = []; precios = {}; guardar(); actualizarBadge(); renderItems();
        });
        $('#rrGenerar').on('click', generar);
    }

    $(function () {
        restaurar();
        construirUI();
        actualizarBadge();
        eventos();
    });
})(jQuery);
