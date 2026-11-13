/**
 * Asistente IA (páginas 139 / 639): chat, propuestas y su confirmación. Común a gestion y gestion_normal.
 * Endpoint: window.ASIS_AJAX (asistente_ia_ajax.php del módulo). Las escrituras mandan window.ASIS_CSRF en X-CSRF-Token.
 */
(function () {
    'use strict';

    const esc = (t) => $('<div>').text(t == null ? '' : String(t)).html();
    const dinero = (v) => Fmt.moneda(Number(v) || 0);
    const num = (v) => Fmt.numero(Number(v) || 0, { maximumFractionDigits: 4 });

    let conversacionId = 0;
    let ocupado = false;
    let disponible = false;

    function pedir(accion, datos, metodo) {
        const esForm = datos instanceof FormData;
        if (esForm) {
            datos.append('accion', accion);
            datos.append('empresa_idx', window.EMPRESA_ID);
        } else {
            datos = Object.assign({ accion: accion, empresa_idx: window.EMPRESA_ID }, datos || {});
        }
        return new Promise((resolve, reject) => {
            $.ajax({
                url: window.ASIS_AJAX, method: metodo || 'POST', data: datos, dataType: 'json',
                processData: !esForm, contentType: esForm ? false : 'application/x-www-form-urlencoded; charset=UTF-8',
                headers: { 'X-CSRF-Token': window.ASIS_CSRF || '' }, timeout: 300000
            }).done((res) => (res && res.success) ? resolve(res) : reject((res && res.message) || 'No se pudo completar.'))
              .fail((xhr) => {
                  let msg = 'No se pudo conectar con el servidor.';
                  try { const j = JSON.parse(xhr.responseText); msg = j.message || msg; } catch (e) { /* respuesta no JSON */ }
                  if (xhr.statusText === 'timeout') msg = 'La IA tardó demasiado en responder. Probá de nuevo.';
                  reject(msg);
              });
        });
    }

    // ---------------------------------------------------------------------------------------------
    // Estado y conversaciones
    // ---------------------------------------------------------------------------------------------

    function cargarEstado() {
        return pedir('estado', {}, 'GET').then((res) => {
            disponible = !!res.data.disponible;
            $('#asisNoDisponible').toggleClass('d-none', disponible).text(res.data.motivo || '');
            $('#asisTexto, #asisEnviar, #asisAdjuntar').prop('disabled', !disponible);
            $('#asisUso').text(res.data.tope ? `Pedidos de hoy: ${res.data.usos_hoy} de ${res.data.tope}` : '');
        }).catch((m) => UiSimple.error(m));
    }

    function cargarConversaciones() {
        return pedir('conversaciones', {}, 'GET').then((res) => {
            const $l = $('#asisConversaciones').empty();
            if (!res.data.length) $l.append('<div class="list-group-item text-muted">Todavía no hay conversaciones.</div>');
            res.data.forEach((c) => {
                $l.append(`<div class="list-group-item list-group-item-action d-flex align-items-start gap-2 ${c.conversacion_id == conversacionId ? 'active' : ''}"
                                data-id="${c.conversacion_id}">
                               <div class="flex-grow-1 text-truncate"><div class="text-truncate">${esc(c.titulo || 'Conversación')}</div>
                               <small class="opacity-75">${esc(Fmt.fechaHora(c.fecha_actualizada))}</small></div>
                               <button type="button" class="btn btn-sm btn-link text-reset p-0 asis-archivar" title="Quitar de la lista"><i class="bi bi-x-lg"></i></button>
                           </div>`);
            });
        }).catch((m) => UiSimple.error(m));
    }

    function abrirConversacion(id) {
        conversacionId = id;
        $('#asisMensajes').html('<div class="asis-pensando"><span class="spinner-border me-2"></span>Cargando…</div>');
        $('#asisLateral').removeClass('abierto');
        pedir('mensajes', { conversacion_id: id }, 'GET').then((res) => {
            $('#asisMensajes').empty();
            pintar(res.data);
            cargarConversaciones();
        }).catch((m) => UiSimple.error(m));
    }

    function nuevaConversacion() {
        conversacionId = 0;
        $('#asisMensajes').empty().append($('#asisBienvenidaTpl').html());
        $('#asisConversaciones .active').removeClass('active');
        $('#asisLateral').removeClass('abierto');
        $('#asisTexto').trigger('focus');
    }

    // ---------------------------------------------------------------------------------------------
    // Mensajes
    // ---------------------------------------------------------------------------------------------

    const NOMBRES_HERR = {
        buscar_entidades: 'Buscó clientes / proveedores', buscar_productos: 'Buscó productos',
        opciones_comprobante: 'Consultó puntos de venta', catalogos_alta_entidad: 'Consultó catálogos',
        buscar_localidades: 'Buscó localidades', consultar_padron_arca: 'Consultó el padrón de ARCA', proponer_factura_venta: 'Armó una factura',
        proponer_remito_venta: 'Armó un remito', proponer_alta_entidad: 'Armó un alta',
        remitos_pendientes_facturar: 'Buscó remitos pendientes', ofrecer_opciones: 'Preparó opciones',
        buscar_facturas_emitidas: 'Buscó facturas emitidas', proponer_copia_facturas: 'Armó la copia de facturas'
    };

    function pintar(data) {
        const $m = $('#asisMensajes');
        $('#asisBienvenida').remove();
        let herr = [];
        const volcarHerr = () => {
            if (herr.length) $m.append(`<div class="asis-herr"><i class="bi bi-gear me-1"></i>${esc([...new Set(herr)].join(' · '))}</div>`);
            herr = [];
        };
        (data.mensajes || []).forEach((msg) => {
            if (msg.rol === 'HERRAMIENTA') {
                herr.push(NOMBRES_HERR[msg.herramienta] || msg.herramienta);
                const p = msg.propuesta_id && data.propuestas && data.propuestas[msg.propuesta_id];
                if (p) { volcarHerr(); $m.append(tarjetaPropuesta(p)); }
                return;
            }
            volcarHerr();
            if (msg.rol === 'USUARIO') {
                cerrarOpciones();
                $m.append(`<div class="asis-msg usuario">${msg.archivo_nombre ? `<span class="asis-adjunto"><i class="bi bi-paperclip"></i> ${esc(msg.archivo_nombre)}</span>` : ''}${esc(msg.texto)}</div>`);
            } else if (msg.rol === 'OPCIONES') {
                $m.append(tarjetaOpciones(msg));
            } else if (msg.rol === 'ASISTENTE') {
                $m.append(`<div class="asis-msg asistente">${esc(msg.texto).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')}</div>`);
            } else if (msg.rol === 'ERROR') {
                $m.append(`<div class="asis-msg error"><i class="bi bi-exclamation-triangle me-1"></i>${esc(msg.texto)}</div>`);
            } else if (msg.rol === 'EVENTO') {
                $m.append(`<div class="asis-evento"><i class="bi bi-info-circle me-1"></i>${esc(msg.texto)}</div>`);
            }
        });
        volcarHerr();
        $m.scrollTop($m[0].scrollHeight);
    }

    // Opciones que ofreció la IA (herramienta ofrecer_opciones): botones o, si es múltiple, casillas + "Elegir"
    function tarjetaOpciones(msg) {
        let d = {};
        try { d = JSON.parse(msg.opciones || '{}'); } catch (e) { /* datos dañados: sin opciones */ }
        const ops = d.opciones || [];
        const cuerpo = d.multiple
            ? ops.map((o, i) => `<div class="form-check"><input class="form-check-input" type="checkbox" id="asisOp${msg.mensaje_id}_${i}" value="${esc(o.texto)}">
                <label class="form-check-label" for="asisOp${msg.mensaje_id}_${i}">${esc(o.texto)}${o.detalle ? ` <span class="text-muted small">· ${esc(o.detalle)}</span>` : ''}</label></div>`).join('')
              + '<button type="button" class="btn btn-primary btn-sm mt-2 asis-opciones-elegir"><i class="bi bi-check-lg me-1"></i>Elegir</button>'
            : '<div class="d-flex flex-wrap gap-2">' + ops.map((o) => `<button type="button" class="btn btn-light border text-start asis-opcion" data-texto="${esc(o.texto)}">
                ${esc(o.texto)}${o.detalle ? `<div class="small text-muted">${esc(o.detalle)}</div>` : ''}</button>`).join('') + '</div>';
        return `<div class="card asis-prop asis-opciones"><div class="card-body py-2"><div class="mb-2 fw-semibold">${esc(msg.texto)}</div>${cuerpo}</div></div>`;
    }

    // Una vez respondidas, las opciones quedan como registro y no se pueden volver a usar
    function cerrarOpciones() {
        $('#asisMensajes .asis-opciones').find('button, input').prop('disabled', true);
    }

    function elegir(texto) {
        if (ocupado || !disponible || !texto) return;
        $('#asisTexto').val(texto);
        enviar();
    }

    function estadoPropuesta(p) {
        if (p.estado === 'CONFIRMADA') {
            return `<span class="badge text-bg-success me-2">Guardado</span>${esc(p.mensaje || '')}`
                + (p.url ? ` <a href="${esc(p.url)}" target="_blank" rel="noopener">Abrir</a>` : '');
        }
        if (p.estado === 'DESCARTADA') return '<span class="badge text-bg-secondary">Descartada</span>';
        return `<button type="button" class="btn btn-primary btn-sm asis-confirmar" data-id="${p.propuesta_id}"><i class="bi bi-check-lg me-1"></i>Confirmar y guardar en Borrador</button>
                <button type="button" class="btn btn-light btn-sm asis-descartar" data-id="${p.propuesta_id}">Descartar</button>`;
    }

    function tarjetaPropuesta(p) {
        const d = p.datos || {};
        let cuerpo = '';
        let titulo = '';
        if (p.tipo === 'ALTA_ENTIDAD') {
            const roles = [d.es_cliente ? 'Cliente' : null, d.es_proveedor ? 'Proveedor' : null].filter(Boolean).join(' y ');
            titulo = d.entidad_existente_id ? `Agregar rol de ${roles} a ${d.entidad_existente}` : `Alta de ${roles}`;
            const fila = (k, v) => v ? `<tr><th class="text-muted fw-normal pe-3">${k}</th><td>${esc(v)}</td></tr>` : '';
            cuerpo = `<table class="table table-sm mb-2"><tbody>
                ${fila('Razón social', d.razon_social)}${fila('Nombre de fantasía', d.nombre_fantasia !== d.razon_social ? d.nombre_fantasia : '')}
                ${fila('CUIT', d.cuit)}${fila('Tipo', d.tipo_persona)}${fila('Condición IVA', d.condicion_fiscal)}
                ${fila('Ingresos Brutos', [d.regimen_iibb, d.numero_iibb, d.jurisdiccion_sede].filter(Boolean).join(' · '))}
                ${fila('Domicilio', d.domicilio)}${fila('Localidad', d.localidad)}${fila('Teléfono', d.telefono)}${fila('Email', d.email)}
                ${fila('Condición de pago', d.condicion_pago)}${fila('Lista de precios', d.lista_precio)}
            </tbody></table>`;
        } else if (p.tipo === 'COPIA_FACTURAS') {
            const pct = Number(d.incremento_pct) || 0;
            titulo = `Copia de ${(d.facturas || []).length} factura(s) · ${pct > 0 ? '+' : ''}${num(pct)} % · ${esc(Fmt.fecha(d.fecha))}`;
            const filas = (d.facturas || []).map((f) => {
                const det = (f.lineas || []).map((l) => `<div class="small text-muted">${num(l.cantidad)} x ${esc(l.producto_nombre)} a ${dinero(l.precio_unitario)}`
                    + `${Number(l.descuento_pct) ? ' (−' + num(l.descuento_pct) + ' %)' : ''}${l.leyenda ? ' · ' + esc(l.leyenda) : ''}</div>`).join('');
                const res = f.venta_factura_id ? '<span class="badge text-bg-success">Guardada</span>'
                    : (f.error ? `<span class="badge text-bg-danger" title="${esc(f.error)}">No se copió</span>` : '');
                const avisos = (f.avisos || []).map((a) => `<div class="small text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i>${esc(a)}</div>`).join('');
                return `<tr><td><details><summary>${esc(f.origen_numero)} <span class="text-muted small">${esc(Fmt.fecha(f.origen_fecha))}</span></summary>${det}</details>${avisos}</td>
                    <td>${esc(f.cliente)}<div class="small text-muted">${esc(f.comprobante_tipo)} · PV ${esc(f.punto_venta)}</div></td>
                    <td class="text-end">${dinero(f.origen_total)}</td><td class="text-end">${dinero(f.totales.total)}</td><td>${res}</td></tr>`;
            }).join('');
            const omitidas = (d.omitidas || []).length
                ? '<div class="small mb-2"><strong>No se copian:</strong>' + d.omitidas.map((o) => `<div>${esc(o.origen_numero)} (${esc(o.cliente)}): ${esc(o.motivo)}</div>`).join('') + '</div>'
                : '';
            cuerpo = `<div class="table-responsive"><table class="table table-sm mb-2">
                <thead><tr><th>Factura original</th><th>Cliente / copia</th><th class="text-end">Total original</th><th class="text-end">Total copia</th><th></th></tr></thead>
                <tbody>${filas}</tbody>
                <tfoot><tr><td colspan="2" class="text-end text-muted">${(d.facturas || []).length} factura(s)</td>
                <td class="text-end">${dinero(d.totales.origen)}</td><td class="text-end fw-bold">${dinero(d.totales.total)}</td><td></td></tr></tfoot></table></div>
                ${omitidas}
                <div class="small text-muted mb-1">Precios sin IVA ${pct ? 'con el incremento aplicado' : 'iguales a los originales'}${Number(d.redondeo) > 0.01 ? ', redondeados a ' + dinero(d.redondeo) : ''}. `
                + `Las percepciones, si corresponden, se calculan al guardar.</div>`
                + ((d.reemplazos || []).length ? `<div class="small text-muted mb-1">Textos: ${d.reemplazos.map((r) => `"${esc(r.buscar)}" → "${esc(r.reemplazar)}"`).join(', ')}</div>` : '');
        } else {
            titulo = `${p.tipo === 'REMITO_VENTA' ? 'Remito' : 'Factura'} ${esc(d.comprobante_tipo)} · PV ${esc(d.punto_venta)}`;
            const lineas = (d.lineas || []).map((l) => `<tr>
                <td>${esc(l.producto_codigo)} ${esc(l.producto_nombre)}${l.remito ? `<div class="small text-muted"><i class="bi bi-truck me-1"></i>${esc(l.remito)}</div>` : ''}${l.leyenda ? `<div class="small text-muted">${esc(l.leyenda)}</div>` : ''}</td>
                <td class="text-end">${num(l.cantidad)}</td>
                <td class="text-end">${dinero(l.precio_unitario)}${l.precio_manual ? ' <i class="bi bi-pencil small text-muted" title="Precio indicado en el pedido"></i>' : ''}</td>
                <td class="text-end">${Number(l.descuento_pct) ? num(l.descuento_pct) + ' %' : ''}</td>
                <td class="text-end">${dinero(l.total)}</td></tr>`).join('');
            // Varios puntos de venta posibles para ese tipo: se elige acá antes de confirmar
            const alt = d.pv_alternativas || [];
            const selectorPv = (p.estado === 'PENDIENTE' && alt.length > 1)
                ? `<div class="d-flex align-items-center gap-2 mb-2"><label class="small text-muted text-nowrap" for="asisPv${p.propuesta_id}">Punto de venta</label>
                   <select class="form-select form-select-sm asis-pv" id="asisPv${p.propuesta_id}" style="max-width: 360px">`
                  + alt.map((a) => `<option value="${a.punto_venta_id}" ${Number(a.punto_venta_id) === Number(d.punto_venta_id) ? 'selected' : ''}>${esc(a.texto)}</option>`).join('')
                  + '</select></div>'
                : '';
            cuerpo = selectorPv + `<div class="mb-2"><strong>${esc(d.cliente)}</strong>${d.sucursal_cliente ? ' · ' + esc(d.sucursal_cliente) : ''} · ${esc(Fmt.fecha(d.fecha))}</div>
                <div class="table-responsive"><table class="table table-sm mb-2">
                <thead><tr><th>Producto</th><th class="text-end">Cant.</th><th class="text-end">Precio s/IVA</th><th class="text-end">Desc.</th><th class="text-end">Total</th></tr></thead>
                <tbody>${lineas}</tbody>
                <tfoot><tr><td colspan="4" class="text-end text-muted">Neto ${dinero(d.totales.neto)} · IVA ${dinero(d.totales.iva)}</td>
                <td class="text-end fw-bold">${dinero(d.totales.total)}</td></tr></tfoot></table></div>
                ${p.tipo === 'FACTURA_VENTA' ? '<div class="small text-muted mb-1">Las percepciones, si corresponden, se calculan al guardar.</div>' : ''}
                ${d.observaciones ? `<div class="small mb-2">Observaciones: ${esc(d.observaciones)}</div>` : ''}`;
        }
        const avisos = (d.avisos || []).map((a) => `<div class="small text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i>${esc(a)}</div>`).join('');
        return `<div class="card asis-prop" data-propuesta="${p.propuesta_id}">
            <div class="card-header py-2"><i class="bi bi-file-earmark-text me-1"></i>${titulo}</div>
            <div class="card-body py-2">${cuerpo}${avisos}<div class="asis-prop-estado mt-2">${estadoPropuesta(p)}</div></div></div>`;
    }

    // ---------------------------------------------------------------------------------------------
    // Envío
    // ---------------------------------------------------------------------------------------------

    function enviar() {
        if (ocupado || !disponible) return;
        const texto = $('#asisTexto').val().trim();
        const archivo = $('#asisArchivo')[0].files[0];
        if (!texto && !archivo) return;
        const fd = new FormData();
        fd.append('texto', texto);
        fd.append('conversacion_id', conversacionId);
        if (archivo) fd.append('archivo', archivo);

        ocupado = true;
        $('#asisEnviar, #asisAdjuntar').prop('disabled', true);
        const $m = $('#asisMensajes');
        $('#asisBienvenida').remove();
        cerrarOpciones();
        const $tmp = $(`<div class="asis-msg usuario">${archivo ? `<span class="asis-adjunto"><i class="bi bi-paperclip"></i> ${esc(archivo.name)}</span>` : ''}${esc(texto)}</div>`);
        const $pensando = $('<div class="asis-pensando mb-2"><span class="spinner-border me-2"></span>Pensando…</div>');
        $m.append($tmp, $pensando).scrollTop($m[0].scrollHeight);
        $('#asisTexto').val('');
        quitarArchivo();

        pedir('enviar', fd).then((res) => {
            $tmp.remove();
            $pensando.remove();
            const nueva = !conversacionId;
            conversacionId = res.data.conversacion_id;
            pintar(res.data);
            if (nueva) cargarConversaciones();
        }).catch((m) => {
            $pensando.remove();
            $m.append(`<div class="asis-msg error"><i class="bi bi-exclamation-triangle me-1"></i>${esc(m)}</div>`);
            if (!texto && !archivo) return;
            $('#asisTexto').val(texto);
        }).finally(() => {
            ocupado = false;
            $('#asisEnviar, #asisAdjuntar').prop('disabled', !disponible);
            cargarEstado();
            $('#asisTexto').trigger('focus');
        });
    }

    function quitarArchivo() {
        $('#asisArchivo').val('');
        $('#asisArchivoNombre').addClass('d-none').empty();
    }

    function resolverPropuesta(accion, id, $btn) {
        $btn.closest('.asis-prop-estado').find('button').prop('disabled', true);
        const $card = $(`.asis-prop[data-propuesta="${id}"]`);
        const datos = { propuesta_id: id };
        if ($card.find('.asis-pv').length) datos.punto_venta_id = $card.find('.asis-pv').val();
        pedir(accion, datos).then((res) => {
            if (res.data.tipo === 'COPIA_FACTURAS') {
                // La tarjeta se vuelve a dibujar con el resultado de cada factura
                $card.replaceWith(tarjetaPropuesta(res.data));
                if (accion === 'confirmar') UiSimple.ok('Guardado en Borrador');
                return;
            }
            $card.find('.asis-pv').prop('disabled', true);
            if (res.data.datos && res.data.datos.punto_venta) {
                $card.find('.card-header').html(`<i class="bi bi-file-earmark-text me-1"></i>${res.data.tipo === 'REMITO_VENTA' ? 'Remito' : 'Factura'} ${esc(res.data.datos.comprobante_tipo)} · PV ${esc(res.data.datos.punto_venta)}`);
            }
            $card.find('.asis-prop-estado').html(estadoPropuesta(res.data));
            if (accion === 'confirmar') UiSimple.ok('Guardado en Borrador');
        }).catch((m) => {
            $btn.closest('.asis-prop-estado').find('button').prop('disabled', false);
            UiSimple.error(m, 'No se pudo guardar');
        });
    }

    // ---------------------------------------------------------------------------------------------
    // Eventos
    // ---------------------------------------------------------------------------------------------

    $(function () {
        $('body').append(`<template id="asisBienvenidaTpl">${$('#asisBienvenida').prop('outerHTML')}</template>`);

        $('#asisForm').on('submit', (ev) => { ev.preventDefault(); enviar(); });
        $('#asisTexto').on('keydown', (ev) => {
            if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); enviar(); }
        });
        $('#asisAdjuntar').on('click', () => $('#asisArchivo').trigger('click'));
        $('#asisArchivo').on('change', function () {
            const f = this.files[0];
            if (!f) return quitarArchivo();
            if (f.size > 5 * 1024 * 1024) { UiSimple.aviso('El archivo no puede superar 5 MB.'); return quitarArchivo(); }
            $('#asisArchivoNombre').removeClass('d-none')
                .html(`<i class="bi bi-paperclip"></i> ${esc(f.name)} <a href="#" class="ms-2" id="asisQuitarArchivo">quitar</a>`);
        });
        $(document).on('click', '#asisQuitarArchivo', (ev) => { ev.preventDefault(); quitarArchivo(); });

        $(document).on('click', '.asis-ejemplos .btn', function () {
            $('#asisTexto').val($(this).text().trim()).trigger('focus');
            if ($(this).data('adjuntar')) $('#asisArchivo').trigger('click');
        });
        $(document).on('click', '.asis-opcion', function () { elegir(String($(this).data('texto'))); });
        $(document).on('click', '.asis-opciones-elegir', function () {
            const elegidas = $(this).closest('.asis-opciones').find('input:checked').map(function () { return this.value; }).get();
            if (!elegidas.length) return UiSimple.aviso('Marcá al menos una opción.');
            elegir(elegidas.length === 1 ? elegidas[0] : 'Elijo: ' + elegidas.join('; '));
        });
        $(document).on('click', '.asis-confirmar',function () { resolverPropuesta('confirmar', $(this).data('id'), $(this)); });
        $(document).on('click', '.asis-descartar', function () { resolverPropuesta('descartar', $(this).data('id'), $(this)); });

        $('#asisConversaciones').on('click', '.list-group-item[data-id]', function (ev) {
            if ($(ev.target).closest('.asis-archivar').length) return;
            abrirConversacion($(this).data('id'));
        });
        $('#asisConversaciones').on('click', '.asis-archivar', function () {
            const id = $(this).closest('[data-id]').data('id');
            pedir('archivar', { conversacion_id: id }).then(() => {
                if (id == conversacionId) nuevaConversacion();
                cargarConversaciones();
            }).catch((m) => UiSimple.error(m));
        });
        $('#asisNueva').on('click', nuevaConversacion);
        $('#asisVerConversaciones').on('click', () => $('#asisLateral').toggleClass('abierto'));

        cargarEstado();
        cargarConversaciones();
    });
})();
