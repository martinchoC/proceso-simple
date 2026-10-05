/**
 * JavaScript: Tarifarios y Conceptos de Honorarios
 * Módulo 4: Estudio Contable (honorarios_conceptos.js)
 */

$(document).ready(function () {
    let tablaAbonos = null;
    let tablaConceptos = null;
    let modalAbono = new bootstrap.Modal(document.getElementById('modalAbono'));
    let modalConcepto = new bootstrap.Modal(document.getElementById('modalConcepto'));

    cargarCombos();
    iniciarTablaAbonos();
    iniciarTablaConceptos();

    $('#filtroAbonoCliente').on('change', function () {
        if (tablaAbonos) tablaAbonos.ajax.reload();
    });

    function cargarCombos() {
        $.getJSON('honorarios_conceptos_ajax.php', { accion: 'obtener_combos' }, function (res) {
            if (!res.success) return;
            let d = res.data;

            // Clientes
            let optCli = '<option value="0">-- Todos los clientes con abono --</option>';
            let optCliModal = '<option value="">Seleccione cliente...</option>';
            d.clientes.forEach(c => {
                optCli += `<option value="${c.cliente_id}">${c.razon_social} (${c.cuit})</option>`;
                optCliModal += `<option value="${c.cliente_id}">${c.razon_social} (${c.cuit})</option>`;
            });
            $('#filtroAbonoCliente').html(optCli);
            $('#ma_cliente_id').html(optCliModal);

            // Conceptos
            let optCon = '<option value="">Seleccione concepto...</option>';
            d.conceptos.forEach(co => {
                optCon += `<option value="${co.concepto_id}">${co.nombre} (${co.tipo_cargo})</option>`;
            });
            $('#ma_concepto_id').html(optCon);

            // Puntos de Venta
            let optPv = '<option value="">Seleccione punto de venta...</option>';
            d.puntos_venta.forEach(pv => {
                optPv += `<option value="${pv.punto_venta_id}">PV ${pv.codigo_fiscal} - ${pv.nombre}</option>`;
            });
            $('#ma_punto_venta_id').html(optPv);

            // Condiciones de Pago
            let optCp = '';
            d.condiciones_pago.forEach(cp => {
                optCp += `<option value="${cp.condicion_pago_id}">${cp.condicion_pago}</option>`;
            });
            $('#ma_condicion_venta_id').html(optCp);

            // Productos ERP para concepto
            let optProd = '<option value="">Seleccione servicio del ERP...</option>';
            d.productos.forEach(p => {
                optProd += `<option value="${p.producto_id}">${p.producto_nombre} (${p.producto_codigo})</option>`;
            });
            $('#mc_producto_id').html(optProd);
        });
    }

    function iniciarTablaAbonos() {
        tablaAbonos = $('#tablaAbonos').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: 'honorarios_conceptos_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar_abonos';
                    d.cliente_id = $('#filtroAbonoCliente').val();
                },
                dataSrc: function (json) { return json.data || []; }
            },
            columns: [
                {
                    data: 'cliente_nombre',
                    render: function (data, type, row) {
                        return `<div><strong>${data}</strong></div><div class="text-muted fs-8">${row.cliente_cuit}</div>`;
                    }
                },
                {
                    data: 'concepto_nombre',
                    render: function (data, type, row) {
                        return `<div><strong>${data}</strong></div><span class="badge bg-secondary fs-8">${row.tipo_cargo}</span>`;
                    }
                },
                {
                    data: 'punto_venta_codigo',
                    render: function (data) {
                        return `<span class="badge bg-light text-dark border">PV ${data || 1}</span>`;
                    }
                },
                {
                    data: 'importe_base',
                    className: 'text-end',
                    render: function (data) {
                        let num = parseFloat(data || 0);
                        return `<strong class="text-primary fs-7">$ ${num.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>`;
                    }
                },
                {
                    data: 'dia_facturacion',
                    render: function (data) {
                        return `Día ${data}`;
                    }
                },
                {
                    data: null,
                    render: function (data, type, row) {
                        if (row.aplica_adicionales_automaticos == 1) {
                            return `<div class="fs-8 text-success"><i class="fas fa-check-circle me-1"></i>Habilitados</div>
                                    <div class="text-muted fs-8">Comp: >${row.tope_comprobantes_incluidos} ($${row.precio_excedente_comprobante})</div>
                                    <div class="text-muted fs-8">Leg: >${row.tope_legajos_incluidos} ($${row.precio_excedente_legajo})</div>`;
                        }
                        return '<span class="text-muted fs-8">Solo Abono Fijo</span>';
                    }
                },
                {
                    data: 'ultimo_periodo_facturado',
                    render: function (data) {
                        return data ? `<span class="badge bg-info text-white">${data}</span>` : '<span class="text-muted fs-8">Sin facturar</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        return `
                            <button type="button" class="btn btn-xs btn-outline-danger btn-eliminar-abono" data-id="${row.abono_id}" title="Dar de baja abono">
                                <i class="fas fa-trash"></i>
                            </button>
                        `;
                    }
                }
            ],
            language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
            dom: '<"row align-items-center p-2 mb-0"<"col-12 col-md-6"l><"col-12 col-md-6 text-end"f>>' +
                 '<"row"<"col-12"tr>>' +
                 '<"row align-items-center p-2 mt-2"<"col-12 col-md-5"i><"col-12 col-md-7 d-flex justify-content-end"p>>',
            initComplete: function () {
                new $.fn.dataTable.Buttons(tablaAbonos, {
                    buttons: [
                        { extend: 'copyHtml5', text: 'Copiar', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'excelHtml5', title: 'Abonos_Mensuales', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'csvHtml5', title: 'Abonos_Mensuales', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'pdfHtml5', title: 'Abonos_Mensuales', orientation: 'landscape', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'print', exportOptions: { columns: ':not(:last-child)' } }
                    ]
                });
            }
        });
    }

    $(document).on('click', '#btnRecargarAbonos', function () {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        tablaAbonos.ajax.reload(function () {
            btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
        }, false);
    });
    $(document).on('click', '#btnExportarCopyAbonos', function () { tablaAbonos.button('.buttons-copy').trigger(); });
    $(document).on('click', '#btnExportarExcelAbonos', function () { tablaAbonos.button('.buttons-excel').trigger(); });
    $(document).on('click', '#btnExportarPDFAbonos', function () { tablaAbonos.button('.buttons-pdf').trigger(); });
    $(document).on('click', '#btnExportarCSVAbonos', function () { tablaAbonos.button('.buttons-csv').trigger(); });
    $(document).on('click', '#btnExportarPrintAbonos', function () { tablaAbonos.button('.buttons-print').trigger(); });

    function iniciarTablaConceptos() {
        tablaConceptos = $('#tablaConceptos').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: 'honorarios_conceptos_ajax.php',
                type: 'GET',
                data: { accion: 'listar_conceptos' },
                dataSrc: function (json) { return json.data || []; }
            },
            columns: [
                { data: 'concepto_id' },
                {
                    data: 'nombre',
                    render: function (data) {
                        return `<strong>${data}</strong>`;
                    }
                },
                {
                    data: 'producto_nombre',
                    render: function (data, type, row) {
                        return `<div>${data}</div><div class="text-muted fs-8">Cód: ${row.producto_codigo}</div>`;
                    }
                },
                {
                    data: 'tipo_cargo',
                    render: function (data) {
                        return `<span class="badge bg-primary fs-8">${data}</span>`;
                    }
                },
                { data: 'descripcion' }
            ],
            language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
            dom: '<"row align-items-center p-2 mb-0"<"col-12 col-md-6"l><"col-12 col-md-6 text-end"f>>' +
                 '<"row"<"col-12"tr>>' +
                 '<"row align-items-center p-2 mt-2"<"col-12 col-md-5"i><"col-12 col-md-7 d-flex justify-content-end"p>>',
            initComplete: function () {
                new $.fn.dataTable.Buttons(tablaConceptos, {
                    buttons: [
                        { extend: 'copyHtml5', text: 'Copiar' },
                        { extend: 'excelHtml5', title: 'Catalogo_Conceptos' },
                        { extend: 'csvHtml5', title: 'Catalogo_Conceptos' },
                        { extend: 'pdfHtml5', title: 'Catalogo_Conceptos', orientation: 'landscape' },
                        { extend: 'print' }
                    ]
                });
            }
        });
    }

    $(document).on('click', '#btnRecargarConceptos', function () {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        tablaConceptos.ajax.reload(function () {
            btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
        }, false);
    });
    $(document).on('click', '#btnExportarCopyConceptos', function () { tablaConceptos.button('.buttons-copy').trigger(); });
    $(document).on('click', '#btnExportarExcelConceptos', function () { tablaConceptos.button('.buttons-excel').trigger(); });
    $(document).on('click', '#btnExportarPDFConceptos', function () { tablaConceptos.button('.buttons-pdf').trigger(); });
    $(document).on('click', '#btnExportarCSVConceptos', function () { tablaConceptos.button('.buttons-csv').trigger(); });
    $(document).on('click', '#btnExportarPrintConceptos', function () { tablaConceptos.button('.buttons-print').trigger(); });

    // Modal Nuevo Abono
    $('#btnNuevoAbono').on('click', function () {
        $('#formAbono')[0].reset();
        $('#ma_abono_id').val(0);
        modalAbono.show();
    });

    // Guardar Abono
    $('#formAbono').on('submit', function (e) {
        e.preventDefault();
        let datos = $(this).serialize();

        $.post('honorarios_conceptos_ajax.php', datos + '&accion=guardar_abono', function (res) {
            if (res.success) {
                modalAbono.hide();
                if (tablaAbonos) tablaAbonos.ajax.reload();
                Swal.fire('¡Éxito!', res.message, 'success');
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    });

    // Eliminar Abono
    $(document).on('click', '.btn-eliminar-abono', function () {
        let id = $(this).data('id');
        Swal.fire({
            title: '¿Dar de baja este abono?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, dar de baja',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (result.isConfirmed) {
                $.post('honorarios_conceptos_ajax.php', { accion: 'eliminar_abono', abono_id: id }, function (res) {
                    if (res.success) {
                        if (tablaAbonos) tablaAbonos.ajax.reload();
                        Swal.fire('¡Baja confirmada!', res.message, 'success');
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }, 'json');
            }
        });
    });

    // Modal Nuevo Concepto
    $('#btnNuevoConcepto').on('click', function () {
        $('#formConcepto')[0].reset();
        $('#mc_concepto_id').val(0);
        modalConcepto.show();
    });

    // Guardar Concepto
    $('#formConcepto').on('submit', function (e) {
        e.preventDefault();
        let datos = $(this).serialize();

        $.post('honorarios_conceptos_ajax.php', datos + '&accion=guardar_concepto', function (res) {
            if (res.success) {
                modalConcepto.hide();
                if (tablaConceptos) tablaConceptos.ajax.reload();
                cargarCombos();
                Swal.fire('¡Éxito!', res.message, 'success');
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    });
});
