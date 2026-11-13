/**
 * JavaScript: Catálogo de Conceptos sin Stock
 * Módulo 4: Estudio Contable (minigestion_conceptos.js)
 */

$(document).ready(function() {
    let tablaConceptos = null;
    let listaConceptos = [];

    function initTablaConceptos() {
        const domConfig = '<"row align-items-center p-2 mb-0"<"col-12 col-md-6"l><"col-12 col-md-6 text-end"f>>' +
                          '<"row"<"col-12"tr>>' +
                          '<"row align-items-center p-2 mt-2"<"col-12 col-md-5"i><"col-12 col-md-7 d-flex justify-content-end"p>>';

        tablaConceptos = $('#tablaConceptos').DataTable({
            responsive: true,
            dom: domConfig,
            language: {
                url: window.APP_ASSETS_URL + 'js/datatables-i18n-1.13.7/es-ES.json',
                emptyTable: 'No hay conceptos registrados para esta empresa'
            },
            order: [[0, 'asc']],
            columns: [
                { 
                    data: 'producto_codigo',
                    className: 'fw-bold'
                },
                { data: 'producto_nombre' },
                { 
                    data: 'es_servicio',
                    render: function(d) {
                        return d == 1 
                            ? '<span class="badge bg-info"><i class="fas fa-hand-holding-usd me-1"></i>Servicio</span>'
                            : '<span class="badge bg-secondary"><i class="fas fa-box me-1"></i>Bien</span>';
                    }
                },
                { 
                    data: 'cuenta_venta_nombre',
                    render: (d, t, r) => d ? `[${r.cuenta_venta_codigo}] ${d}` : '<span class="text-muted small">Por Defecto</span>'
                },
                { 
                    data: 'cuenta_compra_nombre',
                    render: (d, t, r) => d ? `[${r.cuenta_compra_codigo}] ${d}` : '<span class="text-muted small">Por Defecto</span>'
                },
                { 
                    data: 'controla_stock',
                    className: 'text-center',
                    render: () => '<span class="badge bg-light text-dark border">NO (Sin Stock)</span>'
                },
                { 
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function(d) {
                        return `
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary btn-sm btn-editar" data-funcion="editar" data-id="${d.producto_id}" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm btn-anular" data-funcion="inhabilitar" data-id="${d.producto_id}" title="Anular">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        `;
                    }
                }
            ],
            initComplete: function() {
                new $.fn.dataTable.Buttons(tablaConceptos, {
                    buttons: [
                        { extend: 'copyHtml5', title: 'Conceptos_MiniGestion' },
                        { extend: 'excelHtml5', title: 'Conceptos_MiniGestion' },
                        { extend: 'csvHtml5', title: 'Conceptos_MiniGestion' },
                        { extend: 'pdfHtml5', title: 'Conceptos_MiniGestion' },
                        { extend: 'print', title: 'Conceptos_MiniGestion' }
                    ]
                });
            }
        });

        $('#btnConceptosRecargar').on('click', () => cargarConceptos());
        $('#btnConceptosCopiar').on('click', () => tablaConceptos.button('.buttons-copy').trigger());
        $('#btnConceptosExcel').on('click', () => tablaConceptos.button('.buttons-excel').trigger());
        $('#btnConceptosPdf').on('click', () => tablaConceptos.button('.buttons-pdf').trigger());
        $('#btnConceptosCsv').on('click', () => tablaConceptos.button('.buttons-csv').trigger());
        $('#btnConceptosImprimir').on('click', () => tablaConceptos.button('.buttons-print').trigger());
    }

    initTablaConceptos();

    function cargarCuentasSelect() {
        const empresaId = $('#selEmpresaConcepto').val();
        $.ajax({
            url: 'minigestion_conceptos_ajax.php',
            type: 'GET',
            data: { accion: 'listar_cuentas', empresa_id: empresaId },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    let html = '<option value="0">-- Usar Cuenta Parametrizada por Defecto --</option>';
                    (resp.data || []).forEach(function(c) {
                        html += `<option value="${c.cont_cuenta_id}">[${c.codigo}] ${c.nombre}</option>`;
                    });
                    $('.select-cuenta-imputable').html(html);
                }
            }
        });
    }

    // Alícuotas de IVA de la empresa elegida (son por empresa). La del 21% queda como opción por defecto
    // del formulario (atributo selected), así el reset de "Nuevo" vuelve a ella.
    function cargarAlicuotasSelect() {
        const empresaId = $('#selEmpresaConcepto').val();
        $.ajax({
            url: 'minigestion_conceptos_ajax.php',
            type: 'GET',
            data: { accion: 'listar_alicuotas', empresa_id: empresaId },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    let html = '';
                    (resp.data || []).forEach(function(a) {
                        const defecto = parseInt(a.es_gravado, 10) === 1 && parseFloat(a.porcentaje) === 21;
                        html += `<option value="${a.iva_alicuota_id}"${defecto ? ' selected' : ''}>${a.iva_alicuota}</option>`;
                    });
                    $('#con_alicuota').html(html || '<option value="0">IVA 21%</option>');
                }
            }
        });
    }

    function cargarConceptos() {
        const empresaId = $('#selEmpresaConcepto').val();
        $('#con_empresa_id').val(empresaId);

        $.ajax({
            url: 'minigestion_conceptos_ajax.php',
            type: 'GET',
            data: { accion: 'listar', empresa_id: empresaId },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    listaConceptos = resp.data || [];
                    tablaConceptos.clear().rows.add(listaConceptos).draw();
                }
            }
        });
    }

    cargarCuentasSelect();
    cargarAlicuotasSelect();
    cargarConceptos();

    $('#selEmpresaConcepto').on('change', function() {
        cargarCuentasSelect();
        cargarAlicuotasSelect();
        cargarConceptos();
    });

    $('#btnNuevoConcepto').on('click', function() {
        $('#formConcepto')[0].reset();
        $('#con_id').val(0);
        $('#modalConceptoLabel').html('<i class="fas fa-box me-1"></i>Nuevo Concepto');
        $('#modalConcepto').modal('show');
    });

    // Guardar
    $('#formConcepto').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        $('#btnGuardarConcepto').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: 'minigestion_conceptos_ajax.php?accion=guardar',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(resp) {
                $('#btnGuardarConcepto').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Concepto');
                if (resp.success) {
                    $('#modalConcepto').modal('hide');
                    Swal.fire('Guardado', 'Concepto registrado con éxito.', 'success');
                    cargarConceptos();
                } else {
                    Swal.fire('Error', resp.message || 'No se pudo guardar.', 'error');
                }
            },
            error: function() {
                $('#btnGuardarConcepto').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Concepto');
                Swal.fire('Error', 'Fallo de conexión.', 'error');
            }
        });
    });

    // Editar
    $(document).on('click', '.btn-editar', function() {
        const id = $(this).data('id');
        const c = listaConceptos.find(x => x.producto_id == id);
        if (c) {
            $('#con_id').val(c.producto_id);
            $('#con_codigo').val(c.producto_codigo);
            $('#con_nombre').val(c.producto_nombre);
            $('#con_es_servicio').val(c.es_servicio);
            $('#con_alicuota').val(c.iva_alicuota_id);
            $('#con_cuenta_venta').val(c.cont_cuenta_venta_id || 0);
            $('#con_cuenta_compra').val(c.cont_cuenta_compra_id || 0);

            $('#modalConceptoLabel').html('<i class="fas fa-edit me-1"></i>Editar Concepto');
            $('#modalConcepto').modal('show');
        }
    });

    // Anular
    $(document).on('click', '.btn-anular', function() {
        const id = $(this).data('id');
        const empresaId = $('#selEmpresaConcepto').val();

        Swal.fire({
            title: '¿Anular concepto?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, anular',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'minigestion_conceptos_ajax.php',
                    type: 'POST',
                    data: { accion: 'anular', producto_id: id, empresa_id: empresaId },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.success) {
                            Swal.fire('Anulado', 'Concepto dado de baja.', 'success');
                            cargarConceptos();
                        } else {
                            Swal.fire('Error', resp.message || 'No se pudo anular.', 'error');
                        }
                    }
                });
            }
        });
    });
});
