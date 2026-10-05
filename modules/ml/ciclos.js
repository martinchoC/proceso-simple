/**
 * Javascript para Calendario y Ciclos (Página 342 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/ciclos.js
 */

document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresaIdx = urlParams.get('empresa_id') || 2;
    const ajaxUrl = 'modules/ml/ciclos_ajax.php';

    let tablaCiclos = null;
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
        tablaCiclos = $('#tablaCiclos').DataTable({
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
                    d.filtro_obligacion = $('#filtroObligacion').val();
                    d.filtro_estado = $('#filtroEstado').val();
                    d.filtro_vinculo = $('#filtroVinculo').val();
                },
                dataSrc: function (json) {
                    if (!json.success) {
                        alert(json.message || 'Error al cargar los ciclos');
                        return [];
                    }
                    if (json.permisos) {
                        permisosUsuario = json.permisos;
                    }
                    return json.data || [];
                }
            },
            columns: [
                { data: 'ciclo_id', className: 'text-muted small fw-bold' },
                {
                    data: null,
                    render: function (data) {
                        return `<span class="fw-semibold">${data.entidad_nombre}</span><br><small class="text-muted">${data.entidad_tipo_nombre}</small>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        const critColor = data.criticidad_color || '#6c757d';
                        return `<div>
                                    <span class="badge bg-secondary me-1">${data.obligacion_codigo}</span>
                                    <span class="fw-semibold">${data.obligacion_titulo}</span>
                                </div>
                                <div class="mt-1 small">
                                    <span class="badge" style="background-color: ${critColor}; color: #fff;">${data.criticidad_nombre || 'Criticidad'}</span>
                                    <span class="badge bg-light text-dark border ms-1">${data.periodicidad_nombre || 'Ordinaria'}</span>
                                </div>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        return `<small class="text-nowrap">${data.periodo_desde_fmt} al<br>${data.periodo_hasta_fmt}</small>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        return `<span class="fw-semibold text-danger">${data.fecha_vencimiento_fmt}</span>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        const total = parseInt(data.total_tareas || 0, 10);
                        const cumplidas = parseInt(data.tareas_cumplidas || 0, 10);
                        const porc = data.progreso_porcentaje || 0;
                        const bgClass = porc === 100 ? 'bg-success' : (porc > 0 ? 'bg-primary' : 'bg-secondary');
                        return `<div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px;">
                                        <div class="progress-bar ${bgClass}" role="progressbar" style="width: ${porc}%;"></div>
                                    </div>
                                    <small class="fw-bold">${cumplidas}/${total}</small>
                                </div>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        if (data.ciclo_vinculo_tipo_id == 1) {
                            return `<span class="badge bg-warning text-dark"><i class="fas fa-plus-circle me-1"></i>Compl. (#${data.ciclo_origen_id})</span>`;
                        } else if (data.ciclo_vinculo_tipo_id == 2) {
                            return `<span class="badge bg-danger"><i class="fas fa-route me-1"></i>Seguim. (#${data.ciclo_origen_id})</span>`;
                        } else if (data.ciclo_vinculo_tipo_id == 3) {
                            return `<span class="badge bg-info text-dark"><i class="fas fa-redo me-1"></i>Renov.</span>`;
                        }
                        return `<span class="badge bg-light text-muted border">Ordinario</span>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        let colorBadge = 'secondary';
                        switch (parseInt(data.estado_id, 10)) {
                            case 2069: colorBadge = 'info text-dark'; break; // Pendiente
                            case 2070: colorBadge = 'primary'; break; // En curso
                            case 2071: colorBadge = 'danger'; break; // Vencido
                            case 2072: colorBadge = 'success'; break; // Cumplido
                            case 2073: colorBadge = 'warning text-dark'; break; // Fuera de plazo
                            case 2074: colorBadge = 'dark'; break; // Incumplido
                            case 2075: colorBadge = 'secondary'; break; // Eximido
                            case 2076: colorBadge = 'danger'; break; // Cancelado
                        }
                        return `<span class="badge bg-${colorBadge}">${data.estado_nombre}</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function (data) {
                        const id = data.ciclo_id;
                        const estadoId = parseInt(data.estado_id, 10);
                        const esAbierto = [2069, 2070, 2071].includes(estadoId);

                        let btnDetalle = `<button class="btn btn-outline-info btn-ver-detalle" data-id="${id}" data-bs-toggle="tooltip" title="Ver Detalle y Tareas"><i class="fas fa-eye"></i></button>`;
                        
                        let btnCerrar = esAbierto ? `<button class="btn btn-outline-success btn-cerrar" data-id="${id}" data-bs-toggle="tooltip" title="Cerrar Ciclo"><i class="fas fa-check-double"></i></button>` : '';
                        
                        let btnComp = `<button class="btn btn-outline-warning btn-comp" data-id="${id}" data-bs-toggle="tooltip" title="Crear Complementario (C28)"><i class="fas fa-plus-circle"></i></button>`;
                        
                        let btnSeg = `<button class="btn btn-outline-danger btn-seg" data-id="${id}" data-bs-toggle="tooltip" title="Crear Seguimiento (C29)"><i class="fas fa-route"></i></button>`;

                        let btnEximir = esAbierto ? `<button class="btn btn-outline-secondary btn-eximir" data-id="${id}" data-bs-toggle="tooltip" title="Eximir Ciclo"><i class="fas fa-shield-alt"></i></button>` : '';

                        return `<div class="btn-group btn-group-sm" role="group">
                                    ${btnDetalle}
                                    ${btnCerrar}
                                    ${btnComp}
                                    ${btnSeg}
                                    ${btnEximir}
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

                // Obligaciones
                const $ob = $('#filtroObligacion');
                $ob.empty().append('<option value="">-- Todas las obligaciones --</option>');
                res.obligaciones.forEach(o => {
                    $ob.append(`<option value="${o.obligacion_id}">${o.codigo} - ${o.titulo}</option>`);
                });

                // Estados
                const $est = $('#filtroEstado');
                $est.empty().append('<option value="">-- Todos los estados --</option>');
                res.estados.forEach(st => {
                    $est.append(`<option value="${st.tabla_estado_registro_id}">${st.descripcion}</option>`);
                });

                // Vínculos
                const $vinc = $('#filtroVinculo');
                $vinc.empty().append('<option value="">-- Ordinarios y Vinculados --</option>');
                res.vinculos.forEach(v => {
                    $vinc.append(`<option value="${v.ciclo_vinculo_tipo_id}">${v.nombre}</option>`);
                });
            }
        });
    }

    // Eventos de filtros
    $('#filtroEntidad, #filtroObligacion, #filtroEstado, #filtroVinculo').on('change', function () {
        tablaCiclos.ajax.reload();
    });

    $('#btnRecargar').on('click', function () {
        tablaCiclos.ajax.reload();
    });

    // Ver Detalle de Ciclo
    $('#tablaCiclos').on('click', '.btn-ver-detalle', function () {
        const id = $(this).data('id');
        $.ajax({
            url: ajaxUrl,
            type: 'GET',
            data: { accion: 'obtener', ciclo_id: id, empresa_idx: empresaIdx },
            dataType: 'json',
            success: function (res) {
                if (!res.success) {
                    alert(res.message);
                    return;
                }
                const c = res.ciclo;
                $('#detObligacionCodigo').text(c.obligacion_codigo);
                $('#detObligacionTitulo').text(c.obligacion_titulo);
                $('#detEntidadNombre').text(c.entidad_nombre);
                $('#detEntidadTipo').text(c.entidad_tipo_nombre);
                $('#detPeriodicidad').text(c.periodicidad_nombre || 'N/A');
                $('#detCriticidad').text(c.criticidad_nombre || 'N/A');
                $('#detPeriodo').text(`${c.periodo_desde_fmt} al ${c.periodo_hasta_fmt}`);
                $('#detVencimiento').text(c.fecha_vencimiento_fmt);
                $('#detEstadoBadge').html(`<span class="badge bg-primary">${c.estado_nombre}</span>`);
                $('#detFechaCierre').text(c.fecha_cierre_fmt || '--');

                if (c.ciclo_origen_id) {
                    $('#divVinculoDetalle').show();
                    $('#detVinculoTexto').text(`Ciclo vinculado de tipo "${c.vinculo_tipo_nombre}" derivado del Ciclo Origen #${c.ciclo_origen_id}`);
                } else {
                    $('#divVinculoDetalle').hide();
                }

                const $tbody = $('#tbodyTareasDetalle');
                $tbody.empty();
                if (!c.tareas || c.tareas.length === 0) {
                    $tbody.append('<tr><td colspan="6" class="text-center text-muted">Sin tareas registradas</td></tr>');
                } else {
                    c.tareas.forEach(function (t, idx) {
                        let colorT = 'secondary';
                        if (t.tabla_estado_registro_id == 2081) colorT = 'success';
                        else if (t.tabla_estado_registro_id == 2079) colorT = 'primary';
                        else if (t.tabla_estado_registro_id == 2080) colorT = 'warning text-dark';
                        else if (t.tabla_estado_registro_id == 2082) colorT = 'danger';

                        const rev = t.requiere_revision == 1 ? '<span class="badge bg-warning text-dark"><i class="fas fa-check"></i> Sí</span>' : '<span class="badge bg-light text-muted border">No</span>';
                        const evi = t.requiere_evidencia == 1 ? '<span class="badge bg-info text-dark"><i class="fas fa-file-upload"></i> Sí</span>' : '<span class="badge bg-light text-muted border">No</span>';

                        $tbody.append(`<tr>
                            <td class="text-muted small">${idx + 1}</td>
                            <td>
                                <strong>${t.titulo}</strong><br>
                                <small class="text-muted">${t.descripcion || ''}</small>
                            </td>
                            <td><small>${t.fecha_vencimiento_fmt || '--'}</small></td>
                            <td class="text-center">${rev}</td>
                            <td class="text-center">${evi}</td>
                            <td class="text-center"><span class="badge bg-${colorT}">${t.estado_nombre}</span></td>
                        </tr>`);
                    });
                }

                $('#modalDetalleCiclo').modal('show');
            }
        });
    });

    // Abrir Modal Complementario (Caso C28)
    $('#tablaCiclos').on('click', '.btn-comp', function () {
        const id = $(this).data('id');
        const fila = tablaCiclos.row($(this).closest('tr')).data();
        $('#compCicloOrigenId').val(id);
        $('#compCicloOrigenTxt').val(`Ciclo #${id} - ${fila.obligacion_codigo}: ${fila.obligacion_titulo} (${fila.entidad_nombre})`);
        $('#compFechaVencimiento').val('');
        $('#compMotivo').val('');
        $('#modalComplementario').modal('show');
    });

    // Guardar Complementario
    $('#formComplementario').on('submit', function (e) {
        e.preventDefault();
        const data = $(this).serialize() + '&accion=crear_complementario&empresa_idx=' + empresaIdx;
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    $('#modalComplementario').modal('hide');
                    alert(res.message);
                    tablaCiclos.ajax.reload();
                } else {
                    alert('Error: ' + res.message);
                }
            }
        });
    });

    // Abrir Modal Seguimiento (Caso C29)
    $('#tablaCiclos').on('click', '.btn-seg', function () {
        const id = $(this).data('id');
        const fila = tablaCiclos.row($(this).closest('tr')).data();
        $('#segCicloOrigenId').val(id);
        $('#segCicloOrigenTxt').val(`Ciclo #${id} - ${fila.obligacion_codigo}: ${fila.obligacion_titulo} (${fila.entidad_nombre})`);
        $('#segFechaVencimiento').val('');
        $('#segPlanAccion').val('');
        $('#modalSeguimiento').modal('show');
    });

    // Guardar Seguimiento
    $('#formSeguimiento').on('submit', function (e) {
        e.preventDefault();
        const data = $(this).serialize() + '&accion=crear_seguimiento&empresa_idx=' + empresaIdx;
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    $('#modalSeguimiento').modal('hide');
                    alert(res.message);
                    tablaCiclos.ajax.reload();
                } else {
                    alert('Error: ' + res.message);
                }
            }
        });
    });

    // Cerrar Ciclo
    $('#tablaCiclos').on('click', '.btn-cerrar', function () {
        const id = $(this).data('id');
        $('#accionTipo').val('cerrar');
        $('#accionCicloId').val(id);
        $('#headerModalAccion').removeClass().addClass('modal-header bg-success text-white py-2');
        $('#tituloModalAccion').text('Cerrar Ciclo Operativo');
        $('#alertaTextoAccion').html('Se registrará el cierre formal del ciclo verificando el estado de sus tareas operativas.');
        $('#lblMotivoAccion').text('Observaciones de Cierre');
        $('#btnConfirmarAccion').removeClass().addClass('btn btn-sm btn-success').text('Confirmar Cierre');
        $('#accionMotivo').val('').prop('required', false);
        $('#modalAccionEstado').modal('show');
    });

    // Eximir Ciclo
    $('#tablaCiclos').on('click', '.btn-eximir', function () {
        const id = $(this).data('id');
        $('#accionTipo').val('eximir');
        $('#accionCicloId').val(id);
        $('#headerModalAccion').removeClass().addClass('modal-header bg-secondary text-white py-2');
        $('#tituloModalAccion').text('Eximir Ciclo Operativo');
        $('#alertaTextoAccion').html('El ciclo pasará a estado <strong>Eximido</strong> cancelando tareas abiertas.');
        $('#lblMotivoAccion').text('Fundamento / Motivo de Eximición *');
        $('#btnConfirmarAccion').removeClass().addClass('btn btn-sm btn-secondary').text('Confirmar Eximición');
        $('#accionMotivo').val('').prop('required', true);
        $('#modalAccionEstado').modal('show');
    });

    // Procesar acción de estado
    $('#formAccionEstado').on('submit', function (e) {
        e.preventDefault();
        const tipo = $('#accionTipo').val();
        const cicloId = $('#accionCicloId').val();
        const motivo = $('#accionMotivo').val();

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                accion: tipo,
                ciclo_id: cicloId,
                observaciones: motivo,
                motivo: motivo,
                empresa_idx: empresaIdx
            },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    $('#modalAccionEstado').modal('hide');
                    alert(res.message);
                    tablaCiclos.ajax.reload();
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
