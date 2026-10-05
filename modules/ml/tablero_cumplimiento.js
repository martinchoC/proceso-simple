/**
 * Javascript para Tablero de Gobierno y Drilldown Ejecutivo (Página 355 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/tablero_cumplimiento.js
 */

document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresaIdx = parseInt(urlParams.get('empresa_id') || '2', 10);
    const ajaxUrl = window.location.pathname.includes('/modules/ml/') ? 'tablero_cumplimiento_ajax.php' : 'modules/ml/tablero_cumplimiento_ajax.php';

    let currentNivel = 'global';
    let currentFiltros = {
        region_id: 0,
        territorio_id: 0,
        empresa_id: 0
    };
    let breadcrumbStack = [
        { nivel: 'global', nombre: 'Grupo Corporativo Casalucho', filtros: { region_id: 0, territorio_id: 0, empresa_id: 0 } }
    ];

    // Tooltips
    function initTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // Actualizar Breadcrumb Visual
    function renderBreadcrumb() {
        let html = '';
        breadcrumbStack.forEach((b, idx) => {
            if (idx > 0) html += ' <i class="fas fa-chevron-right text-muted mx-1 small"></i> ';
            const esUltimo = idx === breadcrumbStack.length - 1;
            if (esUltimo) {
                html += `<span class="fw-bold text-primary">${b.nombre}</span>`;
            } else {
                html += `<a href="javascript:void(0)" class="text-decoration-none btn-nav-bread text-secondary" data-index="${idx}">${b.nombre}</a>`;
            }
        });
        $('#breadcrumbDrilldown').html(html);

        // Actualizar botón activo en el header
        $('.btn-drill-nivel').removeClass('active');
        $(`.btn-drill-nivel[data-nivel="${currentNivel}"]`).addClass('active');

        // Título del nivel
        const titulos = {
            global: 'Regiones Corporativas',
            region: 'Países / Jurisdicciones',
            pais: 'Empresas Subsidiarias',
            empresa: 'Instalaciones y Tanques Sujetos a Control'
        };
        $('#tituloNivelActual').text(titulos[currentNivel] || currentNivel);
    }

    // Cargar datos de Drilldown
    function cargarDrilldown(nivel, filtros) {
        currentNivel = nivel;
        currentFiltros = filtros || currentFiltros;

        $('#tbodyDrilldown').html(`
            <tr>
                <td colspan="9" class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    Cargando métricas y jerarquías de ${currentNivel}...
                </td>
            </tr>
        `);

        renderBreadcrumb();

        const params = {
            accion: 'drilldown',
            nivel: currentNivel,
            empresa_idx: empresaIdx,
            region_id: currentFiltros.region_id,
            territorio_id: currentFiltros.territorio_id,
            empresa_id: currentFiltros.empresa_id
        };

        $.getJSON(ajaxUrl, params, function (res) {
            if (!res.success) {
                alert(res.message || 'Error al cargar los datos del tablero');
                return;
            }

            const d = res.data;

            // 1. RENDERIZAR KPIS SUPERIORES
            const k = d.kpis;
            $('#kpiVencidas').text(k.vencidas || 0);
            $('#kpiProximas').text(k.proximas || 0);
            $('#kpiEnTermino').text(k.en_termino || 0);
            $('#kpiTasa').text((k.tasa_cumplimiento || 0) + '%');
            $('#kpiTasaBar').css('width', (k.tasa_cumplimiento || 0) + '%');
            $('#kpiCiclosSub').text(`${k.total_ciclos || 0} ciclos de control activos`);

            if (parseInt(k.vencidas, 10) > 0) {
                $('#kpiVencidasSub').html(`<span class="text-danger fw-bold"><i class="fas fa-exclamation-triangle me-1"></i>${k.vencidas} con desvío crítico</span>`);
            } else {
                $('#kpiVencidasSub').html(`<span class="text-success"><i class="fas fa-check-circle me-1"></i>Sin desvíos en este alcance</span>`);
            }

            // 2. RENDERIZAR GRILLA DE DRILLDOWN
            renderTablaDrilldown(d.items, d.siguiente_nivel);

            // 3. RENDERIZAR TAREAS CRÍTICAS
            renderTareasCriticas(d.tareas_criticas);

            // 4. RENDERIZAR CICLOS ACTIVOS
            renderCiclosActivos(d.ciclos_activos);

            initTooltips();
        }).fail(function () {
            $('#tbodyDrilldown').html(`
                <tr>
                    <td colspan="9" class="text-center py-4 text-danger">
                        <i class="fas fa-exclamation-circle me-1"></i>Error de conexión al cargar el tablero de control.
                    </td>
                </tr>
            `);
        });
    }

    // Renderizar Filas de Drilldown
    function renderTablaDrilldown(items, siguienteNivel) {
        if (!items || items.length === 0) {
            $('#tbodyDrilldown').html(`
                <tr>
                    <td colspan="9" class="text-center py-4 text-muted fst-italic">
                        No hay subdivisiones registradas para este nivel.
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';
        items.forEach(item => {
            const semaforoBg = item.semaforo === 'danger' ? 'danger' : (item.semaforo === 'warning' ? 'warning text-dark' : 'success');
            const semaforoIcon = item.semaforo === 'danger' ? 'fa-exclamation-triangle' : (item.semaforo === 'warning' ? 'fa-clock' : 'fa-check');

            const vencidasBadge = parseInt(item.vencidas, 10) > 0 
                ? `<span class="badge bg-danger fs-7 shadow-sm">${item.vencidas}</span>` 
                : `<span class="text-muted small">0</span>`;

            const proximasBadge = parseInt(item.proximas, 10) > 0 
                ? `<span class="badge bg-warning text-dark fs-7 shadow-sm">${item.proximas}</span>` 
                : `<span class="text-muted small">0</span>`;

            const btnExplorar = siguienteNivel !== 'detalle'
                ? `<button class="btn btn-sm btn-outline-primary btn-explorar py-1 px-2" data-id="${item.id}" data-nombre="${item.nombre}" data-siguiente="${siguienteNivel}" title="Profundizar">
                        Explorar <i class="fas fa-arrow-right ms-1"></i>
                   </button>`
                : `<a href="ciclos_tareas.php?entidad_id=${item.id}" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Ver tareas del tanque/instalación">
                        Ver tareas <i class="fas fa-external-link-alt ms-1"></i>
                   </a>`;

            html += `
                <tr class="${item.semaforo === 'danger' ? 'table-danger bg-opacity-25' : ''}">
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="bg-light p-2 rounded me-2 text-primary border">
                                <i class="${item.icono || 'fas fa-folder'} fa-lg"></i>
                            </div>
                            <div>
                                <span class="fw-bold text-dark d-block">${item.nombre}</span>
                                <small class="text-muted">${item.tipo_etiqueta} ${item.sub_total ? `• ${item.sub_total} ${item.sub_etiqueta}` : ''}</small>
                            </div>
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-${semaforoBg} px-2 py-1 shadow-sm">
                            <i class="fas ${semaforoIcon} me-1"></i>${item.semaforo_texto}
                        </span>
                    </td>
                    <td class="text-center">${vencidasBadge}</td>
                    <td class="text-center">${proximasBadge}</td>
                    <td class="text-center"><span class="small fw-semibold text-secondary">${item.total_tareas - item.cumplidas - item.vencidas}</span></td>
                    <td class="text-center"><span class="badge bg-success-subtle text-success border border-success">${item.cumplidas}</span></td>
                    <td class="text-center fw-bold">${item.total_tareas}</td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: ${item.porcentaje}%"></div>
                            </div>
                            <span class="small fw-bold text-muted">${item.porcentaje}%</span>
                        </div>
                    </td>
                    <td class="text-center">${btnExplorar}</td>
                </tr>
            `;
        });

        $('#tbodyDrilldown').html(html);
    }

    // Renderizar Tareas Críticas
    function renderTareasCriticas(tareas) {
        if (!tareas || tareas.length === 0) {
            $('#tbodyTareasCriticas').html(`
                <tr>
                    <td colspan="5" class="text-center py-3 text-success">
                        <i class="fas fa-check-circle me-1"></i>Excelente: No hay tareas vencidas ni próximas a vencer.
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';
        tareas.forEach(t => {
            const esVencida = t.urgencia === 'VENCIDA';
            const badgeClass = esVencida ? 'bg-danger' : 'bg-warning text-dark';
            const badgeText = esVencida ? `VENCIDA (${t.dias_atraso}d atraso)` : `PRÓXIMA (${Math.abs(t.dias_atraso)}d)`;

            html += `
                <tr class="${esVencida ? 'table-danger' : ''}">
                    <td>
                        <span class="fw-semibold d-block text-dark">${t.titulo}</span>
                        <small class="text-muted">${t.obligacion_codigo}</small>
                    </td>
                    <td><small class="fw-semibold">${t.entidad_nombre}</small></td>
                    <td>
                        <span class="small"><i class="fas fa-user-circle text-muted me-1"></i>${t.responsable_nombre}</span>
                    </td>
                    <td>
                        <span class="small fw-bold ${esVencida ? 'text-danger' : 'text-dark'}">${t.fecha_vencimiento_fmt}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge ${badgeClass} shadow-sm small">${badgeText}</span>
                    </td>
                </tr>
            `;
        });

        $('#tbodyTareasCriticas').html(html);
    }

    // Renderizar Ciclos Activos
    function renderCiclosActivos(ciclos) {
        if (!ciclos || ciclos.length === 0) {
            $('#tbodyCiclosActivos').html(`
                <tr>
                    <td colspan="5" class="text-center py-3 text-muted">No hay ciclos de cumplimiento activos.</td>
                </tr>
            `);
            return;
        }

        let html = '';
        ciclos.forEach(c => {
            const dias = parseInt(c.dias_restantes, 10);
            let badgeDias = 'bg-info text-dark';
            if (dias <= 15) badgeDias = 'bg-danger text-white';
            else if (dias <= 60) badgeDias = 'bg-warning text-dark';

            html += `
                <tr>
                    <td>
                        <span class="badge bg-secondary me-1">${c.norma_codigo}</span>
                        <span class="fw-bold small d-block mt-1">${c.obligacion_codigo}</span>
                        <small class="text-muted text-truncate d-block" style="max-width: 220px;">${c.obligacion_titulo}</small>
                    </td>
                    <td><span class="small fw-semibold">${c.entidad_nombre}</span></td>
                    <td><span class="small fw-bold">${c.fecha_vencimiento_fmt}</span></td>
                    <td>
                        <span class="badge ${badgeDias} small shadow-sm">${dias} días restantes</span>
                    </td>
                    <td class="text-center">
                        <div class="small">
                            ${parseInt(c.tareas_vencidas, 10) > 0 ? `<span class="badge bg-danger me-1">${c.tareas_vencidas} Vencidas</span>` : ''}
                            <span class="badge bg-light text-dark border">${c.tareas_cumplidas}/${c.total_tareas} Tareas</span>
                        </div>
                    </td>
                </tr>
            `;
        });

        $('#tbodyCiclosActivos').html(html);
    }

    // EVENTOS DE NAVEGACIÓN Y DRILLDOWN

    // Botones de Nivel Superior
    $('.btn-drill-nivel').on('click', function () {
        const nivel = $(this).data('nivel');
        breadcrumbStack = [
            { nivel: 'global', nombre: 'Grupo Corporativo Casalucho', filtros: { region_id: 0, territorio_id: 0, empresa_id: 0 } }
        ];

        if (nivel === 'region') {
            breadcrumbStack.push({ nivel: 'region', nombre: 'Todas las Regiones', filtros: { region_id: 0 } });
        } else if (nivel === 'pais') {
            breadcrumbStack.push({ nivel: 'pais', nombre: 'Todos los Países', filtros: { territorio_id: 0 } });
        } else if (nivel === 'empresa') {
            breadcrumbStack.push({ nivel: 'empresa', nombre: 'Empresas y Plantas', filtros: { empresa_id: empresaIdx } });
        }

        cargarDrilldown(nivel, breadcrumbStack[breadcrumbStack.length - 1].filtros);
    });

    // Botón Explorar en la Grilla (Drilldown hacia abajo)
    $(document).on('click', '.btn-explorar', function () {
        const id = parseInt($(this).data('id'), 10);
        const nombre = $(this).data('nombre');
        const siguiente = $(this).data('siguiente');

        const nuevosFiltros = Object.assign({}, currentFiltros);
        if (siguiente === 'region') nuevosFiltros.region_id = id;
        if (siguiente === 'pais') nuevosFiltros.territorio_id = id;
        if (siguiente === 'empresa') nuevosFiltros.empresa_id = id;

        breadcrumbStack.push({
            nivel: siguiente,
            nombre: nombre,
            filtros: nuevosFiltros
        });

        cargarDrilldown(siguiente, nuevosFiltros);
    });

    // Click en Breadcrumb para retroceder
    $(document).on('click', '.btn-nav-bread', function () {
        const idx = parseInt($(this).data('index'), 10);
        const target = breadcrumbStack[idx];
        breadcrumbStack = breadcrumbStack.slice(0, idx + 1);
        cargarDrilldown(target.nivel, target.filtros);
    });

    // Botón Recargar
    $('#btnRecargarDrill').on('click', function () {
        cargarDrilldown(currentNivel, currentFiltros);
    });

    // Iniciar con visión global
    cargarDrilldown('global', currentFiltros);
});
