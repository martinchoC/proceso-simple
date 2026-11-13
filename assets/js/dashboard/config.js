/**
 * Pantalla de inicio: modal "Personalizar". Depende de window.Dash (widgets.js), Bootstrap,
 * SortableJS (lo carga footer1.php) y UiSimple / SweetAlert2 para los mensajes.
 *
 * Indicadores: ordenar (arrastrando o con los botones), mostrar u ocultar y elegir el ancho.
 * Accesos: los fijos del perfil se muestran sin poder quitarse; los elegidos se ordenan y se quitan;
 * los disponibles se agregan. El servidor vuelve a validar todo lo que se manda.
 */
(function () {
    'use strict';

    const D = window.Dash;
    if (!D) return;
    const el = D.el, icono = D.icono;

    const ANCHOS = { 3: 'Chico', 4: 'Mediano', 6: 'Medio ancho', 12: 'Ancho completo' };
    let estado = null;      // respuesta de config
    let ocupado = false;

    const $ = id => document.getElementById(id);
    let modalEl = null;
    let modal = null;   // Bootstrap se carga en footer1.php, después de este archivo: se crea en init()

    function mensajeOk(m) { if (window.UiSimple) UiSimple.ok(m); }
    function mensajeError(err, titulo) {
        if (!window.UiSimple) { window.alert(err.message); return; }
        if (err.status === 400) UiSimple.aviso(err.message);
        else UiSimple.error(err.message, titulo);
    }

    // ---------------- Orden ----------------

    function botonesMover(li) {
        const mover = delta => {
            const lista = li.parentElement;
            const hermano = delta < 0 ? li.previousElementSibling : li.nextElementSibling;
            if (!hermano) return;
            lista.insertBefore(li, delta < 0 ? hermano : hermano.nextElementSibling);
            li.querySelector(delta < 0 ? '[data-mover="-1"]' : '[data-mover="1"]').focus();
        };
        return el('div', { class: 'btn-group btn-group-sm' }, [
            el('button', { type: 'button', class: 'btn btn-outline-secondary', 'data-mover': '-1', title: 'Subir', 'aria-label': 'Subir',
                           onclick: () => mover(-1) }, [icono('bi bi-chevron-up')]),
            el('button', { type: 'button', class: 'btn btn-outline-secondary', 'data-mover': '1', title: 'Bajar', 'aria-label': 'Bajar',
                           onclick: () => mover(1) }, [icono('bi bi-chevron-down')]),
        ]);
    }

    function hacerOrdenable(lista) {
        if (!window.Sortable || lista.dataset.ordenable) return;
        new Sortable(lista, { handle: '.dash-asa', animation: 150 });
        lista.dataset.ordenable = '1';
    }

    // ---------------- Indicadores ----------------

    function itemWidget(w) {
        const id = 'dash-vis-' + w.widget_id;
        const check = el('input', { type: 'checkbox', class: 'form-check-input mt-0', id: id, checked: w.visible });
        const select = el('select', { class: 'form-select form-select-sm dash-ancho', 'aria-label': 'Ancho de ' + w.titulo },
            (estado.anchos_validos || [3, 4, 6, 12]).map(a => el('option', { value: String(a), text: ANCHOS[a] || String(a), selected: a === w.ancho })));
        const li = el('li', { class: w.visible ? '' : 'oculto', dataset: { widgetId: String(w.widget_id) } }, [
            el('span', { class: 'dash-asa', title: 'Arrastrar para ordenar' }, [icono('bi bi-grip-vertical')]),
            check,
            el('label', { class: 'dash-orden-texto mb-0', for: id, text: w.titulo, title: w.ayuda || w.titulo }),
            select,
        ]);
        li.appendChild(botonesMover(li));
        check.addEventListener('change', () => li.classList.toggle('oculto', !check.checked));
        return li;
    }

    // ---------------- Accesos ----------------

    function itemAcceso(a, tipo) {
        const hijos = [];
        if (tipo === 'elegido') hijos.push(el('span', { class: 'dash-asa', title: 'Arrastrar para ordenar' }, [icono('bi bi-grip-vertical')]));
        if (tipo === 'fijo') hijos.push(el('span', { class: 'dash-fijo', title: 'Fijo del perfil' }, [icono('bi bi-lock')]));
        hijos.push(icono(a.icono || 'bi bi-link-45deg'));
        hijos.push(el('span', { class: 'dash-orden-texto', text: a.titulo, title: a.titulo }));
        const li = el('li', { dataset: { paginaId: String(a.pagina_id) } }, hijos);
        if (tipo === 'elegido') {
            li.appendChild(botonesMover(li));
            li.appendChild(el('button', { type: 'button', class: 'btn btn-sm btn-outline-danger', title: 'Quitar', 'aria-label': 'Quitar ' + a.titulo,
                                          onclick: () => moverAcceso(a, 'disponible') }, [icono('bi bi-x-lg')]));
        } else if (tipo === 'disponible') {
            li.appendChild(el('button', { type: 'button', class: 'btn btn-sm btn-outline-primary', title: 'Agregar', 'aria-label': 'Agregar ' + a.titulo,
                                          onclick: () => moverAcceso(a, 'elegido') }, [icono('bi bi-plus-lg')]));
        }
        return li;
    }

    /** Pasa un acceso entre "Tus accesos" y "Disponibles" (los agregados van al final). */
    function moverAcceso(a, destino) {
        const actual = document.querySelector('#dash-tab-accesos li[data-pagina-id="' + a.pagina_id + '"]');
        if (actual) actual.remove();
        const lista = destino === 'elegido' ? $('dash-lista-elegidos') : $('dash-lista-disponibles');
        lista.appendChild(itemAcceso(a, destino));
        actualizarVacios();
    }

    function actualizarVacios() {
        $('dash-elegidos-vacio').classList.toggle('d-none', $('dash-lista-elegidos').children.length > 0);
        $('dash-disponibles-vacio').classList.toggle('d-none', $('dash-lista-disponibles').children.length > 0);
    }

    // ---------------- Modal ----------------

    function dibujar() {
        $('dash-lista-widgets').replaceChildren.apply($('dash-lista-widgets'), (estado.widgets || []).map(itemWidget));

        // Sin el widget de accesos activo, la solapa no tiene efecto: se oculta y se vuelve a la de
        // indicadores. Las listas se arman igual para mandar sin cambios lo que el usuario tenía.
        const conAccesos = estado.accesos_activos !== false;
        const solapaAccesos = document.querySelector('[data-bs-target="#dash-tab-accesos"]');
        solapaAccesos.closest('.nav').classList.toggle('d-none', !conAccesos);
        if (!conAccesos) bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#dash-tab-widgets"]')).show();

        const acc = estado.accesos || {};
        const obligatorios = acc.obligatorios || [];
        $('dash-bloque-obligatorios').classList.toggle('d-none', !obligatorios.length);
        $('dash-lista-obligatorios').replaceChildren.apply($('dash-lista-obligatorios'), obligatorios.map(a => itemAcceso(a, 'fijo')));

        const porId = {};
        (acc.disponibles || []).forEach(a => { porId[a.pagina_id] = a; });
        const elegidos = (acc.elegidos || []).filter(id => porId[id]);
        $('dash-lista-elegidos').replaceChildren.apply($('dash-lista-elegidos'), elegidos.map(id => itemAcceso(porId[id], 'elegido')));
        $('dash-lista-disponibles').replaceChildren.apply($('dash-lista-disponibles'),
            (acc.disponibles || []).filter(a => !elegidos.includes(a.pagina_id)).map(a => itemAcceso(a, 'disponible')));
        actualizarVacios();

        hacerOrdenable($('dash-lista-widgets'));
        hacerOrdenable($('dash-lista-elegidos'));
    }

    async function abrir() {
        if (ocupado) return;
        ocupado = true;
        try {
            estado = (await D.api('config')).data || {};
            dibujar();
            modal.show();
        } catch (err) {
            mensajeError(err, 'No se pudo abrir la configuración');
        } finally {
            ocupado = false;
        }
    }

    function leerFormulario() {
        const widgets = Array.from($('dash-lista-widgets').children).map(li => ({
            widget_id: Number(li.dataset.widgetId),
            visible: li.querySelector('input[type=checkbox]').checked ? 1 : 0,
            ancho: Number(li.querySelector('select').value),
        }));
        const accesos = Array.from($('dash-lista-elegidos').children).map(li => Number(li.dataset.paginaId));
        return { widgets: widgets, accesos: accesos };
    }

    async function guardar() {
        if (ocupado) return;
        ocupado = true;
        const boton = $('dash-guardar');
        boton.disabled = true;
        try {
            await D.api('guardar', { metodo: 'POST', cuerpo: leerFormulario() });
            modal.hide();
            mensajeOk('Guardado');
            D.recargar();
        } catch (err) {
            mensajeError(err, 'No se pudo guardar');
        } finally {
            boton.disabled = false;
            ocupado = false;
        }
    }

    async function restablecer() {
        if (ocupado) return;
        const conf = await Swal.fire({
            icon: 'question',
            title: '¿Volver a los valores de tu perfil?',
            text: 'Se pierden el orden, los indicadores y los accesos que elegiste.',
            showCancelButton: true,
            confirmButtonText: 'Restablecer',
            cancelButtonText: 'Cancelar',
        });
        if (!conf.isConfirmed) return;
        ocupado = true;
        try {
            await D.api('restablecer', { metodo: 'POST' });
            modal.hide();
            mensajeOk('Listo');
            D.recargar();
        } catch (err) {
            mensajeError(err, 'No se pudo restablecer');
        } finally {
            ocupado = false;
        }
    }

    function init() {
        modalEl = $('dash-modal');
        if (!modalEl || !window.bootstrap) return;
        modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        document.addEventListener('click', ev => {
            if (ev.target.closest('#dash-personalizar, [data-dash-abrir-config]')) abrir();
        });
        $('dash-guardar').addEventListener('click', guardar);
        $('dash-restablecer').addEventListener('click', restablecer);
        modalEl.addEventListener('shown.bs.modal', () => { if (window.UiSimple && UiSimple.tooltips) UiSimple.tooltips(modalEl); });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
