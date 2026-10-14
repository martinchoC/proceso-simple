/**
 * modules/sindicatos/ddjj_oficio.js
 * Lógica cliente para la gestión de DDJJ de Oficio.
 */

document.addEventListener('DOMContentLoaded', () => {
    const filtroPeriodo = document.getElementById('filtroPeriodo');
    const busquedaOmisos = document.getElementById('busquedaOmisos');
    const btnBuscarOmisos = document.getElementById('btnBuscarOmisos');
    const tbodyOmisos = document.getElementById('tbodyOmisos');
    const badgeTotalOmisos = document.getElementById('badgeTotalOmisos');
    const tbodyEmitidas = document.getElementById('tbodyEmitidas');

    const tabEmitidasBtn = document.getElementById('tabEmitidas-btn');

    // Inicializar período (mes actual)
    const fecha = new Date();
    const pActual = fecha.getFullYear() * 100 + (fecha.getMonth() + 1);
    filtroPeriodo.value = pActual;

    let listaOmisosGlobal = [];

    btnBuscarOmisos.addEventListener('click', cargarOmisos);
    busquedaOmisos.addEventListener('input', filtrarOmisos);
    tabEmitidasBtn.addEventListener('click', cargarEmitidas);

    cargarOmisos();

    function cargarOmisos() {
        const periodo = parseInt(filtroPeriodo.value);
        if (!periodo || periodo < 200001 || periodo > 209912) {
            alert('Ingrese un período válido en formato AAAAMM (ej. 202610).');
            return;
        }

        tbodyOmisos.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-2 text-primary"></i><br>Consultando empleadores omisos...</td></tr>`;

        fetch(`modules/sindicatos/ddjj_oficio_ajax.php?action=listar_omisos&periodo=${periodo}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'ok') {
                    listaOmisosGlobal = res.data;
                    filtrarOmisos();
                } else {
                    tbodyOmisos.innerHTML = `<tr><td colspan="6" class="text-danger py-4 text-center">${res.message}</td></tr>`;
                }
            })
            .catch(err => {
                console.error(err);
                tbodyOmisos.innerHTML = `<tr><td colspan="6" class="text-danger py-4 text-center">Error al conectar con el servidor</td></tr>`;
            });
    }

    function filtrarOmisos() {
        const term = busquedaOmisos.value.trim().toLowerCase();
        const filtrados = listaOmisosGlobal.filter(o => {
            return o.razon_social.toLowerCase().includes(term) || o.cuit.includes(term);
        });

        badgeTotalOmisos.textContent = `${filtrados.length} encontrados`;

        if (filtrados.length === 0) {
            tbodyOmisos.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted">No se encontraron empleadores omisos en este período</td></tr>`;
            return;
        }

        let html = '';
        filtrados.forEach(o => {
            html += `
                <tr>
                    <td class="fw-bold text-dark">${escapeHtml(o.razon_social)}</td>
                    <td><span class="badge bg-light text-dark border">${escapeHtml(o.cuit)}</span></td>
                    <td>
                        <span class="badge" style="background-color: ${o.mora_color || '#6c757d'}; color: #fff;">
                            ${escapeHtml(o.mora_nombre || 'Sin clasificar')}
                        </span>
                    </td>
                    <td class="text-center fw-semibold">${o.empleados_activos} trabajadores</td>
                    <td class="text-center">
                        <span class="badge ${o.situacion === 'FUERA_DE_TERMINO' ? 'bg-danger' : 'bg-warning text-dark'}">
                            ${o.situacion || 'PENDIENTE'}
                        </span>
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="generarOficio(${o.empleador_id}, ${filtroPeriodo.value}, false)" title="Generar borrador de DDJJ de oficio">
                            <i class="fas fa-file-invoice me-1"></i> Generar Oficio
                        </button>
                        <button type="button" class="btn btn-danger btn-sm ms-1" onclick="generarOficio(${o.empleador_id}, ${filtroPeriodo.value}, true)" title="Generar y confirmar deuda de inmediato">
                            <i class="fas fa-gavel me-1"></i> Liquidar y Exigir
                        </button>
                    </td>
                </tr>`;
        });
        tbodyOmisos.innerHTML = html;
    }

    window.generarOficio = function(empleadorId, periodo, confirmar) {
        const accionTexto = confirmar ? "determinar y confirmar de inmediato la deuda en cuenta corriente" : "generar el borrador de liquidación de oficio";
        if (!confirm(`¿Desea ${accionTexto} para el período ${periodo}?`)) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'generar');
        formData.append('empleador_id', empleadorId);
        formData.append('periodo', periodo);
        formData.append('confirmar', confirmar ? '1' : '0');

        fetch('modules/sindicatos/ddjj_oficio_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'ok') {
                alert(res.message);
                cargarOmisos();
            } else {
                alert('Error: ' + res.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error al comunicar con el servidor');
        });
    };

    function cargarEmitidas() {
        tbodyEmitidas.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-2 text-primary"></i><br>Cargando...</td></tr>`;

        fetch('modules/sindicatos/ddjj_oficio_ajax.php?action=listar_emitidas')
            .then(res => res.json())
            .then(res => {
                if (res.status === 'ok') {
                    renderizarEmitidas(res.data);
                } else {
                    tbodyEmitidas.innerHTML = `<tr><td colspan="8" class="text-danger py-4 text-center">${res.message}</td></tr>`;
                }
            })
            .catch(err => {
                tbodyEmitidas.innerHTML = `<tr><td colspan="8" class="text-danger py-4 text-center">Error al consultar el servidor</td></tr>`;
            });
    }

    function renderizarEmitidas(lista) {
        if (lista.length === 0) {
            tbodyEmitidas.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted">No hay DDJJ de oficio emitidas registradas</td></tr>`;
            return;
        }

        let html = '';
        lista.forEach(d => {
            const esConfirmada = parseInt(d.tabla_estado_registro_id) === 28;
            html += `
                <tr>
                    <td><strong>#${d.ddjj_id}</strong></td>
                    <td class="fw-bold">${d.periodo}</td>
                    <td>
                        <div class="fw-bold">${escapeHtml(d.razon_social)}</div>
                        <div class="small text-muted">CUIT: ${escapeHtml(d.cuit)}</div>
                    </td>
                    <td class="text-center">${d.cantidad_empleados} empleados</td>
                    <td class="text-end fw-bold text-primary fs-6">$${formatNumero(d.total)}</td>
                    <td><code>${escapeHtml(d.codigo_verificacion)}</code></td>
                    <td class="text-center">
                        <span class="badge ${esConfirmada ? 'bg-success' : 'bg-warning text-dark'}">
                            ${escapeHtml(d.estado_nombre || (esConfirmada ? 'Presentada' : 'Borrador'))}
                        </span>
                    </td>
                    <td class="text-end">
                        <a href="modules/sindicatos/ddjj.php?id=${d.ddjj_id}" class="btn btn-outline-primary btn-sm" target="_blank" title="Ver detalle completo de la DDJJ">
                            <i class="fas fa-external-link-alt me-1"></i> Ver DDJJ
                        </a>
                    </td>
                </tr>`;
        });
        tbodyEmitidas.innerHTML = html;
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
