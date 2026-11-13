/**
 * ctacte_cliente.js
 *
 * Widget de cuenta corriente de un cliente: resumen (saldo, deuda, vencido, a favor, último
 * cobro) + solapas Deuda / Cuenta corriente / Comprobantes. Lee clientes_ctacte_ajax.php.
 * Requiere comprobantes_ui.js.
 *
 * Uso:
 *   const w = CtacteCliente.crear('#contenedor', { empresaId: 2, mostrarCabecera: true });
 *   enSolapa: true (solapa de clientes.php) = sin botones Excel/PDF y con el buscador en la
 *   fila de filtros, después de "Filtrar".
 *   exportar: barra Excel / PDF / Enviar por email en cada solapa (por defecto, fuera de la
 *   solapa). El envío necesita csrfToken (csrf_token() de la página).
 *   w.cargar(entidadId);
 *   w.limpiar();
 */
(function (window, $) {
    'use strict';

    const URL_AJAX = 'clientes_ctacte_ajax.php';
    const UI = window.ComprobantesUI;
    let contador = 0;

    function crear(selector, opciones) {
        const opts = $.extend({ empresaId: 0, mostrarCabecera: false, enSolapa: false, csrfToken: '' }, opciones || {});
        if (opts.exportar === undefined) opts.exportar = !opts.enSolapa;
        const p = 'ctacte' + (++contador);
        const $root = $(selector);
        let entidadId = 0;
        let cargadas = {};
        let tablas = {};
        let cliente = {};

        $root.html(
            '<div class="ctacte-widget">' +
            (opts.mostrarCabecera
                ? '<div class="d-flex justify-content-between align-items-start mb-3" id="' + p + 'Cab">' +
                  '  <div><h5 class="mb-0 fw-bold" id="' + p + 'Nombre">Seleccione un cliente</h5>' +
                  '  <small class="text-muted" id="' + p + 'Datos"></small></div>' +
                  '  <button type="button" class="btn btn-sm btn-outline-secondary" id="' + p + 'Recargar" title="Recargar"><i class="fas fa-sync-alt"></i></button>' +
                  '</div>'
                : '') +
            '  <div class="alert alert-warning small py-2 d-none" id="' + p + 'Aviso"></div>' +
            '  <div class="row g-2 mb-3">' +
                tarjeta(p + 'Saldo', 'Saldo cuenta corriente', 'fa-balance-scale', 'primary') +
                tarjeta(p + 'Deuda', 'Deuda pendiente', 'fa-file-invoice-dollar', 'warning') +
                tarjeta(p + 'Vencido', 'Vencido', 'fa-exclamation-triangle', 'danger') +
                tarjeta(p + 'Favor', 'Saldo a favor sin aplicar', 'fa-hand-holding-usd', 'success') +
                tarjeta(p + 'Cobro', 'Último cobro', 'fa-receipt', 'secondary') +
            '  </div>' +
            '  <ul class="nav nav-pills nav-sm mb-2" role="tablist">' +
                pestana(p, 'Deuda', '<i class="fas fa-exclamation-circle me-1"></i>Deuda <span class="badge bg-secondary ms-1" id="' + p + 'CantDeuda">0</span>', true) +
                pestana(p, 'Cta', '<i class="fas fa-list-ol me-1"></i>Cuenta corriente', false) +
                pestana(p, 'Comp', '<i class="fas fa-folder-open me-1"></i>Comprobantes emitidos', false) +
            '  </ul>' +
            '  <div class="tab-content border rounded p-2">' +
            '    <div class="tab-pane fade show active" id="' + p + 'PaneDeuda" role="tabpanel">' +
            '      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">' +
            '        <h6 class="small fw-bold text-uppercase text-warning mb-0">Comprobantes adeudados</h6>' +
                     barraExportar('deuda') +
            '      </div>' +
            '      <table class="table table-sm table-striped table-bordered w-100 small" id="' + p + 'TablaDeuda">' +
            '        <thead class="table-light"><tr><th>Emisión</th><th>Vencimiento</th><th>Comprobante</th><th>Sucursal</th>' +
            '        <th class="text-end">Total</th><th class="text-end">Pendiente</th><th class="text-center">Atraso</th><th class="no-export"></th></tr></thead>' +
            '        <tbody></tbody><tfoot><tr class="fw-bold"><td colspan="5" class="text-end">Total adeudado</td><td class="text-end" id="' + p + 'TotDeuda"></td><td colspan="2"></td></tr></tfoot>' +
            '      </table>' +
            '      <div id="' + p + 'BloqueFavor" class="d-none mt-3">' +
            '        <h6 class="small fw-bold text-uppercase text-success mb-2">Saldo a favor (recibos / notas de crédito sin aplicar)</h6>' +
            '        <table class="table table-sm table-striped table-bordered w-100 small" id="' + p + 'TablaFavor">' +
            '          <thead class="table-light"><tr><th>Emisión</th><th>Comprobante</th><th class="text-end">Total</th><th class="text-end">Sin aplicar</th><th class="no-export"></th></tr></thead><tbody></tbody>' +
            '        </table>' +
            '      </div>' +
            '    </div>' +
            '    <div class="tab-pane fade" id="' + p + 'PaneCta" role="tabpanel">' +
                   filtrosFechas(p + 'Cta', 'cta') +
            '      <div class="small mb-2" id="' + p + 'SaldoAnt"></div>' +
            '      <table class="table table-sm table-striped table-bordered w-100 small" id="' + p + 'TablaCta">' +
            '        <thead class="table-light"><tr><th>Fecha</th><th>Vencimiento</th><th>Comprobante</th><th>Sucursal</th>' +
            '        <th class="text-end">Debe</th><th class="text-end">Haber</th><th class="text-end">Saldo</th><th class="no-export"></th></tr></thead>' +
            '        <tbody></tbody><tfoot><tr class="fw-bold"><td colspan="4" class="text-end">Totales del período</td>' +
            '        <td class="text-end" id="' + p + 'TotDebe"></td><td class="text-end" id="' + p + 'TotHaber"></td><td class="text-end" id="' + p + 'TotSaldo"></td><td></td></tr></tfoot>' +
            '      </table>' +
            '    </div>' +
            '    <div class="tab-pane fade" id="' + p + 'PaneComp" role="tabpanel">' +
                   filtrosFechas(p + 'Comp', 'comp') +
            '      <table class="table table-sm table-striped table-bordered w-100 small" id="' + p + 'TablaComp">' +
            '        <thead class="table-light"><tr><th>Emisión</th><th>Comprobante</th><th>Grupo</th><th>Sucursal</th>' +
            '        <th class="text-end">Total</th><th class="text-end">Saldo</th><th class="text-center">Estado</th><th class="no-export"></th></tr></thead><tbody></tbody>' +
            '      </table>' +
            '    </div>' +
            '  </div>' +
            '</div>'
        );

        $('#' + p + 'CtaDesde, #' + p + 'CompDesde').val(UI.hoyMenosDias(365));

        // -------------------------------------------------------------------------
        function tarjeta(id, titulo, icono, color) {
            return '<div class="col">' +
                '<div class="border rounded p-2 h-100 border-start border-4 border-' + color + '">' +
                '<div class="small text-muted text-uppercase"><i class="fas ' + icono + ' me-1 text-' + color + '"></i>' + titulo + '</div>' +
                '<div class="fs-5 fw-bold" id="' + id + '">-</div>' +
                '<div class="small text-muted" id="' + id + 'Sub"></div>' +
                '</div></div>';
        }

        function pestana(pref, clave, texto, activa) {
            return '<li class="nav-item" role="presentation">' +
                '<button class="nav-link py-1 px-3' + (activa ? ' active' : '') + '" type="button" data-bs-toggle="pill"' +
                ' data-bs-target="#' + pref + 'Pane' + clave + '" data-ctacte-tab="' + clave + '" role="tab">' + texto + '</button></li>';
        }

        // Contenedor de Excel / PDF / Enviar por email de una solapa (los botones se agregan al crear su tabla)
        function barraExportar(clave) {
            return opts.exportar ? '<div class="d-flex flex-wrap gap-1 ctacte-exportar" id="' + p + 'Exp_' + clave + '"></div>' : '';
        }

        function filtrosFechas(pref, clave) {
            return '<div class="row g-2 align-items-end mb-2">' +
                '<div class="col-auto"><label class="form-label small mb-0">Desde</label>' +
                '<input type="date" class="form-control form-control-sm" id="' + pref + 'Desde"></div>' +
                '<div class="col-auto"><label class="form-label small mb-0">Hasta</label>' +
                '<input type="date" class="form-control form-control-sm" id="' + pref + 'Hasta"></div>' +
                '<div class="col-auto"><button type="button" class="btn btn-sm btn-primary" id="' + pref + 'Filtrar"><i class="fas fa-filter me-1"></i>Filtrar</button></div>' +
                (opts.enSolapa
                    ? '<div class="col-auto"><label class="form-label small mb-0" for="' + pref + 'Buscar">Buscar:</label>' +
                      '<input type="search" class="form-control form-control-sm" id="' + pref + 'Buscar" data-ctacte-buscar="' + pref + '"></div>'
                    : '') +
                (opts.exportar ? '<div class="col-auto ms-auto">' + barraExportar(clave) + '</div>' : '') +
                '</div>';
        }

        function params(extra) {
            const base = { entidad_id: entidadId };
            if (opts.empresaId) base.empresa_idx = opts.empresaId;
            return $.extend(base, extra || {});
        }

        function error(xhr) {
            const msg = (xhr && xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Error de conexión con el servidor.';
            $('#' + p + 'Aviso').removeClass('d-none alert-warning').addClass('alert-danger').text(msg);
        }

        function aviso(msg) {
            const $a = $('#' + p + 'Aviso').removeClass('alert-danger').addClass('alert-warning');
            if (msg) $a.text(msg).removeClass('d-none'); else $a.addClass('d-none').text('');
        }

        function botonVer(id) {
            return '<button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1 ctacte-ver" data-id="' + id + '" title="Ver detalle"><i class="fas fa-eye"></i></button>';
        }

        // En la solapa: sin botones (B); Cuenta corriente y Comprobantes tampoco llevan el buscador
        // de DataTables (f) porque usan el de la fila de filtros.
        const PIE_TABLA = '<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>';
        const DOM_SOLAPA = {
            deuda: '<"row mb-2"<"col-12 text-end"f>><"row"<"col-12"tr>>' + PIE_TABLA,
            cta: '<"row"<"col-12"tr>>' + PIE_TABLA,
            comp: '<"row"<"col-12"tr>>' + PIE_TABLA
        };

        function dibujarTabla(clave, $tabla, filas, columnas, extra) {
            if (tablas[clave]) {
                tablas[clave].clear().rows.add(filas).draw();
                return;
            }
            const conf = $.extend({ data: filas, columns: columnas }, extra || {});
            if (opts.enSolapa) {
                if (DOM_SOLAPA[clave] && !(extra && extra.dom)) conf.dom = DOM_SOLAPA[clave];
            }
            tablas[clave] = $tabla.DataTable(UI.opcionesTabla(conf));
            agregarBotonesExportar(clave, tablas[clave]);
            if (opts.enSolapa) {
                // Lo escrito antes de la primera carga también filtra
                const $buscar = $('#' + p + (clave === 'cta' ? 'Cta' : 'Comp') + 'Buscar');
                if ((clave === 'cta' || clave === 'comp') && $buscar.val()) tablas[clave].search($buscar.val()).draw();
            }
        }

        // Exportar / enviar ---------------------------------------------------------
        const NOMBRES_SOLAPA = { deuda: 'Deuda', cta: 'Cuenta corriente', comp: 'Comprobantes emitidos' };

        function periodoTexto(clave) {
            if (clave === 'deuda') return '';
            const pref = p + (clave === 'cta' ? 'Cta' : 'Comp');
            const desde = $('#' + pref + 'Desde').val(), hasta = $('#' + pref + 'Hasta').val();
            if (!desde && !hasta) return '';
            return 'Período' + (desde ? ' desde ' + Fmt.fecha(desde) : '') + (hasta ? ' hasta ' + Fmt.fecha(hasta) : '');
        }

        function encabezadoExport(clave) {
            const partes = [cliente.entidad_nombre || '', cliente.cuit ? 'CUIT ' + cliente.cuit : '', periodoTexto(clave)];
            if (clave === 'cta' && $('#' + p + 'CtaDesde').val()) partes.push($('#' + p + 'SaldoAnt').text());
            return partes.filter(Boolean).join(' · ');
        }

        function totalesExport(clave) {
            if (clave === 'deuda') return 'Total adeudado: ' + $('#' + p + 'TotDeuda').text();
            if (clave === 'cta') {
                return 'Totales del período: Debe ' + $('#' + p + 'TotDebe').text() + ' · Haber ' + $('#' + p + 'TotHaber').text() +
                    ' · Saldo ' + $('#' + p + 'TotSaldo').text();
            }
            return '';
        }

        function archivoExport(clave) {
            const base = 'ctacte_' + NOMBRES_SOLAPA[clave] + '_' + (cliente.entidad_nombre || entidadId) + '_' + Fmt.hoyISO();
            return base.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^\w\-]+/g, '_');
        }

        function agregarBotonesExportar(clave, tabla) {
            if (!opts.exportar || !NOMBRES_SOLAPA[clave] || !$.fn.dataTable.Buttons) return;
            const titulo = function () { return NOMBRES_SOLAPA[clave] + (cliente.entidad_nombre ? ' - ' + cliente.entidad_nombre : ''); };
            const comunes = {
                title: titulo,
                messageTop: function () { return encabezadoExport(clave); },
                messageBottom: function () { return totalesExport(clave); },
                filename: function () { return archivoExport(clave); }
            };
            const botones = new $.fn.dataTable.Buttons(tabla, {
                buttons: [
                    // Excel con números de verdad (orthogonal 'export'); PDF con lo que se ve en pantalla
                    $.extend({ extend: 'excelHtml5', text: '<i class="fas fa-file-excel me-1"></i>Excel', className: 'btn btn-sm btn-outline-success',
                               exportOptions: { columns: ':not(.no-export)', orthogonal: 'export' } }, comunes),
                    $.extend({ extend: 'pdfHtml5', text: '<i class="fas fa-file-pdf me-1"></i>PDF', className: 'btn btn-sm btn-outline-danger',
                               orientation: 'landscape', pageSize: 'A4', exportOptions: { columns: ':not(.no-export)' } }, comunes),
                    { text: '<i class="fas fa-envelope me-1"></i>Enviar por email', className: 'btn btn-sm btn-outline-primary',
                      action: function () { enviarPorEmail(clave); } }
                ]
            });
            botones.container().removeClass('btn-group').addClass('d-flex flex-wrap gap-1').appendTo('#' + p + 'Exp_' + clave);
        }

        function enviarPorEmail(clave) {
            if (!entidadId) return;
            $.getJSON(URL_AJAX, params({ accion: 'emails_cliente' })).always(function (res) {
                const emails = (res && res.success && res.data) || [];
                const opciones = emails.map(function (e) { return '<option value="' + UI.esc(e) + '">'; }).join('');
                Swal.fire({
                    title: 'Enviar ' + NOMBRES_SOLAPA[clave].toLowerCase() + ' por email',
                    html: '<div class="text-start small">' +
                        '<div class="mb-2 text-muted">' + UI.esc(encabezadoExport(clave)) + '</div>' +
                        '<label class="form-label mb-0 ui-requerido" for="ctacteEmailPara">Para</label>' +
                        '<input type="email" class="form-control form-control-sm mb-2" id="ctacteEmailPara" list="ctacteEmailLista" value="' + UI.esc(emails[0] || '') + '" placeholder="correo@cliente.com">' +
                        '<datalist id="ctacteEmailLista">' + opciones + '</datalist>' +
                        (emails.length ? '' : '<div class="text-warning mb-2">El cliente no tiene correo cargado en sus sucursales.</div>') +
                        '<label class="form-label mb-0" for="ctacteEmailMensaje">Mensaje (opcional)</label>' +
                        '<textarea class="form-control form-control-sm" id="ctacteEmailMensaje" rows="3" maxlength="2000"></textarea>' +
                        '</div>',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-paper-plane me-1"></i>Enviar',
                    cancelButtonText: 'Cancelar',
                    focusConfirm: false,
                    showLoaderOnConfirm: true,
                    allowOutsideClick: function () { return !Swal.isLoading(); },
                    preConfirm: function () {
                        const para = $('#ctacteEmailPara').val().trim();
                        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(para)) {
                            Swal.showValidationMessage('Ingresá un correo válido.');
                            return false;
                        }
                        const pref = p + (clave === 'cta' ? 'Cta' : 'Comp');
                        return $.ajax({
                            url: URL_AJAX, type: 'POST', dataType: 'json',
                            headers: { 'X-CSRF-Token': opts.csrfToken },
                            data: params({
                                accion: 'enviar_email', solapa: clave, para: para, mensaje: $('#ctacteEmailMensaje').val(),
                                desde: clave === 'deuda' ? '' : $('#' + pref + 'Desde').val(),
                                hasta: clave === 'deuda' ? '' : $('#' + pref + 'Hasta').val()
                            })
                        }).then(function (r) {
                            if (!r.success) Swal.showValidationMessage(r.message || 'No se pudo enviar el correo.');
                            return r.success ? r : false;
                        }, function (xhr) {
                            Swal.showValidationMessage((xhr.responseJSON && xhr.responseJSON.message) || 'Error de conexión con el servidor.');
                            return false;
                        });
                    }
                }).then(function (result) {
                    if (result.isConfirmed && result.value) {
                        Swal.fire({ icon: 'success', title: 'Enviado', text: result.value.message, timer: 2500, showConfirmButton: false });
                    }
                });
            });
        }

        // -------------------------------------------------------------------------
        function cargarResumen() {
            $.getJSON(URL_AJAX, params({ accion: 'resumen' })).done(function (res) {
                if (!res.success) { aviso(res.message); return; }
                aviso(res.message);
                const r = res.data;
                const saldo = parseFloat(r.saldo);
                $('#' + p + 'Saldo').text(UI.moneda(Math.abs(saldo)))
                    .toggleClass('text-danger', saldo > 0.009).toggleClass('text-success', saldo < -0.009);
                $('#' + p + 'SaldoSub').text(saldo > 0.009 ? 'Deudor' : (saldo < -0.009 ? 'Acreedor (a favor del cliente)' : 'Sin saldo'));
                $('#' + p + 'Deuda').text(UI.moneda(r.deuda));
                $('#' + p + 'DeudaSub').text(r.comprobantes_pendientes + ' comprobante(s)' +
                    (r.primer_vencimiento ? ' · desde ' + UI.fecha(r.primer_vencimiento) : ''));
                $('#' + p + 'Vencido').text(UI.moneda(r.vencido)).toggleClass('text-danger', r.vencido > 0.009);
                $('#' + p + 'VencidoSub').text(r.comprobantes_vencidos + ' comprobante(s)');
                $('#' + p + 'Favor').text(UI.moneda(r.a_favor));
                $('#' + p + 'FavorSub').text('');
                $('#' + p + 'Cobro').text(r.ultimo_cobro ? UI.fecha(r.ultimo_cobro) : 'Sin cobros');
                $('#' + p + 'CobroSub').text(r.ultimo_cobro ? UI.moneda(r.ultimo_cobro_importe) : '');
                $('#' + p + 'CantDeuda').text(r.comprobantes_pendientes)
                    .toggleClass('bg-danger', r.comprobantes_vencidos > 0).toggleClass('bg-secondary', r.comprobantes_vencidos === 0);
            }).fail(error);
        }

        function cargarDeuda() {
            $.getJSON(URL_AJAX, params({ accion: 'pendientes' })).done(function (res) {
                if (!res.success) { aviso(res.message); return; }
                const d = res.data;
                let total = 0;
                d.deuda.forEach(function (r) { total += r.pendiente_local; });
                $('#' + p + 'TotDeuda').text(UI.moneda(total));

                dibujarTabla('deuda', $('#' + p + 'TablaDeuda'), d.deuda, [
                    { data: 'f_emision', render: function (v, t) { return t === 'display' || t === 'export' ? UI.fecha(v) : v; } },
                    { data: 'f_vto', render: function (v, t, r) { const f = v || r.f_emision; return t === 'display' || t === 'export' ? UI.fecha(f) : f; } },
                    { data: null, render: function (r) { return UI.esc(UI.numero(r)); } },
                    { data: 'sucursal_nombre', defaultContent: '' },
                    { data: 'importe_total', className: 'text-end', render: function (v, t, r) { return t === 'display' ? UI.moneda(v, r.moneda_simbolo) : v; } },
                    { data: 'pendiente', className: 'text-end fw-semibold', render: function (v, t, r) { return t === 'display' ? UI.moneda(v, r.moneda_simbolo) : v; } },
                    { data: 'dias_atraso', className: 'text-center', render: function (v, t) {
                        if (t !== 'display') return v;
                        return v > 0 ? '<span class="badge ' + (v > 30 ? 'bg-danger' : 'bg-warning text-dark') + '">' + v + ' días</span>' : '<span class="text-muted">al día</span>';
                    } },
                    { data: 'comprobante_id', orderable: false, className: 'text-center no-export', render: botonVer }
                ], { order: [[1, 'asc']], pageLength: 10 });

                $('#' + p + 'BloqueFavor').toggleClass('d-none', d.a_favor.length === 0);
                dibujarTabla('favor', $('#' + p + 'TablaFavor'), d.a_favor, [
                    { data: 'f_emision', render: function (v, t) { return t === 'display' ? UI.fecha(v) : v; } },
                    { data: null, render: function (r) { return UI.esc(UI.numero(r)); } },
                    { data: 'importe_total', className: 'text-end', render: function (v, t, r) { return t === 'display' ? UI.moneda(v, r.moneda_simbolo) : v; } },
                    { data: 'pendiente', className: 'text-end fw-semibold', render: function (v, t, r) { return t === 'display' ? UI.moneda(v, r.moneda_simbolo) : v; } },
                    { data: 'comprobante_id', orderable: false, className: 'text-center no-export', render: botonVer }
                ], { order: [[0, 'asc']], pageLength: 5, dom: 'rtip' });
            }).fail(error);
        }

        function cargarCuenta() {
            $.getJSON(URL_AJAX, params({ accion: 'movimientos', desde: $('#' + p + 'CtaDesde').val(), hasta: $('#' + p + 'CtaHasta').val() }))
                .done(function (res) {
                    if (!res.success) { aviso(res.message); return; }
                    const d = res.data;
                    $('#' + p + 'SaldoAnt').html($('#' + p + 'CtaDesde').val()
                        ? 'Saldo anterior al ' + UI.fecha($('#' + p + 'CtaDesde').val()) + ': <strong>' + UI.moneda(d.saldo_anterior) + '</strong>'
                        : '');
                    $('#' + p + 'TotDebe').text(UI.moneda(d.total_debe));
                    $('#' + p + 'TotHaber').text(UI.moneda(d.total_haber));
                    $('#' + p + 'TotSaldo').text(UI.moneda(d.saldo_final));
                    // El saldo es acumulado: el orden cronológico del servidor es el único válido
                    dibujarTabla('cta', $('#' + p + 'TablaCta'), d.movimientos, [
                        { data: 'f_emision', render: function (v) { return UI.fecha(v); } },
                        { data: 'f_vto', render: function (v) { return v ? UI.fecha(v) : ''; } },
                        { data: null, render: function (r) {
                            return UI.esc(UI.numero(r)) + (r.tipo_cambio !== 1 ?' <span class="badge bg-light text-dark border">' + UI.esc(r.moneda_simbolo) + ' TC ' + r.tipo_cambio + '</span>' : '');
                        } },
                        { data: 'sucursal_nombre', defaultContent: '' },
                        { data: 'debe', className: 'text-end', render: function (v, t) { return t === 'export' ? (v || '') : (v ? UI.moneda(v) : ''); } },
                        { data: 'haber', className: 'text-end', render: function (v, t) { return t === 'export' ? (v || '') : (v ? UI.moneda(v) : ''); } },
                        { data: 'saldo', className: 'text-end fw-semibold', render: function (v, t) {
                            if (t === 'export') return v;
                            return '<span class="' + (v > 0.009 ? 'text-danger' : (v < -0.009 ? 'text-success' : '')) + '">' + UI.moneda(v) + '</span>';
                        } },
                        { data: 'comprobante_id', className: 'text-center no-export', render: botonVer }
                    ], { ordering: false, pageLength: 50 });
                }).fail(error);
        }

        function cargarComprobantes() {
            $.getJSON(URL_AJAX, params({ accion: 'comprobantes', desde: $('#' + p + 'CompDesde').val(), hasta: $('#' + p + 'CompHasta').val() }))
                .done(function (res) {
                    if (!res.success) { aviso(res.message); return; }
                    if (res.message) aviso(res.message);
                    dibujarTabla('comp', $('#' + p + 'TablaComp'), res.data, [
                        { data: 'f_emision', render: function (v, t) { return t === 'display' || t === 'export' ? UI.fecha(v) : v; } },
                        { data: null, render: function (r, t) {
                            if (t === 'export') return UI.numero(r) + ' - ' + (r.comprobante_tipo || '');
                            // El espacio antes del div separa número y tipo en el PDF (que exporta el texto de pantalla)
                            return UI.esc(UI.numero(r)) + ' <div class="text-muted">' + UI.esc(r.comprobante_tipo) + '</div>';
                        } },
                        { data: 'comprobante_grupo', defaultContent: '' },
                        { data: 'sucursal_nombre', defaultContent: '' },
                        { data: 'importe_total', className: 'text-end', render: function (v, t, r) { return t === 'display' ? UI.moneda(v, r.moneda_simbolo) : v; } },
                        { data: 'saldo', className: 'text-end', render: function (v, t, r) {
                            if (v === null || v === undefined) return t === 'display' ? '<span class="text-muted">-</span>' : '';
                            return t === 'display' ? UI.moneda(v, r.moneda_simbolo) : v;
                        } },
                        { data: 'estado', className: 'text-center', render: function (v, t, r) { return t === 'display' ? UI.badgeEstado(r) : v; } },
                        { data: 'comprobante_id', orderable: false, className: 'text-center no-export', render: botonVer }
                    ], { order: [[0, 'desc']] });
                }).fail(error);
        }

        const cargadores = { Deuda: cargarDeuda, Cta: cargarCuenta, Comp: cargarComprobantes };

        function cargarPestanaActiva() {
            const clave = $root.find('[data-ctacte-tab].active').data('ctacte-tab') || 'Deuda';
            if (!cargadas[clave]) {
                cargadas[clave] = true;
                cargadores[clave]();
            }
        }

        // Eventos ---------------------------------------------------------------
        $root.on('shown.bs.tab', '[data-ctacte-tab]', function () {
            if (!entidadId) return;
            cargarPestanaActiva();
            const clave = $(this).data('ctacte-tab');
            const t = tablas[clave === 'Deuda' ? 'deuda' : (clave === 'Cta' ? 'cta' : 'comp')];
            if (t) t.columns.adjust();
        });
        $root.on('click', '#' + p + 'CtaFiltrar', function () { if (entidadId) cargarCuenta(); });
        $root.on('click', '#' + p + 'CompFiltrar', function () { if (entidadId) cargarComprobantes(); });
        $root.on('input search', '[data-ctacte-buscar]', function () {
            const t = tablas[$(this).data('ctacte-buscar') === p + 'Cta' ? 'cta' : 'comp'];
            if (t) t.search(this.value).draw();
        });
        $root.on('click', '#' + p + 'Recargar', function () { if (entidadId) api.cargar(entidadId); });
        $root.on('click', '.ctacte-ver', function () {
            UI.mostrarDetalle(URL_AJAX, params({ accion: 'obtener_comprobante', comprobante_id: $(this).data('id') }));
        });

        const api = {
            cargar: function (id) {
                entidadId = parseInt(id, 10) || 0;
                cargadas = {};
                aviso('');
                if (!entidadId) { api.limpiar(); return; }
                cliente = {};
                if (opts.mostrarCabecera || opts.exportar) {
                    $.getJSON(URL_AJAX, params({ accion: 'cliente' })).done(function (res) {
                        if (!res.success) return;
                        cliente = res.data;   // título de Excel / PDF / correo
                        if (!opts.mostrarCabecera) return;
                        $('#' + p + 'Nombre').text(res.data.entidad_nombre);
                        $('#' + p + 'Datos').text([
                            res.data.entidad_fantasia, res.data.cuit ? 'CUIT ' + res.data.cuit : '', res.data.condicion_fiscal
                        ].filter(Boolean).join(' · '));
                    });
                }
                cargarResumen();
                cargarPestanaActiva();
            },
            limpiar: function () {
                entidadId = 0;
                cargadas = {};
                cliente = {};
                Object.keys(tablas).forEach(function (k) { tablas[k].clear().draw(); });
                $root.find('[id^="' + p + '"][id$="Sub"]').text('');
                ['Saldo', 'Deuda', 'Vencido', 'Favor', 'Cobro'].forEach(function (k) {
                    $('#' + p + k).text('-').removeClass('text-danger text-success');
                });
                $('#' + p + 'CantDeuda').text('0');
                $('#' + p + 'SaldoAnt').html('');
                $('#' + p + 'TotDeuda, #' + p + 'TotDebe, #' + p + 'TotHaber, #' + p + 'TotSaldo').text('');
                if (opts.mostrarCabecera) {
                    $('#' + p + 'Nombre').text('Seleccione un cliente');
                    $('#' + p + 'Datos').text('');
                }
            },
            /** Para recalcular anchos cuando el widget estaba oculto (solapa o modal). */
            ajustar: function () {
                Object.keys(tablas).forEach(function (k) { tablas[k].columns.adjust(); });
            }
        };
        return api;
    }

    window.CtacteCliente = { crear: crear };
})(window, jQuery);
