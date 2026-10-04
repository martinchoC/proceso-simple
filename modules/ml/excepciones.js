/**
 * JavaScript para Excepciones de Cumplimiento (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/excepciones.js
 */

document.addEventListener('DOMContentLoaded', function () {
    let tabla = null;
    let permisosUsuario = [];
    let esAbogado = false;
    let asignadasCache = [];

    const urlParams = new URLSearchParams(window.location.search);
    const empresaIdx = urlParams.get('empresa_id') || urlParams.get('empresa_idx') || '2';
    const preseleccionarAsigId = urlParams.get('asignada_id') || '';

    // Inicializar tooltips de Bootstrap
    function initTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"], [title]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            if (!bootstrap.Tooltip.getInstance(tooltipTriggerEl)) {
                return new bootstrap.Tooltip(tooltipTriggerEl, { container: 'body' });
            }
        });
    }

    // 1. Cargar Permisos
    function cargarPermisos() {
        fetch(`excepciones_ajax.php?accion=permisos&empresa_idx=${empresaIdx}&pagina_idx=${PAGINA_ID}`)
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    permisosUsuario = res.funciones || [];
                    esAbogado = res.es_abogado || false;
                    renderizarBotonNuevo();
                }
            })
            .catch(err => console.error("Error al cargar permisos:", err));
    }

    function renderizarBotonNuevo() {
        const container = document.getElementById('btn-container-excepcion');
        if (!container) return;
        container.innerHTML = '';

        if (permisosUsuario.includes('solicitar_excepcion')) {
            const btn = document.createElement('button');
            btn.className = 'btn btn-sm btn-warning';
            btn.innerHTML = '<i class="fas fa-plus me-1"></i>Solicitar Excepción';
            btn.addEventListener('click', () => abrirModalExcepcion(preseleccionarAsigId));
            container.appendChild(btn);
        }
    }

    // 2. Cargar Combos
    function cargarCombos() {
        return fetch(`excepciones_ajax.php?accion=combos&empresa_idx=${empresaIdx}&pagina_idx=${PAGINA_ID}`)
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    asignadasCache = res.data.obligaciones_asignadas || [];

                    const selFiltro = document.getElementById('filtroAsignada');
                    const selModal = document.getElementById('exc_asignada_id');
                    
                    selFiltro.innerHTML = '<option value="">-- Todas las asignaciones --</option>';
                    selModal.innerHTML = '<option value="">-- Seleccionar obligación asignada --</option>';

                    asignadasCache.forEach(a => {
                        const optF = document.createElement('option');
                        optF.value = a.obligacion_asignada_id;
                        optF.textContent = `${a.entidad_nombre} - [${a.norma_codigo}] ${a.obligacion_codigo}`;
                        selFiltro.appendChild(optF);

                        const optM = document.createElement('option');
                        optM.value = a.obligacion_asignada_id;
                        optM.textContent = `${a.entidad_nombre} - [${a.norma_codigo}] ${a.obligacion_codigo} (${a.obligacion_descripcion.substring(0, 50)}...)`;
                        optM.setAttribute('data-legal', a.es_legal || 0);
                        selModal.appendChild(optM);
                    });

                    if (preseleccionarAsigId) {
                        selFiltro.value = preseleccionarAsigId;
                    }
                }
            })
            .catch(err => console.error("Error al cargar combos:", err));
    }

    // Detectar si la obligación asignada seleccionada es legal
    document.getElementById('exc_asignada_id').addEventListener('change', function () {
        const selected = this.options[this.selectedIndex];
        const isLegal = selected.getAttribute('data-legal') == '1';
        const alerta = document.getElementById('alertaNormaLegal');
        const txtFundamento = document.getElementById('exc_fundamento');
        const lblFundamento = document.getElementById('lblFundamento');

        if (isLegal) {
            alerta.classList.remove('d-none');
            lblFundamento.innerHTML = 'Fundamento Jurídico / Resolución de Autoridad <span class="text-danger">*</span>';
            txtFundamento.required = true;
        } else {
            alerta.classList.add('d-none');
            lblFundamento.innerHTML = 'Fundamento Técnico / Soporte Opcional';
            txtFundamento.required = false;
        }
    });

    // 3. Inicializar DataTable
    function initDataTable() {
        tabla = $('#tablaExcepciones').DataTable({
            responsive: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            order: [[0, 'desc']],
            dom: '<"row mb-2"<"col-12 col-md-6"><"col-12 col-md-6 text-end"f>>rt<"row mt-3 align-items-center"<"col-12 col-md-6 d-flex flex-wrap align-items-center gap-3"li><"col-12 col-md-6 d-flex justify-content-md-end justify-content-center"p>>',
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            ajax: {
                url: `excepciones_ajax.php`,
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.empresa_idx = empresaIdx;
                    d.pagina_idx = PAGINA_ID;
                    d.obligacion_asignada_id = document.getElementById('filtroAsignada').value;
                    d.filtro_estado = document.getElementById('filtroEstado').value;
                },
                dataSrc: function (json) {
                    if (!json.success) {
                        alert(json.message || "Error al cargar datos");
                        return [];
                    }
                    return json.data || [];
                }
            },
            columns: [
                {
                    data: 'excepcion_id',
                    className: 'text-center align-middle',
                    render: function (data) {
                        return `<span class="badge bg-secondary">#${data}</span>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        const ent = data.entidad_nombre || '';
                        const obl = data.obligacion_codigo || '';
                        const nor = data.norma_codigo || '';
                        const legalBadge = data.es_legal == 1 ? '<span class="badge bg-danger ms-1">Legal</span>' : '<span class="badge bg-primary ms-1">Corporativa</span>';
                        return `<div><strong>${ent}</strong></div><div class="small text-muted"><span class="badge bg-light text-dark border me-1">${nor}</span>${obl}${legalBadge}</div>`;
                    }
                },
                {
                    data: 'motivo',
                    render: function (data) {
                        return `<div class="small">${data || '-'}</div>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        const fund = data.fundamento ? `<div class="small text-muted">${data.fundamento.substring(0, 70)}${data.fundamento.length > 70 ? '...' : ''}</div>` : '';
                        const ref = data.fundamento_ref ? `<div><span class="badge bg-light text-dark border font-monospace">${data.fundamento_ref}</span></div>` : '';
                        return `${fund}${ref}` || '<span class="text-muted small"><em>Sin fundamento</em></span>';
                    }
                },
                {
                    data: null,
                    className: 'text-center align-middle',
                    render: function (data) {
                        const desde = data.vigente_desde || '';
                        const hasta = data.vigente_hasta || '';
                        const expirada = data.es_expirada == 1 ? '<div class="mt-1"><span class="badge bg-danger">Vencida</span></div>' : '';
                        return `<span class="small font-monospace">${desde} al ${hasta}</span>${expirada}`;
                    }
                },
                {
                    data: null,
                    className: 'text-center align-middle',
                    render: function (data) {
                        const estId = parseInt(data.tabla_estado_registro_id);
                        const estNom = data.estado_nombre || 'Estado';
                        let badgeClass = 'bg-secondary';
                        if (estId === 2087) badgeClass = 'bg-info text-dark';
                        else if (estId === 2088) badgeClass = 'bg-warning text-dark';
                        else if (estId === 2089) badgeClass = 'bg-success';
                        else if (estId === 2090) badgeClass = 'bg-danger';
                        else if (estId === 2091) badgeClass = 'bg-dark';

                        return `<span class="badge ${badgeClass}">${estNom}</span>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        const sol = data.solicitada_por_nombre ? `<div><small class="text-muted">Sol: ${data.solicitada_por_nombre}</small></div>` : '';
                        const apr = data.aprobada_por_nombre ? `<div><small class="text-success">Apr: ${data.aprobada_por_nombre}</small></div>` : '';
                        const leg = data.aprobada_legal_por_nombre ? `<div><small class="text-primary fw-bold">Dictamen Legal: ${data.aprobada_legal_por_nombre}</small></div>` : '';
                        return `${sol}${apr}${leg}` || '<span class="text-muted small">-</span>';
                    }
                },
                {
                    data: null,
                    className: 'text-center align-middle',
                    orderable: false,
                    render: function (data) {
                        const estId = parseInt(data.tabla_estado_registro_id);
                        const canEsc = permisosUsuario.includes('enviar_aprobacion_legal_excepcion');
                        const canApr = permisosUsuario.includes('aprobar_excepcion');
                        const canRech = permisosUsuario.includes('rechazar_excepcion');
                        const canRev = permisosUsuario.includes('revocar_excepcion');

                        let btns = '<div class="btn-group btn-group-sm" role="group">';

                        // Escalar a legal (si está en 2087 y es legal)
                        if (canEsc && estId === 2087 && data.es_legal == 1) {
                            btns += `<button type="button" class="btn btn-outline-warning btn-escalar" data-id="${data.excepcion_id}" title="Escalar a Legal">
                                        <i class="fas fa-balance-scale"></i>
                                     </button>`;
                        }

                        // Aprobar (si está en 2087 o 2088)
                        if (canApr && (estId === 2087 || estId === 2088)) {
                            btns += `<button type="button" class="btn btn-outline-success btn-aprobar" data-id="${data.excepcion_id}" title="Aprobar Excepción">
                                        <i class="fas fa-check"></i>
                                     </button>`;
                        }

                        // Rechazar
                        if (canRech && (estId === 2087 || estId === 2088)) {
                            btns += `<button type="button" class="btn btn-outline-danger btn-rechazar" data-id="${data.excepcion_id}" title="Rechazar Excepción">
                                        <i class="fas fa-times"></i>
                                     </button>`;
                        }

                        // Revocar anticipadamente (si está en 2089)
                        if (canRev && estId === 2089) {
                            btns += `<button type="button" class="btn btn-outline-dark btn-revocar" data-id="${data.excepcion_id}" title="Revocar Excepción">
                                        <i class="fas fa-ban"></i>
                                     </button>`;
                        }

                        btns += '</div>';
                        return btns;
                    }
                }
            ],
            drawCallback: function () {
                initTooltips();
            }
        });
    }

    // 4. Modal Solicitar Excepción
    function abrirModalExcepcion(preAsigId = '') {
        const form = document.getElementById('formExcepcion');
        form.reset();

        document.getElementById('exc_vigente_desde').value = new Date().toISOString().split('T')[0];
        // Por defecto 6 meses hacia adelante
        const dHasta = new Date();
        dHasta.setMonth(dHasta.getMonth() + 6);
        document.getElementById('exc_vigente_hasta').value = dHasta.toISOString().split('T')[0];

        if (preAsigId) {
            document.getElementById('exc_asignada_id').value = preAsigId;
            document.getElementById('exc_asignada_id').dispatchEvent(new Event('change'));
        }

        const modal = new bootstrap.Modal(document.getElementById('modalExcepcion'));
        modal.show();
    }

    // Guardar Solicitud
    document.getElementById('formExcepcion').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('accion', 'solicitar');
        formData.append('empresa_idx', empresaIdx);
        formData.append('pagina_idx', PAGINA_ID);

        const btn = document.getElementById('btnGuardarExcepcion');
        btn.disabled = true;

        fetch('excepciones_ajax.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(res => {
                btn.disabled = false;
                if (res.success) {
                    bootstrap.Modal.getInstance(document.getElementById('modalExcepcion')).hide();
                    tabla.ajax.reload(null, false);
                } else {
                    alert(res.message || "Error al solicitar");
                }
            })
            .catch(err => {
                btn.disabled = false;
                console.error("Error:", err);
                alert("Error de comunicación.");
            });
    });

    // 5. Transiciones de Estado
    function ejecutarAccion(accion, id, dataExtra = {}) {
        const formData = new FormData();
        formData.append('accion', accion);
        formData.append('excepcion_id', id);
        formData.append('empresa_idx', empresaIdx);
        formData.append('pagina_idx', PAGINA_ID);

        for (const [k, v] of Object.entries(dataExtra)) {
            formData.append(k, v);
        }

        fetch('excepciones_ajax.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    tabla.ajax.reload(null, false);
                } else {
                    alert(res.message || "Error al procesar acción");
                }
            })
            .catch(err => {
                console.error("Error:", err);
                alert("Error de comunicación con el servidor.");
            });
    }

    // Listeners en la tabla
    $('#tablaExcepciones tbody').on('click', '.btn-escalar', function () {
        const id = $(this).data('id');
        if (confirm('¿Desea escalar esta excepción al Área Legal para dictamen?')) {
            ejecutarAccion('escalar_legal', id);
        }
    });

    $('#tablaExcepciones tbody').on('click', '.btn-aprobar', function () {
        const id = $(this).data('id');
        if (confirm('¿Confirma la aprobación de esta excepción temporal? La entidad quedará exenta mientras dure su vigencia.')) {
            ejecutarAccion('aprobar', id);
        }
    });

    $('#tablaExcepciones tbody').on('click', '.btn-rechazar', function () {
        const id = $(this).data('id');
        const motivo = prompt('Indique el motivo del rechazo de la excepción:');
        if (motivo !== null) {
            ejecutarAccion('rechazar', id, { motivo_rechazo: motivo });
        }
    });

    $('#tablaExcepciones tbody').on('click', '.btn-revocar', function () {
        const id = $(this).data('id');
        if (confirm('¿Confirma que desea revocar esta excepción vigente? La obligación volverá a regir plenamente para la entidad.')) {
            ejecutarAccion('revocar', id);
        }
    });

    // Filtros
    document.getElementById('filtroAsignada').addEventListener('change', () => tabla.ajax.reload());
    document.getElementById('filtroEstado').addEventListener('change', () => tabla.ajax.reload());
    document.getElementById('btnRecargar').addEventListener('click', () => tabla.ajax.reload());

    // Arranque
    cargarPermisos();
    cargarCombos().then(() => {
        initDataTable();
    });
});
