/**
 * JavaScript: Plan de Cuentas Jerárquico
 * Módulo 4: Estudio Contable (contabilidad_plan.js)
 */

$(document).ready(function() {
    let tablaPlan = null;
    let listaCuentas = [];

    function initTablaPlan() {
        const domConfig = '<"row align-items-center p-2 mb-0"<"col-12 col-md-6"l><"col-12 col-md-6 text-end"f>>' +
                          '<"row"<"col-12"tr>>' +
                          '<"row align-items-center p-2 mt-2"<"col-12 col-md-5"i><"col-12 col-md-7 d-flex justify-content-end"p>>';

        tablaPlan = $('#tablaPlan').DataTable({
            responsive: true,
            dom: domConfig,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json',
                emptyTable: 'No hay cuentas registradas para esta empresa'
            },
            order: [[0, 'asc']],
            columns: [
                { 
                    data: 'codigo',
                    className: 'fw-bold'
                },
                { 
                    data: 'nombre',
                    render: function(d, t, r) {
                        const indent = '&nbsp;&nbsp;'.repeat(Math.max(0, (r.nivel || 1) - 1));
                        const icon = r.es_imputable == 1 ? '<i class="fas fa-file-alt text-muted me-1"></i>' : '<i class="fas fa-folder text-warning me-1"></i>';
                        return `${indent}${icon}${d}`;
                    }
                },
                { 
                    data: 'naturaleza',
                    render: function(d) {
                        return `<span class="badge bg-light text-dark border">${d}</span>`;
                    }
                },
                { 
                    data: 'nivel',
                    className: 'text-center'
                },
                { 
                    data: 'cuenta_padre_nombre',
                    render: (d, t, r) => d ? `[${r.cuenta_padre_codigo}] ${d}` : '<span class="text-muted small">Raíz</span>'
                },
                { 
                    data: 'es_imputable',
                    className: 'text-center',
                    render: d => d == 1 ? '<span class="badge bg-success">Imputable</span>' : '<span class="badge bg-secondary">Sintética</span>'
                },
                { 
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function(d) {
                        return `
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary btn-sm btn-editar" data-id="${d.cont_cuenta_id}" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm btn-anular" data-id="${d.cont_cuenta_id}" title="Eliminar">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        `;
                    }
                }
            ],
            initComplete: function() {
                new $.fn.dataTable.Buttons(tablaPlan, {
                    buttons: [
                        { extend: 'copyHtml5', title: 'Plan_De_Cuentas' },
                        { extend: 'excelHtml5', title: 'Plan_De_Cuentas' },
                        { extend: 'csvHtml5', title: 'Plan_De_Cuentas' },
                        { extend: 'pdfHtml5', title: 'Plan_De_Cuentas' },
                        { extend: 'print', title: 'Plan_De_Cuentas' }
                    ]
                });
            }
        });

        $('#btnPlanRecargar').on('click', () => cargarPlan());
        $('#btnPlanCopiar').on('click', () => tablaPlan.button('.buttons-copy').trigger());
        $('#btnPlanExcel').on('click', () => tablaPlan.button('.buttons-excel').trigger());
        $('#btnPlanPdf').on('click', () => tablaPlan.button('.buttons-pdf').trigger());
        $('#btnPlanCsv').on('click', () => tablaPlan.button('.buttons-csv').trigger());
        $('#btnPlanImprimir').on('click', () => tablaPlan.button('.buttons-print').trigger());
    }

    initTablaPlan();

    function cargarPlan() {
        const empresaId = $('#selEmpresaPlan').val();
        $('#cta_empresa_id').val(empresaId);

        $.ajax({
            url: 'contabilidad_plan_ajax.php',
            type: 'GET',
            data: { accion: 'listar', empresa_id: empresaId },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    listaCuentas = resp.data || [];
                    tablaPlan.clear().rows.add(listaCuentas).draw();
                    poblarCuentasPadre();
                }
            }
        });
    }

    function poblarCuentasPadre() {
        let html = '<option value="0">-- Sin Cuenta Padre (Raíz) --</option>';
        listaCuentas.filter(x => x.es_imputable == 0).forEach(function(c) {
            html += `<option value="${c.cont_cuenta_id}">[${c.codigo}] ${c.nombre}</option>`;
        });
        $('#cta_padre').html(html);
    }

    cargarPlan();

    $('#selEmpresaPlan').on('change', function() {
        cargarPlan();
    });

    $('#btnNuevaCuenta').on('click', function() {
        $('#formCuenta')[0].reset();
        $('#cta_id').val(0);
        $('#modalCuentaLabel').html('<i class="fas fa-book me-1"></i>Nueva Cuenta Contable');
        $('#modalCuenta').modal('show');
    });

    // Guardar
    $('#formCuenta').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        $('#btnGuardarCuenta').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: 'contabilidad_plan_ajax.php?accion=guardar',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(resp) {
                $('#btnGuardarCuenta').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Cuenta');
                if (resp.success) {
                    $('#modalCuenta').modal('hide');
                    Swal.fire('Guardado', 'Cuenta contable guardada con éxito.', 'success');
                    cargarPlan();
                } else {
                    Swal.fire('Error', resp.message || 'No se pudo guardar la cuenta.', 'error');
                }
            },
            error: function() {
                $('#btnGuardarCuenta').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Cuenta');
                Swal.fire('Error', 'Fallo al procesar la solicitud.', 'error');
            }
        });
    });

    // Editar
    $(document).on('click', '.btn-editar', function() {
        const id = $(this).data('id');
        const c = listaCuentas.find(x => x.cont_cuenta_id == id);
        if (c) {
            $('#cta_id').val(c.cont_cuenta_id);
            $('#cta_codigo').val(c.codigo);
            $('#cta_nombre').val(c.nombre);
            $('#cta_naturaleza').val(c.naturaleza);
            $('#cta_padre').val(c.cuenta_padre_id || 0);
            $('#cta_imputable').val(c.es_imputable);

            $('#modalCuentaLabel').html('<i class="fas fa-edit me-1"></i>Editar Cuenta Contable');
            $('#modalCuenta').modal('show');
        }
    });

    // Anular
    $(document).on('click', '.btn-anular', function() {
        const id = $(this).data('id');
        const empresaId = $('#selEmpresaPlan').val();

        Swal.fire({
            title: '¿Eliminar cuenta contable?',
            text: 'Solo se podrá eliminar si no tiene asientos contables registrados.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'contabilidad_plan_ajax.php',
                    type: 'POST',
                    data: { accion: 'anular', cont_cuenta_id: id, empresa_id: empresaId },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.success) {
                            Swal.fire('Eliminada', 'Cuenta dada de baja.', 'success');
                            cargarPlan();
                        } else {
                            Swal.fire('Atención', resp.message || 'No se pudo eliminar.', 'warning');
                        }
                    }
                });
            }
        });
    });
});
