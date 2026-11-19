/**
 * modules/sindicatos/volantes.js
 * Interacción y controladores reactivos para Volantes de Pago (Página 1030).
 */

document.addEventListener('DOMContentLoaded', function () {
    const URL_AJAX = 'volantes_ajax.php';
    let tablaVolantes = null;
    let deudasCargadas = [];

    // Helper formato moneda
    const formatoMoneda = (val) => {
        return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(val || 0);
    };

    // Helper badge de estado
    const badgeEstado = (estadoId, nombre) => {
        let cls = 'bg-secondary';
        switch (parseInt(estadoId)) {
            case 3:  cls = 'bg-warning text-dark'; break; // Borrador
            case 31: cls = 'bg-primary'; break;           // Emitido
            case 39: cls = 'bg-info text-dark'; break;    // Canc. Parcial
            case 40: cls = 'bg-success'; break;           // Canc. Total
            case 20: cls = 'bg-danger'; break;            // Vencido
            case 38: cls = 'bg-dark'; break;              // Anulado
        }
        return `<span class="badge ${cls} px-2 py-1">${nombre || 'Sin estado'}</span>`;
    };

    // 1. Inicializar DataTable de Volantes
    function initDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaVolantes')) {
            $('#tablaVolantes').DataTable().destroy();
        }

        tablaVolantes = $('#tablaVolantes').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: URL_AJAX + '?accion=listar',
                data: function (d) {
                    d.empleador_id = $('#filtroEmpleador').val();
                },
                dataSrc: 'data'
            },
            columns: [
                {
                    data: 'numero',
                    render: (d) => `<span class="fw-bold font-monospace text-primary">${d}</span>`
                },
                {
                    data: null,
                    render: (row) => `
                        <div class="fw-semibold">${row.empleador_nombre || '-'}</div>
                        <small class="text-muted font-monospace">${row.empleador_cuit || ''}</small>
                    `
                },
                { data: 'f_emision' },
                { data: 'f_pago_1' },
                {
                    data: 'importe_1',
                    className: 'text-end fw-semibold text-primary',
                    render: (d) => formatoMoneda(d)
                },
                { data: 'f_pago_2' },
                {
                    data: 'importe_2',
                    className: 'text-end fw-semibold text-dark',
                    render: (d) => formatoMoneda(d)
                },
                {
                    data: 'codigo_barras',
                    render: (d) => `<span class="font-monospace small text-muted">${d ? d.substring(0, 19) + '...' : '-'}</span>`
                },
                {
                    data: null,
                    render: (row) => badgeEstado(row.tabla_estado_registro_id, row.estado_nombre)
                },
                {
                    data: null,
                    className: 'text-end',
                    orderable: false,
                    // Botones del motor de estados (página 1030), filtrados por estado y permisos en el servidor
                    render: (row) => row.acciones || ''
                }
            ],
            order: [[0, 'desc']]
        });
    }

    // 2. Cargar Combo de Empleadores
    function cargarEmpleadores() {
        fetch(URL_AJAX + '?accion=combo_empleadores')
            .then(res => res.json())
            .then(res => {
                if (!res.success) return;
                const options = res.data.map(e => `<option value="${e.empleador_id}">${e.razon_social} (${e.cuit})</option>`).join('');
                $('#filtroEmpleador').append(options);
                $('#modalEmpleadorId').append(options);
            })
            .catch(err => console.error("Error al cargar empleadores:", err));
    }

    // Filtros reactivos
    $('#filtroEmpleador').on('change', function () {
        if (tablaVolantes) tablaVolantes.ajax.reload();
    });

    $('#btnRecargar').on('click', function () {
        if (tablaVolantes) tablaVolantes.ajax.reload();
    });

    // Emitir volantes solo con la función "agregar" de la página
    fetch(URL_AJAX + '?accion=obtener_boton_agregar')
        .then(res => res.json())
        .then(res => { if (!res || !res.nombre_funcion) $('#btnNuevoVolante').addClass('d-none'); })
        .catch(() => $('#btnNuevoVolante').addClass('d-none'));

    // 3. Abrir Modal de Nuevo Volante
    $('#btnNuevoVolante').on('click', function () {
        $('#formNuevoVolante')[0].reset();
        deudasCargadas = [];
        $('#tbodyDeudasPendientes').html('<tr><td colspan="7" class="text-center py-3 text-muted">Seleccione un empleador para cargar sus deudas pendientes</td></tr>');

        // Establecer fechas sugeridas (vto 1 = +10 días, vto 2 = +20 días)
        const hoy = new Date();
        const vto1 = new Date(); vto1.setDate(hoy.getDate() + 10);
        const vto2 = new Date(); vto2.setDate(hoy.getDate() + 20);

        $('#modalFechaPago1').val(Fmt.isoLocal(vto1));
        $('#modalFechaPago2').val(Fmt.isoLocal(vto2));

        actualizarResumenTotales({
            importe_capital_1: 0, importe_interes_1: 0, importe_total_1: 0,
            importe_capital_2: 0, importe_interes_2: 0, importe_total_2: 0,
            f_pago_1: $('#modalFechaPago1').val(),
            f_pago_2: $('#modalFechaPago2').val()
        });

        $('#btnConfirmarEmision').prop('disabled', true);
        const modal = new bootstrap.Modal(document.getElementById('modalNuevoVolante'));
        modal.show();
    });

    // 4. Cargar Deudas al Seleccionar Empleador en Modal
    $('#modalEmpleadorId').on('change', function () {
        const empId = $(this).val();
        if (!empId) {
            $('#tbodyDeudasPendientes').html('<tr><td colspan="7" class="text-center py-3 text-muted">Seleccione un empleador para cargar sus deudas pendientes</td></tr>');
            deudasCargadas = [];
            recalcularSimulacion();
            return;
        }

        $('#tbodyDeudasPendientes').html('<tr><td colspan="7" class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Buscando deudas pendientes...</td></tr>');

        fetch(URL_AJAX + `?accion=deudas_pendientes&empleador_id=${empId}`)
            .then(res => res.json())
            .then(res => {
                if (!res.success || !res.data || res.data.length === 0) {
                    $('#tbodyDeudasPendientes').html('<tr><td colspan="7" class="text-center py-3 text-warning"><i class="fas fa-check-circle me-1"></i> El empleador no tiene deudas pendientes exigibles</td></tr>');
                    deudasCargadas = [];
                    recalcularSimulacion();
                    return;
                }

                deudasCargadas = res.data;
                let html = '';
                deudasCargadas.forEach((d, idx) => {
                    html += `
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input check-deuda" data-idx="${idx}" value="${d.comprobante_id}" checked>
                            </td>
                            <td><span class="font-monospace small fw-bold">${d.comprobante_codigo || d.tipo_codigo}</span></td>
                            <td><span class="badge bg-light text-dark border">${d.concepto_codigo || d.tipo_codigo}</span></td>
                            <td><span class="font-monospace">${d.periodo || '-'}</span></td>
                            <td><small class="text-muted">${d.f_vto}</small></td>
                            <td class="text-end text-muted">${formatoMoneda(d.importe_total)}</td>
                            <td class="text-end fw-bold text-danger">${formatoMoneda(d.saldo_deuda)}</td>
                        </tr>
                    `;
                });
                $('#tbodyDeudasPendientes').html(html);
                $('#checkTodos').prop('checked', true);
                recalcularSimulacion();
            })
            .catch(err => {
                console.error("Error al consultar deudas:", err);
                $('#tbodyDeudasPendientes').html('<tr><td colspan="7" class="text-center text-danger py-3">Error al consultar deudas pendientes</td></tr>');
            });
    });

    // Checkbox "Seleccionar Todas"
    $('#checkTodos').on('change', function () {
        const estado = $(this).is(':checked');
        $('.check-deuda').prop('checked', estado);
        recalcularSimulacion();
    });

    $(document).on('change', '.check-deuda', function () {
        recalcularSimulacion();
    });

    $('#modalFechaPago1, #modalFechaPago2').on('change', function () {
        recalcularSimulacion();
    });

    // 5. Simular Cálculo en Tiempo Real
    function recalcularSimulacion() {
        const seleccionadas = [];
        $('.check-deuda:checked').each(function () {
            const idx = $(this).data('idx');
            if (deudasCargadas[idx]) {
                seleccionadas.push(deudasCargadas[idx]);
            }
        });

        if (seleccionadas.length === 0) {
            actualizarResumenTotales({
                importe_capital_1: 0, importe_interes_1: 0, importe_total_1: 0,
                importe_capital_2: 0, importe_interes_2: 0, importe_total_2: 0,
                f_pago_1: $('#modalFechaPago1').val(),
                f_pago_2: $('#modalFechaPago2').val()
            });
            $('#btnConfirmarEmision').prop('disabled', true);
            return;
        }

        const f1 = $('#modalFechaPago1').val();
        const f2 = $('#modalFechaPago2').val();

        fetch(URL_AJAX + '?accion=simular', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                deudas: seleccionadas,
                f_pago_1: f1,
                f_pago_2: f2
            })
        })
        .then(res => res.json())
        .then(res => {
            if (res.success && res.calculo) {
                actualizarResumenTotales(res.calculo);
                $('#btnConfirmarEmision').prop('disabled', false);
            }
        })
        .catch(err => console.error("Error al simular:", err));
    }

    function actualizarResumenTotales(calc) {
        $('#lblFecha1').text(calc.f_pago_1 || '-');
        $('#lblCap1').text(formatoMoneda(calc.importe_capital_1));
        $('#lblInt1').text(formatoMoneda(calc.importe_interes_1));
        $('#lblTotal1').text(formatoMoneda(calc.importe_total_1));

        $('#lblFecha2').text(calc.f_pago_2 || '-');
        $('#lblCap2').text(formatoMoneda(calc.importe_capital_2));
        $('#lblInt2').text(formatoMoneda(calc.importe_interes_2));
        $('#lblTotal2').text(formatoMoneda(calc.importe_total_2));
    }

    // 6. Confirmar Emisión del Volante
    $('#btnConfirmarEmision').on('click', function () {
        const empId = $('#modalEmpleadorId').val();
        const f1 = $('#modalFechaPago1').val();
        const f2 = $('#modalFechaPago2').val();
        const obs = $('#modalObservaciones').val();

        const compIds = [];
        $('.check-deuda:checked').each(function () {
            compIds.push($(this).val());
        });

        if (!empId || compIds.length === 0) {
            alert("Debe seleccionar al menos una deuda para emitir el volante.");
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Emitiendo...');

        fetch(URL_AJAX + '?accion=emitir', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                empleador_id: empId,
                f_pago_1: f1,
                f_pago_2: f2,
                comprobante_ids: compIds,
                observaciones: obs
            })
        })
        .then(res => res.json())
        .then(res => {
            btn.prop('disabled', false).html('<i class="fas fa-barcode me-1"></i> Emitir Volante de Pago');
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalNuevoVolante')).hide();
                if (tablaVolantes) tablaVolantes.ajax.reload();
                // Abrir visor formal con el volante recién creado
                abrirVisorVolante(res.volante_id);
            } else {
                alert(res.message || "Error al emitir volante de pago.");
            }
        })
        .catch(err => {
            btn.prop('disabled', false).html('<i class="fas fa-barcode me-1"></i> Emitir Volante de Pago');
            console.error("Error en emisión:", err);
            alert("Ocurrió un error inesperado al emitir el volante.");
        });
    });

    // 7. Ver e Imprimir Volante Oficial
    $(document).on('click', '.btn-accion-ver', function () {
        const id = $(this).data('id');
        abrirVisorVolante(id);
    });

    function abrirVisorVolante(id) {
        fetch(URL_AJAX + `?accion=obtener&volante_id=${id}`)
            .then(res => res.json())
            .then(res => {
                if (!res.success || !res.volante) {
                    alert(res.message || "No se pudo cargar el volante.");
                    return;
                }
                const v = res.volante;

                $('#printNroVolante').text(v.numero);
                $('#printFechaEmision').text(v.f_emision);
                $('#printRazonSocial').text(v.empleador_nombre);
                $('#printCuit').text(v.empleador_cuit);

                let detHtml = '';
                (v.detalles || []).forEach(d => {
                    detHtml += `
                        <tr>
                            <td><span class="font-monospace">${d.comprobante_codigo || '-'}</span></td>
                            <td>${d.concepto_descripcion || d.tipo_nombre}</td>
                            <td class="font-monospace">${d.periodo || '-'}</td>
                            <td class="text-end">${formatoMoneda(d.saldo_deuda)}</td>
                            <td class="text-end text-danger">${formatoMoneda(d.interes_1)}</td>
                            <td class="text-end fw-semibold">${formatoMoneda(d.subtotal_1)}</td>
                            <td class="text-end text-danger">${formatoMoneda(d.interes_2)}</td>
                            <td class="text-end fw-semibold">${formatoMoneda(d.subtotal_2)}</td>
                        </tr>
                    `;
                });
                $('#printTbodyDetalle').html(detHtml);

                $('#printVto1').text(v.f_pago_1);
                $('#printImporte1').text(formatoMoneda(v.importe_1));
                $('#printVto2').text(v.f_pago_2);
                $('#printImporte2').text(formatoMoneda(v.importe_2));

                $('#printCodigoBarrasStr').text(v.codigo_barras);

                // Renderizar código de barras BNA Code128 con JsBarcode
                try {
                    JsBarcode("#barcodeSvg", v.codigo_barras, {
                        format: "CODE128",
                        width: 2,
                        height: 65,
                        displayValue: false
                    });
                } catch (e) {
                    console.warn("JsBarcode render:", e);
                }

                const modal = new bootstrap.Modal(document.getElementById('modalVerVolante'));
                modal.show();
            })
            .catch(err => {
                console.error("Error al obtener volante:", err);
                alert("Error al cargar la información del volante.");
            });
    }

    // 8. Anular Volante
    $(document).on('click', '.btn-accion-anular', function () {
        const id = $(this).data('id');
        if (!confirm("¿Confirma la anulación de este volante de pago? Se despublicará de todos los canales de cobro y liberará los saldos de deuda.")) {
            return;
        }

        const formData = new FormData();
        formData.append('accion', 'anular');
        formData.append('volante_id', id);

        fetch(URL_AJAX, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    if (tablaVolantes) tablaVolantes.ajax.reload();
                } else {
                    alert(res.message || "Error al anular volante.");
                }
            })
            .catch(err => {
                console.error("Error al anular:", err);
                alert("Ocurrió un error al anular el volante.");
            });
    });

    // Iniciar
    initDataTable();
    cargarEmpleadores();
});
