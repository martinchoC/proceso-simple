$(document).ready(function () {
    var tabla = $('#tablaVencimientos').DataTable({
        ajax: {
            url: 'vencimientos_ajax.php',
            data: function (d) {
                d.accion = 'listar';
                d.periodo = $('#filtroPeriodo').val();
            }
        },
        columns: [
            { data: 'periodo', render: function (data) { return '<strong>' + data + '</strong>'; } },
            {
                data: 'terminacion_cuit', className: 'text-center', render: function (data) {
                    return '<span class="badge bg-dark rounded-pill px-3 py-2 fs-6">' + data + '</span>';
                }
            },
            { data: 'tipo_vencimiento' },
            {
                data: 'f_vto', render: function (data) {
                    if (!data) return '-';
                    var parts = data.split('-');
                    return '<span class="fw-semibold text-primary"><i class="fas fa-calendar-day me-1"></i> ' + parts[2] + '/' + parts[1] + '/' + parts[0] + '</span>';
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
        order: [[0, 'desc'], [1, 'asc']]
    });

    // Cargar botón agregar
    $.get('vencimientos_ajax.php?accion=obtener_boton_agregar', function (res) {
        if (res && res.nombre_funcion) {
            $('#contenedor-boton-agregar').html(
                '<button type="button" class="btn btn-primary btn-sm" id="btnNuevoVto">' +
                '<i class="fas fa-plus me-1"></i> ' + res.nombre_funcion +
                '</button>'
            );
        }
    }, 'json');

    $(document).on('click', '#btnNuevoVto', function () {
        $('#formVencimiento')[0].reset();
        $('#vencimiento_id').val('');
        $('#modalVencimientoTitle').html('<i class="fas fa-calendar-check me-2 text-primary"></i>Nuevo Vencimiento');
        $('#modalVencimiento').modal('show');
    });

    $('#btnGenerarPeriodo').click(function () {
        $('#formGenerarPeriodo')[0].reset();
        $('#modalGenerarPeriodo').modal('show');
    });

    $('#tablaVencimientos').on('click', '.btn-accion', function () {
        var btn = $(this);
        var accion = btn.data('accion');
        var id = btn.data('id');

        if (accion === 'editar') {
            $.get('vencimientos_ajax.php?accion=obtener&id=' + id, function (res) {
                if (res.success && res.data) {
                    var d = res.data;
                    $('#vencimiento_id').val(d.vencimiento_id);
                    $('#periodo').val(d.periodo);
                    $('#terminacion_cuit').val(d.terminacion_cuit);
                    $('#tipo_vencimiento').val(d.tipo_vencimiento);
                    $('#f_vto').val(d.f_vto);
                    $('#modalVencimientoTitle').html('<i class="fas fa-edit me-2 text-primary"></i>Editar Vencimiento');
                    $('#modalVencimiento').modal('show');
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
                    $.post('vencimientos_ajax.php?accion=ejecutar_accion', { id: id, accion_js: accion }, function (res) {
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

    $('#formVencimiento').on('submit', function (e) {
        e.preventDefault();
        var btn = $('#btnGuardarVto');
        var orig = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Guardando...');

        $.ajax({
            url: 'vencimientos_ajax.php?accion=guardar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html(orig);
                if (res.success) {
                    $('#modalVencimiento').modal('hide');
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

    $('#formGenerarPeriodo').on('submit', function (e) {
        e.preventDefault();
        var btn = $('#btnSubmitGenerar');
        var orig = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Generando...');

        $.ajax({
            url: 'vencimientos_ajax.php?accion=generar_periodo',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html(orig);
                if (res.success) {
                    $('#modalGenerarPeriodo').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Cronograma Generado', text: res.message, timer: 2000, showConfirmButton: false });
                    $('#filtroPeriodo').val($('#gen_periodo').val());
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

    $('#filtroPeriodo').on('input', function () {
        tabla.ajax.reload();
    });

    $('#btnRecargar').click(function () {
        tabla.ajax.reload();
    });
});
