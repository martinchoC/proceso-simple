/**
 * Javascript para Consola de Alertas (Página 354 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/alertas.js
 */

document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresaIdx = urlParams.get('empresa_id') || 2;
    const ajaxUrl = 'alertas_ajax.php';

    let tablaAlertas = null;
    let permisosUsuario = [];

    // Tooltips
    function initTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // Cargar KPIs
    function cargarKpis() {
        $.getJSON(ajaxUrl, { accion: 'kpis', empresa_idx: empresaIdx }, function (res) {
            if (res.success && res.kpis) {
                $('#kpiTotal').text(res.kpis.total);
                $('#kpiPendientes').text(res.kpis.pendientes);
                $('#kpiEnviadas').text(res.kpis.enviadas);
                $('#kpiEmail').text(res.kpis.canal_email);
                $('#kpiSistema').text(res.kpis.canal_sistema);
            }
        });
    }

    // Inicializar DataTable
    function initDataTable() {
        tablaAlertas = $('#tablaAlertas').DataTable({
            responsive: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            dom: '<"row mb-2"<"col-12 col-md-6"><"col-12 col-md-6 text-end"f>>rt<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>',
            language: {
                url: window.APP_ASSETS_URL + 'js/datatables-i18n-1.13.7/es-ES.json'
            },
            ajax: {
                url: ajaxUrl,
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar_alertas';
                    d.empresa_idx = empresaIdx;
                    d.filtro_canal = $('#filtroCanal').val();
                    d.filtro_estado_id = $('#filtroEstado').val();
                    d.filtro_usuario_id = $('#filtroUsuario').val();
                },
                dataSrc: function (json) {
                    if (!json.success) {
                        Swal.fire('Error', json.message || 'Error al cargar las alertas', 'error');
                        return [];
                    }
                    return json.data || [];
                }
            },
            columns: [
                { data: 'alerta_id', className: 'text-muted small fw-bold' },
                {
                    data: null,
                    render: function (data) {
                        let badgeClass = 'bg-secondary';
                        let icono = 'fa-bell';
                        if (data.motivo_alerta.includes('Evidencia')) {
                            badgeClass = 'bg-info text-dark';
                            icono = 'fa-file-signature';
                        } else if (data.motivo_alerta.includes('Tarea')) {
                            badgeClass = 'bg-warning text-dark';
                            icono = 'fa-tasks';
                        } else {
                            badgeClass = 'bg-primary';
                            icono = 'fa-calendar-alt';
                        }
                        return `<span class="badge ${badgeClass}"><i class="fas ${icono} me-1"></i>${data.motivo_alerta}</span>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        return `<div>
                            <span class="fw-semibold">${data.entidad_nombre || '-'}</span>
                            <div class="small text-muted">${data.obligacion_codigo ? '[' + data.obligacion_codigo + '] ' + (data.obligacion_titulo || '') : ''}</div>
                        </div>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        if (data.evidencia_id) {
                            return `<div class="small">
                                <i class="fas fa-file-contract text-primary me-1"></i>${data.evidencia_titulo || 'Evidencia #' + data.evidencia_id}
                                <div class="text-danger"><i class="far fa-calendar-times me-1"></i>Vence validez: ${data.evidencia_valida_hasta_fmt || '-'}</div>
                            </div>`;
                        } else if (data.ciclo_tarea_id) {
                            return `<div class="small">
                                <i class="fas fa-check-square text-success me-1"></i>${data.tarea_titulo || 'Tarea #' + data.ciclo_tarea_id}
                                <div class="text-muted"><i class="far fa-calendar-alt me-1"></i>Vence ciclo: ${data.ciclo_vencimiento_fmt || '-'}</div>
                            </div>`;
                        } else {
                            return `<span class="small text-muted">Ciclo #${data.ciclo_id || '-'}</span>`;
                        }
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        return `<span class="small"><i class="far fa-user me-1 text-muted"></i>${data.destinatario_nombre || '-'}</span>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        const icon = data.canal === 'EMAIL' ? 'fa-envelope text-info' : 'fa-desktop text-secondary';
                        return `<span class="small"><i class="fas ${icon} me-1"></i>${data.canal}</span>`;
                    }
                },
                {
                    data: 'programada_para_fmt',
                    className: 'small'
                },
                {
                    data: null,
                    render: function (data) {
                        return data.enviada_en_fmt ? `<span class="small text-success"><i class="fas fa-check me-1"></i>${data.enviada_en_fmt}</span>` : `<span class="text-muted small">Pendiente</span>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        if (parseInt(data.estado_id) === 1) {
                            return `<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Pendiente</span>`;
                        } else {
                            return `<span class="badge bg-success"><i class="fas fa-paper-plane me-1"></i>Enviada</span>`;
                        }
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function (data) {
                        return `<div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary btn-reenviar" data-id="${data.alerta_id}" data-bs-toggle="tooltip" title="Despachar / Reenviar ahora">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>`;
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

            // Usuarios
            let optU = '<option value="">-- Todos los usuarios --</option>';
            res.usuarios.forEach(function (u) {
                optU += `<option value="${u.usuario_id}">${u.usuario_nombre}</option>`;
            });
            $('#filtroUsuario').html(optU);

            // Permisos en botones
            if (!permisosUsuario.includes('disparar_alertas')) {
                $('#btnDisparar, #btnDespacharCola').hide();
            }
        });
    }

    // Eventos de Filtro
    $('#filtroCanal, #filtroEstado, #filtroUsuario').on('change', function () {
        tablaAlertas.ajax.reload();
        cargarKpis();
    });

    $('#btnRecargar').on('click', function () {
        tablaAlertas.ajax.reload();
        cargarKpis();
    });

    // Disparar Alertas Preventivas
    $('#btnDisparar').on('click', function () {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Analizando...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                accion: 'disparar_alertas',
                empresa_idx: empresaIdx
            },
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html('<i class="fas fa-cogs me-1"></i>Generar Preventivas');
                if (res.success) {
                    Swal.fire('Proceso Completado', res.message, 'success');
                    tablaAlertas.ajax.reload();
                    cargarKpis();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () {
                btn.prop('disabled', false).html('<i class="fas fa-cogs me-1"></i>Generar Preventivas');
                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
            }
        });
    });

    // Despachar Cola de Notificaciones
    $('#btnDespacharCola').on('click', function () {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Enviando...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                accion: 'despachar_cola',
                empresa_idx: empresaIdx
            },
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-1"></i>Despachar Cola');
                if (res.success) {
                    Swal.fire('Cola Despachada', res.message, 'success');
                    tablaAlertas.ajax.reload();
                    cargarKpis();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () {
                btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-1"></i>Despachar Cola');
                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
            }
        });
    });

    // Reenviar Alerta individual
    $(document).on('click', '.btn-reenviar', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: '¿Despachar alerta #' + id + '?',
            text: 'Se registrará el envío inmediato por su canal configurado.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Sí, despachar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        accion: 'reenviar_alerta',
                        empresa_idx: empresaIdx,
                        alerta_id: id
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            Swal.fire('Despachada', res.message, 'success');
                            tablaAlertas.ajax.reload();
                            cargarKpis();
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
    cargarKpis();
});
