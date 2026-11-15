/**
 * Compra por presentación (doc 27): selector "Unidad / Caja x 12 / …" en la línea de la orden de compra y de la
 * factura de proveedor. Solo se carga con el indicador (usa_compra_presentaciones). Backend: acción
 * cpre_presentaciones del _ajax.php de la pantalla → core/compras_presentaciones.php.
 *
 * Con una presentación elegida, la cantidad y el precio del formulario de la línea están EN ESA PRESENTACIÓN
 * (cajas, precio de la caja). ComprasPres.convertir(linea) pasa la línea a unidad base (lo que siempre se grabó) y
 * le agrega presentacion_id / _cantidad / _precio, que el servidor vuelve a validar y recalcular.
 *
 * Uso: ComprasPres.init({ ajaxUrl, empresaIdx, cantidad: '#producto_cantidad', precio: '#producto_precio', onCambio });
 *      ComprasPres.cargar(productoId) al elegir un producto (preselecciona la de compra por defecto);
 *      ComprasPres.cargar(productoId, presentacionId|null, cantidadPres, precioPres, true) al editar una línea;
 *      ComprasPres.convertir(detalle) antes de agregarlo; ComprasPres.etiqueta(detalle) para la tabla;
 *      ComprasPres.limpiar() al vaciar el formulario.
 */
var ComprasPres = (function ($) {
    'use strict';

    var cfg = { ajaxUrl: '', empresaIdx: 0, cantidad: '#producto_cantidad', precio: '#producto_precio', onCambio: null };
    var opciones = [], factorActual = 1, pedido = 0;

    function esc(t) { return t === null || t === undefined ? '' : String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    function num(v, d) { return window.Fmt ? Fmt.numero(v, d) : (parseFloat(v) || 0).toFixed(d); }
    function $sel() { return $('#cprePres'); }

    function asegurarSelect() {
        if (!$sel().length) {
            $(cfg.cantidad).after('<select class="form-select form-select-sm mt-1 d-none" id="cprePres" title="Presentación de compra"></select>');
            $sel().on('change', function () { cambiarFactor(factorDe($(this).val())); });
        }
    }
    function factorDe(id) {
        var o = opciones.find(function (x) { return String(x.presentacion_id) === String(id); });
        return o ? parseFloat(o.factor) || 1 : 1;
    }
    /** El precio del formulario sigue a la presentación (por unidad × factor); la cantidad no se toca. */
    function cambiarFactor(nuevo) {
        var $p = $(cfg.precio), precio = parseFloat($p.val());
        if (!isNaN(precio) && factorActual > 0) $p.val(+(precio / factorActual * nuevo).toFixed(6));
        factorActual = nuevo;
        if (typeof cfg.onCambio === 'function') cfg.onCambio();
    }
    function pintar(seleccion) {
        asegurarSelect();
        if (!opciones.length) { $sel().addClass('d-none').html(''); factorActual = 1; return; }
        $sel().html('<option value="">Unidad</option>' + opciones.map(function (o) {
            return '<option value="' + o.presentacion_id + '">' + esc(o.presentacion_nombre) + '</option>';
        }).join('')).removeClass('d-none').val(seleccion ? String(seleccion) : '');
    }

    /**
     * Trae las presentaciones de compra del producto. Sin presentacionId: preselecciona la de compra por defecto y
     * convierte el precio que haya en el formulario (por unidad) a esa presentación. Con presentacionId (editar una
     * línea): la deja elegida y pone cantidad y precio en presentaciones.
     */
    function cargar(productoId, presentacionId, cantidadPres, precioPres, editando) {
        var este = ++pedido;
        opciones = []; factorActual = 1; pintar(null);
        if (!productoId) return;
        $.get(cfg.ajaxUrl, { accion: 'cpre_presentaciones', producto_id: productoId, empresa_idx: cfg.empresaIdx }, function (r) {
            if (este !== pedido) return; // llegó la respuesta de un producto anterior
            opciones = (r && r.success && r.data) || [];
            if (presentacionId) {
                pintar(presentacionId);
                factorActual = factorDe(presentacionId);
                if (cantidadPres !== undefined) $(cfg.cantidad).val(cantidadPres);
                if (precioPres !== undefined && precioPres !== null) $(cfg.precio).val(precioPres);
                if (typeof cfg.onCambio === 'function') cfg.onCambio();
                return;
            }
            if (editando) { pintar(null); return; } // línea por unidad: queda en Unidad
            var def = opciones.find(function (o) { return parseInt(o.es_compra_defecto, 10) === 1; });
            pintar(def ? def.presentacion_id : null);
            if (def) cambiarFactor(factorDe(def.presentacion_id));
        }, 'json');
    }

    /**
     * Línea armada por la pantalla con cantidad / precio en la presentación elegida → unidad base + foto. Recalcula
     * neto, IVA y total desde la presentación. Sin presentación elegida, la devuelve tal cual.
     */
    function convertir(d) {
        var id = $sel().length ? $sel().val() : '';
        if (!id) return d;
        var o = opciones.find(function (x) { return String(x.presentacion_id) === String(id); });
        if (!o) return d;
        var f = parseFloat(o.factor) || 1, pc = parseFloat(d.cantidad) || 0, pp = parseFloat(d.precio_unitario) || 0;
        d.presentacion_id = parseInt(id, 10);
        d.presentacion_nombre = o.presentacion_nombre;
        d.presentacion_factor = f;
        d.presentacion_cantidad = pc;
        d.presentacion_precio = pp;
        d.cantidad = +(pc * f).toFixed(4);
        d.precio_unitario = +(pp / f).toFixed(6);
        if (d.neto_gravado !== undefined) {
            d.neto_gravado = +(pc * pp).toFixed(2);
            d.iva_importe = +(d.neto_gravado * (parseFloat(d.iva_porcentaje) || 0) / 100).toFixed(2);
            d.total_linea = +(d.neto_gravado + d.iva_importe + (parseFloat(d.no_gravado) || 0) + (parseFloat(d.exento) || 0)).toFixed(2);
        }
        return d;
    }

    /** Texto para la tabla de líneas: "10 Caja x 12 a $ 9.600" (vacío si la línea es por unidad). */
    function etiqueta(d) {
        if (!d || !d.presentacion_id) return '';
        return '<br><small class="text-primary">' + num(d.presentacion_cantidad, 2) + ' ' + esc(d.presentacion_nombre)
            + (d.presentacion_precio !== null && d.presentacion_precio !== undefined ? ' a $ ' + num(d.presentacion_precio, 2) : '') + '</small>';
    }

    /** Campos de la foto de una línea que viene del servidor (para no perderlos al cargar una OC / factura). */
    function foto(d) {
        if (!d || !d.presentacion_id) return {};
        return { presentacion_id: parseInt(d.presentacion_id, 10), presentacion_nombre: d.presentacion_nombre, presentacion_factor: parseFloat(d.presentacion_factor),
                 presentacion_cantidad: parseFloat(d.presentacion_cantidad), presentacion_precio: d.presentacion_precio !== null ? parseFloat(d.presentacion_precio) : null };
    }

    function limpiar() { ++pedido; opciones = []; factorActual = 1; if ($sel().length) $sel().addClass('d-none').html(''); }
    function init(o) { $.extend(cfg, o || {}); asegurarSelect(); }

    return { init: init, cargar: cargar, convertir: convertir, etiqueta: etiqueta, foto: foto, limpiar: limpiar };
})(jQuery);
