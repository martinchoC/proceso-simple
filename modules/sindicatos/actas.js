/**
 * modules/sindicatos/actas.js
 * Lógica cliente para la gestión de Actas de Inspección Sindical.
 */

document.addEventListener('DOMContentLoaded', () => {
    const filtroEmpleador = document.getElementById('filtroEmpleador');
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroBusqueda = document.getElementById('filtroBusqueda');
    const btnRecargar = document.getElementById('btnRecargar');
    const btnNuevaActa = document.getElementById('btnNuevaActa');
    const tbodyActas = document.getElementById('tbodyActas');

    // Modales
    const modalNuevaActa = new bootstrap.Modal(document.getElementById('modalNuevaActa'));
    const formNuevaActa = document.getElementById('formNuevaActa');
    const nuevoEmpleadorId = document.getElementById('nuevoEmpleadorId');
    const nuevoInspectorId = document.getElementById('nuevoInspectorId');
    const nuevoFInicio = document.getElementById('nuevoFInicio');

    const modalDetalleActa = new bootstrap.Modal(document.getElementById('modalDetalleActa'));
    const detalleActaTitulo = document.getElementById('detalleActaTitulo');
    const detRazonSocial = document.getElementById('detRazonSocial');
    const detCuit = document.getElementById('detCuit');
    const detTipo = document.getElementById('detTipo');
    const detFInicio = document.getElementById('detFInicio');
    const detF72h = document.getElementById('detF72h');
    const detTotalActa = document.getElementById('detTotalActa');
    const tbodyPeriodosActa = document.getElementById('tbodyPeriodosActa');

    const cardAgregarPeriodo = document.getElementById('cardAgregarPeriodo');
    const itemPeriodo = document.getElementById('itemPeriodo');
    const itemDetFondo = document.getElementById('itemDetFondo');
    const itemDetCuota = document.getElementById('itemDetCuota');
    const itemPagado = document.getElementById('itemPagado');
    const btnAgregarPeriodo = document.getElementById('btnAgregarPeriodo');

    const btnConfirmarActa = document.getElementById('btnConfirmarActa');
    const btnAnularActa = document.getElementById('btnAnularActa');

    let actaActivaId = null;
    let estadoActaActiva = null;

    // Inicializar fecha
    nuevoFInicio.value = Fmt.hoyISO();

    cargarAuxiliares();
    cargarActas();

    btnRecargar.addEventListener('click', cargarActas);
    filtroEmpleador.addEventListener('change', cargarActas);
    filtroEstado.addEventListener('change', cargarActas);
    filtroBusqueda.addEventListener('input', debounce(cargarActas, 350));

    btnNuevaActa.addEventListener('click', () => {
        formNuevaActa.reset();
        nuevoFInicio.value = Fmt.hoyISO();
        modalNuevaActa.show();
    });

    formNuevaActa.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(formNuevaActa);
        formData.append('action', 'crear');

        fetch('modules/sindicatos/actas_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'ok') {
                modalNuevaActa.hide();
                cargarActas();
                abrirDetalleActa(res.acta_id);
            } else {
                alert('Error: ' + res.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error de conexión con el servidor');
        });
    });

    function cargarAuxiliares() {
        fetch('modules/sindicatos/actas_ajax.php?action=auxiliares')
            .then(res => res.json())
            .then(res => {
                if (res.status === 'ok') {
                    // Llenar empleadores
                    filtroEmpleador.innerHTML = '<option value="">-- Todos los empleadores --</option>';
                    nuevoEmpleadorId.innerHTML = '<option value="">-- Seleccionar empleador --</option>';
                    res.data.empleadores.forEach(e => {
                        const optF = document.createElement('option');
                        optF.value = e.empleador_id;
                        optF.textContent = `${e.razon_social} (${e.cuit})`;
                        filtroEmpleador.appendChild(optF);

                        const optN = optF.cloneNode(true);
                        nuevoEmpleadorId.appendChild(optN);
                    });

                    // Llenar inspectores
                    nuevoInspectorId.innerHTML = '<option value="">-- Sin inspector asignado --</option>';
                    res.data.inspectores.forEach(i => {
                        const optI = document.createElement('option');
                        optI.value = i.usuario_id;
                        optI.textContent = i.usuario_nombre || i.email;
                        nuevoInspectorId.appendChild(optI);
                    });
                }
            })
            .catch(err => console.error('Error cargando auxiliares:', err));
    }

    function cargarActas() {
        tbodyActas.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-2 text-primary"></i><br>Cargando...</td></tr>`;

        const params = new URLSearchParams({
            action: 'listar',
            empleador_id: filtroEmpleador.value,
            estado_id: filtroEstado.value,
            busqueda: filtroBusqueda.value.trim()
        });

        fetch(`modules/sindicatos/actas_ajax.php?${params}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'ok') {
                    renderizarActas(res.data);
                } else {
                    tbodyActas.innerHTML = `<tr><td colspan="10" class="text-danger py-4 text-center">${res.message}</td></tr>`;
                }
            })
            .catch(err => {
                tbodyActas.innerHTML = `<tr><td colspan="10" class="text-danger py-4 text-center">Error al consultar el servidor</td></tr>`;
            });
    }

    function renderizarActas(lista) {
        if (lista.length === 0) {
            tbodyActas.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-muted">No se encontraron actas de inspección</td></tr>`;
            return;
        }

        let html = '';
        lista.forEach(a => {
            let badgeClass = 'bg-secondary';
            switch (parseInt(a.tabla_estado_registro_id)) {
                case 3: badgeClass = 'bg-warning text-dark'; break;
                case 18: badgeClass = 'bg-info text-dark'; break;
                case 5: badgeClass = 'bg-success'; break;
                case 40: badgeClass = 'bg-primary'; break;
                case 38: badgeClass = 'bg-danger'; break;
            }

            html += `
                <tr>
                    <td><strong class="text-primary">${escapeHtml(a.numero_acta)}</strong></td>
                    <td>
                        <div class="fw-bold">${escapeHtml(a.razon_social)}</div>
                        <div class="small text-muted">CUIT: ${escapeHtml(a.cuit)}</div>
                    </td>
                    <td><span class="badge bg-light text-dark border">${escapeHtml(a.tipo_acta)}</span></td>
                    <td>${formatFecha(a.f_inicio)}</td>
                    <td>${escapeHtml(a.inspector_nombre || 'Sin asignar')}</td>
                    <td class="text-end fw-semibold">$${formatNumero(a.total_diferencia)}</td>
                    <td class="text-end text-danger">$${formatNumero(a.total_intereses)}</td>
                    <td class="text-end fw-bold fs-6">$${formatNumero(a.total_acta)}</td>
                    <td class="text-center">
                        <span class="badge ${badgeClass}">${escapeHtml(a.estado_nombre || 'Desconocido')}</span>
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="abrirDetalleActa(${a.acta_id})" title="Ver detalle o agregar períodos">
                            <i class="fas fa-eye me-1"></i> Ver
                        </button>
                    </td>
                </tr>`;
        });

        tbodyActas.innerHTML = html;
    }

    window.abrirDetalleActa = function(actaId) {
        actaActivaId = actaId;
        tbodyPeriodosActa.innerHTML = `<tr><td colspan="8" class="py-4 text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Cargando...</td></tr>`;
        modalDetalleActa.show();

        fetch(`modules/sindicatos/actas_ajax.php?action=obtener&acta_id=${actaId}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'ok') {
                    const a = res.data;
                    estadoActaActiva = parseInt(a.tabla_estado_registro_id);

                    detalleActaTitulo.innerHTML = `<i class="fas fa-file-contract me-2"></i>Acta ${escapeHtml(a.numero_acta)}`;
                    detRazonSocial.textContent = a.razon_social;
                    detCuit.textContent = a.cuit;
                    detTipo.textContent = a.tipo_acta;
                    detFInicio.textContent = formatFecha(a.f_inicio);
                    detF72h.textContent = a.f_vencimiento_72h ? formatFechaHora(a.f_vencimiento_72h) : 'Sin plazo';
                    detTotalActa.textContent = `$${formatNumero(a.total_acta)}`;

                    // Si ya está confirmada o anulada, deshabilitar edición
                    if (estadoActaActiva === 5 || estadoActaActiva === 40 || estadoActaActiva === 38) {
                        cardAgregarPeriodo.style.display = 'none';
                        btnConfirmarActa.style.display = 'none';
                        btnAnularActa.style.display = (estadoActaActiva === 38) ? 'none' : 'inline-block';
                    } else {
                        cardAgregarPeriodo.style.display = 'block';
                        btnConfirmarActa.style.display = 'inline-block';
                        btnAnularActa.style.display = 'inline-block';
                    }

                    renderizarPeriodosActa(a.periodos || []);
                } else {
                    alert('Error: ' + res.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Error al obtener el acta');
            });
    };

    function renderizarPeriodosActa(periodos) {
        if (periodos.length === 0) {
            tbodyPeriodosActa.innerHTML = `<tr><td colspan="8" class="py-4 text-muted">Aún no se cargaron períodos inspeccionados para esta acta.</td></tr>`;
            return;
        }

        let html = '';
        periodos.forEach(p => {
            html += `
                <tr>
                    <td class="fw-bold">${p.periodo}</td>
                    <td>${formatFecha(p.f_vto_original)}</td>
                    <td class="text-end">$${formatNumero(p.total_declarado)}</td>
                    <td class="text-end">$${formatNumero(p.total_determinado)}</td>
                    <td class="text-end text-success">$${formatNumero(p.total_pagado)}</td>
                    <td class="text-end fw-semibold">$${formatNumero(p.saldo_capital)}</td>
                    <td class="text-end text-danger">$${formatNumero(p.intereses)}</td>
                    <td class="text-end fw-bold">$${formatNumero(p.total_periodo)}</td>
                </tr>`;
        });
        tbodyPeriodosActa.innerHTML = html;
    }

    btnAgregarPeriodo.addEventListener('click', () => {
        const periodo = parseInt(itemPeriodo.value);
        if (!periodo || periodo < 200001 || periodo > 209912) {
            alert('Ingrese un período válido en formato AAAAMM (ej. 202601).');
            return;
        }

        const detFondo = parseFloat(itemDetFondo.value || 0);
        const detCuota = parseFloat(itemDetCuota.value || 0);
        const pagado = parseFloat(itemPagado.value || 0);

        if (detFondo <= 0 && detCuota <= 0) {
            alert('Debe indicar al menos un importe determinado.');
            return;
        }

        // Armar items para conceptos 1 (Fondo Social) y 2 (Cuota Sindical)
        const items = [
            { concepto_id: 1, declarado: 0, determinado: detFondo, pagado: pagado > 0 ? (pagado / 2) : 0 },
            { concepto_id: 2, declarado: 0, determinado: detCuota, pagado: pagado > 0 ? (pagado / 2) : 0 }
        ];

        btnAgregarPeriodo.disabled = true;
        btnAgregarPeriodo.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Liquidando...';

        const formData = new FormData();
        formData.append('action', 'agregar_periodo');
        formData.append('acta_id', actaActivaId);
        formData.append('periodo', periodo);
        formData.append('items', JSON.stringify(items));

        fetch('modules/sindicatos/actas_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            btnAgregarPeriodo.disabled = false;
            btnAgregarPeriodo.innerHTML = '<i class="fas fa-check me-1"></i> Liquidar';

            if (res.status === 'ok') {
                itemPeriodo.value = '';
                itemDetFondo.value = '';
                itemDetCuota.value = '';
                itemPagado.value = '0.00';
                abrirDetalleActa(actaActivaId);
                cargarActas();
            } else {
                alert('Error: ' + res.message);
            }
        })
        .catch(err => {
            btnAgregarPeriodo.disabled = false;
            btnAgregarPeriodo.innerHTML = '<i class="fas fa-check me-1"></i> Liquidar';
            console.error(err);
            alert('Error al comunicar con el servidor');
        });
    });

    btnConfirmarActa.addEventListener('click', () => {
        if (!confirm('¿Confirma el acta de inspección? Esta acción emitirá la deuda exigible formal (comprobante ACT) e impactará en la contabilidad.')) {
            return;
        }

        btnConfirmarActa.disabled = true;
        btnConfirmarActa.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Confirmando...';

        const formData = new FormData();
        formData.append('action', 'confirmar');
        formData.append('acta_id', actaActivaId);

        fetch('modules/sindicatos/actas_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            btnConfirmarActa.disabled = false;
            btnConfirmarActa.innerHTML = '<i class="fas fa-check-double me-1"></i> Confirmar Acta y Emitir Deuda (ACT)';

            if (res.status === 'ok') {
                alert(res.message);
                abrirDetalleActa(actaActivaId);
                cargarActas();
            } else {
                alert('Error: ' + res.message);
            }
        })
        .catch(err => {
            btnConfirmarActa.disabled = false;
            btnConfirmarActa.innerHTML = '<i class="fas fa-check-double me-1"></i> Confirmar Acta y Emitir Deuda (ACT)';
            console.error(err);
            alert('Error al comunicar con el servidor');
        });
    });

    btnAnularActa.addEventListener('click', () => {
        const motivo = prompt('Ingrese el motivo de anulación del acta:');
        if (!motivo) return;

        const formData = new FormData();
        formData.append('action', 'anular');
        formData.append('acta_id', actaActivaId);
        formData.append('motivo', motivo);

        fetch('modules/sindicatos/actas_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'ok') {
                alert(res.message);
                modalDetalleActa.hide();
                cargarActas();
            } else {
                alert('Error: ' + res.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error al anular acta');
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

    function formatFechaHora(fh) {
        if (!fh) return '-';
        const parts = fh.split(' ');
        return `${formatFecha(parts[0])} ${parts[1] || ''}`;
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
