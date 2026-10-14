/**
 * modules/sindicatos/fiscalizacion_tablero.js
 * Lógica de cliente para el Tablero de Fiscalización y Matriz de Cumplimiento.
 */

document.addEventListener('DOMContentLoaded', () => {
    const kpiTotalEmp = document.getElementById('kpiTotalEmp');
    const kpiPuntuales = document.getElementById('kpiPuntuales');
    const kpiMoraMedia = document.getElementById('kpiMoraMedia');
    const kpiMoraGrave = document.getElementById('kpiMoraGrave');

    const filtroBusqueda = document.getElementById('filtroBusqueda');
    const filtroMora = document.getElementById('filtroMora');
    const filtroPeriodoDesde = document.getElementById('filtroPeriodoDesde');
    const filtroPeriodoHasta = document.getElementById('filtroPeriodoHasta');

    const btnFiltrar = document.getElementById('btnFiltrar');
    const btnRecalcularMora = document.getElementById('btnRecalcularMora');
    const theadMatriz = document.getElementById('theadMatriz');
    const tbodyMatriz = document.getElementById('tbodyMatriz');
    const badgeTotalMatriz = document.getElementById('badgeTotalMatriz');

    const modalOmitidos = new bootstrap.Modal(document.getElementById('modalOmitidos'));
    const tbodyOmitidos = document.getElementById('tbodyOmitidos');

    // Inicializar fechas por defecto (últimos 6 meses)
    const fechaActual = new Date();
    const pActual = fechaActual.getFullYear() * 100 + (fechaActual.getMonth() + 1);
    
    const fechaPasada = new Date();
    fechaPasada.setMonth(fechaPasada.getMonth() - 5);
    const pDesde = fechaPasada.getFullYear() * 100 + (fechaPasada.getMonth() + 1);

    filtroPeriodoHasta.value = pActual;
    filtroPeriodoDesde.value = pDesde;

    cargarKpis();
    cargarCategoriasMora();
    cargarMatriz();

    btnFiltrar.addEventListener('click', cargarMatriz);
    btnRecalcularMora.addEventListener('click', ejecutarRecalculoMora);

    function cargarKpis() {
        fetch('modules/sindicatos/fiscalizacion_tablero_ajax.php?action=kpis')
            .then(res => res.json())
            .then(res => {
                if (res.status === 'ok') {
                    const d = res.data;
                    kpiTotalEmp.textContent = d.total_empleadores;
                    kpiPuntuales.textContent = d.puntuales;
                    kpiMoraMedia.textContent = d.mora_leve_media;
                    kpiMoraGrave.textContent = d.mora_grave_cronica;
                }
            })
            .catch(err => console.error('Error cargando KPIs:', err));
    }

    function cargarCategoriasMora() {
        fetch('modules/sindicatos/fiscalizacion_tablero_ajax.php?action=categorias_mora')
            .then(res => res.json())
            .then(res => {
                if (res.status === 'ok') {
                    filtroMora.innerHTML = '<option value="">-- Todas las categorías --</option>';
                    res.data.forEach(c => {
                        const opt = document.createElement('option');
                        opt.value = c.categoria_mora_id;
                        opt.textContent = c.nombre;
                        filtroMora.appendChild(opt);
                    });
                }
            })
            .catch(err => console.error('Error cargando categorias mora:', err));
    }

    function cargarMatriz() {
        tbodyMatriz.innerHTML = `
            <tr>
                <td colspan="12" class="py-5 text-muted">
                    <i class="fas fa-spinner fa-spin fa-2x mb-2 text-primary"></i><br>Consultando cumplimiento...
                </td>
            </tr>`;

        const params = new URLSearchParams({
            action: 'matriz',
            busqueda: filtroBusqueda.value.trim(),
            categoria_mora_id: filtroMora.value,
            periodo_desde: filtroPeriodoDesde.value,
            periodo_hasta: filtroPeriodoHasta.value
        });

        fetch(`modules/sindicatos/fiscalizacion_tablero_ajax.php?${params}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'ok') {
                    renderizarMatriz(res.data);
                } else {
                    tbodyMatriz.innerHTML = `<tr><td colspan="12" class="text-danger py-4">${res.message}</td></tr>`;
                }
            })
            .catch(err => {
                console.error(err);
                tbodyMatriz.innerHTML = `<tr><td colspan="12" class="text-danger py-4">Error al conectar con el servidor</td></tr>`;
            });
    }

    function renderizarMatriz(data) {
        const periodos = data.periodos || [];
        const empleadores = data.empleadores || [];

        badgeTotalMatriz.textContent = `${empleadores.length} empleadores visualizados`;

        // Render encabezado
        let thHtml = `
            <tr>
                <th class="text-start bg-light" style="min-width: 260px;">Empleador / Razón Social</th>
                <th class="bg-light" style="min-width: 130px;">Categoría Mora</th>`;
        periodos.forEach(p => {
            const pStr = p.toString();
            const y = pStr.substring(0, 4);
            const m = pStr.substring(4, 6);
            thHtml += `<th class="bg-light" style="min-width: 90px;">${m}/${y}</th>`;
        });
        thHtml += `</tr>`;
        theadMatriz.innerHTML = thHtml;

        // Render cuerpo
        if (empleadores.length === 0) {
            tbodyMatriz.innerHTML = `<tr><td colspan="${periodos.length + 2}" class="text-muted py-5">No se encontraron empleadores con los filtros seleccionados</td></tr>`;
            return;
        }

        let bodyHtml = '';
        empleadores.forEach(emp => {
            bodyHtml += `
                <tr>
                    <td class="text-start">
                        <div class="fw-bold text-dark">${escapeHtml(emp.razon_social)}</div>
                        <div class="small text-muted">CUIT: ${escapeHtml(emp.cuit)}</div>
                    </td>
                    <td>
                        <span class="badge" style="background-color: ${emp.mora_color || '#6c757d'}; color: #fff;">
                            ${escapeHtml(emp.mora_nombre)}
                        </span>
                    </td>`;

            periodos.forEach(per => {
                const info = emp.periodos[per] || { situacion: 'SIN_REGISTRO', total: 0 };
                let badgeClass = 'bg-secondary';
                let icono = 'fa-minus';
                let label = 'S/D';

                switch (info.situacion) {
                    case 'PRESENTADA':
                        badgeClass = 'bg-success';
                        icono = 'fa-check';
                        label = `$${formatNumero(info.total)}`;
                        break;
                    case 'PENDIENTE':
                        badgeClass = 'bg-warning text-dark';
                        icono = 'fa-clock';
                        label = 'Pendiente';
                        break;
                    case 'FUERA_DE_TERMINO':
                        badgeClass = 'bg-danger';
                        icono = 'fa-exclamation-triangle';
                        label = 'En Mora';
                        break;
                    case 'OFICIO':
                        badgeClass = 'bg-info text-dark';
                        icono = 'fa-gavel';
                        label = 'Oficio';
                        break;
                    default:
                        badgeClass = 'bg-light text-muted border';
                        icono = 'fa-ban';
                        label = '-';
                        break;
                }

                bodyHtml += `
                    <td>
                        <span class="badge ${badgeClass} py-1 px-2 d-inline-block w-100" 
                              style="cursor: pointer;"
                              title="Período: ${per} | Estado: ${info.situacion}"
                              onclick="verDetallePeriodo(${emp.empleador_id}, ${per}, '${info.situacion}')">
                            <i class="fas ${icono} me-1"></i>${label}
                        </span>
                    </td>`;
            });

            bodyHtml += `</tr>`;
        });

        tbodyMatriz.innerHTML = bodyHtml;
    }

    function ejecutarRecalculoMora() {
        if (!confirm('¿Desea recalcular la categoría de mora para todos los empleadores según sus días de atraso actuales?')) {
            return;
        }

        btnRecalcularMora.disabled = true;
        btnRecalcularMora.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Recalculando...';

        fetch('modules/sindicatos/fiscalizacion_tablero_ajax.php?action=recalcular_mora')
            .then(res => res.json())
            .then(res => {
                btnRecalcularMora.disabled = false;
                btnRecalcularMora.innerHTML = '<i class="fas fa-calculator me-1"></i> Recalcular Mora';
                if (res.status === 'ok') {
                    alert(res.message);
                    cargarKpis();
                    cargarMatriz();
                } else {
                    alert('Error: ' + res.message);
                }
            })
            .catch(err => {
                btnRecalcularMora.disabled = false;
                btnRecalcularMora.innerHTML = '<i class="fas fa-calculator me-1"></i> Recalcular Mora';
                console.error(err);
                alert('Error al comunicar con el servidor');
            });
    }

    window.verDetallePeriodo = function(empleadorId, periodo, situacion) {
        if (situacion === 'FUERA_DE_TERMINO' || situacion === 'PENDIENTE') {
            if (confirm(`El período ${periodo} se encuentra ${situacion}. ¿Desea consultar posibles trabajadores omitidos (ddjj_empleados_out)?`)) {
                consultarOmitidos(empleadorId, periodo);
            }
        } else if (situacion === 'PRESENTADA') {
            consultarOmitidos(empleadorId, periodo);
        }
    };

    function consultarOmitidos(empleadorId, periodo) {
        tbodyOmitidos.innerHTML = `<tr><td colspan="5" class="py-4 text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Analizando nómina...</td></tr>`;
        modalOmitidos.show();

        fetch(`modules/sindicatos/fiscalizacion_tablero_ajax.php?action=detectar_omitidos&empleador_id=${empleadorId}&periodo=${periodo}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'ok') {
                    const list = res.data;
                    if (list.length === 0) {
                        tbodyOmitidos.innerHTML = `<tr><td colspan="5" class="py-4 text-center text-success"><i class="fas fa-check-circle me-1"></i> No se detectaron discrepancias ni trabajadores omitidos sin justificar.</td></tr>`;
                    } else {
                        let html = '';
                        list.forEach(o => {
                            html += `
                                <tr>
                                    <td><span class="badge bg-secondary">${escapeHtml(o.cuil)}</span></td>
                                    <td class="fw-bold">${escapeHtml(o.nombre_completo)}</td>
                                    <td>${escapeHtml(o.categoria_nombre)}</td>
                                    <td class="text-end">$${formatNumero(o.ultima_remuneracion)}</td>
                                    <td class="small text-danger"><i class="fas fa-exclamation-circle me-1"></i>${escapeHtml(o.motivo_alerta)}</td>
                                </tr>`;
                        });
                        tbodyOmitidos.innerHTML = html;
                    }
                } else {
                    tbodyOmitidos.innerHTML = `<tr><td colspan="5" class="py-4 text-center text-danger">${res.message}</td></tr>`;
                }
            })
            .catch(err => {
                tbodyOmitidos.innerHTML = `<tr><td colspan="5" class="py-4 text-center text-danger">Error de comunicación con el servidor</td></tr>`;
            });
    }

    function formatNumero(num) {
        return Number(num || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
});
