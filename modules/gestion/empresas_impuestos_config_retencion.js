// Impuestos Adic. - Conf: solapa "Agente de Retención" (cómo aplica la empresa cada retención que practica en las OP).
// Se muestra en lugar de "Agente de Percepción" cuando el tipo de impuesto es una retención de compras.
$(function () {
    function configId() { return parseInt($('#empresa_impuesto_config_id').val() || 0, 10); }
    function esRetencionCompra() { return $('#impuesto_tipo_id option:selected').data('retencion-compra') == 1; }

    function alternarSolapas() {
        var ret = esRetencionCompra();
        $('#retencionTabItem').toggleClass('d-none', !ret);
        $('#agente-tab').closest('li').toggleClass('d-none', ret);
        if (ret && $('#agente-tab').hasClass('active')) $('#configuracion-tab').tab('show');
        if (!ret && $('#retencion-tab').hasClass('active')) $('#configuracion-tab').tab('show');
    }

    function alternarCampos() {
        $('.ret-solo-padron').toggleClass('d-none', $('#ret_tipo_calculo').val() !== 'padron');
    }

    function cargarRetencion() {
        var id = configId();
        $('#retencionSinConfig').toggleClass('d-none', id > 0);
        $('#retencionContenido').toggleClass('d-none', !(id > 0));
        if (!(id > 0)) return;
        $.getJSON('empresas_impuestos_config_ajax.php', { accion: 'obtener_retencion', config_id: id }, function (r) {
            if (!r || !r.success) { Swal.fire('Error', (r && r.message) || 'No se pudieron leer los datos del agente.', 'error'); return; }
            var d = r.data;
            var sel = $('#ret_base_calculo').empty();
            $.each(d.bases, function (codigo, nombre) { sel.append($('<option>').val(codigo).text(nombre)); });
            sel.val(d.base_calculo);
            var selReg = $('#ret_regimen_id').empty().append('<option value="">Sin régimen: base, alícuota genérica y mínimos de abajo</option>');
            (d.regimenes || []).forEach(function (r) { selReg.append($('<option>').val(r.regimen_id).text(r.codigo + ' - ' + r.regimen)); });
            selReg.val(d.regimen_id || '');
            $('#ret_numero_agente').val(d.numero_agente || '');
            $('#ret_tipo_calculo').val(d.tipo_calculo);
            $('#ret_alicuota').val(d.alicuota || '');
            $('#ret_sin_dato_accion').val(d.sin_dato_accion);
            $('#ret_minimo_imponible').val(d.minimo_imponible || 0);
            $('#ret_importe_minimo').val(d.importe_minimo || 0);
            $('#ret_aplica_siempre').val(String(d.aplica_siempre ? 1 : 0));
            $('.ret-solo-iibb').toggleClass('d-none', !d.es_iibb);
            $('#retencionSinCuenta').toggleClass('d-none', !!d.tiene_cuenta);
            alternarCampos();
        });
    }

    $(document).on('change', '#impuesto_tipo_id', alternarSolapas);
    $('#modalImpuestoConfig').on('shown.bs.modal', alternarSolapas);
    $(document).on('shown.bs.tab', '#retencion-tab', cargarRetencion);
    $(document).on('change', '#ret_tipo_calculo', alternarCampos);

    $(document).on('click', '#btnGuardarRetencion', function () {
        var id = configId();
        if (!(id > 0)) return;
        var btn = $(this).prop('disabled', true);
        var datos = {
            accion: 'guardar_retencion', config_id: id,
            numero_agente: $('#ret_numero_agente').val(), base_calculo: $('#ret_base_calculo').val(),
            tipo_calculo: $('#ret_tipo_calculo').val(), alicuota: $('#ret_alicuota').val() || 0,
            sin_dato_accion: $('#ret_sin_dato_accion').val(), minimo_imponible: $('#ret_minimo_imponible').val() || 0,
            importe_minimo: $('#ret_importe_minimo').val() || 0, aplica_siempre: $('#ret_aplica_siempre').val(),
            regimen_id: $('#ret_regimen_id').val()
        };
        $.post('empresas_impuestos_config_ajax.php', datos, function (r) {
            if (r && r.success) {
                // La solapa General guarda las mismas columnas: se sincronizan para que "Guardar Configuración" no las pise
                $('#tipo_calculo').val(datos.tipo_calculo);
                $('#aplica_siempre').prop('checked', datos.aplica_siempre == 1);
                Swal.fire({ icon: 'success', title: r.message, toast: true, position: 'top-end', timer: 3500, showConfirmButton: false });
            } else {
                Swal.fire('Error', (r && r.message) || 'No se pudo guardar.', 'error');
            }
        }, 'json').fail(function () { Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error'); })
          .always(function () { btn.prop('disabled', false); });
    });
});
