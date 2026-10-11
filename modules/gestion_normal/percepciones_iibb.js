// Percepciones IIBB practicadas (página 116). docs/gestion/09_percepciones_iibb.md §8
$(function () {
    var URL_AJAX = 'percepciones_iibb_ajax.php';
    var SITUACIONES = {
        PADRON: 'Padrón', INSCRIPTO_JURISDICCION: 'Inscripto', SIMPLIFICADO_JURISDICCION: 'Simplificado',
        INSCRIPTO_OTRA_JURISDICCION: 'Sin alta en la jurisdicción', NO_INSCRIPTO: 'No inscripto', EXENTO: 'Exento', BIEN_DE_USO: 'Bien de uso'
    };
    var permisos = { exportar: false, liquidar: false };
    var resumenActual = null;

    function esc(t) { return $('<div>').text(t == null ? '' : t).html(); }
    function moneda(v) { return Number(v || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function fecha(d) { return d ? String(d).substr(0, 10).split('-').reverse().join('/') : ''; }

    var tabla = $('#tablaPercepciones').DataTable({
        data: [],
        order: [[0, 'asc']],
        pageLength: 25,
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
        columns: [
            { data: 'f_emision', render: function (d, t) { return t === 'display' ? fecha(d) : d; } },
            { data: null, render: function (r) { return esc(r.comprobante_tipo) + ' ' + esc(r.numero); } },
            { data: 'entidad_nombre', render: esc },
            { data: 'cuit', render: esc },
            { data: 'situacion', render: function (d) { return esc(SITUACIONES[d] || d || ''); } },
            { data: null, className: 'text-end', render: function (r) { return moneda(r.base_imponible * r.signo); } },
            { data: 'alicuota', className: 'text-end', render: function (d) { return moneda(d) + '%'; } },
            { data: 'importe_firmado', className: 'text-end fw-bold', render: function (d) { return moneda(d); } },
            { data: null, render: function (r) {
                return r.liquidacion_comprobante_id ? '<span class="badge bg-success">I_LIQ ' + esc(r.liquidacion_nro) + '</span>' : '<span class="badge bg-warning text-dark">Pendiente</span>';
            } }
        ]
    });

    function filtros() { return { jurisdiccion_id: $('#perJurisdiccion').val(), periodo: $('#perPeriodo').val() }; }

    function cargar() {
        var f = filtros();
        if (!f.jurisdiccion_id || !f.periodo) { tabla.clear().draw(); $('#perResumen').empty(); return; }
        $.getJSON(URL_AJAX, $.extend({ accion: 'listar' }, f), function (r) {
            if (!r || !r.success) { Swal.fire('Error', (r && r.message) || 'No se pudo listar.', 'error'); return; }
            tabla.clear().rows.add(r.data.filas).draw();
            resumenActual = r.data.resumen;
            var cfg = r.data.config;
            var s = r.data.resumen;
            $('#perResumen').html(
                '<span><b>Comprobantes:</b> ' + s.cantidad + '</span>' +
                '<span><b>Base:</b> $' + moneda(s.base) + '</span>' +
                '<span><b>Percibido neto:</b> $' + moneda(s.percibido) + '</span>' +
                '<span><b>Pendiente de liquidar:</b> $' + moneda(s.pendiente) + '</span>' +
                (cfg ? '<span class="text-muted">DDJJ: ' + esc(cfg.formato_ddjj || 'sin formato') + (cfg.numero_agente ? ' · Agente N° ' + esc(cfg.numero_agente) : ' · <span class="text-danger">sin N° de agente</span>') + '</span>'
                     : '<span class="text-danger">Sin configuración de percepción para el período</span>'));
            $('#btnPerExportar').toggleClass('d-none', !permisos.exportar || !s.cantidad);
            $('#btnPerLiquidar').toggleClass('d-none', !permisos.liquidar || !(s.pendiente > 0));
        }).fail(function (x) { Swal.fire('Error', (x.responseJSON && x.responseJSON.message) || 'No se pudo listar.', 'error'); });
    }

    $.getJSON(URL_AJAX, { accion: 'maestros' }, function (r) {
        if (!r || !r.success) return;
        permisos.exportar = !!r.data.puede_exportar;
        permisos.liquidar = !!r.data.puede_liquidar;
        var jur = r.data.jurisdicciones || [];
        $('#perJurisdiccion').html(jur.length ? jur.map(function (j) { return '<option value="' + j.jurisdiccion_id + '">' + esc(j.jurisdiccion_nombre) + '</option>'; }).join('')
                                              : '<option value="">La empresa no es agente de percepción</option>');
        $('#perLiqEntidad').html('<option value="">Seleccione...</option>' + (r.data.organismos || []).map(function (e) {
            return '<option value="' + e.entidad_id + '">' + esc(e.entidad_nombre) + '</option>';
        }).join(''));
        $('#perLiqSucursal').html((r.data.sucursales || []).map(function (s) {
            return '<option value="' + s.sucursal_id + '">' + esc(s.sucursal_nombre) + '</option>';
        }).join(''));
        var hoy = new Date();
        hoy.setMonth(hoy.getMonth() - 1);   // por defecto, el mes anterior (el que se declara)
        $('#perPeriodo').val(hoy.toISOString().slice(0, 7));
        cargar();
    });

    $('#perJurisdiccion, #perPeriodo').on('change', cargar);
    $('#btnPerRecargar').on('click', cargar);

    // Descarga del archivo: si el servidor devuelve un error (texto/JSON) se muestra en vez de bajarlo
    $('#btnPerExportar').on('click', function () {
        var f = filtros();
        var btn = $(this).prop('disabled', true);
        fetch(URL_AJAX + '?' + $.param($.extend({ accion: 'exportar' }, f)), { credentials: 'same-origin' }).then(function (resp) {
            var tipo = resp.headers.get('Content-Type') || '';
            var disp = resp.headers.get('Content-Disposition') || '';
            if (!resp.ok || tipo.indexOf('json') >= 0 || (tipo.indexOf('text/plain') >= 0 && disp.indexOf('attachment') < 0)) {
                return resp.text().then(function (t) {
                    var msg = t; try { msg = JSON.parse(t).message || t; } catch (e) { }
                    throw new Error(msg);
                });
            }
            var nombre = (disp.match(/filename="([^"]+)"/) || [])[1] || 'ddjj.txt';
            return resp.blob().then(function (b) {
                var a = document.createElement('a');
                a.href = URL.createObjectURL(b); a.download = nombre;
                document.body.appendChild(a); a.click(); a.remove();
                setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
            });
        }).catch(function (e) { Swal.fire('No se pudo exportar', e.message, 'warning'); })
          .finally(function () { btn.prop('disabled', false); });
    });

    $('#btnPerLiquidar').on('click', function () {
        $('#perLiqTexto').html('Se genera una liquidación (I_LIQ) por <b>$' + moneda(resumenActual ? resumenActual.pendiente : 0) + '</b> con las percepciones pendientes de ' +
            esc($('#perJurisdiccion option:selected').text()) + ' del período ' + esc($('#perPeriodo').val()) + '. Quedan marcadas como declaradas.');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPerLiquidar')).show();
    });

    $('#formPerLiquidar').on('submit', function (e) {
        e.preventDefault();
        var btn = $(this).find('button[type=submit]').prop('disabled', true);
        $.post(URL_AJAX, $.extend({ accion: 'liquidar', entidad_id: $('#perLiqEntidad').val(), sucursal_id: $('#perLiqSucursal').val(), f_vto: $('#perLiqVto').val(), nro_ddjj: $('#perLiqDdjj').val() }, filtros()), function (r) {
            if (r && r.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalPerLiquidar')).hide();
                Swal.fire('Liquidación generada', r.message, 'success');
                cargar();
            } else {
                Swal.fire('Error', (r && r.message) || 'No se pudo liquidar.', 'error');
            }
        }, 'json').fail(function (x) { Swal.fire('Error', (x.responseJSON && x.responseJSON.message) || 'No se pudo liquidar.', 'error'); })
          .always(function () { btn.prop('disabled', false); });
    });
});
