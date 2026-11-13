/**
 * ui-simple.js — comportamientos comunes del estilo "ui-simple" (body.ui-simple).
 *  - Tooltips de ayuda ("?") y de cualquier [data-bs-toggle="tooltip"].
 *  - Acciones por fila: todos los botones quedan a la vista, en una sola línea y con estilo liviano.
 *    Una tabla con data-ui-acciones="no" queda como está.
 *  - Tablas responsive (Responsive de DataTables, cargado y activado en header1.php): las columnas
 *    "ui-detalle" van al detalle desplegable de la fila y la de acciones no se oculta nunca.
 *    Si una tabla no tiene Responsive, las "ui-detalle" se ven en un renglón desplegable (▸) propio.
 *  - UiSimple.ok() / .error() / .aviso(): mensajes breves.
 */
(function ($) {
    'use strict';
    if (!$) return;
    const UI = window.UiSimple = window.UiSimple || {};
    const activo = () => document.body.classList.contains('ui-simple');

    // ---------------- Tooltips ----------------
    UI.tooltips = function (ctx) {
        if (!window.bootstrap || !bootstrap.Tooltip) return;
        $(ctx || document).find('[data-bs-toggle="tooltip"]').each(function () {
            bootstrap.Tooltip.getOrCreateInstance(this, { container: 'body' });
        });
    };

    // ---------------- Mensajes ----------------
    UI.ok = function (mensaje) {
        if (!window.Swal) return;
        Swal.fire({ icon: 'success', title: mensaje || 'Guardado', toast: true, position: 'top-end',
                    timer: 1800, showConfirmButton: false });
    };
    UI.error = function (mensaje, titulo) {
        if (!window.Swal) { window.alert(mensaje); return; }
        Swal.fire({ icon: 'error', title: titulo || 'No se pudo completar', text: mensaje || 'Probá de nuevo en unos segundos.' });
    };
    UI.aviso = function (mensaje, titulo) {
        if (!window.Swal) { window.alert(mensaje); return; }
        Swal.fire({ icon: 'warning', title: titulo || 'Revisá los datos', text: mensaje });
    };

    // ---------------- Encabezado de modal de entidad ----------------
    // Deja en el título "Editar Entidad" + razón social, CUIT y nombre de fantasía (si difiere).
    // titulo puede traer HTML propio de la página (ícono); los datos de la entidad van como texto.
    UI.encabezadoEntidad = function (selector, titulo, entidad) {
        const $t = $(selector).empty().addClass('ui-titulo-entidad').html(titulo);
        entidad = entidad || {};
        const nombre = String(entidad.entidad_nombre || '').trim();
        const fantasia = String(entidad.entidad_fantasia || '').trim();
        const digitos = String(entidad.cuit || '').replace(/\D/g, '');
        const cuit = digitos.length === 11
            ? digitos.slice(0, 2) + '-' + digitos.slice(2, 10) + '-' + digitos.slice(10)
            : digitos;
        if (!nombre && !cuit && !fantasia) return;

        const $datos = $('<span class="ui-entidad-datos"></span>');
        if (nombre) $datos.append($('<span class="ui-entidad-nombre"></span>').text(nombre));
        if (cuit) $datos.append($('<span class="ui-entidad-chip ui-entidad-cuit"></span>').text('CUIT ' + cuit));
        if (fantasia && fantasia.toLowerCase() !== nombre.toLowerCase()) {
            $datos.append($('<span class="ui-entidad-chip ui-entidad-fantasia"></span>')
                .append('<i class="fas fa-store"></i>').append(document.createTextNode(fantasia)));
        }
        $t.append($datos);
    };

    // ---------------- Acciones por fila ----------------
    // Todos los botones de la celda quedan visibles (sin menú "⋮"): solo se les da el estilo liviano.
    UI.agruparAcciones = function (contenedor) {
        $(contenedor).find('tbody > tr > td').each(function () {
            const $td = $(this);
            if ($td.data('uiAgrupado')) return;
            const botones = $td.find('button.btn, a.btn').filter(function () {
                return !$(this).closest('.dropdown-menu').length && !$(this).is('[data-ui-fijo]');
            });
            if (botones.length < 2) return;
            $td.addClass('ui-acciones');
            botones.removeClass('me-1 me-2 ms-1 mx-1');
            $td.data('uiAgrupado', true);
        });
    };

    // ---------------- Detalle expandible ----------------
    function columnasDetalle(settings) {
        return settings.aoColumns
            .map((c, i) => ((c.className || c.sClass || '').split(/\s+/).includes('ui-detalle') ? i : -1))
            .filter(i => i >= 0);
    }

    function prepararDetalle(settings) {
        const api = new $.fn.dataTable.Api(settings);
        const cols = columnasDetalle(settings);
        if (!cols.length) return;
        // Con Responsive, esas columnas ya van a su detalle desplegable (clase "none", ver header1.php)
        if (settings._responsive) return;
        api.columns(cols).visible(false, false);
        settings._uiDetalle = cols;
        // Se engancha directo en cada botón (no delegado) para cortar el clic antes de que llegue
        // a los handlers de fila que las páginas delegan en el tbody.
        settings._uiDetalleClick = function (e) {
            e.preventDefault();
            e.stopPropagation();
            const tr = $(this).closest('tr');
            const fila = api.row(tr);
            if (fila.child.isShown()) {
                fila.child.hide();
                tr.removeClass('ui-detalle-abierto');
                return;
            }
            const $dl = $('<dl class="ui-detalle-lista">');
            cols.forEach(function (c) {
                const titulo = $(api.column(c).header()).text().trim();
                const valor = api.cell(fila.index(), c).render('display');
                $dl.append($('<div>').append($('<dt>').text(titulo), $('<dd>').html(valor === null || valor === '' ? '-' : valor)));
            });
            fila.child($dl).show();
            tr.addClass('ui-detalle-abierto');
        };
    }

    function togglesDetalle(settings) {
        if (!settings._uiDetalle) return;
        $(settings.nTable).find('tbody > tr[role="row"], tbody > tr.odd, tbody > tr.even').each(function () {
            const $td = $(this).children('td').first();
            if (!$td.length || $td.find('.ui-detalle-toggle').length || $td.hasClass('dataTables_empty')) return;
            $('<button type="button" class="ui-detalle-toggle" aria-label="Ver detalle" title="Ver detalle"><i class="bi bi-chevron-right"></i></button>')
                .on('click', settings._uiDetalleClick)
                .prependTo($td);
        });
    }

    // ---------------- Buscador en la barra del listado ----------------
    // Si la tabla está en una card con barra (.ui-barra), el buscador de DataTables pasa a la barra,
    // al lado del botón principal, con placeholder en lugar del rótulo "Buscar:".
    function buscadorEnBarra(settings) {
        const $barra = $(settings.nTable).closest('.card').find('.card-header.ui-barra, .card-header:has(#contenedor-boton-agregar)').first();
        let $filtro = $(settings.nTableWrapper).find('.dataTables_filter').first();
        // header1.php ya lo pudo haber sacado del wrapper (tablas con el dom por defecto)
        if (!$filtro.length) {
            $filtro = $('.dt-filtro-movido').filter(function () { return $(this).data('tabla') === settings.sTableId; }).first();
        }
        if (!$filtro.length) return;
        $filtro.find('input').removeClass('w-auto ms-1 form-control-sm');
        const $input = $filtro.find('input');
        $input.attr('placeholder', 'Buscar…').addClass('form-control');
        $filtro.find('label').contents().filter(function () { return this.nodeType === 3; }).remove();
        // Ya está en la barra (o lo movió el script de header1.php para las tablas con el dom por defecto)
        // Algunas páginas arman su propio buscador en la cabecera y ocultan el de DataTables: no se duplica
        if ($barra.find('.dataTables_filter').not($filtro).length) return;
        if (!$barra.length || $filtro.closest('.ui-barra').length || $filtro.hasClass('dt-filtro-movido')) {
            $filtro.addClass('ui-buscador');
            return;
        }
        const $fila = $filtro.closest('.row');
        $filtro.addClass('ui-buscador');
        const $fin = $barra.children('.ui-barra-fin');
        const $agregar = $barra.children('#contenedor-boton-agregar');
        if ($agregar.length) $filtro.insertAfter($agregar);
        else if ($fin.length) $filtro.insertBefore($fin);
        else $filtro.appendTo($barra);
        if (!$.trim($fila.text()) && !$fila.find('input, select, button').length) $fila.remove();
    }

    // ---------------- Enganche con DataTables ----------------
    $(document).on('init.dt', function (e, settings) {
        if (!activo() || !settings || e.namespace !== 'dt') return;
        buscadorEnBarra(settings);
        prepararDetalle(settings);
        togglesDetalle(settings);
        if ($(settings.nTable).data('ui-acciones') !== 'no') UI.agruparAcciones(settings.nTable);
    });
    $(document).on('draw.dt', function (e, settings) {
        if (!activo() || !settings || e.namespace !== 'dt') return;
        togglesDetalle(settings);
        if ($(settings.nTable).data('ui-acciones') !== 'no') UI.agruparAcciones(settings.nTable);
        UI.tooltips(settings.nTable);
    });

    // Tablas que se iniciaron dentro de una solapa, modal o panel oculto (ancho 0): se reacomodan
    // al mostrarlo. Vale en todas las páginas, no solo las ui-simple.
    $(document).on('shown.bs.tab shown.bs.modal shown.bs.collapse', function () {
        if (!$.fn.dataTable) return;
        const tablas = $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        if (tablas.responsive) tablas.responsive.recalc();
    });

    // Responsive 2.x solo vuelve a medir con el resize de la ventana: si la tabla se armó antes de que la
    // página terminara de acomodarse, o cambia el ancho de su contenedor (menú lateral, panel que se abre),
    // quedaba desbordada. Se observa el contenedor de cada tabla y se recalcula cuando cambia su ancho.
    if (window.ResizeObserver) {
        const anchos = new WeakMap();
        const observador = new ResizeObserver(function (entradas) {
            entradas.forEach(function (en) {
                const ancho = Math.round(en.contentRect.width);
                if (!ancho || anchos.get(en.target) === ancho) return;
                anchos.set(en.target, ancho);
                const tabla = $.fn.dataTable.tables().find(function (t) { return $(t).closest('.dataTables_wrapper')[0] === en.target; });
                if (!tabla) return;
                const api = $(tabla).DataTable().columns.adjust();
                if (api.responsive) api.responsive.recalc();
            });
        });
        const observar = function (nTable) {
            const wrapper = $(nTable).closest('.dataTables_wrapper')[0];
            if (wrapper) observador.observe(wrapper);
        };
        $(document).on('init.dt', function (e, settings) {
            if (e.namespace === 'dt' && settings) observar(settings.nTable);
        });
        $(function () { if ($.fn.dataTable) $.fn.dataTable.tables().forEach(observar); });
    }

    // Filtros plegables en celular: un botón "Filtros" los muestra u oculta (en escritorio siempre visibles)
    function filtrosPlegables() {
        $('.ui-filtros').not('.modal .ui-filtros').each(function () {
            const $f = $(this);
            if ($f.data('uiPlegable')) return;
            $f.data('uiPlegable', true).addClass('ui-filtros-plegable');
            $('<button type="button" class="btn btn-sm btn-light ui-filtros-toggle" aria-expanded="false">')
                .html('<i class="bi bi-funnel me-1"></i>Filtros')
                .on('click', function () {
                    const abierto = $f.toggleClass('abierto').hasClass('abierto');
                    $(this).attr('aria-expanded', abierto ? 'true' : 'false');
                })
                .insertBefore($f);
        });
    }

    // ---------------- Mensajes de SweetAlert en lenguaje simple ----------------
    // Todas las páginas llaman a Swal.fire con títulos genéricos ("Error", "¡Éxito!", "Atención") y textos
    // técnicos. Acá se normalizan sin tocar cada página: títulos cotidianos, éxitos como aviso que se
    // cierra solo y errores de conexión en una frase clara. Las confirmaciones (con Cancelar) no cambian
    // de forma. El texto que viene del servidor se respeta.
    const TITULOS = [
        [/^(error|error!|errores|ups|oops)$/i, 'No se pudo completar'],
        [/^error al guardar$/i, 'No se pudo guardar'],
        [/^error al (cargar|obtener)( .*)?$/i, 'No se pudo cargar'],
        [/^error de (conexi[oó]n|red|comunicaci[oó]n)$/i, 'Sin conexión'],
        [/^(atenci[oó]n|advertencia|aviso|validaci[oó]n|datos incompletos|campos requeridos)$/i, 'Revisá los datos'],
        [/^([ée]xito|correcto|ok|listo|hecho|perfecto)$/i, 'Listo'],
    ];
    const TEXTOS = [
        [/^error (de|en la) (conexi[oó]n|comunicaci[oó]n|red)( con el servidor)?\.?$/i, 'No pudimos comunicarnos con el servidor. Probá de nuevo.'],
        [/^error al comunicarse con el servidor\.?$/i, 'No pudimos comunicarnos con el servidor. Probá de nuevo.'],
        [/^ocurri[oó] un error( inesperado)?\.?$/i, 'Algo salió mal. Probá de nuevo.'],
    ];
    const limpiar = (s) => String(s).replace(/^¡\s*/, '').replace(/\s*!$/, '').trim();
    function normalizar(o) {
        if (typeof o.title === 'string') {
            let t = limpiar(o.title);
            const m = TITULOS.find(([re]) => re.test(t));
            if (m) t = m[1];
            o.title = t;
        }
        if (typeof o.text === 'string') {
            const m = TEXTOS.find(([re]) => re.test(o.text.trim()));
            if (m) o.text = m[1];
        }
        const esConfirmacion = o.showCancelButton || o.showDenyButton || o.input || o.preConfirm;
        // Éxito simple: aviso arriba a la derecha que se cierra solo
        if (!esConfirmacion && o.icon === 'success' && !o.html && !o.toast) {
            const texto = (o.text || '').trim();
            if (texto && texto.length <= 90 && (!o.title || o.title === 'Listo' || /^(guardad|actualizad|eliminad|cread|registrad)[oa]s?$/i.test(o.title))) {
                o.title = texto.replace(/\s+correctamente\.?$/i, '').replace(/\.$/, '');
                delete o.text;
            }
            Object.assign(o, { toast: true, position: 'top-end', showConfirmButton: false, timer: o.timer || 2200, timerProgressBar: false });
        }
        return o;
    }
    function parchearSwal() {
        if (!window.Swal || Swal.__uiSimple) return;
        const original = Swal.fire.bind(Swal);
        Swal.fire = function (a, b, c) {
            if (!activo()) return original.apply(null, arguments);
            let o;
            if (a && typeof a === 'object' && !(a instanceof Element)) o = Object.assign({}, a);
            else o = { title: a, text: b, icon: c };
            const eraToast = !!o.toast;
            try { o = normalizar(o); } catch (e) { /* ante cualquier sorpresa, el mensaje sale como venía */ }
            const p = original(o);
            // Un éxito convertido en aviso se cierra solo: para el código que espera el "Aceptar"
            // (then(r => r.isConfirmed ...)) se responde como si se hubiera aceptado.
            if (o.toast && !eraToast) {
                return p.then(function (r) { return Object.assign({}, r, { isConfirmed: true, isDismissed: false, value: true }); });
            }
            return p;
        };
        Swal.__uiSimple = true;
    }

    $(function () {
        if (!activo()) return;
        UI.tooltips(document);
        filtrosPlegables();
        parchearSwal();
    });
    // SweetAlert puede cargarse después del footer (algunas páginas lo incluyen al final)
    window.addEventListener('load', function () { if (activo()) parchearSwal(); });
})(window.jQuery);
