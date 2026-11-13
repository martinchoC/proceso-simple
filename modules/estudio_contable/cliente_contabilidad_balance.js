/**
 * Solapa Balance de Contabilidad del Cliente (423) - Módulo 4 Estudio Contable.
 * Copia adaptada de gestion_normal/cont__balances.js (la misma vista que Gestión > Balance) sobre la empresa del cliente:
 * pide 'balance' a cliente_contabilidad_ajax.php con los filtros de la página (cliente, desde, hasta, borradores).
 * cliente_contabilidad.js llama a CnBalance.cargar(params) al abrir la solapa o cambiar el filtro.
 * El botón de mayor de Sumas y Saldos llama a CnBalance.alAbrirMayor(cuenta) (lo fija cliente_contabilidad.js).
 */

window.CnBalance = (function () {
    'use strict';

    let datosBalance = null;
    let modoListado = 'analitico'; // 'analitico', 'arbol', 'sintetico_nivel1', 'sintetico_nivel2'
    let chartEstructura = null;
    let chartMensual = null;
    let periodo = { desde: '', hasta: '' };
    const api = { cargar: cargarBalance, alAbrirMayor: null };

    function esc(t) { return $('<div>').text(t == null ? '' : String(t)).html(); }

    function formatearMoneda(valor) {
        const num = parseFloat(valor) || 0;
        return '$ ' + Fmt.numero(num, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // =========================================================================
    // Carga del balance de la empresa del cliente
    // =========================================================================
    function cargarBalance(params) {
        periodo = { desde: params.desde, hasta: params.hasta };
        mostrarCargando();
        return $.getJSON('cliente_contabilidad_ajax.php', Object.assign({ accion: 'balance' }, params))
            .done(function (r) {
                if (!r || !r.success) {
                    UiSimple.error((r && r.message) || 'Ocurrió un error inesperado.', 'No se pudo calcular el balance');
                    return;
                }
                const d = r.data || {};
                datosBalance = {
                    periodo: d.periodo || { fecha_desde: periodo.desde, fecha_hasta: periodo.hasta },
                    cuentas: d.cuentas || [],
                    kpis: d.kpis || {},
                    totales_sumas_saldos: d.totales || {},
                    graficos: d.graficos || {},
                    desglose: d.desglose || {},
                    auditoria: d.auditoria || {}
                };
                renderizarKPIs(datosBalance.kpis, datosBalance.totales_sumas_saldos);
                renderizarGraficos(datosBalance.graficos, datosBalance.kpis);
                renderizarDesglose(datosBalance.desglose);
                renderizarAuditoria(datosBalance.auditoria);
                renderizarSumasSaldos();
                renderizarEstadoSituacion();
                renderizarEstadoResultados();
            })
            .fail(function () { UiSimple.error('Revisá la conexión e intentá de nuevo.', 'No se pudo cargar'); });
    }

    function mostrarCargando() {
        $('#tbodySumasSaldos').html(`
            <tr>
                <td colspan="10" class="text-center py-4 text-muted">
                    <i class="fas fa-spinner fa-spin me-2 text-primary"></i>Calculando balance y sumas y saldos...
                </td>
            </tr>
        `);
        $('#kpiActivoTotal, #kpiPasivoTotal, #kpiPatrimonioNeto, #kpiResultadoEjercicio').text('...');
    }

    // =========================================================================
    // Renderizado de KPIs Principales
    // =========================================================================
    function renderizarKPIs(kpis, totales) {
        if (!kpis) return;

        // Activo
        $('#kpiActivoTotal').text(formatearMoneda(kpis.total_activo));
        $('#kpiActivoCte').text(formatearMoneda(kpis.total_activo_corriente));
        $('#kpiActivoNoCte').text(formatearMoneda(kpis.total_activo_no_corriente));

        // Pasivo
        $('#kpiPasivoTotal').text(formatearMoneda(kpis.total_pasivo));
        $('#kpiPasivoCte').text(formatearMoneda(kpis.total_pasivo_corriente));
        $('#kpiPasivoNoCte').text(formatearMoneda(kpis.total_pasivo_no_corriente));

        // Patrimonio Neto
        $('#kpiPatrimonioNeto').text(formatearMoneda(kpis.total_patrimonio_neto_total));
        $('#kpiPatrimonioBase').text(formatearMoneda(kpis.total_patrimonio_neto_base));

        // Resultado del Ejercicio
        const resEj = kpis.resultado_ejercicio;
        const $elRes = $('#kpiResultadoEjercicio');
        const $badgeRes = $('#badgeEstadoResultado');
        const $iconRes = $('#kpiIconResultado');

        $elRes.text(formatearMoneda(resEj));

        if (resEj > 0) {
            $elRes.removeClass('text-danger text-dark').addClass('text-success');
            $badgeRes.removeClass('bg-danger-subtle text-danger bg-secondary-subtle text-secondary')
                     .addClass('bg-success-subtle text-success').text('Ganancia');
            $iconRes.removeClass('bg-danger-subtle text-danger').addClass('bg-success-subtle text-success');
        } else if (resEj < 0) {
            $elRes.removeClass('text-success text-dark').addClass('text-danger');
            $badgeRes.removeClass('bg-success-subtle text-success bg-secondary-subtle text-secondary')
                     .addClass('bg-danger-subtle text-danger').text('Pérdida');
            $iconRes.removeClass('bg-success-subtle text-success').addClass('bg-danger-subtle text-danger');
        } else {
            $elRes.removeClass('text-success text-danger').addClass('text-dark');
            $badgeRes.removeClass('bg-success-subtle text-success bg-danger-subtle text-danger')
                     .addClass('bg-secondary-subtle text-secondary').text('Equilibrado');
        }

        $('#kpiMargenNeto').text((kpis.margen_neto_pct || 0) + '%');

        // Ecuación Patrimonial
        const diffEcuacion = Math.abs(kpis.diferencia_ecuacion || 0);
        const $badgeEcuacion = $('#badgeEcuacionCuadre');
        const $textoEcuacion = $('#textoEcuacionDetalle');

        if (diffEcuacion < 0.01) {
            $badgeEcuacion.removeClass('badge-cuadre-error').addClass('badge-cuadre-ok')
                          .html('<i class="fas fa-check-circle"></i> Ecuación Cuadrada (A = P + PN)');
            $textoEcuacion.text('Diferencia: $ 0,00 (Verificado)').removeClass('text-danger fw-bold').addClass('text-muted');
        } else {
            $badgeEcuacion.removeClass('badge-cuadre-ok').addClass('badge-cuadre-error')
                          .html('<i class="fas fa-exclamation-triangle"></i> Descuadre en Ecuación');
            $textoEcuacion.text('Diferencia: ' + formatearMoneda(kpis.diferencia_ecuacion)).removeClass('text-muted').addClass('text-danger fw-bold');
        }
    }

    // =========================================================================
    // Renderizado de Gráficos (Chart.js)
    // =========================================================================
    function renderizarGraficos(graficos, kpis) {
        if (!graficos) return;

        // 1. Gráfico Estructura Patrimonial (Doughnut)
        const canvasEstructura = document.getElementById('chartEstructuraPatrimonial');
        if (canvasEstructura) {
            if (chartEstructura) {
                chartEstructura.destroy();
            }

            const activoCte = Math.max(0, kpis.total_activo_corriente || 0);
            const activoNoCte = Math.max(0, kpis.total_activo_no_corriente || 0);
            const pasivoCte = Math.max(0, kpis.total_pasivo_corriente || 0);
            const pasivoNoCte = Math.max(0, kpis.total_pasivo_no_corriente || 0);
            const pnTotal = Math.max(0, kpis.total_patrimonio_neto_total || 0);

            chartEstructura = new Chart(canvasEstructura, {
                type: 'doughnut',
                data: {
                    labels: ['Activo Corriente', 'Activo No Cte.', 'Pasivo Corriente', 'Pasivo No Cte.', 'Patrimonio Neto'],
                    datasets: [{
                        data: [activoCte, activoNoCte, pasivoCte, pasivoNoCte, pnTotal],
                        backgroundColor: [
                            '#0d6efd', // Azul Activo Cte
                            '#6ea8fe', // Celeste Activo No Cte
                            '#dc3545', // Rojo Pasivo Cte
                            '#ea868f', // Rosa Pasivo No Cte
                            '#0dcaf0'  // Turquesa PN
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                font: { size: 11 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return context.label + ': ' + formatearMoneda(context.raw);
                                }
                            }
                        }
                    },
                    cutout: '65%'
                }
            });
        }

        // 2. Gráfico Evolución Mensual (Bar Chart)
        const canvasMensual = document.getElementById('chartEvolucionMensual');
        if (canvasMensual) {
            if (chartMensual) {
                chartMensual.destroy();
            }

            const ev = graficos.evolucion_mensual || { labels: [], ingresos: [], egresos: [] };

            chartMensual = new Chart(canvasMensual, {
                type: 'bar',
                data: {
                    labels: ev.labels.length > 0 ? ev.labels : ['Sin datos'],
                    datasets: [
                        {
                            label: 'Ingresos ($)',
                            data: ev.ingresos.length > 0 ? ev.ingresos : [0],
                            backgroundColor: 'rgba(25, 135, 84, 0.75)',
                            borderColor: '#198754',
                            borderWidth: 1,
                            borderRadius: 4
                        },
                        {
                            label: 'Egresos (Costos + Gastos)',
                            data: ev.egresos.length > 0 ? ev.egresos : [0],
                            backgroundColor: 'rgba(220, 53, 69, 0.75)',
                            borderColor: '#dc3545',
                            borderWidth: 1,
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (value) {
                                    return '$ ' + Fmt.numero(value);
                                },
                                font: { size: 10 }
                            }
                        },
                        x: {
                            ticks: { font: { size: 10 } }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: { boxWidth: 12, font: { size: 11 } }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return context.dataset.label + ': ' + formatearMoneda(context.raw);
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    // =========================================================================
    // Renderizado de Desglose de Rubros
    // =========================================================================
    function renderizarDesglose(desglose) {
        if (!desglose) return;

        // Rubros Activo
        const $listaActivo = $('#listaRubrosActivo');
        $listaActivo.empty();
        if (desglose.rubros_activo && desglose.rubros_activo.length > 0) {
            desglose.rubros_activo.forEach(function (r) {
                $listaActivo.append(`
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <div>
                            <span class="badge bg-light text-dark border me-1">${esc(r.codigo)}</span>
                            <span>${esc(r.nombre)}</span>
                        </div>
                        <span class="font-monospace-nums fw-bold text-primary">${formatearMoneda(r.saldo)}</span>
                    </div>
                `);
            });
        } else {
            $listaActivo.html('<div class="text-muted text-center py-2">Sin movimientos de activo</div>');
        }

        // Rubros Pasivo
        const $listaPasivo = $('#listaRubrosPasivo');
        $listaPasivo.empty();
        if (desglose.rubros_pasivo && desglose.rubros_pasivo.length > 0) {
            desglose.rubros_pasivo.forEach(function (r) {
                $listaPasivo.append(`
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <div>
                            <span class="badge bg-light text-dark border me-1">${esc(r.codigo)}</span>
                            <span>${esc(r.nombre)}</span>
                        </div>
                        <span class="font-monospace-nums fw-bold text-danger">${formatearMoneda(r.saldo)}</span>
                    </div>
                `);
            });
        } else {
            $listaPasivo.html('<div class="text-muted text-center py-2">Sin movimientos de pasivo</div>');
        }
    }

    // =========================================================================
    // Renderizado de Diagnóstico y Auditoría
    // =========================================================================
    function renderizarAuditoria(auditoria) {
        if (!auditoria) return;

        $('#auditoriaRegistrados').text(auditoria.asientos_registrados || 0);
        $('#auditoriaBorradores').text(auditoria.asientos_borradores || 0);

        const descuadrados = auditoria.asientos_descuadrados || 0;
        const $badgeDesc = $('#auditoriaDescuadrados');
        $badgeDesc.text(descuadrados);
        if (descuadrados > 0) {
            $badgeDesc.removeClass('bg-secondary-subtle text-secondary').addClass('bg-danger-subtle text-danger fw-bold');
        } else {
            $badgeDesc.removeClass('bg-danger-subtle text-danger').addClass('bg-success-subtle text-success');
        }

        const saldoContrario = (auditoria.cuentas_saldo_contrario && auditoria.cuentas_saldo_contrario.length) || 0;
        const $badgeContrario = $('#auditoriaSaldoContrario');
        $badgeContrario.text(saldoContrario);
        if (saldoContrario > 0) {
            $badgeContrario.removeClass('bg-info-subtle text-info').addClass('bg-warning-subtle text-warning fw-bold');
        }
    }

    // =========================================================================
    // Renderizado del Listado de Sumas y Saldos
    // =========================================================================
    function renderizarSumasSaldos() {
        if (!datosBalance || !Array.isArray(datosBalance.cuentas)) return;

        const soloMov = $('#checkSoloMovimientos').is(':checked');
        const busqueda = ($('#buscadorSumasSaldos').val() || '').toLowerCase().trim();
        const $tbody = $('#tbodySumasSaldos');
        $tbody.empty();

        let cantCuentas = 0;
        let sumAntDebe = 0;
        let sumAntHaber = 0;
        let sumPerDebe = 0;
        let sumPerHaber = 0;
        let sumSaldosDeudor = 0;
        let sumSaldosAcreedor = 0;

        datosBalance.cuentas.forEach(function (c) {
            // Filtro de Solo con Movimientos
            if (soloMov && !c.tiene_movimientos) {
                return;
            }

            // Filtro por Modo de Listado
            if (modoListado === 'analitico' && c.es_imputable != 1) {
                return;
            } else if (modoListado === 'sintetico_nivel1' && c.nivel != 1) {
                return;
            } else if (modoListado === 'sintetico_nivel2' && c.nivel > 2) {
                return;
            }

            // Filtro por Buscador de Texto
            if (busqueda !== '') {
                const coincideCod = c.codigo.toLowerCase().includes(busqueda);
                const coincideNombre = c.nombre.toLowerCase().includes(busqueda);
                if (!coincideCod && !coincideNombre) {
                    return;
                }
            }

            cantCuentas++;

            // Clases visuales según nivel
            let trClass = '';
            if (c.nivel === 1) trClass = 'cuenta-nivel-1 table-primary';
            else if (c.nivel === 2) trClass = 'cuenta-nivel-2';
            else if (c.nivel === 3) trClass = 'cuenta-nivel-3';

            const indentClass = `indent-nivel-${Math.min(c.nivel, 5)}`;
            const badgeImputable = (c.es_imputable == 1) 
                ? '<span class="badge bg-light text-muted border me-1">Imp</span>' 
                : '<span class="badge bg-secondary-subtle text-secondary me-1">Rubro</span>';

            const badgeNat = (c.naturaleza === 'D')
                ? '<span class="badge bg-primary-subtle text-primary">D</span>'
                : '<span class="badge bg-danger-subtle text-danger">H</span>';

            // Acumular totales de las cuentas imputables (para evitar duplicación en modo árbol)
            if (c.es_imputable == 1 || modoListado === 'sintetico_nivel1') {
                sumAntDebe += c.saldo_ant_debe;
                sumAntHaber += c.saldo_ant_haber;
                sumPerDebe += c.periodo_debe;
                sumPerHaber += c.periodo_haber;
                sumSaldosDeudor += c.saldo_deudor;
                sumSaldosAcreedor += c.saldo_acreedor;
            }

            const btnMayor = (c.es_imputable == 1) ? `
                <button type="button" class="btn btn-xs btn-outline-primary cn-abrir-mayor" data-cuenta="${c.cont_cuenta_id}" title="Abrir Mayor de ${esc(c.nombre)}">
                    <i class="fas fa-book"></i>
                </button>
            ` : '';

            $tbody.append(`
                <tr class="${trClass}">
                    <td class="font-monospace-nums fw-bold">${esc(c.codigo)}</td>
                    <td class="${indentClass}">
                        ${badgeImputable}
                        <span>${esc(c.nombre)}</span>
                    </td>
                    <td class="text-center small text-muted">${c.nivel}</td>
                    <td class="text-center">${badgeNat}</td>
                    <td class="text-end font-monospace-nums">${formatearMoneda(c.saldo_ant_neto)}</td>
                    <td class="text-end font-monospace-nums">${c.periodo_debe > 0 ? formatearMoneda(c.periodo_debe) : '—'}</td>
                    <td class="text-end font-monospace-nums">${c.periodo_haber > 0 ? formatearMoneda(c.periodo_haber) : '—'}</td>
                    <td class="text-end font-monospace-nums text-success fw-bold">${c.saldo_deudor > 0 ? formatearMoneda(c.saldo_deudor) : '—'}</td>
                    <td class="text-end font-monospace-nums text-danger fw-bold">${c.saldo_acreedor > 0 ? formatearMoneda(c.saldo_acreedor) : '—'}</td>
                    <td class="text-center no-print">${btnMayor}</td>
                </tr>
            `);
        });

        if (cantCuentas === 0) {
            $tbody.html(`
                <tr>
                    <td colspan="10" class="text-center py-4 text-muted">
                        <i class="fas fa-info-circle me-1"></i>No se encontraron cuentas con los filtros seleccionados.
                    </td>
                </tr>
            `);
        }

        $('#lblCantCuentas').text(cantCuentas);

        // Actualizar Footer de Totales y Cuadres
        const tot = datosBalance.totales_sumas_saldos;
        const debeMostrar = (modoListado === 'analitico' || modoListado === 'arbol') ? tot.total_periodo_debe : sumPerDebe;
        const haberMostrar = (modoListado === 'analitico' || modoListado === 'arbol') ? tot.total_periodo_haber : sumPerHaber;
        const deudorMostrar = (modoListado === 'analitico' || modoListado === 'arbol') ? tot.total_saldos_deudores : sumSaldosDeudor;
        const acreedorMostrar = (modoListado === 'analitico' || modoListado === 'arbol') ? tot.total_saldos_acreedores : sumSaldosAcreedor;

        $('#totFooterDebe').text(formatearMoneda(debeMostrar));
        $('#totFooterHaber').text(formatearMoneda(haberMostrar));
        $('#totFooterDeudor').text(formatearMoneda(deudorMostrar));
        $('#totFooterAcreedor').text(formatearMoneda(acreedorMostrar));

        const diffSumas = Math.abs(debeMostrar - haberMostrar);
        const diffSaldos = Math.abs(deudorMostrar - acreedorMostrar);

        $('#diffSumasDebeHaber').html(`Debe - Haber: <strong>${formatearMoneda(debeMostrar - haberMostrar)}</strong>`);
        $('#diffSaldosDeudorAcreedor').html(`Deudor - Acreedor: <strong>${formatearMoneda(deudorMostrar - acreedorMostrar)}</strong>`);

        if (diffSumas < 0.01 && diffSaldos < 0.01) {
            $('#bannerCuadreSumasSaldos').html(`
                <span class="badge-cuadre-ok">
                    <i class="fas fa-check-circle"></i> Balance de Comprobación de Sumas y Saldos VERIFICADO Y CUADRADO
                </span>
            `);
        } else {
            $('#bannerCuadreSumasSaldos').html(`
                <span class="badge-cuadre-error">
                    <i class="fas fa-exclamation-triangle"></i> Descuadre en Sumas y Saldos (Diferencia detectada)
                </span>
            `);
        }
    }

    // =========================================================================
    // Renderizado del Estado de Situación Patrimonial
    // =========================================================================
    function renderizarEstadoSituacion() {
        if (!datosBalance) return;

        const kpis = datosBalance.kpis;
        const cuentas = datosBalance.cuentas;

        // 1. Activo
        const $tbActivo = $('#tbodyBalanceActivo');
        $tbActivo.empty();

        $tbActivo.append(`
            <tr class="table-light fw-bold">
                <td colspan="2" class="p-2 text-primary">ACTIVO CORRIENTE</td>
            </tr>
        `);

        cuentas.filter(c => c.nivel === 3 && c.codigo.startsWith('1.1') && c.tiene_movimientos).forEach(c => {
            $tbActivo.append(`
                <tr>
                    <td class="ps-4">${esc(c.codigo)} - ${esc(c.nombre)}</td>
                    <td class="text-end font-monospace-nums fw-bold">${formatearMoneda(c.saldo_deudor)}</td>
                </tr>
            `);
        });

        $tbActivo.append(`
            <tr class="border-top fw-bold text-muted small">
                <td class="ps-3">Subtotal Activo Corriente</td>
                <td class="text-end font-monospace-nums text-primary">${formatearMoneda(kpis.total_activo_corriente)}</td>
            </tr>
            <tr class="table-light fw-bold mt-2">
                <td colspan="2" class="p-2 text-primary">ACTIVO NO CORRIENTE</td>
            </tr>
        `);

        cuentas.filter(c => c.nivel === 3 && c.codigo.startsWith('1.2') && c.tiene_movimientos).forEach(c => {
            $tbActivo.append(`
                <tr>
                    <td class="ps-4">${esc(c.codigo)} - ${esc(c.nombre)}</td>
                    <td class="text-end font-monospace-nums fw-bold">${formatearMoneda(c.saldo_deudor)}</td>
                </tr>
            `);
        });

        $tbActivo.append(`
            <tr class="border-top fw-bold text-muted small">
                <td class="ps-3">Subtotal Activo No Corriente</td>
                <td class="text-end font-monospace-nums text-primary">${formatearMoneda(kpis.total_activo_no_corriente)}</td>
            </tr>
        `);

        $('#totalSituacionActivo').text(formatearMoneda(kpis.total_activo));

        // 2. Pasivo
        const $tbPasivo = $('#tbodyBalancePasivo');
        $tbPasivo.empty();

        $tbPasivo.append(`
            <tr class="table-light fw-bold">
                <td colspan="2" class="p-2 text-danger">PASIVO CORRIENTE</td>
            </tr>
        `);

        cuentas.filter(c => c.nivel === 3 && c.codigo.startsWith('2.1') && c.tiene_movimientos).forEach(c => {
            $tbPasivo.append(`
                <tr>
                    <td class="ps-4">${esc(c.codigo)} - ${esc(c.nombre)}</td>
                    <td class="text-end font-monospace-nums fw-bold">${formatearMoneda(c.saldo_acreedor)}</td>
                </tr>
            `);
        });

        $tbPasivo.append(`
            <tr class="border-top fw-bold text-muted small">
                <td class="ps-3">Subtotal Pasivo Corriente</td>
                <td class="text-end font-monospace-nums text-danger">${formatearMoneda(kpis.total_pasivo_corriente)}</td>
            </tr>
        `);

        if (kpis.total_pasivo_no_corriente > 0) {
            $tbPasivo.append(`
                <tr class="table-light fw-bold">
                    <td colspan="2" class="p-2 text-danger">PASIVO NO CORRIENTE</td>
                </tr>
                <tr class="border-top fw-bold text-muted small">
                    <td class="ps-3">Subtotal Pasivo No Corriente</td>
                    <td class="text-end font-monospace-nums text-danger">${formatearMoneda(kpis.total_pasivo_no_corriente)}</td>
                </tr>
            `);
        }

        $('#totalSituacionPasivo').text(formatearMoneda(kpis.total_pasivo));

        // 3. Patrimonio Neto
        const $tbPN = $('#tbodyBalancePN');
        $tbPN.empty();

        $tbPN.append(`
            <tr>
                <td class="ps-3">Capital Social y Reservas</td>
                <td class="text-end font-monospace-nums">${formatearMoneda(kpis.total_patrimonio_neto_base)}</td>
            </tr>
            <tr class="table-warning-subtle fw-bold">
                <td class="ps-3">
                    <i class="fas fa-coins me-1 text-warning"></i> Resultado del Ejercicio
                </td>
                <td class="text-end font-monospace-nums ${kpis.resultado_ejercicio >= 0 ? 'text-success' : 'text-danger'}">
                    ${formatearMoneda(kpis.resultado_ejercicio)}
                </td>
            </tr>
        `);

        $('#totalSituacionPN').text(formatearMoneda(kpis.total_patrimonio_neto_total));
        $('#totalSituacionPasivoPN').text(formatearMoneda(kpis.pasivo_mas_patrimonio));
    }

    // =========================================================================
    // Renderizado del Estado de Resultados (P y G)
    // =========================================================================
    function renderizarEstadoResultados() {
        if (!datosBalance) return;

        const kpis = datosBalance.kpis;
        const cuentas = datosBalance.cuentas;

        $('#pygTotalIngresos').text(formatearMoneda(kpis.total_ingresos));
        $('#pygTotalCostos').text(formatearMoneda(kpis.total_costos));
        $('#pygResultadoBruto').text(formatearMoneda(kpis.resultado_bruto));
        $('#pygTotalGastos').text(formatearMoneda(kpis.total_gastos));

        const $resNeto = $('#pygResultadoNeto');
        $resNeto.text(formatearMoneda(kpis.resultado_ejercicio));
        if (kpis.resultado_ejercicio >= 0) {
            $resNeto.removeClass('text-danger').addClass('text-success');
            $('#lblTextoResultadoNeto').text('(=) RESULTADO NETO DEL EJERCICIO (GANANCIA)');
        } else {
            $resNeto.removeClass('text-success').addClass('text-danger');
            $('#lblTextoResultadoNeto').text('(=) RESULTADO NETO DEL EJERCICIO (PÉRDIDA)');
        }

        // Desglose de cuentas de Ingresos (4)
        const $tbIng = $('#tbodyDetalleIngresos');
        $tbIng.empty();
        cuentas.filter(c => (c.nivel === 3 || (c.nivel === 2 && c.es_imputable == 1)) && c.codigo.startsWith('4') && c.tiene_movimientos).forEach(c => {
            $tbIng.append(`
                <tr>
                    <td style="width: 120px;" class="font-monospace-nums text-muted ps-4">${esc(c.codigo)}</td>
                    <td>${esc(c.nombre)}</td>
                    <td class="text-end font-monospace-nums">${formatearMoneda(c.saldo_acreedor)}</td>
                </tr>
            `);
        });

        // Desglose de Costos (5)
        const $tbCostos = $('#tbodyDetalleCostos');
        $tbCostos.empty();
        cuentas.filter(c => (c.nivel === 3 || (c.nivel === 2 && c.es_imputable == 1)) && c.codigo.startsWith('5') && c.tiene_movimientos).forEach(c => {
            $tbCostos.append(`
                <tr>
                    <td style="width: 120px;" class="font-monospace-nums text-muted ps-4">${esc(c.codigo)}</td>
                    <td>${esc(c.nombre)}</td>
                    <td class="text-end font-monospace-nums">${formatearMoneda(c.saldo_deudor)}</td>
                </tr>
            `);
        });

        // Desglose de Gastos (6)
        const $tbGastos = $('#tbodyDetalleGastos');
        $tbGastos.empty();
        cuentas.filter(c => (c.nivel === 3 || (c.nivel === 2 && c.es_imputable == 1)) && c.codigo.startsWith('6') && c.tiene_movimientos).forEach(c => {
            $tbGastos.append(`
                <tr>
                    <td style="width: 120px;" class="font-monospace-nums text-muted ps-4">${esc(c.codigo)}</td>
                    <td>${esc(c.nombre)}</td>
                    <td class="text-end font-monospace-nums">${formatearMoneda(c.saldo_deudor)}</td>
                </tr>
            `);
        });
    }

    // =========================================================================
    // Botones de Modos del Listado de Sumas y Saldos
    $('.btn-modo-listado').on('click', function () {
        $('.btn-modo-listado').removeClass('active');
        $(this).addClass('active');
        modoListado = $(this).data('modo');
        renderizarSumasSaldos();
    });

    $('#checkSoloMovimientos').on('change', renderizarSumasSaldos);
    $('#buscadorSumasSaldos').on('input', renderizarSumasSaldos);
    $('#btnLimpiarBusqueda').on('click', function () {
        $('#buscadorSumasSaldos').val('');
        renderizarSumasSaldos();
    });

    // Mayor de una cuenta imputable: lo abre la solapa Mayores de esta página
    $('#tbodySumasSaldos').on('click', '.cn-abrir-mayor', function () {
        const id = parseInt($(this).data('cuenta'), 10);
        const c = (datosBalance && datosBalance.cuentas || []).find(function (x) { return Number(x.cont_cuenta_id) === id; });
        if (c && typeof api.alAbrirMayor === 'function') { api.alAbrirMayor(c); }
    });

    // Exportar a Excel (CSV con BOM)
    $('#btnExportarExcel').on('click', function () {
        if (!datosBalance || !datosBalance.cuentas) {
            UiSimple.aviso('No hay datos cargados para exportar.');
            return;
        }

        const fDesde = periodo.desde;
        const fHasta = periodo.hasta;
        const soloMov = $('#checkSoloMovimientos').is(':checked');

        let csv = '\uFEFF'; // BOM UTF-8
        csv += 'BALANCE DE COMPROBACIÓN DE SUMAS Y SALDOS\n';
        csv += `Período: ${fDesde} al ${fHasta}\n`;
        csv += `Fecha de Emisión: ${Fmt.fecha(new Date())}\n\n`;
        csv += 'Código;Cuenta Contable;Nivel;Naturaleza;Saldo Anterior;Suma Debe;Suma Haber;Saldo Deudor;Saldo Acreedor\n';

        datosBalance.cuentas.forEach(function (c) {
            if (soloMov && !c.tiene_movimientos) return;
            if (modoListado === 'analitico' && c.es_imputable != 1) return;
            if (modoListado === 'sintetico_nivel1' && c.nivel != 1) return;
            if (modoListado === 'sintetico_nivel2' && c.nivel > 2) return;

            const fila = [
                `"${c.codigo}"`,
                `"${c.nombre.replace(/"/g, '""')}"`,
                c.nivel,
                c.naturaleza,
                c.saldo_ant_neto.toFixed(2).replace('.', ','),
                c.periodo_debe.toFixed(2).replace('.', ','),
                c.periodo_haber.toFixed(2).replace('.', ','),
                c.saldo_deudor.toFixed(2).replace('.', ','),
                c.saldo_acreedor.toFixed(2).replace('.', ',')
            ];
            csv += fila.join(';') + '\n';
        });

        // Totales al pie
        const tot = datosBalance.totales_sumas_saldos;
        csv += `\n"";"TOTALES DE CONTROL";"";"";"";"${tot.total_periodo_debe.toFixed(2).replace('.', ',')}";"${tot.total_periodo_haber.toFixed(2).replace('.', ',')}";"${tot.total_saldos_deudores.toFixed(2).replace('.', ',')}";"${tot.total_saldos_acreedores.toFixed(2).replace('.', ',')}"\n`;

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `sumas_y_saldos_${fDesde}_${fHasta}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });

    // Imprimir Balance
    $('#btnImprimirBalance').on('click', function () {
        window.print();
    });

    return api;
})();
