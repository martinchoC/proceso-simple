/**
 * Javascript para Hallazgos e Incidencias (Página 353 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/incidencias.js
 */

document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresaIdx = urlParams.get('empresa_id') || 2;
    const ajaxUrl = 'incidencias_ajax.php';

    let tablaIncidencias = null;
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
        tablaIncidencias = $('#tablaIncidencias').DataTable({
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
                    d.accion = 'listar_incidencias';
                    d.empresa_idx = empresaIdx;
                    d.filtro_entidad_id = $('#filtroEntidad').val();
                    d.filtro_ciclo_id = $('#filtroCiclo').val();
                    d.filtro_estado_id = $('#filtroEstado').val();
                },
                dataSrc: function (json) {
                    if (!json.success) {
                        Swal.fire('Error', json.message || 'Error al cargar las incidencias', 'error');
                        return [];
                    }
                    return json.data || [];
                }
            },
            columns: [
                { data: 'incidencia_id', className: 'text-muted small fw-bold' },
                {
                    data: null,
                    render: function (data) {
                        return `<span class="fw-semibold">${data.entidad_nombre || '-'}</span>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        return `<div>
                            <span class="badge bg-secondary">Ciclo #${data.ciclo_id}</span>
                            <div class="small fw-semibold mt-1">${data.obligacion_codigo || ''} - ${data.obligacion_titulo || ''}</div>
                        </div>`;
                    }
                },
                {
                    data: 'descripcion',
                    render: function (data) {
                        if (!data) return '-';
                        const corta = data.length > 70 ? data.substring(0, 70) + '...' : data;
                        return `<span title="${data.replace(/"/g, '&quot;')}">${corta}</span>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        return `<div class="small">
                            <div><i class="far fa-clock me-1 text-muted"></i>${data.abierta_en_fmt || '-'}</div>
                            <div class="text-muted"><i class="far fa-user me-1"></i>${data.reportado_por_nombre || 'Sistema'}</div>
                        </div>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        if (data.ciclo_seguimiento_id) {
                            return `<div class="small">
                                <span class="badge bg-info text-dark"><i class="fas fa-link me-1"></i>Ciclo #${data.ciclo_seguimiento_id}</span>
                                <div class="text-muted mt-1"><i class="far fa-calendar-alt me-1"></i>Vence: ${data.seguimiento_vencimiento_fmt || '-'}</div>
                            </div>`;
                        } else {
                            return `<span class="badge bg-light text-muted border">Sin vincular</span>`;
                        }
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        if (parseInt(data.estado_id) === 1) {
                            return `<span class="badge bg-warning text-dark"><i class="fas fa-exclamation-circle me-1"></i>Abierta</span>`;
                        } else if (parseInt(data.estado_id) === 2) {
                            return `<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Subsanada</span>`;
                        } else {
                            return `<span class="badge bg-secondary">${data.estado_nombre || 'Inactiva'}</span>`;
                        }
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function (data) {
                        let botones = `<div class="btn-group btn-group-sm">`;
                        
                        // Ver detalle (siempre disponible)
                        botones += `<button type="button" class="btn btn-outline-info btn-ver" data-id="${data.incidencia_id}" data-bs-toggle="tooltip" title="Ver Detalle">
                            <i class="fas fa-eye"></i>
                        </button>`;

                        // Si está abierta, vincular seguimiento si no tiene
                        if (parseInt(data.estado_id) === 1 && !data.ciclo_seguimiento_id) {
                            botones += `<button type="button" class="btn btn-outline-primary btn-seguimiento" data-id="${data.incidencia_id}" data-bs-toggle="tooltip" title="Vincular Ciclo Seguimiento (C29)">
                                <i class="fas fa-link"></i>
                            </button>`;
                        }

                        // Si está abierta, permitir cerrar
                        if (parseInt(data.estado_id) === 1) {
                            botones += `<button type="button" class="btn btn-outline-success btn-cerrar" data-id="${data.incidencia_id}" data-bs-toggle="tooltip" title="Cerrar y Subsanar">
                                <i class="fas fa-check"></i>
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

            // Entidades
            let optEnt = '<option value="">-- Todas las entidades --</option>';
            res.entidades.forEach(function (e) {
                optEnt += `<option value="${e.entidad_id}">${e.nombre}</option>`;
            });
            $('#filtroEntidad').html(optEnt);

            // Ciclos para el modal de nuevo hallazgo
            let optCic = '<option value="">-- Seleccionar Ciclo Operativo --</option>';
            res.ciclos.forEach(function (c) {
                optCic += `<option value="${c.ciclo_id}">#${c.ciclo_id} | ${c.entidad_nombre} | ${c.obligacion_codigo} - ${c.obligacion_titulo} (Vence: ${c.vencimiento_fmt})</option>`;
            });
            $('#modalCicloId').html(optCic);

            // Permisos de UI
            if (!permisosUsuario.includes('registrar_incidencia')) {
                $('#btnNuevaIncidencia').hide();
            }
        });
    }

    // Eventos de Filtro
    $('#filtroEntidad, #filtroEstado').on('change', function () {
        tablaIncidencias.ajax.reload();
    });

    $('#filtroCiclo').on('keyup', function (e) {
        if (e.key === 'Enter' || this.value.length === 0) {
            tablaIncidencias.ajax.reload();
        }
    });

    $('#btnRecargar').on('click', function () {
        tablaIncidencias.ajax.reload();
    });

    // Abrir Modal Nuevo Hallazgo
    $('#btnNuevaIncidencia').on('click', function () {
        $('#formNuevaIncidencia')[0].reset();
        const modal = new bootstrap.Modal(document.getElementById('modalNuevaIncidencia'));
        modal.show();
    });

    // Guardar Nuevo Hallazgo
    $('#formNuevaIncidencia').on('submit', function (e) {
        e.preventDefault();
        const cicloId = $('#modalCicloId').val();
        const descripcion = $('#modalDescripcion').val().trim();

        if (!cicloId || !descripcion) {
            Swal.fire('Atención', 'Complete todos los campos obligatorios.', 'warning');
            return;
        }

        const btn = $('#btnGuardarIncidencia');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                accion: 'registrar_incidencia',
                empresa_idx: empresaIdx,
                ciclo_id: cicloId,
                descripcion: descripcion
            },
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Registrar Hallazgo');
                if (res.success) {
                    Swal.fire('Éxito', res.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalNuevaIncidencia')).hide();
                    tablaIncidencias.ajax.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () {
                btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Registrar Hallazgo');
                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
            }
        });
    });

    // Abrir Modal Vincular Seguimiento
    $(document).on('click', '.btn-seguimiento', function () {
        const id = $(this).data('id');
        $('#vincularIncidenciaId').val(id);
        $('#vincularPlanAccion').val('');
        const modal = new bootstrap.Modal(document.getElementById('modalVincularSeguimiento'));
        modal.show();
    });

    // Confirmar Vincular Seguimiento
    $('#formVincularSeguimiento').on('submit', function (e) {
        e.preventDefault();
        const id = $('#vincularIncidenciaId').val();
        const plan = $('#vincularPlanAccion').val().trim();

        if (!id || !plan) {
            Swal.fire('Atención', 'Detalle el plan de acción correctivo.', 'warning');
            return;
        }

        const btn = $('#btnConfirmarSeguimiento');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Procesando...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                accion: 'vincular_seguimiento',
                empresa_idx: empresaIdx,
                incidencia_id: id,
                plan_accion: plan
            },
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html('<i class="fas fa-link me-1"></i>Generar Ciclo de Seguimiento');
                if (res.success) {
                    Swal.fire('Ciclo Creado', res.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalVincularSeguimiento')).hide();
                    tablaIncidencias.ajax.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () {
                btn.prop('disabled', false).html('<i class="fas fa-link me-1"></i>Generar Ciclo de Seguimiento');
                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
            }
        });
    });

    // Abrir Modal Cerrar Incidencia
    $(document).on('click', '.btn-cerrar', function () {
        const id = $(this).data('id');
        $('#cerrarIncidenciaId').val(id);
        $('#cerrarMotivo').val('');
        const modal = new bootstrap.Modal(document.getElementById('modalCerrarIncidencia'));
        modal.show();
    });

    // Confirmar Cierre Incidencia
    $('#formCerrarIncidencia').on('submit', function (e) {
        e.preventDefault();
        const id = $('#cerrarIncidenciaId').val();
        const motivo = $('#cerrarMotivo').val().trim();

        if (!id || !motivo) {
            Swal.fire('Atención', 'Detalle el motivo o comprobante de subsanación.', 'warning');
            return;
        }

        const btn = $('#btnConfirmarCierre');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Cerrando...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                accion: 'cerrar_incidencia',
                empresa_idx: empresaIdx,
                incidencia_id: id,
                motivo: motivo
            },
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html('<i class="fas fa-check me-1"></i>Dar por Subsanada');
                if (res.success) {
                    Swal.fire('Subsanada', res.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalCerrarIncidencia')).hide();
                    tablaIncidencias.ajax.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () {
                btn.prop('disabled', false).html('<i class="fas fa-check me-1"></i>Dar por Subsanada');
                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
            }
        });
    });

    // Ver Detalle
    $(document).on('click', '.btn-ver', function () {
        const id = $(this).data('id');
        $.getJSON(ajaxUrl, { accion: 'obtener_incidencia', empresa_idx: empresaIdx, incidencia_id: id }, function (res) {
            if (!res.success) {
                Swal.fire('Error', res.message, 'error');
                return;
            }
            const inc = res.data;
            const estadoBadge = parseInt(inc.tabla_estado_registro_id) === 1 
                ? '<span class="badge bg-warning text-dark">Abierta</span>' 
                : '<span class="badge bg-success">Cerrada / Subsanada</span>';

            const seguimientoInfo = inc.ciclo_seguimiento_id 
                ? `<span class="badge bg-info text-dark">Ciclo #${inc.ciclo_seguimiento_id} (Vence: ${inc.seguimiento_vencimiento_fmt || '-'})</span>`
                : '<span class="text-muted">No vinculado</span>';

            const html = `
                <div class="row g-3">
                    <div class="col-md-6">
                        <strong>ID Incidencia:</strong> #${inc.incidencia_id}<br>
                        <strong>Estado:</strong> ${estadoBadge}<br>
                        <strong>Reportada el:</strong> ${inc.abierta_en_fmt || '-'}<br>
                        <strong>Reportada por:</strong> ${inc.reportado_por_nombre || 'Sistema'}
                    </div>
                    <div class="col-md-6">
                        <strong>Entidad:</strong> ${inc.entidad_nombre}<br>
                        <strong>Ciclo Origen:</strong> #${inc.ciclo_id} (Vence: ${inc.ciclo_vencimiento_fmt})<br>
                        <strong>Obligación:</strong> [${inc.obligacion_codigo}] ${inc.obligacion_titulo}<br>
                        <strong>Ciclo Seguimiento (C29):</strong> ${seguimientoInfo}
                    </div>
                    <div class="col-12">
                        <hr class="my-2">
                        <h6 class="fw-bold mb-1 text-secondary"><i class="fas fa-align-left me-1"></i>Descripción de la Desviación / Bitácora:</h6>
                        <div class="p-3 bg-light rounded border text-monospace small" style="white-space: pre-wrap;">${inc.descripcion || 'Sin descripción'}</div>
                    </div>
                </div>
            `;
            $('#detalleIncidenciaBody').html(html);
            const modal = new bootstrap.Modal(document.getElementById('modalDetalleIncidencia'));
            modal.show();
        });
    });

    // Iniciar
    initDataTable();
    cargarCombos();
});
