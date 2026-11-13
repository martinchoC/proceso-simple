// IVA - Posición Mensual (página 117 / 617). Script: _local/sql/mantenimiento/20261011_iva_posiciones_01.sql
$(function () {
    var URL_AJAX = 'iva_posiciones_ajax.php';
    var permisos = { liquidar: false, anular: false };
    var periodoActual = null;
    var detalleActual = null;

    function esc(t) { return $('<div>').text(t == null ? '' : t).html(); }
    function moneda(v) { return Fmt.numero(Number(v || 0), { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function fecha(d) { return d ? Fmt.fecha(d) : ''; }
    function nombreMes(p) {
        var m = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        return m[parseInt(p.substr(5, 2), 10) - 1] + ' ' + p.substr(0, 4);
    }
    function error(x, def) {
        Swal.fire('Error', (x && x.responseJSON && x.responseJSON.message) || (x && x.message) || def, 'error');
    }
    function signo(v) { return Number(v) < 0 ? 'text-danger' : ''; }

    // Libro IVA con columnas dinámicas (alícuotas y percepciones del período): tabla HTML simple
    function renderLibro(destino, l) {
        var cols = l.columnas;
        var cab = '<th>Fecha</th><th>Comprobante</th><th>' + (l.libro === 'VENTAS' ? 'Cliente' : 'Proveedor') + '</th><th>CUIT</th>'
            + cols.map(function (c) {
                return '<th class="text-end' + (c.grupo === 'percepcion' ? ' text-info' : '') + '" title="' + esc(c.titulo) + '">' + esc(c.titulo) + '</th>';
            }).join('');
        var cuerpo = l.filas.map(function (f) {
            return '<tr><td class="text-nowrap">' + fecha(f.fecha) + '</td>'
                + '<td class="text-nowrap">' + esc(f.comprobante_tipo) + ' ' + String(f.pv || 0).padStart(4, '0') + '-' + String(f.numero || 0).padStart(8, '0')
                + (f.codigo_arca ? ' <small class="text-muted">(' + esc(f.codigo_arca) + ')</small>' : '') + '</td>'
                + '<td>' + esc(f.entidad) + '</td><td class="text-nowrap">' + esc(f.cuit || '') + '</td>'
                + cols.map(function (c) {
                    var v = Number(f.valores[c.clave] || 0);
                    if (c.grupo === 'control') {
                        return '<td class="text-end ' + (Math.abs(v) > 0.05 ? 'text-danger fw-bold' : 'text-muted') + '">' + (Math.abs(v) >= 0.01 ? moneda(v) : '') + '</td>';
                    }
                    return '<td class="text-end' + (c.grupo === 'total' ? ' fw-bold' : '') + '">' + (v ? moneda(v) : '') + '</td>';
                }).join('') + '</tr>';
        }).join('') || '<tr><td colspan="' + (cols.length + 4) + '" class="text-muted small">Sin comprobantes fiscales en el período.</td></tr>';
        var pie = '<tr class="fw-bold table-light"><td colspan="4">Totales (' + l.filas.length + ' comprobantes)</td>'
            + cols.map(function (c) { return '<td class="text-end">' + moneda(l.totales[c.clave]) + '</td>'; }).join('') + '</tr>';
        $(destino).html('<div class="table-responsive" style="max-height: 60vh;"><table class="table table-sm table-striped table-hover align-middle mb-0 small">'
            + '<thead class="table-light" style="position: sticky; top: 0;"><tr>' + cab + '</tr></thead><tbody>' + cuerpo + '</tbody><tfoot>' + pie + '</tfoot></table></div>'
            + '<small class="text-muted">Importes firmados (las notas de crédito restan). Control = total − (netos + IVA + no gravado + exento + percepciones): '
            + 'en rojo, comprobantes cuyo total no cierra con sus líneas; unos centavos en las facturas B son redondeo.</small>');
    }

    function cargarLibros(periodo) {
        $('#ivaTabLibroVentas, #ivaTabLibroCompras').each(function () {
            var destino = this, libro = $(this).data('libro');
            $(destino).html('<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i></div>');
            $.getJSON(URL_AJAX, { accion: 'libro', periodo: periodo, libro: libro }, function (r) {
                if (!r || !r.success) { $(destino).html('<div class="text-danger small">' + esc((r && r.message) || 'No se pudo cargar el libro.') + '</div>'); return; }
                renderLibro(destino, r.data);
            }).fail(function (x) { $(destino).html('<div class="text-danger small">No se pudo cargar el libro.</div>'); });
        });
    }

    // ---------------------------------------------------------------- resumen de 12 meses
    function cargarResumen() {
        $.getJSON(URL_AJAX, { accion: 'resumen', hasta: $('#ivaHasta').val(), meses: 12 }, function (r) {
            if (!r || !r.success) { error(null, (r && r.message) || 'No se pudo cargar el resumen.'); return; }
            var html = r.data.map(function (m) {
                var liq;
                if (m.posicion) {
                    liq = m.posicion.saldo_a_pagar > 0
                        ? '<span class="badge bg-' + (m.posicion.pagada ? 'success' : 'warning text-dark') + '">A pagar $' + moneda(m.posicion.saldo_a_pagar)
                          + (m.posicion.liquidacion ? ' · I_LIQ ' + esc(m.posicion.liquidacion) : '') + (m.posicion.pagada ? ' · pagada' : '') + '</span>'
                        : '<span class="badge bg-info text-dark">A favor $' + moneda(m.posicion.saldo_a_favor) + '</span>';
                } else {
                    liq = '<span class="badge bg-light text-muted border">Sin liquidar</span>';
                }
                return '<tr class="fila-mes' + (m.periodo === periodoActual ? ' table-active' : '') + '" role="button" data-periodo="' + m.periodo + '">'
                    + '<td class="fw-bold">' + nombreMes(m.periodo) + '</td>'
                    + '<td class="text-end">' + moneda(m.debito_fiscal) + '</td>'
                    + '<td class="text-end">' + moneda(m.credito_fiscal) + '</td>'
                    + '<td class="text-end">' + moneda(m.percepciones_iva) + '</td>'
                    + '<td class="text-end">' + moneda(m.retenciones_iva) + '</td>'
                    + '<td class="text-end fw-bold ' + signo(m.saldo_tecnico_mes) + '">' + moneda(m.saldo_tecnico_mes) + '</td>'
                    + '<td class="text-end text-muted">' + moneda(m.percepciones_otras) + '</td>'
                    + '<td class="text-end text-muted">' + moneda(m.retenciones_otras) + '</td>'
                    + '<td class="text-end text-muted">' + moneda(m.percepciones_practicadas) + '</td>'
                    + '<td>' + liq + '</td></tr>';
            }).join('');
            $('#tablaIvaMeses tbody').html(html);
        }).fail(function (x) { error(x, 'No se pudo cargar el resumen.'); });
    }

    // ---------------------------------------------------------------- detalle del período
    function kpi(titulo, valor, clase, ayuda) {
        return '<div class="col-6 col-md-4 col-xl-2"><div class="border rounded p-2 h-100 ' + (clase || '') + '">'
             + '<div class="small text-muted">' + titulo + '</div><div class="fs-5 fw-bold">$' + moneda(valor) + '</div>'
             + (ayuda ? '<div class="small text-muted">' + ayuda + '</div>' : '') + '</div></div>';
    }

    function tablaAlicuotas(filas, conComputable) {
        if (!filas.length) return '<p class="text-muted small mb-0">Sin comprobantes en el período.</p>';
        var t = { neto: 0, iva: 0, ng: 0, ex: 0 };
        var cuerpo = filas.map(function (f) {
            var comp = conComputable && Number(f.computable) !== 1;
            if (!comp) { t.neto += Number(f.neto_gravado); t.iva += Number(f.iva); }
            t.ng += Number(f.no_gravado); t.ex += Number(f.exento);
            return '<tr' + (comp ? ' class="text-muted"' : '') + '><td>' + esc(f.iva_alicuota) + (comp ? ' <small>(B / C: no computable)</small>' : '') + '</td>'
                 + '<td class="text-end">' + moneda(f.neto_gravado) + '</td><td class="text-end">' + moneda(f.iva) + '</td>'
                 + '<td class="text-end">' + moneda(f.no_gravado) + '</td><td class="text-end">' + moneda(f.exento) + '</td></tr>';
        }).join('');
        return '<table class="table table-sm mb-0"><thead class="table-light"><tr><th>Alícuota</th><th class="text-end">Neto gravado</th>'
             + '<th class="text-end">IVA</th><th class="text-end">No gravado</th><th class="text-end">Exento</th></tr></thead><tbody>' + cuerpo + '</tbody>'
             + '<tfoot class="fw-bold"><tr><td>Total' + (conComputable ? ' computable' : '') + '</td><td class="text-end">' + moneda(t.neto) + '</td>'
             + '<td class="text-end">' + moneda(t.iva) + '</td><td class="text-end">' + moneda(t.ng) + '</td><td class="text-end">' + moneda(t.ex) + '</td></tr></tfoot></table>';
    }

    function tablaSimple(titulo, filas, etiqueta) {
        var total = 0;
        var cuerpo = filas.map(function (f) {
            total += Number(f.importe);
            return '<tr><td>' + etiqueta(f) + '</td><td class="text-end">' + (f.comprobantes || '') + '</td><td class="text-end">' + moneda(f.importe) + '</td></tr>';
        }).join('');
        return '<h6 class="fw-bold mt-2">' + titulo + '</h6>' + (filas.length
            ? '<table class="table table-sm mb-2"><thead class="table-light"><tr><th>Concepto</th><th class="text-end">Comprob.</th><th class="text-end">Importe</th></tr></thead><tbody>'
              + cuerpo + '</tbody><tfoot class="fw-bold"><tr><td colspan="2">Total</td><td class="text-end">' + moneda(total) + '</td></tr></tfoot></table>'
            : '<p class="text-muted small">Sin movimientos.</p>');
    }

    function renderDetalle(d) {
        var c = d.calculo, v = d.vigente;
        $('#ivaPeriodoTitulo').text(nombreMes(periodoActual));
        var avisos = '';
        if (v && d.diferencias.length) {
            avisos += '<div class="alert alert-warning py-1 small mb-2"><i class="fas fa-exclamation-triangle me-1"></i>Hubo cambios en los comprobantes después de liquidar: '
                    + d.diferencias.map(function (x) { return esc(x.concepto.replace('_', ' ')) + ' liquidado $' + moneda(x.liquidado) + ' / actual $' + moneda(x.actual); }).join('; ')
                    + '. Anule la liquidación y vuelva a liquidar.</div>';
        }
        if (d.liquidacion_posterior) {
            avisos += '<div class="alert alert-info py-1 small mb-2">El período ' + esc(d.liquidacion_posterior) + ' ya está liquidado y arrastra el saldo de este.</div>';
        }
        $('#ivaAvisos').html(avisos);

        var fuente = v || c;
        $('#ivaKpis').html(
            kpi('Débito fiscal', fuente.debito_fiscal) + kpi('Crédito fiscal', fuente.credito_fiscal) +
            kpi('Percepciones IVA', fuente.percepciones_iva) + kpi('Retenciones IVA', fuente.retenciones_iva) +
            kpi('Saldo a favor anterior', fuente.saldo_favor_anterior, '', c.saldo_favor_anterior_periodo ? 'de ' + nombreMes(c.saldo_favor_anterior_periodo) : '') +
            (Number(fuente.saldo_a_pagar) > 0
                ? kpi('A pagar', fuente.saldo_a_pagar, 'border-danger bg-danger-subtle', v ? 'liquidado' : 'sin liquidar')
                : kpi('Saldo a favor', fuente.saldo_a_favor, 'border-info bg-info-subtle', v ? 'liquidado' : 'sin liquidar')));

        var pos = '<table class="table table-sm mb-2" style="max-width:560px"><tbody>'
            + '<tr><td>Débito fiscal (IVA Ventas)</td><td class="text-end">' + moneda(c.debito_fiscal) + '</td></tr>'
            + '<tr><td>− Crédito fiscal computable (IVA Compras A / M)</td><td class="text-end">' + moneda(c.credito_fiscal) + '</td></tr>'
            + '<tr><td>− Percepciones de IVA sufridas</td><td class="text-end">' + moneda(c.percepciones_iva) + '</td></tr>'
            + '<tr><td>− Retenciones de IVA sufridas</td><td class="text-end">' + moneda(c.retenciones_iva) + '</td></tr>'
            + '<tr><td>− Saldo a favor del período anterior</td><td class="text-end">' + moneda(c.saldo_favor_anterior) + '</td></tr>'
            + '<tr class="fw-bold"><td>' + (c.saldo_a_pagar > 0 ? 'Saldo a pagar' : 'Saldo a favor') + '</td><td class="text-end">'
            + moneda(c.saldo_a_pagar > 0 ? c.saldo_a_pagar : c.saldo_a_favor) + '</td></tr></tbody></table>';
        if (v) {
            pos += '<div class="small"><span class="badge bg-success">Liquidado</span> el ' + fecha(v.fecha_creacion)
                 + (v.liquidacion ? ' · I_LIQ ' + esc(v.liquidacion) + (v.pagada ? ' <span class="badge bg-success">con pagos</span>' : ' <span class="badge bg-warning text-dark">pendiente de pago</span>') : '')
                 + (v.observaciones ? ' · ' + esc(v.observaciones) : '') + '</div>';
        } else {
            pos += '<div class="small text-muted">Sin liquidar. Al liquidar se genera el asiento de la posición'
                 + (c.saldo_a_pagar > 0 ? ' y la obligación (I_LIQ), que se paga desde Órdenes de Pago.' : '; el saldo a favor pasa al período siguiente.') + '</div>';
        }
        $('#ivaTabPosicion').html(pos);
        $('#ivaTabVentas').html(tablaAlicuotas(d.ventas, false));
        $('#ivaTabCompras').html(tablaAlicuotas(d.compras, true));
        $('#ivaTabPercRet').html(
            tablaSimple('Percepciones sufridas (compras)', d.percepciones, function (f) { return esc(f.impuesto_tipo || 'Sin tipo') + (f.jurisdiccion_nombre ? ' · ' + esc(f.jurisdiccion_nombre) : ''); }) +
            tablaSimple('Retenciones sufridas (cobranzas)', d.retenciones, function (f) { return esc(f.impuesto_tipo || f.medio_pago); }) +
            tablaSimple('Percepciones IIBB practicadas (ventas, se liquidan en Percepciones IIBB)', d.practicadas, function (f) { return esc(f.jurisdiccion_nombre || 'Sin jurisdicción'); }));

        $('#btnIvaLiquidar').toggleClass('d-none', !permisos.liquidar || !!v);
        $('#btnIvaAnular').toggleClass('d-none', !permisos.anular || !v || !!d.liquidacion_posterior || !!(v && v.pagada));
        $('#ivaDetalleCard').removeClass('d-none');
    }

    function cargarDetalle(periodo) {
        periodoActual = periodo;
        $('#tablaIvaMeses tbody tr').removeClass('table-active').filter('[data-periodo="' + periodo + '"]').addClass('table-active');
        $.getJSON(URL_AJAX, { accion: 'detalle', periodo: periodo }, function (r) {
            if (!r || !r.success) { error(null, (r && r.message) || 'No se pudo cargar el período.'); return; }
            detalleActual = r.data;
            renderDetalle(r.data);
        }).fail(function (x) { error(x, 'No se pudo cargar el período.'); });
        cargarLibros(periodo);
    }

    function recargarTodo() {
        cargarResumen();
        if (periodoActual) cargarDetalle(periodoActual);
    }

    // ---------------------------------------------------------------- acciones
    $('#tablaIvaMeses').on('click', 'tr.fila-mes', function () { cargarDetalle($(this).data('periodo')); });
    $('#ivaHasta').on('change', cargarResumen);
    $('#btnIvaRecargar').on('click', recargarTodo);
    $(document).on('click', '.btn-iva-csv', function (e) {
        e.preventDefault();
        if (periodoActual) window.location = URL_AJAX + '?' + $.param({ accion: 'exportar_libro', periodo: periodoActual, libro: $(this).data('libro') });
    });

    $('#btnIvaLiquidar').on('click', function () {
        if (!detalleActual) return;
        var c = detalleActual.calculo;
        var pagar = Number(c.saldo_a_pagar) > 0;
        $('#ivaLiqTexto').html('Período <b>' + nombreMes(periodoActual) + '</b>: ' + (pagar
            ? 'a pagar <b>$' + moneda(c.saldo_a_pagar) + '</b>. Se genera la I_LIQ y el asiento de la posición.'
            : 'saldo a favor <b>$' + moneda(c.saldo_a_favor) + '</b>. Se genera el asiento; el saldo pasa al período siguiente (sin comprobante).'));
        $('#ivaLiqPagar').toggle(pagar);
        $('#ivaLiqEntidad').prop('required', pagar);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalIvaLiquidar')).show();
    });

    $('#formIvaLiquidar').on('submit', function (e) {
        e.preventDefault();
        var btn = $(this).find('[type=submit]').prop('disabled', true);
        $.post(URL_AJAX, {
            accion: 'liquidar', periodo: periodoActual, sucursal_id: $('#ivaLiqSucursal').val(), entidad_id: $('#ivaLiqEntidad').val(),
            f_vto: $('#ivaLiqVto').val(), nro_ddjj: $('#ivaLiqDdjj').val(), observaciones: $('#ivaLiqObs').val()
        }, null, 'json').done(function (r) {
            if (!r || !r.success) { error(null, (r && r.message) || 'No se pudo liquidar.'); return; }
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalIvaLiquidar')).hide();
            Swal.fire({ icon: 'success', title: 'Período liquidado', text: r.message });
            recargarTodo();
        }).fail(function (x) { error(x, 'No se pudo liquidar.'); }).always(function () { btn.prop('disabled', false); });
    });

    $('#btnIvaAnular').on('click', function () {
        Swal.fire({
            title: '¿Anular la liquidación de ' + nombreMes(periodoActual) + '?',
            html: '<p class="small mb-0">Se anulan la I_LIQ (si la hay, sin pagos) y el asiento de la posición. Después se puede volver a liquidar.</p>',
            icon: 'warning', input: 'textarea', inputPlaceholder: 'Motivo de la anulación',
            inputValidator: function (v) { return (!v || !v.trim()) ? 'Ingrese el motivo' : null; },
            showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, anular', cancelButtonText: 'Cancelar', reverseButtons: true
        }).then(function (res) {
            if (!res.isConfirmed) return;
            $.post(URL_AJAX, { accion: 'anular', periodo: periodoActual, motivo: res.value.trim() }, null, 'json').done(function (r) {
                if (!r || !r.success) { error(null, (r && r.message) || 'No se pudo anular.'); return; }
                Swal.fire({ icon: 'success', title: 'Liquidación anulada', text: r.message });
                recargarTodo();
            }).fail(function (x) { error(x, 'No se pudo anular.'); });
        });
    });

    // ---------------------------------------------------------------- inicio
    var hoy = new Date();
    $('#ivaHasta').val(hoy.toISOString().slice(0, 7));
    $.getJSON(URL_AJAX, { accion: 'maestros' }, function (r) {
        if (r && r.success) {
            permisos.liquidar = !!r.data.puede_liquidar;
            permisos.anular = !!r.data.puede_anular;
            $('#ivaLiqSucursal').html((r.data.sucursales || []).map(function (s) {
                return '<option value="' + s.sucursal_id + '">' + esc(s.sucursal_nombre) + '</option>';
            }).join(''));
            $('#ivaLiqEntidad').html('<option value="">Seleccione...</option>' + (r.data.organismos || []).map(function (e) {
                return '<option value="' + e.entidad_id + '">' + esc(e.entidad_nombre) + '</option>';
            }).join(''));
        }
        cargarResumen();
        var anterior = new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1);   // por defecto, el mes que se declara
        cargarDetalle(anterior.getFullYear() + '-' + String(anterior.getMonth() + 1).padStart(2, '0'));
    });
});
