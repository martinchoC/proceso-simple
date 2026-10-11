$(document).ready(function () {
    var tabla = $('#tablaEmpleadores').DataTable({
        ajax: 'empleadores_ajax.php?accion=listar',
        columns: [
            {
                data: 'entidad_cuit', render: function (data) {
                    if (!data) return '-';
                    var c = String(data);
                    if (c.length === 11) {
                        return '<code>' + c.substring(0, 2) + '-' + c.substring(2, 10) + '-' + c.substring(10) + '</code>';
                    }
                    return '<code>' + c + '</code>';
                }
            },
            { data: 'entidad_razon_social', render: function (data) { return '<strong>' + data + '</strong>'; } },
            { data: 'rubro', render: function (data) { return data || '<span class="text-muted small">-</span>'; } },
            { data: 'convenio_codigo', render: function (data) { return '<span class="badge bg-secondary">' + (data || 'Sin CCT') + '</span>'; } },
            {
                data: 'entidad_email', render: function (data, type, row) {
                    var str = '';
                    if (data) str += '<a href="mailto:' + data + '" class="d-block small"><i class="fas fa-envelope me-1"></i>' + data + '</a>';
                    if (row.entidad_telefono) str += '<span class="small text-muted"><i class="fas fa-phone me-1"></i>' + row.entidad_telefono + '</span>';
                    return str || '<span class="text-muted small">-</span>';
                }
            },
            {
                data: 'mora_nombre', render: function (data, type, row) {
                    var color = row.mora_color || '#6c757d';
                    return '<span class="badge" style="background-color: ' + color + '; color: #fff;">' + (data || 'Puntual') + '</span>';
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
        }
    });

    // Cargar botón agregar
    $.get('empleadores_ajax.php?accion=obtener_boton_agregar', function (res) {
        if (res && res.nombre_funcion) {
            $('#contenedor-boton-agregar').html(
                '<button type="button" class="btn btn-primary btn-sm" id="btnNuevoEmp">' +
                '<i class="fas fa-plus me-1"></i> ' + res.nombre_funcion +
                '</button>'
            );
        }
    }, 'json');

    function poblarConvenios(convenios, seleccionado) {
        var sel = $('#convenio_id');
        sel.find('option:not(:first)').remove();
        if (convenios) {
            $.each(convenios, function (i, c) {
                sel.append($('<option>', {
                    value: c.convenio_id,
                    text: c.codigo + ' - ' + c.nombre,
                    selected: seleccionado && parseInt(seleccionado) === parseInt(c.convenio_id)
                }));
            });
        }
    }

    $(document).on('click', '#btnNuevoEmp', function () {
        $('#formEmpleador')[0].reset();
        $('#empleador_id').val('');
        $('#cuit').prop('readonly', false);
        $('#modalEmpleadorTitle').html('<i class="fas fa-building me-2 text-primary"></i>Nuevo Empleador');
        $.get('empleadores_ajax.php?accion=obtener&id=0', function (res) {
            if (res.data && res.data.convenios) {
                poblarConvenios(res.data.convenios, null);
            }
            $('#modalEmpleador').modal('show');
        }, 'json');
    });

    $('#tablaEmpleadores').on('click', '.btn-accion', function () {
        var btn = $(this);
        var accion = btn.data('accion');
        var id = btn.data('id');

        if (accion === 'editar') {
            $.get('empleadores_ajax.php?accion=obtener&id=' + id, function (res) {
                if (res.success && res.data && res.data.empleador) {
                    var e = res.data.empleador;
                    poblarConvenios(res.data.convenios, e.convenio_id);

                    $('#empleador_id').val(e.empleador_id);
                    $('#cuit').val(e.entidad_cuit).prop('readonly', true);
                    $('#razon_social').val(e.entidad_razon_social);
                    $('#rubro').val(e.rubro || '');
                    $('#convenio_id').val(e.convenio_id || '');
                    $('#email').val(e.entidad_email || '');
                    $('#telefono').val(e.entidad_telefono || '');
                    $('#domicilio').val(e.entidad_domicilio || '');
                    $('#f_alta_padron').val(e.f_alta_padron || '');

                    $('#modalEmpleadorTitle').html('<i class="fas fa-edit me-2 text-primary"></i>Editar Empleador');
                    $('#modalEmpleador').modal('show');
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
                    $.post('empleadores_ajax.php?accion=ejecutar_accion', { id: id, accion_js: accion }, function (res) {
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

    $('#formEmpleador').on('submit', function (e) {
        e.preventDefault();
        var btn = $('#btnGuardarEmp');
        var orig = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Guardando...');

        $.ajax({
            url: 'empleadores_ajax.php?accion=guardar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html(orig);
                if (res.success) {
                    $('#modalEmpleador').modal('hide');
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
