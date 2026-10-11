// Padrones IIBB (página 115). docs/gestion/09_percepciones_iibb.md §7 y §12
$(function () {
    var URL_AJAX = 'padrones_iibb_ajax.php';
    var SITUACION = { PADRON: 'Con alta (alícuota del padrón)', INSCRIPTO_OTRA_JURISDICCION: 'Sin alta: percepción por falta de alta', NO_PERCIBIR: 'En el padrón, sin percepción' };
    var FUENTE = { ARCHIVO: 'Padrón', WS: 'Servicio web', MANUAL: 'Carga manual' };
    function situacion(r) { return esc(SITUACION[r.situacion] || r.situacion) + (r.fuente && r.fuente !== 'ARCHIVO' ? ' <span class="badge bg-info">' + esc(FUENTE[r.fuente] || r.fuente) + '</span>' : ''); }
    function alicuota(r) { return pct(r.alicuota_percepcion) + (r.codigo_origen ? ' (' + esc(r.codigo_origen) + ')' : ''); }
    function esc(t) { return $('<div>').text(t == null ? '' : t).html(); }
    function pct(v) { return Number(v || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%'; }
    var lang = { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' };

    var tablaImp = $('#tablaPadImportaciones').DataTable({
        data: [], order: [], pageLength: 10, language: lang,
        columns: [
            { data: 'created_at', render: function (d) { return esc(d ? d.substr(0, 16) : ''); } },
            { data: 'jurisdiccion_nombre', render: esc },
            { data: 'periodo', render: function (d, t, r) { return esc(d ? d.substr(0, 4) + '-' + d.substr(4) : (r.f_desde ? r.f_desde + ' a ' + (r.f_hasta || '') : '')); } },
            { data: 'archivo_nombre', render: function (d, t, r) { return esc(d) + (r.observaciones ? ' <i class="fas fa-exclamation-triangle text-warning" title="' + esc(r.observaciones) + '"></i>' : ''); } },
            { data: 'filas_leidas', className: 'text-end' },
            { data: 'filas_cargadas', className: 'text-end' },
            { data: 'filas_rechazadas', className: 'text-end' },
            { data: 'estado', render: function (d) { return '<span class="badge ' + (d === 'Vigente' ? 'bg-success' : 'bg-secondary') + '">' + esc(d) + '</span>'; } }
        ]
    });
    var tablaCli = $('#tablaPadClientes').DataTable({
        data: [], order: [], pageLength: 25, language: lang,
        columns: [
            { data: 'entidad_nombre', render: esc },
            { data: 'cuit', render: esc },
            { data: 'situacion', render: function (d, t, r) { return d == null ? '<span class="text-muted">No figura</span>' : situacion(r); } },
            { data: 'alicuota_percepcion', className: 'text-end', render: function (d, t, r) { return d == null ? '' : alicuota(r); } }
        ]
    });

    function cargarImportaciones() {
        $.getJSON(URL_AJAX, { accion: 'listar' }, function (r) { if (r && r.success) tablaImp.clear().rows.add(r.data).draw(); });
    }

    $.getJSON(URL_AJAX, { accion: 'maestros' }, function (r) {
        if (!r || !r.success) return;
        var jur = r.data.jurisdicciones || [];
        $('#padSinConfig').toggleClass('d-none', jur.length > 0);
        $('#padJurisdiccion').html(jur.map(function (j) {
            return '<option value="' + j.jurisdiccion_id + '">' + esc(j.jurisdiccion_nombre) + ' (' + esc(j.formato_padron) + ')</option>';
        }).join(''));
        $('#cardImportar').toggleClass('d-none', !r.data.puede_importar || !jur.length);
        $('#padMaxUpload').text('Tamaño máximo del servidor: ' + (r.data.max_upload || '?') + '. Si el TXT es más grande, súbalo comprimido en ZIP.');
    });
    var hoy = new Date();
    $('#padPeriodo').val(hoy.toISOString().slice(0, 7));
    cargarImportaciones();
    $('#btnPadRecargar').on('click', cargarImportaciones);

    $('#formPadImportar').on('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(this);
        fd.append('accion', 'importar');
        var btn = $(this).find('button[type=submit]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Importando...');
        $.ajax({ url: URL_AJAX, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json', timeout: 0 })
            .done(function (r) {
                if (r && r.success) {
                    Swal.fire('Padrón importado', r.message + ((r.data.ejemplos || []).length ? '<br><small>' + r.data.ejemplos.map(esc).join('<br>') + '</small>' : ''), 'success');
                    $('#formPadImportar')[0].reset();
                    cargarImportaciones();
                } else Swal.fire('Error', (r && r.message) || 'No se pudo importar.', 'error');
            })
            .fail(function (x) { Swal.fire('Error', (x.responseJSON && x.responseJSON.message) || 'No se pudo importar.', 'error'); })
            .always(function () { btn.prop('disabled', false).html('<i class="fas fa-upload me-1"></i>Importar'); });
    });

    $('#btnPadConsultar').on('click', function () {
        $.getJSON(URL_AJAX, { accion: 'consultar_cuit', jurisdiccion_id: $('#padJurisdiccion').val(), periodo: $('#padPeriodo').val(), cuit: $('#padCuit').val() }, function (r) {
            if (!r || !r.success) { $('#padResultadoCuit').html('<span class="text-danger">' + esc((r && r.message) || 'Error') + '</span>'); return; }
            var p = r.data;
            $('#padResultadoCuit').html(p
                ? '<b>' + esc(p.razon_social) + '</b><br>' + situacion(p) + ' · Alícuota ' + alicuota(p) + (p.crc != null ? ' · CRC ' + esc(p.crc) : '')
                  + ' · Vigencia ' + esc(p.f_desde) + ' a ' + esc(p.f_hasta || 'sin fecha')
                : '<span class="text-muted">No figura en el padrón del período: se sigue por la condición IIBB del cliente.</span>');
        }).fail(function (x) { $('#padResultadoCuit').html('<span class="text-danger">' + esc((x.responseJSON && x.responseJSON.message) || 'Error') + '</span>'); });
    });

    $('#btnPadClientes').on('click', function () {
        $.getJSON(URL_AJAX, { accion: 'clientes', jurisdiccion_id: $('#padJurisdiccion').val(), periodo: $('#padPeriodo').val() }, function (r) {
            if (!r || !r.success) return;
            $('#cardPadClientes').removeClass('d-none');
            tablaCli.clear().rows.add(r.data).draw();
        });
    });
});
