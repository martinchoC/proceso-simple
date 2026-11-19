/**
 * modules/sindicatos/planes.js
 * Lógica cliente para simulación, acuerdo y emisión de Planes de Pago.
 */

document.addEventListener('DOMContentLoaded', () => {
    const filtroEmpleador = document.getElementById('filtroEmpleador');
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroBusqueda = document.getElementById('filtroBusqueda');
    const btnRecargar = document.getElementById('btnRecargar');
    const btnNuevoPlan = document.getElementById('btnNuevoPlan');
    const tbodyPlanes = document.getElementById('tbodyPlanes');

    // Modal nuevo plan
    const modalNuevoPlan = new bootstrap.Modal(document.getElementById('modalNuevoPlan'));
    const planEmpleadorId = document.getElementById('planEmpleadorId');
    const planTipoId = document.getElementById('planTipoId');
    const planCuotas = document.getElementById('planCuotas');
    const planAnticipoPct = document.getElementById('planAnticipoPct');
    const planPrimerVto = document.getElementById('planPrimerVto');
    const planObservaciones = document.getElementById('planObservaciones');
    const btnCargarDeudas = document.getElementById('btnCargarDeudas');
    const tbodyDeudasPendientes = document.getElementById('tbodyDeudasPendientes');
    const chkSelTodas = document.getElementById('chkSelTodas');
    const lblCapitalConsolidado = document.getElementById('lblCapitalConsolidado');

    const btnSimular = document.getElementById('btnSimular');
    const simCapital = document.getElementById('simCapital');
    const simInteres = document.getElementById('simInteres');
    const simTotal = document.getElementById('simTotal');
    const tbodySimulacion = document.getElementById('tbodySimulacion');
    const btnConfirmarPlan = document.getElementById('btnConfirmarPlan');

    // Modal detalle plan
    const modalDetallePlan = new bootstrap.Modal(document.getElementById('modalDetallePlan'));
    const detallePlanTitulo = document.getElementById('detallePlanTitulo');
    const detPlanRazonSocial = document.getElementById('detPlanRazonSocial');
    const detPlanRegimen = document.getElementById('detPlanRegimen');
    const detPlanTotal = document.getElementById('detPlanTotal');
    const detPlanEstado = document.getElementById('detPlanEstado');
    const tbodyCuotasDetalle = document.getElementById('tbodyCuotasDetalle');
    const btnAnularPlan = document.getElementById('btnAnularPlan');

    let planActivoId = null;
    let capitalSeleccionado = 0.00;
    let deudasSeleccionadasIds = [];
    let simulacionActual = null;

    // Inicializar primer vto por defecto (+30 días)
    const fechaVtoDef = new Date();
    fechaVtoDef.setDate(fechaVtoDef.getDate() + 30);
    planPrimerVto.value = Fmt.isoLocal(fechaVtoDef);

    cargarAuxiliares();
    cargarPlanes();

    btnRecargar.addEventListener('click', cargarPlanes);
    filtroEmpleador.addEventListener('change', cargarPlanes);
    filtroEstado.addEventListener('change', cargarPlanes);
    filtroBusqueda.addEventListener('input', debounce(cargarPlanes, 350));

    btnNuevoPlan.addEventListener('click', () => {
        capitalSeleccionado = 0.00;
        deudasSeleccionadasIds = [];
        simulacionActual = null;
        lblCapitalConsolidado.textContent = '$0.00';
        simCapital.textContent = '$0.00';
        simInteres.textContent = '$0.00';
        simTotal.textContent = '$0.00';
        tbodySimulacion.innerHTML = `<tr><td colspan="5" class="py-5 text-muted">Complete los datos y pulse "Simular"</td></tr>`;
        tbodyDeudasPendientes.innerHTML = `<tr><td colspan="4" class="text-muted text-center py-3">Seleccione un empleador</td></tr>`;
        btnConfirmarPlan.disabled = true;
        modalNuevoPlan.show();
    });

    planEmpleadorId.addEventListener('change', cargarDeudasEmpleador);
    btnCargarDeudas.addEventListener('click', cargarDeudasEmpleador);

    function cargarAuxiliares() {
        fetch('modules/sindicatos/planes_ajax.php?action=auxiliares')
            .then(res => res.json())
            .then(res => {
                if (res.status === 'ok') {
                    // Empleadores
                    filtroEmpleador.innerHTML = '<option value="">-- Todos los empleadores --</option>';
                    planEmpleadorId.innerHTML = '<option value="">-- Seleccionar empleador --</option>';
                    res.data.empleadores.forEach(e => {
                        const opt = document.createElement('option');
                        opt.value = e.empleador_id;
                        opt.textContent = `${e.razon_social} (${e.cuit})`;
                        filtroEmpleador.appendChild(opt);

                        const opt2 = opt.cloneNode(true);
                        planEmpleadorId.appendChild(opt2);
                    });

                    // Tipos de planes
                    planTipoId.innerHTML = '<option value="">-- Seleccionar régimen --</option>';
                    res.data.tipos_planes.forEach(tp => {
                        const opt = document.createElement('option');
                        opt.value = tp.plan_tipo_id;
                        opt.textContent = `${tp.nombre} (${tp.sistema} - ${tp.tasa_mensual}% mes - Max ${tp.cuotas_max} cuotas)`;
                        opt.dataset.cuotasMax = tp.cuotas_max;
                        opt.dataset.tasa = tp.tasa_mensual;
                        opt.dataset.anticipoMin = tp.anticipo_min_porc;
                        planTipoId.appendChild(opt);
                    });
                }
            })
            .catch(err => console.error('Error cargando auxiliares:', err));
    }

    planTipoId.addEventListener('change', () => {
        const opt = planTipoId.options[planTipoId.selectedIndex];
        if (opt && opt.dataset.cuotasMax) {
            planCuotas.max = opt.dataset.cuotasMax;
            if (parseInt(planCuotas.value) > parseInt(opt.dataset.cuotasMax)) {
                planCuotas.value = opt.dataset.cuotasMax;
            }
            if (opt.dataset.anticipoMin) {
                planAnticipoPct.value = opt.dataset.anticipoMin;
            }
        }
    });

    function cargarDeudasEmpleador() {
        const empId = planEmpleadorId.value;
        if (!empId) {
            tbodyDeudasPendientes.innerHTML = `<tr><td colspan="4" class="text-muted text-center py-3">Seleccione un empleador</td></tr>`;
            return;
        }

        tbodyDeudasPendientes.innerHTML = `<tr><td colspan="4" class="text-center py-2"><i class="fas fa-spinner fa-spin"></i> Buscando deudas...</td></tr>`;

        fetch(`modules/sindicatos/planes_ajax.php?action=deudas_pendientes&empleador_id=${empId}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'ok') {
                    renderizarDeudasPendientes(res.data);
                } else {
                    tbodyDeudasPendientes.innerHTML = `<tr><td colspan="4" class="text-danger text-center py-2">${res.message}</td></tr>`;
                }
            })
            .catch(err => {
                tbodyDeudasPendientes.innerHTML = `<tr><td colspan="4" class="text-danger text-center py-2">Error al consultar deudas</td></tr>`;
            });
    }

    function renderizarDeudasPendientes(deudas) {
        if (deudas.length === 0) {
            tbodyDeudasPendientes.innerHTML = `<tr><td colspan="4" class="text-muted text-center py-2">Sin deudas exigibles pendientes</td></tr>`;
            actualizarSeleccionDeudas();
            return;
        }

        let html = '';
        deudas.forEach(d => {
            html += `
                <tr>
                    <td>
                        <input type="checkbox" class="chk-deuda" value="${d.comprobante_id}" data-saldo="${d.importe_pendiente}">
                    </td>
                    <td>
                        <span class="badge bg-secondary me-1">${d.tipo_codigo}</span> #${d.numero}
                    </td>
                    <td>${formatFecha(d.f_vto)}</td>
                    <td class="text-end fw-semibold">$${formatNumero(d.importe_pendiente)}</td>
                </tr>`;
        });
        tbodyDeudasPendientes.innerHTML = html;

        chkSelTodas.checked = false;
        chkSelTodas.onchange = () => {
            document.querySelectorAll('.chk-deuda').forEach(chk => {
                chk.checked = chkSelTodas.checked;
            });
            actualizarSeleccionDeudas();
        };

        document.querySelectorAll('.chk-deuda').forEach(chk => {
            chk.onchange = actualizarSeleccionDeudas;
        });

        actualizarSeleccionDeudas();
    }

    function actualizarSeleccionDeudas() {
        capitalSeleccionado = 0.00;
        deudasSeleccionadasIds = [];

        document.querySelectorAll('.chk-deuda:checked').forEach(chk => {
            capitalSeleccionado += parseFloat(chk.dataset.saldo || 0);
            deudasSeleccionadasIds.push(parseInt(chk.value));
        });

        lblCapitalConsolidado.textContent = `$${formatNumero(capitalSeleccionado)}`;
    }

    btnSimular.addEventListener('click', () => {
        const empId = parseInt(planEmpleadorId.value);
        const tipoId = parseInt(planTipoId.value);
        const cuotas = parseInt(planCuotas.value);
        const anticipoPct = parseFloat(planAnticipoPct.value || 0);
        const primerVto = planPrimerVto.value;

        if (!empId) { alert('Seleccione un empleador.'); return; }
        if (!tipoId) { alert('Seleccione el tipo de plan.'); return; }
        if (capitalSeleccionado <= 0) { alert('Seleccione al menos una deuda para refinanciar.'); return; }
        if (!cuotas || cuotas < 1) { alert('La cantidad de cuotas debe ser mayor a cero.'); return; }

        btnSimular.disabled = true;
        btnSimular.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Calculando...';

        const formData = new FormData();
        formData.append('action', 'simular');
        formData.append('plan_tipo_id', tipoId);
        formData.append('capital', capitalSeleccionado);
        formData.append('cuotas', cuotas);
        formData.append('anticipo_pct', anticipoPct);
        formData.append('f_primer_vto', primerVto);

        fetch('modules/sindicatos/planes_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            btnSimular.disabled = false;
            btnSimular.innerHTML = '<i class="fas fa-calculator me-1"></i> Simular Cronograma de Cuotas';

            if (res.status === 'ok') {
                simulacionActual = res.data;
                renderizarSimulacion(res.data);
                btnConfirmarPlan.disabled = false;
            } else {
                alert('Error: ' + res.message);
            }
        })
        .catch(err => {
            btnSimular.disabled = false;
            btnSimular.innerHTML = '<i class="fas fa-calculator me-1"></i> Simular Cronograma de Cuotas';
            console.error(err);
            alert('Error al simular plan');
        });
    });

    function renderizarSimulacion(sim) {
        simCapital.textContent = `$${formatNumero(sim.capital_original)}`;
        simInteres.textContent = `$${formatNumero(sim.interes_financiero)}`;
        simTotal.textContent = `$${formatNumero(sim.total_financiado)}`;

        let html = '';
        sim.cronograma.forEach(c => {
            const esAnticipo = c.numero_cuota === 0;
            html += `
                <tr ${esAnticipo ? 'class="table-warning"' : ''}>
                    <td class="fw-bold">${esAnticipo ? 'Anticipo' : 'Cuota ' + c.numero_cuota}</td>
                    <td>${formatFecha(c.f_vto)}</td>
                    <td class="text-end">$${formatNumero(c.capital)}</td>
                    <td class="text-end text-danger">$${formatNumero(c.interes)}</td>
                    <td class="text-end fw-bold">$${formatNumero(c.total_cuota)}</td>
                </tr>`;
        });
        tbodySimulacion.innerHTML = html;
    }

    btnConfirmarPlan.addEventListener('click', () => {
        if (!simulacionActual) return;
        if (!confirm('¿Confirma el acuerdo de plan de pagos? Esta acción cancelará las deudas originales y generará las cuotas como nuevos comprobantes de refinanciación (PLC).')) {
            return;
        }

        btnConfirmarPlan.disabled = true;
        btnConfirmarPlan.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Confirmando...';

        const formData = new FormData();
        formData.append('action', 'crear_y_confirmar');
        formData.append('empleador_id', planEmpleadorId.value);
        formData.append('plan_tipo_id', planTipoId.value);
        formData.append('capital_original', simulacionActual.capital_original);
        formData.append('cantidad_cuotas', simulacionActual.cantidad_cuotas);
        formData.append('anticipo_pct', planAnticipoPct.value);
        formData.append('f_primer_vto', planPrimerVto.value);
        formData.append('observaciones', planObservaciones.value);
        formData.append('comprobantes_origen_ids', JSON.stringify(deudasSeleccionadasIds));

        fetch('modules/sindicatos/planes_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            btnConfirmarPlan.disabled = false;
            btnConfirmarPlan.innerHTML = '<i class="fas fa-check-double me-1"></i> Confirmar y Refinanciar Deuda';

            if (res.status === 'ok') {
                alert(res.message);
                modalNuevoPlan.hide();
                cargarPlanes();
                abrirDetallePlan(res.data.plan_id);
            } else {
                alert('Error: ' + res.message);
            }
        })
        .catch(err => {
            btnConfirmarPlan.disabled = false;
            btnConfirmarPlan.innerHTML = '<i class="fas fa-check-double me-1"></i> Confirmar y Refinanciar Deuda';
            console.error(err);
            alert('Error al confirmar plan');
        });
    });

    function cargarPlanes() {
        tbodyPlanes.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-2 text-primary"></i><br>Cargando...</td></tr>`;

        const params = new URLSearchParams({
            action: 'listar',
            empleador_id: filtroEmpleador.value,
            estado_id: filtroEstado.value,
            busqueda: filtroBusqueda.value.trim()
        });

        fetch(`modules/sindicatos/planes_ajax.php?${params}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'ok') {
                    renderizarPlanes(res.data);
                } else {
                    tbodyPlanes.innerHTML = `<tr><td colspan="10" class="text-danger py-4 text-center">${res.message}</td></tr>`;
                }
            })
            .catch(err => {
                tbodyPlanes.innerHTML = `<tr><td colspan="10" class="text-danger py-4 text-center">Error al consultar el servidor</td></tr>`;
            });
    }

    function renderizarPlanes(lista) {
        if (lista.length === 0) {
            tbodyPlanes.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-muted">No se encontraron planes de pago</td></tr>`;
            return;
        }

        let html = '';
        lista.forEach(p => {
            let badgeClass = 'bg-secondary';
            switch (parseInt(p.tabla_estado_registro_id)) {
                case 3: badgeClass = 'bg-warning text-dark'; break;
                case 5: badgeClass = 'bg-success'; break;
                case 39: badgeClass = 'bg-info text-dark'; break;
                case 40: badgeClass = 'bg-primary'; break;
                case 38: badgeClass = 'bg-danger'; break;
            }

            html += `
                <tr>
                    <td><strong class="text-primary">${escapeHtml(p.numero_plan)}</strong></td>
                    <td>
                        <div class="fw-bold">${escapeHtml(p.razon_social)}</div>
                        <div class="small text-muted">CUIT: ${escapeHtml(p.cuit)}</div>
                    </td>
                    <td><span class="badge bg-light text-dark border">${escapeHtml(p.tipo_plan_nombre)}</span></td>
                    <td>${formatFecha(p.f_acuerdo)}</td>
                    <td class="text-end fw-semibold">$${formatNumero(p.capital_original)}</td>
                    <td class="text-end text-danger">$${formatNumero(p.interes_financiero)}</td>
                    <td class="text-end fw-bold fs-6">$${formatNumero(p.total_financiado)}</td>
                    <td class="text-center">
                        <span class="badge bg-secondary">${p.cuotas_pagadas} / ${p.cuotas_totales}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge ${badgeClass}">${escapeHtml(p.estado_nombre || 'Desconocido')}</span>
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="abrirDetallePlan(${p.plan_id})" title="Ver detalle de cuotas">
                            <i class="fas fa-eye me-1"></i> Ver
                        </button>
                    </td>
                </tr>`;
        });
        tbodyPlanes.innerHTML = html;
    }

    window.abrirDetallePlan = function(planId) {
        planActivoId = planId;
        tbodyCuotasDetalle.innerHTML = `<tr><td colspan="7" class="py-4 text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Cargando...</td></tr>`;
        modalDetallePlan.show();

        fetch(`modules/sindicatos/planes_ajax.php?action=obtener&plan_id=${planId}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'ok') {
                    const p = res.data;
                    detallePlanTitulo.innerHTML = `<i class="fas fa-handshake me-2"></i>Plan ${escapeHtml(p.numero_plan)}`;
                    detPlanRazonSocial.textContent = p.razon_social;
                    detPlanRegimen.textContent = `${p.tipo_plan_nombre} (${p.sistema})`;
                    detPlanTotal.textContent = `$${formatNumero(p.total_financiado)}`;
                    detPlanEstado.textContent = p.estado_nombre || 'Confirmado';

                    renderizarCuotasDetalle(p.cuotas || []);
                } else {
                    alert('Error: ' + res.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Error al consultar el plan');
            });
    };

    function renderizarCuotasDetalle(cuotas) {
        let html = '';
        cuotas.forEach(c => {
            const esAnticipo = c.numero_cuota === 0;
            html += `
                <tr ${esAnticipo ? 'class="table-warning"' : ''}>
                    <td class="fw-bold">${esAnticipo ? 'Anticipo' : 'Cuota ' + c.numero_cuota}</td>
                    <td>${formatFecha(c.f_vto)}</td>
                    <td class="text-end">$${formatNumero(c.capital)}</td>
                    <td class="text-end text-danger">$${formatNumero(c.interes)}</td>
                    <td class="text-end fw-semibold">$${formatNumero(c.total_cuota)}</td>
                    <td class="text-end text-primary fw-bold">$${formatNumero(c.saldo_pendiente)}</td>
                    <td>
                        <span class="badge bg-light text-dark border">
                            <i class="fas fa-file-invoice me-1"></i>PLC #${c.comprobante_numero || c.comprobante_id}
                        </span>
                    </td>
                </tr>`;
        });
        tbodyCuotasDetalle.innerHTML = html;
    }

    btnAnularPlan.addEventListener('click', () => {
        const motivo = prompt('Ingrese el motivo de anulación del plan:');
        if (!motivo) return;

        const formData = new FormData();
        formData.append('action', 'anular');
        formData.append('plan_id', planActivoId);
        formData.append('motivo', motivo);

        fetch('modules/sindicatos/planes_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'ok') {
                alert(res.message);
                modalDetallePlan.hide();
                cargarPlanes();
            } else {
                alert('Error: ' + res.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error al anular plan');
        });
    });

    function formatNumero(num) {
        return Number(num || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatFecha(fecha) {
        if (!fecha) return '-';
        const p = fecha.split('-');
        if (p.length === 3) return `${p[2]}/${p[1]}/${p[0]}`;
        return fecha;
    }

    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
});
