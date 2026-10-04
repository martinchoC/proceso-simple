/**
 * Javascript para Cambios Masivos e Impacto (Página 363 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/cambios_masivos.js
 */

document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresaIdx = urlParams.get('empresa_id') || 2;
    const ajaxUrl = 'cambios_masivos_ajax.php';

    let tablaCambiosMasivos = null;
    let permisosUsuario = [];

    // Tooltips
    function initTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // Inicializar DataTable
    function initDataTable() {
        tablaCambiosMasivos = $('#tablaCambiosMasivos').DataTable({
            responsive: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            dom: '<"row mb-2"<"col-12 col-md-6"><"col-12 col-md-6 text-end"f>>rt<"row mt-3 align-items-center"<"col-12 col-md-6 d-flex flex-wrap align-items-center gap-3"li><"col-12 col-md-6 d-flex justify-content-md-end justify-content-center"p>>',
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            ajax: {
                url: ajaxUrl,
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar_cambios';
                    d.empresa_idx = empresaIdx;
                    d.filtro_estado_id = $('#filtroEstado').val();
                },
                dataSrc: function (json) {
                    if (!json.success) {
                        Swal.fire('Error', json.message || 'Error al cargar los cambios masivos', 'error');
                        return [];
                    }
                    return json.data || [];
                }
            },
            columns: [
                { data: 'cambio_masivo_id', className: 'text-muted small fw-bold' },
                {
                    data: null,
                    render: function (data) {
                        return `<div>
                            <span class="badge bg-secondary font-monospace">${data.norma_codigo || '-'}</span>
                            <span class="badge bg-light text-dark border">v${data.numero_version || '1'}</span>
                            <div class="small fw-semibold mt-1">${data.norma_titulo || ''}</div>
                        </div>`;
                    }
                },
                {
                    data: 'cambio_descripcion',
                    render: function (data) {
                        if (!data) return '-';
                        const corta = data.length > 60 ? data.substring(0, 60) + '...' : data;
                        return `<span title="${data.replace(/"/g, '&quot;')}">${corta}</span>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        const ent = parseInt(data.total_entidades) || 0;
                        const cic = parseInt(data.total_ciclos_afectados) || 0;
                        const tar = parseInt(data.total_tareas_afectadas) || 0;
                        return `<div>
                            <span class="badge bg-info text-dark" title="Entidades"><i class="fas fa-building me-1"></i>${ent} sedes</span>
                            <span class="badge bg-warning text-dark" title="Ciclos"><i class="fas fa-sync-alt me-1"></i>${cic} ciclos</span>
                            <span class="badge bg-secondary" title="Tareas"><i class="fas fa-tasks me-1"></i>${tar} tareas</span>
                        </div>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        return `<div class="small">
                            <div><i class="far fa-clock me-1 text-muted"></i>${data.creado_en_fmt || '-'}</div>
                            <div class="text-muted"><i class="far fa-user me-1"></i>${data.solicitado_por_nombre || 'Sistema'}</div>
                        </div>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        if (data.aplicado_en_fmt) {
                            return `<div class="small text-success">
                                <i class="fas fa-check-circle me-1"></i>Aplicado el ${data.aplicado_en_fmt}
                            </div>`;
                        } else if (data.aprobado_en_fmt) {
                            return `<div class="small text-primary">
                                <i class="fas fa-user-check me-1"></i>Aprobado por ${data.aprobado_por_nombre || '-'}
                                <div class="text-muted">${data.aprobado_en_fmt}</div>
                            </div>`;
                        } else {
                            return `<span class="badge bg-light text-muted border">Pendiente Aprobación</span>`;
                        }
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        const st = parseInt(data.estado_id);
                        if (st === 3) {
                            return `<span class="badge bg-success"><i class="fas fa-check-double me-1"></i>Aplicado</span>`;
                        } else if (st === 2) {
                            return `<span class="badge bg-primary"><i class="fas fa-thumbs-up me-1"></i>Aprobado</span>`;
                        } else {
                            return `<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Pendiente</span>`;
                        }
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function (data) {
                        let botones = `<div class="btn-group btn-group-sm">`;

                        // Ver detalle de impacto por entidad
                        botones += `<button type="button" class="btn btn-outline-info btn-ver-impacto" data-id="${data.cambio_masivo_id}" data-bs-toggle="tooltip" title="Ver Desglose de Entidades">
                            <i class="fas fa-sitemap"></i>
                        </button>`;

                        const st = parseInt(data.estado_id);

                        // Si está pendiente y tiene permiso, botón Aprobar
                        if (st === 1 && permisosUsuario.includes('aprobar_cambio')) {
                            botones += `<button type="button" class="btn btn-outline-primary btn-aprobar" data-id="${data.cambio_masivo_id}" data-bs-toggle="tooltip" title="Aprobar Cambio Masivo">
                                <i class="fas fa-check"></i>
                            </button>`;
                        }

                        // Si está aprobado y tiene permiso, botón Aplicar
                        if (st === 2 && permisosUsuario.includes('aplicar_cambio')) {
                            botones += `<button type="button" class="btn btn-outline-success btn-aplicar" data-id="${data.cambio_masivo_id}" data-bs-toggle="tooltip" title="Aplicar Recálculo en Cascada">
                                <i class="fas fa-play"></i>
                            </button>`;
                        }

                        botones += `</div>`;
                        return botones;
                    }
                }
            ],
            drawCallback: function () {
                initTooltips();
            }
        });
    }

    // Cargar combos
    function cargarCombos() {
        $.getJSON(ajaxUrl, { accion: 'combos', empresa_idx: empresaIdx }, function (res) {
            if (!res.success) return;

            permisosUsuario = res.funciones_permitidas || [];

            let optN = '<option value="">-- Seleccionar Versión de Norma --</option>';
            res.normas.forEach(function (n) {
                optN += `<option value="${n.norma_version_id}">[${n.norma_codigo}] ${n.norma_titulo} (v${n.numero_version})</option>`;
            });
            $('#modalNormaVersionId').html(optN);

            if (!permisosUsuario.includes('simular_impacto')) {
                $('#btnNuevaSimulacion').hide();
            }
        });
    }

    // Filtros
    $('#filtroEstado').on('change', function () {
        tablaCambiosMasivos.ajax.reload();
    });

    $('#btnRecargar').on('click', function () {
        tablaCambiosMasivos.ajax.reload();
    });

    // Abrir Modal Simulación
    $('#btnNuevaSimulacion').on('click', function () {
        $('#formNuevaSimulacion')[0].reset();
        const modal = new bootstrap.Modal(document.getElementById('modalNuevaSimulacion'));
        modal.show();
    });

    // Procesar Simulación de Impacto
    $('#formNuevaSimulacion').on('submit', function (e) {
        e.preventDefault();
        const versionId = $('#modalNormaVersionId').val();
        const desc = $('#modalDescripcion').val().trim();

        if (!versionId || !desc) {
            Swal.fire('Atención', 'Complete los campos obligatorios.', 'warning');
            return;
        }

        const btn = $('#btnProcesarSimulacion');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Analizando impacto...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                accion: 'simular_impacto',
                empresa_idx: empresaIdx,
                norma_version_id: versionId,
                descripcion: desc
            },
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html('<i class="fas fa-play me-1"></i>Calcular Impacto');
                if (res.success) {
                    Swal.fire({
                        title: 'Simulación Calculada',
                        html: `${res.message}<br><br><strong>Norma:</strong> ${res.norma}<br><strong>Entidades afectadas:</strong> ${res.total_entidades}<br><strong>Ciclos a regenerar:</strong> ${res.total_ciclos}<br><strong>Tareas a recalcular:</strong> ${res.total_tareas}`,
                        icon: 'success'
                    });
                    bootstrap.Modal.getInstance(document.getElementById('modalNuevaSimulacion')).hide();
                    tablaCambiosMasivos.ajax.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () {
                btn.prop('disabled', false).html('<i class="fas fa-play me-1"></i>Calcular Impacto');
                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
            }
        });
    });

    // Ver Desglose de Impacto
    $(document).on('click', '.btn-ver-impacto', function () {
        const id = $(this).data('id');
        $('#tbodyDetalleImpacto').html('<tr><td colspan="3" class="text-center py-3"><i class="fas fa-spinner fa-spin me-1"></i>Cargando impacto...</td></tr>');
        const modal = new bootstrap.Modal(document.getElementById('modalDetalleImpacto'));
        modal.show();

        $.getJSON(ajaxUrl, { accion: 'obtener_detalles', empresa_idx: empresaIdx, cambio_masivo_id: id }, function (res) {
            if (!res.success || !res.detalles) {
                $('#tbodyDetalleImpacto').html('<tr><td colspan="3" class="text-center text-danger py-3">Error al cargar desglose.</td></tr>');
                return;
            }

            let html = '';
            res.detalles.forEach(function (d) {
                html += `
                    <tr>
                        <td class="fw-semibold"><i class="fas fa-building me-1 text-info"></i>${d.entidad_nombre}</td>
                        <td class="text-center"><span class="badge bg-warning text-dark font-monospace">${d.ciclos_afectados}</span></td>
                        <td class="text-center"><span class="badge bg-secondary font-monospace">${d.tareas_afectadas}</span></td>
                    </tr>
                `;
            });

            $('#tbodyDetalleImpacto').html(html || '<tr><td colspan="3" class="text-center text-muted py-3">No hay entidades alcanzadas por este cambio.</td></tr>');
        });
    });

    // Aprobar Cambio Masivo
    $(document).on('click', '.btn-aprobar', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: `¿Aprobar Cambio Masivo #${id}?`,
            text: 'Se registrará su aprobación formal como responsable autorizado antes de la aplicación en cascada.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Sí, aprobar cambio',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        accion: 'aprobar_cambio',
                        empresa_idx: empresaIdx,
                        cambio_masivo_id: id
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            Swal.fire('Aprobado', res.message, 'success');
                            tablaCambiosMasivos.ajax.reload();
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
                    }
                });
            }
        });
    });

    // Aplicar Cambio Masivo
    $(document).on('click', '.btn-aplicar', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: `¿Aplicar Recálculo Masivo #${id}?`,
            text: 'Se ejecutará el motor de herencia y se actualizarán los ciclos y tareas operativas de todas las entidades alcanzadas.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Sí, aplicar recálculo ahora',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Aplicando cambio masivo...',
                    text: 'Por favor aguarde mientras el motor propaga los cambios.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        accion: 'aplicar_cambio',
                        empresa_idx: empresaIdx,
                        cambio_masivo_id: id
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            Swal.fire('Cambio Aplicado', res.message, 'success');
                            tablaCambiosMasivos.ajax.reload();
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
                    }
                });
            }
        });
    });

    // Iniciar
    initDataTable();
    cargarCombos();
});
