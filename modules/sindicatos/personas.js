$(document).ready(function () {
    var tabla = $('#tablaPersonas').DataTable({
        ajax: 'personas_ajax.php?accion=listar',
        columns: [
            {
                data: 'cuil', render: function (data) {
                    if (!data) return '-';
                    var c = String(data);
                    if (c.length === 11) {
                        return '<code>' + c.substring(0, 2) + '-' + c.substring(2, 10) + '-' + c.substring(10) + '</code>';
                    }
                    return '<code>' + c + '</code>';
                }
            },
            {
                data: 'apellido', render: function (data, type, row) {
                    return '<strong>' + data + '</strong>, ' + (row.nombre || '');
                }
            },
            {
                data: 'documento_nro', render: function (data) {
                    return data ? 'DNI ' + data : '<span class="text-muted small">-</span>';
                }
            },
            {
                data: 'sexo', className: 'text-center', render: function (data) {
                    return '<span class="badge bg-light text-dark border">' + (data || 'M') + '</span>';
                }
            },
            {
                data: 'f_nacimiento', render: function (data) {
                    if (!data) return '-';
                    var p = data.split('-');
                    return p[2] + '/' + p[1] + '/' + p[0];
                }
            },
            {
                data: 'email', render: function (data, type, row) {
                    var str = '';
                    if (data) str += '<a href="mailto:' + data + '" class="d-block small"><i class="fas fa-envelope me-1"></i>' + data + '</a>';
                    if (row.telefono) str += '<span class="small text-muted"><i class="fas fa-phone me-1"></i>' + row.telefono + '</span>';
                    return str || '<span class="text-muted small">-</span>';
                }
            },
            {
                data: 'empleos_activos', className: 'text-center', render: function (data) {
                    return parseInt(data) > 0
                        ? '<span class="badge bg-success rounded-pill">' + data + ' activo' + (parseInt(data) > 1 ? 's' : '') + '</span>'
                        : '<span class="badge bg-secondary rounded-pill">0</span>';
                }
            },
            {
                data: 'tabla_estado_registro_id', render: function (data, type, row) {
                    var cls = parseInt(data) === 1 ? 'bg-success' : 'bg-danger';
                    return '<span class="badge ' + cls + '">' + (row.estado_registro || 'Activo') + '</span>';
                }
            },
            { data: 'acciones', className: 'text-end', orderable: false }
        ]
    });

    // Cargar botón agregar
    $.get('personas_ajax.php?accion=obtener_boton_agregar', function (res) {
        if (res && res.nombre_funcion) {
            $('#contenedor-boton-agregar').html(
                '<button type="button" class="btn btn-primary btn-sm" id="btnNuevaPersona">' +
                '<i class="fas fa-plus me-1"></i> ' + res.nombre_funcion +
                '</button>'
            );
        }
    }, 'json');

    $(document).on('click', '#btnNuevaPersona', function () {
        $('#formPersona')[0].reset();
        $('#persona_id').val('');
        $('#cuil').prop('readonly', false);
        $('#modalPersonaTitle').html('<i class="fas fa-user-plus me-2 text-primary"></i>Nueva Persona');
        $('#modalPersona').modal('show');
    });

    $('#tablaPersonas').on('click', '.btn-accion', function () {
        var btn = $(this);
        var accion = btn.data('accion');
        var id = btn.data('id');

        if (accion === 'editar') {
            $.get('personas_ajax.php?accion=obtener&id=' + id, function (res) {
                if (res.success && res.data) {
                    var p = res.data;
                    $('#persona_id').val(p.persona_id);
                    $('#cuil').val(p.cuil).prop('readonly', true);
                    $('#apellido').val(p.apellido);
                    $('#nombre').val(p.nombre);
                    $('#documento_tipo_id').val(p.documento_tipo_id || 1);
                    $('#documento_nro').val(p.documento_nro || '');
                    $('#sexo').val(p.sexo || 'M');
                    $('#f_nacimiento').val(p.f_nacimiento || '');
                    $('#email').val(p.email || '');
                    $('#telefono').val(p.telefono || '');
                    $('#direccion').val(p.direccion || '');

                    $('#modalPersonaTitle').html('<i class="fas fa-user-edit me-2 text-primary"></i>Editar Persona');
                    $('#modalPersona').modal('show');
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message });
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
                    $.post('personas_ajax.php?accion=ejecutar_accion', { id: id, accion_js: accion }, function (res) {
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

    $('#formPersona').on('submit', function (e) {
        e.preventDefault();
        var btn = $('#btnGuardarPer');
        var orig = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Guardando...');

        $.ajax({
            url: 'personas_ajax.php?accion=guardar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html(orig);
                if (res.success) {
                    $('#modalPersona').modal('hide');
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
