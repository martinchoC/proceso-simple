/**
 * Canales de venta (Listas de precios, gestion / gestion_normal). Vista: templates/listas_precios/canales_venta.php.
 * Backend: acciones canales_listar / canal_guardar del listas_precios_ajax.php de cada módulo (cvta_despachar en
 * core/canales_venta.php).
 * Uso: CanalesVenta.init({ ajaxUrl: 'listas_precios_ajax.php', empresaIdx, paginaId, select: '#canal_venta_id' })
 * Abre la ventana cualquier botón con la clase .btn-cvta-canales. Al guardar, rehace las opciones del select.
 */
(function (window, $) {
    'use strict';

    var cfg = { ajaxUrl: 'listas_precios_ajax.php', empresaIdx: 0, paginaId: 0, select: '#canal_venta_id' };
    var canales = [];

    function esc(t) {
        if (t === null || t === undefined) return '';
        return String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function ok(msg) { if (window.UiSimple) window.UiSimple.ok(msg); }
    function error(msg) { if (window.UiSimple) window.UiSimple.error(msg, 'No se pudo guardar'); else Swal.fire({ icon: 'error', text: msg }); }

    function pedir(accion, datos) {
        return $.ajax({
            url: cfg.ajaxUrl, type: 'POST', dataType: 'json',
            data: $.extend({ accion: accion, empresa_idx: cfg.empresaIdx, pagina_id: cfg.paginaId }, datos || {})
        });
    }

    function fila(c) {
        var id = c ? c.canal_venta_id : 0;
        return '<tr data-id="' + id + '">' +
            '<td><input type="text" class="form-control form-control-sm cvta-nombre" maxlength="60" value="' + esc(c ? c.nombre : '') + '"></td>' +
            '<td><input type="text" class="form-control form-control-sm cvta-codigo" maxlength="20" value="' + esc(c ? c.codigo : '') + '" placeholder="automático"></td>' +
            '<td><input type="number" class="form-control form-control-sm cvta-orden" min="0" value="' + esc(c ? c.orden : 0) + '"></td>' +
            '<td><div class="form-check form-switch"><input class="form-check-input cvta-activo" type="checkbox"' + (!c || c.tabla_estado_registro_id === 1 ? ' checked' : '') + '></div></td>' +
            '<td><button type="button" class="btn btn-sm btn-primary cvta-guardar" data-funcion="editar">Guardar</button></td>' +
            '</tr>';
    }

    function pintar() {
        $('#cvtaFilas').html(canales.length ? canales.map(fila).join('') :
            '<tr class="cvta-vacio"><td colspan="5" class="text-muted small">Todavía no hay canales. Agregá, por ejemplo, Minorista y Mayorista.</td></tr>');
    }

    // Opciones del select de la lista: los activos, más el elegido aunque esté inactivo
    function opciones() {
        var $s = $(cfg.select);
        if (!$s.length) return;
        var actual = String($s.val() || '');
        var html = '<option value="">Sin canal</option>';
        canales.forEach(function (c) {
            if (c.tabla_estado_registro_id === 1 || String(c.canal_venta_id) === actual) {
                html += '<option value="' + c.canal_venta_id + '">' + esc(c.nombre) + (c.tabla_estado_registro_id === 1 ? '' : ' (inactivo)') + '</option>';
            }
        });
        $s.html(html).val(actual);
    }

    function cargar() {
        return pedir('canales_listar').done(function (r) {
            if (r && r.success) { canales = r.data.canales || []; pintar(); opciones(); }
        });
    }

    function guardar($tr) {
        var $btn = $tr.find('.cvta-guardar').prop('disabled', true);
        pedir('canal_guardar', {
            canal_venta_id: $tr.data('id') || 0,
            nombre: $tr.find('.cvta-nombre').val(),
            codigo: $tr.find('.cvta-codigo').val(),
            orden: $tr.find('.cvta-orden').val() || 0,
            activo: $tr.find('.cvta-activo').is(':checked') ? 1 : 0
        }).done(function (r) {
            if (r && r.success) { canales = r.data.canales || []; pintar(); opciones(); ok('Guardado'); }
            else error((r && r.message) || 'No se pudo guardar el canal.');
        }).fail(function (x) {
            error((x.responseJSON && (x.responseJSON.message || x.responseJSON.error)) || 'No se pudo guardar el canal.');
        }).always(function () { $btn.prop('disabled', false); });
    }

    var CanalesVenta = {
        init: function (opciones_) {
            $.extend(cfg, opciones_ || {});
            $(document).on('click', '.btn-cvta-canales', function () {
                cargar();
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCanalesVenta')).show();
            });
            $(document).on('click', '#cvtaNuevo', function () {
                $('#cvtaFilas .cvta-vacio').remove();
                $('#cvtaFilas').append(fila(null));
                $('#cvtaFilas tr:last .cvta-nombre').trigger('focus');
            });
            $(document).on('click', '#cvtaFilas .cvta-guardar', function () { guardar($(this).closest('tr')); });
            cargar();
        }
    };

    window.CanalesVenta = CanalesVenta;
})(window, jQuery);
