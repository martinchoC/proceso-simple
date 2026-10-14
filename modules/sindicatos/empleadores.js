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
                data: 'sucursales_activas', className: 'text-center', render: function (data) {
                    return '<span class="badge bg-light text-dark border">' + (parseInt(data) || 0) + '</span>';
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
        ]
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
        $('#seccionSucursales').addClass('d-none');
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
                    $('#seccionSucursales').removeClass('d-none');
                    cargarSucursales(e.empleador_id);
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

    // ---------------------------------------------------------------
    // Sucursales del empleador (N° 1 = Casa Central, no se inhabilita)
    // ---------------------------------------------------------------
    var sucursalesCache = {};

    function esc(v) {
        return $('<div>').text(v == null ? '' : String(v)).html();
    }

    function cargarSucursales(empleadorId) {
        var tbody = $('#tablaSucursales tbody');
        tbody.html('<tr><td colspan="7" class="text-center text-muted small">Cargando...</td></tr>');
        $.get('empleadores_ajax.php', { accion: 'listar_sucursales', empleador_id: empleadorId }, function (res) {
            tbody.empty();
            sucursalesCache = {};
            if (!res.success || !res.data.length) {
                tbody.html('<tr><td colspan="7" class="text-center text-muted small">Sin sucursales.</td></tr>');
                return;
            }
            $.each(res.data, function (i, s) {
                sucursalesCache[s.sucursal_id] = s;
                var activa = parseInt(s.tabla_estado_registro_id) === 1;
                var central = parseInt(s.sucursal_numero) === 1;
                var contacto = [s.sucursal_contacto, s.sucursal_telefono, s.sucursal_email].filter(Boolean).map(esc).join('<br>');
                var acciones = '<button type="button" class="btn btn-sm btn-outline-primary me-1 btn-editar-suc" data-id="' + s.sucursal_id + '" title="Editar"><i class="fas fa-edit"></i></button>';
                if (!central) {
                    acciones += activa
                        ? '<button type="button" class="btn btn-sm btn-outline-danger btn-estado-suc" data-id="' + s.sucursal_id + '" data-estado="2" title="Inhabilitar"><i class="fas fa-ban"></i></button>'
                        : '<button type="button" class="btn btn-sm btn-outline-success btn-estado-suc" data-id="' + s.sucursal_id + '" data-estado="1" title="Habilitar"><i class="fas fa-check"></i></button>';
                }
                tbody.append(
                    '<tr' + (activa ? '' : ' class="text-muted"') + '>' +
                    '<td><span class="badge bg-secondary">' + esc(s.sucursal_numero) + '</span></td>' +
                    '<td>' + esc(s.sucursal_nombre) + (central ? ' <span class="badge bg-primary ms-1">Central</span>' : '') + '</td>' +
                    '<td class="small">' + (esc(s.sucursal_direccion) || '-') + '</td>' +
                    '<td class="small">' + (esc(s.localidad) || '-') + '</td>' +
                    '<td class="small">' + (contacto || '-') + '</td>' +
                    '<td><span class="badge ' + (activa ? 'bg-success' : 'bg-danger') + '">' + (activa ? 'Activa' : 'Inactiva') + '</span></td>' +
                    '<td class="text-end text-nowrap">' + acciones + '</td>' +
                    '</tr>'
                );
            });
        }, 'json');
    }

    function abrirSucursal(s) {
        $('#formSucursal')[0].reset();
        $('#suc_empleador_id').val($('#empleador_id').val());
        $('#sucursal_id').val(s ? s.sucursal_id : '');
        $('#suc_localidad_id').val(s && s.localidad_id ? s.localidad_id : '');
        if (s) {
            $('#sucursal_nombre').val(s.sucursal_nombre);
            $('#sucursal_direccion').val(s.sucursal_direccion || '');
            $('#suc_localidad_texto').val(s.localidad || '');
            $('#sucursal_telefono').val(s.sucursal_telefono || '');
            $('#sucursal_email').val(s.sucursal_email || '');
            $('#sucursal_contacto').val(s.sucursal_contacto || '');
        }
        $('#modalSucursalTitle').html('<i class="fas fa-store me-2 text-primary"></i>' +
            (s ? 'Sucursal N° ' + esc(s.sucursal_numero) : 'Nueva sucursal'));
        $('#modalSucursal').modal('show');
    }

    $('#btnNuevaSucursal').on('click', function () {
        abrirSucursal(null);
    });

    $('#tablaSucursales').on('click', '.btn-editar-suc', function () {
        abrirSucursal(sucursalesCache[$(this).data('id')]);
    });

    $('#tablaSucursales').on('click', '.btn-estado-suc', function () {
        var datos = {
            accion: 'estado_sucursal',
            empleador_id: $('#empleador_id').val(),
            sucursal_id: $(this).data('id'),
            estado: $(this).data('estado')
        };
        $.post('empleadores_ajax.php', datos, function (res) {
            if (res.success) {
                cargarSucursales(datos.empleador_id);
                tabla.ajax.reload(null, false);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    // Localidad: búsqueda por nombre o CP (el catálogo tiene ~23.000 localidades)
    var localidadesBuscadas = {};
    var timerLocalidad = null;
    $('#suc_localidad_texto').on('input', function () {
        var texto = $(this).val();
        $('#suc_localidad_id').val(localidadesBuscadas[texto] || '');
        clearTimeout(timerLocalidad);
        if (texto.length < 3 || localidadesBuscadas[texto]) return;
        timerLocalidad = setTimeout(function () {
            $.get('empleadores_ajax.php', { accion: 'buscar_localidades', q: texto }, function (res) {
                var lista = $('#listaLocalidades').empty();
                $.each(res.data || [], function (i, l) {
                    var etiqueta = l.localidad + ' (CP ' + l.cp + ')';
                    localidadesBuscadas[etiqueta] = l.localidad_id;
                    lista.append($('<option>', { value: etiqueta }));
                });
            }, 'json');
        }, 300);
    });

    $('#formSucursal').on('submit', function (e) {
        e.preventDefault();
        if ($('#suc_localidad_texto').val() && !$('#suc_localidad_id').val()) {
            Swal.fire({ icon: 'warning', title: 'Localidad', text: 'Elija la localidad de la lista.' });
            return;
        }
        var btn = $('#btnGuardarSucursal');
        btn.prop('disabled', true);
        $.post('empleadores_ajax.php?accion=guardar_sucursal', $(this).serialize(), function (res) {
            btn.prop('disabled', false);
            if (res.success) {
                $('#modalSucursal').modal('hide');
                Swal.fire({ icon: 'success', title: 'Guardado', text: res.message, timer: 1500, showConfirmButton: false });
                cargarSucursales($('#suc_empleador_id').val());
                tabla.ajax.reload(null, false);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json').fail(function () {
            btn.prop('disabled', false);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error al comunicarse con el servidor.' });
        });
    });
});
