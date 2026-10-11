// Impuestos Adic. - Conf: solapa "Agente de Percepción" (datos del agente + alícuota por situación del cliente).
// Lee la config abierta en el modal desde #empresa_impuesto_config_id. docs/gestion/09_percepciones_iibb.md
$(function () {
    function esc(t) { return $('<div>').text(t == null ? '' : t).html(); }
    function configId() { return parseInt($('#empresa_impuesto_config_id').val() || 0, 10); }

    function renderSituaciones(filas) {
        var html = (filas || []).map(function (s) {
            return '<tr data-situacion="' + esc(s.situacion) + '">' +
                '<td class="text-center"><input type="checkbox" class="form-check-input ag-activa"' + (s.activa ? ' checked' : '') + '></td>' +
                '<td class="small">' + esc(s.nombre) + '</td>' +
                '<td><input type="number" step="0.0001" min="0" max="100" class="form-control form-control-sm ag-alicuota" value="' + (s.alicuota || 0) + '"' + (s.situacion === 'PADRON' ? ' disabled title="La da el padrón"' : '') + '></td>' +
                '<td class="text-center"><input type="checkbox" class="form-check-input ag-territorio"' + (s.requiere_territorio ? ' checked' : '') + '></td>' +
                '<td class="text-center"><input type="checkbox" class="form-check-input ag-declarar"' + (s.declarar_si_cero ? ' checked' : '') + '></td>' +
                '<td><input type="number" min="1" max="5" class="form-control form-control-sm ag-tipo-reg" value="' + (s.tipo_registro_ddjj == null ? '' : s.tipo_registro_ddjj) + '"></td>' +
                '<td><input type="text" maxlength="120" class="form-control form-control-sm ag-leyenda" value="' + esc(s.leyenda || '') + '"></td>' +
                '</tr>';
        }).join('');
        $('#tablaAgenteSituaciones tbody').html(html);
    }

    function cargarAgente() {
        var id = configId();
        $('#agenteSinConfig').toggleClass('d-none', id > 0);
        $('#agenteContenido').toggleClass('d-none', !(id > 0));
        if (!(id > 0)) return;
        $.getJSON('empresas_impuestos_config_ajax.php', { accion: 'obtener_agente', config_id: id }, function (r) {
            if (!r || !r.success) { Swal.fire('Error', (r && r.message) || 'No se pudieron leer los datos del agente.', 'error'); return; }
            var d = r.data;
            $('#agenteNoPercepcion').toggleClass('d-none', !!d.es_percepcion_iibb);
            $('#ag_numero_agente').val(d.numero_agente || '');
            $('#ag_criterio_territorial').val(d.criterio_territorial || 'ENTREGA');
            $('#ag_base_calculo').val(['NETO', 'NETO_GRAVADO', 'TOTAL'].indexOf(d.base_calculo) >= 0 ? d.base_calculo : 'NETO');
            $('#ag_minimo_imponible').val(d.minimo_imponible || 0);
            $('#ag_importe_minimo_percepcion').val(d.importe_minimo_percepcion || 0);
            $('#ag_arca_tributo_id').val(d.arca_tributo_id || '');
            $('#ag_codigo_jurisdiccion_cm').val(d.codigo_jurisdiccion_cm || '');
            $('#ag_formato_padron').val(d.formato_padron || '');
            $('#ag_formato_ddjj').val(d.formato_ddjj || '');
            $('#ag_codigo_regimen_ddjj').val(d.codigo_regimen_ddjj || '');
            renderSituaciones(d.situaciones);
        });
    }

    $(document).on('shown.bs.tab', '#agente-tab', cargarAgente);
    $('#modalImpuestoConfig').on('hidden.bs.modal', function () { $('#tablaAgenteSituaciones tbody').empty(); });

    $(document).on('click', '#btnGuardarAgente', function () {
        var id = configId();
        if (!(id > 0)) return;
        var situaciones = $('#tablaAgenteSituaciones tbody tr').map(function () {
            var tr = $(this);
            return {
                situacion: tr.data('situacion'),
                activa: tr.find('.ag-activa').is(':checked') ? 1 : 0,
                alicuota: tr.find('.ag-alicuota').val() || 0,
                requiere_territorio: tr.find('.ag-territorio').is(':checked') ? 1 : 0,
                declarar_si_cero: tr.find('.ag-declarar').is(':checked') ? 1 : 0,
                tipo_registro_ddjj: tr.find('.ag-tipo-reg').val(),
                leyenda: tr.find('.ag-leyenda').val()
            };
        }).get();
        var btn = $(this).prop('disabled', true);
        $.post('empresas_impuestos_config_ajax.php', {
            accion: 'guardar_agente', config_id: id,
            numero_agente: $('#ag_numero_agente').val(), criterio_territorial: $('#ag_criterio_territorial').val(),
            base_calculo: $('#ag_base_calculo').val(), minimo_imponible: $('#ag_minimo_imponible').val(),
            importe_minimo_percepcion: $('#ag_importe_minimo_percepcion').val(), arca_tributo_id: $('#ag_arca_tributo_id').val(),
            codigo_jurisdiccion_cm: $('#ag_codigo_jurisdiccion_cm').val(), formato_padron: $('#ag_formato_padron').val(),
            formato_ddjj: $('#ag_formato_ddjj').val(), codigo_regimen_ddjj: $('#ag_codigo_regimen_ddjj').val(),
            situaciones: JSON.stringify(situaciones)
        }, function (r) {
            if (r && r.success) Swal.fire({ icon: 'success', title: r.message, toast: true, position: 'top-end', timer: 1800, showConfirmButton: false });
            else Swal.fire('Error', (r && r.message) || 'No se pudo guardar.', 'error');
        }, 'json').fail(function () { Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error'); })
          .always(function () { btn.prop('disabled', false); });
    });
});
