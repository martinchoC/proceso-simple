// Historial de Situación Fiscal (proveedores / entidades): bloque "Retenciones" con la condición del proveedor frente
// a cada retención que practica la empresa, su régimen habitual y los certificados de exclusión. El modal trae la
// entidad en data-entidad-id. _local/docs/gestion/10_retenciones_percepciones_relevamiento.md
$(function () {
    var URL_AJAX = 'retenciones_proveedores_ajax.php';
    var cont = $('#retencionesProveedor');
    if (!cont.length) return;
    function esc(t) { return $('<div>').text(t == null ? '' : t).html(); }
    function fecha(d) { return d ? Fmt.fecha(d) : ''; }
    function entidad() { return parseInt($('#modalHistorialFiscal').attr('data-entidad-id') || 0, 10); }
    var datos = null;

    function opcionesAgente(soloClave) {
        return (datos.agente || []).map(function (a, i) {
            return '<option value="' + i + '">' + esc(a.nombre) + '</option>';
        }).join('');
    }

    function render() {
        var d = datos;
        if (!d.agente.length) {
            cont.html('<div class="small text-muted">La empresa no es agente de retención (Impuestos Adic. - Conf → Agente de Retención).</div>');
            return;
        }
        var html = '<div class="small text-muted mb-1">Sin condición cargada, el cálculo de la orden de pago avisa y no retiene ese impuesto '
            + '(en IIBB toma la condición IIBB del proveedor).</div>';
        html += '<div class="mb-2">' + d.agente.map(function (a) {
            var c = a.condicion_vigente;
            return '<span class="badge ' + (c ? 'bg-light text-dark border' : 'bg-warning text-dark') + ' me-1 mb-1">' + esc(a.nombre) + ': ' +
                esc(c ? d.tipos[c] : 'sin condición') + '</span>';
        }).join('') + '</div>';

        html += '<div class="row g-3"><div class="col-lg-7"><div class="small fw-bold mb-1">Condición y régimen habitual</div>';
        html += '<table class="table table-sm table-bordered mb-1"><thead class="table-light"><tr><th>Retención</th><th>Condición</th><th>Régimen</th><th>Vigencia</th><th></th></tr></thead><tbody>';
        html += (d.condiciones || []).map(function (c) {
            return '<tr><td class="small">' + esc(c.impuesto_tipo + (c.jurisdiccion_nombre ? ' - ' + c.jurisdiccion_nombre : '')) + '</td>' +
                '<td class="small">' + esc(d.tipos[c.condicion] || c.condicion) + '</td>' +
                '<td class="small">' + (c.regimen_codigo ? esc(c.regimen_codigo + ' - ' + c.regimen_nombre) : '<span class="text-muted">el de la config</span>') + '</td>' +
                '<td class="small">' + fecha(c.f_desde) + ' → ' + (c.f_hasta ? fecha(c.f_hasta) : '…') + '</td>' +
                '<td class="text-center"><a href="#" class="text-danger rprov-baja" data-tipo="condicion" data-id="' + c.entidad_retencion_id + '" title="Inhabilitar">&times;</a></td></tr>';
        }).join('') || '<tr><td colspan="5" class="small text-muted">Sin condiciones cargadas.</td></tr>';
        html += '</tbody></table>';
        html += '<div class="row g-1 align-items-end">' +
            '<div class="col-md-4"><select class="form-select form-select-sm" id="rprov_cond_agente">' + opcionesAgente() + '</select></div>' +
            '<div class="col-md-3"><select class="form-select form-select-sm" id="rprov_cond_condicion">' +
                Object.keys(d.tipos).map(function (k) { return '<option value="' + k + '">' + esc(d.tipos[k]) + '</option>'; }).join('') + '</select></div>' +
            '<div class="col-md-5"><select class="form-select form-select-sm" id="rprov_cond_regimen"></select></div>' +
            '<div class="col-md-4"><input type="date" class="form-control form-control-sm" id="rprov_cond_desde" title="Desde"></div>' +
            '<div class="col-md-4"><input type="date" class="form-control form-control-sm" id="rprov_cond_hasta" title="Hasta (opcional)"></div>' +
            '<div class="col-md-4"><button type="button" class="btn btn-sm btn-outline-primary w-100" id="rprov_cond_agregar"><i class="fas fa-plus me-1"></i>Agregar condición</button></div>' +
            '</div></div>';

        html += '<div class="col-lg-5"><div class="small fw-bold mb-1">Certificados de exclusión / no retención</div>';
        html += (d.exclusiones || []).map(function (x) {
            return '<div class="small border rounded px-2 py-1 mb-1">' + esc((x.impuesto_tipo || '') + (x.jurisdiccion_nombre ? ' - ' + x.jurisdiccion_nombre : '')) +
                ': <b>' + esc(parseFloat(x.porcentaje_exclusion)) + ' %</b> · cert. ' + esc(x.numero_certificado) + ' · ' + fecha(x.f_desde) + ' → ' + fecha(x.f_hasta) +
                ' <a href="#" class="text-danger ms-1 rprov-baja" data-tipo="exclusion" data-id="' + x.entidad_iibb_exclusion_id + '" title="Inhabilitar">&times;</a></div>';
        }).join('') || '<div class="small text-muted mb-1">Sin certificados.</div>';
        html += '<div class="row g-1">' +
            '<div class="col-12"><select class="form-select form-select-sm" id="rprov_exc_agente">' + opcionesAgente() + '</select></div>' +
            '<div class="col-4"><input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" id="rprov_exc_pct" placeholder="%"></div>' +
            '<div class="col-8"><input type="text" class="form-control form-control-sm" id="rprov_exc_cert" maxlength="50" placeholder="N° certificado"></div>' +
            '<div class="col-6"><input type="date" class="form-control form-control-sm" id="rprov_exc_desde" title="Desde"></div>' +
            '<div class="col-6"><input type="date" class="form-control form-control-sm" id="rprov_exc_hasta" title="Vence"></div>' +
            '<div class="col-12"><button type="button" class="btn btn-sm btn-outline-primary w-100" id="rprov_exc_agregar"><i class="fas fa-plus me-1"></i>Agregar certificado</button></div>' +
            '</div></div></div>';
        cont.html(html);
        regimenesDelAgente();
    }

    function regimenesDelAgente() {
        var a = datos.agente[parseInt($('#rprov_cond_agente').val() || 0, 10)];
        var sel = $('#rprov_cond_regimen').empty().append('<option value="">Régimen: el de la config del agente</option>');
        (a ? a.regimenes : []).forEach(function (r) {
            sel.append($('<option>').val(r.regimen_id).text(r.codigo + ' - ' + r.regimen));
        });
    }

    function cargar() {
        var id = entidad();
        if (!(id > 0)) { cont.empty(); return; }
        cont.html('<div class="small text-muted">Cargando…</div>');
        $.getJSON(URL_AJAX, { accion: 'datos', entidad_id: id }, function (r) {
            if (!r || !r.success) { cont.html('<div class="small text-danger">' + esc((r && r.message) || 'No se pudieron leer las retenciones.') + '</div>'); return; }
            datos = r.data;
            render();
        });
    }

    function enviar(datosPost) {
        datosPost.entidad_id = entidad();
        $.post(URL_AJAX, datosPost, function (r) {
            if (r && r.success) {
                Swal.fire({ icon: 'success', title: r.message, toast: true, position: 'top-end', timer: 1800, showConfirmButton: false });
                cargar();
            } else {
                Swal.fire('Atención', (r && r.message) || 'No se pudo guardar.', 'warning');
            }
        }, 'json').fail(function () { Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error'); });
    }

    $('#modalHistorialFiscal').on('shown.bs.modal', cargar);
    $(document).on('change', '#rprov_cond_agente', regimenesDelAgente);
    $(document).on('click', '#rprov_cond_agregar', function () {
        var a = datos.agente[parseInt($('#rprov_cond_agente').val() || 0, 10)];
        if (!a) return;
        enviar({ accion: 'agregar_condicion', impuesto_tipo_id: a.impuesto_tipo_id, jurisdiccion_id: a.jurisdiccion_id || '',
                 condicion: $('#rprov_cond_condicion').val(), regimen_id: $('#rprov_cond_regimen').val(),
                 f_desde: $('#rprov_cond_desde').val(), f_hasta: $('#rprov_cond_hasta').val() });
    });
    $(document).on('click', '#rprov_exc_agregar', function () {
        var a = datos.agente[parseInt($('#rprov_exc_agente').val() || 0, 10)];
        if (!a) return;
        enviar({ accion: 'agregar_exclusion', impuesto_tipo_id: a.impuesto_tipo_id, jurisdiccion_id: a.jurisdiccion_id || '',
                 porcentaje_exclusion: $('#rprov_exc_pct').val(), numero_certificado: $('#rprov_exc_cert').val(),
                 f_desde: $('#rprov_exc_desde').val(), f_hasta: $('#rprov_exc_hasta').val() });
    });
    $(document).on('click', '.rprov-baja', function (e) {
        e.preventDefault();
        var tipo = $(this).data('tipo'), id = $(this).data('id');
        Swal.fire({ title: '¿Inhabilitar?', icon: 'question', showCancelButton: true, confirmButtonText: 'Sí', cancelButtonText: 'Cancelar' })
            .then(function (res) { if (res.isConfirmed) enviar({ accion: 'inhabilitar_' + tipo, id: id }); });
    });
});
