$(document).ready(function () {
    var tabla = $('#tablaMediosPago').DataTable({
        ajax: 'medios_pago_ajax.php?accion=listar',
        columns: [
            { data: 'orden', className: 'text-center' },
            { data: 'codigo', render: function (data) { return '<strong>' + data + '</strong>'; } },
            { data: 'nombre' },
            { data: 'driver', render: function (data) { return '<span class="badge bg-secondary">' + data + '</span>'; } },
            {
                data: 'ambiente', render: function (data) {
                    var cls = data === 'PRODUCCION' ? 'bg-success' : 'bg-warning text-dark';
                    return '<span class="badge ' + cls + '">' + data + '</span>';
                }
            },
            {
                data: 'publica_deuda', render: function (data) {
                    return parseInt(data) === 1
                        ? '<span class="text-success"><i class="fas fa-check-circle"></i> Sí</span>'
                        : '<span class="text-muted"><i class="fas fa-times-circle"></i> No</span>';
                }
            },
            { data: 'rendicion_modo' },
            {
                data: 'tabla_estado_registro_id', render: function (data, type, row) {
                    var cls = parseInt(data) === 1 ? 'bg-success' : 'bg-danger';
                    return '<span class="badge ' + cls + '">' + (row.estado_registro || 'Activo') + '</span>';
                }
            },
            { data: 'acciones', className: 'text-end', orderable: false }
        ],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
        },
        order: [[0, 'asc']]
    });

    // Cargar botón agregar
    $.get('medios_pago_ajax.php?accion=obtener_boton_agregar', function (res) {
        if (res && res.nombre_funcion) {
            $('#contenedor-boton-agregar').html(
                '<button type="button" class="btn btn-primary btn-sm" id="btnNuevoMP">' +
                '<i class="fas fa-plus me-1"></i> ' + res.nombre_funcion +
                '</button>'
            );
        }
    }, 'json');

    // Nuevo medio de pago
    $(document).on('click', '#btnNuevoMP', function () {
        $('#formMedioPago')[0].reset();
        $('#medio_pago_id').val('');
        $('#tabMedioPago button:first').tab('show');
        $('#modalMedioPago').modal('show');
    });

    // Botones de acción dinámica
    $('#tablaMediosPago').on('click', '.btn-accion', function () {
        var btn = $(this);
        var accion = btn.data('accion');
        var id = btn.data('id');

        if (accion === 'editar') {
            $.get('medios_pago_ajax.php?accion=obtener&id=' + id, function (res) {
                if (res.success && res.data) {
                    var d = res.data;
                    $('#medio_pago_id').val(d.medio_pago_id);
                    $('#codigo').val(d.codigo);
                    $('#nombre').val(d.nombre);
                    $('#orden').val(d.orden);
                    $('#driver').val(d.driver);
                    $('#ambiente').val(d.ambiente);
                    $('#barras_prefijo').val(d.barras_prefijo || '');
                    $('#tesoreria_cuenta_codigo').val(d.tesoreria_cuenta_codigo || '');

                    $('#publica_deuda').prop('checked', parseInt(d.publica_deuda) === 1);
                    $('#ente_acuerdo').val(d.ente_acuerdo || '');
                    $('#client_id').val(d.client_id || '');
                    $('#url_servicio').val(d.url_servicio || '');
                    $('#url_autenticacion').val(d.url_autenticacion || '');
                    $('#usuario').val(d.usuario || '');
                    $('#clave').val('');
                    $('#client_secret').val('');

                    $('#rendicion_modo').val(d.rendicion_modo || 'ARCHIVO_MANUAL');
                    $('#sftp_host').val(d.sftp_host || '');
                    $('#sftp_puerto').val(d.sftp_puerto || 22);
                    $('#sftp_usuario').val(d.sftp_usuario || '');
                    $('#sftp_clave').val('');
                    $('#sftp_carpeta_remota').val(d.sftp_carpeta_remota || '');
                    $('#sftp_patron_archivo').val(d.sftp_patron_archivo || '');

                    $('#tabMedioPago button:first').tab('show');
                    $('#modalMedioPago').modal('show');
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                }
            }, 'json');
        } else if (accion === 'probar_conexion') {
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            $.post('medios_pago_ajax.php?accion=probar_conexion', { id: id }, function (res) {
                btn.prop('disabled', false).html('<i class="fas fa-plug"></i> Probar Conexión');
                Swal.fire({
                    icon: res.success ? 'success' : 'error',
                    title: 'Prueba de Conexión',
                    text: res.message
                });
            }, 'json');
        } else {
            // Transición genérica
            Swal.fire({
                title: '¿Confirmar acción?',
                text: 'Se ejecutará la acción seleccionada.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'Cancelar'
            }).then(function (result) {
                if (result.isConfirmed) {
                    $.post('medios_pago_ajax.php?accion=ejecutar_accion', { id: id, accion_js: accion }, function (res) {
                        if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Operación realizada', text: res.message, timer: 1500, showConfirmButton: false });
                            tabla.ajax.reload();
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                        }
                    }, 'json');
                }
            });
        }
    });

    // Guardar formulario
    $('#formMedioPago').on('submit', function (e) {
        e.preventDefault();
        var btn = $('#btnGuardarMP');
        var origText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Guardando...');

        $.ajax({
            url: 'medios_pago_ajax.php?accion=guardar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html(origText);
                if (res.success) {
                    $('#modalMedioPago').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Guardado', text: res.message, timer: 1500, showConfirmButton: false });
                    tabla.ajax.reload();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                }
            },
            error: function () {
                btn.prop('disabled', false).html(origText);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error al comunicarse con el servidor.' });
            }
        });
    });

    $('#btnRecargar').click(function () {
        tabla.ajax.reload();
    });
});
