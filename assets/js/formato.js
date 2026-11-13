/*
 * assets/js/formato.js — formato de fechas y números de la empresa activa (global Fmt).
 *
 * La configuración la publica header1.php en window.APP_FORMATO ({fecha: 'd/m/Y', miles: '.', decimal: ','},
 * de conf__empresas, ver core/formato.php). Los datos siguen viajando en ISO y con punto decimal:
 * Fmt solo cambia lo que se muestra.
 *
 *   Fmt.fecha('2026-10-31')                → 31/10/2026   (acepta ISO, 'AAAA-MM-DD HH:MM:SS', Date y dd/mm/aaaa)
 *   Fmt.fechaHora('2026-10-31 14:05:00')   → 31/10/2026 14:05
 *   Fmt.numero(1234.5, 2)                  → 1.234,50     (o un objeto de opciones de Intl.NumberFormat)
 *   Fmt.moneda(1234.5)                     → $ 1.234,50
 *   Fmt.numberFormat(opciones).format(v)   → reemplazo de new Intl.NumberFormat('es-AR', opciones)
 *   Fmt.parseNumero('1.234,50')            → 1234.5
 *   Fmt.cantidad(12.5)                     → 12,50        (cantidad de stock con los decimales de la empresa)
 *   Fmt.decimalesStock() / Fmt.redondearCantidad(v) / Fmt.pasoStock()  (2 → '0.01', para el step de los inputs)
 *
 * En las tablas DataTables, toda fecha ISO que llegue sin formatear a una celda se muestra con el
 * formato de la empresa (el dato sigue en ISO, así que ordena bien), y las columnas con fechas ya
 * formateadas ordenan como fecha.
 */
(function (window, $) {
    'use strict';

    var cfg = { fecha: 'd/m/Y', miles: '.', decimal: ',', stock: 2 };
    var cacheIntl = {};

    function dos(n) { return (n < 10 ? '0' : '') + n; }

    // Partes de una fecha: {y, m, d, hora} o null. hora = 'HH:MM' / 'HH:MM:SS' o ''.
    function partes(v) {
        if (v === null || v === undefined || v === '') return null;
        if (v instanceof Date) {
            if (isNaN(v.getTime())) return null;
            return { y: v.getFullYear(), m: v.getMonth() + 1, d: v.getDate(), hora: dos(v.getHours()) + ':' + dos(v.getMinutes()) };
        }
        var s = String(v).trim(), r;
        // ISO con zona horaria (Z / +03:00): se pasa a la hora local
        if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})$/.test(s)) return partes(new Date(s));
        r = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}:\d{2})(:\d{2})?(?:\.\d+)?)?$/.exec(s);
        if (r) {
            if (r[1] === '0000') return null;
            return { y: +r[1], m: +r[2], d: +r[3], hora: r[4] ? r[4] + (r[5] || '') : '' };
        }
        // dd/mm/aaaa: lo que todavía arman algunas consultas con DATE_FORMAT(...,'%d/%m/%Y')
        r = /^(\d{1,2})\/(\d{1,2})\/(\d{4})(?: (\d{2}:\d{2})(:\d{2})?)?$/.exec(s);
        if (r) return { y: +r[3], m: +r[2], d: +r[1], hora: r[4] ? r[4] + (r[5] || '') : '' };
        return null;
    }

    function armar(p) {
        return cfg.fecha.replace(/[dmY]/g, function (c) {
            return c === 'd' ? dos(p.d) : (c === 'm' ? dos(p.m) : String(p.y));
        });
    }

    function fecha(v, vacio) {
        var p = partes(v);
        if (!p) return (v === null || v === undefined || v === '' || /^0000-/.test(String(v))) ? (vacio === undefined ? '' : vacio) : String(v);
        return armar(p);
    }

    function fechaHora(v, vacio) {
        var p = partes(v);
        if (!p) return fecha(v, vacio);
        return armar(p) + (p.hora ? ' ' + p.hora.substr(0, 5) : '');
    }

    function opcionesIntl(opts) {
        if (typeof opts === 'number') return { minimumFractionDigits: opts, maximumFractionDigits: opts };
        return opts || {};
    }

    // Number.toLocaleString con los separadores de la empresa. opts: decimales (número) u opciones de Intl.
    function numero(v, opts) {
        var o = opcionesIntl(opts);
        var clave = JSON.stringify(o);
        var nf = cacheIntl[clave] || (cacheIntl[clave] = new Intl.NumberFormat('en-US', o));
        var n = (typeof v === 'number') ? v : Number(v);
        return nf.formatToParts(n).map(function (p) {
            if (p.type === 'group') return cfg.miles;
            if (p.type === 'decimal') return cfg.decimal;
            return p.value;
        }).join('');
    }

    function moneda(v, decimales, simbolo) {
        var s = simbolo === undefined ? '$' : simbolo;
        return (s ? s + ' ' : '') + numero(parseFloat(v) || 0, decimales === undefined ? 2 : decimales);
    }

    function parseNumero(v) {
        if (typeof v === 'number') return v;
        if (v === null || v === undefined) return NaN;
        var s = String(v).replace(/[^\d.,\-\s']/g, '').trim();
        if (cfg.miles) s = s.split(cfg.miles).join('');
        s = s.replace(/\s/g, '');
        if (cfg.decimal !== '.') s = s.replace(cfg.decimal, '.');
        return parseFloat(s);
    }

    var Fmt = {
        configurar: function (nueva) {
            if (nueva) {
                if (nueva.fecha) cfg.fecha = nueva.fecha;
                if (nueva.miles !== undefined) cfg.miles = nueva.miles;
                if (nueva.decimal) cfg.decimal = nueva.decimal;
                if (nueva.stock !== undefined && nueva.stock !== null && !isNaN(parseInt(nueva.stock, 10))) cfg.stock = parseInt(nueva.stock, 10);
            }
            configurarDataTables();
        },
        config: function () { return { fecha: cfg.fecha, miles: cfg.miles, decimal: cfg.decimal, stock: cfg.stock }; },
        // Cantidades de stock: decimales de la empresa (conf__empresas.decimales_stock)
        decimalesStock: function () { return cfg.stock; },
        cantidad: function (v) { return numero(parseFloat(v) || 0, cfg.stock); },
        redondearCantidad: function (v) {
            var f = Math.pow(10, cfg.stock);
            return Math.round((parseFloat(v) || 0) * f) / f;
        },
        // Valor de un input type=number (siempre con punto) y su step
        cantidadInput: function (v) { return (parseFloat(v) || 0).toFixed(cfg.stock); },
        pasoStock: function () { return cfg.stock > 0 ? (1 / Math.pow(10, cfg.stock)).toFixed(cfg.stock) : '1'; },
        fecha: fecha,
        fechaHora: fechaHora,
        numero: numero,
        moneda: moneda,
        numberFormat: function (opts) { return { format: function (v) { return numero(v, opts); } }; },
        parseNumero: parseNumero,
        // Hoy en 'AAAA-MM-DD' con la hora LOCAL (para inputs date). new Date().toISOString() da la
        // fecha UTC: en Argentina, después de las 21 hs ya es mañana.
        hoyISO: function () {
            var d = new Date();
            return d.getFullYear() + '-' + dos(d.getMonth() + 1) + '-' + dos(d.getDate());
        },
        // Para ordenar: 'AAAAMMDDHHMMSS' de una fecha en cualquiera de los formatos aceptados ('' si no es fecha)
        claveFecha: function (v) {
            var p = partes(v);
            return p ? String(p.y) + dos(p.m) + dos(p.d) + (p.hora || '').replace(/:/g, '') : '';
        }
    };

    // ---------------------------------------------------------------- DataTables
    var reIso = /\b(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?\b(?![-\/\d])/g;

    function textoFechaEmpresa(t) {
        return t.replace(reIso, function (todo, y, m, d, hh, mi, ss) {
            if (+m < 1 || +m > 12 || +d < 1 || +d > 31 || y === '0000') return todo;
            var p = { y: +y, m: +m, d: +d, hora: hh ? hh + ':' + mi + (ss ? ':' + ss : '') : '' };
            return armar(p) + (p.hora ? ' ' + p.hora : '');
        });
    }

    // Fechas ISO que quedaron sin formatear en las celdas visibles (no toca inputs ni scripts).
    function formatearCeldas(raiz) {
        if (!raiz) return;
        var walker = document.createTreeWalker(raiz, NodeFilter.SHOW_TEXT, {
            acceptNode: function (n) {
                var padre = n.parentNode && n.parentNode.nodeName;
                if (padre === 'SCRIPT' || padre === 'STYLE' || padre === 'TEXTAREA' || padre === 'OPTION') return NodeFilter.FILTER_REJECT;
                return /\d{4}-\d{2}-\d{2}/.test(n.nodeValue) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP;
            }
        });
        var nodos = [], n;
        while ((n = walker.nextNode())) nodos.push(n);
        nodos.forEach(function (nodo) {
            var nuevo = textoFechaEmpresa(nodo.nodeValue);
            if (nuevo !== nodo.nodeValue) nodo.nodeValue = nuevo;
        });
    }

    function reFechaEmpresa() {
        var sep = cfg.fecha.replace(/[dmY]/g, '').charAt(0) || '/';
        var esc = sep.replace(/[.\/-]/g, '\\$&');
        var partesFmt = cfg.fecha.split(sep).map(function (c) { return c === 'Y' ? '\\d{4}' : '\\d{2}'; });
        return new RegExp('^' + partesFmt.join(esc) + '(?: \\d{2}:\\d{2}(?::\\d{2})?)?$');
    }

    function claveFechaEmpresa(t) {
        var s = $.trim(String(t === null || t === undefined ? '' : t).replace(/<[^>]*>/g, ''));
        if (!s) return '';
        var sep = cfg.fecha.replace(/[dmY]/g, '').charAt(0) || '/';
        var orden = cfg.fecha.split(sep);
        var resto = s.split(' ');
        var valores = resto[0].split(sep);
        var p = {};
        orden.forEach(function (c, i) { p[c] = valores[i]; });
        return p.Y + p.m + p.d + (resto[1] || '').replace(/:/g, '');
    }

    var dtConfigurado = false;
    function configurarDataTables() {
        if (!$ || !$.fn || !$.fn.dataTable) return;
        var dt = $.fn.dataTable;
        // Separadores para que DataTables reconozca y ordene los números ya formateados
        // (decimal '' = el punto, el default de DataTables: así no busca un tipo 'num.' que no existe)
        $.extend(true, dt.defaults, { language: { decimal: cfg.decimal === '.' ? '' : cfg.decimal, thousands: cfg.miles } });
        if (dtConfigurado) return;
        dtConfigurado = true;

        // Las tablas que cargan el es-ES.json de DataTables traen decimal ',' / miles '.': se corrigen
        // acá, con el idioma ya cargado y antes de la primera carga de datos.
        $(document).on('preInit.dt', function (e, settings) {
            if (e.namespace !== 'dt' || !settings || !settings.oLanguage) return;
            settings.oLanguage.sDecimal = cfg.decimal === '.' ? '' : cfg.decimal;
            settings.oLanguage.sThousands = cfg.miles;
        });

        // Columnas con fechas ya formateadas (render propio de la página): ordenar como fecha
        dt.ext.type.detect.unshift(function (d) {
            if (d === null || d === undefined || d === '') return null;
            var s = $.trim(String(d).replace(/<[^>]*>/g, ''));
            return s && reFechaEmpresa().test(s) ? 'fecha-empresa' : null;
        });
        dt.ext.type.order['fecha-empresa-pre'] = claveFechaEmpresa;

        $(document).on('draw.dt', function (e, settings) {
            if (e.namespace !== 'dt' || !settings || !settings.nTBody) return;
            formatearCeldas(settings.nTBody);
        });
        // Detalle desplegable de Responsive (se arma con el render de la celda, no con el DOM ya corregido)
        $(document).on('responsive-display.dt', function (e, api, row, mostrar) {
            if (!mostrar) return;
            var tr = row.node();
            if (tr && tr.nextSibling && /\bchild\b/.test(tr.nextSibling.className || '')) formatearCeldas(tr.nextSibling);
        });
    }

    window.Fmt = Fmt;
    Fmt.configurar(window.APP_FORMATO || null);
})(window, window.jQuery);
