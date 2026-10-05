/**
 * JavaScript: Facturación Masiva de Abonos
 * Módulo 4: Estudio Contable (honorarios_facturacion.js)
 */

$(document).ready(function () {
    let itemsConsolidados = [];

    // Cargar consolidación inicial
    cargarConsolidacion();

    $('#btnConsolidar').on('click', function () {
        cargarConsolidacion();
    });

    function cargarConsolidacion() {
        let periodo = $('#hf_periodo_fiscal').val();
        if (!periodo || periodo.length !== 6) {
            Swal.fire('Atención', 'Ingrese un período válido (YYYYMM).', 'warning');
            return;
        }

        $('#body_facturacion').html('<tr><td colspan="10" class="text-center py-4"><div class="spinner-border text-primary"></div> Consolidando abonos y novedades...</td></tr>');

        $.getJSON('honorarios_facturacion_ajax.php', { accion: 'consolidar', periodo_fiscal: periodo }, function (res) {
            if (!res.success) {
                Swal.fire('Error', res.message, 'error');
                return;
            }

            itemsConsolidados = res.data || [];
            renderTabla();
        }).fail(function () {
            Swal.fire('Error', 'Fallo al conectar con el servidor.', 'error');
        });
    }

    function renderTabla() {
        if (itemsConsolidados.length === 0) {
            $('#body_facturacion').html('<tr><td colspan="10" class="text-center text-muted py-4">No se encontraron abonos activos para facturar en este período.</td></tr>');
            actualizarTotales();
            return;
        }

        let html = '';
        itemsConsolidados.forEach((it, idx) => {
            let disabled = it.ya_facturado ? 'disabled' : '';
            let checked = !it.ya_facturado ? 'checked' : '';
            let badgeEstado = it.ya_facturado 
                ? `<span class="badge bg-secondary"><i class="fas fa-check-double me-1"></i>Facturado (${it.ultimo_periodo_facturado})</span>`
                : '<span class="badge bg-success"><i class="fas fa-clock me-1"></i>Listo p/ Emitir</span>';

            html += `
                <tr class="${it.ya_facturado ? 'table-light opacity-75' : ''}">
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input check_abono" data-index="${idx}" value="${it.abono_id}" ${checked} ${disabled}>
                    </td>
                    <td>
                        <div><strong>${it.cliente_nombre}</strong></div>
                        <div class="text-muted fs-8">${it.cliente_cuit} (${it.condicion_fiscal_codigo})</div>
                    </td>
                    <td>
                        <div><strong>${it.concepto_nombre}</strong></div>
                        <div class="text-muted fs-8">PV ${it.punto_venta_codigo} - Factura ${it.condicion_fiscal_codigo === 'RI' ? 'A' : 'B'}</div>
                    </td>
                    <td class="text-end">$ ${it.importe_base.toLocaleString('es-AR', { minimumFractionDigits: 2 })}</td>
                    <td class="text-end">$ ${it.adicional_legajos.toLocaleString('es-AR', { minimumFractionDigits: 2 })}</td>
                    <td class="text-end">$ ${it.novedades_monto.toLocaleString('es-AR', { minimumFractionDigits: 2 })}</td>
                    <td class="text-end fw-semibold">$ ${it.importe_neto.toLocaleString('es-AR', { minimumFractionDigits: 2 })}</td>
                    <td class="text-end text-muted">$ ${it.importe_iva.toLocaleString('es-AR', { minimumFractionDigits: 2 })}</td>
                    <td class="text-end text-primary fw-bold fs-7">$ ${it.importe_total.toLocaleString('es-AR', { minimumFractionDigits: 2 })}</td>
                    <td>${badgeEstado}</td>
                </tr>
            `;
        });

        $('#body_facturacion').html(html);
        actualizarTotales();
    }

    // Toggle Seleccionar Todos
    $('#check_todos').on('change', function () {
        let checked = $(this).is(':checked');
        $('.check_abono:not(:disabled)').prop('checked', checked);
        actualizarTotales();
    });

    $(document).on('change', '.check_abono', function () {
        actualizarTotales();
    });

    function actualizarTotales() {
        let total = 0.0;
        let count = 0;

        $('.check_abono:checked').each(function () {
            let idx = $(this).data('index');
            let item = itemsConsolidados[idx];
            if (item) {
                total += parseFloat(item.importe_total);
                count++;
            }
        });

        $('#txt_total_lote').text('$ ' + total.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        $('#txt_conteo_sel').text(count + ' seleccionados');
    }

    // Botón Emitir Lote
    $('#btnEmitirLote').on('click', function () {
        let seleccionados = [];
        $('.check_abono:checked').each(function () {
            seleccionados.push($(this).val());
        });

        if (seleccionados.length === 0) {
            Swal.fire('Atención', 'Debe seleccionar al menos un abono para emitir.', 'warning');
            return;
        }

        let periodo = $('#hf_periodo_fiscal').val();

        Swal.fire({
            title: '¿Emitir Facturación Masiva?',
            text: `Se generarán las facturas en el módulo de ventas y se sincronizará el comprobante universal para ${seleccionados.length} clientes.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, emitir lote',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Emitiendo facturas...',
                    text: 'Generando comprobantes de venta con transacción segura',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                $.post('honorarios_facturacion_ajax.php', {
                    accion: 'emitir_lote',
                    periodo_fiscal: periodo,
                    abonos: seleccionados
                }, function (res) {
                    if (res.success) {
                        Swal.fire({
                            title: '¡Facturación Exitosa!',
                            text: res.message,
                            icon: 'success'
                        });
                        cargarConsolidacion();
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }, 'json').fail(function () {
                    Swal.fire('Error', 'Error de comunicación durante la facturación masiva.', 'error');
                });
            }
        });
    });
});
