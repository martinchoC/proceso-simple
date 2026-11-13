/**
 * Conciliación bancaria: utilidades comunes de las pantallas bancos_* (gestion y gestion_normal).
 * Global Bco. Las escrituras mandan el token CSRF (window.BCO_CSRF) en el header X-CSRF-Token.
 */
window.Bco = (function () {
    const esc = (t) => $('<div>').text(t == null ? '' : String(t)).html();
    const num = (v, dec = 2) => (v == null || v === '') ? '' :
        Fmt.numero(Number(v), { minimumFractionDigits: dec, maximumFractionDigits: dec });
    const fecha = (f) => f ? Fmt.fecha(f) : '';
    const importe = (v) => `<span class="font-monospace ${Number(v) < 0 ? 'text-danger' : ''}">${num(v)}</span>`;
    const letra = (i) => (i == null || i === '') ? '' : String.fromCharCode(65 + Number(i));

    function mensajeError(res, xhr) {
        let msg = (res && (res.message || res.error)) || 'No se pudo completar la operación.';
        if (xhr && xhr.responseText) {
            try { const j = JSON.parse(xhr.responseText); msg = j.message || j.error || msg; } catch (e) { /* respuesta no JSON */ }
        }
        return msg;
    }

    /** Mensaje de lo que rechazó pedir(): una respuesta {success:false} o un jqXHR. */
    function mensajeDe(r) {
        return (r && r.responseText !== undefined) ? mensajeError(null, r) : mensajeError(r);
    }

    function error(res, xhr, titulo) {
        Swal.fire({ icon: 'error', title: titulo || 'No se pudo completar', text: mensajeError(res, xhr) });
    }

    function ok(msg) {
        Swal.fire({ icon: 'success', title: msg || 'Listo', timer: 1600, showConfirmButton: false });
    }

    /** Petición al _ajax.php de la página; resuelve con res.data o rechaza mostrando el error. */
    function pedir(url, datos, opciones = {}) {
        const esForm = datos instanceof FormData;
        if (esForm) {
            if (!datos.has('empresa_idx')) datos.append('empresa_idx', window.EMPRESA_ID);
        } else {
            datos = Object.assign({ empresa_idx: window.EMPRESA_ID }, datos);
        }
        return new Promise((resolve, reject) => {
            $.ajax({
                url, method: opciones.metodo || 'POST', data: datos, dataType: 'json',
                processData: !esForm, contentType: esForm ? false : 'application/x-www-form-urlencoded; charset=UTF-8',
                headers: { 'X-CSRF-Token': window.BCO_CSRF || '' }
            }).done((res) => {
                if (res && res.success) return resolve(res);
                if (!opciones.silencioso) error(res);
                reject(res);
            }).fail((xhr) => {
                if (!opciones.silencioso) error(null, xhr);
                reject(xhr);
            });
        });
    }

    function claseBoton(b, defecto) {
        return (b.bg_clase && b.text_clase) ? `${b.bg_clase} ${b.text_clase}` : (b.color_clase || defecto);
    }

    /** Botón de fila del motor (conf__paginas_funciones). */
    function botonFila(b, id, nombre) {
        const icono = b.icono_clase ? `<i class="${b.icono_clase}"></i>` : esc(b.nombre_funcion);
        return `<button type="button" class="btn btn-sm ${claseBoton(b, 'btn-outline-primary')} me-1 btn-accion" title="${esc(b.nombre_funcion)}"
                    data-id="${id}" data-accion="${esc(b.accion_js)}" data-confirmable="${b.es_confirmable || 0}" data-nombre="${esc(nombre)}">${icono}</button>`;
    }

    function botonesFila(botones, id, nombre) {
        return (botones || []).map(b => botonFila(b, id, nombre)).join('') || '<span class="text-muted small">Sin acciones</span>';
    }

    /** Botón de página (función de origen 0). */
    function botonPagina(b, idHtml, extra = '') {
        return `<button type="button" class="btn ${claseBoton(b, 'btn-primary')} ${extra}" id="${idHtml}">
                  <i class="${b.icono_clase || 'fas fa-plus'} me-1"></i>${esc(b.nombre_funcion)}</button>`;
    }

    function estado(r) {
        return `<span class="badge ${r.bg_clase || 'bg-secondary'} ${r.text_clase || 'text-white'}">${esc(r.estado_registro || '')}</span>`;
    }

    /** Pide confirmación (y un motivo si se indica). Resuelve con el motivo ('' si no se pidió). */
    function confirmar(titulo, texto, conMotivo) {
        return Swal.fire({
            icon: 'question', title: titulo, text: texto, showCancelButton: true, confirmButtonText: 'Sí', cancelButtonText: 'Cancelar',
            input: conMotivo ? 'text' : undefined, inputPlaceholder: conMotivo ? 'Motivo' : undefined,
            inputValidator: conMotivo ? (v) => (!v || !v.trim()) ? 'Indicá el motivo.' : undefined : undefined
        }).then(r => r.isConfirmed ? (r.value || '') : Promise.reject('cancelado'));
    }

    /** dataSrc de DataTables: muestra el error y deja la tabla vacía si el listado falla. */
    function datos(j) {
        if (j && j.success) return j.data || [];
        error(j);
        return [];
    }

    function idioma() {
        return { url: window.APP_ASSETS_URL + 'js/datatables-i18n-2.1.8/es-ES.json' };
    }

    function opcionesSelect($sel, items, valor, texto, vacio) {
        $sel.html((vacio !== undefined ? `<option value="">${esc(vacio)}</option>` : '') +
            items.map(i => `<option value="${esc(i[valor])}">${esc(typeof texto === 'function' ? texto(i) : i[texto])}</option>`).join(''));
    }

    function listaAvisos(bloqueos, avisos) {
        let h = '';
        (bloqueos || []).forEach(t => { h += `<div class="alert alert-danger py-2 mb-2"><i class="bi bi-x-octagon me-1"></i>${esc(t)}</div>`; });
        (avisos || []).forEach(t => { h += `<div class="alert alert-warning py-2 mb-2"><i class="bi bi-exclamation-triangle me-1"></i>${esc(t)}</div>`; });
        return h;
    }

    return { esc, num, fecha, importe, letra, error, ok, pedir, botonFila, botonesFila, botonPagina, estado, confirmar, idioma,
             opcionesSelect, listaAvisos, mensajeError, mensajeDe, datos };
})();
