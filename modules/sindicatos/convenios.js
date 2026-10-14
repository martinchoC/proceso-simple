$(document).ready(function () {
    var categoriaActivaId = null;

    var tabla = $('#tablaConvenios').DataTable({
        ajax: 'convenios_ajax.php?accion=listar',
        columns: [
            { data: 'codigo', render: function (data) { return '<strong>' + data + '</strong>'; } },
            { data: 'nombre' },
            { data: 'descripcion', render: function (data) { return data || '<span class="text-muted small">-</span>'; } },
            {
                data: 'total_categorias', className: 'text-center', render: function (data, type, row) {
                    return '<button type="button" class="btn btn-xs btn-outline-info btn-ver-categorias" data-id="' + row.convenio_id + '" data-nombre="' + row.nombre + '">' +
                        '<i class="fas fa-layer-group me-1"></i> ' + data + ' cat.</button>';
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
    $.get('convenios_ajax.php?accion=obtener_boton_agregar', function (res) {
        if (res && res.nombre_funcion) {
            $('#contenedor-boton-agregar').html(
                '<button type="button" class="btn btn-primary btn-sm" id="btnNuevoConvenio">' +
                '<i class="fas fa-plus me-1"></i> ' + res.nombre_funcion +
                '</button>'
            );
        }
    }, 'json');

    $(document).on('click', '#btnNuevoConvenio', function () {
        $('#formConvenio')[0].reset();
        $('#convenio_id').val('');
        $('#modalConvenioTitle').html('<i class="fas fa-file-contract me-2 text-primary"></i>Nuevo Convenio');
        $('#modalConvenio').modal('show');
    });

    // Acciones de tabla
    $('#tablaConvenios').on('click', '.btn-accion', function () {
        var btn = $(this);
        var accion = btn.data('accion');
        var id = btn.data('id');

        if (accion === 'editar') {
            $.get('convenios_ajax.php?accion=obtener&id=' + id, function (res) {
                if (res.success && res.data) {
                    $('#convenio_id').val(res.data.convenio_id);
                    $('#codigo').val(res.data.codigo);
                    $('#nombre').val(res.data.nombre);
                    $('#descripcion').val(res.data.descripcion || '');
                    $('#modalConvenioTitle').html('<i class="fas fa-edit me-2 text-primary"></i>Editar Convenio');
                    $('#modalConvenio').modal('show');
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
                    $.post('convenios_ajax.php?accion=ejecutar_accion', { id: id, accion_js: accion }, function (res) {
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

    $('#formConvenio').on('submit', function (e) {
        e.preventDefault();
        var btn = $('#btnGuardarConvenio');
        var origText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Guardando...');

        $.ajax({
            url: 'convenios_ajax.php?accion=guardar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html(origText);
                if (res.success) {
                    $('#modalConvenio').modal('hide');
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

    // Sub-panel de Categorías y Escalas
    $('#tablaConvenios').on('click', '.btn-ver-categorias', function () {
        var convId = $(this).data('id');
        var convNombre = $(this).data('nombre');
        $('#modal_convenio_id').val(convId);
        $('#spanNombreConvenio').text(convNombre);
        $('#spanCategoriaSeleccionada').text('(Seleccionar categoría)');
        $('#btnNuevaEscala').hide();
        categoriaActivaId = null;
        cargarCategorias(convId);
        $('#modalCategorias').modal('show');
    });

    function cargarCategorias(convId) {
        var lista = $('#listaCategorias');
        lista.html('<div class="text-center py-3"><i class="fas fa-spinner fa-spin text-muted"></i></div>');

        $.get('convenios_ajax.php?accion=listar_categorias&convenio_id=' + convId, function (res) {
            lista.empty();
            if (res.success && res.data && res.data.length > 0) {
                $.each(res.data, function (i, cat) {
                    var item = $('<a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center item-categoria" data-id="' + cat.categoria_id + '" data-codigo="' + cat.codigo + '" data-nombre="' + cat.nombre + '">' +
                        '<div><strong>' + cat.codigo + '</strong> - ' + cat.nombre + '</div>' +
                        '<span class="badge bg-primary rounded-pill">' + cat.total_escalas + ' esc.</span>' +
                        '</a>');
                    lista.append(item);
                });
            } else {
                lista.html('<div class="text-center text-muted py-3">No hay categorías registradas en este convenio.</div>');
            }
        }, 'json');
    }

    $(document).on('click', '.item-categoria', function (e) {
        e.preventDefault();
        $('.item-categoria').removeClass('active');
        $(this).addClass('active');

        categoriaActivaId = $(this).data('id');
        var codigo = $(this).data('codigo');
        var nombre = $(this).data('nombre');

        $('#spanCategoriaSeleccionada').text(codigo + ' - ' + nombre);
        $('#btnNuevaEscala').show();
        cargarEscalas(categoriaActivaId);
    });

    function cargarEscalas(catId) {
        var tbody = $('#tablaEscalas tbody');
        tbody.html('<tr><td colspan="5" class="text-center py-3"><i class="fas fa-spinner fa-spin text-muted"></i></td></tr>');

        $.get('convenios_ajax.php?accion=listar_escalas&categoria_id=' + catId, function (res) {
            tbody.empty();
            if (res.success && res.data && res.data.length > 0) {
                $.each(res.data, function (i, esc) {
                    var basico = parseFloat(esc.basico || 0);
                    var rem1 = parseFloat(esc.rem_1 || 0);
                    var rem2 = parseFloat(esc.rem_2 || 0);
                    var total = basico + rem1 + rem2;

                    tbody.append(
                        '<tr>' +
                        '<td><strong>' + esc.periodo + '</strong></td>' +
                        '<td class="text-end">$' + basico.toFixed(2) + '</td>' +
                        '<td class="text-end">$' + rem1.toFixed(2) + '</td>' +
                        '<td class="text-end">$' + rem2.toFixed(2) + '</td>' +
                        '<td class="text-end fw-bold text-success">$' + total.toFixed(2) + '</td>' +
                        '</tr>'
                    );
                });
            } else {
                tbody.html('<tr><td colspan="5" class="text-center text-muted py-3">No hay escalas registradas para esta categoría.</td></tr>');
            }
        }, 'json');
    }

    // Modal prompt para nueva categoría
    $('#btnNuevaCategoria').click(function () {
        var convId = $('#modal_convenio_id').val();
        Swal.fire({
            title: 'Nueva Categoría',
            html:
                '<input id="swal-cat-codigo" class="swal2-input" placeholder="Código (ej: ADM_A)">' +
                '<input id="swal-cat-nombre" class="swal2-input" placeholder="Nombre (ej: Administrativo A)">',
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                var c = $('#swal-cat-codigo').val().trim();
                var n = $('#swal-cat-nombre').val().trim();
                if (!c || !n) {
                    Swal.showValidationMessage('Código y nombre son obligatorios');
                    return false;
                }
                return { codigo: c, nombre: n };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('convenios_ajax.php?accion=guardar_categoria', {
                    convenio_id: convId,
                    codigo: result.value.codigo,
                    nombre: result.value.nombre
                }, function (res) {
                    if (res.success) {
                        cargarCategorias(convId);
                        tabla.ajax.reload();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                    }
                }, 'json');
            }
        });
    });

    // Modal prompt para nueva escala
    $('#btnNuevaEscala').click(function () {
        if (!categoriaActivaId) return;

        Swal.fire({
            title: 'Cargar Escala Salarial',
            html:
                '<label class="text-start small fw-bold d-block">Período (AAAAMM):</label>' +
                '<input id="swal-esc-periodo" class="swal2-input mt-1" type="number" placeholder="ej: 202610">' +
                '<label class="text-start small fw-bold d-block mt-2">Básico:</label>' +
                '<input id="swal-esc-basico" class="swal2-input mt-1" type="number" step="0.01" placeholder="0.00">' +
                '<label class="text-start small fw-bold d-block mt-2">Remunerativo 1:</label>' +
                '<input id="swal-esc-rem1" class="swal2-input mt-1" type="number" step="0.01" placeholder="0.00">' +
                '<label class="text-start small fw-bold d-block mt-2">Remunerativo 2:</label>' +
                '<input id="swal-esc-rem2" class="swal2-input mt-1" type="number" step="0.01" placeholder="0.00">',
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Guardar Escala',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                var p = parseInt($('#swal-esc-periodo').val());
                if (!p || p < 200001 || p > 209912) {
                    Swal.showValidationMessage('Período inválido (AAAAMM)');
                    return false;
                }
                return {
                    periodo: p,
                    basico: $('#swal-esc-basico').val() || 0,
                    rem_1: $('#swal-esc-rem1').val() || 0,
                    rem_2: $('#swal-esc-rem2').val() || 0
                };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('convenios_ajax.php?accion=guardar_escala', {
                    categoria_id: categoriaActivaId,
                    periodo: result.value.periodo,
                    basico: result.value.basico,
                    rem_1: result.value.rem_1,
                    rem_2: result.value.rem_2
                }, function (res) {
                    if (res.success) {
                        cargarEscalas(categoriaActivaId);
                        cargarCategorias($('#modal_convenio_id').val());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                    }
                }, 'json');
            }
        });
    });

    $('#btnRecargar').click(function () {
        tabla.ajax.reload();
    });
});
