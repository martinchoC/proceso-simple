/**
 * Importar precios por presentación (Listas de precios, gestion / gestion_normal).
 * Vista: templates/listas_precios/importar_presentaciones.php. Backend: acciones importar_presentaciones_* del
 * listas_precios_ajax.php de cada módulo (core/listas_precios_presentaciones_import.php).
 * Uso: LpreImport.init({ ajaxUrl: 'listas_precios_ajax.php', empresaIdx, paginaId, alTerminar: fn })
 * Abre el modal cualquier botón con la clase .btn-lpre-importar.
 */
(function (window, $) {
    'use strict';

    var cfg = { ajaxUrl: 'listas_precios_ajax.php', empresaIdx: 0, paginaId: 0, alTerminar: null };
    var token = null;

    var ESTADOS = {
        NUEVO: ['Nuevo', 'success'], CAMBIA: ['Cambia', 'primary'], QUITAR: ['Se quita', 'warning'],
        IGUAL: ['Sin cambio', 'secondary'], REPETIDA: ['Repetida', 'secondary'], ERROR: ['Error', 'danger']
    };

    function esc(t) {
        if (t === null || t === undefined) return '';
        return String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function moneda(v) {
        if (v === null || v === undefined || v === '') return '—';
        return window.Fmt && window.Fmt.moneda ? window.Fmt.moneda(v) : Number(v).toFixed(2);
    }
    function aviso(msg) { if (window.UiSimple) window.UiSimple.aviso(msg); else Swal.fire({ icon: 'warning', text: msg }); }
    function error(msg) { if (window.UiSimple) window.UiSimple.error(msg, 'No se pudo importar'); else Swal.fire({ icon: 'error', text: msg }); }
    function modal() { return bootstrap.Modal.getOrCreateInstance(document.getElementById('modalLpreImport')); }

    function kpi(etiqueta, valor, sub) {
        return '<div class="col-6 col-md-2"><div class="ui-kpi"><div class="ui-kpi-etiqueta">' + esc(etiqueta) + '</div>' +
            '<div class="ui-kpi-valor">' + esc(valor) + '</div>' + (sub ? '<div class="ui-kpi-sub">' + esc(sub) + '</div>' : '') + '</div></div>';
    }

    function limpiar() {
        token = null;
        $('#lpreImpArchivo').val('');
        $('#lpreImpResultado').addClass('d-none');
        $('#lpreImpFilas').empty();
        $('#lpreImpConfirmar').prop('disabled', true);
    }

    function mostrar(d) {
        var r = d.resumen || {};
        $('#lpreImpKpis').html(
            kpi('Nuevos', r.NUEVO || 0) + kpi('Cambian', r.CAMBIA || 0) + kpi('Se quitan', r.QUITAR || 0) +
            kpi('Sin cambio', r.IGUAL || 0) + kpi('Con error', r.ERROR || 0, r.ERROR ? 'no se graban' : '') +
            kpi('Filas vacías', r.vacias || 0));
        var nota = [];
        if (d.listas_reconocidas && d.listas_reconocidas.length) nota.push('Columnas de listas: ' + d.listas_reconocidas.join(', ') + '.');
        if (d.total_filas > (d.filas || []).length) nota.push('Se muestran ' + d.filas.length + ' de ' + d.total_filas + ' (errores y cambios primero).');
        nota.push(d.aplicables ? 'Al grabar se aplican ' + d.aplicables + ' precios, todos juntos: si uno falla no se graba ninguno.' : 'No hay cambios para grabar.');
        $('#lpreImpNota').text(nota.join(' '));
        $('#lpreImpFilas').html((d.filas || []).map(function (f) {
            var e = ESTADOS[f.estado] || [f.estado, 'secondary'];
            return '<tr><td>' + esc(f.fila) + '</td><td>' + esc(f.presentacion) + '</td><td>' + esc(f.lista) + '</td>' +
                '<td class="text-end">' + moneda(f.actual) + '</td><td class="text-end">' + moneda(f.nuevo) + '</td>' +
                '<td><span class="badge text-bg-' + e[1] + '">' + esc(e[0]) + '</span>' +
                (f.mensaje ? ' <span class="small text-muted">' + esc(f.mensaje) + '</span>' : '') + '</td></tr>';
        }).join('') || '<tr><td colspan="6" class="text-center text-muted small">El archivo no trae precios.</td></tr>');
        $('#lpreImpResultado').removeClass('d-none');
        token = d.token;
        $('#lpreImpConfirmar').prop('disabled', !d.aplicables);
    }

    function simular() {
        var archivo = $('#lpreImpArchivo')[0].files[0];
        if (!archivo) { aviso('Elegí la planilla con los precios.'); return; }
        var fd = new FormData();
        fd.append('accion', 'importar_presentaciones_simular');
        fd.append('empresa_idx', cfg.empresaIdx);
        fd.append('pagina_id', cfg.paginaId);
        fd.append('archivo', archivo);
        var $b = $('#lpreImpSimular').prop('disabled', true);
        $('#lpreImpConfirmar').prop('disabled', true);
        $.ajax({ url: cfg.ajaxUrl, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
            .done(function (res) {
                if (res && res.success) mostrar(res.data); else error((res && res.message) || 'No se pudo leer el archivo.');
            })
            .fail(function () { error('No se pudo leer el archivo.'); })
            .always(function () { $b.prop('disabled', false); });
    }

    function confirmar() {
        if (!token) return;
        var $b = $('#lpreImpConfirmar').prop('disabled', true);
        $.post(cfg.ajaxUrl, { accion: 'importar_presentaciones_confirmar', empresa_idx: cfg.empresaIdx, pagina_id: cfg.paginaId, token: token }, null, 'json')
            .done(function (res) {
                if (res && res.success) {
                    token = null;
                    modal().hide();
                    if (window.UiSimple) window.UiSimple.ok(res.message || 'Precios grabados'); else Swal.fire({ icon: 'success', text: res.message });
                    if (typeof cfg.alTerminar === 'function') cfg.alTerminar();
                } else {
                    error((res && res.message) || 'No se grabó nada.');
                    $b.prop('disabled', false);
                }
            })
            .fail(function () { error('No se grabó nada.'); $b.prop('disabled', false); });
    }

    window.LpreImport = {
        init: function (opciones) {
            $.extend(cfg, opciones || {});
            if (!$('#modalLpreImport').length) return;
            $('#lpreImpSimular').on('click', simular);
            $('#lpreImpConfirmar').on('click', confirmar);
            $('#lpreImpArchivo').on('change', function () {
                token = null;
                $('#lpreImpConfirmar').prop('disabled', true);
                $('#lpreImpResultado').addClass('d-none');
            });
            $('#lpreImpPlantilla').on('click', function (e) {
                e.preventDefault();
                window.location.href = cfg.ajaxUrl + '?' + $.param({ accion: 'importar_presentaciones_plantilla', empresa_idx: cfg.empresaIdx, pagina_id: cfg.paginaId });
            });
            $(document).on('click', '.btn-lpre-importar', function () {
                limpiar();
                modal().show();
            });
        }
    };
})(window, jQuery);
