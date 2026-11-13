/**
 * tesoreria_dashboard.js
 *
 * Lógica Frontend y Visualización para el Dashboard Financiero
 * Sistema gestion_multipyme - Módulo Tesorería
 */

$(document).ready(function () {
    let dashboardData = null;
    let chartMovimientos = null;
    let chartFlujo = null;
    let diasFlujoSeleccionados = 30;
    let filtroCuentasTipo = '';
    let filtroChequesCategoria = '';

    // ==============================================================
    // FORMATTERS
    // ==============================================================
    function formatearMoneda(valor, simbolo = '$') {
        const num = parseFloat(valor || 0);
        return simbolo + ' ' + Fmt.numero(num, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatearFecha(fechaStr) {
        return Fmt.fecha(fechaStr, '-');
    }

    // ==============================================================
    // CARGA DE DATOS DEL DASHBOARD
    // ==============================================================
    function cargarDashboard() {
        const sucursalId = $('#filtroSucursal').val();
        const fechaDesde = $('#movFechaDesde').val();
        const fechaHasta = $('#movFechaHasta').val();
        const cuentaId   = $('#movCuentaId').val();

        // Indicador visual de carga
        $('#btnActualizarDashboard').prop('disabled', true).find('i').addClass('fa-spin');

        $.ajax({
            url: 'tesoreria_dashboard_ajax.php',
            type: 'GET',
            dataType: 'json',
            data: {
                accion: 'obtener_todo',
                sucursal_id: sucursalId,
                desde: fechaDesde,
                hasta: fechaHasta,
                cuenta_id: cuentaId,
                dias_flujo: diasFlujoSeleccionados
            },
            success: function (res) {
                $('#btnActualizarDashboard').prop('disabled', false).find('i').removeClass('fa-spin');
                if (res.success) {
                    dashboardData = res;
                    renderizarKPIs(res);
                    renderizarCuentas(res.saldos.cuentas);
                    poblarSelectCuentas(res.saldos.cuentas);
                    renderizarMovimientos(res.movimientos);
                    renderizarFlujo(res.flujo);
                    renderizarValores(res.valores);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error al cargar dashboard',
                        text: res.error || 'Ocurrió un error inesperado al procesar los datos.'
                    });
                }
            },
            error: function (xhr) {
                $('#btnActualizarDashboard').prop('disabled', false).find('i').removeClass('fa-spin');
                console.error("Error AJAX Dashboard:", xhr.responseText);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de comunicación',
                    text: 'No se pudo conectar con el servidor para obtener los datos de tesorería.'
                });
            }
        });
    }

    // ==============================================================
    // 1. RENDERIZADO DE KPIS SUPERIORES
    // ==============================================================
    function renderizarKPIs(data) {
        const saldosTot = data.saldos.totales;
        const movTot = data.movimientos.totales;
        const valRes = data.valores.resumen;

        // KPI 1: Saldo Consolidado ARS
        $('#kpiSaldoConsolidado').text(formatearMoneda(saldosTot.total_ars));
        $('#kpiSaldoCajas').text(formatearMoneda(saldosTot.total_cajas_ars));
        $('#kpiSaldoBancos').text(formatearMoneda(saldosTot.total_bancos_ars));

        // KPI 2: Moneda Extranjera USD
        $('#kpiSaldoUSD').text(formatearMoneda(saldosTot.total_usd, 'U$S'));
        $('#kpiCotizacionUSD').text(formatearMoneda(saldosTot.tipo_cambio_usd));
        $('#kpiConsolidadoTotalARS').text(formatearMoneda(saldosTot.total_consolidado_ars));

        // KPI 3: Flujo Neto del Período
        const neto = movTot.flujo_neto;
        const $elNeto = $('#kpiFlujoNeto');
        $elNeto.text(formatearMoneda(neto));
        if (neto > 0) {
            $elNeto.removeClass('text-danger text-dark').addClass('text-success');
        } else if (neto < 0) {
            $elNeto.removeClass('text-success text-dark').addClass('text-danger');
        } else {
            $elNeto.removeClass('text-success text-danger').addClass('text-dark');
        }
        $('#kpiTotalIngresos').text(formatearMoneda(movTot.ingresos));
        $('#kpiTotalEgresos').text(formatearMoneda(movTot.egresos));

        // KPI 4: Cheques en Cartera
        $('#kpiCarteraMonto').text(formatearMoneda(valRes.total_cartera));
        $('#kpiCarteraCant').text(`${valRes.cant_cartera} cheques en cartera`);

        const $alertasChq = $('#kpiCarteraAlertas');
        if (valRes.cant_vencidos > 0) {
            $alertasChq.removeClass().addClass('badge badge-subtle-danger')
                .html(`<i class="fas fa-exclamation-circle me-1"></i>${valRes.cant_vencidos} vencido(s)`);
        } else if (valRes.cant_hoy > 0) {
            $alertasChq.removeClass().addClass('badge badge-subtle-warning')
                .html(`<i class="fas fa-clock me-1"></i>${valRes.cant_hoy} al cobro hoy`);
        } else {
            $alertasChq.removeClass().addClass('badge badge-subtle-success')
                .html('<i class="fas fa-check-circle me-1"></i>Al día');
        }

        // Auditoría / Alerta de desfasajes
        if (saldosTot.desfasajes_detectados > 0) {
            $('#alertaDesfasajeContable').removeClass('d-none');
            $('#alertaDesfasajeTexto').html(
                `Se detectaron <strong>${saldosTot.desfasajes_detectados} cuenta(s)</strong> con discrepancias entre el saldo materializado y el subdiario de tesorería. Revise la columna de Integridad en la pestaña de Cuentas.`
            );
        } else {
            $('#alertaDesfasajeContable').addClass('d-none');
        }
    }

    // ==============================================================
    // 2. RENDERIZADO DE TABLA DE CUENTAS (TAB 1)
    // ==============================================================
    function renderizarCuentas(cuentas) {
        const $tbody = $('#tbodyCuentas');
        $tbody.empty();

        const filtradas = cuentas.filter(cta => {
            if (!filtroCuentasTipo) return true;
            return cta.cuenta_tipo === filtroCuentasTipo;
        });

        if (filtradas.length === 0) {
            $tbody.html(`
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        No se encontraron cuentas activas con la configuración seleccionada.
                    </td>
                </tr>
            `);
            return;
        }

        filtradas.forEach(cta => {
            let tipoBadge = '';
            if (cta.cuenta_tipo === 'CAJA') {
                tipoBadge = '<span class="badge badge-subtle-success"><i class="fas fa-cash-register me-1"></i>Caja</span>';
            } else if (cta.cuenta_tipo === 'BANCO') {
                tipoBadge = '<span class="badge badge-subtle-primary"><i class="fas fa-university me-1"></i>Banco</span>';
            } else if (cta.cuenta_tipo === 'VALORES') {
                tipoBadge = '<span class="badge badge-subtle-warning"><i class="fas fa-money-check me-1"></i>Valores</span>';
            } else {
                tipoBadge = `<span class="badge badge-subtle-info">${cta.cuenta_tipo}</span>`;
            }

            // Datos bancarios
            let datosBancarios = '-';
            if (cta.cuenta_tipo === 'BANCO') {
                const parts = [];
                if (cta.banco) parts.push(`<strong>${cta.banco}</strong>`);
                if (cta.numero) parts.push(`<small class="text-muted">N°: ${cta.numero}</small>`);
                if (cta.cbu) parts.push(`<small class="text-muted d-block">CBU: ${cta.cbu}</small>`);
                if (cta.alias) parts.push(`<small class="text-muted">Alias: ${cta.alias}</small>`);
                datosBancarios = parts.join(' | ');
            }

            // Integridad
            let integridadHtml = '';
            let btnReconciliar = '';
            if (cta.tiene_desfasaje) {
                integridadHtml = `
                    <span class="badge badge-subtle-danger" title="Discrepancia: ${formatearMoneda(cta.diferencia_auditoria, cta.moneda_simbolo)}">
                        <i class="fas fa-times-circle me-1"></i>Desfasaje
                    </span>
                `;
                btnReconciliar = `
                    <button type="button" class="btn btn-xs btn-outline-warning btn-abrir-reconciliar"
                            data-id="${cta.tesoreria_cuenta_id}"
                            data-nombre="${cta.tesoreria_cuenta}"
                            data-actual="${cta.saldo_actual}"
                            data-libro="${cta.saldo_calculado_libro}"
                            data-dif="${cta.diferencia_auditoria}"
                            data-simbolo="${cta.moneda_simbolo}"
                            title="Reconciliar saldo materializado">
                        <i class="fas fa-sync-alt"></i> Reconciliar
                    </button>
                `;
            } else {
                integridadHtml = `
                    <span class="badge badge-subtle-success" title="Saldo materializado coincide exactamente con el libro">
                        <i class="fas fa-check-circle me-1"></i>OK
                    </span>
                `;
                btnReconciliar = `
                    <button type="button" class="btn btn-xs btn-outline-secondary btn-abrir-reconciliar"
                            data-id="${cta.tesoreria_cuenta_id}"
                            data-nombre="${cta.tesoreria_cuenta}"
                            data-actual="${cta.saldo_actual}"
                            data-libro="${cta.saldo_calculado_libro}"
                            data-dif="${cta.diferencia_auditoria}"
                            data-simbolo="${cta.moneda_simbolo}"
                            title="Auditar / Reconciliar">
                        <i class="fas fa-check"></i>
                    </button>
                `;
            }

            const tr = `
                <tr>
                    <td>${tipoBadge}</td>
                    <td>
                        <strong class="text-dark">${cta.tesoreria_cuenta}</strong>
                        <div class="small text-muted">${cta.codigo_cuenta || ''}</div>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">
                            <i class="fas fa-book me-1 text-secondary"></i>${cta.cont_codigo} - ${cta.cont_nombre}
                        </span>
                    </td>
                    <td>${datosBancarios}</td>
                    <td><small class="text-muted">${cta.sucursal_nombre || 'Corporativa'}</small></td>
                    <td class="text-end">
                        <strong class="fs-6 ${cta.saldo_actual < 0 ? 'text-danger' : 'text-dark'}">
                            ${formatearMoneda(cta.saldo_actual, cta.moneda_simbolo)}
                        </strong>
                        ${cta.permite_saldo_negativo == 1 ? '<div class="small text-muted"><i class="fas fa-shield-alt text-info me-1"></i>Admite descubierto</div>' : ''}
                    </td>
                    <td class="text-center">${integridadHtml}</td>
                    <td class="text-center">${btnReconciliar}</td>
                </tr>
            `;
            $tbody.append(tr);
        });
    }

    function poblarSelectCuentas(cuentas) {
        const $sel = $('#movCuentaId');
        const actual = $sel.val();
        $sel.find('option:not(:first)').remove();
        cuentas.forEach(c => {
            $sel.append(`<option value="${c.tesoreria_cuenta_id}">${c.tesoreria_cuenta} (${c.cuenta_tipo})</option>`);
        });
        if (actual) $sel.val(actual);
    }

    // ==============================================================
    // 3. RENDERIZADO DE MOVIMIENTOS Y GRÁFICO (TAB 2)
    // ==============================================================
    function renderizarMovimientos(movData) {
        // 1) Tabla de movimientos
        const $tbody = $('#tbodyMovimientos');
        $tbody.empty();

        if (!movData.movimientos || movData.movimientos.length === 0) {
            $tbody.html(`
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        No se registraron movimientos en el período seleccionado.
                    </td>
                </tr>
            `);
        } else {
            movData.movimientos.forEach(m => {
                const esIngreso = m.tipo_movimiento === 'INGRESO';
                const tipoBadge = esIngreso
                    ? '<span class="badge badge-subtle-success"><i class="fas fa-arrow-down me-1"></i>Ingreso</span>'
                    : '<span class="badge badge-subtle-danger"><i class="fas fa-arrow-up me-1"></i>Egreso</span>';

                const comprobanteTexto = m.comprobante_nro 
                    ? `<span class="badge bg-light text-dark border">${m.comprobante_pv ? String(m.comprobante_pv).padStart(4, '0') + '-' : ''}${String(m.comprobante_nro).padStart(8, '0')}</span>`
                    : '<span class="text-muted">-</span>';

                const tr = `
                    <tr>
                        <td>${formatearFecha(m.f_movimiento)}</td>
                        <td>${tipoBadge}</td>
                        <td><strong>${m.tesoreria_cuenta}</strong></td>
                        <td><small class="text-muted">${m.medio_pago || 'Interno / Contable'}</small></td>
                        <td>${m.concepto || '-'}</td>
                        <td>${comprobanteTexto}</td>
                        <td class="text-end fw-bold ${esIngreso ? 'text-success' : 'text-danger'}">
                            ${esIngreso ? '+' : '-'}${formatearMoneda(m.importe, m.moneda_simbolo)}
                        </td>
                    </tr>
                `;
                $tbody.append(tr);
            });
        }

        // 2) Gráfico Chart.js
        renderizarGraficoMovimientos(movData.grafico);
    }

    function renderizarGraficoMovimientos(grafico) {
        const ctx = document.getElementById('chartMovimientos');
        if (!ctx) return;

        if (chartMovimientos) {
            chartMovimientos.destroy();
        }

        chartMovimientos = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: grafico.labels || [],
                datasets: [
                    {
                        label: 'Ingresos',
                        data: grafico.ingresos || [],
                        backgroundColor: 'rgba(25, 135, 84, 0.7)',
                        borderColor: '#198754',
                        borderWidth: 1,
                        borderRadius: 4
                    },
                    {
                        label: 'Egresos',
                        data: grafico.egresos || [],
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: '#dc3545',
                        borderWidth: 1,
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ctx.dataset.label + ': ' + formatearMoneda(ctx.raw);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (val) {
                                return '$ ' + Fmt.numero(Number(val));
                            }
                        }
                    }
                }
            }
        });
    }

    // ==============================================================
    // 4. RENDERIZADO DEL FLUJO PROYECTADO (TAB 3)
    // ==============================================================
    function renderizarFlujo(flujoData) {
        // Buckets
        const b = flujoData.buckets || {};
        $('#flujoVencidoIng').text(formatearMoneda(b.vencido ? b.vencido.ingresos : 0));
        $('#flujoVencidoEgr').text(formatearMoneda(b.vencido ? b.vencido.egresos : 0));
        $('#flujo7dIng').text(formatearMoneda(b.dias_1_7 ? b.dias_1_7.ingresos : 0));
        $('#flujo7dEgr').text(formatearMoneda(b.dias_1_7 ? b.dias_1_7.egresos : 0));
        $('#flujo15dIng').text(formatearMoneda(b.dias_8_15 ? b.dias_8_15.ingresos : 0));
        $('#flujo15dEgr').text(formatearMoneda(b.dias_8_15 ? b.dias_8_15.egresos : 0));
        $('#flujo30dIng').text(formatearMoneda(b.dias_16_30 ? b.dias_16_30.ingresos : 0));
        $('#flujo30dEgr').text(formatearMoneda(b.dias_16_30 ? b.dias_16_30.egresos : 0));

        // Tabla timeline
        const $tbody = $('#tbodyFlujoTimeline');
        $tbody.empty();

        const timeline = flujoData.timeline || [];
        if (timeline.length === 0) {
            $tbody.html(`
                <tr>
                    <td colspan="5" class="text-center py-4 text-muted">
                        No hay datos de vencimientos en el horizonte proyectado.
                    </td>
                </tr>
            `);
        } else {
            timeline.forEach(item => {
                // Solo mostrar días con movimientos o cada 5 días para no sobrecargar
                if (item.ingresos > 0 || item.egresos > 0 || item.dia_indice === 0 || item.dia_indice % 7 === 0) {
                    const saldoClase = item.saldo_proyectado < 0 ? 'text-danger fw-bold' : 'text-dark fw-bold';
                    const tr = `
                        <tr>
                            <td><strong>${item.fecha_formato}</strong> <span class="text-muted small">(${item.fecha})</span></td>
                            <td><span class="badge bg-light text-muted border">${item.dia_nombre}</span></td>
                            <td class="text-end text-success">${item.ingresos > 0 ? '+' + formatearMoneda(item.ingresos) : '-'}</td>
                            <td class="text-end text-danger">${item.egresos > 0 ? '-' + formatearMoneda(item.egresos) : '-'}</td>
                            <td class="text-end ${saldoClase}">${formatearMoneda(item.saldo_proyectado)}</td>
                        </tr>
                    `;
                    $tbody.append(tr);
                }
            });
        }

        // Gráfico de línea de saldo acumulado proyectado
        renderizarGraficoFlujo(timeline);
    }

    function renderizarGraficoFlujo(timeline) {
        const ctx = document.getElementById('chartFlujo');
        if (!ctx) return;

        if (chartFlujo) {
            chartFlujo.destroy();
        }

        const labels = timeline.map(t => t.fecha_formato);
        const saldos = timeline.map(t => t.saldo_proyectado);

        chartFlujo = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Saldo Proyectado Acumulado',
                        data: saldos,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return 'Saldo Proyectado: ' + formatearMoneda(ctx.raw);
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        ticks: {
                            callback: function (val) {
                                return '$ ' + Fmt.numero(Number(val));
                            }
                        }
                    }
                }
            }
        });
    }

    // ==============================================================
    // 5. RENDERIZADO DE VALORES A DEPOSITAR (TAB 4)
    // ==============================================================
    function renderizarValores(valoresData) {
        const res = valoresData.resumen || {};
        $('#cantChqTodos').text(res.cant_cartera || 0);
        $('#cantChqVencidos').text(res.cant_vencidos || 0);
        $('#cantChqHoy').text(res.cant_hoy || 0);
        $('#cantChq7d').text(res.cant_prox7 || 0);

        const $tbody = $('#tbodyValores');
        $tbody.empty();

        const cheques = valoresData.cheques || [];
        const filtrados = cheques.filter(chq => {
            if (!filtroChequesCategoria) return true;
            return chq.categoria_cobro === filtroChequesCategoria;
        });

        if (filtrados.length === 0) {
            $tbody.html(`
                <tr>
                    <td colspan="9" class="text-center py-4 text-muted">
                        No hay cheques en cartera con el filtro de situación seleccionado.
                    </td>
                </tr>
            `);
            return;
        }

        filtrados.forEach(chq => {
            let catBadge = '';
            let diasTexto = '';
            const dias = parseInt(chq.dias_al_cobro || 0);

            if (chq.categoria_cobro === 'VENCIDO') {
                catBadge = '<span class="badge badge-subtle-danger"><i class="fas fa-exclamation-triangle me-1"></i>Vencido</span>';
                diasTexto = `<span class="text-danger fw-bold">${Math.abs(dias)} días vencido</span>`;
            } else if (chq.categoria_cobro === 'HOY') {
                catBadge = '<span class="badge badge-subtle-warning"><i class="fas fa-clock me-1"></i>Al Cobro Hoy</span>';
                diasTexto = '<strong class="text-warning">¡Hoy!</strong>';
            } else if (chq.categoria_cobro === 'PROX_7_DIAS') {
                catBadge = '<span class="badge badge-subtle-info">Próximos 7d</span>';
                diasTexto = `<span class="text-info">${dias} días</span>`;
            } else {
                catBadge = '<span class="badge badge-subtle-success">Diferido</span>';
                diasTexto = `<span class="text-muted">${dias} días</span>`;
            }

            const tr = `
                <tr>
                    <td><strong>${chq.cheque_numero}</strong></td>
                    <td>${chq.banco_nombre}</td>
                    <td>
                        <span class="fw-semibold text-dark">${chq.librador}</span>
                        ${chq.cuit ? `<div class="small text-muted">CUIT: ${chq.cuit}</div>` : ''}
                    </td>
                    <td>${formatearFecha(chq.f_emision)}</td>
                    <td><strong>${formatearFecha(chq.f_pago)}</strong></td>
                    <td class="text-center">${diasTexto}</td>
                    <td class="text-center">${catBadge}</td>
                    <td class="text-end fw-bold text-dark fs-6">${formatearMoneda(chq.importe, chq.moneda_simbolo)}</td>
                    <td class="text-center">
                        <a href="cheques_cartera.php" class="btn btn-xs btn-outline-primary" title="Gestionar en Cartera de Cheques">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </td>
                </tr>
            `;
            $tbody.append(tr);
        });
    }

    // ==============================================================
    // EVENTOS Y FILTROS
    // ==============================================================

    // Cambio de sucursal
    $('#filtroSucursal').on('change', function () {
        cargarDashboard();
    });

    // Botón refrescar
    $('#btnActualizarDashboard').on('click', function () {
        cargarDashboard();
    });

    // Filtros de tipo de cuenta (Tab 1)
    $('.btn-filtro-cta').on('click', function () {
        $('.btn-filtro-cta').removeClass('active');
        $(this).addClass('active');
        filtroCuentasTipo = $(this).data('tipo');
        if (dashboardData) {
            renderizarCuentas(dashboardData.saldos.cuentas);
        }
    });

    // Presets de fechas para movimientos (Tab 2)
    $('.btn-preset-fecha').on('click', function () {
        $('.btn-preset-fecha').removeClass('active');
        $(this).addClass('active');

        const preset = $(this).data('preset');
        const hoy = new Date();
        let desde = new Date();
        let hasta = new Date();

        if (preset === 'hoy') {
            // Hoy
        } else if (preset === 'semana') {
            const day = hoy.getDay();
            const diff = hoy.getDate() - day + (day === 0 ? -6 : 1); // lunes
            desde = new Date(hoy.setDate(diff));
            hasta = new Date();
        } else if (preset === 'mes') {
            desde = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
            hasta = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0);
        } else if (preset === '30d') {
            desde.setDate(hoy.getDate() - 30);
            hasta = new Date();
        }

        const format = d => d.toISOString().split('T')[0];
        $('#movFechaDesde').val(format(desde));
        $('#movFechaHasta').val(format(hasta));

        cargarMovimientosSolo();
    });

    // Botón filtrar movimientos
    $('#btnFiltrarMovimientos').on('click', function () {
        cargarMovimientosSolo();
    });

    function cargarMovimientosSolo() {
        const fechaDesde = $('#movFechaDesde').val();
        const fechaHasta = $('#movFechaHasta').val();
        const cuentaId   = $('#movCuentaId').val();

        $.getJSON('tesoreria_dashboard_ajax.php', {
            accion: 'obtener_movimientos',
            desde: fechaDesde,
            hasta: fechaHasta,
            cuenta_id: cuentaId
        }, function (res) {
            if (res.success) {
                renderizarMovimientos(res.data);
                // Actualizar KPI de movimientos
                const movTot = res.data.totales;
                const neto = movTot.flujo_neto;
                const $elNeto = $('#kpiFlujoNeto');
                $elNeto.text(formatearMoneda(neto));
                if (neto > 0) $elNeto.removeClass('text-danger text-dark').addClass('text-success');
                else if (neto < 0) $elNeto.removeClass('text-success text-dark').addClass('text-danger');
                else $elNeto.removeClass('text-success text-danger').addClass('text-dark');
                $('#kpiTotalIngresos').text(formatearMoneda(movTot.ingresos));
                $('#kpiTotalEgresos').text(formatearMoneda(movTot.egresos));
            }
        });
    }

    // Botones de horizonte de flujo (Tab 3)
    $('.btn-horizonte-flujo').on('click', function () {
        $('.btn-horizonte-flujo').removeClass('active');
        $(this).addClass('active');
        diasFlujoSeleccionados = parseInt($(this).data('dias') || 30);

        $.getJSON('tesoreria_dashboard_ajax.php', {
            accion: 'obtener_flujo',
            dias: diasFlujoSeleccionados
        }, function (res) {
            if (res.success) {
                renderizarFlujo(res.data);
            }
        });
    });

    // Filtros de categoría de cheques (Tab 4)
    $('.btn-filtro-chq').on('click', function () {
        $('.btn-filtro-chq').removeClass('active');
        $(this).addClass('active');
        filtroChequesCategoria = $(this).data('cat');
        if (dashboardData) {
            renderizarValores(dashboardData.valores);
        }
    });

    // Redibujar gráficos al cambiar de pestaña
    $('button[data-bs-toggle="pill"]').on('shown.bs.tab', function (e) {
        if (e.target.id === 'tab-movimientos-btn' && chartMovimientos) {
            chartMovimientos.resize();
        } else if (e.target.id === 'tab-flujo-btn' && chartFlujo) {
            chartFlujo.resize();
        }
    });

    // ==============================================================
    // RECONCILIACIÓN DE SALDO MATERIALIZADO (AUDITORÍA)
    // ==============================================================
    $(document).on('click', '.btn-abrir-reconciliar', function () {
        const ctaId     = $(this).data('id');
        const ctaNombre = $(this).data('nombre');
        const actual    = $(this).data('actual');
        const libro     = $(this).data('libro');
        const dif       = $(this).data('dif');
        const sim       = $(this).data('simbolo') || '$';

        $('#recCuentaId').val(ctaId);
        $('#recCuentaNombre').text(ctaNombre);
        $('#recSaldoActual').text(formatearMoneda(actual, sim));
        $('#recSaldoLibro').text(formatearMoneda(libro, sim));
        $('#recDiferencia').text(formatearMoneda(dif, sim));

        $('#modalReconciliarSaldo').modal('show');
    });

    $('#btnEjecutarReconciliacion').on('click', function () {
        const ctaId = $('#recCuentaId').val();
        if (!ctaId) return;

        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Reconciliando...');

        $.ajax({
            url: 'tesoreria_dashboard_ajax.php',
            type: 'POST',
            dataType: 'json',
            data: {
                accion: 'reconciliar_saldo',
                cuenta_id: ctaId
            },
            success: function (res) {
                $('#btnEjecutarReconciliacion').prop('disabled', false).html('<i class="fas fa-check me-1"></i> Aplicar Reconciliación');
                $('#modalReconciliarSaldo').modal('hide');

                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Saldo Reconciliado',
                        text: `El saldo de la cuenta fue actualizado de ${formatearMoneda(res.saldo_anterior)} a ${formatearMoneda(res.saldo_nuevo)} según el libro inmutable de tesorería.`
                    });
                    cargarDashboard();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.error || 'No se pudo reconciliar el saldo.'
                    });
                }
            },
            error: function () {
                $('#btnEjecutarReconciliacion').prop('disabled', false).html('<i class="fas fa-check me-1"></i> Aplicar Reconciliación');
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Ocurrió un error al contactar al servidor.'
                });
            }
        });
    });

    // ==============================================================
    // INICIALIZACIÓN
    // ==============================================================
    cargarDashboard();
});
