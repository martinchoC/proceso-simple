// Historial de Situación Fiscal (clientes / entidades): bloque "Percepciones IIBB" con las jurisdicciones de alta
// del Convenio Multilateral y los certificados de no percepción. El modal trae la entidad en data-entidad-id.
// _local/docs/gestion/09_percepciones_iibb.md §4.3 / §4.5
$(function () {
    var URL_AJAX = 'percepciones_iibb_clientes_ajax.php';
    var cont = $('#percepcionesIibbCliente');
    if (!cont.length) return;
    function esc(t) { return $('<div>').text(t == null ? '' : t).html(); }
    function fecha(d) { return d ? Fmt.fecha(d) : ''; }
    function entidad() { return parseInt($('#modalHistorialFiscal').attr('data-entidad-id') || 0, 10); }
    var datos = null;

    function render() {
        var d = datos, c = d.condicion;
        var html = '<div class="row g-3">';
        // Jurisdicciones de alta
        html += '<div class="col-lg-5"><div class="small fw-bold mb-1">Jurisdicciones de alta (Convenio Multilateral)</div>';
        if (!c) {
            html += '<div class="small text-muted">Sin condición IIBB vigente: para percepciones se toma como no inscripto.</div>';
        } else {
            html += '<div class="small text-muted mb-1">' + esc(c.regimen_iibb) + (c.sede ? ' · sede ' + esc(c.sede) : '') + '. La sede ya cuenta como jurisdicción de alta.</div>';
            html += (d.jurisdicciones || []).map(function (j) {
                return '<span class="badge bg-light text-dark border me-1 mb-1">' + esc(j.codigo_comarb + ' ' + j.jurisdiccion_sede) +
                    ' <a href="#" class="text-danger ms-1 pcli-quitar-jur" data-id="' + j.entidad_condicion_iibb_jurisdiccion_id + '" title="Quitar">&times;</a></span>';
            }).join('') || '<div class="small text-muted">Ninguna además de la sede.</div>';
            html += '<div class="input-group input-group-sm mt-1"><select class="form-select" id="pcliSede">' +
                (d.sedes || []).map(function (s) { return '<option value="' + s.jurisdiccion_sede_id + '">' + esc(s.codigo_comarb + ' - ' + s.jurisdiccion_sede) + '</option>'; }).join('') +
                '</select><button class="btn btn-outline-success" type="button" id="pcliAgregarJur"><i class="fas fa-plus"></i></button></div>';
        }
        html += '</div>';
        // Certificados
        html += '<div class="col-lg-7"><div class="small fw-bold mb-1">Certificados de no percepción / exclusión</div>';
        html += '<table class="table table-sm table-bordered mb-1"><thead class="table-light"><tr><th>Jurisdicción</th><th class="text-end">%</th><th>Certificado</th><th>Desde</th><th>Hasta</th><th></th></tr></thead><tbody>' +
            ((d.exclusiones || []).map(function (x) {
                return '<tr><td>' + esc(x.jurisdiccion_nombre) + '</td><td class="text-end">' + esc(x.porcentaje_exclusion) + '</td><td>' + esc(x.numero_certificado || '') +
                    '</td><td>' + fecha(x.f_desde) + '</td><td>' + (fecha(x.f_hasta) || '-') + '</td><td class="text-center"><a href="#" class="text-danger pcli-inh-excl" data-id="' +
                    x.entidad_iibb_exclusion_id + '" title="Inhabilitar"><i class="fas fa-ban"></i></a></td></tr>';
            }).join('') || '<tr><td colspan="6" class="text-muted small">Sin certificados.</td></tr>') + '</tbody></table>';
        html += '<div class="row g-1"><div class="col-md-4"><select class="form-select form-select-sm" id="pcliExclJur">' +
            (d.jurisdicciones_provinciales || []).map(function (j) { return '<option value="' + j.jurisdiccion_id + '">' + esc(j.jurisdiccion_nombre) + '</option>'; }).join('') +
            '</select></div><div class="col-md-2"><input type="number" class="form-control form-control-sm" id="pcliExclPct" value="100" min="0.01" max="100" step="0.01" title="% de exclusión"></div>' +
            '<div class="col-md-2"><input type="text" class="form-control form-control-sm" id="pcliExclNro" placeholder="N° cert."></div>' +
            '<div class="col-md-2"><input type="date" class="form-control form-control-sm" id="pcliExclDesde" title="Desde"></div>' +
            '<div class="col-md-2 d-flex gap-1"><input type="date" class="form-control form-control-sm" id="pcliExclHasta" title="Hasta">' +
            '<button class="btn btn-sm btn-outline-success" type="button" id="pcliAgregarExcl" title="Agregar"><i class="fas fa-plus"></i></button></div></div>';
        html += '</div></div>';
        cont.html(html);
    }

    function cargar() {
        var id = entidad();
        if (!id) { cont.empty(); return; }
        $.getJSON(URL_AJAX, { accion: 'datos', entidad_id: id }, function (r) {
            if (!r || !r.success) { cont.html('<div class="text-danger small">' + esc((r && r.message) || 'Error') + '</div>'); return; }
            datos = r.data; render();
        });
    }

    function enviar(data) {
        $.post(URL_AJAX, $.extend({ entidad_id: entidad() }, data), function (r) {
            if (!r || !r.success) { Swal.fire('Error', (r && r.message) || 'No se pudo guardar.', 'error'); return; }
            cargar();
        }, 'json').fail(function (x) { Swal.fire('Error', (x.responseJSON && x.responseJSON.message) || 'No se pudo guardar.', 'error'); });
    }

    $('#modalHistorialFiscal').on('shown.bs.modal', cargar);
    cont.on('click', '#pcliAgregarJur', function () {
        enviar({ accion: 'agregar_jurisdiccion', entidad_condicion_iibb_id: datos.condicion.entidad_condicion_iibb_id, jurisdiccion_sede_id: $('#pcliSede').val() });
    });
    cont.on('click', '.pcli-quitar-jur', function (e) { e.preventDefault(); enviar({ accion: 'quitar_jurisdiccion', id: $(this).data('id') }); });
    cont.on('click', '#pcliAgregarExcl', function () {
        enviar({ accion: 'agregar_exclusion', jurisdiccion_id: $('#pcliExclJur').val(), porcentaje_exclusion: $('#pcliExclPct').val(),
                 numero_certificado: $('#pcliExclNro').val(), f_desde: $('#pcliExclDesde').val(), f_hasta: $('#pcliExclHasta').val() });
    });
    cont.on('click', '.pcli-inh-excl', function (e) { e.preventDefault(); enviar({ accion: 'inhabilitar_exclusion', id: $(this).data('id') }); });
});
