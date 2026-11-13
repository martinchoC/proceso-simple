/**
 * Javascript para Tareas Operativas - Rediseño ClickUp UI/UX (Módulo 3 Matriz Legal)
 * Archivo: modules/ml/ciclos_tareas.js
 */

document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresaIdx = urlParams.get('empresa_id') || 2;
    const ajaxUrl = window.location.pathname.includes('/modules/ml/') ? 'ciclos_tareas_ajax.php' : 'modules/ml/ciclos_tareas_ajax.php';

    let todasLasTareas = [];
    let permisosUsuario = [];
    let tablaTareas = null;
    let filtroRapidoActual = 'todas';
    let meModeActivo = false;
    let currentUserId = null;
    let currentUserName = '';
    let sortableInstances = [];
    let listaTodasEntidades = [];

    // Colores para avatares (Paleta moderna ClickUp / Fluent)
    const avatarColors = [
        '#7b68ee', '#0078d4', '#107c41', '#5c2d91', '#d83b01', 
        '#008272', '#b4009e', '#038387', '#498205', '#e3008c'
    ];

    function getAvatarColor(str) {
        let hash = 0;
        for (let i = 0; i < (str || '').length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        return avatarColors[Math.abs(hash) % avatarColors.length];
    }

    function getIniciales(nombre) {
        if (!nombre) return '?';
        const parts = nombre.trim().split(/\s+/);
        if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    function initTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    function calcularDias(fechaStr) {
        if (!fechaStr) return { dias: 0, vencida: false, proxima: false };
        const vtoDate = new Date(fechaStr.replace(' ', 'T'));
        const now = new Date();
        const diffMs = vtoDate - now;
        const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));
        return {
            dias: diffDays,
            vencida: diffDays < 0,
            proxima: diffDays >= 0 && diffDays <= 7
        };
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;")
            .replace(/\n/g, '<br>');
    }

    // =========================================================================
    // PRIORIDADES CLICKUP (Urgente, Alta, Normal, Baja)
    // =========================================================================
    function getPriorityInfo(t, fechaInfo) {
        const estadoId = parseInt(t.estado_id, 10);
        const cerrada = (estadoId === 2081 || estadoId === 2083);
        const critPeso = parseInt(t.criticidad_peso || 0, 10);
        const critNom = (t.criticidad_nombre || '').toLowerCase();

        if (!cerrada && (fechaInfo.vencida || critPeso >= 3 || critNom.includes('crítica') || critNom.includes('urgente'))) {
            return { level: 'urgent', label: 'Urgente', class: 'cu-priority-urgent', icon: 'fas fa-flag' };
        }
        if (!cerrada && (fechaInfo.proxima || critPeso === 2 || critNom.includes('alta') || critNom.includes('media'))) {
            return { level: 'high', label: 'Alta', class: 'cu-priority-high', icon: 'fas fa-flag' };
        }
        if (critPeso === 1 || critNom.includes('baja')) {
            return { level: 'low', label: 'Baja', class: 'cu-priority-low', icon: 'fas fa-flag' };
        }
        return { level: 'normal', label: 'Normal', class: 'cu-priority-normal', icon: 'far fa-flag' };
    }

    // =========================================================================
    // FILTRADO CENTRALIZADO (Buscador, Me Mode, Filtros rápidos)
    // =========================================================================
    function obtenerTareasFiltradas() {
        const searchTerm = ($('#plannerSearch').val() || '').toLowerCase().trim();

        return todasLasTareas.filter(t => {
            const fechaInfo = calcularDias(t.fecha_vencimiento);
            const cerrada = (parseInt(t.estado_id, 10) === 2081 || parseInt(t.estado_id, 10) === 2083);

            // Modo Mis Tareas (Me Mode ClickUp)
            if (meModeActivo) {
                const matchUserId = currentUserId && parseInt(t.responsable_usuario_id, 10) === parseInt(currentUserId, 10);
                const matchUserName = currentUserName && (t.responsable_nombre || '').toLowerCase().includes(currentUserName.toLowerCase());
                if (!matchUserId && !matchUserName) return false;
            }

            // Píldoras Rápidas
            if (filtroRapidoActual === 'vencidas' && (cerrada || !fechaInfo.vencida)) return false;
            if (filtroRapidoActual === 'proximas' && (cerrada || !fechaInfo.proxima)) return false;

            // Buscador General
            if (searchTerm) {
                const matchTitulo = (t.titulo || '').toLowerCase().includes(searchTerm);
                const matchResp = (t.responsable_nombre || '').toLowerCase().includes(searchTerm);
                const matchEnt = (t.entidad_nombre || '').toLowerCase().includes(searchTerm);
                const matchObl = (t.obligacion_codigo || '').toLowerCase().includes(searchTerm);
                const matchOblTit = (t.obligacion_titulo || '').toLowerCase().includes(searchTerm);
                if (!matchTitulo && !matchResp && !matchEnt && !matchObl && !matchOblTit) return false;
            }

            return true;
        });
    }

    // =========================================================================
    // 1. CARGA DE DATOS PRINCIPALES
    // =========================================================================
    function cargarTareas() {
        $.getJSON(ajaxUrl, {
            accion: 'listar',
            empresa_idx: empresaIdx,
            filtro_pais: $('#filtroPais').val(),
            filtro_entidad: $('#filtroEntidad').val(),
            filtro_ciclo: $('#filtroCiclo').val()
        }, function (res) {
            if (!res.success) {
                alert(res.message || 'Error al cargar las tareas');
                return;
            }

            todasLasTareas = res.data || [];
            if (res.permisos) permisosUsuario = res.permisos;
            if (res.current_user_id) currentUserId = res.current_user_id;
            if (res.current_user_name) currentUserName = res.current_user_name;

            // Actualizar contador superior
            $('#badgeTotalTareas').html(`<i class="fas fa-check-square text-primary me-1"></i>${todasLasTareas.length} Tareas en total`);

            // Re-renderizar Vistas ClickUp
            actualizarVistas();

            // Actualizar tabla clásica si está inicializada
            if (tablaTareas) {
                tablaTareas.clear().rows.add(todasLasTareas).draw();
            }
        });
    }

    function actualizarVistas() {
        const tareasFiltradas = obtenerTareasFiltradas();

        // Actualizar badges en tabs
        $('#badgeCountLista').text(tareasFiltradas.length);
        $('#badgeCountTablero').text(tareasFiltradas.length);

        renderClickUpListView(tareasFiltradas);
        renderPlannerBoard(tareasFiltradas);
        renderGraficos(tareasFiltradas);
    }

    // =========================================================================
    // 2. VISTA LISTA AGRUPADA CLICKUP (CLICKUP GROUPED LIST VIEW)
    // =========================================================================
    function renderClickUpListView(tareasFiltradas) {
        const groupBy = $('#plannerGroupBy').val() || 'estado';
        const container = $('#cuListViewContainer');
        container.empty();

        // Actualizar título informativo de agrupación
        const mapLabels = {
            'estado': 'Agrupado por Estado de Cumplimiento',
            'responsable': 'Agrupado por Responsable de Ejecución',
            'entidad': 'Agrupado por Instalación / Entidad',
            'vencimiento': 'Agrupado por Plazo / Vencimiento'
        };
        $('#lblListGroupInfo').html(`<i class="fas fa-layer-group text-primary me-1"></i>${mapLabels[groupBy] || 'Lista Agrupada'}`);

        if (!tareasFiltradas || tareasFiltradas.length === 0) {
            container.html(`
                <div class="cu-empty-state">
                    <i class="fas fa-inbox text-muted"></i>
                    <h6 class="fw-bold text-dark mt-2">No se encontraron tareas</h6>
                    <p class="text-muted small mb-0">Modifica los filtros de búsqueda o desactiva "Mis Tareas" para explorar más registros.</p>
                </div>
            `);
            return;
        }

        // Definir grupos según selector
        let grupos = [];

        if (groupBy === 'estado') {
            grupos = [
                { id: '2078', titulo: 'Pendientes (No iniciadas)', color: '#64748b', estadoId: 2078 },
                { id: '2079', titulo: 'En curso', color: '#0284c7', estadoId: 2079 },
                { id: '2080', titulo: 'En revisión técnica', color: '#d97706', estadoId: 2080 },
                { id: '2081', titulo: 'Cumplidas', color: '#16a34a', estadoId: 2081 }
            ];
        } else if (groupBy === 'responsable') {
            const mapResp = new Map();
            tareasFiltradas.forEach(t => {
                const nombre = t.responsable_nombre || 'Sin Asignar';
                if (!mapResp.has(nombre)) {
                    mapResp.set(nombre, { id: nombre, titulo: nombre, color: getAvatarColor(nombre) });
                }
            });
            grupos = Array.from(mapResp.values());
            if (grupos.length === 0) grupos.push({ id: 'Sin Asignar', titulo: 'Sin Asignar', color: '#94a3b8' });
        } else if (groupBy === 'entidad') {
            const mapEnt = new Map();
            tareasFiltradas.forEach(t => {
                const nombre = t.entidad_nombre || 'General';
                if (!mapEnt.has(nombre)) {
                    mapEnt.set(nombre, { id: nombre, titulo: nombre, color: '#7b68ee' });
                }
            });
            grupos = Array.from(mapEnt.values());
        } else if (groupBy === 'vencimiento') {
            grupos = [
                { id: 'vencidas', titulo: 'Con retraso / Vencidas', color: '#dc2626' },
                { id: 'proximas', titulo: 'Próximas (≤ 7 días)', color: '#f59e0b' },
                { id: 'termino', titulo: 'En plazo / A término', color: '#0284c7' },
                { id: 'completadas', titulo: 'Completadas', color: '#16a34a' }
            ];
        }

        // Renderizar cada grupo
        grupos.forEach((grp, idx) => {
            const tareasGrp = tareasFiltradas.filter(t => {
                const estadoId = parseInt(t.estado_id, 10);
                const cerrada = (estadoId === 2081 || estadoId === 2083);
                const fechaInfo = calcularDias(t.fecha_vencimiento);

                if (groupBy === 'estado') {
                    return estadoId === grp.estadoId;
                } else if (groupBy === 'responsable') {
                    return (t.responsable_nombre || 'Sin Asignar') === grp.id;
                } else if (groupBy === 'entidad') {
                    return (t.entidad_nombre || 'General') === grp.id;
                } else if (groupBy === 'vencimiento') {
                    if (cerrada) return grp.id === 'completadas';
                    if (fechaInfo.vencida) return grp.id === 'vencidas';
                    if (fechaInfo.proxima) return grp.id === 'proximas';
                    return grp.id === 'termino';
                }
                return true;
            });

            // Si el grupo no tiene tareas y agrupamos por responsable o entidad, podemos omitirlo si está vacío
            if (tareasGrp.length === 0 && (groupBy === 'responsable' || groupBy === 'entidad')) {
                return;
            }

            const totalGrp = tareasGrp.length;
            const cerradasGrp = tareasGrp.filter(t => {
                const st = parseInt(t.estado_id, 10);
                return st === 2081 || st === 2083;
            }).length;
            const progressPct = totalGrp > 0 ? Math.round((cerradasGrp / totalGrp) * 100) : 0;
            const safeGrpId = 'grp_' + String(grp.id).replace(/[^a-zA-Z0-9_-]/g, '_');

            // Filas de tareas del grupo
            let rowsHtml = '';
            if (tareasGrp.length === 0) {
                rowsHtml = `<div class="p-3 text-center text-muted small fst-italic">Sin tareas en este grupo</div>`;
            } else {
                const colsHeaderHtml = `
                    <div class="cu-list-cols-header">
                        <div class="cu-row-select"></div>
                        <div class="cu-row-title">Tarea / Requisito</div>
                        <div class="cu-row-assignee">Responsable</div>
                        <div class="cu-row-due">Vencimiento</div>
                        <div class="cu-row-priority">Prioridad</div>
                        <div class="cu-row-meta">Detalles</div>
                        <div class="cu-row-actions"></div>
                    </div>
                `;
                rowsHtml = colsHeaderHtml + tareasGrp.map(t => renderClickUpTaskRow(t)).join('');
            }

            const groupCardHtml = `
                <div class="cu-group-card" data-group-id="${safeGrpId}">
                    <div class="cu-group-header" data-target="#body_${safeGrpId}">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-chevron-down cu-group-chevron"></i>
                            <span class="cu-group-dot" style="background-color: ${grp.color};"></span>
                            <span class="cu-group-title">${grp.titulo}</span>
                            <span class="cu-group-count">${totalGrp}</span>
                        </div>
                        <div class="cu-group-progress-wrap">
                            <span class="cu-group-progress-label">${progressPct}% completado</span>
                            <div class="cu-group-progress-bar">
                                <div class="cu-group-progress-fill" style="width: ${progressPct}%;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="cu-group-body" id="body_${safeGrpId}">
                        ${rowsHtml}
                    </div>
                </div>
            `;

            container.append(groupCardHtml);
        });

        initTooltips();
    }

    // Render de una Fila de Lista ClickUp
    function renderClickUpTaskRow(t) {
        const id = t.ciclo_tarea_id;
        const estadoId = parseInt(t.estado_id, 10);
        const cerrada = (estadoId === 2081 || estadoId === 2083);
        const fechaInfo = calcularDias(t.fecha_vencimiento);
        const prio = getPriorityInfo(t, fechaInfo);

        // Checkbox ClickUp
        const checkIcon = cerrada ? '<i class="fas fa-check small text-white"></i>' : '';
        const checkClass = cerrada ? 'checked' : '';

        // Badge de Fecha de Vencimiento
        let dueHtml = '';
        if (cerrada) {
            dueHtml = `<span class="cu-due-badge cu-due-ok"><i class="fas fa-check me-1"></i>Completada</span>`;
        } else if (fechaInfo.vencida) {
            dueHtml = `<span class="cu-due-badge cu-due-overdue" title="Retraso de ${Math.abs(fechaInfo.dias)} días">
                           <i class="fas fa-exclamation-triangle me-1"></i>${t.fecha_vencimiento_fmt || ''} (${Math.abs(fechaInfo.dias)}d)
                       </span>`;
        } else if (fechaInfo.proxima) {
            dueHtml = `<span class="cu-due-badge cu-due-upcoming" title="Vence en ${fechaInfo.dias} días">
                           <i class="fas fa-clock me-1"></i>${t.fecha_vencimiento_fmt || ''} (${fechaInfo.dias}d)
                       </span>`;
        } else {
            dueHtml = `<span class="cu-due-badge cu-due-normal"><i class="far fa-calendar-alt me-1"></i>${t.fecha_vencimiento_fmt || '--'}</span>`;
        }

        // Avatar Responsable
        const respNombre = t.responsable_nombre || 'Sin Asignar';
        const iniciales = getIniciales(respNombre);
        const avatarBg = getAvatarColor(respNombre);
        const avatarHtml = `
            <div class="cu-row-assignee" title="Responsable: ${respNombre}">
                <div class="cu-avatar" style="background-color: ${avatarBg}; width: 24px; height: 24px; font-size: 10px;">${iniciales}</div>
                <span class="cu-assignee-name">${respNombre}</span>
            </div>
        `;

        // Sigla e ícono visual de la obligación
        const oblSigla = t.obligacion_sigla || t.obligacion_codigo || 'OBL';
        const oblIcon = t.obligacion_icono || 'fas fa-shield-alt';
        const oblColor = t.obligacion_color || '#0284c7';
        const oblBadge = `
            <span class="badge text-white shadow-sm fw-bold px-2 py-1" style="background-color: ${oblColor}; border-radius: 4px; font-size: 0.72rem;" title="${t.obligacion_codigo} - ${t.obligacion_titulo}">
                <i class="${oblIcon} me-1"></i>${oblSigla}
            </span>
        `;

        // Tags / Indicadores
        const evidCount = parseInt(t.total_evidencias || 0, 10);
        const evidIcon = (t.requiere_evidencia == 1 || evidCount > 0) 
            ? `<span class="cu-meta-chip" title="${evidCount} Evidencias adjuntas"><i class="fas fa-paperclip text-info"></i>${evidCount > 0 ? evidCount : ''}</span>` 
            : '';

        const msgCount = parseInt(t.total_mensajes || 0, 10);
        const msgIcon = msgCount > 0
            ? `<span class="cu-meta-chip" title="${msgCount} mensajes en el hilo" style="color: var(--cu-purple); font-weight: 700;"><i class="fas fa-comments"></i>${msgCount}</span>`
            : '';

        const revIcon = t.requiere_revision == 1 
            ? `<span class="cu-meta-chip" title="Requiere Revisión Técnica"><i class="fas fa-user-check text-warning"></i></span>` 
            : '';

        const predCount = parseInt(t.total_predecesoras || 0, 10);
        const predIcon = predCount > 0 
            ? `<span class="cu-meta-chip" title="${predCount} predecesoras"><i class="fas fa-sitemap text-secondary"></i>${predCount}</span>` 
            : '';

        return `
            <div class="cu-task-row" data-id="${id}" data-estado-id="${estadoId}">
                <!-- Checkbox Circular -->
                <div class="cu-row-select">
                    <div class="planner-check-circle ${checkClass} btn-quick-check" data-id="${id}" data-estado-id="${estadoId}" title="${cerrada ? 'Reabrir tarea' : 'Marcar cumplida'}">
                        ${checkIcon}
                    </div>
                </div>

                <!-- Título y Etiquetas -->
                <div class="cu-row-title">
                    <span class="cu-task-title-text btn-ver-tarea" data-id="${id}">${t.titulo}</span>
                    <div class="cu-task-tags">
                        ${oblBadge}
                        <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.71rem;">
                            <i class="fas fa-industry text-secondary me-1"></i>${t.entidad_nombre}
                        </span>
                    </div>
                </div>

                <!-- Responsable (Avatar + Nombre) -->
                ${avatarHtml}

                <!-- Fecha de Vencimiento -->
                <div class="cu-row-due">
                    ${dueHtml}
                </div>

                <!-- Bandera de Prioridad ClickUp -->
                <div class="cu-row-priority">
                    <span class="cu-priority-flag ${prio.class}" title="Prioridad: ${prio.label}">
                        <i class="${prio.icon}"></i> ${prio.label}
                    </span>
                </div>

                <!-- Indicadores de Metadatos -->
                <div class="cu-row-meta">
                    ${msgIcon}
                    ${evidIcon}
                    ${revIcon}
                    ${predIcon}
                </div>

                <!-- Acciones Rápidas -->
                <div class="cu-row-actions">
                    <button type="button" class="btn btn-xs btn-outline-secondary btn-ver-tarea" data-id="${id}" title="Abrir Panel Lateral">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        `;
    }

    // =========================================================================
    // 3. RENDERIZADO DEL TABLERO KANBAN ESTILO CLICKUP / PLANNER
    // =========================================================================
    function renderPlannerBoard(tareasFiltradas) {
        if (!tareasFiltradas) tareasFiltradas = obtenerTareasFiltradas();
        const groupBy = $('#plannerGroupBy').val() || 'estado';
        const container = $('#plannerBoardContainer');
        container.empty();

        // Destruir instancias de Sortable previas
        sortableInstances.forEach(inst => inst.destroy());
        sortableInstances = [];

        // Definir columnas según "Agrupar por"
        let columnas = [];

        if (groupBy === 'estado') {
            columnas = [
                { id: '2078', titulo: 'Pendientes (No iniciadas)', icon: 'fa-hourglass-start text-secondary', estadoId: 2078 },
                { id: '2079', titulo: 'En curso', icon: 'fa-spinner text-primary', estadoId: 2079 },
                { id: '2080', titulo: 'En revisión técnica', icon: 'fa-user-check text-warning', estadoId: 2080 },
                { id: '2081', titulo: 'Cumplidas', icon: 'fa-check-circle text-success', estadoId: 2081 }
            ];
        } else if (groupBy === 'responsable') {
            const mapResp = new Map();
            tareasFiltradas.forEach(t => {
                const nombre = t.responsable_nombre || 'Sin Asignar';
                if (!mapResp.has(nombre)) {
                    mapResp.set(nombre, { id: nombre, titulo: nombre, icon: 'fa-user text-info' });
                }
            });
            columnas = Array.from(mapResp.values());
            if (columnas.length === 0) columnas.push({ id: 'Sin Asignar', titulo: 'Sin Asignar', icon: 'fa-user text-muted' });
        } else if (groupBy === 'entidad') {
            const mapEnt = new Map();
            tareasFiltradas.forEach(t => {
                const nombre = t.entidad_nombre || 'General';
                if (!mapEnt.has(nombre)) {
                    mapEnt.set(nombre, { id: nombre, titulo: nombre, icon: 'fa-cubes text-secondary' });
                }
            });
            columnas = Array.from(mapEnt.values());
        } else if (groupBy === 'vencimiento') {
            columnas = [
                { id: 'vencidas', titulo: 'Con retraso / Vencidas', icon: 'fa-exclamation-triangle text-danger' },
                { id: 'proximas', titulo: 'Próximas (≤ 7 días)', icon: 'fa-clock text-warning' },
                { id: 'termino', titulo: 'En plazo / A término', icon: 'fa-calendar-check text-primary' },
                { id: 'completadas', titulo: 'Completadas', icon: 'fa-check-double text-success' }
            ];
        }

        // Renderizar cada columna
        columnas.forEach(col => {
            const tareasColumna = tareasFiltradas.filter(t => {
                const estadoId = parseInt(t.estado_id, 10);
                const cerrada = (estadoId === 2081 || estadoId === 2083);
                const fechaInfo = calcularDias(t.fecha_vencimiento);

                if (groupBy === 'estado') {
                    return estadoId === col.estadoId;
                } else if (groupBy === 'responsable') {
                    return (t.responsable_nombre || 'Sin Asignar') === col.id;
                } else if (groupBy === 'entidad') {
                    return (t.entidad_nombre || 'General') === col.id;
                } else if (groupBy === 'vencimiento') {
                    if (cerrada) return col.id === 'completadas';
                    if (fechaInfo.vencida) return col.id === 'vencidas';
                    if (fechaInfo.proxima) return col.id === 'proximas';
                    return col.id === 'termino';
                }
                return true;
            });

            const colHtml = `
                <div class="planner-column" data-col-id="${col.id}" data-estado-id="${col.estadoId || ''}">
                    <div class="planner-column-header">
                        <h6 class="planner-column-title">
                            <i class="fas ${col.icon} me-2"></i>${col.titulo}
                        </h6>
                        <span class="planner-column-count">${tareasColumna.length}</span>
                    </div>
                    <div class="planner-column-cards connectedSortable" data-estado-id="${col.estadoId || ''}">
                        ${tareasColumna.map(t => renderTaskCard(t)).join('')}
                    </div>
                </div>
            `;
            container.append(colHtml);
        });

        // Inicializar Drag & Drop con Sortable si agrupamos por estado
        if (groupBy === 'estado') {
            document.querySelectorAll('.planner-column-cards').forEach(el => {
                const sortable = new Sortable(el, {
                    group: 'planner-tasks',
                    animation: 150,
                    ghostClass: 'bg-light',
                    dragClass: 'shadow-lg',
                    onEnd: function (evt) {
                        const cardEl = evt.item;
                        const tareaId = cardEl.getAttribute('data-id');
                        const targetCol = evt.to;
                        const nuevoEstadoId = targetCol.getAttribute('data-estado-id');

                        if (tareaId && nuevoEstadoId) {
                            ejecutarTransicionRapida(tareaId, nuevoEstadoId);
                        }
                    }
                });
                sortableInstances.push(sortable);
            });
        }

        initTooltips();
    }

    // Render de Tarjeta Kanban
    function renderTaskCard(t) {
        const id = t.ciclo_tarea_id;
        const estadoId = parseInt(t.estado_id, 10);
        const cerrada = (estadoId === 2081 || estadoId === 2083);
        const fechaInfo = calcularDias(t.fecha_vencimiento);
        const prio = getPriorityInfo(t, fechaInfo);

        let cardClass = '';
        if (cerrada) {
            cardClass = 'card-completed';
        } else if (fechaInfo.vencida) {
            cardClass = 'card-overdue';
        } else if (fechaInfo.proxima) {
            cardClass = 'card-upcoming';
        } else if (estadoId === 2079) {
            cardClass = 'card-in-progress';
        } else if (estadoId === 2080) {
            cardClass = 'card-review';
        }

        const checkIcon = cerrada ? '<i class="fas fa-check small text-white"></i>' : '';
        const checkClass = cerrada ? 'checked' : '';

        // Badge de Fecha de Vencimiento
        let vtoBadge = '';
        if (cerrada) {
            vtoBadge = `<span class="small text-muted"><i class="fas fa-check text-success me-1"></i>Completada</span>`;
        } else if (fechaInfo.vencida) {
            vtoBadge = `<span class="badge bg-danger shadow-sm py-1 px-2" title="Retraso de ${Math.abs(fechaInfo.dias)} días">
                            <i class="fas fa-exclamation-triangle me-1"></i>${t.fecha_vencimiento_fmt || ''} (${Math.abs(fechaInfo.dias)}d)
                        </span>`;
        } else if (fechaInfo.proxima) {
            vtoBadge = `<span class="badge bg-warning text-dark shadow-sm py-1 px-2">
                            <i class="fas fa-clock me-1"></i>${t.fecha_vencimiento_fmt || ''} (${fechaInfo.dias}d)
                        </span>`;
        } else {
            vtoBadge = `<span class="small text-muted"><i class="far fa-calendar-alt me-1"></i>${t.fecha_vencimiento_fmt || '--'}</span>`;
        }

        // Avatar
        const respNombre = t.responsable_nombre || 'Sin Asignar';
        const iniciales = getIniciales(respNombre);
        const avatarBg = getAvatarColor(respNombre);
        const avatarHtml = `
            <div class="planner-avatar" style="background-color: ${avatarBg};" data-bs-toggle="tooltip" title="Responsable: ${respNombre}">
                ${iniciales}
            </div>
        `;

        // Indicadores
        const evidCount = parseInt(t.total_evidencias || 0, 10);
        const evidIcon = (t.requiere_evidencia == 1 || evidCount > 0) 
            ? `<span title="${evidCount} Evidencias adjuntas"><i class="fas fa-paperclip me-1 text-info"></i>${evidCount > 0 ? evidCount : ''}</span>` 
            : '';

        const revIcon = t.requiere_revision == 1 
            ? `<span title="Requiere Revisión Técnica"><i class="fas fa-user-check text-warning"></i></span>` 
            : '';

        const predCount = parseInt(t.total_predecesoras || 0, 10);
        const predIcon = predCount > 0 
            ? `<span title="${predCount} dependencias predecesoras"><i class="fas fa-sitemap text-secondary"></i></span>` 
            : '';

        const msgCount = parseInt(t.total_mensajes || 0, 10);
        const msgIcon = msgCount > 0
            ? `<span title="${msgCount} mensajes en el hilo" class="text-primary fw-bold"><i class="fas fa-comments me-1"></i>${msgCount}</span>`
            : '';

        // Obligación
        const oblSigla = t.obligacion_sigla || t.obligacion_codigo || 'OBL';
        const oblIcon = t.obligacion_icono || 'fas fa-shield-alt';
        const oblColor = t.obligacion_color || '#0284c7';
        const oblBadge = `
            <span class="planner-tag text-white shadow-sm fw-bold px-2 py-1" style="background-color: ${oblColor}; border-radius: 4px; font-size: 0.73rem;" title="${t.obligacion_codigo} - ${t.obligacion_titulo}">
                <i class="${oblIcon} me-1"></i>${oblSigla}
            </span>
        `;

        // Bandera de Prioridad ClickUp
        const prioBadge = `
            <span class="cu-priority-flag ${prio.class} ms-auto" style="font-size: 0.7rem; padding: 2px 6px;">
                <i class="${prio.icon}"></i> ${prio.label}
            </span>
        `;

        return `
            <div class="planner-card ${cardClass}" data-id="${id}" data-estado-id="${estadoId}">
                <!-- Encabezado con Check y Título -->
                <div class="d-flex align-items-start">
                    <div class="planner-check-circle ${checkClass} btn-quick-check" data-id="${id}" data-estado-id="${estadoId}" title="${cerrada ? 'Reabrir tarea' : 'Marcar cumplida'}">
                        ${checkIcon}
                    </div>
                    <div class="flex-grow-1 btn-ver-tarea" data-id="${id}">
                        <div class="planner-card-title">${t.titulo}</div>
                    </div>
                </div>

                <!-- Etiquetas de Contexto + Prioridad ClickUp -->
                <div class="mb-2 d-flex flex-wrap align-items-center gap-1">
                    ${oblBadge}
                    <span class="planner-tag bg-light text-dark border px-2 py-1" style="font-size: 0.72rem;">
                        <i class="fas fa-industry text-secondary me-1"></i>${t.entidad_nombre}
                    </span>
                    ${prioBadge}
                </div>

                <!-- Pie de la tarjeta -->
                <div class="planner-card-footer">
                    <div>${vtoBadge}</div>
                    <div class="d-flex align-items-center">
                        <div class="planner-icons me-2">
                            ${msgIcon}
                            ${evidIcon}
                            ${revIcon}
                            ${predIcon}
                        </div>
                        ${avatarHtml}
                    </div>
                </div>
            </div>
        `;
    }

    // =========================================================================
    // 4. VISTA DE GRÁFICOS Y MÉTRICAS
    // =========================================================================
    function renderGraficos(tareasFiltradas) {
        if (!tareasFiltradas) tareasFiltradas = obtenerTareasFiltradas();
        let overdue = 0, upcoming = 0, progress = 0, completed = 0;
        const mapResp = new Map();
        const mapBuckets = new Map();

        tareasFiltradas.forEach(t => {
            const estadoId = parseInt(t.estado_id, 10);
            const cerrada = (estadoId === 2081 || estadoId === 2083);
            const fechaInfo = calcularDias(t.fecha_vencimiento);

            if (cerrada) {
                completed++;
            } else if (fechaInfo.vencida) {
                overdue++;
            } else if (fechaInfo.proxima) {
                upcoming++;
            } else {
                progress++;
            }

            // Conteo por Responsable
            const resp = t.responsable_nombre || 'Sin Asignar';
            if (!mapResp.has(resp)) mapResp.set(resp, { total: 0, vencidas: 0 });
            mapResp.get(resp).total++;
            if (!cerrada && fechaInfo.vencida) mapResp.get(resp).vencidas++;

            // Conteo por Depósito / Entidad
            const ent = t.entidad_nombre || 'General';
            if (!mapBuckets.has(ent)) mapBuckets.set(ent, { total: 0, vencidas: 0 });
            mapBuckets.get(ent).total++;
            if (!cerrada && fechaInfo.vencida) mapBuckets.get(ent).vencidas++;
        });

        const total = tareasFiltradas.length || 1;
        $('#chartCountOverdue').text(overdue);
        $('#chartCountProgress').text(progress);
        $('#chartCountUpcoming').text(upcoming);
        $('#chartCountCompleted').text(completed);

        $('#barOverdue').css('width', (overdue / total * 100) + '%');
        $('#barUpcoming').css('width', (upcoming / total * 100) + '%');
        $('#barProgress').css('width', (progress / total * 100) + '%');
        $('#barCompleted').css('width', (completed / total * 100) + '%');

        // Miembros
        let htmlResp = '';
        mapResp.forEach((data, resp) => {
            const iniciales = getIniciales(resp);
            const bg = getAvatarColor(resp);
            htmlResp += `
                <div class="d-flex align-items-center justify-content-between p-2 mb-2 border rounded bg-light">
                    <div class="d-flex align-items-center">
                        <div class="planner-avatar me-2" style="background-color: ${bg};">${iniciales}</div>
                        <span class="small fw-bold">${resp}</span>
                    </div>
                    <div>
                        ${data.vencidas > 0 ? `<span class="badge bg-danger me-1">${data.vencidas} vencidas</span>` : ''}
                        <span class="badge bg-secondary">${data.total} tareas</span>
                    </div>
                </div>
            `;
        });
        $('#chartMembersList').html(htmlResp || '<p class="text-muted text-center">Sin miembros asignados</p>');

        // Instalaciones
        let htmlBuckets = '';
        mapBuckets.forEach((data, ent) => {
            htmlBuckets += `
                <div class="d-flex align-items-center justify-content-between p-2 mb-2 border rounded bg-light">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-cubes text-secondary me-2"></i>
                        <span class="small fw-bold text-truncate" style="max-width: 170px;">${ent}</span>
                    </div>
                    <div>
                        ${data.vencidas > 0 ? `<span class="badge bg-danger me-1">${data.vencidas} vencidas</span>` : ''}
                        <span class="badge bg-primary">${data.total} tareas</span>
                    </div>
                </div>
            `;
        });
        $('#chartBucketsList').html(htmlBuckets || '<p class="text-muted text-center">Sin instalaciones registradas</p>');
    }

    // =========================================================================
    // 5. TRANSICIONES RÁPIDAS DE ESTADO
    // =========================================================================
    function ejecutarTransicionRapida(tareaId, nuevoEstadoId) {
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                accion: 'cambiar_estado',
                ciclo_tarea_id: tareaId,
                nuevo_estado_id: nuevoEstadoId,
                empresa_idx: empresaIdx,
                observaciones: 'Actualización rápida desde Matriz Legal (ClickUp View)'
            },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    cargarTareas();
                } else {
                    alert('No se pudo mover la tarea: ' + res.message);
                    actualizarVistas();
                }
            },
            error: function () {
                alert('Error al conectar con el servidor.');
                actualizarVistas();
            }
        });
    }

    // Click en Checkbox circular de completado rápido
    $(document).on('click', '.btn-quick-check', function (e) {
        e.stopPropagation();
        const tareaId = $(this).data('id');
        const estadoId = parseInt($(this).data('estado-id'), 10);

        if (estadoId === 2081) {
            if (confirm('¿Desea reabrir esta tarea y volverla a poner En Curso?')) {
                ejecutarTransicionRapida(tareaId, 2079);
            }
        } else {
            ejecutarTransicionRapida(tareaId, 2081);
        }
    });

    // =========================================================================
    // 6. INICIALIZAR VISTA CUADRÍCULA (DATATABLES)
    // =========================================================================
    function initDataTable() {
        tablaTareas = $('#tablaTareas').DataTable({
            responsive: true,
            pageLength: 10,
            data: todasLasTareas,
            language: { url: window.APP_ASSETS_URL + 'js/datatables-i18n-1.13.7/es-ES.json' },
            columns: [
                { data: 'ciclo_tarea_id', className: 'text-muted small fw-bold' },
                {
                    data: null,
                    render: function (d) {
                        return `<span class="fw-semibold">${d.entidad_nombre}</span><br><small class="text-muted">${d.entidad_tipo_nombre || ''}</small>`;
                    }
                },
                {
                    data: null,
                    render: function (d) {
                        const sigla = d.obligacion_sigla || d.obligacion_codigo || 'OBL';
                        const icono = d.obligacion_icono || 'fas fa-balance-scale';
                        const color = d.obligacion_color || '#475569';
                        return `<div>
                            <span class="badge text-white me-1 shadow-sm" style="background-color: ${color};"><i class="${icono} me-1"></i>${sigla}</span>
                            <span class="small fw-semibold text-dark">${d.obligacion_titulo}</span>
                        </div>`;
                    }
                },
                {
                    data: null,
                    render: function (d) {
                        return `<span class="fw-bold text-dark btn-ver-tarea cursor-pointer" data-id="${d.ciclo_tarea_id}">${d.titulo}</span>`;
                    }
                },
                {
                    data: null,
                    render: function (d) {
                        const nombre = d.responsable_nombre || 'Sin Asignar';
                        const ini = getIniciales(nombre);
                        const bg = getAvatarColor(nombre);
                        return `<div class="d-flex align-items-center">
                                    <div class="planner-avatar me-2" style="background-color: ${bg}; width: 24px; height: 24px; font-size: 10px;">${ini}</div>
                                    <span class="small">${nombre}</span>
                                </div>`;
                    }
                },
                {
                    data: null,
                    render: function (d) {
                        const fi = calcularDias(d.fecha_vencimiento);
                        if (fi.vencida) {
                            return `<span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>VENCIDA (${Math.abs(fi.dias)}d)</span>`;
                        } else if (fi.proxima) {
                            return `<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>PRÓXIMA (${fi.dias}d)</span>`;
                        }
                        return `<span class="small text-muted"><i class="far fa-calendar-alt me-1"></i>${d.fecha_vencimiento_fmt || '--'}</span>`;
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    render: function (d) {
                        const rev = d.requiere_revision == 1 ? '<i class="fas fa-check-circle text-warning me-1" title="Requiere Revisión"></i>' : '';
                        const evi = d.requiere_evidencia == 1 ? '<i class="fas fa-paperclip text-info" title="Requiere Evidencia"></i>' : '';
                        return `${rev} ${evi}` || '<span class="text-muted small">-</span>';
                    }
                },
                {
                    data: null,
                    render: function (d) {
                        const est = parseInt(d.estado_id, 10);
                        let badge = 'secondary';
                        if (est === 2078) badge = 'info text-dark';
                        if (est === 2079) badge = 'primary';
                        if (est === 2080) badge = 'warning text-dark';
                        if (est === 2081) badge = 'success';
                        return `<span class="badge bg-${badge}">${d.estado_nombre}</span>`;
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    render: function (d) {
                        return `<button class="btn btn-sm btn-outline-primary btn-ver-tarea" data-id="${d.ciclo_tarea_id}" title="Ver Detalle en Drawer"><i class="fas fa-arrow-right"></i></button>`;
                    }
                }
            ]
        });
    }

    // =========================================================================
    // 7. CARGA DE COMBOS Y FILTROS EN CASCADA
    // =========================================================================
    function cargarCombos() {
        $.getJSON(ajaxUrl, { accion: 'combos', empresa_idx: empresaIdx }, function (res) {
            if (!res.success) return;
            
            if (res.current_user_id) currentUserId = res.current_user_id;
            if (res.current_user_name) currentUserName = res.current_user_name;

            // Países
            const $pais = $('#filtroPais');
            $pais.empty().append('<option value="">-- Todos los países --</option>');
            (res.paises || []).forEach(p => {
                $pais.append(`<option value="${p.territorio_id}">${p.nombre} (${p.codigo})</option>`);
            });

            // Entidades
            listaTodasEntidades = res.entidades || [];
            actualizarComboEntidades();

            // Parámetros URL
            const urlPais = urlParams.get('pais_id') || urlParams.get('territorio_id');
            const urlEntidad = urlParams.get('entidad_id');
            if (urlPais) {
                $pais.val(urlPais);
                actualizarComboEntidades();
            }
            if (urlEntidad) {
                $('#filtroEntidad').val(urlEntidad);
            }

            if (urlPais || urlEntidad) {
                cargarTareas();
            }
        });
    }

    function actualizarComboEntidades() {
        const paisSel = $('#filtroPais').val();
        const $ent = $('#filtroEntidad');
        const valActual = $ent.val();
        $ent.empty().append('<option value="">-- Todas las instalaciones --</option>');

        listaTodasEntidades.forEach(e => {
            if (!paisSel || String(e.territorio_id) === String(paisSel)) {
                $ent.append(`<option value="${e.entidad_id}">${e.nombre} (${e.codigo})</option>`);
            }
        });

        if (valActual && $ent.find(`option[value="${valActual}"]`).length > 0) {
            $ent.val(valActual);
        }
    }

    // =========================================================================
    // 8. EVENTOS DE INTERFAZ CLICKUP (FILTROS, ME MODE, DRAWER, CHAT)
    // =========================================================================

    // Buscador instantáneo con re-render
    let searchTimeout = null;
    $('#plannerSearch').on('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function () {
            actualizarVistas();
        }, 180);
    });

    // Selector Agrupar por
    $('#plannerGroupBy').on('change', function () {
        actualizarVistas();
    });

    // Toggle "Mis Tareas" (Me Mode ClickUp)
    $('#btnMeMode').on('click', function () {
        meModeActivo = !meModeActivo;
        $(this).toggleClass('active', meModeActivo);
        actualizarVistas();
    });

    // Píldoras Rápidas (Todas, Vencidas, Próximas)
    $('.btn-filtro-planner').on('click', function () {
        $('.btn-filtro-planner').removeClass('active');
        $(this).addClass('active');
        filtroRapidoActual = $(this).data('filter');
        actualizarVistas();
    });

    // Filtros de combos
    $('#filtroPais').on('change', function () {
        actualizarComboEntidades();
        cargarTareas();
    });

    $('#filtroEntidad').on('change', function () {
        cargarTareas();
    });

    $('#btnRecargarPlanner').on('click', function () {
        cargarTareas();
    });

    // Colapsar / Expandir Grupo individual al hacer click en el header
    $(document).on('click', '.cu-group-header', function () {
        const targetId = $(this).data('target');
        const $body = $(targetId);
        const $chevron = $(this).find('.cu-group-chevron');

        if ($body.is(':visible')) {
            $body.slideUp(180);
            $chevron.addClass('collapsed');
        } else {
            $body.slideDown(180);
            $chevron.removeClass('collapsed');
        }
    });

    // Expandir todos los grupos
    $('#btnExpandirTodosGrupos').on('click', function () {
        $('.cu-group-body').slideDown(180);
        $('.cu-group-chevron').removeClass('collapsed');
    });

    // Colapsar todos los grupos
    $('#btnColapsarTodosGrupos').on('click', function () {
        $('.cu-group-body').slideUp(180);
        $('.cu-group-chevron').addClass('collapsed');
    });

    // Alternar Grilla Tabular Clásica DataTables
    $('#btnToggleTablaClasica').on('click', function () {
        const $wrapper = $('#cuTableViewWrapper');
        if ($wrapper.hasClass('d-none')) {
            $wrapper.removeClass('d-none');
            $(this).html('<i class="fas fa-list-ul me-1"></i>Ocultar Tabla');
        } else {
            $wrapper.addClass('d-none');
            $(this).html('<i class="fas fa-table me-1"></i>Tabla Clásica');
        }
    });

    // Click en la fila para abrir Drawer (si no fue en check ni botón)
    $(document).on('click', '.cu-task-row', function (e) {
        if ($(e.target).closest('.btn-quick-check, button, a, input').length === 0) {
            const tareaId = $(this).data('id');
            if (tareaId) abrirDrawerDetalle(tareaId);
        }
    });

    // Click explícito en botón ver tarea
    $(document).on('click', '.btn-ver-tarea', function (e) {
        e.stopPropagation();
        const tareaId = $(this).data('id');
        if (tareaId) abrirDrawerDetalle(tareaId);
    });

    // =========================================================================
    // 9. SIDE-OVER DRAWER CLICKUP (PANEL LATERAL DESLIZANTE)
    // =========================================================================
    function abrirDrawerDetalle(id) {
        $.getJSON(ajaxUrl, { accion: 'obtener', ciclo_tarea_id: id, empresa_idx: empresaIdx }, function (res) {
            if (!res.success) {
                alert(res.message || 'No se pudo cargar la tarea');
                return;
            }
            const t = res.tarea;
            const fechaInfo = calcularDias(t.fecha_vencimiento);
            const prio = getPriorityInfo(t, fechaInfo);

            // Título y breadcrumb
            $('#dtTareaTitulo').text(t.titulo);
            $('#dtCicloEntidad').text(`${t.entidad_nombre} (Ciclo #${t.ciclo_id})`);
            $('#dtVencimiento').text(t.fecha_vencimiento_fmt || '--');

            // Estado Badge
            const estId = parseInt(t.estado_id, 10);
            let estBadge = 'bg-secondary';
            if (estId === 2078) estBadge = 'bg-info text-dark';
            if (estId === 2079) estBadge = 'bg-primary';
            if (estId === 2080) estBadge = 'bg-warning text-dark';
            if (estId === 2081) estBadge = 'bg-success';
            $('#dtEstadoBadge').html(`<span class="badge ${estBadge} py-1 px-2">${t.estado_nombre}</span>`);

            // Sigla e ícono visual de Obligación
            const sigla = t.obligacion_sigla || t.obligacion_codigo || 'OBL';
            const icono = t.obligacion_icono || 'fas fa-balance-scale';
            const color = t.obligacion_color || '#0284c7';
            $('#dtObligacionBadge').html(`
                <span class="badge text-white px-2 py-1 shadow-sm" style="background-color: ${color}; font-size: 0.72rem;">
                    <i class="${icono} me-1"></i>${sigla} - ${t.obligacion_codigo}
                </span>
            `);

            // Responsable Principal
            const resp = t.responsable_nombre || 'Sin Asignar';
            const ini = getIniciales(resp);
            const bg = getAvatarColor(resp);
            $('#dtResponsableAvatar').css('background-color', bg).text(ini);
            $('#dtResponsableNombre').text(resp);

            // Bandera de Prioridad
            $('#dtPrioridadFlag').html(`
                <span class="cu-priority-flag ${prio.class}">
                    <i class="${prio.icon}"></i> ${prio.label}
                </span>
            `);

            // Predecesoras
            const $pred = $('#ulPredecesoras');
            $pred.empty();
            if (t.predecesoras && t.predecesoras.length > 0) {
                t.predecesoras.forEach(p => {
                    const obl = p.obligatoria == 1 ? '<span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1" style="font-size: 0.65rem;">Obligatoria</span>' : '';
                    $pred.append(`
                        <li class="list-group-item bg-transparent px-0 py-1 border-0 d-flex justify-content-between align-items-center">
                            <span class="text-truncate" style="max-width: 70%;" title="${p.predecesora_titulo}">
                                <i class="fas fa-arrow-right text-muted me-1"></i>${p.predecesora_titulo} ${obl}
                            </span>
                            <span class="badge bg-light text-dark border">${p.predecesora_estado}</span>
                        </li>
                    `);
                });
            } else {
                $pred.append('<li class="list-group-item bg-transparent text-muted px-0 py-1 border-0">Sin dependencias predecesoras</li>');
            }

            // Matriz RACI
            const $raci = $('#ulRaci');
            $raci.empty();
            if (t.raci && t.raci.length > 0) {
                t.raci.forEach(r => {
                    $raci.append(`
                        <li class="list-group-item bg-transparent px-0 py-1 border-0 d-flex justify-content-between align-items-center">
                            <span><strong class="text-primary">[${r.rol_codigo || 'R'}]</strong> ${r.nombre_completo || r.usuario_nombre}</span>
                            <span class="badge bg-light text-muted border">${r.raci_rol_nombre || r.rol_nombre}</span>
                        </li>
                    `);
                });
            } else {
                $raci.append('<li class="list-group-item bg-transparent text-muted px-0 py-1 border-0">Sin asignaciones registradas</li>');
            }

            // Instructivo y Documento Operativo Asociado (Punto 5)
            let instHtml = '';
            if (t.procedimiento_codigo || t.procedimiento_nombre) {
                const procVerBadge = t.procedimiento_version ? `<span class="badge bg-success ms-1">v${t.procedimiento_version}</span>` : '';
                const procLink = t.procedimiento_archivo_ref ? `
                    <div class="mt-2">
                        <a href="${t.procedimiento_archivo_ref}" target="_blank" class="btn btn-sm btn-outline-danger py-1 px-2" title="Descargar documento controlado vigente">
                            <i class="fas fa-file-pdf me-1"></i>Descargar ${escapeHtml(t.procedimiento_archivo_nombre || 'Procedimiento Vigente')}
                        </a>
                    </div>` : '';
                instHtml += `
                    <div class="p-2 mb-2 bg-light border rounded">
                        <div class="fw-semibold text-primary"><i class="fas fa-file-alt me-1"></i>[${escapeHtml(t.procedimiento_codigo || 'PROC')}] ${escapeHtml(t.procedimiento_nombre || '')} ${procVerBadge}</div>
                        ${procLink}
                    </div>`;
            }
            if (t.instructivo) {
                instHtml += `<pre class="mb-0" style="white-space: pre-wrap; font-family: inherit; font-size: 0.85rem; color: #334155;">${escapeHtml(t.instructivo)}</pre>`;
            } else if (!t.procedimiento_codigo) {
                instHtml = '<span class="text-muted small">Sin instructivo operativo formal asociado.</span>';
            }
            $('#divInstructivoContenido').html(instHtml);

            // Evidencias adjuntas
            const $eviDiv = $('#divTareaEvidencias');
            $eviDiv.empty();
            if (t.evidencias && t.evidencias.length > 0) {
                let htmlEvi = '<ul class="list-group list-group-flush">';
                t.evidencias.forEach(ev => {
                    const hashCorto = ev.hash ? ev.hash.substring(0, 10) + '...' : '';
                    const badgeComp = ev.es_comprobante == 1 ? '<span class="badge bg-success ms-1"><i class="fas fa-certificate me-1"></i>Comprobante</span>' : '';
                    const valida = ev.valida_hasta_fmt ? `<span class="badge bg-light text-dark border ms-1">Vence: ${ev.valida_hasta_fmt}</span>` : '<span class="badge bg-light text-muted border ms-1">Permanente</span>';
                    htmlEvi += `
                        <li class="list-group-item px-1 py-2 d-flex justify-content-between align-items-center">
                            <div>
                                <strong class="d-block small text-dark"><i class="fas fa-file-pdf text-danger me-1"></i>${ev.titulo}</strong>
                                <small class="text-muted font-monospace"><i class="fas fa-fingerprint me-1"></i>${hashCorto}</small>
                                ${badgeComp}
                                ${valida}
                            </div>
                            <div>
                                <a href="${ev.archivo_ref}" target="_blank" class="btn btn-xs btn-outline-primary" title="Descargar / Ver Documento">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        </li>
                    `;
                });
                htmlEvi += '</ul>';
                $eviDiv.html(htmlEvi);
            } else {
                $eviDiv.html('<span class="text-muted small p-2 d-block">Sin evidencias registradas para esta tarea.</span>');
            }

            // Hilo de Conversación / Mensajes
            $('#msgCicloTareaId').val(t.ciclo_tarea_id);
            renderMensajesTarea(t.mensajes || []);

            // Mostrar el panel lateral deslizante (Bootstrap 5 Offcanvas)
            const drawerEl = document.getElementById('modalDetalleTarea');
            if (drawerEl) {
                const offcanvas = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
                offcanvas.show();
            }
        });
    }

    // Renderizar lista de mensajes en el chat del Drawer
    function renderMensajesTarea(mensajes) {
        const $cont = $('#divTareaMensajes');
        $cont.empty();
        $('#badgeTotalMensajesModal').text((mensajes || []).length);

        if (!mensajes || mensajes.length === 0) {
            $cont.html('<span class="text-muted small p-2 d-block"><i class="far fa-comment-dots me-1"></i>No hay notas en este hilo. Sé el primero en comentar.</span>');
            return;
        }

        mensajes.forEach(m => {
            const ini = getIniciales(m.usuario_nombre);
            const bg = getAvatarColor(m.usuario_nombre);
            const esSys = (m.tipo === 'SISTEMA');
            const bubbleClass = esSys ? 'is-system' : '';
            const tipoBadge = esSys ? '<span class="badge bg-secondary-subtle text-secondary ms-1">Sistema</span>' : '';

            const itemHtml = `
                <div class="chat-msg-bubble ${bubbleClass}">
                    <div class="chat-avatar-mini" style="background-color: ${bg};">${ini}</div>
                    <div class="chat-msg-content flex-grow-1">
                        <div class="chat-msg-meta">
                            <strong class="text-dark">${m.usuario_nombre || 'Usuario'}</strong>
                            ${tipoBadge}
                            <span>•</span>
                            <span>${m.creado_en_fmt || m.creado_en}</span>
                        </div>
                        <div class="text-dark">${escapeHtml(m.mensaje)}</div>
                    </div>
                </div>
            `;
            $cont.append(itemHtml);
        });

        $cont.scrollTop($cont[0].scrollHeight);
    }

    // Enviar nuevo mensaje en el hilo
    $('#formAgregarMensaje').on('submit', function (e) {
        e.preventDefault();
        const tareaId = $('#msgCicloTareaId').val();
        const texto = $('#msgTexto').val().trim();
        if (!tareaId || !texto) return;

        const $btn = $('#btnEnviarMensaje');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                accion: 'agregar_mensaje',
                ciclo_tarea_id: tareaId,
                mensaje: texto,
                empresa_idx: empresaIdx
            },
            dataType: 'json',
            success: function (res) {
                $btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-1"></i>Comentar');
                if (res.success) {
                    $('#msgTexto').val('');
                    $.getJSON(ajaxUrl, { accion: 'listar_mensajes', ciclo_tarea_id: tareaId }, function (mRes) {
                        if (mRes.success) renderMensajesTarea(mRes.data);
                    });

                    // Actualizar el conteo en memoria y vistas
                    const tIndex = todasLasTareas.findIndex(x => parseInt(x.ciclo_tarea_id, 10) === parseInt(tareaId, 10));
                    if (tIndex !== -1) {
                        todasLasTareas[tIndex].total_mensajes = (parseInt(todasLasTareas[tIndex].total_mensajes || 0, 10) + 1);
                        actualizarVistas();
                    }
                } else {
                    alert(res.message || 'No se pudo registrar el mensaje');
                }
            },
            error: function () {
                $btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-1"></i>Comentar');
                alert('Error al comunicarse con el servidor.');
            }
        });
    });

    // Iniciar carga
    cargarCombos();
    initDataTable();
    cargarTareas();
});
