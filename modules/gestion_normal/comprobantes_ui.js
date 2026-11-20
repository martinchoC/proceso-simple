/**
 * comprobantes_ui.js
 *
 * Helpers compartidos para mostrar comprobantes (gestion__comprobantes) y el Expediente Completo
 * en formato Árbol Explorer (Resumen, Imputaciones navegables, Asientos Contables, Documentos
 * Escaneados con visor inline, Comentarios multiusuario e Historial/Log).
 * Lo usan comprobantes_consulta.php, clientes_ctacte.php, proveedores_ctacte.php y clientes.php.
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

    function fechaHora(iso) {
        if (!iso) return '';
        const p = String(iso).split(' ');
        const f = fecha(p[0]);
        const h = p[1] && p[1] !== '00:00:00' ? ' ' + esc(p[1].substring(0, 5)) : '';
        return f + h;
    }

    function formatoBytes(b) {
        b = parseInt(b, 10) || 0;
        if (b < 1024) return b + ' B';
        if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
        return (b / 1048576).toFixed(1) + ' MB';
    }

    function iniciales(nombre) {
        const partes = String(nombre || 'U').trim().split(/\s+/);
        return ((partes[0] ? partes[0][0] : '') + (partes[1] ? partes[1][0] : '')).toUpperCase() || 'U';
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
    // Estilos modernos del Explorer (se inyectan una sola vez)
    // ---------------------------------------------------------------------------------
    function asegurarEstilos() {
        if ($('#compExplorerStyles').length) return;
        $('head').append(
            '<style id="compExplorerStyles">' +
            '.cexp-wrap{display:flex;min-height:540px;max-height:74vh;background:#f8fafc;}' +
            '.cexp-side{width:290px;flex:0 0 290px;background:#f1f5f9;border-right:1px solid #e2e8f0;overflow-y:auto;padding:12px 10px;}' +
            '.cexp-main{flex:1;min-width:0;overflow-y:auto;padding:18px 20px;background:#fff;}' +
            '.cexp-root{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:10px 12px;margin-bottom:12px;box-shadow:0 1px 2px rgba(15,23,42,.04);}' +
            '.cexp-node{display:flex;align-items:center;justify-content:space-between;gap:6px;padding:7px 10px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;color:#334155;transition:all .12s ease;border-left:3px solid transparent;user-select:none;}' +
            '.cexp-node:hover{background:#e2e8f0;color:#0f172a;}' +
            '.cexp-node.active{background:#dbeafe;color:#1d4ed8;border-left-color:#2563eb;font-weight:600;}' +
            '.cexp-children{margin-left:14px;padding-left:10px;border-left:1px dashed #cbd5e1;margin-top:2px;margin-bottom:6px;}' +
            '.cexp-child{display:flex;align-items:center;gap:7px;padding:5px 8px;border-radius:6px;cursor:pointer;font-size:12px;color:#475569;transition:all .12s ease;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}' +
            '.cexp-child:hover{background:#e2e8f0;color:#0f172a;}' +
            '.cexp-child.active{background:#eff6ff;color:#1d4ed8;font-weight:600;}' +
            '.cexp-pill{font-size:11px;padding:1px 7px;border-radius:999px;background:#e2e8f0;color:#475569;font-weight:600;}' +
            '.cexp-node.active .cexp-pill{background:#bfdbfe;color:#1e40af;}' +
            '.cexp-card{border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;background:#fff;margin-bottom:12px;}' +
            '.cexp-preview-frame{width:100%;height:460px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;}' +
            '.cexp-preview-img{max-width:100%;max-height:460px;border:1px solid #e2e8f0;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.06);}' +
            '.cexp-avatar{width:30px;height:30px;border-radius:50%;background:#2563eb;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex:0 0 30px;}' +
            '.cexp-timeline{position:relative;padding-left:22px;}' +
            '.cexp-timeline:before{content:"";position:absolute;left:7px;top:6px;bottom:6px;width:2px;background:#e2e8f0;}' +
            '.cexp-tl-item{position:relative;padding-bottom:14px;}' +
            '.cexp-tl-dot{position:absolute;left:-20px;top:3px;width:12px;height:12px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 0 2px #cbd5e1;}' +
            '@media (max-width: 768px){.cexp-wrap{flex-direction:column;max-height:none;}.cexp-side{width:100%;flex:none;border-right:none;border-bottom:1px solid #e2e8f0;max-height:220px;}}' +
            '</style>'
        );
    }

    // ---------------------------------------------------------------------------------
    // Modal de detalle Explorer (se crea una sola vez por página)
    // ---------------------------------------------------------------------------------
    function asegurarModal() {
        asegurarEstilos();
        if ($('#modalDetalleComprobante').length) return;
        $('body').append(
            '<div class="modal fade" id="modalDetalleComprobante" tabindex="-1" aria-hidden="true">' +
            '  <div class="modal-dialog modal-xl modal-dialog-scrollable">' +
            '    <div class="modal-content border-0 shadow-lg">' +
            '      <div class="modal-header bg-dark text-white py-2 px-3">' +
            '        <div class="d-flex align-items-center gap-2">' +
            '          <button type="button" class="btn btn-sm btn-outline-light d-none" id="detCompVolver" title="Volver al comprobante anterior"><i class="fas fa-arrow-left"></i></button>' +
            '          <h5 class="modal-title fs-6 fw-bold mb-0"><i class="fas fa-folder-open me-2 text-info"></i><span id="detCompTitulo">Expediente del Comprobante</span></h5>' +
            '        </div>' +
            '        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>' +
            '      </div>' +
            '      <div class="modal-body p-0" id="detCompCuerpo"></div>' +
            '      <div class="modal-footer bg-light py-2 px-3">' +
            '        <a href="#" class="btn btn-outline-primary btn-sm d-none" id="detCompIrOrigen" target="_blank" rel="noopener">' +
            '          <i class="fas fa-external-link-alt me-1"></i>Ir a la pantalla de origen</a>' +
            '        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>' +
            '      </div>' +
            '    </div>' +
            '  </div>' +
            '</div>'
        );
        $('#modalDetalleComprobante').on('show.bs.modal', function () {
            const abiertos = $('.modal.show').not(this).length;
            $(this).css('z-index', 1055 + abiertos * 20);
            setTimeout(function () {
                $('.modal-backdrop').last().css('z-index', 1050 + abiertos * 20);
            }, 0);
        }).on('hidden.bs.modal', function () {
            if ($('.modal.show').length) $('body').addClass('modal-open');
            pilaNavegacion = [];
        });
    }

    let estadoExp = { url: null, params: null, data: null, nodo: 'resumen' };
    let pilaNavegacion = [];

    function filaDato(etiqueta, valor) {
        return '<div class="col-md-4 col-6 mb-2"><div class="small text-muted">' + esc(etiqueta) + '</div><div class="fw-semibold">' + valor + '</div></div>';
    }

    // ---------------------------------------------------------------------------------
    // Renderizado de cada vista del panel derecho
    // ---------------------------------------------------------------------------------
    function vistaResumen(data) {
        const c = data.comprobante;
        const sim = c.moneda_simbolo || '$';
        let html = '<div class="d-flex justify-content-between align-items-center mb-3">' +
            '<h6 class="fw-bold mb-0"><i class="fas fa-file-alt text-primary me-2"></i>Datos del Comprobante</h6>' +
            badgeEstado(c) + '</div>';
        html += '<div class="cexp-card"><div class="row">' +
            filaDato('Comprobante', esc(numero(c)) + '<div class="small text-muted">' + esc(c.comprobante_tipo) + '</div>') +
            filaDato('Entidad', esc(c.entidad_nombre || '-') + (c.cuit ? '<div class="small text-muted">CUIT ' + esc(c.cuit) + '</div>' : '')) +
            filaDato('Sucursal', esc(c.sucursal_nombre || '-')) +
            filaDato('Emisión', fecha(c.f_emision)) +
            filaDato('Vencimiento', c.f_vto ? fecha(c.f_vto) : '-') +
            (c.cae ? filaDato('CAE', '<i class="fas fa-shield-alt text-success me-1"></i>' + esc(c.cae) + (c.f_vto_cae ? '<div class="small text-muted">Vence ' + fecha(c.f_vto_cae) + '</div>' : '')) : filaDato('Moneda', esc(sim))) +
            filaDato('Neto', moneda(c.importe_neto, sim)) +
            filaDato('IVA', moneda(c.importe_iva, sim)) +
            filaDato('Total', '<span class="fs-5 text-dark">' + moneda(c.importe_total, sim) + '</span>' +
                (parseFloat(c.tipo_cambio) !== 1 ? '<div class="small text-muted">TC ' + esc(c.tipo_cambio) + '</div>' : '')) +
            '</div></div>';

        if (c.saldo !== null && c.saldo !== undefined) {
            html += '<div class="alert ' + (c.saldo > 0.009 ? 'alert-warning' : 'alert-success') + ' py-2 small mb-3">' +
                '<i class="fas fa-balance-scale me-1"></i>Saldo pendiente: <strong>' + moneda(c.saldo, sim) + '</strong>' +
                ' (imputado como cancelado: ' + moneda(c.imputado_destino, sim) + ' · aplicado a otros: ' + moneda(c.imputado_origen, sim) + ')</div>';
        }
        if (c.observaciones && c.observaciones !== '0') {
            html += '<div class="cexp-card"><div class="small text-muted mb-1">Observaciones</div><div class="small">' + esc(c.observaciones) + '</div></div>';
        }
        return html;
    }

    function vistaImputaciones(data) {
        const imps = data.imputaciones || [];
        let html = '<h6 class="fw-bold mb-3"><i class="fas fa-link text-primary me-2"></i>Imputaciones y Comprobantes Vinculados</h6>';
        if (!imps.length) {
            return html + '<div class="text-muted small">Sin imputaciones registradas para este comprobante.</div>';
        }
        html += '<div class="table-responsive"><table class="table table-sm table-bordered table-hover align-middle small mb-0">' +
            '<thead class="table-light"><tr><th>Fecha</th><th>Relación</th><th>Comprobante</th><th class="text-end">Importe</th><th>Estado</th><th class="text-center" style="width:70px">Abrir</th></tr></thead><tbody>';
        imps.forEach(function (i) {
            const anulada = i.imputacion_codigo && i.imputacion_codigo !== 'CONFIRMADO';
            html += '<tr' + (anulada ? ' class="text-muted text-decoration-line-through"' : '') + '>' +
                '<td>' + fecha(i.f_imputacion) + '</td>' +
                '<td>' + (i.rol === 'CANCELADO_POR' ? 'Cancelado por' : 'Cancela a') + '</td>' +
                '<td><strong>' + esc(numero(i)) + '</strong><div class="text-muted">' + fecha(i.otro_f_emision) + ' · ' + moneda(i.otro_importe_total) + '</div></td>' +
                '<td class="text-end fw-semibold">' + moneda(i.rol === 'CANCELADO_POR' ? i.importe_imputado : i.importe_origen) + '</td>' +
                '<td>' + esc(i.imputacion_estado || '') + '</td>' +
                '<td class="text-center"><button type="button" class="btn btn-xs btn-outline-primary cexp-ir-comp" data-comp-id="' + i.otro_comprobante_id + '" title="Abrir en el explorador"><i class="fas fa-folder-open"></i></button></td></tr>';
        });
        html += '</tbody></table></div>';
        return html;
    }

    function vistaAsientos(data, soloAsientoId) {
        let asientos = data.asientos || [];
        if (soloAsientoId) {
            asientos = asientos.filter(a => Number(a.cont_asiento_id) === Number(soloAsientoId));
        }
        let html = '<h6 class="fw-bold mb-3"><i class="fas fa-book text-primary me-2"></i>Asiento Contable Relacionado</h6>';
        if (!asientos.length) {
            return html + '<div class="text-muted small">Sin asiento contable asociado a este comprobante.</div>';
        }
        asientos.forEach(function (a) {
            const badgeCls = a.anulado ? 'bg-danger' : 'bg-success';
            html += '<div class="cexp-card">' +
                '<div class="d-flex justify-content-between align-items-center mb-2">' +
                '  <div class="small"><strong>Asiento #' + a.cont_asiento_id + '</strong> · ' + fecha(a.f_asiento) + ' · <span class="text-muted">' + esc(a.descripcion || '') + '</span></div>' +
                '  <span class="badge ' + badgeCls + '">' + esc(a.estado || '') + '</span>' +
                '</div>' +
                '<div class="table-responsive"><table class="table table-sm table-bordered align-middle small mb-0">' +
                '<thead class="table-light"><tr><th>Cuenta</th><th>Detalle</th><th class="text-end" style="width:125px">Debe</th><th class="text-end" style="width:125px">Haber</th></tr></thead><tbody>';
            (a.lineas || []).forEach(function (l) {
                html += '<tr' + (a.anulado ? ' class="text-muted text-decoration-line-through"' : '') + '>' +
                    '<td><span class="font-monospace text-primary">' + esc(l.codigo) + '</span> ' + esc(l.nombre) + '</td>' +
                    '<td>' + esc(l.descripcion || '') + '</td>' +
                    '<td class="text-end">' + (l.debe > 0 ? moneda(l.debe, '$') : '') + '</td>' +
                    '<td class="text-end">' + (l.haber > 0 ? moneda(l.haber, '$') : '') + '</td></tr>';
            });
            html += '</tbody><tfoot class="fw-bold table-light"><tr><td colspan="2" class="text-end">Totales</td>' +
                '<td class="text-end">' + moneda(a.total_debe, '$') + '</td>' +
                '<td class="text-end">' + moneda(a.total_haber, '$') + '</td></tr></tfoot></table></div></div>';
        });
        return html;
    }

    function vistaAdjuntos(data) {
        const c = data.comprobante;
        const adjuntos = data.adjuntos || [];
        let html = '<h6 class="fw-bold mb-3"><i class="fas fa-paperclip text-primary me-2"></i>Documentos Escaneados y Adjuntos</h6>';

        html += '<form id="formSubirAdjuntoComp" class="cexp-card bg-light" enctype="multipart/form-data">' +
            '<input type="hidden" name="accion" value="subir_adjunto">' +
            '<input type="hidden" name="comprobante_id" value="' + c.comprobante_id + '">' +
            '<div class="small fw-semibold mb-2"><i class="fas fa-cloud-upload-alt me-1 text-primary"></i>Asociar nuevo documento escaneado (PDF, JPG, PNG, WebP — máx. 10 MB)</div>' +
            '<div class="row g-2 align-items-center">' +
            '  <div class="col-md-5"><input type="file" name="archivo" id="adjCompArchivo" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp" required></div>' +
            '  <div class="col-md-4"><input type="text" name="descripcion" id="adjCompDesc" class="form-control form-control-sm" placeholder="Descripción (ej: Factura firmada, transf.)"></div>' +
            '  <div class="col-md-3 d-grid"><button type="submit" class="btn btn-sm btn-primary" id="btnSubirAdjuntoComp"><i class="fas fa-upload me-1"></i>Adjuntar</button></div>' +
            '</div>' +
            '<div id="adjCompMsg" class="small mt-2 d-none"></div>' +
            '</form>';

        if (!adjuntos.length) {
            html += '<div class="text-muted small">Todavía no hay documentos adjuntos. Subí uno arriba o seleccionalo desde el árbol.</div>';
        } else {
            html += '<div class="list-group">';
            adjuntos.forEach(function (adj, idx) {
                const verUrl = 'comprobantes_consulta_ajax.php?accion=ver_adjunto&origen=' + encodeURIComponent(adj.origen) + '&id=' + adj.id;
                const icono = (adj.mime || '').indexOf('pdf') !== -1 ? 'fa-file-pdf text-danger' : 'fa-file-image text-info';
                html += '<div class="list-group-item d-flex justify-content-between align-items-center gap-2 small">' +
                    '<div>' +
                    '  <i class="fas ' + icono + ' me-1"></i><strong>' + esc(adj.nombre_original) + '</strong>' +
                    '  <span class="badge bg-light text-dark border ms-1">' + esc(adj.origen_label) + '</span>' +
                    '  <span class="text-muted ms-1">(' + formatoBytes(adj.tamanio_bytes) + ')</span>' +
                    (adj.descripcion ? '<div class="text-muted">' + esc(adj.descripcion) + '</div>' : '') +
                    '  <div class="text-muted" style="font-size:11px;">' + esc(adj.usuario_nombre || '') + (adj.fecha_alta ? ' · ' + fechaHora(adj.fecha_alta) : '') + '</div>' +
                    '</div>' +
                    '<div class="btn-group btn-group-sm text-nowrap">' +
                    '  <button type="button" class="btn btn-outline-primary cexp-ver-adj" data-idx="' + idx + '" title="Previsualizar aquí"><i class="fas fa-eye me-1"></i>Ver</button>' +
                    '  <a href="' + verUrl + '" target="_blank" rel="noopener" class="btn btn-outline-secondary" title="Abrir en pestaña nueva"><i class="fas fa-external-link-alt"></i></a>' +
                    (adj.puede_eliminar ? '<button type="button" class="btn btn-outline-danger btn-del-adjunto" data-id="' + adj.id + '" title="Quitar adjunto"><i class="fas fa-trash"></i></button>' : '') +
                    '</div></div>';
            });
            html += '</div>';
        }
        return html;
    }

    function vistaPreviaAdjunto(data, idx) {
        const adj = (data.adjuntos || [])[idx];
        if (!adj) return vistaAdjuntos(data);
        const verUrl = 'comprobantes_consulta_ajax.php?accion=ver_adjunto&origen=' + encodeURIComponent(adj.origen) + '&id=' + adj.id;
        const esPdf = (adj.mime || '').indexOf('pdf') !== -1;
        let html = '<div class="d-flex justify-content-between align-items-start gap-2 mb-3">' +
            '<div>' +
            '  <h6 class="fw-bold mb-1"><i class="fas ' + (esPdf ? 'fa-file-pdf text-danger' : 'fa-file-image text-info') + ' me-2"></i>' + esc(adj.nombre_original) + '</h6>' +
            '  <div class="small text-muted">' + esc(adj.origen_label) + ' · ' + formatoBytes(adj.tamanio_bytes) +
            (adj.usuario_nombre ? ' · Subido por ' + esc(adj.usuario_nombre) : '') + (adj.fecha_alta ? ' el ' + fechaHora(adj.fecha_alta) : '') + '</div>' +
            (adj.descripcion ? '<div class="small mt-1">' + esc(adj.descripcion) + '</div>' : '') +
            '</div>' +
            '<div class="btn-group btn-group-sm text-nowrap">' +
            '  <a href="' + verUrl + '" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="fas fa-external-link-alt me-1"></i>Abrir pestaña</a>' +
            (adj.puede_eliminar ? '<button type="button" class="btn btn-outline-danger btn-del-adjunto" data-id="' + adj.id + '"><i class="fas fa-trash me-1"></i>Quitar</button>' : '') +
            '</div></div>';

        if (esPdf) {
            html += '<iframe class="cexp-preview-frame" src="' + verUrl + '"></iframe>';
        } else {
            html += '<div class="text-center p-2 bg-light rounded border"><img class="cexp-preview-img" src="' + verUrl + '" alt="' + esc(adj.nombre_original) + '"></div>';
        }
        return html;
    }

    function vistaComentarios(data) {
        const c = data.comprobante;
        const coms = data.comentarios || [];
        let html = '<h6 class="fw-bold mb-3"><i class="fas fa-comments text-primary me-2"></i>Comentarios del Comprobante</h6>';
        if (!coms.length) {
            html += '<div class="text-muted small mb-3">Sin comentarios todavía. Podés dejar una nota o consulta para otros usuarios.</div>';
        } else {
            html += '<div class="d-flex flex-column gap-2 mb-3">';
            coms.forEach(function (cm) {
                html += '<div class="cexp-card mb-0 py-2 px-3">' +
                    '<div class="d-flex align-items-start gap-2">' +
                    '  <div class="cexp-avatar">' + esc(iniciales(cm.usuario_nombre)) + '</div>' +
                    '  <div class="flex-grow-1">' +
                    '    <div class="d-flex justify-content-between align-items-center">' +
                    '      <div class="small"><strong>' + esc(cm.usuario_nombre) + '</strong> <span class="text-muted ms-1">' + fechaHora(cm.fecha_alta) + '</span></div>' +
                    (cm.es_propio ? '<button type="button" class="btn btn-link btn-sm text-danger p-0 btn-del-comentario" data-id="' + cm.comentario_id + '" title="Eliminar comentario"><i class="fas fa-trash-alt"></i></button>' : '') +
                    '    </div>' +
                    '    <div class="small mt-1" style="white-space:pre-wrap;">' + esc(cm.comentario) + '</div>' +
                    '  </div>' +
                    '</div></div>';
            });
            html += '</div>';
        }
        html += '<form id="formNuevoComentarioComp" class="cexp-card bg-light mb-0">' +
            '<input type="hidden" name="accion" value="agregar_comentario">' +
            '<input type="hidden" name="comprobante_id" value="' + c.comprobante_id + '">' +
            '<div class="mb-2"><textarea name="comentario" id="txtNuevoComentarioComp" rows="2" class="form-control form-control-sm" placeholder="Escribí un comentario o aclaración sobre este comprobante..." required></textarea></div>' +
            '<div class="d-flex justify-content-end"><button type="submit" class="btn btn-sm btn-primary" id="btnEnviarComentarioComp"><i class="fas fa-paper-plane me-1"></i>Comentar</button></div>' +
            '</form>';
        return html;
    }

    function vistaHistorial(data) {
        const hist = data.historial || [];
        let html = '<h6 class="fw-bold mb-3"><i class="fas fa-history text-primary me-2"></i>Historial y Auditoría de Usuarios</h6>';
        if (!hist.length) {
            return html + '<div class="text-muted small">Sin eventos registrados.</div>';
        }
        const colores = {
            ALTA: '#2563eb', APROBACION: '#16a34a', ASIENTO: '#7c3aed', IMPUTACION: '#0891b2',
            ADJUNTO_ALTA: '#0284c7', ADJUNTO_BAJA: '#dc2626', COMENTARIO: '#d97706', ANULACION: '#dc2626', MODIF: '#64748b'
        };
        html += '<div class="cexp-timeline">';
        hist.forEach(function (ev) {
            const col = colores[ev.tipo] || '#64748b';
            html += '<div class="cexp-tl-item">' +
                '<span class="cexp-tl-dot" style="background:' + col + ';"></span>' +
                '<div class="small"><strong>' + esc(ev.titulo) + '</strong>' +
                ' <span class="text-muted ms-1">· ' + fechaHora(ev.fecha) + '</span></div>' +
                (ev.detalle ? '<div class="small text-muted">' + esc(ev.detalle) + '</div>' : '') +
                '<div style="font-size:11px;" class="text-secondary"><i class="fas fa-user me-1"></i>' + esc(ev.usuario || 'Sistema') + '</div>' +
                '</div>';
        });
        html += '</div>';
        return html;
    }

    // ---------------------------------------------------------------------------------
    // Renderizado completo del Explorer (Árbol izquierdo + Panel derecho)
    // ---------------------------------------------------------------------------------
    function seleccionarNodo(nodo) {
        estadoExp.nodo = nodo;
        const data = estadoExp.data;
        if (!data) return;

        $('#detCompCuerpo .cexp-node, #detCompCuerpo .cexp-child').removeClass('active');
        $('#detCompCuerpo [data-nodo="' + nodo + '"]').addClass('active');

        let html = '';
        if (nodo === 'resumen') html = vistaResumen(data);
        else if (nodo === 'imputaciones') html = vistaImputaciones(data);
        else if (nodo === 'asientos') html = vistaAsientos(data, null);
        else if (nodo.indexOf('asiento:') === 0) html = vistaAsientos(data, nodo.split(':')[1]);
        else if (nodo === 'adjuntos') html = vistaAdjuntos(data);
        else if (nodo.indexOf('adjunto:') === 0) html = vistaPreviaAdjunto(data, parseInt(nodo.split(':')[1], 10));
        else if (nodo === 'comentarios') html = vistaComentarios(data);
        else if (nodo === 'historial') html = vistaHistorial(data);
        else html = vistaResumen(data);

        $('#cexpMain').html(html);
        enlazarEventosPanelDerecho();
    }

    function enlazarEventosPanelDerecho() {
        $('#formSubirAdjuntoComp').off('submit').on('submit', function (ev) {
            ev.preventDefault();
            const fd = new FormData(this);
            const $btn = $('#btnSubirAdjuntoComp');
            const $msg = $('#adjCompMsg');
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Subiendo...');
            $msg.addClass('d-none');
            $.ajax({
                url: 'comprobantes_consulta_ajax.php', type: 'POST', data: fd,
                processData: false, contentType: false, dataType: 'json'
            }).done(function (res) {
                if (res && res.success) {
                    mostrarDetalle(estadoExp.url, estadoExp.params, 'adjuntos');
                } else {
                    $msg.removeClass('d-none').addClass('text-danger').text((res && res.message) || 'No se pudo subir el archivo.');
                    $btn.prop('disabled', false).html('<i class="fas fa-upload me-1"></i>Adjuntar');
                }
            }).fail(function (xhr) {
                $msg.removeClass('d-none').addClass('text-danger').text((xhr.responseJSON && xhr.responseJSON.message) || 'Error al subir el archivo.');
                $btn.prop('disabled', false).html('<i class="fas fa-upload me-1"></i>Adjuntar');
            });
        });

        $('#cexpMain').off('click', '.btn-del-adjunto').on('click', '.btn-del-adjunto', function () {
            const aid = $(this).data('id');
            if (!confirm('¿Quitar este documento adjunto del comprobante?')) return;
            $.post('comprobantes_consulta_ajax.php', { accion: 'eliminar_adjunto', adjunto_id: aid }, function (res) {
                if (res && res.success) mostrarDetalle(estadoExp.url, estadoExp.params, 'adjuntos');
                else alert((res && res.message) || 'No se pudo quitar el adjunto.');
            }, 'json');
        });

        $('#cexpMain').off('click', '.cexp-ver-adj').on('click', '.cexp-ver-adj', function () {
            seleccionarNodo('adjunto:' + $(this).data('idx'));
        });

        $('#cexpMain').off('click', '.cexp-ir-comp').on('click', '.cexp-ir-comp', function () {
            const otroId = parseInt($(this).data('comp-id'), 10);
            if (!otroId) return;
            pilaNavegacion.push({ url: estadoExp.url, params: $.extend({}, estadoExp.params), nodo: estadoExp.nodo });
            mostrarDetalle('comprobantes_consulta_ajax.php', { accion: 'obtener', comprobante_id: otroId }, 'resumen');
        });

        $('#formNuevoComentarioComp').off('submit').on('submit', function (ev) {
            ev.preventDefault();
            const $btn = $('#btnEnviarComentarioComp');
            $btn.prop('disabled', true);
            $.post('comprobantes_consulta_ajax.php', $(this).serialize(), function (res) {
                if (res && res.success) mostrarDetalle(estadoExp.url, estadoExp.params, 'comentarios');
                else { alert((res && res.message) || 'No se pudo guardar el comentario.'); $btn.prop('disabled', false); }
            }, 'json').fail(function () { alert('Error al guardar el comentario.'); $btn.prop('disabled', false); });
        });

        $('#cexpMain').off('click', '.btn-del-comentario').on('click', '.btn-del-comentario', function () {
            const cid = $(this).data('id');
            if (!confirm('¿Eliminar tu comentario?')) return;
            $.post('comprobantes_consulta_ajax.php', { accion: 'eliminar_comentario', comentario_id: cid }, function (res) {
                if (res && res.success) mostrarDetalle(estadoExp.url, estadoExp.params, 'comentarios');
                else alert((res && res.message) || 'No se pudo eliminar.');
            }, 'json');
        });
    }

    function renderDetalle(data, nodoInicial) {
        estadoExp.data = data;
        const c = data.comprobante;
        const sim = c.moneda_simbolo || '$';
        const imps = data.imputaciones || [];
        const asientos = data.asientos || [];
        const adjuntos = data.adjuntos || [];
        const coms = data.comentarios || [];
        const hist = data.historial || [];

        let side = '<div class="cexp-root">' +
            '<div class="fw-bold text-dark small">' + esc(numero(c)) + '</div>' +
            '<div class="small text-muted text-truncate">' + esc(c.entidad_nombre || c.comprobante_tipo || '') + '</div>' +
            '<div class="d-flex justify-content-between align-items-center mt-2">' +
            '  ' + badgeEstado(c) +
            '  <span class="fw-bold small text-dark">' + moneda(c.importe_total, sim) + '</span>' +
            '</div></div>';

        // Nodo: Resumen
        side += '<div class="cexp-node" data-nodo="resumen"><span><i class="fas fa-file-alt me-2 text-primary"></i>Resumen</span></div>';

        // Nodo: Imputaciones + hijos navegables
        side += '<div class="cexp-node" data-nodo="imputaciones"><span><i class="fas fa-link me-2 text-info"></i>Imputaciones</span>' +
            (imps.length ? '<span class="cexp-pill">' + imps.length + '</span>' : '') + '</div>';
        if (imps.length) {
            side += '<div class="cexp-children">';
            imps.forEach(function (i) {
                side += '<div class="cexp-child cexp-tree-comp" data-comp-id="' + i.otro_comprobante_id + '" title="Abrir ' + esc(numero(i)) + '">' +
                    '<i class="fas fa-file-invoice text-secondary"></i><span>' + esc(numero(i)) + '</span></div>';
            });
            side += '</div>';
        }

        // Nodo: Asientos + hijos
        side += '<div class="cexp-node" data-nodo="asientos"><span><i class="fas fa-book me-2 text-purple" style="color:#7c3aed"></i>Asiento Contable</span>' +
            (asientos.length ? '<span class="cexp-pill">' + asientos.length + '</span>' : '') + '</div>';
        if (asientos.length) {
            side += '<div class="cexp-children">';
            asientos.forEach(function (a) {
                side += '<div class="cexp-child" data-nodo="asiento:' + a.cont_asiento_id + '">' +
                    '<i class="fas fa-balance-scale ' + (a.anulado ? 'text-danger' : 'text-success') + '"></i>' +
                    '<span>#' + a.cont_asiento_id + ' · ' + fecha(a.f_asiento) + '</span></div>';
            });
            side += '</div>';
        }

        // Nodo: Adjuntos + hijos previsualizables
        side += '<div class="cexp-node" data-nodo="adjuntos"><span><i class="fas fa-paperclip me-2 text-warning"></i>Adjuntos</span>' +
            (adjuntos.length ? '<span class="cexp-pill">' + adjuntos.length + '</span>' : '') + '</div>';
        if (adjuntos.length) {
            side += '<div class="cexp-children">';
            adjuntos.forEach(function (adj, idx) {
                const ic = (adj.mime || '').indexOf('pdf') !== -1 ? 'fa-file-pdf text-danger' : 'fa-file-image text-info';
                side += '<div class="cexp-child" data-nodo="adjunto:' + idx + '" title="' + esc(adj.nombre_original) + '">' +
                    '<i class="fas ' + ic + '"></i><span class="text-truncate">' + esc(adj.nombre_original) + '</span></div>';
            });
            side += '</div>';
        }

        // Nodo: Comentarios
        side += '<div class="cexp-node" data-nodo="comentarios"><span><i class="fas fa-comments me-2 text-success"></i>Comentarios</span>' +
            (coms.length ? '<span class="cexp-pill">' + coms.length + '</span>' : '') + '</div>';

        // Nodo: Historial
        side += '<div class="cexp-node" data-nodo="historial"><span><i class="fas fa-history me-2 text-secondary"></i>Historial / Log</span>' +
            (hist.length ? '<span class="cexp-pill">' + hist.length + '</span>' : '') + '</div>';

        $('#detCompTitulo').text('Expediente · ' + numero(c));
        $('#detCompCuerpo').html(
            '<div class="cexp-wrap">' +
            '  <div class="cexp-side">' + side + '</div>' +
            '  <div class="cexp-main" id="cexpMain"></div>' +
            '</div>'
        );

        $('#detCompVolver').toggleClass('d-none', pilaNavegacion.length === 0).off('click').on('click', function () {
            const prev = pilaNavegacion.pop();
            if (prev) mostrarDetalle(prev.url, prev.params, prev.nodo);
        });

        $('#detCompCuerpo').off('click', '[data-nodo]').on('click', '[data-nodo]', function () {
            seleccionarNodo($(this).attr('data-nodo'));
        });
        $('#detCompCuerpo').off('click', '.cexp-tree-comp').on('click', '.cexp-tree-comp', function () {
            const otroId = parseInt($(this).data('comp-id'), 10);
            if (!otroId) return;
            pilaNavegacion.push({ url: estadoExp.url, params: $.extend({}, estadoExp.params), nodo: estadoExp.nodo });
            mostrarDetalle('comprobantes_consulta_ajax.php', { accion: 'obtener', comprobante_id: otroId }, 'resumen');
        });

        const $ir = $('#detCompIrOrigen');
        if (c.origen_url && c.origen_pagina_id) {
            $ir.attr('href', c.origen_url + '?pagina_id=' + c.origen_pagina_id + '&modulo_id=5')
               .html('<i class="fas fa-external-link-alt me-1"></i>Ir a ' + esc(c.origen_pagina))
               .removeClass('d-none');
        } else {
            $ir.addClass('d-none');
        }

        seleccionarNodo(nodoInicial || 'resumen');
    }

    function mostrarDetalle(url, params, nodoInicial) {
        estadoExp.url = url;
        estadoExp.params = params;
        asegurarModal();
        $('#detCompTitulo').text('Expediente del Comprobante');
        $('#detCompCuerpo').html('<div class="text-center py-5"><div class="spinner-border spinner-border-sm text-primary"></div> Cargando expediente...</div>');
        $('#detCompIrOrigen').addClass('d-none');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalleComprobante')).show();

        $.getJSON(url, params).done(function (res) {
            if (res && res.success) {
                renderDetalle(res.data, nodoInicial || 'resumen');
            } else {
                $('#detCompCuerpo').html('<div class="p-3"><div class="alert alert-danger small mb-0">' + esc((res && res.message) || 'No se pudo obtener el comprobante.') + '</div></div>');
            }
        }).fail(function (xhr) {
            const msg = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Error de conexión con el servidor.';
            $('#detCompCuerpo').html('<div class="p-3"><div class="alert alert-danger small mb-0">' + esc(msg) + '</div></div>');
        });
    }

    function hoyMenosDias(dias) {
        const d = new Date();
        d.setDate(d.getDate() - dias);
        return Fmt.isoLocal(d);
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
