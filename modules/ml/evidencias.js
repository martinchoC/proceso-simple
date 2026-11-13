/**
 * Javascript para Bandeja de Evidencias (Página 352 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/evidencias.js
 */

document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresaIdx = urlParams.get('empresa_id') || 2;
    const ajaxUrl = window.location.pathname.includes('/modules/ml/') ? 'evidencias_ajax.php' : 'modules/ml/evidencias_ajax.php';

    let tablaEvidencias = null;
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
        tablaEvidencias = $('#tablaEvidencias').DataTable({
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
                    d.accion = 'listar';
                    d.empresa_idx = empresaIdx;
                    d.filtro_entidad = $('#filtroEntidad').val();
                    d.filtro_ciclo = $('#filtroCiclo').val();
                    d.filtro_comprobante = $('#filtroComprobante').val();
                    d.filtro_estado = $('#filtroEstado').val();
                },
                dataSrc: function (json) {
                    if (!json.success) {
                        alert(json.message || 'Error al cargar las evidencias');
                        return [];
                    }
                    if (json.permisos) {
                        permisosUsuario = json.permisos;
                    }
                    return json.data || [];
                }
            },
            columns: [
                { data: 'evidencia_id', className: 'text-muted small fw-bold' },
                {
                    data: null,
                    render: function (data) {
                        return `<span class="fw-semibold">${data.entidad_nombre}</span>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        return `<div>
                                    <span class="badge bg-secondary me-1">${data.obligacion_codigo}</span>
                                    <span class="small fw-semibold">${data.obligacion_titulo}</span>
                                </div>
                                <div class="text-muted small">Ciclo #${data.ciclo_id}</div>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        return `<span class="fw-bold">${data.tarea_titulo}</span>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        const hashCorto = data.hash ? data.hash.substring(0, 10) + '...' : 'Sin hash';
                        return `<div><i class="fas fa-file-alt text-primary me-1"></i><span class="fw-semibold">${data.titulo}</span></div>
                                <div class="small text-muted font-monospace"><i class="fas fa-fingerprint me-1"></i>${hashCorto}</div>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        if (!data.valida_hasta_fmt) {
                            return '<span class="badge bg-light text-muted border">Permanente</span>';
                        }
                        const badgeClass = data.vigencia_estado === 'Vencida' ? 'bg-danger' : 'bg-success';
                        return `<span class="badge ${badgeClass}">${data.valida_hasta_fmt}</span>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        return data.retener_hasta_fmt ? `<small class="text-muted">${data.retener_hasta_fmt}</small>` : '<span class="text-muted small">-</span>';
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    render: function (data) {
                        let html = '';
                        if (data.es_comprobante == 1) {
                            html += '<span class="badge bg-primary" title="Comprobante Oficial"><i class="fas fa-stamp"></i></span> ';
                        }
                        if (data.confidencial == 1) {
                            html += '<span class="badge bg-danger" title="Confidencial"><i class="fas fa-lock"></i></span>';
                        }
                        return html || '<span class="text-muted small">-</span>';
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        const esActiva = (data.estado_id == 1 || data.estado_id == 2039);
                        return `<span class="badge ${esActiva ? 'bg-success' : 'bg-danger'}">${data.estado_nombre}</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function (data) {
                        const id = data.evidencia_id;
                        const esActiva = (data.estado_id == 1 || data.estado_id == 2039);

                        let btnVer = `<button class="btn btn-outline-info btn-ver-evi" data-id="${id}" data-bs-toggle="tooltip" title="Ver Ficha / Hash"><i class="fas fa-eye"></i></button>`;
                        
                        let btnDescargar = `<a href="${data.archivo_ref}" target="_blank" class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Descargar"><i class="fas fa-download"></i></a>`;

                        let btnAnular = esActiva ? `<button class="btn btn-outline-danger btn-anular-evi" data-id="${id}" data-bs-toggle="tooltip" title="Anular Evidencia"><i class="fas fa-ban"></i></button>` : '';

                        return `<div class="btn-group btn-group-sm" role="group">
                                    ${btnVer}
                                    ${btnDescargar}
                                    ${btnAnular}
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

                // Tareas abiertas para modal
                const $tSelect = $('#eviTareaId');
                $tSelect.empty().append('<option value="">-- Seleccionar tarea operativa --</option>');
                res.tareas.forEach(t => {
                    $tSelect.append(`<option value="${t.ciclo_tarea_id}">#${t.ciclo_tarea_id} - ${t.titulo} (Ciclo #${t.ciclo_id} / ${t.obligacion_codigo})</option>`);
                });
            }
        });
    }

    // Filtros
    $('#filtroEntidad, #filtroComprobante, #filtroEstado').on('change', function () {
        tablaEvidencias.ajax.reload();
    });

    $('#filtroCiclo').on('keyup change', function () {
        tablaEvidencias.ajax.reload();
    });

    $('#btnRecargar').on('click', function () {
        tablaEvidencias.ajax.reload();
    });

    // Abrir Modal Subir Evidencia
    $('#btnSubirEvidencia').on('click', function () {
        $('#eviTareaId').val('');
        $('#eviTitulo').val('');
        $('#eviDescripcion').val('');
        $('#eviArchivo').val('');
        $('#eviValidaHasta').val('');
        $('#eviRetenerHasta').val('');
        $('#eviEsComprobante').prop('checked', false);
        $('#eviConfidencial').prop('checked', false);
        $('#modalSubirEvidencia').modal('show');
    });

    // Subir Evidencia vía FormData
    $('#formSubirEvidencia').on('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('accion', 'subir');
        formData.append('empresa_idx', empresaIdx);

        const $btn = $('#btnGuardarEvi');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Subiendo...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                $btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Subir y Firmar SHA-256');
                if (res.success) {
                    $('#modalSubirEvidencia').modal('hide');
                    alert(res.message);
                    tablaEvidencias.ajax.reload();
                } else {
                    alert('Error: ' + res.message);
                }
            },
            error: function () {
                $btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Subir y Firmar SHA-256');
                alert('Error al comunicarse con el servidor.');
            }
        });
    });

    // Ver Detalle de Evidencia
    $('#tablaEvidencias').on('click', '.btn-ver-evi', function () {
        const id = $(this).data('id');
        $.ajax({
            url: ajaxUrl,
            type: 'GET',
            data: { accion: 'obtener', evidencia_id: id, empresa_idx: empresaIdx },
            dataType: 'json',
            success: function (res) {
                if (!res.success) {
                    alert(res.message);
                    return;
                }
                const e = res.evidencia;
                $('#detEviTitulo').text(e.titulo);
                $('#detEviEntidadCiclo').text(`${e.entidad_nombre} | Ciclo #${e.ciclo_id}`);
                $('#detEviTarea').text(e.tarea_titulo);
                $('#detEviObligacion').text(`${e.obligacion_codigo} - ${e.obligacion_titulo}`);
                $('#detEviHash').text(e.hash || 'Sin hash calculado');
                $('#detEviValida').text(e.valida_hasta_fmt || 'Permanente');
                $('#detEviRetiene').text(e.retener_hasta_fmt || 'No estipulado');
                $('#detEviUsuario').text(`${e.cargado_por_nombre || '--'} (${e.creado_en_fmt})`);
                $('#detEviDesc').text(e.descripcion || 'Sin descripción adicional.');
                $('#btnDescargaArchivo').attr('href', e.archivo_ref);

                $('#modalDetalleEvidencia').modal('show');
            }
        });
    });

    // Abrir Modal Anular Evidencia
    $('#tablaEvidencias').on('click', '.btn-anular-evi', function () {
        const id = $(this).data('id');
        $('#anularEviId').val(id);
        $('#anularMotivo').val('');
        $('#modalAnularEvidencia').modal('show');
    });

    // Guardar Anulación
    $('#formAnularEvidencia').on('submit', function (e) {
        e.preventDefault();
        const data = $(this).serialize() + '&accion=anular&empresa_idx=' + empresaIdx;
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    $('#modalAnularEvidencia').modal('hide');
                    alert(res.message);
                    tablaEvidencias.ajax.reload();
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
