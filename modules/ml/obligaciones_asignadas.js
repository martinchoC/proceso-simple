/**
 * JavaScript para Obligaciones Asignadas y Orígenes Consolidados (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/obligaciones_asignadas.js
 */

document.addEventListener('DOMContentLoaded', function () {
    let tabla = null;
    let permisosUsuario = [];

    const urlParams = new URLSearchParams(window.location.search);
    const empresaIdx = urlParams.get('empresa_id') || urlParams.get('empresa_idx') || '2';

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
        fetch(`obligaciones_asignadas_ajax.php?accion=permisos&empresa_idx=${empresaIdx}&pagina_idx=${PAGINA_ID}`)
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    permisosUsuario = res.funciones || [];
                }
            })
            .catch(err => console.error("Error al cargar permisos:", err));
    }

    // 2. Cargar Combos para Filtros
    function cargarCombos() {
        return fetch(`obligaciones_asignadas_ajax.php?accion=combos&empresa_idx=${empresaIdx}&pagina_idx=${PAGINA_ID}`)
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    const selEnt = document.getElementById('filtroEntidad');
                    selEnt.innerHTML = '<option value="">-- Todas las entidades --</option>';
                    (res.data.entidades || []).forEach(e => {
                        const opt = document.createElement('option');
                        opt.value = e.entidad_id;
                        opt.textContent = `${e.nombre} (${e.tipo_nombre || e.codigo})`;
                        selEnt.appendChild(opt);
                    });

                    const selNor = document.getElementById('filtroNorma');
                    selNor.innerHTML = '<option value="">-- Todas las normas --</option>';
                    (res.data.normas || []).forEach(n => {
                        const opt = document.createElement('option');
                        opt.value = n.norma_id;
                        opt.textContent = `${n.codigo} - ${n.titulo.substring(0, 45)}...`;
                        selNor.appendChild(opt);
                    });
                }
            })
            .catch(err => console.error("Error al cargar combos:", err));
    }

    // 3. Inicializar DataTable
    function initDataTable() {
        tabla = $('#tablaObligacionesAsignadas').DataTable({
            responsive: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            order: [[0, 'asc'], [1, 'asc']],
            dom: '<"row mb-2"<"col-12 col-md-6"><"col-12 col-md-6 text-end"f>>rt<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>',
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            ajax: {
                url: `obligaciones_asignadas_ajax.php`,
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.empresa_idx = empresaIdx;
                    d.pagina_idx = PAGINA_ID;
                    d.entidad_id = document.getElementById('filtroEntidad').value;
                    d.norma_id = document.getElementById('filtroNorma').value;
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
                    data: null,
                    render: function (data) {
                        const nom = data.entidad_nombre || '';
                        const tipo = data.entidad_tipo_nombre ? `<span class="badge bg-light text-dark border me-1">${data.entidad_tipo_nombre}</span>` : '';
                        const cod = data.entidad_codigo ? `<small class="text-muted font-monospace">[${data.entidad_codigo}]</small>` : '';
                        return `<div><strong>${nom}</strong> ${cod}</div><div>${tipo}</div>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        const cod = data.obligacion_codigo || '';
                        const desc = data.obligacion_descripcion ? (data.obligacion_descripcion.substring(0, 50) + (data.obligacion_descripcion.length > 50 ? '...' : '')) : '';
                        const art = data.articulo_referencia ? `<span class="badge bg-secondary ms-1">${data.articulo_referencia}</span>` : '';
                        return `<div><strong class="text-primary">${cod}</strong>${art}</div><small class="text-muted">${desc}</small>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        const ncod = data.norma_codigo || '';
                        const ntit = data.norma_titulo ? (data.norma_titulo.substring(0, 40) + (data.norma_titulo.length > 40 ? '...' : '')) : '';
                        const legalBadge = data.es_legal == 1 
                            ? '<span class="badge bg-danger ms-1">Legal</span>' 
                            : '<span class="badge bg-primary ms-1">Corporativa</span>';
                        return `<div><strong>${ncod}</strong>${legalBadge}</div><small class="text-muted">${ntit}</small>`;
                    }
                },
                {
                    data: null,
                    className: 'text-center align-middle',
                    render: function (data) {
                        const peri = data.periodicidad_nombre || data.periodicidad_codigo || '-';
                        const crit = data.criticidad_nombre || 'Estándar';
                        let badgeClass = 'bg-secondary';
                        if (data.criticidad_peso >= 10) badgeClass = 'bg-danger';
                        else if (data.criticidad_peso >= 5) badgeClass = 'bg-warning text-dark';
                        else if (data.criticidad_peso >= 2) badgeClass = 'bg-info text-dark';
                        return `<div><span class="badge bg-light text-dark border">${peri}</span></div><div class="mt-1"><span class="badge ${badgeClass}">${crit}</span></div>`;
                    }
                },
                {
                    data: null,
                    className: 'text-center align-middle',
                    render: function (data) {
                        const total = parseInt(data.total_origenes || 1);
                        if (total > 1) {
                            return `<button type="button" class="btn btn-xs btn-outline-warning btn-ver-origenes" data-id="${data.obligacion_asignada_id}" data-codigo="${data.obligacion_codigo}" data-entidad="${data.entidad_nombre}" title="Ver orígenes consolidados">
                                        <i class="fas fa-code-branch me-1"></i>${total} orígenes
                                    </button>`;
                        }
                        return `<span class="badge bg-light text-muted border">Directa (1)</span>`;
                    }
                },
                {
                    data: null,
                    className: 'text-center align-middle',
                    render: function (data) {
                        if (data.excepcion_activa_id) {
                            return `<span class="badge bg-warning text-dark" title="Excepción temporal vigente"><i class="fas fa-exclamation-triangle me-1"></i>Exenta</span>`;
                        }
                        return `<span class="text-muted small">No</span>`;
                    }
                },
                {
                    data: null,
                    className: 'text-center align-middle',
                    render: function (data) {
                        const desde = data.vigente_desde || '';
                        const hasta = data.vigente_hasta ? data.vigente_hasta : 'Indefinida';
                        return `<span class="small font-monospace">${desde} al ${hasta}</span>`;
                    }
                },
                {
                    data: 'tabla_estado_registro_id',
                    className: 'text-center align-middle',
                    render: function (data) {
                        if (data == 1) {
                            return '<span class="badge bg-success">Activa</span>';
                        }
                        return '<span class="badge bg-secondary">Inactiva</span>';
                    }
                },
                {
                    data: null,
                    className: 'text-center align-middle',
                    orderable: false,
                    render: function (data) {
                        const canDeact = permisosUsuario.includes('desactivar_obligacion_asignada');

                        let btns = '<div class="btn-group btn-group-sm" role="group">';
                        btns += `<button type="button" class="btn btn-outline-info btn-ver-origenes" data-id="${data.obligacion_asignada_id}" data-codigo="${data.obligacion_codigo}" data-entidad="${data.entidad_nombre}" title="Trazabilidad de orígenes">
                                    <i class="fas fa-code-branch"></i>
                                 </button>`;

                        btns += `<a href="excepciones.php?asignada_id=${data.obligacion_asignada_id}" class="btn btn-outline-warning" title="Solicitar Excepción">
                                    <i class="fas fa-shield-alt"></i>
                                 </a>`;

                        if (canDeact && data.tabla_estado_registro_id == 1) {
                            btns += `<button type="button" class="btn btn-outline-danger btn-desactivar" data-id="${data.obligacion_asignada_id}" title="Desactivar Asignación">
                                        <i class="fas fa-power-off"></i>
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

    // 4. Modal Trazabilidad de Orígenes
    function abrirModalOrigenes(asigId, codigo, entidadNombre) {
        document.getElementById('orig_prevaleciente_info').textContent = `Exigencia Prevaleciente: ${codigo}`;
        document.getElementById('orig_entidad_info').textContent = `Entidad: ${entidadNombre}`;

        const tbody = document.getElementById('tbodyOrigenesDetalle');
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-2 text-muted">Cargando orígenes...</td></tr>';

        const modal = new bootstrap.Modal(document.getElementById('modalOrigenes'));
        modal.show();

        fetch(`obligaciones_asignadas_ajax.php?accion=origenes&obligacion_asignada_id=${asigId}&empresa_idx=${empresaIdx}&pagina_idx=${PAGINA_ID}`)
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    renderizarOrigenes(res.data || []);
                } else {
                    tbody.innerHTML = `<tr><td colspan="6" class="text-center py-2 text-danger">${res.message || "Error al cargar"}</td></tr>`;
                }
            })
            .catch(err => {
                console.error("Error al cargar orígenes:", err);
                tbody.innerHTML = `<tr><td colspan="6" class="text-center py-2 text-danger">Error de comunicación.</td></tr>`;
            });
    }

    function renderizarOrigenes(origenes) {
        const tbody = document.getElementById('tbodyOrigenesDetalle');
        tbody.innerHTML = '';

        if (origenes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-2 text-muted">Sin orígenes registrados.</td></tr>';
            return;
        }

        origenes.forEach(o => {
            const tr = document.createElement('tr');
            const legalBadge = o.es_legal == 1 
                ? '<span class="badge bg-danger">Legal</span>' 
                : '<span class="badge bg-primary">Corporativa</span>';

            tr.innerHTML = `
                <td><strong>${o.norma_codigo}</strong><div class="small text-muted">${o.norma_titulo.substring(0, 35)}...</div></td>
                <td>${legalBadge}</td>
                <td><strong>${o.obligacion_codigo}</strong></td>
                <td><small>${o.obligacion_descripcion.substring(0, 55)}...</small></td>
                <td class="text-center"><span class="badge bg-secondary">${o.criticidad_nombre} (p${o.criticidad_peso})</span></td>
                <td class="text-center"><span class="badge bg-light text-dark border">${o.periodicidad_nombre}</span></td>
            `;
            tbody.appendChild(tr);
        });
    }

    // 5. Desactivar Asignación
    function desactivarAsignacion(id) {
        if (!confirm('¿Confirma que desea desactivar esta asignación de obligación? La entidad dejará de recibir nuevos ciclos.')) {
            return;
        }

        const formData = new FormData();
        formData.append('accion', 'desactivar');
        formData.append('obligacion_asignada_id', id);
        formData.append('empresa_idx', empresaIdx);
        formData.append('pagina_idx', PAGINA_ID);

        fetch('obligaciones_asignadas_ajax.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    tabla.ajax.reload(null, false);
                } else {
                    alert(res.message || "Error al desactivar");
                }
            })
            .catch(err => {
                console.error("Error al desactivar:", err);
                alert("Error de comunicación.");
            });
    }

    // Listeners en la tabla
    $('#tablaObligacionesAsignadas tbody').on('click', '.btn-ver-origenes', function () {
        const id = $(this).data('id');
        const codigo = $(this).data('codigo');
        const entidad = $(this).data('entidad');
        abrirModalOrigenes(id, codigo, entidad);
    });

    $('#tablaObligacionesAsignadas tbody').on('click', '.btn-desactivar', function () {
        const id = $(this).data('id');
        desactivarAsignacion(id);
    });

    // Filtros
    document.getElementById('filtroEntidad').addEventListener('change', () => tabla.ajax.reload());
    document.getElementById('filtroNorma').addEventListener('change', () => tabla.ajax.reload());
    document.getElementById('filtroEstado').addEventListener('change', () => tabla.ajax.reload());
    document.getElementById('btnRecargar').addEventListener('click', () => tabla.ajax.reload());

    // Arranque
    cargarPermisos();
    cargarCombos().then(() => {
        initDataTable();
    });
});
