/**
 * Presentaciones de productos (caja, pack, pallet) en el formulario de productos de gestion / gestion_normal.
 * Vista: templates/productos/presentaciones_seccion.php (solo está si la empresa usa presentaciones).
 * Backend: acción obtener_presentaciones del productos_ajax.php de cada módulo y core/productos_presentaciones.php.
 *
 * Uso desde la página:
 *   ProdPres.init({ ajaxUrl: 'productos_ajax.php', empresaIdx: empresa_idx });
 *   ProdPres.activo()                    -> la sección está en la página
 *   ProdPres.limpiar()                   -> formulario de alta
 *   ProdPres.cargar(productoId, {soloLectura})
 *   ProdPres.recolectar()                -> array para enviar como JSON en "presentaciones", o string con el error
 */
(function (window, $) {
    'use strict';

    var cfg = { ajaxUrl: 'productos_ajax.php', empresaIdx: 0 };
    var token = 0;

    function $sec() { return $('#seccionPresentaciones'); }

    function esc(t) {
        if (t === null || t === undefined) return '';
        return String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // Valor numérico para un input number: sin ceros de relleno ("12.0000" -> "12")
    function num(v) {
        if (v === null || v === undefined || v === '') return '';
        var n = parseFloat(v);
        return isNaN(n) ? '' : String(n);
    }

    function unidadBase() {
        var $um = $('#unidad_medida_id');
        var txt = $um.length && $um.val() ? $.trim($um.find('option:selected').text()) : '';
        var m = txt.match(/\(([^)]+)\)\s*$/);
        return m ? m[1] : (txt || 'unidad');
    }

    function refrescarUnidad() {
        var u = unidadBase();
        $sec().find('.prpr-unidad-base').text(u);
        $sec().find('.prpr-u').text(u);
    }

    function filaHtml(p) {
        p = p || {};
        var compra = p.aplica_compra === undefined ? 1 : parseInt(p.aplica_compra, 10);
        var venta = p.aplica_venta === undefined ? 1 : parseInt(p.aplica_venta, 10);
        return '<tr class="prpr-fila" data-id="' + (parseInt(p.presentacion_id, 10) || 0) + '">' +
            '<td><input type="text" class="form-control form-control-sm prpr-nombre" maxlength="60" placeholder="Ej: Caja x 12" value="' + esc(p.presentacion_nombre) + '"></td>' +
            '<td><div class="input-group input-group-sm">' +
                '<input type="number" step="any" min="0" class="form-control prpr-factor" placeholder="12" value="' + esc(num(p.factor)) + '">' +
                '<span class="input-group-text prpr-u">' + esc(unidadBase()) + '</span></div></td>' +
            '<td><input type="text" class="form-control form-control-sm prpr-barras" maxlength="50" placeholder="DUN-14 / EAN" value="' + esc(p.codigo_barras) + '"></td>' +
            '<td><input type="number" step="any" min="0" class="form-control form-control-sm prpr-peso" value="' + esc(num(p.peso_bruto_kg)) + '"></td>' +
            '<td><div class="prpr-medidas">' +
                '<input type="number" step="any" min="0" class="form-control form-control-sm prpr-largo" placeholder="L" title="Largo (cm)" value="' + esc(num(p.largo_cm)) + '">' +
                '<input type="number" step="any" min="0" class="form-control form-control-sm prpr-ancho" placeholder="A" title="Ancho (cm)" value="' + esc(num(p.ancho_cm)) + '">' +
                '<input type="number" step="any" min="0" class="form-control form-control-sm prpr-alto" placeholder="H" title="Alto (cm)" value="' + esc(num(p.alto_cm)) + '">' +
            '</div></td>' +
            '<td><div class="prpr-uso">' +
                '<label class="mb-0" title="Se puede comprar en esta presentación"><input type="checkbox" class="form-check-input prpr-aplica-compra"' + (compra === 1 ? ' checked' : '') + '> se usa</label>' +
                '<label class="mb-0" title="Presentación habitual de compra"><input type="checkbox" class="form-check-input prpr-def-compra"' + (parseInt(p.es_compra_defecto, 10) === 1 ? ' checked' : '') + '> habitual</label>' +
            '</div></td>' +
            '<td><div class="prpr-uso">' +
                '<label class="mb-0" title="Se puede vender en esta presentación"><input type="checkbox" class="form-check-input prpr-aplica-venta"' + (venta === 1 ? ' checked' : '') + '> se usa</label>' +
                '<label class="mb-0" title="Presentación habitual de venta"><input type="checkbox" class="form-check-input prpr-def-venta"' + (parseInt(p.es_venta_defecto, 10) === 1 ? ' checked' : '') + '> habitual</label>' +
            '</div></td>' +
            '<td class="text-end"><button type="button" class="btn btn-sm btn-link text-danger p-0 prpr-quitar" title="Quitar presentación"><i class="fas fa-trash-alt"></i></button></td>' +
            '</tr>';
    }

    function actualizarVacio() {
        var $tb = $sec().find('tbody');
        $tb.find('.prpr-vacio').toggle($tb.find('.prpr-fila').length === 0);
    }

    function pintar(filas, soloLectura) {
        var $tb = $sec().find('tbody');
        $tb.find('.prpr-fila').remove();
        (filas || []).forEach(function (p) { $tb.append(filaHtml(p)); });
        actualizarVacio();
        bloquear(!!soloLectura);
    }

    function bloquear(si) {
        $sec().find(':input').prop('disabled', si);
        $sec().find('.prpr-agregar, .prpr-quitar').toggle(!si);
    }

    var ProdPres = {
        init: function (opciones) {
            $.extend(cfg, opciones || {});
            if (!ProdPres.activo()) return;
            var $s = $sec();
            $s.on('click', '.prpr-agregar', function () {
                $s.find('tbody').append(filaHtml({}));
                actualizarVacio();
                $s.find('.prpr-fila:last .prpr-nombre').trigger('focus');
            });
            $s.on('click', '.prpr-quitar', function () {
                $(this).closest('tr').remove();
                actualizarVacio();
            });
            // Una sola habitual de compra y una de venta; habitual implica "se usa"
            $s.on('change', '.prpr-def-compra, .prpr-def-venta', function () {
                if (!this.checked) return;
                var lado = $(this).hasClass('prpr-def-compra') ? 'compra' : 'venta';
                $s.find('.prpr-def-' + lado).not(this).prop('checked', false);
                $(this).closest('tr').find('.prpr-aplica-' + lado).prop('checked', true);
            });
            $s.on('change', '.prpr-aplica-compra, .prpr-aplica-venta', function () {
                if (this.checked) return;
                var lado = $(this).hasClass('prpr-aplica-compra') ? 'compra' : 'venta';
                $(this).closest('tr').find('.prpr-def-' + lado).prop('checked', false);
            });
            $(document).on('change', '#unidad_medida_id', refrescarUnidad);
            refrescarUnidad();
        },

        activo: function () { return $sec().length > 0; },

        limpiar: function () {
            if (!ProdPres.activo()) return;
            token++;
            pintar([], false);
            refrescarUnidad();
        },

        cargar: function (productoId, opciones) {
            if (!ProdPres.activo()) return;
            var soloLectura = !!(opciones && opciones.soloLectura);
            var t = ++token;
            refrescarUnidad();
            if (!productoId) { pintar([], soloLectura); return; }
            var $tb = $sec().find('tbody');
            $tb.find('.prpr-fila').remove();
            $tb.find('.prpr-vacio').show().find('td').text('Cargando presentaciones...');
            $.get(cfg.ajaxUrl, { accion: 'obtener_presentaciones', producto_id: productoId, empresa_idx: cfg.empresaIdx }, function (res) {
                if (t !== token) return; // respuesta vieja
                $tb.find('.prpr-vacio td').text('Sin presentaciones: se compra y se vende solo por unidad.');
                pintar(res && res.success && Array.isArray(res.data) ? res.data : [], soloLectura);
            }, 'json').fail(function () {
                if (t !== token) return;
                $tb.find('.prpr-vacio td').html('<span class="text-danger">No se pudieron cargar las presentaciones</span>');
            });
        },

        /** Array para mandar como JSON, o string con el primer error (la fila queda marcada). */
        recolectar: function () {
            if (!ProdPres.activo()) return [];
            var filas = [];
            var error = null;
            $sec().find('.prpr-fila').each(function () {
                var $tr = $(this);
                $tr.find('input').removeClass('is-invalid');
                var nombre = $.trim($tr.find('.prpr-nombre').val());
                var factor = $.trim($tr.find('.prpr-factor').val());
                if (!error && nombre === '') { error = 'Falta el nombre de una presentación.'; $tr.find('.prpr-nombre').addClass('is-invalid'); }
                if (!error && !(parseFloat(factor) > 0)) { error = 'La presentación "' + nombre + '" tiene que contener más de cero unidades.'; $tr.find('.prpr-factor').addClass('is-invalid'); }
                filas.push({
                    presentacion_id: parseInt($tr.data('id'), 10) || 0,
                    presentacion_nombre: nombre,
                    factor: factor,
                    codigo_barras: $.trim($tr.find('.prpr-barras').val()),
                    peso_bruto_kg: $.trim($tr.find('.prpr-peso').val()),
                    largo_cm: $.trim($tr.find('.prpr-largo').val()),
                    ancho_cm: $.trim($tr.find('.prpr-ancho').val()),
                    alto_cm: $.trim($tr.find('.prpr-alto').val()),
                    aplica_compra: $tr.find('.prpr-aplica-compra').is(':checked') ? 1 : 0,
                    aplica_venta: $tr.find('.prpr-aplica-venta').is(':checked') ? 1 : 0,
                    es_compra_defecto: $tr.find('.prpr-def-compra').is(':checked') ? 1 : 0,
                    es_venta_defecto: $tr.find('.prpr-def-venta').is(':checked') ? 1 : 0
                });
            });
            return error || filas;
        },

        bloquear: bloquear
    };

    window.ProdPres = ProdPres;
})(window, jQuery);
