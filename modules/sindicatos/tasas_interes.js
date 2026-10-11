$(document).ready(function () {
    var tabla = $('#tablaTasas').DataTable({
        ajax: 'tasas_interes_ajax.php?accion=listar',
        columns: [
            {
                data: 'clase', render: function (data) {
                    return parseInt(data) === 1 ? '<span class="badge bg-secondary">Resarcitorio</span>' : 'Clase ' + data;
                }
            },
            {
                data: 'tasa_mensual', className: 'text-end', render: function (data) {
                    return '<strong class="text-danger">' + parseFloat(data).toFixed(4) + ' %</strong>';
                }
            },
            {
                data: 'f_desde', render: function (data) {
                    if (!data) return '-';
                    var p = data.split('-');
                    return p[2] + '/' + p[1] + '/' + p[0];
                }
            },
            {
                data: 'f_hasta', render: function (data) {
                    if (!data) return '-';
                    var p = data.split('-');
                    return p[2] + '/' + p[1] + '/' + p[0];
                }
            },
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
        order: [[2, 'desc']]
    });

    // Cargar botón agregar
    $.get('tasas_interes_ajax.php?accion=obtener_boton_agregar', function (res) {
        if (res && res.nombre_funcion) {
            $('#contenedor-boton-agregar').html(
                '<button type="button" class="btn btn-primary btn-sm" id="btnNuevaTasa">' +
                '<i class="fas fa-plus me-1"></i> ' + res.nombre_funcion +
                '</button>'
            );
        }
    }, 'json');

    $(document).on('click', '#btnNuevaTasa', function () {
        $('#formTasa')[0].reset();
        $('#tasa_interes_id').val('');
        $('#modalTasaTitle').html('<i class="fas fa-percentage me-2 text-primary"></i>Nueva Tasa');
        $('#modalTasa').modal('show');
    });

    $('#tablaTasas').on('click', '.btn-accion', function () {
        var btn = $(this);
        var accion = btn.data('accion');
        var id = btn.data('id');

        if (accion === 'editar') {
            $.get('tasas_interes_ajax.php?accion=obtener&id=' + id, function (res) {
                if (res.success && res.data) {
                    var d = res.data;
                    $('#tasa_interes_id').val(d.tasa_interes_id);
                    $('#clase').val(d.clase);
                    $('#tasa_mensual').val(d.tasa_mensual);
                    $('#f_desde').val(d.f_desde);
                    $('#f_hasta').val(d.f_hasta);
                    $('#modalTasaTitle').html('<i class="fas fa-edit me-2 text-primary"></i>Editar Tasa');
                    $('#modalTasa').modal('show');
                }
            }, 'json');
        } else {
            Swal.fire({
                title: '¿Confirmar acción?',
                text: 'Se ejecutará la acción seleccionada.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'Cancelar'
            }).then(function (result) {
                if (result.isConfirmed) {
                    $.post('tasas_interes_ajax.php?accion=ejecutar_accion', { id: id, accion_js: accion }, function (res) {
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

    $('#formTasa').on('submit', function (e) {
        e.preventDefault();
        var btn = $('#btnGuardarTasa');
        var orig = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Guardando...');

        $.ajax({
            url: 'tasas_interes_ajax.php?accion=guardar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html(orig);
                if (res.success) {
                    $('#modalTasa').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Guardado', text: res.message, timer: 1500, showConfirmButton: false });
                    tabla.ajax.reload();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                }
            },
            error: function () {
                btn.prop('disabled', false).html(orig);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error al comunicarse con el servidor.' });
            }
        });
    });

    $('#btnRecargar').click(function () {
        tabla.ajax.reload();
    });
});
