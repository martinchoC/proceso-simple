/**
 * Javascript para Tareas Operativas - Modelo Microsoft Planner (Página 343 - Módulo 3 Matriz Legal)
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
    let sortableInstances = [];

    // Colores para avatares
    const avatarColors = [
        '#0078d4', '#107c41', '#5c2d91', '#d83b01', '#008272', 
        '#b4009e', '#038387', '#498205', '#e3008c', '#69797e'
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

    // Inicializar Tooltips
    function initTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // Calcular días de retraso / restantes
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

    // =========================================================================
    // 1. CARGA DE DATOS PRINCIPALES
    // =========================================================================
    function cargarTareas() {
        $.getJSON(ajaxUrl, {
            accion: 'listar',
            empresa_idx: empresaIdx,
            filtro_entidad: $('#filtroEntidad').val(),
            filtro_ciclo: $('#filtroCiclo').val()
        }, function (res) {
            if (!res.success) {
                alert(res.message || 'Error al cargar las tareas');
                return;
            }

            todasLasTareas = res.data || [];
            if (res.permisos) permisosUsuario = res.permisos;

            // Actualizar contador superior
            $('#badgeTotalTareas').html(`<i class="fas fa-check-square text-primary me-1"></i>${todasLasTareas.length} Tareas en total`);

            // Renderizar vistas
            renderPlannerBoard();
            renderGraficos();
            if (tablaTareas) {
                tablaTareas.clear().rows.add(todasLasTareas).draw();
            }
        });
    }

    // =========================================================================
    // 2. RENDERIZADO DEL TABLERO KANBAN ESTILO MICROSOFT PLANNER
    // =========================================================================
    function renderPlannerBoard() {
        const groupBy = $('#plannerGroupBy').val() || 'estado';
        const searchTerm = ($('#plannerSearch').val() || '').toLowerCase().trim();
        const container = $('#plannerBoardContainer');
        container.empty();

        // Destruir instancias de Sortable previas
        sortableInstances.forEach(inst => inst.destroy());
        sortableInstances = [];

        // Filtrar tareas según buscador y píldoras rápidas
        const tareasFiltradas = todasLasTareas.filter(t => {
            const fechaInfo = calcularDias(t.fecha_vencimiento);
            const cerrada = (parseInt(t.estado_id, 10) === 2081 || parseInt(t.estado_id, 10) === 2083);

            if (filtroRapidoActual === 'vencidas' && (cerrada || !fechaInfo.vencida)) return false;
            if (filtroRapidoActual === 'proximas' && (cerrada || !fechaInfo.proxima)) return false;

            if (searchTerm) {
                const matchTitulo = (t.titulo || '').toLowerCase().includes(searchTerm);
                const matchResp = (t.responsable_nombre || '').toLowerCase().includes(searchTerm);
                const matchEnt = (t.entidad_nombre || '').toLowerCase().includes(searchTerm);
                const matchObl = (t.obligacion_codigo || '').toLowerCase().includes(searchTerm);
                if (!matchTitulo && !matchResp && !matchEnt && !matchObl) return false;
            }

            return true;
        });

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
            // Filtrar tareas que caen en esta columna
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

            // HTML de la columna
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

    // =========================================================================
    // 3. RENDERIZADO DE LA TARJETA INDIVIDUAL (PLANNER CARD)
    // =========================================================================
    function renderTaskCard(t) {
        const id = t.ciclo_tarea_id;
        const estadoId = parseInt(t.estado_id, 10);
        const cerrada = (estadoId === 2081 || estadoId === 2083);
        const fechaInfo = calcularDias(t.fecha_vencimiento);

        // Clase de borde según estado / retraso
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

        // Checkbox circular de Planner
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

        // Avatar del Responsable
        const respNombre = t.responsable_nombre || 'Sin Asignar';
        const iniciales = getIniciales(respNombre);
        const avatarBg = getAvatarColor(respNombre);
        const avatarHtml = `
            <div class="planner-avatar" style="background-color: ${avatarBg};" data-bs-toggle="tooltip" title="Responsable: ${respNombre}">
                ${iniciales}
            </div>
        `;

        // Indicadores (Evidencias, Revisión, Predecesoras)
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

        return `
            <div class="planner-card ${cardClass}" data-id="${id}" data-estado-id="${estadoId}">
                <!-- Encabezado de la tarjeta con check rápido y título -->
                <div class="d-flex align-items-start">
                    <div class="planner-check-circle ${checkClass} btn-quick-check" data-id="${id}" data-estado-id="${estadoId}" title="${cerrada ? 'Reabrir tarea' : 'Marcar cumplida'}">
                        ${checkIcon}
                    </div>
                    <div class="flex-grow-1 btn-ver-tarea" data-id="${id}">
                        <div class="planner-card-title">${t.titulo}</div>
                    </div>
                </div>

                <!-- Etiquetas de Contexto (Entidad y Obligación) -->
                <div class="mb-2">
                    <span class="planner-tag bg-light text-dark border">
                        <i class="fas fa-industry text-primary me-1"></i>${t.entidad_nombre}
                    </span>
                    <span class="planner-tag bg-secondary-subtle text-secondary border">
                        ${t.obligacion_codigo}
                    </span>
                </div>

                <!-- Pie de la tarjeta: Vencimiento, Íconos y Avatar -->
                <div class="planner-card-footer">
                    <div>${vtoBadge}</div>
                    <div class="d-flex align-items-center">
                        <div class="planner-icons me-2">
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
    // 4. VISTA DE GRÁFICOS Y MÉTRICAS ESTILO MICROSOFT PLANNER
    // =========================================================================
    function renderGraficos() {
        let overdue = 0, upcoming = 0, progress = 0, completed = 0;
        const mapResp = new Map();
        const mapBuckets = new Map();

        todasLasTareas.forEach(t => {
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

            // Conteo por Depósito
            const ent = t.entidad_nombre || 'General';
            if (!mapBuckets.has(ent)) mapBuckets.set(ent, { total: 0, vencidas: 0 });
            mapBuckets.get(ent).total++;
            if (!cerrada && fechaInfo.vencida) mapBuckets.get(ent).vencidas++;
        });

        const total = todasLasTareas.length || 1;
        $('#chartCountOverdue').text(overdue);
        $('#chartCountProgress').text(progress);
        $('#chartCountUpcoming').text(upcoming);
        $('#chartCountCompleted').text(completed);

        $('#barOverdue').css('width', (overdue / total * 100) + '%');
        $('#barUpcoming').css('width', (upcoming / total * 100) + '%');
        $('#barProgress').css('width', (progress / total * 100) + '%');
        $('#barCompleted').css('width', (completed / total * 100) + '%');

        // Lista de Miembros
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

        // Lista de Depósitos
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
        $('#chartBucketsList').html(htmlBuckets || '<p class="text-muted text-center">Sin depósitos registrados</p>');
    }

    // =========================================================================
    // 5. TRANSICIONES RÁPIDAS DE ESTADO (DRAG & DROP / CLICK)
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
                observaciones: 'Actualización rápida desde Planner'
            },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    cargarTareas();
                } else {
                    alert('No se pudo mover la tarea: ' + res.message);
                    renderPlannerBoard();
                }
            },
            error: function () {
                alert('Error al conectar con el servidor.');
                renderPlannerBoard();
            }
        });
    }

    // Click en Checkbox circular de completado rápido
    $(document).on('click', '.btn-quick-check', function (e) {
        e.stopPropagation();
        const tareaId = $(this).data('id');
        const estadoId = parseInt($(this).data('estado-id'), 10);

        if (estadoId === 2081) {
            // Reabrir (volver a En curso)
            if (confirm('¿Desea reabrir esta tarea y volverla a poner En Curso?')) {
                ejecutarTransicionRapida(tareaId, 2079);
            }
        } else {
            // Cumplir directamente
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
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
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
                        return `<div><span class="badge bg-secondary me-1">${d.obligacion_codigo}</span><span class="small fw-semibold">${d.obligacion_titulo}</span></div>`;
                    }
                },
                {
                    data: null,
                    render: function (d) {
                        return `<span class="fw-bold text-dark">${d.titulo}</span>`;
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
                        return `<button class="btn btn-sm btn-outline-primary btn-ver-tarea" data-id="${d.ciclo_tarea_id}" title="Ver Detalle"><i class="fas fa-eye"></i></button>`;
                    }
                }
            ]
        });
    }

    // =========================================================================
    // 7. EVENTOS Y MODALES
    // =========================================================================

    // Cargar combos iniciales
    function cargarCombos() {
        $.getJSON(ajaxUrl, { accion: 'combos', empresa_idx: empresaIdx }, function (res) {
            if (!res.success) return;
            const $ent = $('#filtroEntidad');
            $ent.empty().append('<option value="">-- Todas las instalaciones --</option>');
            res.entidades.forEach(e => {
                $ent.append(`<option value="${e.entidad_id}">${e.nombre} (${e.codigo})</option>`);
            });
        });
    }

    // Cambio en "Agrupar por"
    $('#plannerGroupBy').on('change', function () {
        renderPlannerBoard();
    });

    // Buscador instantáneo de Planner
    $('#plannerSearch').on('keyup', function () {
        renderPlannerBoard();
        if (tablaTareas) tablaTareas.search($(this).val()).draw();
    });

    // Filtro de instalación
    $('#filtroEntidad').on('change', function () {
        cargarTareas();
    });

    // Botones de filtro rápido de Planner
    $('.btn-filtro-planner').on('click', function () {
        $('.btn-filtro-planner').removeClass('active');
        $(this).addClass('active');
        filtroRapidoActual = $(this).data('filter');
        renderPlannerBoard();
    });

    // Recargar datos
    $('#btnRecargarPlanner').on('click', function () {
        cargarTareas();
    });

    // Ver Detalle de Tarea
    $(document).on('click', '.btn-ver-tarea', function () {
        const id = $(this).data('id');
        $.getJSON(ajaxUrl, { accion: 'obtener', ciclo_tarea_id: id, empresa_idx: empresaIdx }, function (res) {
            if (!res.success) return;
            const t = res.tarea;
            $('#dtTareaTitulo').text(t.titulo);
            $('#dtCicloEntidad').text(`${t.entidad_nombre} (Ciclo #${t.ciclo_id})`);
            $('#dtObligacion').text(`${t.obligacion_codigo} - ${t.obligacion_titulo}`);
            $('#dtVencimiento').text(t.fecha_vencimiento_fmt || '--');
            $('#dtFechaCierre').text(t.fecha_cierre_fmt || 'En gestión');
            $('#dtEstadoBadge').html(`<span class="badge bg-primary fs-7">${t.estado_nombre}</span>`);

            // Matriz RACI
            const $raci = $('#ulRaci');
            $raci.empty();
            if (t.raci && t.raci.length > 0) {
                t.raci.forEach(r => {
                    $raci.append(`<li class="list-group-item d-flex justify-content-between align-items-center py-1">
                        <span><strong>[${r.rol_codigo}]</strong> ${r.usuario_nombre}</span>
                        <span class="badge bg-light text-dark border">${r.rol_nombre}</span>
                    </li>`);
                });
            } else {
                $raci.append('<li class="list-group-item text-muted">Sin asignaciones registradas</li>');
            }

            // Instructivo
            $('#divInstructivoContenido').html(t.instructivo ? `<pre class="mb-0" style="white-space: pre-wrap; font-family: inherit;">${t.instructivo}</pre>` : '<span class="text-muted small">Sin instructivo operativo formal asociado.</span>');

            $('#modalDetalleTarea').modal('show');
        });
    });

    // Iniciar
    cargarCombos();
    initDataTable();
    cargarTareas();
});
