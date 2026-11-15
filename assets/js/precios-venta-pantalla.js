/**
 * Reglas de precio de venta en las pantallas de remitos y facturas (doc 22, paso 4e).
 *
 * Las líneas directas (sin pedido ni remito de origen) piden al servidor ("resolver_descuentos") el descuento
 * automático (el mayor entre cliente, escala y promoción, con el tope de la lista). El % de la línea se arma como
 * automático + el manual que el usuario haya agregado encima: si cambia el automático (otra cantidad, otro
 * producto), se conserva el manual. El servidor controla el tope del manual al grabar.
 * Solo corre con window.PRV_ACTIVO (lo fija la página según el indicador de la empresa).
 */
var PrvPantalla = (function () {
    'use strict';

    function compone(auto, manual) {
        return Math.round((1 - (1 - auto / 100) * (1 - manual / 100)) * 10000) / 100;
    }

    // Manual que contiene un total respecto de un automático (0 si el total no lo supera)
    function manualDe(total, auto) {
        if (auto >= 100) return 0;
        var m = (1 - (1 - total / 100) / (1 - auto / 100)) * 100;
        return m > 0.005 ? Math.round(m * 100) / 100 : 0;
    }

    /**
     * Crea un resolvedor con espera (300 ms) y sin pedidos repetidos.
     * opts.url: el *_ajax.php de la pantalla.
     * opts.contexto(): {empresa_idx, entidad_id, sucursal_id, fecha}.
     * opts.lineas(): [{producto_id, presentacion_id, cantidad, ref}] (solo las directas).
     * opts.aplicar(ref, resultado): resultado = {auto_pct, regla_tipo, regla_nombre, ...} del servidor.
     * opts.listo(): opcional, después de aplicar todas.
     */
    function crear(opts) {
        var timer = null, firma = '';
        function resolver() {
            var lineas = opts.lineas() || [];
            if (!lineas.length) { firma = ''; return; }
            var ctx = opts.contexto() || {};
            var datos = {
                accion: 'resolver_descuentos', empresa_idx: ctx.empresa_idx || 0, entidad_id: ctx.entidad_id || 0,
                sucursal_id: ctx.sucursal_id || 0, fecha: ctx.fecha || '', descuento_manual_pct: 0,
                lineas: JSON.stringify(lineas.map(function (l) {
                    return { producto_id: l.producto_id, presentacion_id: l.presentacion_id || 0, cantidad: l.cantidad, descuento_manual_pct: 0 };
                }))
            };
            var f = JSON.stringify(datos);
            if (f === firma) return;
            $.post(opts.url, datos, function (r) {
                if (!r || !r.success || !r.data || !r.data.activo) return;
                firma = f;
                (r.data.lineas || []).forEach(function (res, i) {
                    if (lineas[i]) opts.aplicar(lineas[i].ref, res);
                });
                if (opts.listo) opts.listo();
            }, 'json');
        }
        return {
            programar: function () {
                if (!window.PRV_ACTIVO) return;
                clearTimeout(timer);
                timer = setTimeout(resolver, 300);
            },
            reiniciar: function () { firma = ''; }
        };
    }

    /**
     * % nuevo de una línea: con el automático anterior conocido, conserva el manual que el usuario agregó encima;
     * sin él (línea recién abierta o recién agregada), nunca baja el % que trae: lo sube al automático si hace falta.
     */
    function nuevoPct(actual, autoPrevio, auto) {
        actual = parseFloat(actual) || 0;
        auto = parseFloat(auto) || 0;
        if (autoPrevio === undefined || autoPrevio === null || autoPrevio === '') return Math.max(actual, auto);
        return compone(auto, manualDe(actual, parseFloat(autoPrevio) || 0));
    }

    // Texto corto de la regla para mostrar junto a la línea
    function etiqueta(res) {
        if (!res || !(parseFloat(res.auto_pct) > 0)) return '';
        var origen = res.regla_nombre || (res.regla_tipo === 'CLIENTE' ? 'Cliente' : '');
        return (origen ? origen + ' ' : '') + (window.Fmt ? Fmt.numero(res.auto_pct, 2) : res.auto_pct) + ' %';
    }

    return { compone: compone, manualDe: manualDe, nuevoPct: nuevoPct, crear: crear, etiqueta: etiqueta };
})();
