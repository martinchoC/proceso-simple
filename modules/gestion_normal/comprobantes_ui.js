/**
 * comprobantes_ui.js
 *
 * Helpers compartidos para mostrar comprobantes (gestion__comprobantes): formato de importes,
 * fechas y numeración, badge de estado y modal de detalle con imputaciones.
 * Lo usan comprobantes_consulta.php, clientes_ctacte.php y la solapa de clientes.php.
 */
(function (window, $) {
    'use strict';

    const fmtImporte = Fmt.numberFormat({ minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function esc(valor) {
        return $('<div>').text(valor === null || valor === undefined ? '' : String(valor)).html();
    }

    function moneda(valor, simbolo) {
        const n = parseFloat(valor);
        if (isNaN(n)) return '';
        return (simbolo || '$') + ' ' + fmtImporte.format(n);
    }

    function fecha(iso) {
        return iso ? esc(Fmt.fecha(iso)) : '';
    }

    /** "FA A 0001-00000012" / "RCX 0001-00000003" / "PC (borrador)" */
    function numero(c) {
        const tipo = c.tipo_codigo || c.otro_tipo_codigo || '';
        const letra = c.letra || c.otro_letra || '';
        const pv = c.comprobante_pv !== undefined ? c.comprobante_pv : c.otro_pv;
        const nro = c.comprobante_nro !== undefined ? c.comprobante_nro : c.otro_nro;
        const prefijo = (tipo + (letra && tipo.indexOf(letra) === -1 ? ' ' + letra : '')).trim();
        if (!parseInt(nro, 10)) {
            return prefijo + ' (borrador)';
        }
        return prefijo + ' ' + String(parseInt(pv, 10) || 0).padStart(4, '0') + '-' + String(parseInt(nro, 10)).padStart(8, '0');
    }

    // Por código estándar: conf__colores tiene casi todos los estados en "primario".
    function claseEstado(c) {
        switch (c.codigo_estandar) {
            case 'CONFIRMADO': return 'bg-primary';
            case 'CANC_TOTAL': return 'bg-success';
            case 'CANC_PARCIAL': return 'bg-info text-dark';
            case 'BORRADOR': return 'bg-secondary';
            case 'ANULADO':
            case 'ELIMINADO': return 'bg-danger';
            case 'PEND_APROB':
            case 'PEND_FACT':
            case 'FACT_PARC': return 'bg-warning text-dark';
            case 'FACTURADO': return 'bg-success';
            default: return 'bg-light text-dark border';
        }
    }

    function badgeEstado(c) {
        return '<span class="badge ' + claseEstado(c) + '">' + esc(c.estado || c.codigo_estandar || '-') + '</span>';
    }

    /** Opciones comunes de DataTables (botones Excel/PDF; el idioma lo fija header1.php). */
    function opcionesTabla(extra) {
        return $.extend(true, {
            pageLength: 25,
            autoWidth: false,
            buttons: [
                { extend: 'excelHtml5', text: '<i class="fas fa-file-excel"></i> Excel', className: 'btn btn-success btn-sm me-1', exportOptions: { columns: ':visible:not(.no-export)' } },
                { extend: 'pdfHtml5', text: '<i class="fas fa-file-pdf"></i> PDF', className: 'btn btn-danger btn-sm', orientation: 'landscape', pageSize: 'A4', exportOptions: { columns: ':visible:not(.no-export)' } }
            ]
        }, extra || {});
    }

    // ---------------------------------------------------------------------------------
    // Modal de detalle (se crea una sola vez por página)
    // ---------------------------------------------------------------------------------
    function asegurarModal() {
        if ($('#modalDetalleComprobante').length) return;
        $('body').append(
            '<div class="modal fade" id="modalDetalleComprobante" tabindex="-1" aria-hidden="true">' +
            '  <div class="modal-dialog modal-lg modal-dialog-scrollable">' +
            '    <div class="modal-content">' +
            '      <div class="modal-header bg-dark text-white py-2">' +
            '        <h5 class="modal-title fs-6 fw-bold"><i class="fas fa-file-invoice me-2"></i><span id="detCompTitulo">Comprobante</span></h5>' +
            '        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>' +
            '      </div>' +
            '      <div class="modal-body p-3" id="detCompCuerpo"></div>' +
            '      <div class="modal-footer bg-light py-2">' +
            '        <a href="#" class="btn btn-outline-primary btn-sm d-none" id="detCompIrOrigen" target="_blank" rel="noopener">' +
            '          <i class="fas fa-external-link-alt me-1"></i>Ir a la pantalla de origen</a>' +
            '        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>' +
            '      </div>' +
            '    </div>' +
            '  </div>' +
            '</div>'
        );
        // Abierto sobre otro modal (solapa de clientes.php): queda por encima de él y de su fondo
        $('#modalDetalleComprobante').on('show.bs.modal', function () {
            const abiertos = $('.modal.show').not(this).length;
            $(this).css('z-index', 1055 + abiertos * 20);
            setTimeout(function () {
                $('.modal-backdrop').last().css('z-index', 1050 + abiertos * 20);
            }, 0);
        }).on('hidden.bs.modal', function () {
            // Bootstrap saca modal-open del body al cerrar; si queda otro modal abierto, se repone
            if ($('.modal.show').length) $('body').addClass('modal-open');
        });
    }

    function filaDato(etiqueta, valor) {
        return '<div class="col-md-4 col-6 mb-2"><div class="small text-muted">' + esc(etiqueta) + '</div><div class="fw-semibold">' + valor + '</div></div>';
    }

    function renderDetalle(data) {
        const c = data.comprobante;
        const sim = c.moneda_simbolo || '$';
        let html = '<div class="row">' +
            filaDato('Comprobante', esc(numero(c)) + '<div class="small text-muted">' + esc(c.comprobante_tipo) + '</div>') +
            filaDato('Estado', badgeEstado(c)) +
            filaDato('Entidad', esc(c.entidad_nombre || '-') + (c.cuit ? '<div class="small text-muted">CUIT ' + esc(c.cuit) + '</div>' : '')) +
            filaDato('Emisión', fecha(c.f_emision)) +
            filaDato('Vencimiento', c.f_vto ? fecha(c.f_vto) : '-') +
            filaDato('Sucursal', esc(c.sucursal_nombre || '-')) +
            filaDato('Neto', moneda(c.importe_neto, sim)) +
            filaDato('IVA', moneda(c.importe_iva, sim)) +
            filaDato('Total', '<span class="fs-6">' + moneda(c.importe_total, sim) + '</span>' +
                (parseFloat(c.tipo_cambio) !== 1 ? '<div class="small text-muted">TC ' + esc(c.tipo_cambio) + '</div>' : '')) +
            (c.cae
                ? filaDato('CAE', '<i class="fas fa-shield-alt text-success me-1"></i>' + esc(c.cae) +
                    (c.f_vto_cae ? '<div class="small text-muted">Vence ' + fecha(c.f_vto_cae) + '</div>' : ''))
                : '') +
            '</div>';

        if (c.saldo !== null && c.saldo !== undefined) {
            html += '<div class="alert ' + (c.saldo > 0.009 ? 'alert-warning' : 'alert-success') + ' py-2 small mb-3">' +
                '<i class="fas fa-balance-scale me-1"></i>Saldo pendiente: <strong>' + moneda(c.saldo, sim) + '</strong>' +
                ' (imputado como cancelado: ' + moneda(c.imputado_destino, sim) + ' · aplicado a otros: ' + moneda(c.imputado_origen, sim) + ')</div>';
        }
        if (c.observaciones && c.observaciones !== '0') {
            html += '<p class="small mb-3"><span class="text-muted">Observaciones:</span> ' + esc(c.observaciones) + '</p>';
        }

        html += '<h6 class="fw-bold small text-uppercase text-primary mb-2"><i class="fas fa-link me-1"></i>Imputaciones</h6>';
        if (!data.imputaciones || !data.imputaciones.length) {
            html += '<div class="text-muted small">Sin imputaciones.</div>';
        } else {
            html += '<div class="table-responsive"><table class="table table-sm table-bordered align-middle small mb-0">' +
                '<thead class="table-light"><tr><th>Fecha</th><th>Relación</th><th>Comprobante</th><th class="text-end">Importe</th><th>Estado</th></tr></thead><tbody>';
            data.imputaciones.forEach(function (i) {
                const anulada = i.imputacion_codigo && i.imputacion_codigo !== 'CONFIRMADO';
                html += '<tr' + (anulada ? ' class="text-muted text-decoration-line-through"' : '') + '>' +
                    '<td>' + fecha(i.f_imputacion) + '</td>' +
                    '<td>' + (i.rol === 'CANCELADO_POR' ? 'Cancelado por' : 'Cancela a') + '</td>' +
                    '<td>' + esc(numero(i)) + '<div class="text-muted">' + fecha(i.otro_f_emision) + ' · ' + moneda(i.otro_importe_total) + '</div></td>' +
                    '<td class="text-end">' + moneda(i.rol === 'CANCELADO_POR' ? i.importe_imputado : i.importe_origen) + '</td>' +
                    '<td>' + esc(i.imputacion_estado || '') + '</td></tr>';
            });
            html += '</tbody></table></div>';
        }

        $('#detCompTitulo').text(numero(c));
        $('#detCompCuerpo').html(html);
        const $ir = $('#detCompIrOrigen');
        if (c.origen_url && c.origen_pagina_id) {
            $ir.attr('href', c.origen_url + '?pagina_id=' + c.origen_pagina_id + '&modulo_id=5')
               .html('<i class="fas fa-external-link-alt me-1"></i>Ir a ' + esc(c.origen_pagina))
               .removeClass('d-none');
        } else {
            $ir.addClass('d-none');
        }
    }

    /**
     * Abre el modal de detalle. url/params: endpoint y parámetros de la acción que devuelve
     * {comprobante, imputaciones} (comprobantes_consulta_ajax 'obtener' o clientes_ctacte_ajax
     * 'obtener_comprobante').
     */
    function mostrarDetalle(url, params) {
        asegurarModal();
        $('#detCompTitulo').text('Comprobante');
        $('#detCompCuerpo').html('<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div> Cargando...</div>');
        $('#detCompIrOrigen').addClass('d-none');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalleComprobante')).show();

        $.getJSON(url, params).done(function (res) {
            if (res && res.success) {
                renderDetalle(res.data);
            } else {
                $('#detCompCuerpo').html('<div class="alert alert-danger small mb-0">' + esc((res && res.message) || 'No se pudo obtener el comprobante.') + '</div>');
            }
        }).fail(function (xhr) {
            const msg = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Error de conexión con el servidor.';
            $('#detCompCuerpo').html('<div class="alert alert-danger small mb-0">' + esc(msg) + '</div>');
        });
    }

    function hoyMenosDias(dias) {
        const d = new Date();
        d.setDate(d.getDate() - dias);
        return d.toISOString().substring(0, 10);
    }

    window.ComprobantesUI = {
        esc: esc,
        moneda: moneda,
        fecha: fecha,
        numero: numero,
        badgeEstado: badgeEstado,
        opcionesTabla: opcionesTabla,
        mostrarDetalle: mostrarDetalle,
        hoyMenosDias: hoyMenosDias
    };
})(window, jQuery);
