/**
 * JavaScript: Mini-Gestión y Amortización de Activos Fijos
 * Módulo 4: Estudio Contable (activos_amortizaciones.js)
 */

$(document).ready(function() {
    let tabla = null;
    let cacheCombos = null;

    cargarCombos();

    function cargarCombos() {
        $.getJSON('activos_amortizaciones_ajax.php', { action: 'combos' }, function(res) {
            if (!res.success) {
                Swal.fire('Error', res.message, 'error');
                return;
            }
            cacheCombos = res.data;

            // Llenar categorías
            let optCatFiltro = '<option value="">-- Todas las categorías --</option>';
            let optCatModal = '';
            res.data.categorias.forEach(c => {
                optCatFiltro += `<option value="${c}">${c.replace('_', ' ')}</option>`;
                optCatModal += `<option value="${c}">${c.replace('_', ' ')}</option>`;
            });
            $('#filtroCategoria').html(optCatFiltro);
            $('#modal_categoria').html(optCatModal);

            // Llenar cuentas contables
            let optCuentas = '<option value="0">-- Sin imputar --</option>';
            res.data.cuentas.forEach(cta => {
                const txt = `${cta.codigo} - ${cta.nombre}`;
                optCuentas += `<option value="${cta.cont_cuenta_id}">${txt}</option>`;
            });
            $('#modal_cuenta_activo_id').html(optCuentas);
            $('#modal_cuenta_amort_acum_id').html(optCuentas);
            $('#modal_cuenta_gasto_amort_id').html(optCuentas);

            inicializarDataTable();
        });
    }

    function inicializarDataTable() {
        tabla = $('#tablaActivos').DataTable({
            responsive: true,
            language: {
                url: window.APP_ASSETS_URL + "js/datatables-i18n-1.13.6/es-ES.json"
            },
            ajax: {
                url: 'activos_amortizaciones_ajax.php',
                type: 'POST',
                data: function(d) {
                    d.action = 'listar';
                    d.categoria_activo = $('#filtroCategoria').val();
                    d.buscar = $('#filtroBuscar').val();
                },
                dataSrc: 'data'
            },
            columns: [
                { 
                    data: 'codigo',
                    className: 'fw-bold text-primary',
                    render: function(data) {
                        return `<code>${data}</code>`;
                    }
                },
                { 
                    data: 'descripcion',
                    render: function(data, type, row) {
                        let html = `<strong>${data}</strong>`;
                        if (row.cuenta_gasto_nombre) {
                            html += `<br><small class="text-muted"><i class="fas fa-tag me-1"></i>Gasto: ${row.cuenta_gasto_nombre}</small>`;
                        }
                        return html;
                    }
                },
                { 
                    data: 'categoria_activo',
                    render: function(data) {
                        return `<span class="badge bg-secondary">${data.replace('_', ' ')}</span>`;
                    }
                },
                { data: 'f_adquisicion' },
                { 
                    data: 'valor_origen',
                    className: 'text-end fw-bold',
                    render: function(data) {
                        return '$ ' + Fmt.numero(parseFloat(data), 2);
                    }
                },
                { 
                    data: 'cuota_mensual_estimada',
                    className: 'text-end text-success fw-bold',
                    render: function(data) {
                        return '$ ' + Fmt.numero(parseFloat(data), 2);
                    }
                },
                { 
                    data: 'meses_amortizados',
                    className: 'text-center',
                    render: function(data, type, row) {
                        const am = parseInt(data || 0);
                        const tot = parseInt(row.vida_util_meses || 1);
                        const pct = Math.min(Math.round((am / tot) * 100), 100);
                        let barCls = pct >= 100 ? 'bg-success' : 'bg-primary';
                        return `<div><small>${am} / ${tot} meses (${pct}%)</small></div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar ${barCls}" style="width: ${pct}%"></div>
                                </div>`;
                    }
                },
                { 
                    data: 'valor_residual_contable',
                    className: 'text-end fw-bold',
                    render: function(data) {
                        const val = parseFloat(data || 0);
                        const color = val <= 0 ? 'text-muted' : 'text-dark';
                        return `<span class="${color}">$ ${Fmt.numero(val, 2)}</span>`;
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function(data, type, row) {
                        return `
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-dark btn-historial" data-id="${row.activo_id}" data-desc="${row.descripcion}" title="Ver Historial de Cuotas">
                                    <i class="fas fa-history"></i>
                                </button>
                                <button class="btn btn-outline-primary btn-editar" data-funcion="editar" data-id="${row.activo_id}" title="Editar Activo">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        `;
                    }
                }
            ],
            dom: '<"row align-items-center p-2 mb-0"<"col-12 col-md-6"l><"col-12 col-md-6 text-end"f>>' +
                 '<"row"<"col-12"tr>>' +
                 '<"row align-items-center p-2 mt-2"<"col-12 col-md-5"i><"col-12 col-md-7 d-flex justify-content-end"p>>',
            initComplete: function () {
                new $.fn.dataTable.Buttons(tabla, {
                    buttons: [
                        { extend: 'copyHtml5', text: 'Copiar', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'excelHtml5', title: 'Activos_Fijos_Amortizaciones', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'csvHtml5', title: 'Activos_Fijos_Amortizaciones', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'pdfHtml5', title: 'Activos_Fijos_Amortizaciones', orientation: 'landscape', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'print', exportOptions: { columns: ':not(:last-child)' } }
                    ]
                });
            }
        });
    }

    $(document).on('click', '#btnRecargar', function () {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        tabla.ajax.reload(function () {
            btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
        }, false);
    });
    $(document).on('click', '#btnExportarCopy', function () { tabla.button('.buttons-copy').trigger(); });
    $(document).on('click', '#btnExportarExcel', function () { tabla.button('.buttons-excel').trigger(); });
    $(document).on('click', '#btnExportarPDF', function () { tabla.button('.buttons-pdf').trigger(); });
    $(document).on('click', '#btnExportarCSV', function () { tabla.button('.buttons-csv').trigger(); });
    $(document).on('click', '#btnExportarPrint', function () { tabla.button('.buttons-print').trigger(); });

    // Filtros
    $('#btnFiltrar').on('click', function() {
        if (tabla) tabla.ajax.reload();
    });

    $('#btnLimpiar').on('click', function() {
        $('#filtroCategoria').val('');
        $('#filtroBuscar').val('');
        if (tabla) tabla.ajax.reload();
    });

    // Nuevo Activo Modal
    $('#btnNuevoActivo').on('click', function() {
        $('#formActivo')[0].reset();
        $('#activo_id').val(0);
        $('#modalActivoTitle').text('Registrar Activo Fijo');
        $('#modal_f_adquisicion').val(new Date().toISOString().substring(0, 10));
        $('#modal_vida_util_meses').val(60);
        $('#modal_valor_residual').val('0.00');
        $('#modalActivo').modal('show');
    });

    // Guardar Activo
    $('#formActivo').on('submit', function(e) {
        e.preventDefault();
        const postData = $(this).serialize() + '&action=guardar';

        $.post('activos_amortizaciones_ajax.php', postData, function(res) {
            if (res.success) {
                Swal.fire('Guardado', res.message, 'success');
                $('#modalActivo').modal('hide');
                tabla.ajax.reload();
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    });

    // Editar Activo
    $('#tablaActivos').on('click', '.btn-editar', function() {
        const id = $(this).data('id');
        const rowData = tabla.row($(this).parents('tr')).data();

        $('#activo_id').val(rowData.activo_id);
        $('#modal_codigo').val(rowData.codigo);
        $('#modal_descripcion').val(rowData.descripcion);
        $('#modal_categoria').val(rowData.categoria_activo);
        $('#modal_f_adquisicion').val(rowData.f_adquisicion);
        $('#modal_valor_origen').val(rowData.valor_origen);
        $('#modal_valor_residual').val(rowData.valor_residual);
        $('#modal_vida_util_meses').val(rowData.vida_util_meses);
        $('#modal_metodo').val(rowData.metodo_amortizacion);
        $('#modal_cuenta_activo_id').val(rowData.cuenta_activo_id);
        $('#modal_cuenta_amort_acum_id').val(rowData.cuenta_amort_acum_id);
        $('#modal_cuenta_gasto_amort_id').val(rowData.cuenta_gasto_amort_id);

        $('#modalActivoTitle').text('Editar Activo Fijo');
        $('#modalActivo').modal('show');
    });

    // Historial de Cuotas
    $('#tablaActivos').on('click', '.btn-historial', function() {
        const id = $(this).data('id');
        const desc = $(this).data('desc');
        $('#historialTituloActivo').text(`Bien de Uso: ${desc} (ID: ${id})`);

        $.getJSON('activos_amortizaciones_ajax.php', { action: 'listar_amortizaciones', activo_id: id }, function(res) {
            let html = '';
            if (res.success && res.data.length > 0) {
                res.data.forEach(c => {
                    const imp = Fmt.numero(parseFloat(c.importe_amortizacion), 2);
                    html += `
                        <tr>
                            <td><strong>${c.periodo}</strong></td>
                            <td class="text-center">${c.cuota_nro}</td>
                            <td class="text-end fw-bold text-success">$ ${imp}</td>
                            <td>${c.f_asiento}</td>
                            <td><span class="badge bg-primary">Asiento #${c.asiento_id || 'N/A'}</span></td>
                            <td>${c.asiento_descripcion || 'Amortización Periódica'}</td>
                        </tr>
                    `;
                });
            } else {
                html = '<tr><td colspan="6" class="text-center text-muted">Aún no se han computado amortizaciones para este activo.</td></tr>';
            }
            $('#tablaHistorialAmort tbody').html(html);
            $('#modalHistorialAmort').modal('show');
        });
    });

    // Abrir Modal de Amortización Mensual en Lote
    $('#btnAmortizarMes').on('click', function() {
        $('#modalLoteAmortizar').modal('show');
    });

    // Ejecutar Amortización Mensual
    $('#formLoteAmortizar').on('submit', function(e) {
        e.preventDefault();
        const periodo = $('#lote_periodo').val();

        Swal.fire({
            title: `¿Amortizar Período ${periodo}?`,
            text: 'Se procesarán todos los activos vigentes pendientes y se generará automáticamente el Asiento Contable.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, ejecutar proceso',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Procesando contabilidad...',
                    text: 'Calculando cuotas y generando asientos contables.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.post('activos_amortizaciones_ajax.php', { action: 'amortizar_mensual', periodo: periodo }, function(res) {
                    if (res.success) {
                        Swal.fire('Proceso Completado', res.message, 'success');
                        $('#modalLoteAmortizar').modal('hide');
                        tabla.ajax.reload();
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }, 'json');
            }
        });
    });
});
