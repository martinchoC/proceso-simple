/**
 * Mostrador (doc 29): templates/mostrador/mostrador_vista.php + mostrador_ajax.php (core/ventas_mostrador/).
 * Lector de códigos: el lector USB / Bluetooth escribe el código y Enter en el campo (siempre con foco); con la cámara,
 * BarcodeDetector. Un código de una presentación agrega 1 caja; el de un producto, 1 unidad. Precios, descuentos y
 * totales los calcula el servidor (valorizar); acá solo producto, presentación y cantidad. "Cobrar y facturar":
 * factura de venta confirmada y aprobada con los medios de contado (consumidor final) o a la cuenta del cliente.
 */
(function () {
    'use strict';
    var raiz = document.getElementById('vmos');
    if (!raiz || !window.VMOS) return;
    var C = window.VMOS;
    var $ = function (sel) { return raiz.querySelector(sel); };
    var estado = {items: {}, entidad: 0, pv: 0, total: 0, inicio: null, medios: []};
    var timer = null;

    function plata(v) { return window.Fmt ? Fmt.moneda(v) : '$ ' + (Number(v) || 0).toFixed(2); }
    function esc(t) { return String(t === null || t === undefined ? '' : t).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; }); }
    function aviso(msg, tipo) {
        if (window.UiSimple) { tipo === 'error' ? UiSimple.error(msg, 'No se pudo completar') : (tipo === 'aviso' ? UiSimple.aviso(msg) : UiSimple.ok(msg)); }
        else { alert(msg); }
    }
    function api(accion, datos, metodo) {
        datos = datos || {};
        datos.empresa_idx = C.empresa;
        var url = C.ajax + '?accion=' + encodeURIComponent(accion);
        var op = {method: metodo || 'GET', credentials: 'same-origin', headers: {'Accept': 'application/json'}};
        if (op.method === 'GET') {
            Object.keys(datos).forEach(function (k) { url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(datos[k]); });
        } else {
            var fd = new FormData();
            Object.keys(datos).forEach(function (k) { fd.append(k, datos[k]); });
            op.body = fd;
            op.headers['X-CSRF-Token'] = C.csrf;
        }
        return fetch(url, op).then(function (r) { return r.json(); }, function () { throw new Error('Sin conexión con el servidor.'); })
            .then(function (j) { if (!j || !j.success) { var e = new Error((j && j.message) || 'No se pudo completar.'); e.data = j && j.data; throw e; } return j; });
    }
    function lista() {
        return Object.keys(estado.items).map(function (k) { var i = estado.items[k]; return {producto_id: i.producto_id, presentacion_id: i.presentacion_id, cantidad: i.cantidad}; })
            .filter(function (i) { return i.cantidad > 0; });
    }
    function foco() { var l = $('[data-vmos-lector]'); if (l && document.activeElement !== l && !document.activeElement.closest('[data-vmos-cobro], [data-vmos-cliente-q], [data-vmos-cf-nombre], select, input[type=number]')) l.focus(); }

    // ---------------------------------------------------------------- productos
    function buscar(q, directo) {
        q = (q || '').trim();
        var cont = $('[data-vmos-resultados]');
        if (q.length < 2) { cont.innerHTML = ''; return; }
        api('buscar', {q: q, entidad_id: estado.entidad, punto_venta_id: estado.pv}).then(function (j) {
            var ps = j.data.productos;
            if (directo && ps.length === 1) {
                var p = ps[0];
                var of = p.ofertas.find(function (o) { return (o.presentacion_id || 0) === (p.presentacion_escaneada || 0); }) || (p.ofertas.length === 1 ? p.ofertas[0] : null);
                if (of) { sumar(p, of); cont.innerHTML = ''; $('[data-vmos-lector]').value = ''; return; }
            }
            cont.innerHTML = ps.length ? ps.map(function (p, i) {
                return p.ofertas.map(function (o, k) {
                    return '<button type="button" class="list-group-item list-group-item-action vmos-resultado" data-p="' + i + '" data-o="' + k + '">'
                        + '<strong>' + esc(p.nombre) + '</strong> · ' + esc(o.nombre) + ' · ' + plata(o.precio_final)
                        + (o.minimo ? ' <small>mín. ' + esc(o.minimo) + '</small>' : '') + (o.disponible !== null ? ' <small class="text-muted">disp. ' + esc(o.disponible) + '</small>' : '')
                        + (o.etiqueta ? ' <span class="badge bg-success">' + esc(o.etiqueta) + '</span>' : '') + '</button>';
                }).join('');
            }).join('') : '<div class="text-muted small p-2">Sin productos con precio en la lista.</div>';
            cont.querySelectorAll('[data-p]').forEach(function (b) {
                b.addEventListener('click', function () { var p = ps[b.getAttribute('data-p')]; sumar(p, p.ofertas[b.getAttribute('data-o')]); cont.innerHTML = ''; $('[data-vmos-lector]').value = ''; foco(); });
            });
        }).catch(function (e) { aviso(e.message, 'error'); });
    }
    function sumar(p, of) {
        var pres = of.presentacion_id || 0;
        var clave = p.producto_id + '-' + pres;
        var it = estado.items[clave] || {producto_id: p.producto_id, presentacion_id: pres, cantidad: 0};
        it.cantidad = Math.round((Number(it.cantidad) + Math.max(1, Number(of.multiplo) || 1)) * 100) / 100;
        if (Number(of.minimo) > 0 && it.cantidad < Number(of.minimo)) it.cantidad = Number(of.minimo);
        estado.items[clave] = it;
        valorizar();
    }
    function valorizar() {
        clearTimeout(timer);
        timer = setTimeout(function () {
            var items = lista();
            if (!items.length) { pintar({lineas: [], cabecera: {subtotal: 0, impuestos: 0, total: 0}}); return; }
            api('valorizar', {items: JSON.stringify(items), entidad_id: estado.entidad, punto_venta_id: estado.pv}, 'POST')
                .then(function (j) { pintar(j.data); }).catch(function (e) { aviso(e.message, 'error'); });
        }, 200);
    }
    function pintar(v) {
        var tb = $('[data-vmos-lineas]');
        tb.innerHTML = v.lineas.length ? v.lineas.map(function (l) {
            var clave = l.producto_id + '-' + (l.presentacion_id || 0);
            return '<tr><td>' + esc(l.nombre) + (l.descuento_pct > 0 ? ' <small class="text-muted">−' + esc(l.descuento_pct) + ' %</small>' : '')
                + (l.etiqueta ? ' <span class="badge bg-success">' + esc(l.etiqueta) + '</span>' : '') + '</td>'
                + '<td class="text-center"><input type="number" class="form-control form-control-sm vmos-cant mx-auto" min="0" step="1" value="' + esc((estado.items[clave] || {}).cantidad) + '" data-k="' + clave + '"></td>'
                + '<td class="text-end">' + plata(l.total_linea) + '</td>'
                + '<td><button type="button" class="btn btn-sm btn-link text-danger" data-quitar="' + clave + '" title="Quitar">&times;</button></td></tr>';
        }).join('') : '<tr><td colspan="4" class="text-muted">Sin productos.</td></tr>';
        tb.querySelectorAll('[data-k]').forEach(function (inp) {
            inp.addEventListener('change', function () { var k = inp.getAttribute('data-k'); var n = Number(inp.value); if (n > 0) estado.items[k].cantidad = n; else delete estado.items[k]; valorizar(); });
        });
        tb.querySelectorAll('[data-quitar]').forEach(function (b) { b.addEventListener('click', function () { delete estado.items[b.getAttribute('data-quitar')]; valorizar(); }); });
        estado.total = Number(v.cabecera.total) || 0;
        $('[data-vmos-neto]').textContent = plata(v.cabecera.subtotal);
        $('[data-vmos-iva]').textContent = plata(v.cabecera.impuestos);
        $('[data-vmos-total]').textContent = plata(estado.total);
        var primero = $('[data-vmos-medios] [data-importe]');
        if (primero && $('[data-vmos-medios]').children.length === 1) primero.value = estado.total.toFixed(2);
        vuelto();
    }

    // ---------------------------------------------------------------- cobro
    function filaMedio() {
        var ini = estado.inicio;
        var div = document.createElement('div');
        div.className = 'vmos-medio-fila';
        div.innerHTML = '<select class="form-select form-select-sm" data-medio>' + ini.medios.map(function (m) { return '<option value="' + m.medio_pago_id + '" data-tipo="' + esc(m.tipo) + '">' + esc(m.nombre) + '</option>'; }).join('') + '</select>'
            + '<select class="form-select form-select-sm" data-cuenta>' + ini.cuentas.map(function (c) { return '<option value="' + c.tesoreria_cuenta_id + '" data-tipo="' + esc(c.tipo) + '">' + esc(c.nombre) + '</option>'; }).join('') + '</select>'
            + '<input type="number" class="form-control form-control-sm" min="0" step="0.01" data-importe placeholder="Importe">'
            + '<button type="button" class="btn btn-sm btn-link text-danger" title="Quitar">&times;</button>';
        var medio = div.querySelector('[data-medio]'), cuenta = div.querySelector('[data-cuenta]');
        var sugerir = function () {   // efectivo → caja; transferencia / tarjeta → banco
            var t = medio.options[medio.selectedIndex].getAttribute('data-tipo');
            var quiere = t === 'EFECTIVO' ? 'CAJA' : (t === 'CHEQUE_TERCERO' ? 'VALORES' : 'BANCO');
            var op = Array.prototype.find.call(cuenta.options, function (o) { return o.getAttribute('data-tipo') === quiere; });
            if (op) cuenta.value = op.value;
        };
        medio.addEventListener('change', sugerir);
        sugerir();
        div.querySelector('[data-importe]').addEventListener('input', vuelto);
        div.querySelector('button').addEventListener('click', function () { div.remove(); vuelto(); });
        $('[data-vmos-medios]').appendChild(div);
        return div;
    }
    function medios() {
        return Array.prototype.map.call($('[data-vmos-medios]').children, function (d) {
            return {medio_pago_id: Number(d.querySelector('[data-medio]').value), tesoreria_cuenta_id: Number(d.querySelector('[data-cuenta]').value),
                    importe: Number(d.querySelector('[data-importe]').value) || 0,
                    tipo: d.querySelector('[data-medio]').options[d.querySelector('[data-medio]').selectedIndex].getAttribute('data-tipo')};
        }).filter(function (m) { return m.importe > 0; });
    }
    function vuelto() {
        var pagado = medios().reduce(function (s, m) { return s + m.importe; }, 0);
        $('[data-vmos-vuelto]').textContent = plata(Math.max(0, pagado - estado.total));
    }
    function facturar() {
        var items = lista();
        if (!items.length) { aviso('Agregá al menos un producto.', 'aviso'); return; }
        var ms = [];
        if (estado.entidad === 0) {
            // El vuelto sale del efectivo: se cobra exactamente el total
            ms = medios();
            var pagado = ms.reduce(function (s, m) { return s + m.importe; }, 0);
            if (pagado + 0.005 < estado.total) { aviso('Los medios de cobro no cubren el total.', 'aviso'); return; }
            var sobra = Math.round((pagado - estado.total) * 100) / 100;
            var ef = ms.find(function (m) { return m.tipo === 'EFECTIVO'; });
            if (sobra > 0 && (!ef || ef.importe < sobra)) { aviso('El vuelto solo puede salir del efectivo.', 'aviso'); return; }
            if (ef) ef.importe = Math.round((ef.importe - sobra) * 100) / 100;
            ms = ms.filter(function (m) { return m.importe > 0; }).map(function (m) { return {medio_pago_id: m.medio_pago_id, tesoreria_cuenta_id: m.tesoreria_cuenta_id, importe: m.importe}; });
        }
        var btn = $('[data-vmos-facturar]');
        btn.disabled = true;
        api('facturar', {items: JSON.stringify(items), entidad_id: estado.entidad, punto_venta_id: estado.pv, medios: JSON.stringify(ms),
                         consumidor_final_nombre: $('[data-vmos-cf-nombre]').value}, 'POST')
            .then(function (j) {
                var d = j.data;
                $('[data-vmos-resultado]').innerHTML = '<div class="alert ' + (d.aprobada ? 'alert-success' : 'alert-warning') + '">' + esc(j.message)
                    + (d.comprobante_nro ? ' N° ' + esc(d.comprobante_nro) : '') + ' · ' + plata(d.total)
                    + ' <a class="ms-2" target="_blank" href="' + C.imprimir + '?venta_factura_id=' + d.venta_factura_id + '&empresa_idx=' + C.empresa + '">Imprimir</a></div>';
                estado.items = {};
                $('[data-vmos-medios]').innerHTML = '';
                filaMedio();
                valorizar();
            })
            .catch(function (e) { aviso(e.message, 'error'); })
            .finally(function () { btn.disabled = false; foco(); });
    }

    // ---------------------------------------------------------------- cliente opcional
    function buscarClientes(q) {
        var cont = $('[data-vmos-clientes]');
        if (q.trim().length < 3) { cont.innerHTML = ''; return; }
        api('clientes', {q: q.trim()}).then(function (j) {
            cont.innerHTML = j.data.map(function (c) { return '<button type="button" class="list-group-item list-group-item-action" data-id="' + c.entidad_id + '">' + esc(c.nombre) + ' <small>' + esc(c.cuit || '') + '</small></button>'; }).join('');
            cont.querySelectorAll('[data-id]').forEach(function (b) {
                b.addEventListener('click', function () { elegirCliente(Number(b.getAttribute('data-id')), b.textContent); });
            });
        }).catch(function (e) { aviso(e.message, 'error'); });
    }
    function elegirCliente(id, nombre) {
        estado.entidad = id;
        $('[data-vmos-cliente-q]').value = id ? nombre : '';
        $('[data-vmos-clientes]').innerHTML = '';
        $('[data-vmos-cobro]').style.display = id ? 'none' : '';      // con cliente: a su cuenta corriente
        $('[data-vmos-cf-nombre]').style.display = id ? 'none' : '';
        valorizar();
    }

    // ---------------------------------------------------------------- cámara
    var flujo = null;
    function camara() {
        var video = $('[data-vmos-video]');
        if (flujo) { flujo.getTracks().forEach(function (t) { t.stop(); }); flujo = null; video.style.display = 'none'; return; }
        if (!('BarcodeDetector' in window) || !navigator.mediaDevices) { aviso('Este navegador no lee códigos con la cámara: usá el lector o escribilo.', 'aviso'); return; }
        var det = new window.BarcodeDetector({formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'itf']});
        navigator.mediaDevices.getUserMedia({video: {facingMode: 'environment'}}).then(function (s) {
            flujo = s; video.srcObject = s; video.style.display = '';
            video.play();
            var leer = function () {
                if (!flujo) return;
                det.detect(video).then(function (c) {
                    if (c.length) { buscar(c[0].rawValue, true); setTimeout(leer, 1200); } else { setTimeout(leer, 250); }
                }).catch(function () { setTimeout(leer, 500); });
            };
            leer();
        }).catch(function () { aviso('No se pudo usar la cámara.', 'error'); });
    }

    // ---------------------------------------------------------------- arranque
    var lector = $('[data-vmos-lector]');
    lector.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); buscar(lector.value, true); } });
    lector.addEventListener('input', function () { clearTimeout(lector._t); lector._t = setTimeout(function () { buscar(lector.value, false); }, 400); });
    $('[data-vmos-camara]').addEventListener('click', camara);
    $('[data-vmos-medio-agregar]').addEventListener('click', function () { filaMedio(); });
    $('[data-vmos-facturar]').addEventListener('click', facturar);
    var cq = $('[data-vmos-cliente-q]');
    cq.addEventListener('input', function () { clearTimeout(cq._t); cq._t = setTimeout(function () { buscarClientes(cq.value); }, 350); });
    $('[data-vmos-cliente-quitar]').addEventListener('click', function () { elegirCliente(0, ''); });
    $('[data-vmos-pv]').addEventListener('change', function () { estado.pv = Number(this.value); valorizar(); });
    document.addEventListener('click', function () { setTimeout(foco, 0); });

    api('inicio').then(function (j) {
        estado.inicio = j.data;
        var pvs = j.data.puntos_venta;
        $('[data-vmos-pv]').innerHTML = pvs.map(function (p) { return '<option value="' + p.punto_venta_id + '">' + esc(p.sucursal + ' · ' + p.nombre + ' (' + p.comprobante_tipo + ')') + '</option>'; }).join('');
        estado.pv = pvs.length ? pvs[0].punto_venta_id : 0;
        if (!pvs.length) aviso('No tenés un punto de venta habilitado para facturar a consumidor final.', 'aviso');
        if (!j.data.puede_facturar) $('[data-vmos-facturar]').disabled = true;
        filaMedio();
        lector.focus();
    }).catch(function (e) {
        raiz.innerHTML = '<div class="alert alert-warning">' + esc(e.message) + '</div>';
    });
}());
