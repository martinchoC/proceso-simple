/**
 * Pantalla de inicio: carga y dibujo de widgets. Configuración en window.DASH_CONFIG (index.php).
 *
 * Pide el layout y arma una tarjeta por widget con un esqueleto; cada widget se carga con su propio
 * request, así uno lento o con error no frena a los demás (el que falla ofrece "Reintentar").
 * Se dibuja por tipo (kpi, lista, minichart, atajos), no por widget: un widget nuevo de un tipo
 * existente no toca este archivo. Todo el texto entra por textContent / atributos, nunca innerHTML.
 * Expone window.Dash (api, recargar, formatear) para config.js.
 */
(function () {
    'use strict';

    const CFG = window.DASH_CONFIG || {};
    const SVG_NS = 'http://www.w3.org/2000/svg';
    const ANCHOS_LG = { 3: 'col-lg-3', 4: 'col-lg-4', 6: 'col-lg-6', 12: 'col-lg-12' };
    // Color de cada tarjeta y acceso, en forma cíclica (RGB para usar con rgba en el CSS). Sin rojo:
    // queda para las alertas. Tonos oscuros para que el título se lea sobre blanco.
    const PALETA = ['13, 110, 253', '25, 135, 84', '111, 66, 193', '194, 89, 0', '214, 51, 132', '8, 127, 140'];
    const color = i => PALETA[i % PALETA.length];

    // ---------------- Utilidades ----------------

    /** Crea un elemento. attrs: class, text, dataset, on{evento}, resto como atributos. */
    function el(tag, attrs, hijos) {
        const nodo = document.createElement(tag);
        Object.entries(attrs || {}).forEach(([k, v]) => {
            if (v === null || v === undefined || v === false) return;
            if (k === 'class') nodo.className = v;
            else if (k === 'text') nodo.textContent = v;
            else if (k === 'dataset') Object.assign(nodo.dataset, v);
            else if (k.startsWith('on')) nodo.addEventListener(k.slice(2), v);
            else nodo.setAttribute(k, v === true ? '' : v);
        });
        (hijos || []).forEach(h => { if (h) nodo.appendChild(typeof h === 'string' ? document.createTextNode(h) : h); });
        return nodo;
    }

    function icono(clase) {
        return el('i', { class: clase, 'aria-hidden': 'true' });
    }

    const fmtCache = {};
    function nf(min, max) {
        const k = min + '-' + max;
        return fmtCache[k] || (fmtCache[k] = Fmt.numberFormat({ minimumFractionDigits: min, maximumFractionDigits: max }));
    }

    /** Formatos de la lista blanca del servidor. corto = sin decimales en importes grandes. */
    function formatear(valor, formato, corto) {
        if (valor === null || valor === undefined || valor === '') return '—';
        const n = Number(valor);
        if (!isFinite(n)) return '—';
        switch (formato) {
            case 'moneda': return '$ ' + (corto && Math.abs(n) >= 1000 ? nf(0, 0) : nf(2, 2)).format(n);
            case 'entero': return nf(0, 0).format(n);
            case 'porcentaje': return nf(1, 1).format(n) + ' %';
            default: return nf(0, 2).format(n);
        }
    }

    /** "hace 5 min" a partir de 'Y-m-d H:i:s' del servidor (misma zona horaria que el usuario). */
    function haceCuanto(fecha) {
        const t = Date.parse(String(fecha).replace(' ', 'T'));
        if (!isFinite(t)) return '';
        const min = Math.max(0, Math.round((Date.now() - t) / 60000));
        if (min < 1) return 'recién';
        if (min < 60) return 'hace ' + min + ' min';
        return 'hace ' + Math.round(min / 60) + ' h';
    }

    /** Error con el mensaje del servidor y el código HTTP. */
    class DashError extends Error {
        constructor(mensaje, status, codigo) {
            super(mensaje);
            this.status = status;
            this.codigo = codigo;
        }
    }

    let redirigiendo = false;

    /**
     * Llamada al endpoint. GET con params en la URL; POST con cuerpo JSON y el token CSRF en el header.
     * Devuelve la respuesta completa ({success, data, message, meta}) o lanza DashError.
     */
    async function api(accion, opciones) {
        const op = opciones || {};
        const metodo = op.metodo || 'GET';
        const qs = new URLSearchParams(Object.assign({ accion: accion }, op.params || {}));
        const init = { method: metodo, credentials: 'same-origin', headers: { Accept: 'application/json' } };
        if (metodo === 'POST') {
            init.headers['Content-Type'] = 'application/json';
            init.headers['X-CSRF-Token'] = CFG.csrf || '';
            init.body = JSON.stringify(op.cuerpo || {});
        }

        let resp;
        try {
            resp = await fetch(CFG.endpoint + '?' + qs.toString(), init);
        } catch (e) {
            throw new DashError('No hay conexión con el servidor.', 0, 'RED');
        }
        if (resp.status === 401) {
            if (!redirigiendo && CFG.login) {
                redirigiendo = true;
                window.alert('La sesión expiró. Vuelva a ingresar al sistema.');
                window.location.href = CFG.login;
            }
            throw new DashError('La sesión expiró.', 401, 'SESSION_EXPIRED');
        }
        let json = null;
        try {
            json = await resp.json();
        } catch (e) {
            throw new DashError('El servidor respondió algo inesperado.', resp.status, 'JSON');
        }
        if (!resp.ok || !json || json.success !== true) {
            throw new DashError((json && json.message) || 'No se pudo completar la operación.', resp.status, json && json.codigo);
        }
        return json;
    }

    // ---------------- Tarjeta ----------------

    function ayuda(texto) {
        if (!texto) return null;
        return el('span', { class: 'ui-ayuda', tabindex: '0', role: 'button', 'data-bs-toggle': 'tooltip',
                            'data-bs-placement': 'bottom', title: texto, 'aria-label': 'Ayuda' },
                  [icono('bi bi-question-circle')]);
    }

    function esqueleto() {
        return el('div', { class: 'dash-esqueleto', 'aria-label': 'Cargando' }, [el('span'), el('span'), el('span')]);
    }

    /** Arma la columna con la tarjeta del widget. Devuelve {col, cuerpo, acciones}. */
    function crearTarjeta(w, i) {
        const titulo = el('h2', { class: 'dash-card-titulo', id: 'dash-t-' + w.widget_id },
            [w.link ? el('a', { href: w.link, text: w.titulo }) : document.createTextNode(w.titulo)]);
        const acciones = el('div', { class: 'dash-card-acciones' });
        const cuerpo = el('div', { class: 'dash-cuerpo' }, [esqueleto()]);
        const card = el('section', { class: 'dash-card', 'aria-labelledby': 'dash-t-' + w.widget_id,
                                     dataset: { widgetId: String(w.widget_id) } },
            [el('div', { class: 'dash-card-cab' }, [titulo, ayuda(w.ayuda), acciones]), cuerpo]);
        if (w.tipo !== 'atajos') card.style.setProperty('--dash-rgb', color(i));
        else card.classList.add('dash-card-atajos');
        const anchoLg = ANCHOS_LG[w.ancho] || 'col-lg-4';
        const col = el('div', { class: 'col-12 ' + (w.ancho >= 12 ? '' : 'col-md-6 ') + anchoLg }, [card]);
        return { col: col, cuerpo: cuerpo, acciones: acciones };
    }

    function reemplazar(nodo, hijos) {
        nodo.replaceChildren.apply(nodo, hijos.filter(Boolean));
    }

    function mostrarError(t, w, err) {
        const msg = err && err.status === 403 ? 'No tenés acceso a este indicador.' : (err && err.message) || 'No se pudo cargar.';
        const hijos = [icono('bi bi-exclamation-circle'), el('span', { text: msg })];
        if (!err || err.status !== 403) {
            hijos.push(el('button', { type: 'button', class: 'btn btn-link btn-sm p-0', text: 'Reintentar',
                                      onclick: () => cargarWidget(t, w, false) }));
        }
        reemplazar(t.cuerpo, [el('div', { class: 'dash-error', role: 'alert' }, hijos)]);
        reemplazar(t.acciones, []);
    }

    /** Carga un widget; forzar = saltear la caché del servidor. */
    async function cargarWidget(t, w, forzar) {
        reemplazar(t.cuerpo, [esqueleto()]);
        try {
            const params = { widget_id: w.widget_id };
            if (forzar) params.forzar = 1;
            const r = await api('widget', { params: params });
            const render = RENDER[r.data && r.data.tipo] || RENDER[w.tipo];
            if (!render) throw new DashError('Tipo de indicador desconocido.', 0, 'TIPO');
            reemplazar(t.cuerpo, render(r.data || {}));
            pintarMeta(t, w, r.meta || {});
        } catch (err) {
            mostrarError(t, w, err);
        }
    }

    /** "Actualizado hace N min" + botón para recalcular, solo si vino de la caché. */
    function pintarMeta(t, w, meta) {
        const hijos = [];
        if (meta.desde_cache) {
            hijos.push(el('span', { class: 'dash-actualizado', text: haceCuanto(meta.calculado_en), title: 'Calculado: ' + meta.calculado_en }));
        }
        hijos.push(el('button', { type: 'button', class: 'dash-btn-icono', title: 'Actualizar', 'aria-label': 'Actualizar ' + w.titulo,
                                  onclick: () => cargarWidget(t, w, true) }, [icono('bi bi-arrow-clockwise')]));
        reemplazar(t.acciones, hijos);
    }

    // ---------------- Dibujo por tipo ----------------

    function listaSub(sub) {
        if (!Array.isArray(sub) || !sub.length) return null;
        return el('ul', { class: 'dash-sub' }, sub.map(s =>
            el('li', { class: s.alerta ? 'alerta' : '' }, [el('span', { text: s.etiqueta }), el('span', { text: formatear(s.valor, s.formato) })])));
    }

    /** Línea SVG (sin librerías). Valores cualesquiera; se escala al alto disponible. */
    function sparkline(serie) {
        if (!Array.isArray(serie) || serie.length < 2) return null;
        const nums = serie.map(Number);
        const min = Math.min.apply(null, nums), max = Math.max.apply(null, nums);
        const rango = max - min || 1;
        const W = 100, H = 36, pad = 2;
        const pts = nums.map((v, i) => [(i / (nums.length - 1)) * W, H - pad - ((v - min) / rango) * (H - pad * 2)]);
        const linea = pts.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(2) + ' ' + p[1].toFixed(2)).join(' ');
        const svg = document.createElementNS(SVG_NS, 'svg');
        svg.setAttribute('class', 'dash-sparkline');
        svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
        svg.setAttribute('preserveAspectRatio', 'none');
        svg.setAttribute('aria-hidden', 'true');
        const area = document.createElementNS(SVG_NS, 'path');
        area.setAttribute('d', linea + ' L' + W + ' ' + H + ' L0 ' + H + ' Z');
        area.setAttribute('fill', 'currentColor');
        area.setAttribute('fill-opacity', '0.08');
        const trazo = document.createElementNS(SVG_NS, 'path');
        trazo.setAttribute('d', linea);
        trazo.setAttribute('fill', 'none');
        trazo.setAttribute('stroke', 'currentColor');
        trazo.setAttribute('stroke-width', '2');
        trazo.setAttribute('vector-effect', 'non-scaling-stroke');
        trazo.setAttribute('stroke-linejoin', 'round');
        svg.appendChild(area);
        svg.appendChild(trazo);
        return svg;
    }

    function variacion(pct) {
        if (pct === null || pct === undefined || !isFinite(Number(pct))) return null;
        const n = Number(pct);
        const sube = n >= 0;
        return el('span', { class: 'dash-variacion ' + (sube ? 'sube' : 'baja'),
                            title: 'Contra el mismo tramo del mes anterior' },
            [icono(sube ? 'bi bi-arrow-up-short' : 'bi bi-arrow-down-short'), (sube ? '+' : '') + formatear(n, 'porcentaje')]);
    }

    /**
     * Columnas agrupadas por categoría (p. ej. meses): d.categorias = ['oct 25', ...] y
     * d.series = [{etiqueta, valores: [...]}, ...]. Valores negativos se dibujan en cero.
     * Debajo de cada grupo va el mes; el año solo en la primera columna y en enero.
     */
    function columnas(d) {
        const cats = Array.isArray(d.categorias) ? d.categorias : [];
        const series = (Array.isArray(d.series) ? d.series : []).filter(s => Array.isArray(s.valores));
        if (!cats.length || !series.length) return [el('p', { class: 'dash-vacio-widget', text: d.vacio || 'Sin datos.' })];

        const max = Math.max(0, ...series.flatMap(s => s.valores.map(v => Number(v) || 0))) || 1;
        const leyenda = series.length > 1 ? el('ul', { class: 'dash-leyenda' }, series.map((s, i) =>
            el('li', {}, [el('span', { class: 'dash-muestra serie-' + i, 'aria-hidden': 'true' }), s.etiqueta]))) : null;

        const resumen = series.map(s => s.etiqueta + ': ' + cats.map((c, j) => c + ' ' + formatear(s.valores[j], d.formato)).join(', ')).join('. ');
        const grafico = el('div', { class: 'dash-columnas', role: 'img', 'aria-label': resumen }, cats.map((cat, j) => {
            const [mes, anio] = String(cat).split(' ');
            const barras = el('div', { class: 'dash-col-barras' }, series.map((s, i) => {
                const v = Number(s.valores[j]) || 0;
                const barra = el('div', { class: 'dash-col serie-' + i, title: s.etiqueta + ' ' + cat + ': ' + formatear(v, d.formato) });
                barra.style.height = (Math.max(0, v) / max * 100).toFixed(1) + '%';
                return barra;
            }));
            const etiqueta = el('div', { class: 'dash-col-etq' }, [el('span', { text: mes })]);
            if (anio && (j === 0 || mes === 'ene')) etiqueta.appendChild(el('small', { text: "'" + anio }));
            return el('div', { class: 'dash-col-grupo' }, [barras, etiqueta]);
        }));

        return [leyenda, grafico, listaSub(d.sub), d.nota ? el('p', { class: 'dash-nota', text: d.nota }) : null];
    }

    const RENDER = {
        kpi(d) {
            if (d.aviso) return [el('p', { class: 'dash-aviso mb-0', text: d.aviso })];
            return [
                el('div', { class: 'dash-kpi-fila' }, [
                    el('div', { class: 'dash-kpi-valor', text: formatear(d.valor, d.formato, true),
                                title: formatear(d.valor, d.formato) }),
                    variacion(d.variacion_pct),
                ]),
                listaSub(d.sub),
                sparkline(d.serie),
            ];
        },

        lista(d) {
            const out = [];
            if (d.total) {
                out.push(el('div', { class: 'dash-total' }, [
                    el('span', { class: 'dash-kpi-valor', text: formatear(d.total.valor, d.total.formato, true) }),
                    el('small', { text: d.total.etiqueta + (d.total.cantidad ? ' · ' + formatear(d.total.cantidad, 'entero') + ' comprobantes' : '') }),
                ]));
            }
            out.push(listaSub(d.sub));
            const filas = Array.isArray(d.filas) ? d.filas : [];
            if (!filas.length) {
                out.push(el('p', { class: 'dash-vacio-widget', text: d.vacio || 'Sin datos.' }));
                return out;
            }
            out.push(el('ul', { class: 'dash-lista' }, filas.map(f =>
                el('li', { class: f.alerta ? 'alerta' : '' }, [
                    el('div', { class: 'dash-item-texto' }, [el('div', { text: f.texto, title: f.texto }), el('small', { text: f.detalle || '' })]),
                    el('span', { class: 'dash-item-valor', text: formatear(f.valor, f.formato) }),
                ]))));
            return out;
        },

        minichart(d) {
            if (d.grafico === 'columnas') return columnas(d);
            const series = Array.isArray(d.series) ? d.series : [];
            if (!series.length) return [el('p', { class: 'dash-vacio-widget', text: d.vacio || 'Sin datos.' })];
            if (d.grafico === 'linea') {
                return [sparkline(series.map(s => s.valor)), d.total ? listaSub([d.total]) : null];
            }
            const max = Math.max.apply(null, series.map(s => Math.max(0, Number(s.valor) || 0))) || 1;
            const barras = el('ul', { class: 'dash-barras' }, series.map(s => {
                const ancho = Math.max(0, Math.min(100, (Number(s.valor) || 0) / max * 100));
                const relleno = el('div');
                relleno.style.width = ancho.toFixed(1) + '%';
                return el('li', { class: s.resto ? 'resto' : '' }, [
                    el('div', { class: 'dash-barra-cab' }, [el('span', { text: s.etiqueta, title: s.etiqueta }),
                                                           el('span', { text: formatear(s.valor, d.formato, true) })]),
                    el('div', { class: 'dash-barra', role: 'img', 'aria-label': s.etiqueta + ': ' + formatear(s.valor, d.formato) }, [relleno]),
                ]);
            }));
            return [barras, d.total ? listaSub([d.total]) : null];
        },
    };

    /** Accesos directos: vienen en el layout; los badges se piden aparte, todos juntos. */
    function dibujarAtajos(t, accesos) {
        reemplazar(t.acciones, []);
        if (!accesos.length) {
            reemplazar(t.cuerpo, [el('p', { class: 'dash-vacio-widget', text: 'No tenés accesos directos. Agregalos desde Personalizar.' })]);
            return;
        }
        const grilla = el('nav', { class: 'dash-atajos', 'aria-label': 'Accesos directos' }, accesos.map((a, i) => {
            const enlace = el('a', { class: 'dash-atajo', href: a.url, dataset: { paginaId: String(a.pagina_id) }, title: a.titulo }, [
                icono(a.icono || 'bi bi-link-45deg'),
                el('span', { class: 'dash-atajo-texto', text: a.titulo }),
            ]);
            enlace.style.setProperty('--dash-rgb', color(i));
            return enlace;
        }));
        reemplazar(t.cuerpo, [grilla]);

        if (!accesos.some(a => a.badge_widget_id)) return;
        api('badges').then(r => {
            Object.entries(r.data || {}).forEach(([paginaId, n]) => {
                const cant = Number(n) || 0;
                const a = grilla.querySelector('[data-pagina-id="' + Number(paginaId) + '"]');
                if (!a || cant <= 0) return;
                a.appendChild(el('span', { class: 'badge rounded-pill text-bg-danger dash-badge', text: formatear(cant, 'entero'),
                                           'aria-label': formatear(cant, 'entero') + ' pendientes' }));
            });
        }).catch(() => { /* sin badge no es un error para el usuario */ });
    }

    // ---------------- Layout ----------------

    async function recargar() {
        const grilla = document.getElementById('dash-grilla');
        const vacio = document.getElementById('dash-vacio');
        if (!grilla) return;
        reemplazar(grilla, [el('div', { class: 'col-12' }, [el('div', { class: 'dash-card' }, [esqueleto()])])]);
        vacio.classList.add('d-none');

        let layout;
        try {
            layout = (await api('layout')).data || {};
        } catch (err) {
            reemplazar(grilla, [el('div', { class: 'col-12' }, [el('div', { class: 'dash-card' }, [
                el('div', { class: 'dash-error', role: 'alert' }, [icono('bi bi-exclamation-circle'), el('span', { text: err.message }),
                    el('button', { type: 'button', class: 'btn btn-link btn-sm p-0', text: 'Reintentar', onclick: recargar })]),
            ])])]);
            return;
        }

        const widgets = Array.isArray(layout.widgets) ? layout.widgets : [];
        reemplazar(grilla, []);
        if (!widgets.length) {
            vacio.classList.remove('d-none');
            return;
        }
        let n = 0;   // índice de color: los accesos no consumen uno
        widgets.forEach(w => {
            const t = crearTarjeta(w, w.tipo === 'atajos' ? 0 : n++);
            grilla.appendChild(t.col);
            if (w.tipo === 'atajos') dibujarAtajos(t, layout.accesos || []);
            else cargarWidget(t, w, false);   // sin await: cada uno por su cuenta
        });
        if (window.UiSimple && UiSimple.tooltips) UiSimple.tooltips(grilla);
    }

    window.Dash = { api: api, recargar: recargar, formatear: formatear, el: el, icono: icono, DashError: DashError };

    document.addEventListener('DOMContentLoaded', recargar);
})();
