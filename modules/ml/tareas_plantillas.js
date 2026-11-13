/**
 * JavaScript para Plantillas de Tareas y Dependencias (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/tareas_plantillas.js
 */

document.addEventListener('DOMContentLoaded', function () {
    let tabla = null;
    let permisosUsuario = [];
    let comboCache = {};

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
        fetch(`tareas_plantillas_ajax.php?accion=permisos&empresa_idx=${empresaIdx}&pagina_idx=${PAGINA_ID}`)
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    permisosUsuario = res.funciones || [];
                    renderizarBotonNuevo();
                }
            })
            .catch(err => console.error("Error al cargar permisos:", err));
    }

    function renderizarBotonNuevo() {
        const container = document.getElementById('btn-container-plantilla');
        if (!container) return;
        container.innerHTML = '';

        if (permisosUsuario.includes('guardar_tarea_plantilla')) {
            const btn = document.createElement('button');
            btn.className = 'btn btn-sm btn-primary';
            btn.innerHTML = '<i class="fas fa-plus me-1"></i>Nueva Plantilla';
            btn.addEventListener('click', () => abrirModalPlantilla(0));
            container.appendChild(btn);
        }
    }

    // 2. Cargar Combos
    function cargarCombos() {
        return fetch(`tareas_plantillas_ajax.php?accion=combos&empresa_idx=${empresaIdx}&pagina_idx=${PAGINA_ID}`)
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    comboCache = res.data;

                    // Poblar combos de Obligaciones
                    const selFiltro = document.getElementById('filtroObligacion');
                    const selModal = document.getElementById('plantilla_obligacion_id');
                    
                    selFiltro.innerHTML = '<option value="">-- Todas las obligaciones --</option>';
                    selModal.innerHTML = '<option value="">-- Seleccionar obligación --</option>';

                    (res.data.obligaciones || []).forEach(o => {
                        const optF = document.createElement('option');
                        optF.value = o.obligacion_id;
                        optF.textContent = `[${o.norma_codigo}] ${o.codigo} - ${o.descripcion.substring(0, 50)}...`;
                        selFiltro.appendChild(optF);

                        const optM = document.createElement('option');
                        optM.value = o.obligacion_id;
                        optM.textContent = `[${o.norma_codigo}] ${o.codigo} - ${o.descripcion.substring(0, 60)}`;
                        selModal.appendChild(optM);
                    });

                    // Poblar Procedimientos
                    const selPrc = document.getElementById('plantilla_procedimiento_id');
                    selPrc.innerHTML = '<option value="">-- Sin procedimiento asignado --</option>';
                    (res.data.procedimientos || []).forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = p.procedimiento_id;
                        opt.textContent = `${p.codigo} - ${p.nombre}`;
                        selPrc.appendChild(opt);
                    });

                    // Poblar Estados Activadores
                    const selEst = document.getElementById('dep_estado_activador_id');
                    selEst.innerHTML = '';
                    (res.data.estados_activadores || []).forEach(e => {
                        const opt = document.createElement('option');
                        opt.value = e.tabla_estado_registro_id;
                        opt.textContent = e.tabla_estado_registro;
                        if (e.tabla_estado_registro_id == 2081) {
                            opt.selected = true; // Por defecto: Cumplida
                        }
                        selEst.appendChild(opt);
                    });

                    // Poblar Posiciones / Cargos para modal RACI
                    const selPosRaci = document.getElementById('tpl_raci_posicion_id');
                    if (selPosRaci) {
                        selPosRaci.innerHTML = '<option value="">-- Seleccionar cargo --</option>';
                        (res.data.posiciones || []).forEach(p => {
                            const opt = document.createElement('option');
                            opt.value = p.posicion_id;
                            const supTxt = p.superior_nombre ? ` (Sup: ${p.superior_nombre})` : '';
                            opt.textContent = `${p.nombre} [${p.codigo}] - ${p.area_departamento}${supTxt}`;
                            selPosRaci.appendChild(opt);
                        });
                    }
                }
            })
            .catch(err => console.error("Error al cargar combos:", err));
    }

    // 3. Inicializar DataTable
    function initDataTable() {
        tabla = $('#tablaTareasPlantillas').DataTable({
            responsive: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            order: [[0, 'asc']],
            dom: '<"row mb-2"<"col-12 col-md-6"><"col-12 col-md-6 text-end"f>>rt<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>',
            language: {
                url: window.APP_ASSETS_URL + 'js/datatables-i18n-1.13.7/es-ES.json'
            },
            ajax: {
                url: `tareas_plantillas_ajax.php`,
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.empresa_idx = empresaIdx;
                    d.pagina_idx = PAGINA_ID;
                    d.obligacion_id = document.getElementById('filtroObligacion').value;
                    d.filtro_estado = document.getElementById('filtroEstado').value;
                },
                dataSrc: function (json) {
                    if (!json.success) {
                        alert(json.message || "Error al cargar tareas");
                        return [];
                    }
                    return json.data || [];
                }
            },
            columns: [
                {
                    data: 'orden',
                    className: 'text-center align-middle',
                    render: function (data) {
                        return `<span class="badge bg-secondary rounded-pill px-2 py-1">#${data}</span>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        const obl = data.obligacion_codigo || '';
                        const desc = data.obligacion_descripcion ? (data.obligacion_descripcion.substring(0, 45) + (data.obligacion_descripcion.length > 45 ? '...' : '')) : '';
                        const nor = data.norma_codigo ? `<span class="badge bg-light text-dark border me-1">${data.norma_codigo}</span>` : '';
                        return `<div>${nor}<strong class="text-primary">${obl}</strong></div><small class="text-muted">${desc}</small>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        const tit = data.titulo || '';
                        const desc = data.descripcion ? `<div class="small text-muted mt-1">${data.descripcion.substring(0, 70)}${data.descripcion.length > 70 ? '...' : ''}</div>` : '';
                        return `<div><strong>${tit}</strong>${desc}</div>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        if (data.procedimiento_id && data.procedimiento_codigo) {
                            return `<span class="badge bg-info text-dark" title="${data.procedimiento_nombre || ''}"><i class="fas fa-file-alt me-1"></i>${data.procedimiento_codigo}</span>`;
                        }
                        return `<span class="text-muted small"><em>Sin asignar</em></span>`;
                    }
                },
                {
                    data: null,
                    className: 'text-center align-middle',
                    render: function (data) {
                        const inicio = data.offset_dias_inicio !== null ? `${data.offset_dias_inicio > 0 ? '+' : ''}${data.offset_dias_inicio}d` : '-';
                        const venc = data.offset_dias_vencimiento !== null ? `${data.offset_dias_vencimiento > 0 ? '+' : ''}${data.offset_dias_vencimiento}d` : '-';
                        return `<span class="badge bg-light text-dark border font-monospace">Ini: ${inicio} | Venc: ${venc}</span>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        const total = parseInt(data.total_predecesoras || 0);
                        if (total === 0) {
                            return `<span class="badge bg-light text-muted border">Sin dependencias</span>`;
                        }
                        const nombres = data.predecesoras_nombres || '';
                        return `<span class="badge bg-warning text-dark border" title="${nombres}"><i class="fas fa-link me-1"></i>${total} previa(s)</span>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        if (data.roles_raci_resumen) {
                            return `<span class="badge bg-light text-primary border" title="${data.roles_raci_resumen}"><i class="fas fa-users-cog me-1"></i>${data.roles_raci_resumen}</span>`;
                        }
                        return `<span class="text-muted small"><em>Sin roles predefinidos</em></span>`;
                    }
                },
                {
                    data: 'tabla_estado_registro_id',
                    className: 'text-center align-middle',
                    render: function (data) {
                        if (data == 1) {
                            return '<span class="badge bg-success">Activo</span>';
                        }
                        return '<span class="badge bg-secondary">Inactivo</span>';
                    }
                },
                {
                    data: null,
                    className: 'text-center align-middle',
                    orderable: false,
                    render: function (data) {
                        const canEdit = permisosUsuario.includes('guardar_tarea_plantilla');
                        const canDep = permisosUsuario.includes('vincular_dependencia_tarea');

                        let btns = '<div class="btn-group btn-group-sm" role="group">';
                        
                        if (canEdit) {
                            btns += `<button type="button" class="btn btn-outline-primary btn-editar" data-id="${data.tarea_plantilla_id}" title="Editar Plantilla">
                                        <i class="fas fa-edit"></i>
                                     </button>`;
                        }

                        // Botón de Matriz RACI por Cargos
                        btns += `<button type="button" class="btn btn-outline-success btn-raci" data-id="${data.tarea_plantilla_id}" data-titulo="${data.titulo}" data-orden="${data.orden}" data-oblcodigo="${data.obligacion_codigo}" title="Matriz RACI por Cargos">
                                    <i class="fas fa-users-cog"></i>
                                 </button>`;

                        if (canDep) {
                            btns += `<button type="button" class="btn btn-outline-info btn-dependencias" data-id="${data.tarea_plantilla_id}" data-obligacion="${data.obligacion_id}" data-titulo="${data.titulo}" data-orden="${data.orden}" data-oblcodigo="${data.obligacion_codigo}" title="Secuencia y Dependencias">
                                        <i class="fas fa-project-diagram"></i>
                                     </button>`;
                        }

                        if (canEdit) {
                            btns += `<button type="button" class="btn btn-outline-danger btn-eliminar" data-id="${data.tarea_plantilla_id}" title="Eliminar Plantilla">
                                        <i class="fas fa-trash-alt"></i>
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

    // 4. Modal Plantilla (Crear / Editar)
    function abrirModalPlantilla(id = 0) {
        const form = document.getElementById('formPlantilla');
        form.reset();
        document.getElementById('plantilla_id').value = id;

        // Si hay una obligación filtrada, seleccionarla por defecto
        const filtroObl = document.getElementById('filtroObligacion').value;
        if (filtroObl) {
            document.getElementById('plantilla_obligacion_id').value = filtroObl;
        }

        if (id > 0) {
            document.getElementById('modalPlantillaLabel').innerHTML = '<i class="fas fa-edit me-2"></i>Editar Plantilla de Tarea';
            fetch(`tareas_plantillas_ajax.php?accion=obtener&tarea_plantilla_id=${id}&empresa_idx=${empresaIdx}&pagina_idx=${PAGINA_ID}`)
                .then(res => res.json())
                .then(res => {
                    if (res.success && res.data) {
                        const d = res.data;
                        document.getElementById('plantilla_obligacion_id').value = d.obligacion_id;
                        document.getElementById('plantilla_orden').value = d.orden;
                        document.getElementById('plantilla_titulo').value = d.titulo;
                        document.getElementById('plantilla_descripcion').value = d.descripcion || '';
                        document.getElementById('plantilla_procedimiento_id').value = d.procedimiento_id || '';
                        document.getElementById('plantilla_offset_inicio').value = d.offset_dias_inicio !== null ? d.offset_dias_inicio : '';
                        document.getElementById('plantilla_offset_vencimiento').value = d.offset_dias_vencimiento !== null ? d.offset_dias_vencimiento : '';
                        document.getElementById('plantilla_estado').value = d.tabla_estado_registro_id;

                        const modal = new bootstrap.Modal(document.getElementById('modalPlantilla'));
                        modal.show();
                    } else {
                        alert(res.message || "Error al obtener datos");
                    }
                });
        } else {
            document.getElementById('modalPlantillaLabel').innerHTML = '<i class="fas fa-plus me-2"></i>Nueva Plantilla de Tarea';
            // Auto sugerir orden si hay obligacion seleccionada
            if (filtroObl) {
                sugerirProximoOrden(filtroObl);
            }
            const modal = new bootstrap.Modal(document.getElementById('modalPlantilla'));
            modal.show();
        }
    }

    function sugerirProximoOrden(obligacionId) {
        if (!obligacionId) return;
        const rows = tabla ? tabla.rows().data().toArray() : [];
        let maxOrden = 0;
        rows.forEach(r => {
            if (r.obligacion_id == obligacionId) {
                const ord = parseInt(r.orden || 0);
                if (ord > maxOrden) maxOrden = ord;
            }
        });
        document.getElementById('plantilla_orden').value = maxOrden + 1;
    }

    document.getElementById('plantilla_obligacion_id').addEventListener('change', function () {
        if (document.getElementById('plantilla_id').value == '0') {
            sugerirProximoOrden(this.value);
        }
    });

    // Guardar Plantilla
    document.getElementById('formPlantilla').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('accion', 'guardar');
        formData.append('empresa_idx', empresaIdx);
        formData.append('pagina_idx', PAGINA_ID);

        const btnGuardar = document.getElementById('btnGuardarPlantilla');
        btnGuardar.disabled = true;

        fetch('tareas_plantillas_ajax.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(res => {
                btnGuardar.disabled = false;
                if (res.success) {
                    bootstrap.Modal.getInstance(document.getElementById('modalPlantilla')).hide();
                    tabla.ajax.reload(null, false);
                } else {
                    alert(res.message || "Error al guardar");
                }
            })
            .catch(err => {
                btnGuardar.disabled = false;
                console.error("Error al guardar:", err);
                alert("Ocurrió un error en la comunicación con el servidor.");
            });
    });

    // Eliminar Plantilla
    function eliminarPlantilla(id) {
        if (!confirm('¿Confirma que desea eliminar esta plantilla de tarea? Se eliminarán también sus secuencias asociadas.')) {
            return;
        }

        const formData = new FormData();
        formData.append('accion', 'eliminar');
        formData.append('tarea_plantilla_id', id);
        formData.append('empresa_idx', empresaIdx);
        formData.append('pagina_idx', PAGINA_ID);

        fetch('tareas_plantillas_ajax.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    tabla.ajax.reload(null, false);
                } else {
                    alert(res.message || "Error al eliminar");
                }
            })
            .catch(err => {
                console.error("Error al eliminar:", err);
                alert("Error al comunicarse con el servidor.");
            });
    }

    // 5. Gestión de Dependencias
    function abrirModalDependencias(tareaId, obligacionId, titulo, orden, oblCodigo) {
        document.getElementById('dep_tarea_id').value = tareaId;
        document.getElementById('dep_tarea_info').textContent = `#${orden} ${titulo}`;
        document.getElementById('dep_obligacion_info').textContent = `Obligación: ${oblCodigo}`;

        cargarListaDependencias(tareaId, obligacionId);

        const modal = new bootstrap.Modal(document.getElementById('modalDependencias'));
        modal.show();
    }

    function cargarListaDependencias(tareaId, obligacionId) {
        fetch(`tareas_plantillas_ajax.php?accion=listar_dependencias&tarea_plantilla_id=${tareaId}&obligacion_id=${obligacionId}&empresa_idx=${empresaIdx}&pagina_idx=${PAGINA_ID}`)
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    renderizarTablaDependencias(res.dependencias || []);
                    renderizarCandidatasPredecesoras(res.candidatas || []);
                } else {
                    alert(res.message || "Error al cargar dependencias");
                }
            })
            .catch(err => console.error("Error al cargar dependencias:", err));
    }

    function renderizarTablaDependencias(deps) {
        const tbody = document.getElementById('tbodyDependencias');
        tbody.innerHTML = '';

        if (deps.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-3">Esta tarea no tiene dependencias predecesoras (puede iniciarse de inmediato).</td></tr>`;
            return;
        }

        deps.forEach(d => {
            const tr = document.createElement('tr');
            const obligatoriaBadge = d.obligatoria == 1 
                ? '<span class="badge bg-danger">Obligatoria</span>' 
                : '<span class="badge bg-secondary">Opcional</span>';

            tr.innerHTML = `
                <td class="text-center"><span class="badge bg-secondary">#${d.predecesora_orden}</span></td>
                <td><strong>${d.predecesora_titulo}</strong></td>
                <td class="text-center"><span class="badge bg-success">${d.estado_activador_nombre || 'Cumplida'}</span></td>
                <td class="text-center">${obligatoriaBadge}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 btn-quitar-dep" data-id="${d.tarea_plantilla_dependencia_id}" title="Quitar">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        // Eventos quitar dependencia
        tbody.querySelectorAll('.btn-quitar-dep').forEach(btn => {
            btn.addEventListener('click', function () {
                const depId = this.getAttribute('data-id');
                quitarDependencia(depId);
            });
        });
    }

    function renderizarCandidatasPredecesoras(candidatas) {
        const sel = document.getElementById('dep_predecesora_id');
        sel.innerHTML = '<option value="">-- Seleccionar tarea previa --</option>';

        candidatas.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.tarea_plantilla_id;
            opt.textContent = `#${c.orden} - ${c.titulo}`;
            sel.appendChild(opt);
        });

        const btnAdd = document.getElementById('btnAgregarDep');
        if (candidatas.length === 0) {
            sel.disabled = true;
            btnAdd.disabled = true;
        } else {
            sel.disabled = false;
            btnAdd.disabled = false;
        }
    }

    // Agregar Dependencia
    document.getElementById('formNuevaDependencia').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('accion', 'guardar_dependencia');
        formData.append('empresa_idx', empresaIdx);
        formData.append('pagina_idx', PAGINA_ID);

        const btn = document.getElementById('btnAgregarDep');
        btn.disabled = true;

        fetch('tareas_plantillas_ajax.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(res => {
                btn.disabled = false;
                if (res.success) {
                    const tareaId = document.getElementById('dep_tarea_id').value;
                    const obligacionId = document.getElementById('filtroObligacion').value;
                    cargarListaDependencias(tareaId, obligacionId);
                    tabla.ajax.reload(null, false);
                } else {
                    alert(res.message || "Error al vincular dependencia");
                }
            })
            .catch(err => {
                btn.disabled = false;
                console.error("Error al vincular:", err);
                alert("Error al comunicarse con el servidor.");
            });
    });

    // Quitar Dependencia
    function quitarDependencia(depId) {
        if (!confirm('¿Desea desvincular esta relación de dependencia?')) return;

        const formData = new FormData();
        formData.append('accion', 'eliminar_dependencia');
        formData.append('tarea_plantilla_dependencia_id', depId);
        formData.append('empresa_idx', empresaIdx);
        formData.append('pagina_idx', PAGINA_ID);

        fetch('tareas_plantillas_ajax.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    const tareaId = document.getElementById('dep_tarea_id').value;
                    const obligacionId = document.getElementById('filtroObligacion').value;
                    cargarListaDependencias(tareaId, obligacionId);
                    tabla.ajax.reload(null, false);
                } else {
                    alert(res.message || "Error al eliminar dependencia");
                }
            })
            .catch(err => {
                console.error("Error al eliminar dependencia:", err);
                alert("Error al comunicarse con el servidor.");
            });
    }

    // 5.2. Modal Matriz RACI por Cargos Tipo
    function abrirModalRaci(id, titulo, orden, oblCodigo) {
        document.getElementById('raci_modal_tarea_id').value = id;
        document.getElementById('raci_tarea_info').innerHTML = `<i class="fas fa-tasks text-primary me-2"></i>#${orden} - ${titulo}`;
        document.getElementById('raci_obligacion_info').innerHTML = `Obligación: <strong>${oblCodigo}</strong>`;

        document.getElementById('formNuevoRaci').reset();
        document.getElementById('tpl_raci_rol_id').value = '1';
        document.getElementById('boxTplUsarSup').style.display = 'none';

        cargarListaRaci(id);

        const modal = new bootstrap.Modal(document.getElementById('modalPlantillaRaci'));
        modal.show();
    }

    // Cambiar rol RACI en formulario de plantilla
    $('#tpl_raci_rol_id').on('change', function () {
        const rol = parseInt($(this).val(), 10);
        if (rol === 2) {
            $('#boxTplUsarSup').slideDown(150);
        } else {
            $('#boxTplUsarSup').slideUp(150);
            $('#tpl_usar_sup').prop('checked', false);
        }
    });

    // Cargar Lista de Roles RACI en la Plantilla
    function cargarListaRaci(tareaId) {
        const tbody = document.getElementById('tbodyRolesRaci');
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i>Cargando roles...</td></tr>';

        fetch(`tareas_plantillas_ajax.php?accion=listar_raci&tarea_plantilla_id=${tareaId}&empresa_idx=${empresaIdx}&pagina_idx=${PAGINA_ID}`)
            .then(res => res.json())
            .then(res => {
                tbody.innerHTML = '';
                if (!res.success || !res.raci || res.raci.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No hay roles RACI predefinidos para esta tarea.</td></tr>';
                    return;
                }

                res.raci.forEach(r => {
                    const tr = document.createElement('tr');

                    let badgeColor = 'secondary';
                    switch (parseInt(r.raci_rol_id, 10)) {
                        case 1: badgeColor = 'primary'; break;
                        case 2: badgeColor = 'success'; break;
                        case 3: badgeColor = 'warning text-dark'; break;
                        case 4: badgeColor = 'info text-dark'; break;
                    }

                    const reglaEsp = parseInt(r.usar_supervisor_como_aprobador, 10) === 1
                        ? '<span class="badge bg-primary"><i class="fas fa-sitemap me-1"></i>Supervisor</span>'
                        : '<span class="text-muted small">Titular Cargo</span>';

                    const titular = r.ocupante_titular_nombre 
                        ? `<span class="fw-semibold text-dark">${r.ocupante_titular_nombre}</span>`
                        : '<span class="text-muted small fst-italic">Vacante</span>';

                    tr.innerHTML = `
                        <td class="text-center">
                            <span class="badge bg-${badgeColor} fw-bold">${r.rol_nombre}</span>
                        </td>
                        <td>
                            <strong>${r.posicion_nombre}</strong>
                            <div class="small text-muted">[${r.posicion_codigo}]</div>
                        </td>
                        <td class="small text-muted">${r.posicion_area || ''}</td>
                        <td class="small">${titular}</td>
                        <td class="text-center">${reglaEsp}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-raci" data-id="${r.tarea_plantilla_raci_id}" title="Desvincular Rol">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });

                // Asignar listeners a botones eliminar
                tbody.querySelectorAll('.btn-quitar-raci').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const raciId = this.getAttribute('data-id');
                        quitarRolRaci(raciId);
                    });
                });
            })
            .catch(err => {
                console.error("Error al cargar RACI:", err);
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3">Error al cargar roles RACI.</td></tr>';
            });
    }

    // Guardar Rol RACI en Plantilla
    document.getElementById('formNuevoRaci').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('accion', 'guardar_raci');
        formData.append('empresa_idx', empresaIdx);
        formData.append('pagina_idx', PAGINA_ID);

        const btn = document.getElementById('btnAgregarRaci');
        btn.disabled = true;

        fetch('tareas_plantillas_ajax.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(res => {
                btn.disabled = false;
                if (res.success) {
                    const tareaId = document.getElementById('raci_modal_tarea_id').value;
                    cargarListaRaci(tareaId);
                    tabla.ajax.reload(null, false);
                    document.getElementById('tpl_raci_posicion_id').value = '';
                } else {
                    alert(res.message || "Error al vincular rol RACI");
                }
            })
            .catch(err => {
                btn.disabled = false;
                console.error("Error al vincular RACI:", err);
                alert("Error al comunicarse con el servidor.");
            });
    });

    // Quitar Rol RACI de Plantilla
    function quitarRolRaci(raciId) {
        if (!confirm('¿Desea desvincular este cargo de la matriz RACI de la tarea?')) return;

        const formData = new FormData();
        formData.append('accion', 'eliminar_raci');
        formData.append('tarea_plantilla_raci_id', raciId);
        formData.append('empresa_idx', empresaIdx);
        formData.append('pagina_idx', PAGINA_ID);

        fetch('tareas_plantillas_ajax.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    const tareaId = document.getElementById('raci_modal_tarea_id').value;
                    cargarListaRaci(tareaId);
                    tabla.ajax.reload(null, false);
                } else {
                    alert(res.message || "Error al eliminar rol RACI");
                }
            })
            .catch(err => {
                console.error("Error al eliminar rol RACI:", err);
                alert("Error al comunicarse con el servidor.");
            });
    }

    // 6. Listeners Globales en la Tabla
    $('#tablaTareasPlantillas tbody').on('click', '.btn-editar', function () {
        const id = $(this).data('id');
        abrirModalPlantilla(id);
    });

    $('#tablaTareasPlantillas tbody').on('click', '.btn-raci', function () {
        const id = $(this).data('id');
        const titulo = $(this).data('titulo');
        const orden = $(this).data('orden');
        const oblCodigo = $(this).data('oblcodigo');
        abrirModalRaci(id, titulo, orden, oblCodigo);
    });

    $('#tablaTareasPlantillas tbody').on('click', '.btn-dependencias', function () {
        const id = $(this).data('id');
        const obligacionId = $(this).data('obligacion');
        const titulo = $(this).data('titulo');
        const orden = $(this).data('orden');
        const oblCodigo = $(this).data('oblcodigo');
        abrirModalDependencias(id, obligacionId, titulo, orden, oblCodigo);
    });

    $('#tablaTareasPlantillas tbody').on('click', '.btn-eliminar', function () {
        const id = $(this).data('id');
        eliminarPlantilla(id);
    });

    // Filtros
    document.getElementById('filtroObligacion').addEventListener('change', () => tabla.ajax.reload());
    document.getElementById('filtroEstado').addEventListener('change', () => tabla.ajax.reload());
    document.getElementById('btnRecargar').addEventListener('click', () => tabla.ajax.reload());

    // 7. Arranque Inicial
    cargarPermisos();
    cargarCombos().then(() => {
        initDataTable();
    });
});
