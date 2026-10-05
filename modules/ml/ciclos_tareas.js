/**
 * Javascript para Tareas Operativas (Página 343 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/ciclos_tareas.js
 */

document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresaIdx = urlParams.get('empresa_id') || 2;
    const ajaxUrl = 'modules/ml/ciclos_tareas_ajax.php';

    let tablaTareas = null;
    let permisosUsuario = [];

    // Inicializar Tooltips
    function initTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // Inicializar DataTable
    function initDataTable() {
        tablaTareas = $('#tablaTareas').DataTable({
            responsive: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            dom: '<"row mb-2"<"col-12 col-md-6"><"col-12 col-md-6 text-end"f>>rt<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>',
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            ajax: {
                url: ajaxUrl,
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.empresa_idx = empresaIdx;
                    d.filtro_entidad = $('#filtroEntidad').val();
                    d.filtro_usuario = $('#filtroUsuario').val();
                    d.filtro_estado = $('#filtroEstado').val();
                    d.filtro_ciclo = $('#filtroCiclo').val();
                },
                dataSrc: function (json) {
                    if (!json.success) {
                        alert(json.message || 'Error al cargar las tareas operativas');
                        return [];
                    }
                    if (json.permisos) {
                        permisosUsuario = json.permisos;
                    }
                    return json.data || [];
                }
            },
            columns: [
                { data: 'ciclo_tarea_id', className: 'text-muted small fw-bold' },
                {
                    data: null,
                    render: function (data) {
                        return `<span class="fw-semibold">${data.entidad_nombre}</span><br><small class="text-muted">${data.entidad_tipo_nombre}</small>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        return `<div>
                                    <span class="badge bg-secondary me-1">${data.obligacion_codigo}</span>
                                    <span class="small fw-semibold">${data.obligacion_titulo}</span>
                                </div>
                                <div class="text-muted small">Ciclo #${data.ciclo_id} (${data.ciclo_periodo_desde_fmt} - ${data.ciclo_periodo_hasta_fmt})</div>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        const platTag = data.plantilla_codigo ? `<span class="badge bg-light text-dark border me-1">${data.plantilla_codigo}</span>` : '';
                        return `<div>${platTag}<span class="fw-bold">${data.titulo}</span></div>
                                <div class="text-muted small text-truncate" style="max-width: 250px;">${data.descripcion || ''}</div>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        return data.responsable_nombre ? `<i class="fas fa-user text-muted me-1"></i><span class="small">${data.responsable_nombre}</span>` : '<span class="text-muted small fst-italic">Sin asignar</span>';
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        return `<span class="small fw-semibold text-danger">${data.fecha_vencimiento_fmt || '--'}</span>`;
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    render: function (data) {
                        const rev = data.requiere_revision == 1 ? '<i class="fas fa-check-circle text-warning me-1" title="Requiere Revisión"></i>' : '';
                        const evi = data.requiere_evidencia == 1 ? '<i class="fas fa-paperclip text-info" title="Requiere Evidencia"></i>' : '';
                        return `${rev} ${evi}` || '<span class="text-muted small">-</span>';
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        let colorBadge = 'secondary';
                        switch (parseInt(data.estado_id, 10)) {
                            case 2077: colorBadge = 'secondary'; break; // Inactiva
                            case 2078: colorBadge = 'info text-dark'; break; // Pendiente
                            case 2079: colorBadge = 'primary'; break; // En curso
                            case 2080: colorBadge = 'warning text-dark'; break; // En revisión
                            case 2081: colorBadge = 'success'; break; // Cumplida
                            case 2082: colorBadge = 'danger'; break; // Rechazada
                            case 2083: colorBadge = 'dark'; break; // Cancelada
                        }
                        return `<span class="badge bg-${colorBadge}">${data.estado_nombre}</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function (data) {
                        const id = data.ciclo_tarea_id;
                        const estadoId = parseInt(data.estado_id, 10);

                        let btnDetalle = `<button class="btn btn-outline-info btn-ver-tarea" data-id="${id}" data-bs-toggle="tooltip" title="Ver Detalle / Instructivo"><i class="fas fa-eye"></i></button>`;

                        let btnIniciar = '';
                        let btnCumplir = '';
                        let btnRechazar = '';

                        // Transiciones según estado
                        if (estadoId === 2078 || estadoId === 2082) { // Pendiente o Rechazada
                            btnIniciar = `<button class="btn btn-outline-primary btn-trans" data-id="${id}" data-estado="2079" data-accion="iniciar" data-bs-toggle="tooltip" title="Iniciar Tarea"><i class="fas fa-play"></i></button>`;
                        }

                        if (estadoId === 2079) { // En curso -> Cumplir o Enviar a revisión
                            const icon = data.requiere_revision == 1 ? 'fa-paper-plane' : 'fa-check';
                            const title = data.requiere_revision == 1 ? 'Enviar a Revisión' : 'Marcar Cumplida';
                            btnCumplir = `<button class="btn btn-outline-success btn-trans" data-id="${id}" data-estado="2081" data-accion="cumplir" data-bs-toggle="tooltip" title="${title}"><i class="fas ${icon}"></i></button>`;
                        }

                        if (estadoId === 2080) { // En revisión -> Aprobar (2081) o Rechazar (2082)
                            btnCumplir = `<button class="btn btn-outline-success btn-trans" data-id="${id}" data-estado="2081" data-accion="aprobar" data-bs-toggle="tooltip" title="Aprobar Revisión"><i class="fas fa-check-double"></i></button>`;
                            btnRechazar = `<button class="btn btn-outline-danger btn-trans" data-id="${id}" data-estado="2082" data-accion="rechazar" data-bs-toggle="tooltip" title="Rechazar / Observar"><i class="fas fa-times-circle"></i></button>`;
                        }

                        return `<div class="btn-group btn-group-sm" role="group">
                                    ${btnDetalle}
                                    ${btnIniciar}
                                    ${btnCumplir}
                                    ${btnRechazar}
                                </div>`;
                    }
                }
            ],
            drawCallback: function () {
                initTooltips();
            }
        });
    }

    // Cargar combos iniciales
    function cargarCombos() {
        $.ajax({
            url: ajaxUrl,
            type: 'GET',
            data: { accion: 'combos', empresa_idx: empresaIdx },
            dataType: 'json',
            success: function (res) {
                if (!res.success) return;

                // Entidades
                const $ent = $('#filtroEntidad');
                $ent.empty().append('<option value="">-- Todas las entidades --</option>');
                res.entidades.forEach(e => {
                    $ent.append(`<option value="${e.entidad_id}">${e.nombre} (${e.codigo})</option>`);
                });

                // Responsables
                const $usr = $('#filtroUsuario');
                $usr.empty().append('<option value="">-- Todos los responsables --</option>');
                res.usuarios.forEach(u => {
                    $usr.append(`<option value="${u.usuario_id}">${u.nombre_completo}</option>`);
                });

                // Estados
                const $est = $('#filtroEstado');
                $est.empty().append('<option value="">-- Todos los estados --</option>');
                res.estados.forEach(st => {
                    $est.append(`<option value="${st.tabla_estado_registro_id}">${st.descripcion}</option>`);
                });
            }
        });
    }

    // Eventos filtros
    $('#filtroEntidad, #filtroUsuario, #filtroEstado').on('change', function () {
        tablaTareas.ajax.reload();
    });

    $('#filtroCiclo').on('keyup change', function () {
        tablaTareas.ajax.reload();
    });

    $('#btnRecargar').on('click', function () {
        tablaTareas.ajax.reload();
    });

    // Ver Detalle Tarea e Instructivo (Caso C14)
    $('#tablaTareas').on('click', '.btn-ver-tarea', function () {
        const id = $(this).data('id');
        $.ajax({
            url: ajaxUrl,
            type: 'GET',
            data: { accion: 'obtener', ciclo_tarea_id: id, empresa_idx: empresaIdx },
            dataType: 'json',
            success: function (res) {
                if (!res.success) {
                    alert(res.message);
                    return;
                }
                const t = res.tarea;
                $('#dtTareaTitulo').text(t.titulo);
                $('#dtCicloEntidad').text(`Ciclo #${t.ciclo_id} (${t.periodo_desde_fmt} al ${t.periodo_hasta_fmt}) - ${t.entidad_nombre}`);
                $('#dtObligacion').text(`${t.obligacion_codigo} - ${t.obligacion_titulo}`);
                $('#dtVencimiento').text(t.fecha_vencimiento_fmt || '--');
                $('#dtEstadoBadge').html(`<span class="badge bg-secondary">${t.estado_nombre}</span>`);
                $('#dtFechaCierre').text(t.fecha_cierre_fmt || '--');

                // Predecesoras
                const $ulP = $('#ulPredecesoras');
                $ulP.empty();
                if (!t.predecesoras || t.predecesoras.length === 0) {
                    $ulP.append('<li class="list-group-item text-muted">Sin dependencias predecesoras</li>');
                } else {
                    t.predecesoras.forEach(p => {
                        const ob = p.obligatoria == 1 ? '<span class="badge bg-danger ms-1">Obligatoria</span>' : '<span class="badge bg-secondary ms-1">Opcional</span>';
                        $ulP.append(`<li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-arrow-right text-muted me-1"></i>${p.predecesora_titulo}</span>
                            <span><span class="badge bg-light text-dark border">${p.predecesora_estado}</span> ${ob}</span>
                        </li>`);
                    });
                }

                // RACI
                const $ulR = $('#ulRaci');
                $ulR.empty();
                if (!t.raci || t.raci.length === 0) {
                    $ulR.append('<li class="list-group-item text-muted">Sin asignaciones registradas</li>');
                } else {
                    t.raci.forEach(r => {
                        $ulR.append(`<li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>${r.raci_rol_nombre}:</strong> ${r.nombre_completo}</span>
                            <small class="text-muted">${r.email || ''}</small>
                        </li>`);
                    });
                }

                // Instructivo
                const $divIns = $('#divInstructivoContenido');
                if (t.instructivo_contenido) {
                    $divIns.html(`<div class="p-2 border rounded bg-white"><pre style="white-space: pre-wrap; font-family: inherit;">${t.instructivo_contenido}</pre></div>`);
                } else {
                    $divIns.html('<span class="text-muted small fst-italic">Sin instructivo operativo formal asociado.</span>');
                }

                $('#modalDetalleTarea').modal('show');
            }
        });
    });

    // Abrir Modal de Transición de Tarea
    $('#tablaTareas').on('click', '.btn-trans', function () {
        const id = $(this).data('id');
        const estado = $(this).data('estado');
        const accionTipo = $(this).data('accion');

        $('#transTareaId').val(id);
        $('#transNuevoEstado').val(estado);
        $('#transObservaciones').val('');

        let headerClass = 'bg-primary text-white';
        let titulo = 'Transición de Tarea';
        let mensaje = '';
        let btnClass = 'btn-primary';
        let btnTexto = 'Confirmar';

        switch (accionTipo) {
            case 'iniciar':
                headerClass = 'bg-primary text-white';
                titulo = 'Iniciar Tarea Operativa';
                mensaje = 'La tarea cambiará a estado <strong>En curso</strong>.';
                btnClass = 'btn-primary';
                btnTexto = 'Iniciar';
                break;
            case 'cumplir':
                headerClass = 'bg-success text-white';
                titulo = 'Completar Tarea Operativa';
                mensaje = 'Si la tarea requiere revisión, pasará a <strong>En revisión</strong>. Caso contrario, se marcará <strong>Cumplida</strong> activando dependencias sucesoras.';
                btnClass = 'btn-success';
                btnTexto = 'Completar';
                break;
            case 'aprobar':
                headerClass = 'bg-success text-white';
                titulo = 'Aprobar Tarea Operativa';
                mensaje = 'Se confirmará la aprobación técnica. La tarea pasará a <strong>Cumplida</strong> activando sucesoras en cascada.';
                btnClass = 'btn-success';
                btnTexto = 'Aprobar';
                break;
            case 'rechazar':
                headerClass = 'bg-danger text-white';
                titulo = 'Rechazar / Observar Tarea';
                mensaje = 'La tarea se registrará como <strong>Rechazada</strong> y requerirá corrección.';
                btnClass = 'btn-danger';
                btnTexto = 'Rechazar';
                break;
        }

        $('#headerModalTareaAccion').removeClass().addClass(`modal-header py-2 ${headerClass}`);
        $('#tituloModalTareaAccion').text(titulo);
        $('#alertaTextoTareaAccion').removeClass().addClass(`alert alert-secondary py-2`).html(mensaje);
        $('#btnConfirmarTrans').removeClass().addClass(`btn btn-sm ${btnClass}`).text(btnTexto);

        $('#modalCambiarEstado').modal('show');
    });

    // Confirmar Transición
    $('#formCambiarEstado').on('submit', function (e) {
        e.preventDefault();
        const data = $(this).serialize() + '&accion=cambiar_estado&empresa_idx=' + empresaIdx;
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    $('#modalCambiarEstado').modal('hide');
                    alert(res.message);
                    tablaTareas.ajax.reload();
                } else {
                    alert('Error: ' + res.message);
                }
            }
        });
    });

    // Iniciar
    cargarCombos();
    initDataTable();
});
