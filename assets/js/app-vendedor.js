/**
 * Aplicación del vendedor (doc 28, pasos 9-7 / 9-8): app_vendedor/index.php + api.php. Sin librerías.
 * Flujo: clientes de la cartera → pedido del cliente (buscar / escanear, cantidades, descuento manual si tiene tope)
 * → guardar (Borrador real, origen APP) o confirmar. Los precios y totales los calcula el servidor (valorizar); acá
 * solo se mandan producto, presentación y cantidad. Sin conexión: avisa y guarda lo cargado en este teléfono
 * (localStorage) para no perderlo; no hay modo sin conexión (etapa 5).
 */
(function () {
    'use strict';
    var raiz = document.getElementById('av');
    if (!raiz) return;
    var API = raiz.getAttribute('data-api');
    var EMPRESA = raiz.getAttribute('data-empresa');
    var CSRF = raiz.getAttribute('data-csrf');
    var vista = raiz.querySelector('[data-av-vista]');
    var titulo = raiz.querySelector('[data-av-titulo]');
    var btnVolver = raiz.querySelector('[data-av-volver]');
    var aviso = raiz.querySelector('[data-av-aviso]');
    var inicio = null;          // datos de la acción inicio
    var ped = null;             // pedido en pantalla: {cliente, items: {clave: {producto_id, presentacion_id, cantidad, nombre, oferta}}, ...}
    var timerValorizar = null;
    var timerAviso = null;
    var ICONO_CODIGO = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">'
        + '<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 8v8M10.5 8v8M14 8v8M17 8v8"/></svg>';
    var VACIO_PEDIDO = 'Buscá o escaneá un producto para agregarlo.';

    function esc(t) {
        return String(t === null || t === undefined ? '' : t).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    }
    function plata(v) {
        return '$ ' + (Number(v) || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    function num(v) { return (Number(v) || 0).toLocaleString(undefined, {maximumFractionDigits: 2}); }
    function iniciales(t) {
        var p = String(t || '').trim().split(/\s+/).filter(Boolean);
        return ((p[0] || '?').charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : '')).toUpperCase();
    }
    function mostrarAviso(texto, tipo) {
        clearTimeout(timerAviso);
        if (!texto) { aviso.hidden = true; return; }
        aviso.textContent = texto;
        aviso.className = 'av-aviso av-aviso-' + (tipo || 'info');
        aviso.hidden = false;
        timerAviso = setTimeout(function () { aviso.hidden = true; }, tipo === 'error' ? 8000 : 3000);
    }
    function api(accion, datos, metodo) {
        datos = datos || {};
        datos.empresa_idx = EMPRESA;
        if (!navigator.onLine) {
            return Promise.reject(new Error('Sin conexión. Lo cargado queda guardado en este teléfono.'));
        }
        var opciones = {method: metodo || 'GET', credentials: 'same-origin', headers: {'Accept': 'application/json'}};
        var url = API + '?accion=' + encodeURIComponent(accion);
        if (opciones.method === 'GET') {
            Object.keys(datos).forEach(function (k) { url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(datos[k]); });
        } else {
            var fd = new FormData();
            Object.keys(datos).forEach(function (k) { fd.append(k, datos[k]); });
            opciones.body = fd;
            opciones.headers['X-CSRF-Token'] = CSRF;
        }
        return fetch(url, opciones).then(function (r) {
            if (r.status === 401) { location.reload(); throw new Error('La sesión venció.'); }
            return r.json().catch(function () { throw new Error('Respuesta inválida del servidor.'); });
        }, function () {
            throw new Error('Sin conexión. Lo cargado queda guardado en este teléfono.');
        }).then(function (j) {
            if (!j || !j.success) throw new Error((j && j.message) || 'No se pudo completar.');
            return j;
        });
    }
    function guardarLocal() {
        if (!ped) return;
        try {
            localStorage.setItem('av:' + EMPRESA + ':' + ped.cliente.entidad_id, JSON.stringify({
                items: ped.items, observaciones: ped.observaciones, manual: ped.manual, pedido_id: ped.pedido_id, sucio: ped.sucio, ts: Date.now()
            }));
        } catch (e) { /* sin almacenamiento: se sigue igual */ }
    }
    function leerLocal(clienteId) {
        try { return JSON.parse(localStorage.getItem('av:' + EMPRESA + ':' + clienteId) || 'null'); } catch (e) { return null; }
    }
    function borrarLocal(clienteId) {
        try { localStorage.removeItem('av:' + EMPRESA + ':' + clienteId); } catch (e) { /* nada */ }
    }

    // ------------------------------------------------------------------ Clientes y Deudas
    var vistaPrincipal = 'clientes';
    function verClientes(q) {
        ped = null;
        btnVolver.hidden = true;
        titulo.textContent = vistaPrincipal === 'deudas' ? 'Listado de deuda' : 'Clientes';
        vista.innerHTML = '<div class="av-tabs av-tabs-2" data-av-modo>'
            + '<button type="button" class="av-tab' + (vistaPrincipal === 'clientes' ? ' av-tab-activa' : '') + '" data-modo="clientes">Clientes</button>'
            + '<button type="button" class="av-tab' + (vistaPrincipal === 'deudas' ? ' av-tab-activa' : '') + '" data-modo="deudas">Listado de deuda</button>'
            + '</div>'
            + '<div class="av-buscador"><input type="search" class="av-input" placeholder="Buscar cliente o CUIT" data-av-q value="' + esc(q || '') + '"></div>'
            + '<div data-av-resumen-deuda></div>'
            + '<ul class="av-lista av-lista-tarjeta" data-av-clientes><li class="av-vacio av-cargando">Cargando…</li></ul>';
        var input = vista.querySelector('[data-av-q]');
        var t = null;
        input.addEventListener('input', function () {
            clearTimeout(t);
            t = setTimeout(function () {
                vistaPrincipal === 'deudas' ? cargarDeudas(input.value) : cargarClientes(input.value);
            }, 300);
        });
        vista.querySelectorAll('[data-modo]').forEach(function (b) {
            b.addEventListener('click', function () {
                vistaPrincipal = b.getAttribute('data-modo');
                verClientes(input.value);
            });
        });
        vistaPrincipal === 'deudas' ? cargarDeudas(q || '') : cargarClientes(q || '');
    }
    function cargarDeudas(q) {
        var ul = vista.querySelector('[data-av-clientes]');
        var resBox = vista.querySelector('[data-av-resumen-deuda]');
        api('deudas', {q: q}).then(function (j) {
            var d = j.data || {};
            if (!d.configurado) {
                resBox.innerHTML = '';
                ul.innerHTML = '<li class="av-vacio">Cuenta corriente no configurada para la empresa.</li>';
                return;
            }
            resBox.innerHTML = '<div class="av-tarjeta av-deuda-resumen">'
                + '<div><span class="av-fila-sub">Deuda total</span><strong>' + plata(d.total_deuda) + '</strong></div>'
                + '<div><span class="av-fila-sub">Vencido</span><strong class="' + (d.total_vencido > 0 ? 'av-texto-alerta' : '') + '">' + plata(d.total_vencido) + '</strong></div>'
                + '</div>';
            var lista = d.clientes || [];
            if (!lista.length) {
                ul.innerHTML = '<li class="av-vacio">Sin clientes con deuda' + (q ? ' para esa búsqueda' : '') + '.</li>';
                return;
            }
            ul.innerHTML = lista.map(function (c) {
                return '<li><button type="button" class="av-fila" data-id="' + c.entidad_id + '">'
                    + '<div class="av-fila-linea"><span class="av-fila-titulo">' + esc(c.nombre) + '</span><strong>' + plata(c.deuda) + '</strong></div>'
                    + '<div class="av-fila-linea"><span class="av-fila-sub">' + esc(c.cuit || '') + (c.primer_vencimiento ? ' · 1° vto ' + esc(fechaAr(c.primer_vencimiento)) : '') + '</span>'
                    + (c.vencido > 0 ? '<span class="av-chip av-chip-alerta">Vencido ' + plata(c.vencido) + '</span>' : '<span class="av-chip av-chip-ok">Al día</span>') + '</div>'
                    + '</button></li>';
            }).join('');
            ul.querySelectorAll('[data-id]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var c = lista.find(function (x) { return String(x.entidad_id) === b.getAttribute('data-id'); });
                    verPedido(c);
                });
            });
        }).catch(function (e) { resBox.innerHTML = ''; ul.innerHTML = '<li class="av-vacio">' + esc(e.message) + '</li>'; });
    }
    function cargarClientes(q) {
        var ul = vista.querySelector('[data-av-clientes]');
        api('clientes', {q: q}).then(function (j) {
            if (!j.data.length) { ul.innerHTML = '<li class="av-vacio">Sin clientes en tu cartera' + (q ? ' con esa búsqueda' : '') + '.</li>'; return; }
            ul.innerHTML = j.data.map(function (c) {
                var local = leerLocal(c.entidad_id);
                var enCurso = c.borrador_id || (local && local.sucio);
                return '<li><button type="button" class="av-fila" data-id="' + c.entidad_id + '">'
                    + '<span class="av-avatar" aria-hidden="true">' + esc(iniciales(c.nombre)) + '</span><span class="av-fila-cuerpo">'
                    + '<span class="av-fila-titulo">' + esc(c.nombre) + '</span>'
                    + '<span class="av-fila-sub">' + esc(c.cuit || '') + (c.razon_social !== c.nombre ? ' · ' + esc(c.razon_social) : '') + '</span>'
                    + (enCurso ? '<span class="av-chip">Pedido en preparación</span>' : '') + '</span><span class="av-chevron" aria-hidden="true"></span></button></li>';
            }).join('');
            ul.querySelectorAll('[data-id]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var c = j.data.find(function (x) { return String(x.entidad_id) === b.getAttribute('data-id'); });
                    verPedido(c);
                });
            });
        }).catch(function (e) { ul.innerHTML = '<li class="av-vacio">' + esc(e.message) + '</li>'; });
    }

    // ------------------------------------------------------------------ Pedido del cliente
    function verPedido(cliente) {
        ped = {cliente: cliente, items: {}, observaciones: '', manual: 0, pedido_id: null, sucio: false, sucursal: (inicio.sucursales[0] || {}).sucursal_id || 0, valor: null};
        titulo.textContent = cliente.nombre;
        btnVolver.hidden = false;
        vista.innerHTML = '<section class="av-ficha" data-av-ficha><span class="av-vacio av-cargando">Cargando…</span></section>'
            + '<div class="av-buscador"><input type="search" class="av-input" placeholder="Código, nombre, marca o código de barras" data-av-buscar>'
            + '<button type="button" class="av-btn av-btn-icono" data-av-escanear aria-label="Escanear código de barras" title="Escanear">' + ICONO_CODIGO + '</button></div>'
            + '<div class="av-escaner" data-av-escaner hidden><video playsinline muted></video><button type="button" class="av-btn" data-av-cerrar-escaner>Cerrar cámara</button></div>'
            + '<ul class="av-lista av-lista-tarjeta av-resultados" data-av-resultados></ul>'
            + '<h2 class="av-subtitulo">Pedido <span class="av-contador" data-av-contador hidden></span></h2>'
            + '<ul class="av-lista av-lista-tarjeta av-lineas" data-av-lineas><li class="av-vacio">' + VACIO_PEDIDO + '</li></ul>'
            + '<section class="av-tarjeta av-opciones">'
            + (inicio.descuento_manual ? '<label class="av-campo">Descuento manual % <small>(tu tope: ' + num(inicio.tope_descuento) + ' %)</small>'
                + '<input type="number" class="av-input" min="0" max="100" step="0.5" value="0" data-av-manual></label>' : '')
            + (inicio.sucursales.length > 1 ? '<label class="av-campo">Sucursal<select class="av-input" data-av-sucursal>'
                + inicio.sucursales.map(function (s) { return '<option value="' + s.sucursal_id + '">' + esc(s.nombre) + '</option>'; }).join('') + '</select></label>' : '')
            + '<label class="av-campo">Observaciones<textarea class="av-input" rows="2" maxlength="500" data-av-obs></textarea></label>'
            + '</section>'
            + '<div class="av-tarjeta av-totales" data-av-totales></div>'
            + '<details class="av-tarjeta av-historial" data-av-consulta><summary>Pedidos, deuda, cuenta corriente, facturas y pagos</summary>'
            + '<div class="av-tabs av-tabs-5" data-av-tabs>'
            + '<button type="button" class="av-tab av-tab-activa" data-tab="pedidos">Pedidos</button>'
            + '<button type="button" class="av-tab" data-tab="deuda">Deuda</button>'
            + '<button type="button" class="av-tab" data-tab="ctacte">Cta. Cte.</button>'
            + '<button type="button" class="av-tab" data-tab="facturas">Facturas</button>'
            + '<button type="button" class="av-tab" data-tab="pagos">Pagos</button>'
            + '</div>'
            + '<div data-av-historial><p class="av-vacio">Abrí para consultar…</p></div></details>'
            + '<div class="av-acciones"><div class="av-acciones-resumen"><span data-av-resumen-cant>Sin productos</span><strong data-av-resumen-total>' + plata(0) + '</strong></div>'
            + '<button type="button" class="av-btn" data-av-guardar>Guardar</button>'
            + '<button type="button" class="av-btn av-btn-primario" data-av-confirmar>Confirmar pedido</button></div>';
        var buscar = vista.querySelector('[data-av-buscar]');
        var t = null;
        buscar.addEventListener('input', function () { clearTimeout(t); t = setTimeout(function () { buscarProductos(buscar.value); }, 350); });
        buscar.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); buscarProductos(buscar.value, true); } });
        vista.querySelector('[data-av-escanear]').addEventListener('click', abrirEscaner);
        vista.querySelector('[data-av-cerrar-escaner]').addEventListener('click', cerrarEscaner);
        vista.querySelector('[data-av-obs]').addEventListener('input', function () { ped.observaciones = this.value; ped.sucio = true; guardarLocal(); });
        var manual = vista.querySelector('[data-av-manual]');
        if (manual) manual.addEventListener('change', function () { ped.manual = Math.max(0, Math.min(100, Number(this.value) || 0)); ped.sucio = true; valorizar(); });
        var suc = vista.querySelector('[data-av-sucursal]');
        if (suc) suc.addEventListener('change', function () { ped.sucursal = Number(this.value); valorizar(); });
        vista.querySelector('[data-av-guardar]').addEventListener('click', function () { guardar(false); });
        vista.querySelector('[data-av-confirmar]').addEventListener('click', function () { guardar(true); });

        api('cliente', {entidad_id: cliente.entidad_id}).then(function (j) {
            var d = j.data;
            vista.querySelector('[data-av-ficha]').innerHTML = (d.canal ? '<span class="av-chip">' + esc(d.canal) + '</span>' : '')
                + (d.descuento_cliente > 0 ? '<span class="av-chip">Desc. cliente ' + num(d.descuento_cliente) + ' %</span>' : '')
                + (d.entrega ? '<span class="av-chip av-chip-ok">Entrega estimada: ' + esc(d.entrega.dia) + ' ' + esc(d.entrega.fecha.split('-').reverse().join('/')) + '</span>' : '')
                + '<span class="av-chip ' + (d.cuenta.vencido > 0 ? 'av-chip-alerta' : 'av-chip-neutro') + '">Saldo ' + plata(d.cuenta.saldo) + (d.cuenta.vencido > 0 ? ' · vencido ' + plata(d.cuenta.vencido) : '') + '</span>';
            configurarConsultaCliente(cliente.entidad_id, d.cuenta);
            var local = leerLocal(cliente.entidad_id);
            if (local && local.sucio) {                       // lo cargado y no guardado en este teléfono
                ped.items = local.items || {}; ped.observaciones = local.observaciones || ''; ped.manual = local.manual || 0;
                ped.pedido_id = local.pedido_id || (d.borrador ? d.borrador.venta_pedido_id : null); ped.sucio = true;
                mostrarAviso('Se recuperó lo que habías cargado en este teléfono.', 'info');
            } else if (d.borrador) {                          // pedido en preparación guardado en el sistema
                ped.pedido_id = d.borrador.venta_pedido_id; ped.observaciones = d.borrador.observaciones || '';
                ped.manual = inicio.descuento_manual ? (d.borrador.descuento_manual_pct || 0) : 0;
                d.borrador.items.forEach(function (it) {
                    var clave = it.producto_id + '-' + (it.presentacion_id || 0);
                    ped.items[clave] = {producto_id: it.producto_id, presentacion_id: it.presentacion_id || 0, cantidad: it.cantidad, nombre: ''};
                });
            }
            vista.querySelector('[data-av-obs]').value = ped.observaciones;
            if (manual) manual.value = ped.manual;
            valorizar();
        }).catch(function (e) { mostrarAviso(e.message, 'error'); });
    }

    function fechaAr(f) {
        if (!f) return '';
        return String(f).slice(0, 10).split('-').reverse().join('/');
    }
    function pvNro(pv, nro) {
        var n = Number(nro) || 0;
        if (!n) return 'Borrador';
        var p = pv ? String(pv).padStart(4, '0') + '-' : '';
        return p + String(n).padStart(8, '0');
    }
    function configurarConsultaCliente(entidadId, cuentaResumen) {
        var det = vista.querySelector('[data-av-consulta]');
        var cont = vista.querySelector('[data-av-historial]');
        if (!det || !cont) return;
        var datos = null;
        var tabActual = 'pedidos';

        function pintarTab() {
            if (!datos) return;
            if (tabActual === 'pedidos') {
                cont.innerHTML = datos.pedidos.length ? '<ul class="av-lista">' + datos.pedidos.map(function (h) {
                    return '<li class="av-fila-estatica"><span><strong>' + (h.comprobante_nro > 0 ? 'N° ' + esc(h.comprobante_nro) : 'Borrador') + '</strong> · ' + esc(fechaAr(h.f_emision))
                        + (h.origen ? ' <small class="av-chip">' + esc(h.origen) + '</small>' : '') + '</span>'
                        + '<span><strong>' + plata(h.total) + '</strong><br><small class="av-fila-sub">' + esc(h.estado || '') + '</small></span></li>';
                }).join('') + '</ul>' : '<p class="av-vacio">Sin pedidos registrados.</p>';
            } else if (tabActual === 'deuda') {
                var dd = datos.deuda || {};
                if (!dd.configurado) { cont.innerHTML = '<p class="av-vacio">Cuenta corriente no configurada para la empresa.</p>'; return; }
                var pends = dd.pendientes || [];
                var favs = dd.a_favor || [];
                var html = pends.length ? '<ul class="av-lista">' + pends.map(function (m) {
                    return '<li class="av-fila-estatica"><span><strong>' + esc(m.tipo) + ' ' + esc(m.numero) + '</strong>'
                        + '<br><small class="av-fila-sub">Em. ' + esc(fechaAr(m.fecha)) + (m.vto ? ' · Vto ' + esc(fechaAr(m.vto)) : '') + '</small>'
                        + (m.dias_atraso > 0 ? ' <span class="av-chip av-chip-alerta">' + m.dias_atraso + ' d. atraso</span>' : '') + '</span>'
                        + '<span class="av-der"><strong>' + plata(m.pendiente) + '</strong><br><small class="av-fila-sub">de ' + plata(m.importe) + '</small></span></li>';
                }).join('') + '</ul>' : '<p class="av-vacio">Sin comprobantes impagos.</p>';
                if (favs.length) {
                    html += '<p class="av-fila-sub" style="margin:.6rem 0 .2rem">Saldo a favor sin aplicar</p><ul class="av-lista">' + favs.map(function (m) {
                        return '<li class="av-fila-estatica"><span><strong>' + esc(m.tipo) + ' ' + esc(m.numero) + '</strong> · ' + esc(fechaAr(m.fecha)) + '</span>'
                            + '<span class="av-der"><strong class="av-texto-ok">' + plata(m.pendiente) + '</strong></span></li>';
                    }).join('') + '</ul>';
                }
                cont.innerHTML = html;
            } else if (tabActual === 'ctacte') {
                var cc = datos.ctacte || {};
                if (!cc.configurado) { cont.innerHTML = '<p class="av-vacio">Cuenta corriente no configurada para la empresa.</p>'; return; }
                var res = '<div class="av-cc-resumen"><span>Saldo actual: <strong>' + plata(cuentaResumen.saldo) + '</strong></span>'
                    + (cuentaResumen.vencido > 0 ? '<span class="av-chip av-chip-alerta">Vencido: ' + plata(cuentaResumen.vencido) + '</span>' : '') + '</div>';
                cont.innerHTML = res + (cc.movimientos && cc.movimientos.length ? '<ul class="av-lista">' + cc.movimientos.map(function (m) {
                    var imp = m.debe > 0 ? ('+' + plata(m.debe)) : ('-' + plata(m.haber));
                    return '<li class="av-fila-estatica"><span><strong>' + esc(m.tipo) + ' ' + esc(m.numero) + '</strong><br><small class="av-fila-sub">' + esc(fechaAr(m.fecha))
                        + (m.vto ? ' · Vto ' + esc(fechaAr(m.vto)) : '') + (m.pendiente > 0 ? ' · Pend. ' + plata(m.pendiente) : '') + '</small></span>'
                        + '<span class="av-der"><strong class="' + (m.debe > 0 ? '' : 'av-texto-ok') + '">' + imp + '</strong><br><small class="av-fila-sub">Saldo ' + plata(m.saldo) + '</small></span></li>';
                }).join('') + '</ul>' : '<p class="av-vacio">Sin movimientos en cuenta corriente.</p>');
            } else if (tabActual === 'facturas') {
                cont.innerHTML = datos.facturas.length ? '<ul class="av-lista">' + datos.facturas.map(function (f) {
                    return '<li class="av-fila-estatica"><span><strong>' + esc((f.tipo_codigo || f.comprobante_tipo || 'FAC') + ' ' + pvNro(f.pv, f.comprobante_nro)) + '</strong>'
                        + '<br><small class="av-fila-sub">' + esc(fechaAr(f.f_emision)) + (f.f_vto ? ' · Vto ' + esc(fechaAr(f.f_vto)) : '')
                        + (f.CAE ? ' · CAE ' + esc(f.CAE) : '') + '</small></span>'
                        + '<span class="av-der"><strong>' + plata(f.importe_total) + '</strong><br><small class="av-fila-sub">' + esc(f.estado || '') + '</small></span></li>';
                }).join('') + '</ul>' : '<p class="av-vacio">Sin facturas registradas.</p>';
            } else if (tabActual === 'pagos') {
                cont.innerHTML = datos.pagos.length ? '<ul class="av-lista">' + datos.pagos.map(function (p) {
                    return '<li class="av-fila-estatica"><span><strong>' + esc((p.tipo_codigo || p.comprobante_tipo || 'REC') + ' ' + pvNro(p.pv, p.comprobante_nro)) + '</strong>'
                        + '<br><small class="av-fila-sub">' + esc(fechaAr(p.f_emision)) + (p.observaciones ? ' · ' + esc(p.observaciones) : '') + '</small></span>'
                        + '<span class="av-der"><strong class="av-texto-ok">' + plata(p.importe_total) + '</strong><br><small class="av-fila-sub">' + esc(p.estado || '') + '</small></span></li>';
                }).join('') + '</ul>' : '<p class="av-vacio">Sin pagos recibidos.</p>';
            }
        }

        det.querySelectorAll('[data-tab]').forEach(function (b) {
            b.addEventListener('click', function () {
                tabActual = b.getAttribute('data-tab');
                det.querySelectorAll('[data-tab]').forEach(function (x) { x.classList.toggle('av-tab-activa', x === b); });
                pintarTab();
            });
        });

        det.addEventListener('toggle', function () {
            if (!det.open || datos) return;
            cont.innerHTML = '<p class="av-vacio">Cargando…</p>';
            api('cliente_detalle', {entidad_id: entidadId}).then(function (j) {
                datos = j.data;
                pintarTab();
            }).catch(function (e) {
                cont.innerHTML = '<p class="av-vacio">' + esc(e.message) + '</p>';
            });
        });
    }

    function buscarProductos(q, unico) {
        var ul = vista.querySelector('[data-av-resultados]');
        if (!q || q.trim().length < 2) { ul.innerHTML = ''; return; }
        api('buscar', {entidad_id: ped.cliente.entidad_id, q: q.trim(), sucursal_id: ped.sucursal}).then(function (j) {
            var prods = j.data.productos;
            // Escáner / Enter con un solo resultado: se agrega directo (la presentación escaneada, o la única oferta)
            if (unico && prods.length === 1) {
                var p = prods[0];
                var of = p.ofertas.find(function (o) { return (o.presentacion_id || 0) === (p.presentacion_escaneada || 0); }) || (p.ofertas.length === 1 ? p.ofertas[0] : null);
                if (of) { sumar(p, of, 1); ul.innerHTML = ''; vista.querySelector('[data-av-buscar]').value = ''; return; }
            }
            ul.innerHTML = prods.length ? prods.map(function (p, i) {
                return '<li class="av-producto"><div class="av-fila-titulo">' + esc(p.nombre) + '</div><div class="av-fila-sub">' + esc(p.codigo) + (p.marca ? ' · ' + esc(p.marca) : '') + '</div>'
                    + p.ofertas.map(function (o, k) {
                        var datos = [o.minimo ? 'mín. ' + num(o.minimo) : '', o.multiplo ? 'de a ' + num(o.multiplo) : '',
                                     o.disponible !== null ? 'disp. ' + num(o.disponible) : ''].filter(Boolean).join(' · ');
                        return '<div class="av-oferta"><span class="av-oferta-info"><span class="av-oferta-nombre">' + esc(o.nombre)
                            + (o.etiqueta ? ' <span class="av-chip av-chip-ok">' + esc(o.etiqueta) + '</span>' : '') + '</span>'
                            + '<span class="av-precio">' + plata(o.precio_final) + '</span>'
                            + (datos ? '<span class="av-oferta-datos">' + esc(datos) + '</span>' : '') + '</span>'
                            + '<button type="button" class="av-btn av-btn-chico av-btn-agregar" data-p="' + i + '" data-o="' + k + '" aria-label="Agregar ' + esc(o.nombre) + '">+</button></div>';
                    }).join('') + '</li>';
            }).join('') : '<li class="av-vacio">Sin resultados con precio en la lista del cliente.</li>';
            ul.querySelectorAll('[data-p]').forEach(function (b) {
                b.addEventListener('click', function () { var p = prods[b.getAttribute('data-p')]; sumar(p, p.ofertas[b.getAttribute('data-o')], 0); });
            });
        }).catch(function (e) { mostrarAviso(e.message, 'error'); });
    }

    function sumar(p, of, delta) {
        var pres = of.presentacion_id || 0;
        var clave = p.producto_id + '-' + pres;
        var paso = delta || Math.max(1, Number(of.multiplo) || 1);
        var it = ped.items[clave] || {producto_id: p.producto_id, presentacion_id: pres, cantidad: 0, nombre: ''};
        it.cantidad = Math.round((Number(it.cantidad) + paso) * 100) / 100;
        if (Number(of.minimo) > 0 && it.cantidad < Number(of.minimo)) it.cantidad = Number(of.minimo);
        ped.items[clave] = it;
        ped.sucio = true;
        guardarLocal();
        mostrarAviso(p.nombre + ' · ' + of.nombre + ': ' + num(it.cantidad), 'ok');
        valorizar();
    }

    function itemsLista() {
        return Object.keys(ped.items).map(function (k) { var i = ped.items[k]; return {producto_id: i.producto_id, presentacion_id: i.presentacion_id, cantidad: i.cantidad}; })
            .filter(function (i) { return i.cantidad > 0; });
    }

    function valorizar() {
        clearTimeout(timerValorizar);
        timerValorizar = setTimeout(function () {
            var ul = vista.querySelector('[data-av-lineas]');
            var items = itemsLista();
            if (!items.length) { ul.innerHTML = '<li class="av-vacio">' + VACIO_PEDIDO + '</li>'; vista.querySelector('[data-av-totales]').innerHTML = ''; resumen(0, 0); return; }
            api('valorizar', {entidad_id: ped.cliente.entidad_id, sucursal_id: ped.sucursal, items: JSON.stringify(items), descuento_manual_pct: ped.manual}, 'POST')
                .then(function (j) { pintarLineas(j.data); })
                .catch(function (e) { mostrarAviso(e.message, 'error'); });
        }, 250);
    }

    function pintarLineas(v) {
        var ul = vista.querySelector('[data-av-lineas]');
        ul.innerHTML = v.lineas.map(function (l) {
            var clave = l.producto_id + '-' + (l.presentacion_id || 0);
            var it = ped.items[clave] || {};
            return '<li class="av-linea"><div><div class="av-fila-titulo">' + esc(l.nombre || ('Producto ' + l.producto_id)) + '</div>'
                + '<div class="av-fila-sub">' + (l.descuento_pct > 0 ? 'Desc. ' + num(l.descuento_pct) + ' %' : '') + (l.etiqueta ? ' · ' + esc(l.etiqueta) : '')
                + (l.requiere_aprobacion ? ' <span class="av-chip av-chip-alerta">supera tu tope</span>' : '') + '</div></div>'
                + '<div class="av-cantidad"><button type="button" class="av-btn av-btn-chico" data-k="' + clave + '" data-d="-1" aria-label="Uno menos">−</button>'
                + '<input type="number" inputmode="decimal" class="av-input av-input-cant" value="' + esc(it.cantidad) + '" data-k="' + clave + '" aria-label="Cantidad">'
                + '<button type="button" class="av-btn av-btn-chico" data-k="' + clave + '" data-d="1" aria-label="Uno más">+</button></div>'
                + '<div class="av-importe">' + plata(l.total_linea) + '</div></li>';
        }).join('');
        ul.querySelectorAll('button[data-k]').forEach(function (b) {
            b.addEventListener('click', function () { cambiar(b.getAttribute('data-k'), Number(ped.items[b.getAttribute('data-k')].cantidad) + Number(b.getAttribute('data-d'))); });
        });
        ul.querySelectorAll('input[data-k]').forEach(function (inp) {
            inp.addEventListener('change', function () { cambiar(inp.getAttribute('data-k'), Number(inp.value)); });
        });
        vista.querySelector('[data-av-totales]').innerHTML = '<div><span>Neto</span><span>' + plata(v.cabecera.subtotal) + '</span></div>'
            + '<div><span>IVA</span><span>' + plata(v.cabecera.impuestos) + '</span></div>'
            + '<div class="av-total"><span>Total</span><span>' + plata(v.cabecera.total) + '</span></div>';
        resumen(v.lineas.length, v.cabecera.total);
    }

    function resumen(n, total) {
        var c = vista.querySelector('[data-av-contador]');
        if (c) { c.textContent = n; c.hidden = !n; }
        var rc = vista.querySelector('[data-av-resumen-cant]');
        var rt = vista.querySelector('[data-av-resumen-total]');
        if (rc) rc.textContent = n ? n + (n === 1 ? ' producto' : ' productos') : 'Sin productos';
        if (rt) rt.textContent = plata(total);
    }

    function cambiar(clave, cant) {
        if (!ped.items[clave]) return;
        if (!(cant > 0)) { delete ped.items[clave]; } else { ped.items[clave].cantidad = Math.round(cant * 100) / 100; }
        ped.sucio = true;
        guardarLocal();
        valorizar();
    }

    function guardar(confirmar) {
        var items = itemsLista();
        if (!items.length) { mostrarAviso('Agregá al menos un producto.', 'error'); return; }
        var botones = vista.querySelectorAll('[data-av-guardar], [data-av-confirmar]');
        botones.forEach(function (b) { b.disabled = true; });
        api('guardar', {entidad_id: ped.cliente.entidad_id, sucursal_id: ped.sucursal, items: JSON.stringify(items), descuento_manual_pct: ped.manual,
                        observaciones: ped.observaciones, pedido_id: ped.pedido_id || 0, confirmar: confirmar ? 1 : 0}, 'POST')
            .then(function (j) {
                ped.pedido_id = j.data.venta_pedido_id;
                ped.sucio = false;
                if (j.data.confirmado) {
                    borrarLocal(ped.cliente.entidad_id);
                    var entrega = j.data.entrega ? ' Entrega estimada: ' + j.data.entrega.dia + ' ' + j.data.entrega.fecha.split('-').reverse().join('/') + '.' : '';
                    mostrarAviso('Pedido confirmado N° ' + j.data.comprobante_nro + '.' + entrega, 'ok');
                    verClientes();
                } else {
                    guardarLocal();
                    mostrarAviso(j.message, confirmar ? 'error' : 'ok');
                }
            })
            .catch(function (e) { mostrarAviso(e.message, 'error'); })
            .finally(function () { botones.forEach(function (b) { b.disabled = false; }); });
    }

    // ------------------------------------------------------------------ Escáner (BarcodeDetector; si no hay, búsqueda a mano)
    var flujo = null, detector = null, escaneando = false;
    function abrirEscaner() {
        if (!('BarcodeDetector' in window) || !navigator.mediaDevices) {
            mostrarAviso('Este navegador no lee códigos con la cámara: escribí el código en el buscador.', 'info');
            vista.querySelector('[data-av-buscar]').focus();
            return;
        }
        var caja = vista.querySelector('[data-av-escaner]');
        var video = caja.querySelector('video');
        detector = new window.BarcodeDetector({formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'itf']});
        navigator.mediaDevices.getUserMedia({video: {facingMode: 'environment'}}).then(function (s) {
            flujo = s; video.srcObject = s; caja.hidden = false; escaneando = true;
            return video.play().then(function () { leer(video); });
        }).catch(function () { mostrarAviso('No se pudo usar la cámara: escribí el código en el buscador.', 'error'); });
    }
    function leer(video) {
        if (!escaneando) return;
        detector.detect(video).then(function (codigos) {
            if (codigos.length) {
                var codigo = codigos[0].rawValue;
                cerrarEscaner();
                vista.querySelector('[data-av-buscar]').value = codigo;
                buscarProductos(codigo, true);
                return;
            }
            setTimeout(function () { leer(video); }, 250);
        }).catch(function () { setTimeout(function () { leer(video); }, 500); });
    }
    function cerrarEscaner() {
        escaneando = false;
        if (flujo) { flujo.getTracks().forEach(function (t) { t.stop(); }); flujo = null; }
        var caja = vista.querySelector('[data-av-escaner]');
        if (caja) caja.hidden = true;
    }

    // ------------------------------------------------------------------ Arranque
    btnVolver.addEventListener('click', function () { cerrarEscaner(); verClientes(); });
    aviso.addEventListener('click', function () { mostrarAviso(''); });
    var selEmpresa = raiz.querySelector('[data-av-empresa]');
    if (selEmpresa) {
        // La empresa elegida queda en la sesión (POST a index.php). Lo cargado sin guardar queda en este teléfono,
        // separado por empresa, pero se avisa antes de salir del pedido.
        selEmpresa.addEventListener('change', function () {
            if (ped && ped.sucio) {
                guardarLocal();
                if (!window.confirm('El pedido de ' + (ped.cliente.nombre || 'este cliente') + ' no está guardado en el sistema. '
                    + 'Queda en este teléfono y lo vas a ver al volver a esta empresa. ¿Cambiar de empresa?')) {
                    this.value = EMPRESA;
                    return;
                }
            }
            raiz.querySelector('[data-av-empresa-form]').submit();
        });
    }
    window.addEventListener('offline', function () { mostrarAviso('Sin conexión. Lo cargado queda guardado en este teléfono.', 'error'); });
    window.addEventListener('online', function () { mostrarAviso('Conexión recuperada.', 'ok'); });
    api('inicio').then(function (j) { inicio = j.data; verClientes(); }).catch(function (e) {
        vista.innerHTML = '<p class="av-vacio">' + esc(e.message) + '</p>';
    });
}());
