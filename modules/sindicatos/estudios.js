$(document).ready(function () {
    var tabla = $('#tablaEstudios').DataTable({
        ajax: 'estudios_ajax.php?accion=listar',
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
            { data: 'matricula', render: function (data) { return data || '<span class="text-muted small">-</span>'; } },
            { data: 'contacto', render: function (data) { return data || '<span class="text-muted small">-</span>'; } },
            {
                data: 'entidad_email', render: function (data, type, row) {
                    var str = '';
                    if (data) str += '<a href="mailto:' + data + '" class="d-block small"><i class="fas fa-envelope me-1"></i>' + data + '</a>';
                    if (row.entidad_telefono) str += '<span class="small text-muted"><i class="fas fa-phone me-1"></i>' + row.entidad_telefono + '</span>';
                    return str || '<span class="text-muted small">-</span>';
                }
            },
            {
                data: 'vinculaciones_activas', className: 'text-center', render: function (data, type, row) {
                    return '<button type="button" class="btn btn-xs btn-outline-info btn-ver-vinculaciones" data-id="' + row.estudio_id + '" data-nombre="' + row.entidad_razon_social + '">' +
                        '<i class="fas fa-link me-1"></i> ' + data + ' emp.</button>';
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
    $.get('estudios_ajax.php?accion=obtener_boton_agregar', function (res) {
        if (res && res.nombre_funcion) {
            $('#contenedor-boton-agregar').html(
                '<button type="button" class="btn btn-primary btn-sm" id="btnNuevoEstudio">' +
                '<i class="fas fa-plus me-1"></i> ' + res.nombre_funcion +
                '</button>'
            );
        }
    }, 'json');

    $(document).on('click', '#btnNuevoEstudio', function () {
        $('#formEstudio')[0].reset();
        $('#estudio_id').val('');
        $('#cuit').prop('readonly', false);
        $('#modalEstudioTitle').html('<i class="fas fa-briefcase me-2 text-primary"></i>Nuevo Estudio Contable');
        $('#modalEstudio').modal('show');
    });

    $('#tablaEstudios').on('click', '.btn-accion', function () {
        var btn = $(this);
        var accion = btn.data('accion');
        var id = btn.data('id');

        if (accion === 'editar') {
            $.get('estudios_ajax.php?accion=obtener&id=' + id, function (res) {
                if (res.success && res.data) {
                    var e = res.data;
                    $('#estudio_id').val(e.estudio_id);
                    $('#cuit').val(e.entidad_cuit).prop('readonly', true);
                    $('#razon_social').val(e.entidad_razon_social);
                    $('#matricula').val(e.matricula || '');
                    $('#contacto').val(e.contacto || '');
                    $('#email').val(e.entidad_email || '');
                    $('#telefono').val(e.entidad_telefono || '');

                    $('#modalEstudioTitle').html('<i class="fas fa-edit me-2 text-primary"></i>Editar Estudio Contable');
                    $('#modalEstudio').modal('show');
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
                    $.post('estudios_ajax.php?accion=ejecutar_accion', { id: id, accion_js: accion }, function (res) {
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

    $('#formEstudio').on('submit', function (e) {
        e.preventDefault();
        var btn = $('#btnGuardarEst');
        var orig = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Guardando...');

        $.ajax({
            url: 'estudios_ajax.php?accion=guardar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html(orig);
                if (res.success) {
                    $('#modalEstudio').modal('hide');
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

    // Gestión de vinculaciones
    $('#tablaEstudios').on('click', '.btn-ver-vinculaciones', function () {
        var id = $(this).data('id');
        var nombre = $(this).data('nombre');
        $('#modal_estudio_id').val(id);
        $('#spanNombreEstudio').text(nombre);
        cargarVinculaciones(id);
        $('#modalVinculaciones').modal('show');
    });

    function cargarVinculaciones(estudioId) {
        var tbody = $('#tablaVinculaciones tbody');
        tbody.html('<tr><td colspan="5" class="text-center py-3"><i class="fas fa-spinner fa-spin text-muted"></i></td></tr>');

        $.get('estudios_ajax.php?accion=listar_vinculaciones&estudio_id=' + estudioId, function (res) {
            tbody.empty();
            if (res.success && res.data) {
                // Llenar combo de empleadores
                var combo = $('#combo_empleadores_vincular');
                combo.find('option:not(:first)').remove();
                if (res.data.empleadores) {
                    $.each(res.data.empleadores, function (i, emp) {
                        combo.append($('<option>', {
                            value: emp.empleador_id,
                            text: emp.entidad_cuit + ' - ' + emp.entidad_razon_social
                        }));
                    });
                }

                if (res.data.vinculaciones && res.data.vinculaciones.length > 0) {
                    $.each(res.data.vinculaciones, function (i, v) {
                        var cls = parseInt(v.tabla_estado_registro_id) === 1 ? 'bg-success' : (parseInt(v.tabla_estado_registro_id) === 4 ? 'bg-warning text-dark' : 'bg-danger');
                        tbody.append(
                            '<tr>' +
                            '<td><code>' + v.empleador_cuit + '</code></td>' +
                            '<td>' + v.empleador_nombre + '</td>' +
                            '<td><span class="badge bg-secondary">' + v.iniciado_por + '</span></td>' +
                            '<td>' + (v.f_solicitud || '').substring(0, 10) + '</td>' +
                            '<td><span class="badge ' + cls + '">' + (v.estado_registro || 'Activo') + '</span></td>' +
                            '</tr>'
                        );
                    });
                } else {
                    tbody.html('<tr><td colspan="5" class="text-center text-muted py-3">No hay empleadores vinculados a este estudio.</td></tr>');
                }
            }
        }, 'json');
    }

    $('#formNuevaVinculacion').on('submit', function (e) {
        e.preventDefault();
        var estId = $('#modal_estudio_id').val();
        var empId = $('#combo_empleadores_vincular').val();
        if (!empId) return;

        var btn = $('#btnVincularEmp');
        btn.prop('disabled', true);

        $.post('estudios_ajax.php?accion=vincular', {
            estudio_id: estId,
            empleador_id: empId
        }, function (res) {
            btn.prop('disabled', false);
            if (res.success) {
                cargarVinculaciones(estId);
                tabla.ajax.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    $('#btnRecargar').click(function () {
        tabla.ajax.reload();
    });
});
