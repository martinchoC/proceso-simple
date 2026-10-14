$(document).ready(function () {
    var tabla = $('#tablaConceptos').DataTable({
        ajax: 'conceptos_ajax.php?accion=listar',
        columns: [
            { data: 'codigo', render: function (data) { return '<strong>' + data + '</strong>'; } },
            { data: 'descripcion' },
            { data: 'convenio_codigo', render: function (data) { return '<span class="badge bg-secondary">' + (data || '-') + '</span>'; } },
            { data: 'base_calculo' },
            {
                data: 'porcentaje', className: 'text-end', render: function (data) {
                    return parseFloat(data) > 0 ? parseFloat(data).toFixed(2) + ' %' : '-';
                }
            },
            {
                data: 'solo_afiliados', className: 'text-center', render: function (data) {
                    return parseInt(data) === 1
                        ? '<span class="text-primary"><i class="fas fa-check-circle"></i> Sí</span>'
                        : '<span class="text-muted"><i class="fas fa-times-circle"></i> No</span>';
                }
            },
            {
                data: 'aplica_piso_escala', className: 'text-center', render: function (data) {
                    return parseInt(data) === 1
                        ? '<span class="text-success"><i class="fas fa-check-circle"></i> Sí</span>'
                        : '<span class="text-muted"><i class="fas fa-times-circle"></i> No</span>';
                }
            },
            {
                data: 'incluye_sac', className: 'text-center', render: function (data) {
                    return parseInt(data) === 1
                        ? '<span class="text-info"><i class="fas fa-check-circle"></i> Sí</span>'
                        : '<span class="text-muted"><i class="fas fa-times-circle"></i> No</span>';
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
    $.get('conceptos_ajax.php?accion=obtener_boton_agregar', function (res) {
        if (res && res.nombre_funcion) {
            $('#contenedor-boton-agregar').html(
                '<button type="button" class="btn btn-primary btn-sm" id="btnNuevoConcepto">' +
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

    // Tipos de comprobante de deuda (signo +) y de reversión (signo -) de la empresa
    function poblarTipos(tipos, deuda, reversion) {
        var selD = $('#comprobante_tipo_id'), selR = $('#comprobante_tipo_reversion_id');
        selD.find('option:not(:first)').remove();
        selR.find('option:not(:first)').remove();
        $.each(tipos || [], function (i, t) {
            var sel = t.signo === '-' ? selR : selD;
            sel.append($('<option>', { value: t.comprobante_tipo_id, text: t.codigo + ' - ' + t.comprobante_tipo }));
        });
        selD.val(deuda || '');
        selR.val(reversion || '');
    }

    // La cuenta de recurso sigue al código mientras no se haya cambiado a mano
    $('#codigo').on('input', function () {
        var cta = $('#cuenta_concepto_codigo');
        if (!cta.data('manual')) {
            cta.val($(this).val() ? 'SINDI_APORTE_' + $(this).val().toUpperCase() : '');
        }
    });
    $('#cuenta_concepto_codigo').on('input', function () {
        $(this).data('manual', true);
    });

    $(document).on('click', '#btnNuevoConcepto', function () {
        $('#formConcepto')[0].reset();
        $('#concepto_id').val('');
        $('#cuenta_concepto_codigo').val('').data('manual', false);
        $('#modalConceptoTitle').html('<i class="fas fa-tag me-2 text-primary"></i>Nuevo Concepto');
        $.get('conceptos_ajax.php?accion=obtener&id=0', function (res) {
            if (res.data && res.data.convenios) {
                poblarConvenios(res.data.convenios, null);
            }
            poblarTipos(res.data ? res.data.tipos : [], null, null);
            $('#modalConcepto').modal('show');
        }, 'json');
    });

    $('#tablaConceptos').on('click', '.btn-accion', function () {
        var btn = $(this);
        var accion = btn.data('accion');
        var id = btn.data('id');

        if (accion === 'editar') {
            $.get('conceptos_ajax.php?accion=obtener&id=' + id, function (res) {
                if (res.success && res.data && res.data.concepto) {
                    var c = res.data.concepto;
                    poblarConvenios(res.data.convenios, c.convenio_id);

                    $('#concepto_id').val(c.concepto_id);
                    $('#convenio_id').val(c.convenio_id);
                    $('#codigo').val(c.codigo);
                    $('#descripcion').val(c.descripcion);
                    $('#base_calculo').val(c.base_calculo);
                    $('#porcentaje').val(c.porcentaje);
                    $('#valor_fijo').val(c.valor_fijo);
                    $('#solo_afiliados').prop('checked', parseInt(c.solo_afiliados) === 1);
                    $('#aplica_piso_escala').prop('checked', parseInt(c.aplica_piso_escala) === 1);
                    $('#incluye_sac').prop('checked', parseInt(c.incluye_sac) === 1);
                    $('#cuenta_concepto_codigo').val(c.cuenta_concepto_codigo).data('manual', true);
                    poblarTipos(res.data.tipos, c.comprobante_tipo_id, c.comprobante_tipo_reversion_id);
                    $('#f_desde').val(c.f_desde || '');
                    $('#f_hasta').val(c.f_hasta || '');

                    $('#modalConceptoTitle').html('<i class="fas fa-edit me-2 text-primary"></i>Editar Concepto');
                    $('#modalConcepto').modal('show');
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
                    $.post('conceptos_ajax.php?accion=ejecutar_accion', { id: id, accion_js: accion }, function (res) {
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

    $('#formConcepto').on('submit', function (e) {
        e.preventDefault();
        var btn = $('#btnGuardarConcepto');
        var origText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Guardando...');

        $.ajax({
            url: 'conceptos_ajax.php?accion=guardar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html(origText);
                if (res.success) {
                    $('#modalConcepto').modal('hide');
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
