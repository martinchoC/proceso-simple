$(document).ready(function () {
    function cargarParametros() {
        $.ajax({
            url: 'parametros_ajax.php?accion=obtener',
            type: 'GET',
            dataType: 'json',
            success: function (res) {
                if (!res.success) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.message || 'No se pudieron cargar los parámetros.'
                    });
                    return;
                }

                // Llenar combo de convenios
                var selectConv = $('#convenio_id_default');
                selectConv.find('option:not(:first)').remove();
                if (res.data.convenios && res.data.convenios.length > 0) {
                    $.each(res.data.convenios, function (i, c) {
                        selectConv.append($('<option>', {
                            value: c.convenio_id,
                            text: c.codigo + ' - ' + c.nombre
                        }));
                    });
                }

                var p = res.data.parametros;
                if (p) {
                    $('#parametro_id').val(p.parametro_id);
                    $('#portal_slug').val(p.portal_slug || '');
                    $('#convenio_id_default').val(p.convenio_id_default || '');
                    $('#dias_gracia').val(p.dias_gracia || 0);
                    $('#email_avisos').val(p.email_avisos || '');
                    $('#aprobacion_autoalta').prop('checked', parseInt(p.aprobacion_autoalta) === 1);

                    $('#media_jornada_piso').val(p.media_jornada_piso || 'COMPLETO');
                    $('#media_jornada_factor').val(p.media_jornada_factor || '0.50');

                    $('#smtp_host').val(p.smtp_host || '');
                    $('#smtp_puerto').val(p.smtp_puerto || 587);
                    $('#smtp_seguridad').val(p.smtp_seguridad || 'tls');
                    $('#smtp_usuario').val(p.smtp_usuario || '');
                    if (p.tiene_smtp_clave) {
                        $('#smtp_clave_estado').html('<span class="text-success"><i class="fas fa-check-circle"></i> Clave configurada</span>');
                    } else {
                        $('#smtp_clave_estado').html('<span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Sin clave</span>');
                    }

                    $('#correo_ingreso_host').val(p.correo_ingreso_host || '');
                    $('#correo_ingreso_puerto').val(p.correo_ingreso_puerto || 993);
                    $('#correo_ingreso_protocolo').val(p.correo_ingreso_protocolo || 'imap');
                    $('#correo_ingreso_usuario').val(p.correo_ingreso_usuario || '');
                    if (p.tiene_correo_ingreso_clave) {
                        $('#correo_ingreso_clave_estado').html('<span class="text-success"><i class="fas fa-check-circle"></i> Clave configurada</span>');
                    } else {
                        $('#correo_ingreso_clave_estado').html('<span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Sin clave</span>');
                    }

                    toggleFactorMediaJornada();
                }
            },
            error: function () {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'No se pudo conectar con el servidor.'
                });
            }
        });
    }

    function toggleFactorMediaJornada() {
        if ($('#media_jornada_piso').val() === 'PROPORCIONAL') {
            $('#bloque_factor_media_jornada').slideDown();
        } else {
            $('#bloque_factor_media_jornada').slideUp();
        }
    }

    $('#media_jornada_piso').change(toggleFactorMediaJornada);

    $('#formParametros').on('submit', function (e) {
        e.preventDefault();
        var btn = $('#btnGuardar');
        var origHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Guardando...');

        $.ajax({
            url: 'parametros_ajax.php?accion=guardar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html(origHtml);
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Parámetros guardados',
                        text: res.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                    cargarParametros();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.message
                    });
                }
            },
            error: function () {
                btn.prop('disabled', false).html(origHtml);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de comunicación',
                    text: 'Ocurrió un error al enviar los datos.'
                });
            }
        });
    });

    cargarParametros();
});
