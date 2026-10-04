/**
 * JavaScript para la Consola del Motor de Herencia y Recálculos (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/recalculos_motor.js
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
        fetch(`recalculos_motor_ajax.php?accion=permisos&empresa_idx=${empresaIdx}&pagina_idx=${PAGINA_ID}`)
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    permisosUsuario = res.funciones || [];
                    document.getElementById('btnEjecutarMotor').disabled = !permisosUsuario.includes('ejecutar_recalculo_motor');
                    document.getElementById('btnSimularImpacto').disabled = !permisosUsuario.includes('simular_impacto_motor');
                    document.getElementById('btnProcesarCola').disabled = !permisosUsuario.includes('procesar_cola_recalculos');
                }
            })
            .catch(err => console.error("Error al cargar permisos:", err));
    }

    // 2. Cargar Estadísticas
    function cargarEstadisticas() {
        fetch(`recalculos_motor_ajax.php?accion=estadisticas&empresa_idx=${empresaIdx}&pagina_idx=${PAGINA_ID}`)
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    const d = res.data;
                    document.getElementById('statEntidades').textContent = d.total_entidades;
                    document.getElementById('statAsignadas').textContent = d.total_asignadas;
                    document.getElementById('statSolapamientos').textContent = d.total_solapamientos;
                    document.getElementById('statExcepciones').textContent = d.total_excepciones;
                    document.getElementById('statPendientes').textContent = d.total_pendientes;
                }
            })
            .catch(err => console.error("Error al cargar estadísticas:", err));
    }

    // 3. Cargar Combos
    function cargarCombos() {
        return fetch(`recalculos_motor_ajax.php?accion=combos&empresa_idx=${empresaIdx}&pagina_idx=${PAGINA_ID}`)
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    const sel = document.getElementById('selectAlcanceEntidad');
                    sel.innerHTML = '<option value="0">-- Toda la Empresa (Todas las Entidades) --</option>';
                    (res.data.entidades || []).forEach(e => {
                        const opt = document.createElement('option');
                        opt.value = e.entidad_id;
                        opt.textContent = `${e.nombre} (${e.tipo_nombre || e.codigo})`;
                        sel.appendChild(opt);
                    });
                }
            })
            .catch(err => console.error("Error al cargar combos:", err));
    }

    // 4. Inicializar DataTable de Cola
    function initDataTable() {
        tabla = $('#tablaColaRecalculos').DataTable({
            responsive: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            order: [[0, 'desc']],
            dom: '<"row mb-2"<"col-12 col-md-6"><"col-12 col-md-6 text-end"f>>rt<"row mt-3 align-items-center"<"col-12 col-md-6 d-flex flex-wrap align-items-center gap-3"li><"col-12 col-md-6 d-flex justify-content-md-end justify-content-center"p>>',
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            ajax: {
                url: `recalculos_motor_ajax.php`,
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar_cola';
                    d.empresa_idx = empresaIdx;
                    d.pagina_idx = PAGINA_ID;
                },
                dataSrc: function (json) {
                    if (!json.success) return [];
                    return json.data || [];
                }
            },
            columns: [
                {
                    data: 'recalculo_pendiente_id',
                    className: 'text-center align-middle',
                    render: function (data) {
                        return `<span class="badge bg-secondary font-monospace">#${data}</span>`;
                    }
                },
                {
                    data: 'causa',
                    render: function (data) {
                        return `<span class="badge bg-light text-dark border font-monospace">${data}</span>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        return `<div><strong>${data.objeto_tabla}</strong></div><small class="text-muted font-monospace">ID: ${data.objeto_id}</small>`;
                    }
                },
                {
                    data: 'fecha_efecto',
                    className: 'text-center align-middle',
                    render: function (data) {
                        return `<span class="small font-monospace">${data || '-'}</span>`;
                    }
                },
                {
                    data: 'creado_en',
                    render: function (data) {
                        return `<span class="small text-muted font-monospace">${data || '-'}</span>`;
                    }
                },
                {
                    data: 'procesado_en',
                    render: function (data) {
                        return `<span class="small text-muted font-monospace">${data || '-'}</span>`;
                    }
                },
                {
                    data: null,
                    className: 'text-center align-middle',
                    render: function (data) {
                        const estId = parseInt(data.tabla_estado_registro_id);
                        const estNom = data.estado_nombre || 'Estado';
                        if (estId === 2092) return `<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>${estNom}</span>`;
                        if (estId === 2093) return `<span class="badge bg-success"><i class="fas fa-check me-1"></i>${estNom}</span>`;
                        if (estId === 2094) return `<span class="badge bg-danger"><i class="fas fa-exclamation me-1"></i>${estNom}</span>`;
                        return `<span class="badge bg-secondary">${estNom}</span>`;
                    }
                }
            ],
            drawCallback: function () {
                initTooltips();
            }
        });
    }

    // 5. Simular Impacto (Caso C35: Vista Previa en Memoria)
    document.getElementById('btnSimularImpacto').addEventListener('click', function () {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Simulando...';

        fetch(`recalculos_motor_ajax.php?accion=simular&empresa_idx=${empresaIdx}&pagina_idx=${PAGINA_ID}`)
            .then(res => res.json())
            .then(res => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-search-plus me-1"></i>Simular Impacto (C35)';

                if (res.success) {
                    renderizarSimulacion(res);
                } else {
                    alert(res.message || "Error al realizar la simulación.");
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-search-plus me-1"></i>Simular Impacto (C35)';
                console.error("Error en simulación:", err);
                alert("Error de comunicación.");
            });
    });

    function renderizarSimulacion(res) {
        const panel = document.getElementById('panelSimulacion');
        const tbody = document.getElementById('tbodySimulacion');
        tbody.innerHTML = '';

        panel.classList.remove('d-none');

        const detalles = res.detalles || [];
        if (detalles.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-2 text-muted">No se detectaron entidades activas para evaluar.</td></tr>';
            return;
        }

        detalles.forEach(d => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong>${d.nombre}</strong></td>
                <td><span class="badge bg-light text-dark border">${d.tipo || '-'}</span></td>
                <td class="text-center"><span class="badge bg-primary">${d.normas_alcanzadas} normas</span></td>
                <td class="text-center"><span class="badge bg-success">${d.obligaciones_asignadas} exigencias</span></td>
                <td class="text-center"><span class="badge bg-info text-dark">${d.solapamientos_resueltos} solapamientos</span></td>
            `;
            tbody.appendChild(tr);
        });
    }

    document.getElementById('btnCerrarSimulacion').addEventListener('click', function () {
        document.getElementById('panelSimulacion').classList.add('d-none');
    });

    // 6. Ejecutar Motor en Firme
    document.getElementById('btnEjecutarMotor').addEventListener('click', function () {
        const selVal = document.getElementById('selectAlcanceEntidad').value;
        const selText = document.getElementById('selectAlcanceEntidad').options[document.getElementById('selectAlcanceEntidad').selectedIndex].text;

        if (!confirm(`¿Confirma la ejecución del Motor de Herencia para: ${selText}?\nSe recalcularán las obligaciones asignadas y se consolidarán los solapamientos normativos.`)) {
            return;
        }

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Calculando...';

        const formData = new FormData();
        formData.append('accion', 'ejecutar');
        formData.append('entidad_id', selVal);
        formData.append('empresa_idx', empresaIdx);
        formData.append('pagina_idx', PAGINA_ID);

        fetch('recalculos_motor_ajax.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(res => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i>Ejecutar Motor Ahora';

                if (res.success) {
                    alert(`¡Cálculo finalizado con éxito!\nTotal obligaciones asignadas generadas/consolidadas: ${res.total_asignadas}`);
                    cargarEstadisticas();
                    tabla.ajax.reload(null, false);
                } else {
                    alert(res.message || "Error al ejecutar el motor.");
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i>Ejecutar Motor Ahora';
                console.error("Error al ejecutar:", err);
                alert("Error de comunicación.");
            });
    });

    // 7. Procesar Cola de Recálculos
    document.getElementById('btnProcesarCola').addEventListener('click', function () {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Procesando...';

        const formData = new FormData();
        formData.append('accion', 'procesar_cola');
        formData.append('empresa_idx', empresaIdx);
        formData.append('pagina_idx', PAGINA_ID);

        fetch('recalculos_motor_ajax.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(res => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-tasks me-1"></i>Procesar Cola';

                if (res.success) {
                    alert(`Lote de cola procesado:\nEvaluados: ${res.total_evaluados}\nExitosos: ${res.procesados}\nErrores: ${res.errores}`);
                    cargarEstadisticas();
                    tabla.ajax.reload(null, false);
                } else {
                    alert(res.message || "Error al procesar la cola.");
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-tasks me-1"></i>Procesar Cola';
                console.error("Error:", err);
                alert("Error de comunicación.");
            });
    });

    document.getElementById('btnRecargarCola').addEventListener('click', () => {
        cargarEstadisticas();
        tabla.ajax.reload();
    });

    // Arranque
    cargarPermisos();
    cargarEstadisticas();
    cargarCombos().then(() => {
        initDataTable();
    });
});
