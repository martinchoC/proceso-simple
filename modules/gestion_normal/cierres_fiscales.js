// Control Fiscal (página 125 / 625). Scripts: _local/sql/mantenimiento/20261016_cierres_fiscales_01_estructura.sql y _02_registro_inscripciones.sql
$(function () {
    var URL_AJAX = 'cierres_fiscales_ajax.php';
    var maestros = { obligaciones: [], jurisdicciones: [], origenes: {}, puede_cerrar: false, puede_reabrir: false };
    var ultimoCuadre = null;

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
    function claseDif(v) { return Math.abs(Number(v)) >= 0.01 ? 'text-danger fw-bold' : 'text-success'; }
    function mesActual(delta) {
        var d = new Date();
        d.setDate(1);
        d.setMonth(d.getMonth() + (delta || 0));
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    }

    // ---------------------------------------------------------------- períodos cerrados
    var tabla = $('#tablaCierres').DataTable({
        data: [], order: [[0, 'desc']], pageLength: 25,
        language: { url: window.APP_ASSETS_URL + 'js/datatables-i18n-1.13.6/es-ES.json' },
        columns: [
            { data: 'periodo', render: function (d, t) { return t === 'display' ? '<span class="fw-bold">' + nombreMes(d) + '</span>' : d; } },
            { data: 'impuesto_tipo', render: esc },
            { data: 'jurisdiccion_nombre', render: function (d) { return d ? esc(d) : '<span class="text-muted">Nacional</span>'; } },
            { data: 'origen', render: function (d) {
                var cls = d === 'MANUAL' ? 'bg-secondary' : 'bg-info text-dark';
                return '<span class="badge ' + cls + '">' + esc(maestros.origenes[d] || d) + '</span>';
            } },
            { data: null, render: function (r) { return esc(r.usuario || '') + '<br><small class="text-muted">' + fecha(r.fecha_creacion) + '</small>'; } },
            { data: null, render: function (r) {
                if (Number(r.tabla_estado_registro_id) === 5) return '<span class="badge bg-danger"><i class="fas fa-lock me-1"></i>' + esc(r.estado || 'Cerrado') + '</span>';
                return '<span class="badge bg-light text-dark border">' + esc(r.estado || 'Reabierto') + '</span><br><small class="text-muted">'
                    + esc(r.usuario_reapertura || '') + ' ' + fecha(r.fecha_reapertura) + ': ' + esc(r.motivo_reapertura || '') + '</small>';
            } },
            { data: 'observaciones', render: esc },
            { data: null, orderable: false, className: 'text-end', render: function (r) {
                if (Number(r.tabla_estado_registro_id) !== 5 || !maestros.puede_reabrir) return '';
                if (r.origen === 'IVA_POSICION' || r.origen === 'IIBB_POSICION') return '<small class="text-muted">Se reabre anulando la liquidación</small>';
                return '<button type="button" class="btn btn-outline-danger btn-sm btn-reabrir" data-id="' + r.cierre_id + '" title="Reabrir"><i class="fas fa-lock-open"></i> Reabrir</button>';
            } }
        ]
    });

    function cargarCierres() {
        $.getJSON(URL_AJAX, {
            accion: 'listar', impuesto_tipo_id: $('#cfFiltroImpuesto').val(), anio: $('#cfFiltroAnio').val(),
            incluir_reabiertos: $('#cfFiltroReabiertos').is(':checked') ? 1 : 0
        }, function (r) {
            if (!r || !r.success) { error(null, (r && r.message) || 'No se pudieron cargar los cierres.'); return; }
            tabla.clear().rows.add(r.data).draw();
        }).fail(function (x) { error(x, 'No se pudieron cargar los cierres.'); });
    }

    function obligacion(id) {
        return maestros.obligaciones.filter(function (o) { return String(o.impuesto_tipo_id) === String(id); })[0];
    }

    $.getJSON(URL_AJAX, { accion: 'maestros' }, function (r) {
        if (!r || !r.success) { error(null, (r && r.message) || 'No se pudieron cargar los datos.'); return; }
        maestros = r.data;
        var ops = maestros.obligaciones.map(function (o) { return '<option value="' + o.impuesto_tipo_id + '">' + esc(o.impuesto_tipo) + '</option>'; }).join('');
        $('#cfFiltroImpuesto').append(ops);
        $('#cfImpuesto').html('<option value="">Elegir...</option>' + ops);
        $('#cfJurisdiccion').html('<option value="">Elegir...</option>' + maestros.jurisdicciones.map(function (j) {
            return '<option value="' + j.jurisdiccion_id + '">' + esc(j.jurisdiccion_nombre) + '</option>';
        }).join(''));
        $('#btnCfCerrar').toggleClass('d-none', !maestros.puede_cerrar);
        $('#btnCfInscribir').toggleClass('d-none', !maestros.puede_inscribir);
        $('#cfRegCircuito').append(Object.keys(maestros.circuitos).map(function (k) { return '<option value="' + k + '">' + esc(maestros.circuitos[k]) + '</option>'; }).join(''));
        $('#cfRegImpuesto').append(maestros.impuestos.map(function (i) { return '<option value="' + i.impuesto_tipo_id + '">' + esc(i.impuesto_tipo) + '</option>'; }).join(''));
        var jurs = maestros.jurisdicciones.map(function (j) { return '<option value="' + j.jurisdiccion_id + '">' + esc(j.jurisdiccion_nombre) + '</option>'; }).join('');
        $('#cfRegJurisdiccion').append(jurs);
        var estados = Object.keys(maestros.certificado_estados || {}).map(function (k) {
            return '<option value="' + k + '">' + esc(maestros.certificado_estados[k]) + '</option>';
        }).join('');
        $('#cfRegEstadoCert').append(Object.keys(maestros.certificado_estados || {}).map(function (k) {
            return '<option value="' + k + '">Certificados ' + esc(maestros.certificado_estados[k].toLowerCase()) + 's</option>';
        }).join(''));
        $('#cfCertEstado').html(estados);
        $('#cfInsJurisdiccion').html('<option value="">Elegir...</option>' + jurs);
        $('#cfInsRegimen').html(maestros.regimenes_iibb.map(function (r) {
            return '<option value="' + r.iibb_regimen_id + '" data-codigo="' + esc(r.iibb_regimen_codigo) + '">' + esc(r.iibb_regimen_nombre) + '</option>';
        }).join(''));
        $('#cfLiqEntidad').html('<option value="">Elegir...</option>' + maestros.liquidacion.organismos.map(function (o) {
            return '<option value="' + o.entidad_id + '">' + esc(o.entidad_nombre) + '</option>';
        }).join(''));
        $('#cfLiqSucursal').html(maestros.liquidacion.sucursales.map(function (s) {
            return '<option value="' + s.sucursal_id + '">' + esc(s.sucursal_nombre) + '</option>';
        }).join(''));
        $('#cfIibbEntidad').html($('#cfLiqEntidad').html());
        $('#cfIibbSucursal').html($('#cfLiqSucursal').html());
        cargarCierres();
        cargarInscripciones();
        consultarRegistro();
        cargarLiquidacion();
        cargarIibb();
    }).fail(function (x) { error(x, 'No se pudieron cargar los datos.'); });

    $('#cfFiltroImpuesto, #cfFiltroAnio, #cfFiltroReabiertos').on('change', cargarCierres);
    $('#btnCfRecargar').on('click', cargarCierres);

    $('#cfImpuesto').on('change', function () {
        var o = obligacion($(this).val());
        $('#cfJurisdiccionGrupo').toggleClass('d-none', !(o && o.requiere_jurisdiccion));
    });

    $('#btnCfCerrar').on('click', function () {
        $('#formCfCerrar')[0].reset();
        $('#cfJurisdiccionGrupo').addClass('d-none');
        $('#cfPeriodo').val(mesActual(-1)).attr('max', mesActual(0));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCfCerrar')).show();
    });

    $('#formCfCerrar').on('submit', function (e) {
        e.preventDefault();
        var o = obligacion($('#cfImpuesto').val());
        if (!o) { Swal.fire('Atención', 'Elija la obligación.', 'warning'); return; }
        if (o.requiere_jurisdiccion && !$('#cfJurisdiccion').val()) { Swal.fire('Atención', 'Elija la jurisdicción.', 'warning'); return; }
        $.post(URL_AJAX, {
            accion: 'cerrar', impuesto_tipo_id: o.impuesto_tipo_id, jurisdiccion_id: o.requiere_jurisdiccion ? $('#cfJurisdiccion').val() : 0,
            periodo: $('#cfPeriodo').val(), observaciones: $('#cfObs').val()
        }, null, 'json').done(function (r) {
            if (!r || !r.success) { error(null, (r && r.message) || 'No se pudo cerrar el período.'); return; }
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCfCerrar')).hide();
            Swal.fire({ icon: 'success', title: 'Listo', text: r.message, timer: 1800, showConfirmButton: false });
            cargarCierres();
        }).fail(function (x) { error(x, 'No se pudo cerrar el período.'); });
    });

    $('#tablaCierres').on('click', '.btn-reabrir', function () {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Reabrir período', input: 'text', inputLabel: 'Motivo (queda registrado)', inputAttributes: { maxlength: 255 },
            showCancelButton: true, confirmButtonText: 'Reabrir', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545',
            inputValidator: function (v) { return !v || !v.trim() ? 'Ingrese el motivo.' : undefined; }
        }).then(function (res) {
            if (!res.isConfirmed) return;
            $.post(URL_AJAX, { accion: 'reabrir', cierre_id: id, motivo: res.value }, null, 'json').done(function (r) {
                if (!r || !r.success) { error(null, (r && r.message) || 'No se pudo reabrir.'); return; }
                Swal.fire({ icon: 'success', title: 'Listo', text: r.message, timer: 1500, showConfirmButton: false });
                cargarCierres();
            }).fail(function (x) { error(x, 'No se pudo reabrir.'); });
        });
    });

    // ---------------------------------------------------------------- registro de los cuatro circuitos
    var ultimoRegistro = null;
    var tablaReg = $('#tablaRegistro').DataTable({
        data: [], order: [[1, 'asc']], pageLength: 25,
        language: { url: window.APP_ASSETS_URL + 'js/datatables-i18n-1.13.6/es-ES.json' },
        columns: [
            { data: 'circuito_nombre', render: esc },
            { data: 'fecha', render: function (d, t) { return t === 'display' ? fecha(d) : d; } },
            { data: 'comprobante', render: esc },
            { data: null, render: function (r) { return esc(r.entidad || '') + (r.cuit ? '<br><small class="text-muted">' + esc(r.cuit) + '</small>' : ''); } },
            { data: 'impuesto', render: esc },
            { data: 'jurisdiccion', render: function (d, t, r) { return Number(r.jurisdiccion_id) > 0 ? esc(d) : '<span class="text-muted">Nacional</span>'; } },
            { data: 'base', className: 'text-end', render: function (d) { return d === null ? '' : moneda(d); } },
            { data: 'alicuota', className: 'text-end', render: function (d) { return d === null ? '' : Fmt.numero(Number(d), { maximumFractionDigits: 4 }) + ' %'; } },
            { data: 'importe', className: 'text-end fw-bold', render: function (d, t) { return t === 'display' ? moneda(d) : d; } },
            { data: null, render: function (r) {
                var html = esc(r.certificado || '');
                if (r.circuito === 'RET_SUFRIDA') {
                    var cls = { PENDIENTE: 'bg-warning text-dark', RECIBIDO: 'bg-info text-dark', CONCILIADO: 'bg-success' }[r.certificado_estado] || 'bg-secondary';
                    html += (html ? '<br>' : '') + '<span class="badge ' + cls + '">' + esc((maestros.certificado_estados || {})[r.certificado_estado] || r.certificado_estado || '') + '</span>'
                        + (r.f_certificado ? ' <small class="text-muted">' + fecha(r.f_certificado) + '</small>' : '')
                        + (Number(r.tiene_adjunto) ? ' <a href="' + URL_AJAX + '?accion=ver_certificado&cobranza_medio_id=' + r.cobranza_medio_id + '" target="_blank" title="Ver archivo"><i class="fas fa-paperclip"></i></a>' : '');
                }
                return html;
            } },
            { data: 'declarada', render: function (d) { return Number(d) ? '<span class="badge bg-success">Sí</span>' : ''; } },
            { data: null, orderable: false, render: function (r) {
                return r.circuito === 'RET_SUFRIDA' && maestros.puede_actualizar_certificado
                    ? '<button type="button" class="btn btn-outline-primary btn-sm btn-cert" data-id="' + r.cobranza_medio_id + '" title="Certificado"><i class="fas fa-pen"></i></button>' : '';
            } }
        ]
    });

    function filtrosRegistro() {
        return { desde: $('#cfRegDesde').val(), hasta: $('#cfRegHasta').val(), circuito: $('#cfRegCircuito').val(),
                 impuesto_tipo_id: $('#cfRegImpuesto').val(), jurisdiccion_id: $('#cfRegJurisdiccion').val(), tercero: $('#cfRegTercero').val(),
                 certificado_estado: $('#cfRegEstadoCert').val() };
    }

    function consultarRegistro() {
        var f = filtrosRegistro();
        if (!f.desde || !f.hasta) return;
        $.getJSON(URL_AJAX, $.extend({ accion: 'registro' }, f), function (r) {
            if (!r || !r.success) { error(null, (r && r.message) || 'No se pudo consultar.'); return; }
            ultimoRegistro = f;
            filasRegistro = r.data.filas;
            tablaReg.clear().rows.add(r.data.filas).draw();
            var res = r.data.resumen.map(function (x) {
                return '<tr><td>' + esc(x.circuito_nombre) + '</td><td>' + esc(x.impuesto) + '</td><td>' + (x.jurisdiccion ? esc(x.jurisdiccion) : '<span class="text-muted">Nacional</span>') + '</td>'
                    + '<td class="text-end">' + x.registros + '</td><td class="text-end">' + moneda(x.base) + '</td><td class="text-end fw-bold">' + moneda(x.importe) + '</td>'
                    + '<td class="text-end">' + (x.declarado ? moneda(x.declarado) : '') + '</td></tr>';
            }).join('');
            var ter = r.data.terceros.slice(0, 15).map(function (x) {
                return '<tr><td>' + esc(x.circuito_nombre) + '</td><td>' + esc(x.entidad || '') + ' <small class="text-muted">' + esc(x.cuit || '') + '</small></td>'
                    + '<td class="text-end">' + x.registros + '</td><td class="text-end fw-bold">' + moneda(x.importe) + '</td></tr>';
            }).join('');
            $('#cfRegResumen').html('<div class="row g-2 mb-2"><div class="col-lg-7">'
                + tablaHtml(['Circuito', 'Impuesto', 'Jurisdicción', '>Registros', '>Base', '>Importe', '>Declarado'], res)
                + '</div><div class="col-lg-5">'
                + tablaHtml(['Circuito', 'Tercero (los 15 mayores)', '>Registros', '>Importe'], ter)
                + '</div></div>');
            $('#btnCfRegExportar').toggleClass('d-none', !r.data.filas.length);
        }).fail(function (x) { error(x, 'No se pudo consultar.'); });
    }

    $('#cfRegDesde').val(mesActual(-1));
    $('#cfRegHasta').val(mesActual(0));
    $('#btnCfRegistro').on('click', consultarRegistro);
    $('#cfRegTercero').on('keydown', function (e) { if (e.key === 'Enter') consultarRegistro(); });
    $('#btnCfRegExportar').on('click', function () {
        if (ultimoRegistro) window.location = URL_AJAX + '?' + $.param($.extend({ accion: 'exportar_registro' }, ultimoRegistro));
    });

    // Certificado de una retención sufrida
    var filasRegistro = [];
    $('#tablaRegistro').on('click', '.btn-cert', function () {
        var id = Number($(this).data('id'));
        var r = filasRegistro.filter(function (x) { return Number(x.cobranza_medio_id) === id; })[0];
        if (!r) return;
        $('#formCfCertificado')[0].reset();
        $('#formCfCertificado').data('id', id);
        $('#cfCertTexto').html(esc(r.impuesto) + (Number(r.jurisdiccion_id) > 0 ? ' - ' + esc(r.jurisdiccion) : '') + ' · ' + esc(r.comprobante) + ' · '
            + esc(r.entidad || '') + ' · <b>$' + moneda(r.importe) + '</b>');
        $('#cfCertEstado').val(r.certificado_estado || 'PENDIENTE');
        $('#cfCertPeriodo').val(r.periodo_computo || r.periodo);
        $('#cfCertNro').val(r.certificado || '');
        $('#cfCertFecha').val(r.f_certificado || '');
        $('#cfCertRegimen').val(r.regimen || '');
        $('#cfCertArchivoActual').html(Number(r.tiene_adjunto)
            ? 'Ya tiene archivo: <a href="' + URL_AJAX + '?accion=ver_certificado&cobranza_medio_id=' + id + '" target="_blank">ver</a>. Si sube otro, lo reemplaza.' : '');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCfCertificado')).show();
    });

    $('#cfCertNro').on('input', function () {
        if ($(this).val().trim() && $('#cfCertEstado').val() === 'PENDIENTE') $('#cfCertEstado').val('RECIBIDO');
    });

    $('#formCfCertificado').on('submit', function (e) {
        e.preventDefault();
        var fd = new FormData();
        fd.append('accion', 'actualizar_certificado');
        fd.append('cobranza_medio_id', $(this).data('id'));
        fd.append('retencion_certificado_estado', $('#cfCertEstado').val());
        fd.append('retencion_periodo_computo', $('#cfCertPeriodo').val());
        fd.append('retencion_certificado_nro', $('#cfCertNro').val());
        fd.append('retencion_f_certificado', $('#cfCertFecha').val());
        fd.append('retencion_regimen', $('#cfCertRegimen').val());
        var archivo = $('#cfCertArchivo')[0].files[0];
        if (archivo) fd.append('archivo', archivo);
        var btn = $(this).find('[type=submit]').prop('disabled', true);
        $.ajax({ url: URL_AJAX, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' }).done(function (r) {
            if (!r || !r.success) { error(null, (r && r.message) || 'No se pudo guardar.'); return; }
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCfCertificado')).hide();
            Swal.fire({ icon: 'success', title: 'Listo', text: r.message, timer: 1500, showConfirmButton: false });
            consultarRegistro();
        }).fail(function (x) { error(x, 'No se pudo guardar.'); }).always(function () { btn.prop('disabled', false); });
    });

    // ---------------------------------------------------------------- liquidación de retenciones practicadas
    var liqFilas = [];
    var liqActual = null;

    function cargarLiquidacion() {
        var periodo = $('#cfLiqPeriodo').val();
        if (!periodo) return;
        $.getJSON(URL_AJAX, { accion: 'liquidacion_resumen', periodo: periodo }, function (r) {
            if (!r || !r.success) { error(null, (r && r.message) || 'No se pudo cargar la liquidación.'); return; }
            liqFilas = r.data.filas;
            var html = liqFilas.map(function (f, i) {
                var liqs = f.liquidaciones.map(function (l) {
                    return '<div class="small">I_LIQ ' + esc(l.numero) + ' · $' + moneda(l.total) + ' · ' + l.retenciones + ' reg. · ' + esc(l.organismo || '')
                        + (l.nro_ddjj ? ' · DDJJ ' + esc(l.nro_ddjj) : '')
                        + (l.pagada ? ' <span class="badge bg-success">Pagada</span>' : ' <span class="badge bg-warning text-dark">A pagar</span>')
                        + ' <a href="#" class="btn-liq-detalle" data-comp="' + l.comprobante_id + '" title="Ver retenciones"><i class="fas fa-list"></i></a>'
                        + (!l.pagada && maestros.puede_anular_liquidacion && !f.cerrado
                            ? ' <a href="#" class="text-danger btn-liq-anular" data-comp="' + l.comprobante_id + '" data-numero="' + esc(l.numero) + '" title="Anular"><i class="fas fa-ban"></i></a>' : '')
                        + '</div>';
                }).join('') || '<span class="text-muted small">Sin liquidar</span>';
                var acc = '';
                if (f.cerrado) acc = '<span class="badge bg-danger"><i class="fas fa-lock me-1"></i>Período cerrado</span>';
                else if (!f.tiene_cuenta && f.pendientes) acc = '<span class="badge bg-warning text-dark" title="Configurar en Impuestos Adic. - Conf">Config. sin cuenta contable</span>';
                else if (f.pendientes && maestros.puede_liquidar_retenciones) {
                    acc = '<button type="button" class="btn btn-success btn-sm btn-liq-liquidar" data-i="' + i + '"><i class="fas fa-file-invoice-dollar me-1"></i>Liquidar</button>';
                }
                return '<tr><td>' + esc(f.impuesto) + '</td><td>' + (f.jurisdiccion ? esc(f.jurisdiccion) : '<span class="text-muted">Nacional</span>') + '</td>'
                    + '<td class="text-end">' + (f.pendientes ? '<a href="#" class="btn-liq-pendientes" data-i="' + i + '">' + f.pendientes + '</a>' : '0') + '</td>'
                    + '<td class="text-end fw-bold">' + moneda(f.importe_pendiente) + '</td><td>' + liqs + '</td><td class="text-end">' + acc + '</td></tr>';
            }).join('') || '<tr><td colspan="6" class="text-muted small">La empresa no es agente de retención en el período.</td></tr>';
            $('#tablaLiquidacion tbody').html(html);
        }).fail(function (x) { error(x, 'No se pudo cargar la liquidación.'); });
    }

    function verRetenciones(params, titulo) {
        $.getJSON(URL_AJAX, $.extend({ accion: 'liquidacion_detalle' }, params), function (r) {
            if (!r || !r.success) { error(null, (r && r.message) || 'No se pudo cargar el detalle.'); return; }
            var filas = r.data.map(function (x) {
                return '<tr><td>' + fecha(x.fecha) + '</td><td>' + esc(x.certificado || '') + '</td><td>' + esc(x.tercero || '') + '<br><small class="text-muted">' + esc(x.cuit || '') + '</small></td>'
                    + '<td>' + esc(x.comprobante || '') + '</td><td class="text-end">' + moneda(x.base_imponible) + '</td>'
                    + '<td class="text-end">' + Fmt.numero(Number(x.alicuota || 0), { maximumFractionDigits: 4 }) + ' %</td><td class="text-end fw-bold">' + moneda(x.importe) + '</td></tr>';
            }).join('');
            Swal.fire({ title: titulo, width: 900, html: '<div class="text-start small">'
                + tablaHtml(['Fecha', 'Certificado', 'Tercero', 'Comprobante', '>Base', '>Alícuota', '>Importe'], filas) + '</div>' });
        }).fail(function (x) { error(x, 'No se pudo cargar el detalle.'); });
    }

    $('#cfLiqPeriodo').val(mesActual(-1)).attr('max', mesActual(0)).on('change', cargarLiquidacion);
    $('#btnCfLiqRecargar').on('click', cargarLiquidacion);

    $('#tablaLiquidacion').on('click', '.btn-liq-pendientes', function (e) {
        e.preventDefault();
        var f = liqFilas[$(this).data('i')];
        verRetenciones({ periodo: $('#cfLiqPeriodo').val(), impuesto_tipo_id: f.impuesto_tipo_id, jurisdiccion_id: f.jurisdiccion_id },
                       'Pendientes: ' + f.impuesto + (f.jurisdiccion ? ' - ' + f.jurisdiccion : ''));
    });
    $('#tablaLiquidacion').on('click', '.btn-liq-detalle', function (e) {
        e.preventDefault();
        verRetenciones({ comprobante_id: $(this).data('comp') }, 'Retenciones de la liquidación');
    });

    $('#tablaLiquidacion').on('click', '.btn-liq-liquidar', function () {
        liqActual = liqFilas[$(this).data('i')];
        $('#formCfLiquidar')[0].reset();
        $('#cfLiqTexto').html('<b>' + esc(liqActual.impuesto) + (liqActual.jurisdiccion ? ' - ' + esc(liqActual.jurisdiccion) : '') + '</b>, período <b>'
            + nombreMes($('#cfLiqPeriodo').val()) + '</b>: ' + liqActual.pendientes + ' registros por <b>$' + moneda(liqActual.importe_pendiente) + '</b>.');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCfLiquidar')).show();
    });

    $('#formCfLiquidar').on('submit', function (e) {
        e.preventDefault();
        if (!liqActual) return;
        var btn = $(this).find('[type=submit]').prop('disabled', true);
        $.post(URL_AJAX, {
            accion: 'liquidar_retenciones', periodo: $('#cfLiqPeriodo').val(), impuesto_tipo_id: liqActual.impuesto_tipo_id,
            jurisdiccion_id: liqActual.jurisdiccion_id, entidad_id: $('#cfLiqEntidad').val(), sucursal_id: $('#cfLiqSucursal').val(),
            f_vto: $('#cfLiqVto').val(), nro_ddjj: $('#cfLiqDdjj').val()
        }, null, 'json').done(function (r) {
            if (!r || !r.success) { error(null, (r && r.message) || 'No se pudo liquidar.'); return; }
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCfLiquidar')).hide();
            Swal.fire({ icon: 'success', title: 'Liquidado', text: r.message });
            cargarLiquidacion();
        }).fail(function (x) { error(x, 'No se pudo liquidar.'); }).always(function () { btn.prop('disabled', false); });
    });

    $('#tablaLiquidacion').on('click', '.btn-liq-anular', function (e) {
        e.preventDefault();
        var comp = $(this).data('comp'), numero = $(this).data('numero');
        Swal.fire({
            title: '¿Anular la liquidación ' + numero + '?', icon: 'warning', input: 'text', inputLabel: 'Motivo',
            html: '<p class="small mb-0">Se anula la I_LIQ y sus retenciones vuelven a quedar pendientes.</p>',
            inputValidator: function (v) { return (!v || !v.trim()) ? 'Ingrese el motivo' : null; },
            showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Anular', cancelButtonText: 'Cancelar'
        }).then(function (res) {
            if (!res.isConfirmed) return;
            $.post(URL_AJAX, { accion: 'anular_liquidacion_retenciones', comprobante_id: comp, motivo: res.value.trim() }, null, 'json').done(function (r) {
                if (!r || !r.success) { error(null, (r && r.message) || 'No se pudo anular.'); return; }
                Swal.fire({ icon: 'success', title: 'Anulada', text: r.message, timer: 1800, showConfirmButton: false });
                cargarLiquidacion();
            }).fail(function (x) { error(x, 'No se pudo anular.'); });
        });
    });

    // ---------------------------------------------------------------- inscripciones IIBB
    function cargarInscripciones() {
        $.getJSON(URL_AJAX, { accion: 'inscripciones', incluir_inactivas: $('#cfInsInactivas').is(':checked') ? 1 : 0 }, function (r) {
            if (!r || !r.success) { error(null, (r && r.message) || 'No se pudieron cargar las inscripciones.'); return; }
            var html = r.data.map(function (i) {
                var activa = Number(i.tabla_estado_registro_id) === 1;
                return '<tr' + (activa ? '' : ' class="text-muted"') + '><td>' + esc(i.jurisdiccion_nombre) + '</td><td>' + esc(i.iibb_regimen_nombre || '') + '</td>'
                    + '<td>' + esc(i.nro_inscripcion || '') + '</td><td>' + (Number(i.es_sede) ? '<span class="badge bg-primary">Sede</span>' : '') + '</td>'
                    + '<td class="text-end">' + (i.coeficiente_cm !== null ? Fmt.numero(Number(i.coeficiente_cm), { minimumFractionDigits: 4, maximumFractionDigits: 6 }) : '') + '</td>'
                    + '<td class="text-nowrap">' + fecha(i.f_desde) + ' a ' + (i.f_hasta ? fecha(i.f_hasta) : 'hoy') + '</td>'
                    + '<td>' + (i.actividades === null || i.iibb_regimen_codigo === 'EXENTO' ? ''
                        : '<a href="#" class="btn-ins-actividades" data-id="' + i.inscripcion_id + '" data-nombre="' + esc(i.jurisdiccion_nombre) + '" data-desde="' + esc(i.f_desde) + '">'
                          + (Number(i.actividades) ? Number(i.actividades) + ' actividad' + (Number(i.actividades) === 1 ? '' : 'es') : '<span class="text-danger">Cargar actividades</span>') + '</a>') + '</td>'
                    + '<td>' + esc(i.observaciones || '') + '</td>'
                    + '<td>' + (activa ? '<span class="badge bg-success">Activa</span>' : '<span class="badge bg-secondary">Inhabilitada</span>') + '</td>'
                    + '<td class="text-end">' + (activa && maestros.puede_inhabilitar_inscripcion
                        ? '<button type="button" class="btn btn-outline-danger btn-sm btn-ins-inhabilitar" data-id="' + i.inscripcion_id + '" title="Inhabilitar"><i class="fas fa-ban"></i></button>' : '')
                    + '</td></tr>';
            }).join('') || '<tr><td colspan="10" class="text-muted small">Sin inscripciones cargadas.</td></tr>';
            $('#tablaInscripciones tbody').html(html);
        }).fail(function (x) { error(x, 'No se pudieron cargar las inscripciones.'); });
    }
    $('#cfInsInactivas').on('change', cargarInscripciones);

    $('#btnCfInscribir').on('click', function () {
        $('#formCfInscripcion')[0].reset();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCfInscripcion')).show();
    });

    $('#formCfInscripcion').on('submit', function (e) {
        e.preventDefault();
        $.post(URL_AJAX, {
            accion: 'agregar_inscripcion', jurisdiccion_id: $('#cfInsJurisdiccion').val(), iibb_regimen_id: $('#cfInsRegimen').val(),
            nro_inscripcion: $('#cfInsNro').val(), coeficiente_cm: $('#cfInsCoef').val(), f_desde: $('#cfInsDesde').val(), f_hasta: $('#cfInsHasta').val(),
            es_sede: $('#cfInsSede').is(':checked') ? 1 : 0, observaciones: $('#cfInsObs').val()
        }, null, 'json').done(function (r) {
            if (!r || !r.success) { error(null, (r && r.message) || 'No se pudo guardar.'); return; }
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCfInscripcion')).hide();
            Swal.fire({ icon: 'success', title: 'Listo', text: r.message, timer: 1500, showConfirmButton: false });
            cargarInscripciones();
        }).fail(function (x) { error(x, 'No se pudo guardar.'); });
    });

    $('#tablaInscripciones').on('click', '.btn-ins-inhabilitar', function () {
        var id = $(this).data('id');
        Swal.fire({ title: '¿Inhabilitar la inscripción?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Inhabilitar',
                    cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' }).then(function (res) {
            if (!res.isConfirmed) return;
            $.post(URL_AJAX, { accion: 'inhabilitar_inscripcion', inscripcion_id: id }, null, 'json').done(function (r) {
                if (!r || !r.success) { error(null, (r && r.message) || 'No se pudo inhabilitar.'); return; }
                cargarInscripciones();
            }).fail(function (x) { error(x, 'No se pudo inhabilitar.'); });
        });
    });

    // ---------------------------------------------------------------- liquidación mensual de Ingresos Brutos
    var iibbFilas = [];
    var iibbActual = null;

    function resultadoHtml(pagar, favor) {
        if (Number(pagar) > 0) return '<span class="fw-bold text-danger">A pagar ' + moneda(pagar) + '</span>';
        if (Number(favor) > 0) return '<span class="fw-bold text-success">A favor ' + moneda(favor) + '</span>';
        return '<span class="text-muted">0,00</span>';
    }

    function cargarIibb() {
        var periodo = $('#cfIibbPeriodo').val();
        if (!periodo) return;
        if (!maestros.iibb_disponible) {
            $('#tablaIibb tbody').html('<tr><td colspan="9" class="text-muted small">Falta correr el script 20261111_iibb_liquidacion_mensual_01.sql.</td></tr>');
            return;
        }
        $.getJSON(URL_AJAX, { accion: 'iibb_resumen', periodo: periodo }, function (r) {
            if (!r || !r.success) { error(null, (r && r.message) || 'No se pudo cargar Ingresos Brutos.'); return; }
            iibbFilas = r.data.filas;
            $('#cfIibbBaseTotal').html('Ventas fiscales del mes sin IVA: <b>$' + moneda(r.data.base_total) + '</b> (' + r.data.comprobantes + ' comprobantes)');
            var html = iibbFilas.map(function (f, i) {
                var l = f.liquidacion;
                var v = l || f;
                var juris = '<b>' + esc(f.jurisdiccion) + '</b><br><small class="text-muted">' + esc(f.regimen || '')
                    + (f.regimen_codigo === 'CONVENIO_MULTILATERAL' ? ' · coef. ' + (f.coeficiente_cm !== null
                        ? Fmt.numero(f.coeficiente_cm, { minimumFractionDigits: 4, maximumFractionDigits: 6 }) : '<span class="text-danger">sin cargar</span>') : '')
                    + '</small>';
                var base = (!l && f.base_calculada === null)
                    ? '<span class="text-danger small">Falta el coeficiente</span>' : moneda(v.base_imponible);
                var liq, acc = '';
                if (l) {
                    liq = (l.numero ? 'I_LIQ ' + esc(l.numero) + (l.pagada ? ' <span class="badge bg-success">Pagada</span>' : ' <span class="badge bg-warning text-dark">A pagar</span>')
                                    : '<span class="badge bg-info text-dark">Saldo a favor</span>')
                        + (l.nro_ddjj ? '<br><small class="text-muted">DDJJ ' + esc(l.nro_ddjj) + '</small>' : '')
                        + ' <a href="#" class="btn-iibb-ver" data-i="' + i + '" title="Ver actividades declaradas"><i class="fas fa-list"></i></a>';
                    if (maestros.puede_anular_liquidacion_iibb && !l.pagada && !f.posterior) {
                        acc = '<button type="button" class="btn btn-outline-danger btn-sm btn-iibb-anular" data-i="' + i + '" title="Anular"><i class="fas fa-ban"></i> Anular</button>';
                    } else if (f.posterior) {
                        acc = '<small class="text-muted">Liquidado ' + esc(nombreMes(f.posterior)) + '</small>';
                    }
                } else {
                    liq = '<span class="text-muted small">Sin liquidar</span>';
                    if (f.cerrado_manual) acc = '<span class="badge bg-danger"><i class="fas fa-lock me-1"></i>Cerrado a mano</span>';
                    else if (!f.actividades.length) acc = '<span class="badge bg-warning text-dark" title="Solapa Inscripciones IIBB">Sin actividades</span>';
                    else if (maestros.puede_liquidar_iibb) {
                        acc = '<button type="button" class="btn btn-success btn-sm btn-iibb-liquidar" data-i="' + i + '"><i class="fas fa-file-invoice-dollar me-1"></i>Liquidar</button>';
                    }
                }
                var ded = function (importe, tipo) {
                    return Number(importe) ? '<a href="#" class="btn-iibb-ded" data-i="' + i + '" data-tipo="' + tipo + '">' + moneda(importe) + '</a>' : moneda(0);
                };
                return '<tr><td>' + juris + '</td><td class="text-end">' + base + '</td><td class="text-end">' + moneda(v.impuesto_determinado) + '</td>'
                    + '<td class="text-end">' + ded(v.retenciones, 'retenciones') + '</td><td class="text-end">' + ded(v.percepciones, 'percepciones') + '</td>'
                    + '<td class="text-end">' + moneda(v.saldo_favor_anterior) + '</td><td class="text-end">' + resultadoHtml(v.saldo_a_pagar, v.saldo_a_favor) + '</td>'
                    + '<td>' + liq + '</td><td class="text-end">' + acc + '</td></tr>';
            }).join('') || '<tr><td colspan="9" class="text-muted small">La empresa no tiene inscripciones de Ingresos Brutos vigentes en el período: cárguelas en la solapa Inscripciones IIBB.</td></tr>';
            $('#tablaIibb tbody').html(html);
        }).fail(function (x) { error(x, 'No se pudo cargar Ingresos Brutos.'); });
    }

    $('#cfIibbPeriodo').val(mesActual(-1)).attr('max', mesActual(0)).on('change', cargarIibb);
    $('#btnCfIibbRecargar').on('click', cargarIibb);

    $('#tablaIibb').on('click', '.btn-iibb-ded', function (e) {
        e.preventDefault();
        var f = iibbFilas[$(this).data('i')], tipo = $(this).data('tipo');
        $.getJSON(URL_AJAX, { accion: 'iibb_deducciones', periodo: $('#cfIibbPeriodo').val(), jurisdiccion_id: f.jurisdiccion_id }, function (r) {
            if (!r || !r.success) { error(null, (r && r.message) || 'No se pudo cargar el detalle.'); return; }
            var filas = r.data[tipo].map(function (x) {
                return '<tr><td>' + fecha(x.fecha) + '</td><td>' + esc(x.comprobante || '') + '</td><td>' + esc(x.tercero || '') + '<br><small class="text-muted">' + esc(x.cuit || '') + '</small></td>'
                    + '<td>' + esc(x.certificado || '') + '</td><td class="text-end fw-bold">' + moneda(x.importe) + '</td></tr>';
            }).join('');
            Swal.fire({ title: (tipo === 'retenciones' ? 'Retenciones' : 'Percepciones') + ' IIBB ' + f.jurisdiccion, width: 850,
                        html: '<div class="text-start small">' + tablaHtml(['Fecha', 'Comprobante', 'Tercero', 'Certificado', '>Importe'], filas) + '</div>' });
        }).fail(function (x) { error(x, 'No se pudo cargar el detalle.'); });
    });

    $('#tablaIibb').on('click', '.btn-iibb-ver', function (e) {
        e.preventDefault();
        var f = iibbFilas[$(this).data('i')];
        $.getJSON(URL_AJAX, { accion: 'iibb_liquidacion_actividades', iibb_posicion_id: f.liquidacion.iibb_posicion_id }, function (r) {
            if (!r || !r.success) { error(null, (r && r.message) || 'No se pudo cargar el detalle.'); return; }
            var filas = r.data.map(function (a) {
                return '<tr><td>' + esc(a.codigo || '') + ' ' + esc(a.descripcion || '') + '</td><td class="text-end">' + moneda(a.base_imponible) + '</td>'
                    + '<td class="text-end">' + Fmt.numero(Number(a.alicuota), { maximumFractionDigits: 4 }) + ' %</td>'
                    + '<td class="text-end">' + (a.minimo_mensual !== null ? moneda(a.minimo_mensual) : '') + '</td><td class="text-end fw-bold">' + moneda(a.impuesto_determinado) + '</td></tr>';
            }).join('');
            Swal.fire({ title: 'IIBB ' + f.jurisdiccion + ' · ' + nombreMes($('#cfIibbPeriodo').val()), width: 800,
                        html: '<div class="text-start small">' + tablaHtml(['Actividad', '>Base', '>Alícuota', '>Mínimo', '>Impuesto'], filas) + '</div>' });
        }).fail(function (x) { error(x, 'No se pudo cargar el detalle.'); });
    });

    function impuestoActividad(base, alicuota, minimo) {
        var calc = Math.round(Number(base || 0) * Number(alicuota)) / 100;
        return Math.max(calc, minimo !== null ? Number(minimo) : 0);
    }

    function recalcularIibb() {
        if (!iibbActual) return;
        var f = iibbActual, base = 0, imp = 0;
        $('#tablaIibbActividades tbody tr').each(function () {
            var a = f.actividades[$(this).data('i')];
            var b = Number($(this).find('.iibb-base').val() || 0);
            var x = impuestoActividad(b, a.alicuota, a.minimo_mensual);
            base += b;
            imp += x;
            $(this).find('.iibb-imp').text(moneda(x));
        });
        $('#tablaIibbActividades tfoot').html('<tr class="fw-bold"><td>Total</td><td class="text-end">' + moneda(base) + '</td><td></td><td></td><td class="text-end">' + moneda(imp) + '</td></tr>');
        var difBase = f.base_calculada !== null ? Math.round((base - Number(f.base_calculada)) * 100) / 100 : null;
        $('#cfIibbAvisoBase').html(difBase !== null && Math.abs(difBase) >= 0.01
            ? '<span class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>La base declarada difiere en ' + moneda(difBase) + ' de la calculada ($' + moneda(f.base_calculada) + ').</span>'
            : (f.base_calculada === null ? '<span class="text-danger">La inscripción de Convenio Multilateral no tiene coeficiente: cargue la base a mano.</span>' : ''));
        var res = Math.round((imp - f.retenciones - f.percepciones - f.saldo_favor_anterior) * 100) / 100;
        $('#cfIibbResultado').html('<table class="table table-sm mb-0"><tbody>'
            + '<tr><td>Impuesto determinado</td><td class="text-end">' + moneda(imp) + '</td></tr>'
            + '<tr><td>− Retenciones IIBB sufridas</td><td class="text-end">' + moneda(f.retenciones) + '</td></tr>'
            + '<tr><td>− Percepciones IIBB sufridas</td><td class="text-end">' + moneda(f.percepciones) + '</td></tr>'
            + '<tr><td>− Saldo a favor anterior' + (f.saldo_favor_anterior_periodo ? ' (' + esc(nombreMes(f.saldo_favor_anterior_periodo)) + ')' : '') + '</td><td class="text-end">' + moneda(f.saldo_favor_anterior) + '</td></tr>'
            + '<tr class="fs-6"><td class="fw-bold">Resultado</td><td class="text-end">' + resultadoHtml(res > 0 ? res : 0, res < 0 ? -res : 0) + '</td></tr>'
            + '</tbody></table>');
        $('#cfIibbEntidadGrupo').toggleClass('opacity-50', res <= 0);
    }

    $('#tablaIibb').on('click', '.btn-iibb-liquidar', function () {
        iibbActual = iibbFilas[$(this).data('i')];
        var f = iibbActual;
        $('#formCfIibb')[0].reset();
        $('#cfIibbTexto').html('<b>' + esc(f.jurisdiccion) + '</b> (' + esc(f.regimen || '') + (f.nro_inscripcion ? ', N° ' + esc(f.nro_inscripcion) : '') + '), período <b>'
            + nombreMes($('#cfIibbPeriodo').val()) + '</b>. Ventas del mes sin IVA: $' + moneda(f.base_total)
            + (f.regimen_codigo === 'CONVENIO_MULTILATERAL' && f.coeficiente_cm !== null
                ? ' × coeficiente ' + Fmt.numero(f.coeficiente_cm, { minimumFractionDigits: 4, maximumFractionDigits: 6 }) + ' = $' + moneda(f.base_calculada) : '') + '.');
        $('#tablaIibbActividades tbody').html(f.actividades.map(function (a, i) {
            return '<tr data-i="' + i + '"><td>' + (a.codigo ? '<span class="text-muted">' + esc(a.codigo) + '</span> ' : '') + esc(a.descripcion)
                + (Number(a.es_principal) ? ' <span class="badge bg-primary">Principal</span>' : '') + '</td>'
                + '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end iibb-base" value="' + Number(a.base_imponible).toFixed(2) + '"></td>'
                + '<td class="text-end">' + Fmt.numero(a.alicuota, { maximumFractionDigits: 4 }) + ' %</td>'
                + '<td class="text-end">' + (a.minimo_mensual !== null ? moneda(a.minimo_mensual) : '') + '</td>'
                + '<td class="text-end iibb-imp"></td></tr>';
        }).join(''));
        recalcularIibb();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCfIibb')).show();
    });

    $('#tablaIibbActividades').on('input', '.iibb-base', recalcularIibb);

    $('#formCfIibb').on('submit', function (e) {
        e.preventDefault();
        if (!iibbActual) return;
        var f = iibbActual;
        var actividades = [];
        $('#tablaIibbActividades tbody tr').each(function () {
            actividades.push({ actividad_id: f.actividades[$(this).data('i')].actividad_id, base_imponible: $(this).find('.iibb-base').val() || '0' });
        });
        var btn = $(this).find('[type=submit]').prop('disabled', true);
        $.post(URL_AJAX, {
            accion: 'liquidar_iibb', periodo: $('#cfIibbPeriodo').val(), jurisdiccion_id: f.jurisdiccion_id, actividades: JSON.stringify(actividades),
            entidad_id: $('#cfIibbEntidad').val(), sucursal_id: $('#cfIibbSucursal').val(), f_vto: $('#cfIibbVto').val(),
            nro_ddjj: $('#cfIibbDdjj').val(), observaciones: $('#cfIibbObs').val()
        }, null, 'json').done(function (r) {
            if (!r || !r.success) { error(null, (r && r.message) || 'No se pudo liquidar.'); return; }
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCfIibb')).hide();
            Swal.fire({ icon: 'success', title: 'Liquidado', text: r.message });
            cargarIibb();
            cargarCierres();
        }).fail(function (x) { error(x, 'No se pudo liquidar.'); }).always(function () { btn.prop('disabled', false); });
    });

    $('#tablaIibb').on('click', '.btn-iibb-anular', function () {
        var f = iibbFilas[$(this).data('i')];
        Swal.fire({
            title: '¿Anular la liquidación de IIBB ' + f.jurisdiccion + '?', icon: 'warning', input: 'text', inputLabel: 'Motivo',
            html: '<p class="small mb-0">Se anulan la I_LIQ (si tiene) y el asiento, y el período de IIBB de la jurisdicción se reabre.</p>',
            inputValidator: function (v) { return (!v || !v.trim()) ? 'Ingrese el motivo' : null; },
            showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Anular', cancelButtonText: 'Cancelar'
        }).then(function (res) {
            if (!res.isConfirmed) return;
            $.post(URL_AJAX, { accion: 'anular_liquidacion_iibb', iibb_posicion_id: f.liquidacion.iibb_posicion_id, motivo: res.value.trim() }, null, 'json').done(function (r) {
                if (!r || !r.success) { error(null, (r && r.message) || 'No se pudo anular.'); return; }
                Swal.fire({ icon: 'success', title: 'Anulada', text: r.message, timer: 1800, showConfirmButton: false });
                cargarIibb();
                cargarCierres();
            }).fail(function (x) { error(x, 'No se pudo anular.'); });
        });
    });

    // Actividades de una inscripción
    var actInscripcion = null;

    function cargarActividades() {
        if (!actInscripcion) return;
        $.getJSON(URL_AJAX, { accion: 'actividades', inscripcion_id: actInscripcion.id, incluir_inactivas: 1 }, function (r) {
            if (!r || !r.success) { error(null, (r && r.message) || 'No se pudieron cargar las actividades.'); return; }
            $('#tablaActividades tbody').html(r.data.map(function (a) {
                var activa = Number(a.tabla_estado_registro_id) === 1;
                return '<tr' + (activa ? '' : ' class="text-muted"') + '><td>' + esc(a.codigo || '') + '</td><td>' + esc(a.descripcion) + '</td>'
                    + '<td class="text-end">' + Fmt.numero(Number(a.alicuota), { maximumFractionDigits: 4 }) + ' %</td>'
                    + '<td class="text-end">' + (a.minimo_mensual !== null ? moneda(a.minimo_mensual) : '') + '</td>'
                    + '<td class="text-nowrap">' + fecha(a.f_desde) + ' a ' + (a.f_hasta ? fecha(a.f_hasta) : 'hoy') + '</td>'
                    + '<td>' + (Number(a.es_principal) ? '<span class="badge bg-primary">Principal</span>' : '') + (activa ? '' : ' <span class="badge bg-secondary">Inhabilitada</span>') + '</td>'
                    + '<td class="text-end">' + (activa && maestros.puede_inhabilitar_inscripcion
                        ? '<button type="button" class="btn btn-outline-danger btn-sm btn-act-inhabilitar" data-id="' + a.actividad_id + '" title="Inhabilitar"><i class="fas fa-ban"></i></button>' : '')
                    + '</td></tr>';
            }).join('') || '<tr><td colspan="7" class="text-muted small">Sin actividades cargadas.</td></tr>');
        }).fail(function (x) { error(x, 'No se pudieron cargar las actividades.'); });
    }

    $('#tablaInscripciones').on('click', '.btn-ins-actividades', function (e) {
        e.preventDefault();
        actInscripcion = { id: $(this).data('id'), desde: $(this).data('desde') };
        $('#cfActTitulo').text('· ' + $(this).data('nombre'));
        $('#formCfActividad')[0].reset();
        $('#formCfActividad').toggleClass('d-none', !maestros.puede_inscribir);
        cargarActividades();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCfActividades')).show();
    });

    $('#formCfActividad').on('submit', function (e) {
        e.preventDefault();
        var btn = $(this).find('[type=submit]').prop('disabled', true);
        $.post(URL_AJAX, {
            accion: 'agregar_actividad', inscripcion_id: actInscripcion.id, codigo: $('#cfActCodigo').val(), descripcion: $('#cfActDescripcion').val(),
            alicuota: $('#cfActAlicuota').val(), minimo_mensual: $('#cfActMinimo').val(), f_desde: $('#cfActDesde').val() || actInscripcion.desde,
            f_hasta: $('#cfActHasta').val(), es_principal: $('#cfActPrincipal').is(':checked') ? 1 : 0
        }, null, 'json').done(function (r) {
            if (!r || !r.success) { error(null, (r && r.message) || 'No se pudo guardar.'); return; }
            $('#formCfActividad')[0].reset();
            Swal.fire({ icon: 'success', title: 'Listo', text: r.message, timer: 1200, showConfirmButton: false });
            cargarActividades();
            cargarInscripciones();
            cargarIibb();
        }).fail(function (x) { error(x, 'No se pudo guardar.'); }).always(function () { btn.prop('disabled', false); });
    });

    $('#tablaActividades').on('click', '.btn-act-inhabilitar', function () {
        var id = $(this).data('id');
        Swal.fire({ title: '¿Inhabilitar la actividad?', text: 'Las liquidaciones ya hechas no cambian.', icon: 'warning', showCancelButton: true,
                    confirmButtonText: 'Inhabilitar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' }).then(function (res) {
            if (!res.isConfirmed) return;
            $.post(URL_AJAX, { accion: 'inhabilitar_actividad', actividad_id: id }, null, 'json').done(function (r) {
                if (!r || !r.success) { error(null, (r && r.message) || 'No se pudo inhabilitar.'); return; }
                cargarActividades();
                cargarInscripciones();
                cargarIibb();
            }).fail(function (x) { error(x, 'No se pudo inhabilitar.'); });
        });
    });

    // ---------------------------------------------------------------- cuadre
    $('#cfCuadreDesde').val(mesActual(-1));
    $('#cfCuadreHasta').val(mesActual(-1));

    function tablaHtml(cabecera, filas, pie) {
        return '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-3"><thead class="table-light"><tr>'
            + cabecera.map(function (c) { return '<th' + (c.charAt(0) === '>' ? ' class="text-end">' + esc(c.substr(1)) : '>' + esc(c)) + '</th>'; }).join('')
            + '</tr></thead><tbody>' + (filas || '<tr><td colspan="' + cabecera.length + '" class="text-muted small">Sin datos</td></tr>') + '</tbody>'
            + (pie || '') + '</table></div>';
    }

    function renderCuadre(c) {
        var t = c.totales;
        var resumen = '<div class="alert ' + (t.diferencias ? 'alert-warning' : 'alert-success') + ' py-2 small">'
            + (t.diferencias
                ? '<i class="fas fa-exclamation-triangle me-1"></i>' + t.diferencias + ' comprobante(s) no cuadran con su asiento. Ver el detalle abajo.'
                : '<i class="fas fa-check-circle me-1"></i>Todas las percepciones y retenciones del rango están contabilizadas en su cuenta por el mismo importe.')
            + '</div>';

        var imp = c.impuestos.map(function (i) {
            return '<tr><td>' + esc(i.impuesto) + '</td><td>' + (i.cuenta ? esc(i.cuenta) : '<span class="text-danger">Sin cuenta</span>') + '</td>'
                + '<td class="text-end">' + i.comprobantes + '</td><td class="text-end">' + moneda(i.esperado) + '</td>'
                + '<td class="text-end">' + moneda(i.contabilizado) + '</td><td class="text-end ' + claseDif(i.diferencia) + '">' + moneda(i.diferencia) + '</td>'
                + '<td class="text-end">' + (i.con_diferencia ? '<span class="badge bg-warning text-dark">' + i.con_diferencia + '</span>' : '') + '</td></tr>';
        }).join('');

        var ctas = c.cuentas.map(function (k) {
            return '<tr><td>' + esc(k.cuenta) + '<br><small class="text-muted">' + esc(k.impuestos || '') + '</small></td>'
                + '<td class="text-end">' + moneda(k.saldo_inicial) + '</td><td class="text-end">' + moneda(k.mov_comprobantes) + '</td>'
                + '<td class="text-end">' + moneda(k.mov_otros) + '</td><td class="text-end fw-bold">' + moneda(k.saldo_final) + '</td></tr>';
        }).join('');

        var dif = c.diferencias.map(function (d) {
            return '<tr><td>' + esc(d.origen) + '</td><td>' + fecha(d.fecha) + '</td><td>' + esc(d.comprobante) + '<br><small class="text-muted">' + esc(d.entidad || '') + '</small></td>'
                + '<td>' + esc(d.impuestos) + '</td><td>' + (d.cuenta ? esc(d.cuenta) : '') + '</td>'
                + '<td class="text-end">' + moneda(d.esperado) + '</td><td class="text-end">' + moneda(d.contabilizado) + '</td>'
                + '<td class="text-end ' + claseDif(d.diferencia) + '">' + moneda(d.diferencia) + '</td><td><span class="badge bg-warning text-dark">' + esc(d.motivo) + '</span></td></tr>';
        }).join('');

        $('#cfCuadreCuerpo').html(resumen
            + '<h6 class="fw-bold mt-2">Por impuesto y jurisdicción</h6>'
            + '<small class="text-muted d-block mb-1">Importes como van al asiento: Debe positivo (sufridas, activo), Haber negativo (practicadas, pasivo).</small>'
            + tablaHtml(['Impuesto', 'Cuenta', '>Registros', '>Según comprobantes', '>Contabilizado', '>Diferencia', '>Con diferencia'], imp)
            + '<h6 class="fw-bold">Por cuenta</h6>'
            + '<small class="text-muted d-block mb-1">Movimientos por fecha de asiento. "Liquidaciones y otros": liquidaciones de IVA / IIBB, pagos de obligaciones y asientos manuales.</small>'
            + tablaHtml(['Cuenta', '>Saldo inicial', '>Comprobantes', '>Liquidaciones y otros', '>Saldo final'], ctas)
            + '<h6 class="fw-bold">Comprobantes con diferencias</h6>'
            + tablaHtml(['Origen', 'Fecha', 'Comprobante', 'Impuestos', 'Cuenta', '>Según comprobante', '>Contabilizado', '>Diferencia', 'Motivo'], dif));
        $('#btnCfExportar').toggleClass('d-none', !c.diferencias.length);
    }

    $('#btnCfCuadre').on('click', function () {
        var desde = $('#cfCuadreDesde').val(), hasta = $('#cfCuadreHasta').val();
        if (!desde || !hasta) { Swal.fire('Atención', 'Elija el rango de períodos.', 'warning'); return; }
        $('#cfCuadreCuerpo').html('<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i></div>');
        $.getJSON(URL_AJAX, { accion: 'cuadre', desde: desde, hasta: hasta }, function (r) {
            if (!r || !r.success) { $('#cfCuadreCuerpo').empty(); error(null, (r && r.message) || 'No se pudo calcular el cuadre.'); return; }
            ultimoCuadre = { desde: desde, hasta: hasta };
            renderCuadre(r.data);
        }).fail(function (x) { $('#cfCuadreCuerpo').empty(); error(x, 'No se pudo calcular el cuadre.'); });
    });

    $('#btnCfExportar').on('click', function () {
        if (!ultimoCuadre) return;
        window.location = URL_AJAX + '?' + $.param({ accion: 'exportar_diferencias', desde: ultimoCuadre.desde, hasta: ultimoCuadre.hasta });
    });
});
