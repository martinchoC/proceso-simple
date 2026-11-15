/**
 * Armado y hojas de ruta (página 149 / 649). Vista: templates/distribucion/hojas.php.
 * Backend: distribucion_hojas_ajax.php del módulo → vdhr_despachar (core/distribucion/distribucion_hojas.php).
 * Orden de visita con Sortable (assets/js/sortablejs-1.15.0). Hoja de ruta y lista de carga se imprimen desde el navegador.
 * Uso: DistHojas.init({ ajaxUrl: 'distribucion_hojas_ajax.php', empresaIdx, empresaNombre })
 */
(function (window, $) {
    'use strict';

    var cfg = { ajaxUrl: 'distribucion_hojas_ajax.php', empresaIdx: 0, empresaNombre: '' };
    var cat = { camiones: [], zonas: [], depositos: [] };
    var disponibles = [], hojasDia = [], hoja = null, sortable = null, rendirPedido = 0;
    var ESTADOS = { 3: ['Borrador', 'bg-secondary'], 5: ['Confirmada', 'bg-primary'], 21: ['Rendida', 'bg-success'], 38: ['Anulada', 'bg-dark'] };
    var RESULTADOS = { ENTREGADO: ['Entregado', 'bg-success'], PARCIAL: ['Parcial', 'bg-warning text-dark'], RECHAZADO: ['Rechazado', 'bg-danger'], NO_VISITADO: ['No visitado', 'bg-secondary'] };

    function esc(t) {
        if (t === null || t === undefined) return '';
        return String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function num(v, d) { return window.Fmt && Fmt.numero ? Fmt.numero(parseFloat(v) || 0, d) : (parseFloat(v) || 0).toFixed(d); }
    function moneda(v) { return window.Fmt && Fmt.moneda ? Fmt.moneda(parseFloat(v) || 0) : '$' + (parseFloat(v) || 0).toFixed(2); }
    function fecha(v) { return v ? (window.Fmt && Fmt.fecha ? Fmt.fecha(v) : v) : ''; }
    function ok(m) { if (window.UiSimple) UiSimple.ok(m || 'Guardado'); }
    function error(m) { if (window.UiSimple) UiSimple.error(m, 'No se pudo completar'); else Swal.fire({ icon: 'error', text: m }); }
    function aviso(m) { if (window.UiSimple) UiSimple.aviso(m); else Swal.fire({ icon: 'warning', text: m }); }
    function hoy() { var d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
    function manana() { var d = new Date(); d.setDate(d.getDate() + 1); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
    function cliente(p) { return p.entidad_fantasia || p.entidad_nombre || ''; }
    function modal(id) { return bootstrap.Modal.getOrCreateInstance(document.getElementById(id)); }
    function estadoBadge(e) { var x = ESTADOS[parseInt(e, 10)] || ['?', 'bg-light text-dark']; return '<span class="badge ' + x[1] + '">' + x[0] + '</span>'; }
    function sinDato(p) { return (parseInt(p.sin_peso, 10) > 0 || parseInt(p.sin_volumen, 10) > 0) ? ' <i class="fas fa-triangle-exclamation text-warning" title="Hay líneas sin peso o volumen cargados en el producto o la presentación"></i>' : ''; }

    function pedir(accion, datos, metodo) {
        return $.ajax({ url: cfg.ajaxUrl, type: metodo || 'POST', dataType: 'json', data: $.extend({ accion: accion, empresa_idx: cfg.empresaIdx }, datos || {}) });
    }
    function fallo(x) { error((x && x.responseJSON && (x.responseJSON.message || x.responseJSON.error)) || 'No se pudo completar.'); }
    function grabar(accion, datos, despues) {
        return pedir(accion, datos).done(function (r) {
            if (!r || !r.success) { error((r && r.message) || 'No se pudo guardar.'); return; }
            if (r.message) ok(r.message);
            if (despues) despues(r);
        }).fail(fallo);
    }

    function opciones(lista, vacio, id, nom) {
        return (vacio !== null ? '<option value="">' + esc(vacio) + '</option>' : '') + lista.map(function (o) { return '<option value="' + o[id] + '">' + esc(nom(o)) + '</option>'; }).join('');
    }
    function nomCamion(c) { return c.nombre + (c.patente ? ' (' + c.patente + ')' : ''); }
    function nomDeposito(d) { return d.boca_nombre + (d.sucursal_nombre ? ' · ' + d.sucursal_nombre : ''); }

    // ---------------------------------------------------------------- Armado
    function cargarDisponibles() {
        $('#dhrDispTodos').prop('checked', false);
        $('#dhrAgregar').prop('disabled', true);
        return pedir('disponibles', { boca_id: $('#dhrDeposito').val() || 0, zona_id: $('#dhrZona').val() || 0 }, 'GET').done(function (r) {
            disponibles = (r && r.success && r.data) || [];
            var kg = 0, m3 = 0;
            var porZona = {};
            disponibles.forEach(function (p) { kg += parseFloat(p.peso_kg) || 0; m3 += parseFloat(p.volumen_m3) || 0; var z = p.zona_nombre || 'Sin zona'; (porZona[z] = porZona[z] || []).push(p); });
            $('#dhrDispResumen').text(disponibles.length + ' pedido(s) · ' + num(kg, 1) + ' kg · ' + num(m3, 2) + ' m³');
            if (!disponibles.length) { $('#dhrDispFilas').html('<tr><td colspan="7" class="text-muted small p-3">No hay pedidos liberados para distribución con esos filtros.</td></tr>'); return; }
            var html = '';
            Object.keys(porZona).sort().forEach(function (z) {
                var ps = porZona[z], zk = ps.reduce(function (a, p) { return a + (parseFloat(p.peso_kg) || 0); }, 0);
                html += '<tr class="table-light"><td><input type="checkbox" class="form-check-input dhr-zona-todos" data-zona="' + esc(z) + '"></td><td colspan="6" class="small fw-bold">' + esc(z) + ' <span class="text-muted fw-normal">· ' + ps.length + ' pedido(s) · ' + num(zk, 1) + ' kg</span></td></tr>';
                ps.forEach(function (p) {
                    html += '<tr><td><input type="checkbox" class="form-check-input dhr-disp" value="' + p.venta_pedido_id + '" data-zona="' + esc(z) + '"></td>'
                        + '<td class="small">N° ' + esc(p.comprobante_nro) + '<br><span class="text-muted">' + esc(fecha(p.f_entrega_estimada || p.f_emision)) + '</span></td>'
                        + '<td class="small">' + esc(cliente(p)) + '<br><span class="text-muted">' + esc(p.direccion_entrega || p.entrega_sucursal || '') + '</span>'
                        + (p.vendedor_nombre ? '<br><span class="text-muted"><i class="fas fa-user-tie me-1"></i>' + esc(p.vendedor_nombre) + '</span>' : '') + '</td>'
                        + '<td class="small">' + esc(p.zona_nombre || '—') + '</td>'
                        + '<td class="text-end small">' + num(p.peso_kg, 1) + sinDato(p) + '</td><td class="text-end small">' + num(p.volumen_m3, 3) + '</td>'
                        + '<td class="text-end small">' + moneda(p.total) + '</td></tr>';
                });
            });
            $('#dhrDispFilas').html(html);
        }).fail(fallo);
    }

    function barra(valor, capacidad, unidad, dec) {
        valor = parseFloat(valor) || 0;
        capacidad = parseFloat(capacidad) || 0;
        if (!capacidad) return '<div class="small text-muted">' + num(valor, dec) + ' ' + unidad + ' (sin capacidad cargada)</div>';
        var pct = Math.round(valor / capacidad * 100), clase = pct > 100 ? 'bg-danger' : (pct > 85 ? 'bg-warning' : 'bg-success');
        return '<div class="small d-flex justify-content-between"><span>' + num(valor, dec) + ' / ' + num(capacidad, dec) + ' ' + unidad + '</span><span>' + pct + ' %</span></div>'
            + '<div class="progress mb-1" style="height: 6px;"><div class="progress-bar ' + clase + '" style="width: ' + Math.min(pct, 100) + '%"></div></div>';
    }

    function cargarHojasDia() {
        return pedir('hojas', { fecha: $('#dhrFecha').val() }, 'GET').done(function (r) {
            hojasDia = ((r && r.success && r.data) || []).filter(function (h) { return [3, 5].indexOf(parseInt(h.tabla_estado_registro_id, 10)) >= 0; });
            $('#dhrDestino').html(hojasDia.length ? hojasDia.map(function (h) {
                return '<option value="' + h.hoja_id + '">Hoja ' + h.numero + ' · ' + esc(h.camion_nombre || 'sin camión') + '</option>';
            }).join('') : '<option value="">Sin hojas: cree una</option>');
            $('#dhrHojasDia').html(hojasDia.length ? hojasDia.map(function (h) {
                return '<div class="border rounded p-2 mb-2"><div class="d-flex align-items-center gap-2 mb-1"><strong class="small">Hoja ' + h.numero + '</strong>' + estadoBadge(h.tabla_estado_registro_id)
                    + '<span class="small text-muted">' + esc(h.camion_nombre || 'sin camión') + (h.chofer ? ' · ' + esc(h.chofer) : '') + ' · ' + (parseInt(h.pedidos, 10) || 0) + ' pedido(s)</span>'
                    + '<button type="button" class="btn btn-sm btn-outline-primary ms-auto dhr-abrir" data-id="' + h.hoja_id + '">Abrir</button></div>'
                    + barra(h.peso_kg, h.capacidad_kg, 'kg', 0) + barra(h.volumen_m3, h.capacidad_m3, 'm³', 2) + '</div>';
            }).join('') : '<div class="text-muted small">No hay hojas activas para esa fecha.</div>');
        }).fail(fallo);
    }

    function agregarMarcados(forzar) {
        var ids = $('#dhrDispFilas .dhr-disp:checked').map(function () { return parseInt(this.value, 10); }).get();
        var destino = $('#dhrDestino').val();
        if (!destino) { aviso('Cree una hoja para ese día.'); return; }
        if (!ids.length) return;
        pedir('agregar_pedidos', { hoja_id: destino, pedidos: JSON.stringify(ids), forzar: forzar ? 1 : 0 }).done(function (r) {
            if (!r || !r.success) { error((r && r.message) || 'No se pudo agregar.'); return; }
            if (r.data && r.data.requiere_confirmar) {
                Swal.fire({ icon: 'warning', title: 'Supera la capacidad del camión', html: (r.data.avisos || []).map(esc).join('<br>') + '<br><br>¿Agregar igual?',
                    showCancelButton: true, confirmButtonText: 'Agregar igual', cancelButtonText: 'Cancelar' }).then(function (res) { if (res.isConfirmed) agregarMarcados(true); });
                return;
            }
            ok(r.message);
            cargarDisponibles(); cargarHojasDia();
        }).fail(fallo);
    }

    function nuevaHoja() {
        $('#dhrNFecha').val($('#dhrFecha').val() || manana());
        $('#dhrNCamion').html(opciones(cat.camiones, 'Elegir camión…', 'camion_id', nomCamion));
        $('#dhrNDeposito').html(opciones(cat.depositos, 'El del camión', 'boca_id', nomDeposito)).val($('#dhrDeposito').val() || '');
        $('#dhrNChofer').val('');
        modal('dhrModalNueva').show();
    }
    function crearHoja() {
        grabar('hoja_crear', { fecha_reparto: $('#dhrNFecha').val(), camion_id: $('#dhrNCamion').val() || 0, chofer: $.trim($('#dhrNChofer').val()), boca_id: $('#dhrNDeposito').val() || 0 },
            function () { modal('dhrModalNueva').hide(); $('#dhrFecha').val($('#dhrNFecha').val()); cargarHojasDia(); });
    }

    // ---------------------------------------------------------------- Una hoja
    function abrirHoja(id) {
        return pedir('hoja', { hoja_id: id }, 'GET').done(function (r) {
            if (!r || !r.success) { error((r && r.message) || 'No se pudo abrir.'); return; }
            hoja = r.data;
            pintarHoja();
            bootstrap.Tab.getOrCreateInstance(document.getElementById('dhrTabHojaBtn')).show();
        }).fail(fallo);
    }

    function pintarHoja() {
        var est = parseInt(hoja.tabla_estado_registro_id, 10), editable = est === 3 || est === 5;
        $('#dhrHojaTitulo').text('Hoja de ruta N° ' + hoja.numero + ' · ' + fecha(hoja.fecha_reparto));
        $('#dhrHojaEstado').html(estadoBadge(est));
        $('#dhrHojaCabecera').toggleClass('d-none', !editable);
        $('#dhrHFecha').val(hoja.fecha_reparto);
        $('#dhrHCamion').html(opciones(cat.camiones, 'Sin camión', 'camion_id', nomCamion)).val(hoja.camion_id ? String(hoja.camion_id) : '');
        $('#dhrHDeposito').html(opciones(cat.depositos, '—', 'boca_id', nomDeposito)).val(hoja.boca_id ? String(hoja.boca_id) : '');
        $('#dhrHChofer').val(hoja.chofer || '');
        $('#dhrHObs').val(hoja.observaciones || '');
        $('#dhrHojaCapacidad').html('<div class="row"><div class="col-md-6">' + barra(hoja.peso_kg, hoja.capacidad_kg, 'kg', 0) + '</div><div class="col-md-6">' + barra(hoja.volumen_m3, hoja.capacidad_m3, 'm³', 2) + '</div></div>');
        var acc = '<button type="button" class="btn btn-sm btn-outline-secondary" id="dhrImprimirHoja"><i class="fas fa-print me-1"></i>Hoja de ruta</button>'
            + '<button type="button" class="btn btn-sm btn-outline-secondary" id="dhrImprimirCarga"><i class="fas fa-boxes-stacked me-1"></i>Lista de carga</button>';
        if (editable) acc += '<button type="button" class="btn btn-sm btn-outline-primary" id="dhrGuardarCab" data-funcion="editar"><i class="fas fa-save me-1"></i>Guardar datos</button>';
        if (est === 3) acc += '<button type="button" class="btn btn-sm btn-primary" id="dhrConfirmar" data-funcion="confirmar"><i class="fas fa-check me-1"></i>Confirmar</button>';
        if (est === 5) acc += '<button type="button" class="btn btn-sm btn-success" id="dhrCerrarRend" data-funcion="rendir"><i class="fas fa-flag-checkered me-1"></i>Cerrar rendición</button>';
        if (editable) acc += '<button type="button" class="btn btn-sm btn-outline-danger" id="dhrAnular" data-funcion="inhabilitar"><i class="fas fa-ban me-1"></i>Anular</button>';
        $('#dhrHojaAcciones').html(acc);
        $('#dhrOrdenAyuda').toggleClass('d-none', !editable);
        var filas = (hoja.pedidos || []).map(function (p, i) {
            var res = p.resultado ? RESULTADOS[p.resultado] : null;
            var rend = res ? '<span class="badge ' + res[1] + '">' + res[0] + '</span>' + (p.cobrado_importe ? ' <span class="small">' + moneda(p.cobrado_importe) + (p.cobrado_medio ? ' ' + esc(p.cobrado_medio) : '') + '</span>' : '') + (p.motivo ? '<br><span class="small text-muted">' + esc(p.motivo) + '</span>' : '') : '';
            var kg = est === 3 ? p.peso_kg : (p.foto_peso !== null ? p.foto_peso : p.peso_kg);
            var m3 = est === 3 ? p.volumen_m3 : (p.foto_volumen !== null ? p.foto_volumen : p.volumen_m3);
            var botones = (est === 5 ? '<button type="button" class="btn btn-sm btn-outline-success dhr-rendir" data-id="' + p.venta_pedido_id + '" data-funcion="rendir" title="Rendir"><i class="fas fa-clipboard-check"></i></button> ' : '')
                + (editable ? '<button type="button" class="btn btn-sm btn-outline-danger dhr-quitar" data-id="' + p.venta_pedido_id + '" data-funcion="editar" title="Sacar de la hoja"><i class="fas fa-times"></i></button>' : '');
            return '<tr data-id="' + p.venta_pedido_id + '"><td class="text-muted' + (editable ? ' dhr-asa" style="cursor: grab;' : '') + '">' + (editable ? '<i class="fas fa-grip-vertical"></i>' : '') + '</td>'
                + '<td class="dhr-orden">' + (i + 1) + '</td><td class="small">N° ' + esc(p.comprobante_nro) + '</td>'
                + '<td class="small">' + esc(cliente(p)) + '<br><span class="text-muted">' + esc(p.direccion_entrega || p.entrega_sucursal || '') + (p.zona_nombre ? ' · ' + esc(p.zona_nombre) : '') + '</span></td>'
                + '<td class="text-end small">' + num(kg, 1) + sinDato(p) + '</td><td class="text-end small">' + num(m3, 3) + '</td><td class="text-end small">' + moneda(p.total) + '</td>'
                + '<td>' + rend + '</td><td class="text-nowrap">' + botones + '</td></tr>';
        });
        $('#dhrHojaFilas').html(filas.length ? filas.join('') : '<tr><td colspan="9" class="text-muted small p-3">La hoja no tiene pedidos: agréguelos desde Armado.</td></tr>');
        if (sortable) { sortable.destroy(); sortable = null; }
        if (editable && window.Sortable && filas.length > 1) {
            sortable = Sortable.create(document.getElementById('dhrHojaFilas'), { handle: '.dhr-asa', animation: 150, onEnd: guardarOrden });
        }
    }

    function guardarOrden() {
        var ids = $('#dhrHojaFilas tr[data-id]').map(function () { return parseInt($(this).data('id'), 10); }).get();
        $('#dhrHojaFilas .dhr-orden').each(function (i) { $(this).text(i + 1); });
        pedir('ordenar', { hoja_id: hoja.hoja_id, pedidos: JSON.stringify(ids) }).done(function (r) { if (!r || !r.success) { error((r && r.message) || 'No se guardó el orden.'); abrirHoja(hoja.hoja_id); } }).fail(fallo);
    }

    function accionHoja(accion, pregunta, despues) {
        var correr = function () { grabar(accion, { hoja_id: hoja.hoja_id }, function (r) { if (despues) despues(r); abrirHoja(hoja.hoja_id); cargarHojasDia(); cargarDisponibles(); }); };
        if (!pregunta) { correr(); return; }
        Swal.fire({ icon: 'question', title: pregunta, showCancelButton: true, confirmButtonText: 'Sí', cancelButtonText: 'Cancelar' }).then(function (res) { if (res.isConfirmed) correr(); });
    }

    function abrirRendir(id) {
        var p = (hoja.pedidos || []).find(function (x) { return String(x.venta_pedido_id) === String(id); });
        if (!p) return;
        rendirPedido = p.venta_pedido_id;
        $('#dhrRPedido').text('N° ' + p.comprobante_nro + ' · ' + cliente(p));
        $('#dhrRResultado').val(p.resultado || 'ENTREGADO');
        $('#dhrRMotivo').val(p.motivo || '');
        $('#dhrRImporte').val(p.cobrado_importe || '');
        $('#dhrRMedio').val(p.cobrado_medio || '');
        modal('dhrModalRendir').show();
    }
    function guardarRendir() {
        grabar('rendir_pedido', { hoja_id: hoja.hoja_id, venta_pedido_id: rendirPedido, resultado: $('#dhrRResultado').val(), motivo: $.trim($('#dhrRMotivo').val()),
            cobrado_importe: $('#dhrRImporte').val(), cobrado_medio: $.trim($('#dhrRMedio').val()) }, function () { modal('dhrModalRendir').hide(); abrirHoja(hoja.hoja_id); });
    }

    // ---------------------------------------------------------------- Impresión
    function imprimir(titulo, cuerpo) {
        var w = window.open('', '_blank');
        if (!w) { aviso('El navegador bloqueó la ventana de impresión.'); return; }
        w.document.write('<!doctype html><html><head><meta charset="utf-8"><title>' + esc(titulo) + '</title><style>'
            + 'body{font-family:Arial,sans-serif;font-size:12px;margin:16px}h1{font-size:16px;margin:0 0 4px}table{width:100%;border-collapse:collapse;margin-top:8px}'
            + 'th,td{border:1px solid #999;padding:4px;vertical-align:top}th{background:#eee;text-align:left}.d{text-align:right}.m{color:#555;font-size:11px}.firma{height:28px}'
            + '</style></head><body>' + cuerpo + '<script>window.onload=function(){window.print();}<\/script></body></html>');
        w.document.close();
    }
    function cabeceraImpresion(titulo) {
        return '<h1>' + esc(titulo) + ' · Hoja N° ' + hoja.numero + '</h1><div class="m">' + esc(cfg.empresaNombre) + ' · Reparto del ' + esc(fecha(hoja.fecha_reparto))
            + ' · Camión: ' + esc(hoja.camion_nombre || '—') + (hoja.patente ? ' (' + esc(hoja.patente) + ')' : '') + ' · Chofer: ' + esc(hoja.chofer || '—')
            + ' · Depósito: ' + esc(hoja.boca_nombre || '—') + '</div>';
    }
    function imprimirHoja() {
        var total = 0;
        var filas = (hoja.pedidos || []).map(function (p, i) {
            total += parseFloat(p.total) || 0;
            return '<tr><td>' + (i + 1) + '</td><td>N° ' + esc(p.comprobante_nro) + '</td><td>' + esc(cliente(p)) + '<div class="m">' + esc(p.direccion_entrega || p.entrega_sucursal || '')
                + (p.sucursal_telefono ? ' · Tel. ' + esc(p.sucursal_telefono) : '') + (p.vendedor_nombre ? ' · Vend. ' + esc(p.vendedor_nombre) : '') + '</div>'
                + (p.observaciones ? '<div class="m">' + esc(p.observaciones) + '</div>' : '') + '</td><td>' + esc(p.condicion_pago || '') + '</td><td class="d">' + moneda(p.total) + '</td>'
                + '<td class="firma"></td><td class="firma"></td></tr>';
        }).join('');
        imprimir('Hoja de ruta ' + hoja.numero, cabeceraImpresion('Hoja de ruta')
            + '<table><thead><tr><th>#</th><th>Pedido</th><th>Cliente y entrega</th><th>Condición</th><th class="d">Importe</th><th>Cobrado</th><th>Firma / observaciones</th></tr></thead><tbody>'
            + filas + '</tbody><tfoot><tr><th colspan="4" class="d">Total</th><th class="d">' + moneda(total) + '</th><th></th><th></th></tr></tfoot></table>');
    }
    function imprimirCarga() {
        pedir('lista_carga', { hoja_id: hoja.hoja_id }, 'GET').done(function (r) {
            var filas = (r && r.success && r.data) || [];
            imprimir('Lista de carga ' + hoja.numero, cabeceraImpresion('Lista de carga') + '<div class="m">' + (hoja.pedidos || []).length + ' pedido(s) · ' + num(hoja.peso_kg, 1) + ' kg · ' + num(hoja.volumen_m3, 2) + ' m³</div>'
                + '<table><thead><tr><th>Código</th><th>Producto</th><th>Presentación</th><th class="d">Bultos</th><th class="d">Unidades</th><th>Cargado</th></tr></thead><tbody>'
                + filas.map(function (f) {
                    return '<tr><td>' + esc(f.producto_codigo || '') + '</td><td>' + esc(f.producto_nombre || '') + '</td><td>' + esc(f.presentacion_nombre || 'Unidad') + '</td>'
                        + '<td class="d">' + (f.bultos !== null ? num(f.bultos, 2) : '') + '</td><td class="d">' + num(f.unidades, 2) + '</td><td></td></tr>';
                }).join('') + '</tbody></table>');
        }).fail(fallo);
    }

    // ---------------------------------------------------------------- Listado
    function listarHojas() {
        pedir('hojas', { fecha: $('#dhrDesde').val(), hasta: $('#dhrHasta').val(), incluir_anuladas: $('#dhrAnuladas').is(':checked') ? 1 : 0 }, 'GET').done(function (r) {
            var hs = (r && r.success && r.data) || [];
            $('#dhrHojasFilas').html(hs.length ? hs.map(function (h) {
                return '<tr><td>' + h.numero + '</td><td>' + esc(fecha(h.fecha_reparto)) + '</td><td>' + esc(h.camion_nombre || '—') + '</td><td>' + esc(h.chofer || '') + '</td>'
                    + '<td class="text-end">' + (parseInt(h.pedidos, 10) || 0) + '</td><td class="text-end">' + num(h.peso_kg, 1) + '</td><td class="text-end">' + num(h.volumen_m3, 2) + '</td>'
                    + '<td>' + estadoBadge(h.tabla_estado_registro_id) + '</td><td><button type="button" class="btn btn-sm btn-outline-primary dhr-abrir" data-id="' + h.hoja_id + '">Abrir</button></td></tr>';
            }).join('') : '<tr><td colspan="9" class="text-muted small p-3">Sin hojas en esas fechas.</td></tr>');
        }).fail(fallo);
    }

    // ---------------------------------------------------------------- Inicio
    function init(opciones_) {
        $.extend(cfg, opciones_ || {});
        $('#dhrFecha').val(manana());
        $('#dhrDesde').val(hoy());
        $('#dhrHasta').val(manana());
        pedir('catalogos', {}, 'GET').done(function (r) {
            if (r && r.success) cat = r.data;
            $('#dhrDeposito').html(opciones(cat.depositos || [], 'Todos los depósitos', 'boca_id', nomDeposito));
            $('#dhrZona').html('<option value="0">Todas las zonas</option><option value="-1">Sin zona</option>' + (cat.zonas || []).map(function (z) { return '<option value="' + z.zona_id + '">' + esc(z.nombre) + '</option>'; }).join(''));
            cargarDisponibles(); cargarHojasDia();
        });

        $('#dhrFecha').on('change', cargarHojasDia);
        $('#dhrDeposito, #dhrZona').on('change', cargarDisponibles);
        $('#dhrNuevaHoja').on('click', nuevaHoja);
        $('#dhrNGuardar').on('click', crearHoja);
        $('#dhrDispTodos').on('change', function () { $('#dhrDispFilas input[type=checkbox]').prop('checked', this.checked); $('#dhrAgregar').prop('disabled', !this.checked); });
        $(document).on('change', '.dhr-zona-todos', function () { $('#dhrDispFilas .dhr-disp[data-zona="' + $(this).data('zona') + '"]').prop('checked', this.checked); $('#dhrAgregar').prop('disabled', !$('#dhrDispFilas .dhr-disp:checked').length); });
        $(document).on('change', '.dhr-disp', function () { $('#dhrAgregar').prop('disabled', !$('#dhrDispFilas .dhr-disp:checked').length); });
        $('#dhrAgregar').on('click', function () { agregarMarcados(false); });
        $(document).on('click', '.dhr-abrir', function () { abrirHoja($(this).data('id')); });

        $(document).on('click', '#dhrGuardarCab', function () {
            grabar('hoja_editar', { hoja_id: hoja.hoja_id, fecha_reparto: $('#dhrHFecha').val(), camion_id: $('#dhrHCamion').val() || 0, chofer: $.trim($('#dhrHChofer').val()),
                boca_id: $('#dhrHDeposito').val() || 0, observaciones: $.trim($('#dhrHObs').val()) }, function () { abrirHoja(hoja.hoja_id); cargarHojasDia(); });
        });
        $(document).on('click', '#dhrConfirmar', function () { accionHoja('confirmar', 'Confirmar la hoja ' + hoja.numero + '?'); });
        $(document).on('click', '#dhrAnular', function () { accionHoja('anular', 'Anular la hoja ' + hoja.numero + '? Sus pedidos vuelven al armado.'); });
        $(document).on('click', '#dhrCerrarRend', function () {
            accionHoja('cerrar_rendicion', 'Cerrar la rendición de la hoja ' + hoja.numero + '?', function (r) {
                var d = (r && r.data) || {}, res = d.resultados || {};
                Swal.fire({ icon: 'success', title: 'Hoja rendida', html: 'Entregados: ' + (res.ENTREGADO || 0) + ' · Parciales: ' + (res.PARCIAL || 0) + ' · Rechazados: ' + (res.RECHAZADO || 0)
                    + ' · No visitados: ' + (res.NO_VISITADO || 0) + '<br>Cobrado: ' + moneda(d.cobrado) + '<br><span class="small text-muted">Los rechazados y no visitados vuelven al armado.</span>' });
            });
        });
        $(document).on('click', '.dhr-quitar', function () { var id = $(this).data('id'); grabar('quitar_pedido', { hoja_id: hoja.hoja_id, venta_pedido_id: id }, function () { abrirHoja(hoja.hoja_id); cargarDisponibles(); cargarHojasDia(); }); });
        $(document).on('click', '.dhr-rendir', function () { abrirRendir($(this).data('id')); });
        $('#dhrRGuardar').on('click', guardarRendir);
        $(document).on('click', '#dhrImprimirHoja', imprimirHoja);
        $(document).on('click', '#dhrImprimirCarga', imprimirCarga);

        $('#dhrTabHojasBtn').on('shown.bs.tab', listarHojas);
        $('#dhrDesde, #dhrHasta, #dhrAnuladas').on('change', listarHojas);
    }

    window.DistHojas = { init: init };
})(window, jQuery);
