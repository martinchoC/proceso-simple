/**
 * Cobro de contado a consumidor final al aprobar una factura de venta, para las pantallas que aprueban facturas
 * fuera de Facturas de venta (Remitos de Pedidos y Remitos Pendientes de Facturación, que facturan desde el
 * remito). Mismo criterio que el modal "Cobro de contado" de ventas_facturas.js: si la factura no tiene cliente
 * (cobro_contado_info.requiere), se piden los medios (efectivo, transferencia, cheque; sin retenciones), que
 * tienen que sumar el total, y viajan en medios_contado al aprobar. Sin medios, el servidor cobra en efectivo.
 *
 * Uso:
 *   CobroContado.pedirSiCorresponde({ facturaId, empresaIdx, paginaIdx, comprobante, target })
 *     .then(function (medios) { ... });   // null = no requiere; array = medios elegidos; false = canceló
 * target: elemento donde se monta el SweetAlert (el modal de Bootstrap abierto, para que no le robe el foco).
 */
(function (window, $) {
    'use strict';

    var URL = 'ventas_facturas_ajax.php';
    var maestros = null;
    var info = null;

    function esc(s) {
        return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function moneda(v) { return window.Fmt ? Fmt.moneda(v) : '$ ' + Number(v || 0).toFixed(2); }

    function getJSON(data) {
        return new Promise(function (resolve, reject) {
            $.getJSON(URL, data).done(resolve).fail(function () { reject(new Error('Error al comunicarse con el servidor.')); });
        });
    }

    function cuentas(tipo) {
        return (maestros.cuentas_tesoreria || []).filter(function (c) {
            return c.cuenta_tipo === tipo && (tipo !== 'CAJA' || !c.sucursal_id || parseInt(c.sucursal_id, 10) === info.sucursal_id);
        }).map(function (c) { return '<option value="' + c.tesoreria_cuenta_id + '">' + esc(c.tesoreria_cuenta) + '</option>'; }).join('');
    }

    function renderCampos(tr) {
        var tipo = tr.find('.cc-medio option:selected').data('tipo');
        var html = '';
        if (tipo === 'EFECTIVO') {
            html = '<select class="form-select form-select-sm cc-cuenta">' + (cuentas('CAJA') || '<option value="">Sin caja en la sucursal de la factura</option>') + '</select>';
        } else if (tipo === 'TRANSFERENCIA') {
            html = '<div class="row g-1"><div class="col-sm-6"><select class="form-select form-select-sm cc-cuenta">' + cuentas('BANCO') + '</select></div>'
                + '<div class="col-sm-6"><input type="text" class="form-control form-control-sm cc-nro-operacion" placeholder="N° Operación"></div></div>';
        } else if (tipo === 'CHEQUE_TERCERO') {
            var bancos = (maestros.bancos || []).map(function (b) { return '<option value="' + b.banco_id + '">' + esc(b.banco) + '</option>'; }).join('');
            html = '<div class="row g-1"><div class="col-sm-4"><select class="form-select form-select-sm cc-cheque-banco"><option value="">-- Banco --</option>' + bancos + '</select></div>'
                + '<div class="col-sm-2"><input type="text" class="form-control form-control-sm cc-cheque-numero" placeholder="N° Cheque"></div>'
                + '<div class="col-sm-3"><input type="date" class="form-control form-control-sm cc-cheque-fpago" title="Fecha de pago"></div>'
                + '<div class="col-sm-3"><input type="text" class="form-control form-control-sm cc-cheque-librador" placeholder="Librador"></div></div>';
        }
        tr.find('.cc-campos').html(html);
    }

    function total(tabla) {
        var t = 0;
        tabla.find('.cc-importe').each(function () { t += parseFloat(this.value) || 0; });
        return Math.round(t * 100) / 100;
    }

    function recalcular(tabla) {
        var t = total(tabla);
        var dif = Math.round((info.total - t) * 100) / 100;
        $('#ccTotalMedios').text(moneda(t));
        if (dif > 0.001) $('#ccDiferencia').attr('class', 'text-danger').text('Falta cobrar ' + moneda(dif));
        else if (dif < -0.001) $('#ccDiferencia').attr('class', 'text-danger').text('Supera el total en ' + moneda(-dif));
        else $('#ccDiferencia').attr('class', 'text-success').text('Cubre el total de la factura.');
    }

    function agregarFila(tabla, importe) {
        var opciones = (maestros.medios_pago || []).filter(function (m) { return m.medio_pago_tipo !== 'RETENCION'; })
            .map(function (m) { return '<option value="' + m.medio_pago_id + '" data-tipo="' + esc(m.medio_pago_tipo) + '">' + esc(m.medio_pago) + '</option>'; }).join('');
        var tr = $('<tr>'
            + '<td><select class="form-select form-select-sm cc-medio">' + opciones + '</select></td>'
            + '<td class="cc-campos"></td>'
            + '<td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm text-end cc-importe" value="' + (importe > 0 ? Number(importe).toFixed(2) : '') + '"></td>'
            + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger cc-quitar" title="Quitar"><i class="fas fa-times"></i></button></td>'
            + '</tr>');
        tabla.find('tbody').append(tr);
        renderCampos(tr);
        recalcular(tabla);
        return tr;
    }

    function recopilar(tabla) {
        var medios = [], error = null;
        tabla.find('tbody tr').each(function (i) {
            var tr = $(this), tipo = tr.find('.cc-medio option:selected').data('tipo');
            var m = { medio_pago_id: parseInt(tr.find('.cc-medio').val(), 10) || 0, importe: parseFloat(tr.find('.cc-importe').val()) || 0 };
            var fila = 'Fila ' + (i + 1) + ': ';
            if (m.importe <= 0) { error = fila + 'el importe tiene que ser mayor a cero.'; return false; }
            if (tipo === 'EFECTIVO' || tipo === 'TRANSFERENCIA') {
                m.tesoreria_cuenta_id = parseInt(tr.find('.cc-cuenta').val(), 10) || null;
                if (!m.tesoreria_cuenta_id) { error = fila + 'elegí la ' + (tipo === 'EFECTIVO' ? 'caja' : 'cuenta bancaria') + '.'; return false; }
                if (tipo === 'TRANSFERENCIA') m.nro_operacion = $.trim(tr.find('.cc-nro-operacion').val());
            } else if (tipo === 'CHEQUE_TERCERO') {
                m.cheque = {
                    banco_id: parseInt(tr.find('.cc-cheque-banco').val(), 10) || 0,
                    numero: $.trim(tr.find('.cc-cheque-numero').val()),
                    f_pago: tr.find('.cc-cheque-fpago').val(),
                    librador_nombre: $.trim(tr.find('.cc-cheque-librador').val())
                };
                if (!m.cheque.banco_id || !m.cheque.numero || !m.cheque.f_pago) { error = fila + 'completá banco, número y fecha de pago del cheque.'; return false; }
            }
            medios.push(m);
        });
        if (!error && !medios.length) error = 'Agregá al menos un medio de pago.';
        return error ? { error: error } : { medios: medios };
    }

    function pedir(comprobante, target) {
        return Swal.fire({
            target: target || 'body',
            title: 'Cobro de contado',
            html: '<p class="mb-2">' + esc(comprobante) + ' a consumidor final por <strong>' + moneda(info.total) + '</strong>. ¿Cómo pagó?</p>'
                + '<div class="table-responsive text-start"><table class="table table-sm align-middle mb-2" id="ccTablaMedios">'
                + '<thead><tr><th style="width:28%">Medio</th><th>Datos</th><th style="width:18%" class="text-end">Importe</th><th style="width:40px"></th></tr></thead><tbody></tbody></table></div>'
                + '<div class="d-flex justify-content-between align-items-start">'
                + '<button type="button" class="btn btn-sm btn-outline-primary" id="ccAgregarMedio"><i class="fas fa-plus me-1"></i>Agregar medio</button>'
                + '<div class="text-end"><div>Total medios: <strong id="ccTotalMedios"></strong></div><small id="ccDiferencia"></small></div></div>',
            width: 960, showCancelButton: true, confirmButtonText: 'Aprobar y cobrar', cancelButtonText: 'Cancelar',
            reverseButtons: true, allowOutsideClick: false, focusConfirm: false,
            didOpen: function () {
                var tabla = $('#ccTablaMedios');
                var tr = agregarFila(tabla, info.total);
                var efectivo = tr.find('.cc-medio option[data-tipo="EFECTIVO"]').first();
                if (efectivo.length) { tr.find('.cc-medio').val(efectivo.val()); renderCampos(tr); }
                $('#ccAgregarMedio').on('click', function () {
                    agregarFila(tabla, Math.max(0, Math.round((info.total - total(tabla)) * 100) / 100));
                });
                tabla.on('change', '.cc-medio', function () { renderCampos($(this).closest('tr')); });
                tabla.on('input', '.cc-importe', function () { recalcular(tabla); });
                tabla.on('click', '.cc-quitar', function () { $(this).closest('tr').remove(); recalcular(tabla); });
            },
            preConfirm: function () {
                var tabla = $('#ccTablaMedios');
                var r = recopilar(tabla);
                if (r.error) { Swal.showValidationMessage(r.error); return false; }
                if (Math.abs(total(tabla) - info.total) > 0.001) {
                    Swal.showValidationMessage('Los medios tienen que sumar el total de la factura (' + moneda(info.total) + ').');
                    return false;
                }
                return r.medios;
            }
        }).then(function (r) { return r.isConfirmed && r.value ? r.value : false; });
    }

    function pedirSiCorresponde(o) {
        return getJSON({ accion: 'cobro_contado_info', venta_factura_id: o.facturaId, empresa_idx: o.empresaIdx, pagina_idx: o.paginaIdx })
            .then(function (res) {
                if (!res || !res.success) throw new Error((res && (res.message || res.error)) || 'No se pudo leer la factura.');
                if (!res.data.requiere) return null;
                info = res.data;
                var cargar = maestros ? Promise.resolve() : getJSON({ accion: 'cobro_maestros', empresa_idx: o.empresaIdx }).then(function (m) {
                    if (!m || !m.success) throw new Error((m && (m.message || m.error)) || 'No se pudieron cargar los medios de pago.');
                    maestros = m.data;
                });
                return cargar.then(function () { return pedir(o.comprobante || ('Factura #' + o.facturaId), o.target); });
            });
    }

    window.CobroContado = { pedirSiCorresponde: pedirSiCorresponde };
})(window, jQuery);
