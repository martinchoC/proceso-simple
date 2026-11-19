/**
 * JavaScript: Mini-Gestión de Clientes
 * Módulo 4: Estudio Contable (minigestion.js)
 */

$(document).ready(function() {
    let tablaVentas = null;
    let tablaCompras = null;
    let clienteActual = null;

    const formatoMoneda = { format: v => Fmt.moneda(v) };

    // Inicializar DataTables con el patrón canónico sin botones de texto por defecto
    function initDataTables() {
        const domConfig = '<"row align-items-center p-2 mb-0"<"col-12 col-md-6"l><"col-12 col-md-6 text-end"f>>' +
                          '<"row"<"col-12"tr>>' +
                          '<"row align-items-center p-2 mt-2"<"col-12 col-md-5"i><"col-12 col-md-7 d-flex justify-content-end"p>>';

        const languageConfig = {
            url: window.APP_ASSETS_URL + 'js/datatables-i18n-1.13.7/es-ES.json',
            emptyTable: 'No hay comprobantes registrados para este cliente y período'
        };

        // 1. Tabla Ventas
        tablaVentas = $('#tablaVentas').DataTable({
            responsive: true,
            dom: domConfig,
            language: languageConfig,
            order: [[0, 'desc']],
            columns: [
                { data: 'f_emision' },
                { 
                    data: null,
                    render: function(d) {
                        const pv = String(d.punto_venta || 1).padStart(4, '0');
                        const nro = String(d.numero || 0).padStart(8, '0');
                        const letra = d.letra || 'A';
                        return `<span class="badge bg-secondary me-1">${letra}</span> ${d.tipo_nombre} ${pv}-${nro}`;
                    }
                },
                { data: 'contraparte' },
                { data: 'cuit_contraparte' },
                { 
                    data: 'neto_gravado',
                    className: 'text-end fw-semibold',
                    render: d => formatoMoneda.format(d || 0)
                },
                { 
                    data: 'importe_iva',
                    className: 'text-end text-danger fw-semibold',
                    render: d => formatoMoneda.format(d || 0)
                },
                { 
                    data: 'no_gravado_exento',
                    className: 'text-end',
                    render: d => formatoMoneda.format(d || 0)
                },
                { 
                    data: 'importe_total',
                    className: 'text-end text-primary fw-bold',
                    render: d => formatoMoneda.format(d || 0)
                },
                { 
                    data: 'origen',
                    render: function(d) {
                        return d === 'ERP' 
                            ? '<span class="badge bg-success"><i class="fas fa-network-wired me-1"></i>ERP</span>'
                            : '<span class="badge bg-info"><i class="fas fa-edit me-1"></i>Mini-Gestión</span>';
                    }
                },
                { 
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function(d) {
                        if (d.origen === 'MANUAL') {
                            return `
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary btn-sm btn-editar-comp" data-id="${d.id}" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm btn-anular-comp" data-id="${d.id}" title="Anular">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            `;
                        } else {
                            return `
                                <span class="badge bg-light text-secondary border">Solo Lectura ERP</span>
                            `;
                        }
                    }
                }
            ],
            initComplete: function() {
                new $.fn.dataTable.Buttons(tablaVentas, {
                    buttons: [
                        { extend: 'copyHtml5', title: 'MiniGestion_Ventas' },
                        { extend: 'excelHtml5', title: 'MiniGestion_Ventas' },
                        { extend: 'csvHtml5', title: 'MiniGestion_Ventas' },
                        { extend: 'pdfHtml5', title: 'MiniGestion_Ventas', orientation: 'landscape' },
                        { extend: 'print', title: 'MiniGestion_Ventas' }
                    ]
                });
            }
        });

        // 2. Tabla Compras
        tablaCompras = $('#tablaCompras').DataTable({
            responsive: true,
            dom: domConfig,
            language: languageConfig,
            order: [[0, 'desc']],
            columns: [
                { data: 'f_emision' },
                { 
                    data: null,
                    render: function(d) {
                        const pv = String(d.punto_venta || 1).padStart(4, '0');
                        const nro = String(d.numero || 0).padStart(8, '0');
                        const letra = d.letra || 'A';
                        return `<span class="badge bg-secondary me-1">${letra}</span> ${d.tipo_nombre} ${pv}-${nro}`;
                    }
                },
                { data: 'contraparte' },
                { data: 'cuit_contraparte' },
                { 
                    data: 'neto_gravado',
                    className: 'text-end fw-semibold',
                    render: d => formatoMoneda.format(d || 0)
                },
                { 
                    data: 'importe_iva',
                    className: 'text-end text-success fw-semibold',
                    render: d => formatoMoneda.format(d || 0)
                },
                { 
                    data: 'percepciones',
                    className: 'text-end',
                    render: d => formatoMoneda.format(d || 0)
                },
                { 
                    data: 'importe_total',
                    className: 'text-end text-success fw-bold',
                    render: d => formatoMoneda.format(d || 0)
                },
                { 
                    data: 'origen',
                    render: function(d) {
                        return d === 'ERP' 
                            ? '<span class="badge bg-success"><i class="fas fa-network-wired me-1"></i>ERP</span>'
                            : '<span class="badge bg-info"><i class="fas fa-edit me-1"></i>Mini-Gestión</span>';
                    }
                },
                { 
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function(d) {
                        if (d.origen === 'MANUAL') {
                            return `
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary btn-sm btn-editar-comp" data-id="${d.id}" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm btn-anular-comp" data-id="${d.id}" title="Anular">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            `;
                        } else {
                            return `
                                <span class="badge bg-light text-secondary border">Solo Lectura ERP</span>
                            `;
                        }
                    }
                }
            ],
            initComplete: function() {
                new $.fn.dataTable.Buttons(tablaCompras, {
                    buttons: [
                        { extend: 'copyHtml5', title: 'MiniGestion_Compras' },
                        { extend: 'excelHtml5', title: 'MiniGestion_Compras' },
                        { extend: 'csvHtml5', title: 'MiniGestion_Compras' },
                        { extend: 'pdfHtml5', title: 'MiniGestion_Compras', orientation: 'landscape' },
                        { extend: 'print', title: 'MiniGestion_Compras' }
                    ]
                });
            }
        });

        // Enlace de botones de exportación Ventas
        $('#btnVentasRecargar').on('click', () => cargarDatos());
        $('#btnVentasCopiar').on('click', () => tablaVentas.button('.buttons-copy').trigger());
        $('#btnVentasExcel').on('click', () => tablaVentas.button('.buttons-excel').trigger());
        $('#btnVentasPdf').on('click', () => tablaVentas.button('.buttons-pdf').trigger());
        $('#btnVentasCsv').on('click', () => tablaVentas.button('.buttons-csv').trigger());
        $('#btnVentasImprimir').on('click', () => tablaVentas.button('.buttons-print').trigger());

        // Enlace de botones de exportación Compras
        $('#btnComprasRecargar').on('click', () => cargarDatos());
        $('#btnComprasCopiar').on('click', () => tablaCompras.button('.buttons-copy').trigger());
        $('#btnComprasExcel').on('click', () => tablaCompras.button('.buttons-excel').trigger());
        $('#btnComprasPdf').on('click', () => tablaCompras.button('.buttons-pdf').trigger());
        $('#btnComprasCsv').on('click', () => tablaCompras.button('.buttons-csv').trigger());
        $('#btnComprasImprimir').on('click', () => tablaCompras.button('.buttons-print').trigger());
    }

    initDataTables();

    // Cliente activo del estudio (estudio_cliente_activo.js): ?cliente_id=N o el último elegido en otra página
    (function () {
        const id = window.EstudioCliente ? EstudioCliente.inicial() : 0;
        if (id > 0 && $('#selCliente option[value="' + id + '"]').length) {
            setTimeout(function () { $('#selCliente').val(String(id)).trigger('change'); }, 0);
        }
    })();

    // Eventos de Filtro
    $('#selCliente').on('change', function() {
        const clienteId = $(this).val();
        if (window.EstudioCliente) { EstudioCliente.guardar(clienteId); }
        if (!clienteId) {
            $('#bannerModo').hide();
            limpiarTablas();
            return;
        }
        cargarDatos();
    });

    $('#filtroPeriodo').on('change', function() {
        if ($('#selCliente').val()) {
            cargarDatos();
        }
    });

    $('#btnActualizarDatos').on('click', function() {
        if ($('#selCliente').val()) {
            cargarDatos();
        } else {
            Swal.fire('Atención', 'Seleccione un cliente para ver sus comprobantes.', 'info');
        }
    });

    $('#btnLimpiarFiltros').on('click', function() {
        $('#filtroPeriodo').val('');
        if ($('#selCliente').val()) {
            cargarDatos();
        }
    });

    // Cargar información completa del cliente y sus comprobantes
    function cargarDatos() {
        const clienteId = $('#selCliente').val();
        const periodo = ($('#filtroPeriodo').val() || '').replace('-', '');

        if (!clienteId) return;

        // 1. Obtener Info Cliente y Configurar Modo
        $.ajax({
            url: 'minigestion_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_cliente_info', cliente_id: clienteId },
            dataType: 'json',
            success: function(resp) {
                if (resp.success && resp.data) {
                    clienteActual = resp.data;
                    actualizarBannerModo(clienteActual);
                    cargarVentas(clienteId, periodo);
                    cargarCompras(clienteId, periodo);
                    cargarTotales(clienteId, periodo);
                    cargarLiquidacion();
                } else {
                    Swal.fire('Error', resp.message || 'No se pudo obtener la información del cliente.', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Fallo de conexión al consultar cliente.', 'error');
            }
        });
    }

    function actualizarBannerModo(c) {
        const $banner = $('#bannerModo');
        $banner.removeClass('alert-secondary alert-success alert-primary').show();

        if (c.modo === 'ERP') {
            $banner.addClass('alert-success');
            $('#bannerIcono').html('<i class="fas fa-network-wired text-success"></i>');
            $('#bannerTitulo').html(`Modo Visualización / Auditoría ERP: <strong>${c.razon_social}</strong>`);
            $('#bannerDescripcion').html(`Este cliente opera directamente en el ERP vinculado: <strong>${c.empresa_vinculada_nombre || 'Empresa ID ' + c.empresa_vinculada_id}</strong>. Los comprobantes se leen en tiempo real de su subdiario.`);
            $('#bannerBadge').html('<span class="badge bg-success fs-7"><i class="fas fa-lock me-1"></i>Solo Lectura ERP</span>');

            $('#btnNuevaVenta, #btnNuevaCompra').hide();
            $('#badgeAvisoErpVentas, #badgeAvisoErpCompras').show();
        } else {
            $banner.addClass('alert-primary');
            $('#bannerIcono').html('<i class="fas fa-edit text-primary"></i>');
            $('#bannerTitulo').html(`Modo Carga Mini-Gestión: <strong>${c.razon_social}</strong>`);
            $('#bannerDescripcion').html(`Cliente externo del estudio contable (no utiliza el sistema de gestión). El estudio carga sus facturas para liquidación de impuestos y generación de libros.`);
            $('#bannerBadge').html('<span class="badge bg-primary fs-7"><i class="fas fa-plus-circle me-1"></i>Modo Carga Activa</span>');

            $('#btnNuevaVenta, #btnNuevaCompra').show();
            $('#badgeAvisoErpVentas, #badgeAvisoErpCompras').hide();
        }
    }

    function cargarVentas(clienteId, periodo) {
        $.ajax({
            url: 'minigestion_ajax.php',
            type: 'GET',
            data: { accion: 'listar_ventas', cliente_id: clienteId, periodo_fiscal: periodo },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    tablaVentas.clear().rows.add(resp.data || []).draw();
                }
            }
        });
    }

    function cargarCompras(clienteId, periodo) {
        $.ajax({
            url: 'minigestion_ajax.php',
            type: 'GET',
            data: { accion: 'listar_compras', cliente_id: clienteId, periodo_fiscal: periodo },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    tablaCompras.clear().rows.add(resp.data || []).draw();
                }
            }
        });
    }

    function cargarTotales(clienteId, periodo) {
        $.ajax({
            url: 'minigestion_ajax.php',
            type: 'GET',
            data: { accion: 'totales_fiscales', cliente_id: clienteId, periodo_fiscal: periodo },
            dataType: 'json',
            success: function(resp) {
                if (resp.success && resp.data) {
                    const t = resp.data;
                    $('#kpiVentasNeto').text(formatoMoneda.format(t.ventas_neto));
                    $('#kpiIvaDebito').text(formatoMoneda.format(t.iva_debito));
                    $('#kpiComprasNeto').text(formatoMoneda.format(t.compras_neto));
                    $('#kpiPosicionIva').text(formatoMoneda.format(Math.abs(t.posicion_final)));

                    $('#kpiCantVentas').text(`${t.cant_ventas} comprobantes`);
                    $('#kpiCantCompras').text(`${t.cant_compras} comprobantes`);

                    if (t.posicion_final > 0) {
                        $('#kpiSaldoDetalle').removeClass('bg-secondary bg-success').addClass('bg-danger').text('A Ingresar (AFIP)');
                    } else if (t.posicion_final < 0) {
                        $('#kpiSaldoDetalle').removeClass('bg-secondary bg-danger').addClass('bg-success').text('Saldo Técnico a Favor');
                    } else {
                        $('#kpiSaldoDetalle').removeClass('bg-success bg-danger').addClass('bg-secondary').text('Equilibrado');
                    }

                    // Pestaña Resumen
                    $('#resVentasNeto').text(formatoMoneda.format(t.ventas_neto));
                    $('#resIvaDebito').text(formatoMoneda.format(t.iva_debito));
                    $('#resComprasNeto').text(formatoMoneda.format(t.compras_neto));
                    $('#resIvaCredito').text(formatoMoneda.format(t.iva_credito));
                    $('#resSaldoTecnico').text(formatoMoneda.format(t.saldo_tecnico));
                    $('#resPercepciones').text(formatoMoneda.format(t.percepciones_iva || 0));
                    $('#resPercepcionesIibb').text(formatoMoneda.format(t.percepciones_iibb || 0));
                    $('#resPercepcionesSinDisc').text(formatoMoneda.format(t.percepciones_sin_discriminar || 0));
                    $('#filaPercSinDiscriminar').toggle(Math.abs(t.percepciones_sin_discriminar || 0) > 0);
                    renderPorAlicuota(t);
                    $('#resPosicionFinal').text(formatoMoneda.format(t.posicion_final));
                }
            }
        });
    }

    // Detalle por alícuota (solo clientes MANUAL: el ERP no discrimina en esta lectura)
    function renderPorAlicuota(t) {
        const $tb = $('#tablaPorAlicuota tbody').empty();
        $('#avisoPorAlicuotaErp').toggle(t.modo === 'ERP');
        const mapa = {};
        (t.ventas_por_alicuota || []).forEach(r => { (mapa[r.alicuota] = mapa[r.alicuota] || {}).v = r; });
        (t.compras_por_alicuota || []).forEach(r => { (mapa[r.alicuota] = mapa[r.alicuota] || {}).c = r; });
        Object.keys(mapa).map(Number).sort((a, b) => b - a).forEach(al => {
            const v = mapa[al].v || { neto: 0, iva: 0 }, c = mapa[al].c || { neto: 0, iva: 0 };
            $tb.append(`<tr><td>${Fmt.numero(al, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} %</td>
                <td class="text-end">${formatoMoneda.format(v.neto)}</td><td class="text-end text-danger">${formatoMoneda.format(v.iva)}</td>
                <td class="text-end">${formatoMoneda.format(c.neto)}</td><td class="text-end text-success">${formatoMoneda.format(c.iva)}</td></tr>`);
        });
    }

    function limpiarTablas() {
        tablaVentas.clear().draw();
        tablaCompras.clear().draw();
        $('#kpiVentasNeto, #kpiIvaDebito, #kpiComprasNeto, #kpiPosicionIva').text('$ 0,00');
        $('#kpiCantVentas, #kpiCantCompras').text('0 comprobantes');
        $('#kpiSaldoDetalle').removeClass('bg-success bg-danger').addClass('bg-secondary').text('Sin selección');
    }


    // ---------------------------------------------------------------- Liquidación mensual de IVA
    let liqActual = null;

    function periodoSeleccionado() { return ($('#filtroPeriodo').val() || '').replace('-', ''); }

    function cargarLiquidacion() {
        const clienteId = $('#selCliente').val();
        const periodo = periodoSeleccionado();
        if (!clienteId || !/^\d{6}$/.test(periodo)) {
            liqActual = null;
            $('#liqCuerpo').hide();
            $('#liqAvisoPeriodo').show();
            $('#liqEstado').removeClass('bg-success bg-danger bg-warning').addClass('bg-secondary').text('Sin período');
            return;
        }
        $.getJSON('minigestion_ajax.php', { accion: 'calcular_liquidacion_iva', cliente_id: clienteId, periodo_fiscal: periodo,
                                            retenciones_iva: $('#liqRetenciones').val() || 0 }, function(resp) {
            if (!resp.success) { $('#liqAvisoPeriodo').text(resp.error || resp.message).show(); $('#liqCuerpo').hide(); return; }
            pintarLiquidacion(resp.data);
        });
    }

    function pintarLiquidacion(l) {
        liqActual = l;
        const m = v => formatoMoneda.format(v || 0);
        const vig = l.vigente;
        $('#liqAvisoPeriodo').hide();
        $('#liqCuerpo').show();
        $('#liqDebito').text(m(l.debito_fiscal));
        $('#liqCredito').text(m(l.credito_fiscal));
        $('#liqPercepciones').text(m(l.percepciones_iva));
        $('#liqAnterior').text(m(l.saldo_favor_anterior));
        $('#liqAntPeriodo').text(l.saldo_favor_anterior_periodo ? '(' + String(l.saldo_favor_anterior_periodo).replace(/^(\d{4})(\d{2})$/, '$2/$1') + ')' : '');
        if (vig) $('#liqRetenciones').val(Number(vig.retenciones_iva).toFixed(2));
        $('#liqRetenciones').prop('disabled', !!vig || l.modo === 'ERP');
        $('#liqObs').prop('disabled', !!vig || l.modo === 'ERP').val(vig ? (vig.observaciones || '') : $('#liqObs').val());
        if (l.saldo_a_pagar > 0) { $('#liqResultadoLabel').text('Saldo a ingresar'); $('#liqResultado').text(m(l.saldo_a_pagar)).attr('class', 'text-end fw-bold fs-5 text-danger'); }
        else if (l.saldo_a_favor > 0) { $('#liqResultadoLabel').text('Saldo a favor (pasa al período siguiente)'); $('#liqResultado').text(m(l.saldo_a_favor)).attr('class', 'text-end fw-bold fs-5 text-success'); }
        else { $('#liqResultadoLabel').text('Saldo'); $('#liqResultado').text(m(0)).attr('class', 'text-end fw-bold fs-5'); }
        $('#liqEstado').removeClass('bg-secondary bg-success bg-danger bg-warning')
            .addClass(vig ? 'bg-success' : 'bg-warning').text(vig ? 'Liquidado' : 'Sin liquidar');
        const erp = l.modo === 'ERP';
        $('#liqNotaErp').toggle(erp);
        $('#btnLiquidarIva').toggle(!vig && !erp);
        $('#btnAnularLiquidacion').toggle(!!vig && !erp);
    }

    // Recalcula al tipear las retenciones (el servidor es el que manda: esto es solo vista previa)
    let liqTimer = null;
    $('#liqRetenciones').on('input', function() { clearTimeout(liqTimer); liqTimer = setTimeout(cargarLiquidacion, 300); });
    $('#filtroPeriodo').on('change', cargarLiquidacion);
    $('a[data-bs-target="#tab-resumen"], #tab-resumen-btn').on('shown.bs.tab click', cargarLiquidacion);

    $('#btnLiquidarIva').on('click', function() {
        if (!liqActual) return;
        const m = v => formatoMoneda.format(v || 0);
        const res = liqActual.saldo_a_pagar > 0 ? 'a ingresar ' + m(liqActual.saldo_a_pagar) : 'a favor ' + m(liqActual.saldo_a_favor);
        Swal.fire({
            title: '¿Liquidar el período?',
            html: 'Saldo ' + res + '.<br>Mientras esté liquidado no se pueden cargar, editar ni anular comprobantes de ese mes.',
            icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, liquidar', cancelButtonText: 'Cancelar'
        }).then(r => {
            if (!r.isConfirmed) return;
            $.post('minigestion_ajax.php?accion=liquidar_iva', { cliente_id: $('#selCliente').val(), periodo_fiscal: periodoSeleccionado(),
                retenciones_iva: $('#liqRetenciones').val() || 0, observaciones: $('#liqObs').val() }, function(resp) {
                if (resp.success) { Swal.fire('Liquidado', 'El período quedó liquidado.', 'success'); cargarLiquidacion(); }
                else Swal.fire('No se pudo liquidar', resp.error || resp.message, 'error');
            }, 'json').fail(() => Swal.fire('Error', 'Fallo al procesar la solicitud.', 'error'));
        });
    });

    $('#btnAnularLiquidacion').on('click', function() {
        Swal.fire({ title: '¿Anular la liquidación?', text: 'El período vuelve a quedar abierto para modificar sus comprobantes.', icon: 'warning',
                    showCancelButton: true, confirmButtonText: 'Sí, anular', cancelButtonText: 'Cancelar' }).then(r => {
            if (!r.isConfirmed) return;
            $.post('minigestion_ajax.php?accion=anular_liquidacion_iva', { cliente_id: $('#selCliente').val(), periodo_fiscal: periodoSeleccionado() }, function(resp) {
                if (resp.success) { Swal.fire('Anulada', 'La liquidación fue anulada.', 'success'); cargarLiquidacion(); }
                else Swal.fire('No se pudo anular', resp.error || resp.message, 'error');
            }, 'json').fail(() => Swal.fire('Error', 'Fallo al procesar la solicitud.', 'error'));
        });
    });

    // Modal de Carga de Comprobante Manual
    $('#btnNuevaVenta').on('click', function() {
        abrirModalComprobante('VENTA');
    });

    $('#btnNuevaCompra').on('click', function() {
        abrirModalComprobante('COMPRA');
    });

    const ALICUOTAS = [27, 21, 10.5, 5, 2.5, 0];
    const TIPOS_PERCEPCION = [['IIBB', 'Ingresos Brutos'], ['IVA', 'IVA'], ['GANANCIAS', 'Ganancias'], ['SUSS', 'SUSS'], ['MUNICIPAL', 'Municipal'], ['OTRA', 'Otra']];
    let jurisdiccionesCliente = [];

    function esc(t) { return $('<div>').text(t == null ? '' : t).html(); }
    function redondear(n) { return Math.round((n + Number.EPSILON) * 100) / 100; }

    function agregarLineaIva(alicuota = 21, neto = 0) {
        const opts = ALICUOTAS.map(a => `<option value="${a}" ${Number(a) === Number(alicuota) ? 'selected' : ''}>${String(a).replace('.', ',')} %</option>`).join('');
        $('#tablaLineasIva tbody').append(`<tr>
            <td><select class="form-select form-select-sm li-alicuota">${opts}</select></td>
            <td><input type="number" step="0.01" class="form-control form-control-sm text-end li-neto" value="${Number(neto).toFixed(2)}"></td>
            <td class="text-end li-iva fw-bold text-danger">0,00</td>
            <td><button type="button" class="btn btn-sm btn-outline-danger li-quitar" title="Quitar"><i class="fas fa-times"></i></button></td></tr>`);
        recalcularTotalModal();
    }

    function agregarPercepcion(tipo = 'IIBB', jurId = 0, importe = 0) {
        const opts = TIPOS_PERCEPCION.map(([v, n]) => `<option value="${v}" ${v === tipo ? 'selected' : ''}>${n}</option>`).join('');
        const jur = ['<option value="0">—</option>'].concat(jurisdiccionesCliente.map(j =>
            `<option value="${j.jurisdiccion_id}" ${Number(j.jurisdiccion_id) === Number(jurId) ? 'selected' : ''}>${esc(j.jurisdiccion_nombre || ('Jurisdicción ' + j.jurisdiccion_id))}</option>`)).join('');
        $('#tablaPercepciones tbody').append(`<tr>
            <td><select class="form-select form-select-sm pe-tipo">${opts}</select></td>
            <td><select class="form-select form-select-sm pe-jur">${jur}</select></td>
            <td><input type="number" step="0.01" class="form-control form-control-sm text-end pe-importe" value="${Number(importe).toFixed(2)}"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger pe-quitar" title="Quitar"><i class="fas fa-times"></i></button></td></tr>`);
        actualizarJurisdiccionesHabilitadas();
        recalcularTotalModal();
    }

    // La jurisdicción solo aplica a IIBB y Municipal
    function actualizarJurisdiccionesHabilitadas() {
        $('#tablaPercepciones tbody tr').each(function() {
            const aplica = ['IIBB', 'MUNICIPAL'].includes($(this).find('.pe-tipo').val());
            $(this).find('.pe-jur').prop('disabled', !aplica);
            if (!aplica) $(this).find('.pe-jur').val('0');
        });
    }

    function abrirModalComprobante(tipo, datos = null) {
        if (!clienteActual) {
            Swal.fire('Atención', 'Seleccione un cliente primero.', 'warning');
            return;
        }

        $('#formComprobante')[0].reset();
        $('#tablaLineasIva tbody, #tablaPercepciones tbody').empty();
        $('#comp_id').val(datos ? datos.comprobante_id : 0);
        $('#comp_cliente_id').val(clienteActual.cliente_id);
        $('#comp_tipo_operacion').val(tipo);
        $('#comp_tipo_op_display').val(tipo === 'VENTA' ? 'VENTA (Emitido por el Cliente)' : 'COMPRA (Recibido por el Cliente)');

        if (tipo === 'VENTA') {
            $('#modalComprobanteLabel').html('<i class="fas fa-sign-out-alt me-1 text-primary"></i>Cargar Comprobante de Venta');
            $('#lblContraparteCuit').text('CUIT Cliente Receptor');
            $('#lblContraparteNombre').text('Razón Social Cliente Receptor');
        } else {
            $('#modalComprobanteLabel').html('<i class="fas fa-sign-in-alt me-1 text-success"></i>Cargar Comprobante de Compra');
            $('#lblContraparteCuit').text('CUIT Proveedor Emisor');
            $('#lblContraparteNombre').text('Razón Social Proveedor Emisor');
        }

        // Jurisdicciones del cliente para las percepciones de IIBB, y recién después se arman las filas
        $.getJSON('minigestion_ajax.php', { accion: 'obtener_jurisdicciones_cliente', cliente_id: clienteActual.cliente_id }, function(resp) {
            jurisdiccionesCliente = (resp && resp.success && resp.data) ? resp.data : [];
        }).always(function() {
            if (datos) {
                $('#comp_f_emision').val(datos.f_emision);
                $('#comp_tipo_id').val(datos.comprobante_tipo_id);
                $('#comp_letra').val(datos.comprobante_letra);
                $('#comp_pv').val(datos.punto_venta);
                $('#comp_nro').val(datos.comprobante_nro);
                $('#comp_cuit').val(datos.cuit_contraparte);
                $('#comp_razon').val(datos.razon_social_contraparte || '');
                $('#comp_nograv').val(Number(datos.no_gravado_exento || 0).toFixed(2));
                $('#comp_obs').val(datos.observaciones || '');
                (datos.iva_detalle || []).forEach(l => agregarLineaIva(Number(l.alicuota), Number(l.neto)));
                (datos.percepciones || []).forEach(p => agregarPercepcion(p.tipo, p.jurisdiccion_id || 0, Number(p.importe)));
                if (!(datos.iva_detalle || []).length) agregarLineaIva(21, 0);
            } else {
                $('#comp_f_emision').val(Fmt.hoyISO());
                $('#comp_pv').val(1);
                $('#comp_nograv').val('0.00');
                agregarLineaIva(21, 0);
            }
            recalcularTotalModal();
            $('#modalComprobante').modal('show');
        });
    }

    // Líneas dinámicas: el IVA de cada línea y el total se calculan acá solo para mostrar; el servidor los recalcula.
    $('#btnAddLineaIva').on('click', () => agregarLineaIva(21, 0));
    $('#btnAddPercepcion').on('click', () => agregarPercepcion('IIBB', 0, 0));
    $('#tablaLineasIva').on('input change', '.li-alicuota, .li-neto', recalcularTotalModal);
    $('#tablaLineasIva').on('click', '.li-quitar', function() { $(this).closest('tr').remove(); recalcularTotalModal(); });
    $('#tablaPercepciones').on('input change', '.pe-importe', recalcularTotalModal);
    $('#tablaPercepciones').on('change', '.pe-tipo', function() { actualizarJurisdiccionesHabilitadas(); });
    $('#tablaPercepciones').on('click', '.pe-quitar', function() { $(this).closest('tr').remove(); recalcularTotalModal(); });
    $('#comp_nograv').on('input change', recalcularTotalModal);

    function leerLineasIva() {
        const out = [];
        $('#tablaLineasIva tbody tr').each(function() {
            out.push({ alicuota: parseFloat($(this).find('.li-alicuota').val()), neto: parseFloat($(this).find('.li-neto').val()) || 0 });
        });
        return out;
    }
    function leerPercepciones() {
        const out = [];
        $('#tablaPercepciones tbody tr').each(function() {
            out.push({ tipo: $(this).find('.pe-tipo').val(), jurisdiccion_id: parseInt($(this).find('.pe-jur').val(), 10) || 0,
                       importe: parseFloat($(this).find('.pe-importe').val()) || 0 });
        });
        return out;
    }

    function recalcularTotalModal() {
        let neto = 0, iva = 0;
        $('#tablaLineasIva tbody tr').each(function() {
            const n = parseFloat($(this).find('.li-neto').val()) || 0;
            const a = parseFloat($(this).find('.li-alicuota').val()) || 0;
            const i = redondear(n * a / 100);
            $(this).find('.li-iva').text(Fmt.numero(i, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            neto += n; iva += i;
        });
        const nograv = parseFloat($('#comp_nograv').val()) || 0;
        let perc = 0;
        leerPercepciones().forEach(p => perc += p.importe);
        $('#comp_total').val(redondear(neto + iva + nograv + perc).toFixed(2));
    }

    $('#comp_tipo_id').on('change', function() {
        const letra = $(this).find('option:selected').data('letra') || 'A';
        $('#comp_letra').val(letra);
    });

    // Guardar Comprobante Manual (POST)
    $('#formComprobante').on('submit', function(e) {
        e.preventDefault();

        if (!leerLineasIva().some(l => l.neto !== 0) && !(parseFloat($('#comp_nograv').val()) || 0)) {
            Swal.fire('Atención', 'Cargá al menos un neto gravado o un importe no gravado / exento.', 'warning');
            return;
        }
        $('#comp_iva_detalle').val(JSON.stringify(leerLineasIva()));
        $('#comp_percepciones').val(JSON.stringify(leerPercepciones()));
        const formData = $(this).serialize();
        $('#btnGuardarComprobante').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: 'minigestion_ajax.php?accion=guardar_comprobante',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(resp) {
                $('#btnGuardarComprobante').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Comprobante');
                if (resp.success) {
                    $('#modalComprobante').modal('hide');
                    Swal.fire('Guardado', 'Comprobante registrado exitosamente.', 'success');
                    cargarDatos();
                } else {
                    Swal.fire('No se pudo guardar', resp.error || resp.message || 'No se pudo guardar el comprobante.', 'error');
                }
            },
            error: function() {
                $('#btnGuardarComprobante').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Comprobante');
                Swal.fire('Error', 'Fallo al procesar la solicitud.', 'error');
            }
        });
    });

    // Editar Comprobante Manual
    $(document).on('click', '.btn-editar-comp', function() {
        const compId = $(this).data('id');
        $.ajax({
            url: 'minigestion_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_comprobante', comprobante_id: compId },
            dataType: 'json',
            success: function(resp) {
                if (resp.success && resp.data) {
                    abrirModalComprobante(resp.data.tipo_operacion, resp.data);
                } else {
                    Swal.fire('Error', 'No se pudo cargar el comprobante.', 'error');
                }
            }
        });
    });

    // Anular Comprobante Manual
    $(document).on('click', '.btn-anular-comp', function() {
        const compId = $(this).data('id');
        Swal.fire({
            title: '¿Anular comprobante?',
            text: 'Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, anular',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'minigestion_ajax.php',
                    type: 'POST',
                    data: { accion: 'anular_comprobante', comprobante_id: compId },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.success) {
                            Swal.fire('Anulado', 'El comprobante ha sido anulado.', 'success');
                            cargarDatos();
                        } else {
                            Swal.fire('No se pudo anular', resp.error || resp.message || 'No se pudo anular.', 'error');
                        }
                    }
                });
            }
        });
    });

    // Botones de exportación consolidada en Tab 3
    $('#btnExportarLibroVentas').on('click', function() {
        tablaVentas.button('.buttons-excel').trigger();
    });

    $('#btnExportarLibroCompras').on('click', function() {
        tablaCompras.button('.buttons-excel').trigger();
    });
});
