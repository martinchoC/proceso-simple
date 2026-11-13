/**
 * Javascript para Portada y Tablero de Gobierno Territorial (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/index.js
 */

document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresaIdx = parseInt(urlParams.get('empresa_id') || '2', 10);
    const ajaxUrl = window.location.pathname.includes('/modules/ml/') ? 'tablero_cumplimiento_ajax.php' : 'modules/ml/tablero_cumplimiento_ajax.php';

    let currentNivel = 'global';
    let currentFiltros = { region_id: 0, territorio_id: 0, empresa_id: 0 };
    let breadcrumbStack = [
        { nivel: 'global', nombre: 'Grupo Casalucho', filtros: { region_id: 0, territorio_id: 0, empresa_id: 0 } }
    ];

    // Datos territoriales para el Mapa interactivo
    const datosPaises = {
        'ARG': {
            nombre: 'Argentina',
            codigo: 'ARG',
            pais_id: 2,
            cumplimiento: 83.3,
            vencidas: 0,
            proximas: 1,
            cumplidas: 5,
            total_tareas: 6,
            semaforo: 'warning',
            semaforo_texto: 'EN CURSO'
        }
    };

    let leafletMap = null;
    let markersLayer = null;

    // Instalaciones con coordenadas geográficas reales (Terminal Puerto Rosales y Sede Martínez)
    const instalacionesGeo = [
        {
            id: 18,
            nombre: 'Terminal de Almacenaje Puerto Rosales',
            codigo: 'TERM-ROSALES',
            pais: 'Argentina',
            pais_codigo: 'ARG',
            pais_id: 2,
            lat: -38.9280,
            lng: -62.0620,
            icono: 'fa-oil-can',
            semaforo: 'success',
            semaforo_texto: 'AL DÍA',
            vencidas: 0,
            proximas: 0,
            cumplidas: 5,
            descripcion: 'Terminal portuaria de almacenaje de petróleo crudo, boyas y parque de tanques en Puerto Rosales (Coronel Rosales, BsAs)'
        },
        {
            id: 19,
            nombre: 'Oficina Comercial Martínez',
            codigo: 'SEDE-MARTINEZ',
            pais: 'Argentina',
            pais_codigo: 'ARG',
            pais_id: 2,
            lat: -34.4920,
            lng: -58.5020,
            icono: 'fa-building',
            semaforo: 'warning',
            semaforo_texto: 'EN GESTIÓN',
            vencidas: 0,
            proximas: 1,
            cumplidas: 5,
            descripcion: 'Sede comercial y administrativa central en Martínez (San Isidro, Pcia. de Buenos Aires)'
        }
    ];

    // =========================================================================
    // 1. INICIALIZACIÓN DEL MAPA CARTOGRÁFICO REAL (LEAFLET + CARTO VOYAGER)
    // =========================================================================
    function initMapaRealLeaflet() {
        if (typeof L === 'undefined') {
            console.warn('Leaflet no está cargado');
            return;
        }

        const mapEl = document.getElementById('mapRealLeaflet');
        if (!mapEl) return;

        // Inicializar mapa centrado en Buenos Aires / Argentina
        leafletMap = L.map('mapRealLeaflet', {
            zoomControl: true,
            scrollWheelZoom: false,
            center: [-36.8, -60.2],
            zoom: 6
        });

        // Capa de OpenStreetMap estándar: 100% libre, pública y sin requerimiento de API Key
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            subdomains: ['a', 'b', 'c'],
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors'
        }).addTo(leafletMap);

        // Forzar ajuste de dimensiones
        setTimeout(function () {
            if (leafletMap) leafletMap.invalidateSize();
        }, 200);

        markersLayer = L.layerGroup().addTo(leafletMap);

        // Renderizar marcadores de instalaciones
        instalacionesGeo.forEach(inst => {
            const pinClass = `pin-${inst.semaforo}`;
            const pulseClass = `pulse-${inst.semaforo}`;
            const customIcon = L.divIcon({
                className: 'custom-geo-marker',
                html: `
                    <div class="geo-pulse-ring ${pulseClass}"></div>
                    <div class="custom-geo-pin ${pinClass}" title="${inst.nombre}">
                        <i class="fas ${inst.icono}"></i>
                    </div>
                `,
                iconSize: [32, 32],
                iconAnchor: [16, 16],
                popupAnchor: [0, -18]
            });

            const marker = L.marker([inst.lat, inst.lng], { icon: customIcon }).addTo(markersLayer);

            const popupHtml = `
                <div style="min-width: 220px; font-family: inherit;">
                    <div class="p-2 border-bottom d-flex align-items-center justify-content-between" style="background-color: #f8fafc;">
                        <div>
                            <strong class="d-block text-dark mb-0">${inst.nombre}</strong>
                            <small class="text-muted"><i class="fas fa-map-marker-alt me-1 text-primary"></i>${inst.pais}</small>
                        </div>
                        <span class="badge bg-${inst.semaforo} ms-2">${inst.semaforo_texto}</span>
                    </div>
                    <div class="p-2">
                        <small class="text-secondary d-block mb-2">${inst.descripcion}</small>
                        <div class="d-flex justify-content-between small mb-2 border rounded p-1 bg-light">
                            <span class="text-danger fw-bold"><i class="fas fa-times-circle me-1"></i>${inst.vencidas} Vencidas</span>
                            <span class="text-warning-emphasis fw-bold"><i class="fas fa-clock me-1"></i>${inst.proximas} Próx.</span>
                            <span class="text-success"><i class="fas fa-check me-1"></i>${inst.cumplidas} Listas</span>
                        </div>
                        <div class="d-grid">
                            <a href="ciclos_tareas.php?empresa_id=${empresaIdx}&modulo_id=3&entidad_id=${inst.id}&pais_id=${inst.pais_id}" 
                               class="btn btn-xs btn-primary py-1">
                                <i class="fas fa-tasks me-1"></i>Ver Tareas en Planner
                            </a>
                        </div>
                    </div>
                </div>
            `;
            marker.bindPopup(popupHtml);
        });

        // Botones de enfoque territorial rápido
        $('.btn-map-zoom').on('click', function () {
            const target = $(this).data('target');
            $('.btn-map-zoom').removeClass('active');
            $(this).addClass('active');

            if (target === 'arg') {
                leafletMap.flyTo([-36.8, -60.2], 6, { duration: 1.2 });
            } else if (target === 'rosales') {
                leafletMap.flyTo([-38.9280, -62.0620], 12, { duration: 1.2 });
            } else if (target === 'martinez') {
                leafletMap.flyTo([-34.4920, -58.5020], 13, { duration: 1.2 });
            }
        });

        // Clic en país (Lista lateral del Monitor)
        $('.country-row-item').on('click', function () {
            const paisCod = $(this).data('country');
            const data = datosPaises[paisCod];
            if (!data || !data.pais_id) return;

            $('.country-row-item').removeClass('active');
            $(this).addClass('active');

            if (paisCod === 'ARG') {
                if (leafletMap) leafletMap.flyTo([-36.8, -60.2], 6, { duration: 1.2 });
                cambiarNivel('pais', { region_id: 1, territorio_id: 2, empresa_id: 2 }, 'Argentina');
            }

            $('html, body').animate({
                scrollTop: $("#seccionDrilldown").offset().top - 70
            }, 300);
        });
    }

    // =========================================================================
    // 2. NAVEGACIÓN Y CARGA DE DATOS
    // =========================================================================
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

        $('.btn-drill-nivel').removeClass('active');
        $(`.btn-drill-nivel[data-nivel="${currentNivel}"]`).addClass('active');
    }

    function cambiarNivel(nuevoNivel, filtros, nombreItem) {
        currentNivel = nuevoNivel;
        currentFiltros = filtros;

        breadcrumbStack.push({
            nivel: nuevoNivel,
            nombre: nombreItem,
            filtros: filtros
        });

        cargarDatos();
    }

    function cargarDatos() {
        $('#tbodyDrilldown').html(`
            <tr>
                <td colspan="7" class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                    Actualizando indicadores...
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
                return;
            }

            const d = res.data;

            // 1. KPIs
            const k = d.kpis;
            $('#kpiVencidas').text(k.vencidas || 0);
            $('#kpiProximas').text(k.proximas || 0);
            $('#kpiEnTermino').text(k.en_termino || 0);
            $('#kpiTasa').text((k.tasa_cumplimiento || 0) + '%');
            $('#kpiTasaBar').css('width', (k.tasa_cumplimiento || 0) + '%');

            // 2. Grilla
            renderTablaDrilldown(d.items, d.siguiente_nivel);

            // 3. Tareas Críticas
            renderTareasCriticas(d.tareas_criticas || d.alertas || []);
        });
    }

    function renderTablaDrilldown(items, siguienteNivel) {
        const tbody = $('#tbodyDrilldown');
        tbody.empty();

        if (!items || items.length === 0) {
            tbody.html(`
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        No hay registros en este nivel de supervisión.
                    </td>
                </tr>
            `);
            return;
        }

        items.forEach(item => {
            const semColor = item.semaforo || 'secondary';
            const semBadge = `<span class="badge bg-${semColor} px-2 py-1">${item.semaforo_texto || 'NORMAL'}</span>`;
            
            const btnAccion = siguienteNivel ? `
                <button class="btn btn-xs btn-outline-primary btn-entrar-nivel px-2 py-1" 
                        data-siguiente="${siguienteNivel}" 
                        data-id="${item.id}" 
                        data-nombre="${item.nombre}">
                    Explorar <i class="fas fa-chevron-right ms-1"></i>
                </button>
            ` : `
                <a href="ciclos_tareas.php?empresa_id=${empresaIdx}&modulo_id=3&entidad_id=${item.id}" class="btn btn-xs btn-primary px-2 py-1">
                    <i class="fas fa-tasks me-1"></i>Ver Tareas
                </a>
            `;

            const tr = `
                <tr>
                    <td>
                        <strong class="d-block text-dark">${item.nombre}</strong>
                        <small class="text-muted">${item.tipo_etiqueta || ''}</small>
                    </td>
                    <td class="text-center">${semBadge}</td>
                    <td class="text-center fw-bold text-${parseInt(item.vencidas, 10) > 0 ? 'danger' : 'muted'}">
                        ${item.vencidas || 0}
                    </td>
                    <td class="text-center fw-bold text-${parseInt(item.proximas, 10) > 0 ? 'warning' : 'muted'}">
                        ${item.proximas || 0}
                    </td>
                    <td class="text-center text-secondary">${item.total_tareas || 0}</td>
                    <td style="min-width: 140px;">
                        <div class="d-flex align-items-center justify-content-between small mb-1">
                            <span class="text-muted small">Avance:</span>
                            <span class="fw-bold">${item.porcentaje}%</span>
                        </div>
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar bg-${semColor}" style="width: ${item.porcentaje}%"></div>
                        </div>
                    </td>
                    <td class="text-center">${btnAccion}</td>
                </tr>
            `;
            tbody.append(tr);
        });

        $('.btn-entrar-nivel').off('click').on('click', function () {
            const sig = $(this).data('siguiente');
            const id = parseInt($(this).data('id'), 10);
            const nom = $(this).data('nombre');

            const nuevosFiltros = Object.assign({}, currentFiltros);
            if (currentNivel === 'global') nuevosFiltros.region_id = id;
            else if (currentNivel === 'region') nuevosFiltros.territorio_id = id;
            else if (currentNivel === 'pais') nuevosFiltros.empresa_id = id;

            cambiarNivel(sig, nuevosFiltros, nom);
        });
    }

    // Renderizar Tareas Críticas Inmediatas
    function renderTareasCriticas(alertas) {
        const container = $('#contenedorTareasCriticas');
        container.empty();

        if (!alertas || alertas.length === 0) {
            container.html(`
                <div class="alert alert-success border-0 py-3 text-center my-1">
                    <i class="fas fa-check-circle fa-2x text-success mb-2 d-block"></i>
                    <strong>Excelente: No hay tareas vencidas ni próximas inmediatas.</strong>
                </div>
            `);
            return;
        }

        alertas.forEach(a => {
            const esVencida = a.urgencia === 'VENCIDA';
            const badgeUrgencia = esVencida ?
                `<span class="badge bg-danger">VENCIDA (${Math.abs(a.dias_atraso)}d)</span>` :
                `<span class="badge bg-warning text-dark">PRÓXIMA (en ${Math.abs(a.dias_atraso)}d)</span>`;

            const oblSigla = a.obligacion_sigla || a.obligacion_codigo || 'LEGAL';
            const oblIcono = a.obligacion_icono || 'fas fa-shield-alt';
            const oblColor = a.obligacion_color || '#0284c7';
            const badgeObl = `<span class="badge text-white ms-2 shadow-sm" style="background-color: ${oblColor};"><i class="${oblIcono} me-1"></i>${oblSigla}</span>`;

            const card = `
                <div class="card mb-2 border-0 shadow-sm border-start border-4 border-${esVencida ? 'danger' : 'warning'}">
                    <div class="card-body p-2 d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                        <div class="me-3 mb-2 mb-md-0">
                            <div class="d-flex align-items-center mb-1">
                                ${badgeUrgencia}
                                ${badgeObl}
                                <small class="text-muted ms-2"><i class="fas fa-calendar-alt me-1"></i>Vence: <strong>${a.fecha_vencimiento_fmt}</strong></small>
                            </div>
                            <h6 class="mb-0 fw-bold text-dark">${a.titulo}</h6>
                            <small class="text-muted">
                                ${a.entidad_nombre} • Resp: <strong>${a.responsable_nombre || 'Sin Asignar'}</strong>
                            </small>
                        </div>
                        <div class="text-nowrap">
                            <a href="ciclos_tareas.php?empresa_id=${empresaIdx}&modulo_id=3" class="btn btn-xs btn-${esVencida ? 'danger' : 'outline-primary'} py-1 px-3">
                                Gestionar <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            `;
            container.append(card);
        });
    }

    // Botones de nivel en la barra superior
    $('.btn-drill-nivel').on('click', function () {
        const nivel = $(this).data('nivel');
        if (nivel === currentNivel) return;

        if (nivel === 'global') {
            breadcrumbStack = [{ nivel: 'global', nombre: 'Grupo Casalucho', filtros: { region_id: 0, territorio_id: 0, empresa_id: 0 } }];
            currentFiltros = { region_id: 0, territorio_id: 0, empresa_id: 0 };
        }
        currentNivel = nivel;
        cargarDatos();
    });

    $(document).on('click', '.btn-nav-bread', function () {
        const idx = parseInt($(this).data('index'), 10);
        const item = breadcrumbStack[idx];
        breadcrumbStack = breadcrumbStack.slice(0, idx + 1);
        currentNivel = item.nivel;
        currentFiltros = item.filtros;
        cargarDatos();
    });

    $('#btnRecargarDrill').on('click', function () {
        cargarDatos();
    });

    initMapaRealLeaflet();
    cargarDatos();
});
