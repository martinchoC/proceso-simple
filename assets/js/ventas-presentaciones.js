/**
 * Venta por presentación en las líneas de pedidos, remitos y facturas (paso 2, doc 20).
 * Backend: core/ventas_presentaciones.php. La línea sigue en unidad base (cantidad, precio_unitario*) y, si se
 * vende en una presentación, lleva además presentacion_id / _nombre / _factor / _cantidad / _precio.
 * Sin venta por presentación ninguna línea tiene presentacion_id y nada de esto cambia los cálculos: las
 * pantallas siguen por su camino de siempre (VentaPres.esPres(d) === false).
 *
 * Global VentaPres:
 *   esPres(d)                 la línea es por presentación
 *   clave(d)                  presentacion_id o 0 (para buscar la línea de un producto + presentación)
 *   copiar(desde, hacia)      copia las cinco columnas (al cargar líneas del servidor); devuelve hacia
 *   cantidadVisible(d)        cantidad en lo que se vende (cajas o unidades)
 *   entregadoVisible(d)       cantidad_entregada en lo que se vende
 *   precioNetoVisible(d)      precio neto de lo que se vende (la caja o la unidad)
 *   fijarCantidad(d, n)       n en presentaciones: actualiza cantidad base e importes
 *   recalcular(d)             importes de una línea por presentación (descuento_general_pct, iva_porcentaje)
 *   nuevaLinea(of, n, pct)    campos de una línea por presentación desde una oferta del buscador
 *   ofertas(item)             ofertas del producto (o [] sin venta por presentación)
 *   textoOferta(of)           "Caja x 12 · disp. 3"
 */
(function (window) {
    'use strict';

    var CAMPOS = ['presentacion_id', 'presentacion_nombre', 'presentacion_factor', 'presentacion_cantidad', 'presentacion_precio'];

    function num(v) { var n = parseFloat(v); return isNaN(n) ? 0 : n; }
    function r2(v) { return Math.round((v + Number.EPSILON) * 100) / 100; }
    function r4(v) { return Math.round(v * 10000) / 10000; }
    function r6(v) { return Math.round(v * 1000000) / 1000000; }

    var VentaPres = {
        esPres: function (d) { return !!d && parseInt(d.presentacion_id, 10) > 0 && num(d.presentacion_factor) > 0; },

        clave: function (d) { return VentaPres.esPres(d) ? parseInt(d.presentacion_id, 10) : 0; },

        copiar: function (desde, hacia) {
            if (desde && parseInt(desde.presentacion_id, 10) > 0) {
                CAMPOS.forEach(function (c) { hacia[c] = desde[c]; });
                // recalcular() lo necesita aunque la pantalla no lo copie
                if (hacia.descuento_general_pct === undefined) hacia.descuento_general_pct = desde.descuento_general_pct || 0;
            }
            return hacia;
        },

        cantidadVisible: function (d) {
            return VentaPres.esPres(d) ? num(d.presentacion_cantidad) : d.cantidad;
        },

        entregadoVisible: function (d) {
            return VentaPres.esPres(d) ? r4(num(d.cantidad_entregada) / num(d.presentacion_factor)) : (parseFloat(d.cantidad_entregada) || 0);
        },

        precioNetoVisible: function (d) {
            return VentaPres.esPres(d) ? r2(num(d.precio_unitario_neto) * num(d.presentacion_factor)) : d.precio_unitario_neto;
        },

        recalcular: function (d) {
            var factor = num(d.presentacion_factor);
            var pc = num(d.presentacion_cantidad);
            var pp = num(d.presentacion_precio);
            var pct = num(d.descuento_general_pct);
            var unit = factor > 0 ? r6(pp / factor) : 0;
            d.cantidad = r4(pc * factor);
            d.precio_unitario = unit;
            d.descuento_general = r6(unit * pct / 100);
            d.precio_unitario_neto = r6(unit - d.descuento_general);
            // Igual que vpre_normalizar_lineas(): el neto sale de la presentación, no del unitario redondeado
            d.neto_gravado = r2(pc * pp * (1 - pct / 100));
            d.iva_importe = r2(d.neto_gravado * num(d.iva_porcentaje) / 100);
            d.total_linea = r2(d.neto_gravado + d.iva_importe + num(d.no_gravado) + num(d.exento));
            return d;
        },

        fijarCantidad: function (d, n) {
            d.presentacion_cantidad = r4(n);
            return VentaPres.recalcular(d);
        },

        nuevaLinea: function (of, n, pct) {
            return {
                presentacion_id: parseInt(of.presentacion_id, 10),
                presentacion_nombre: of.nombre,
                presentacion_factor: num(of.factor),
                presentacion_cantidad: r4(n),
                presentacion_precio: num(of.precio),
                descuento_general_pct: num(pct)
            };
        },

        // Conversión entre unidad base y presentaciones para una línea (o cualquier objeto con presentacion_factor)
        aVisible: function (d, cantidadBase) {
            return VentaPres.esPres(d) ? r4(num(cantidadBase) / num(d.presentacion_factor)) : cantidadBase;
        },
        aBase: function (d, visible) {
            return VentaPres.esPres(d) ? r4(num(visible) * num(d.presentacion_factor)) : visible;
        },
        // Badge con el nombre de la presentación ('' si es por unidad)
        badge: function (d) {
            if (!VentaPres.esPres(d)) return '';
            var t = String(d.presentacion_nombre || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            return ' <span class="badge bg-secondary">' + t + '</span>';
        },

        ofertas: function (item) { return item && Array.isArray(item.ofertas) ? item.ofertas : []; },

        textoOferta: function (of) {
            var partes = [of.presentacion_id ? of.nombre : 'Unidad'];
            if (of.minimo) partes.push('mín. ' + of.minimo);
            if (of.multiplo) partes.push('de a ' + of.multiplo);
            if (of.disponible !== null && of.disponible !== undefined) partes.push('disp. ' + of.disponible);
            return partes.join(' · ');
        }
    };

    window.VentaPres = VentaPres;
})(window);
