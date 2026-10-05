/**
 * Javascript para Cargas Masivas (Página 362 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/cargas_masivas.js
 */

document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresaIdx = urlParams.get('empresa_id') || 2;
    const ajaxUrl = 'cargas_masivas_ajax.php';

    let tablaCargas = null;
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
        tablaCargas = $('#tablaCargas').DataTable({
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
                    d.accion = 'listar_cargas';
                    d.empresa_idx = empresaIdx;
                    d.filtro_tipo_id = $('#filtroTipo').val();
                    d.filtro_estado_id = $('#filtroEstado').val();
                },
                dataSrc: function (json) {
                    if (!json.success) {
                        Swal.fire('Error', json.message || 'Error al cargar las cargas masivas', 'error');
                        return [];
                    }
                    if (json.permisos && Array.isArray(json.permisos)) {
                        permisosUsuario = json.permisos;
                    }
                    return json.data || [];
                }
            },
            columns: [
                { data: 'carga_id', className: 'text-muted small fw-bold' },
                {
                    data: null,
                    render: function (data) {
                        let badgeClass = 'bg-primary';
                        if (data.tipo_codigo === 'OBLIGACIONES') badgeClass = 'bg-warning text-dark';
                        else if (data.tipo_codigo === 'ENTIDADES') badgeClass = 'bg-info text-dark';
                        return `<span class="badge ${badgeClass}">${data.tipo_nombre || data.tipo_codigo}</span>`;
                    }
                },
                {
                    data: 'archivo_ref',
                    render: function (data) {
                        if (!data) return '-';
                        const nombreArchivo = data.split('/').pop();
                        return `<span class="small font-monospace text-truncate d-inline-block" style="max-width: 200px;" title="${data}"><i class="far fa-file-alt me-1 text-muted"></i>${nombreArchivo}</span>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        const tot = parseInt(data.total_filas) || 0;
                        const ok = parseInt(data.filas_validas) || 0;
                        const err = parseInt(data.filas_con_error) || 0;
                        return `<div>
                            <span class="badge bg-secondary" title="Total">${tot}</span>
                            <span class="badge bg-success" title="Válidas"><i class="fas fa-check me-1"></i>${ok}</span>
                            ${err > 0 ? `<span class="badge bg-danger" title="Con Error"><i class="fas fa-exclamation-triangle me-1"></i>${err}</span>` : ''}
                        </div>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        return `<div class="small">
                            <div><i class="far fa-clock me-1 text-muted"></i>${data.validada_en_fmt || data.creado_en_fmt || '-'}</div>
                            <div class="text-muted"><i class="far fa-user me-1"></i>${data.cargado_por_nombre || 'Sistema'}</div>
                        </div>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        if (data.confirmada_en_fmt) {
                            return `<div class="small">
                                <div class="text-success"><i class="fas fa-check-circle me-1"></i>${data.confirmada_en_fmt}</div>
                                <div class="text-muted"><i class="far fa-user me-1"></i>${data.confirmado_por_nombre || '-'}</div>
                            </div>`;
                        } else {
                            return `<span class="text-muted small">No confirmada</span>`;
                        }
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        const st = parseInt(data.estado_id);
                        if (st === 4) {
                            return `<span class="badge bg-success"><i class="fas fa-check-double me-1"></i>Confirmada</span>`;
                        } else if (st === 2) {
                            return `<span class="badge bg-primary"><i class="fas fa-check me-1"></i>Validada OK</span>`;
                        } else if (st === 3) {
                            return `<span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i>Con Errores</span>`;
                        } else {
                            return `<span class="badge bg-secondary">${data.estado_nombre || 'Cargada'}</span>`;
                        }
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function (data) {
                        let botones = `<div class="btn-group btn-group-sm">`;

                        // 1. Ver filas y diagnóstico (siempre disponible)
                        botones += `<button type="button" class="btn btn-outline-info btn-ver-filas" data-id="${data.carga_id}" data-bs-toggle="tooltip" title="Ver Diagnóstico de Filas">
                            <i class="fas fa-list-ol"></i>
                        </button>`;

                        // 2. Si no está confirmada y tiene filas válidas, permitir confirmar
                        const st = parseInt(data.estado_id);
                        const ok = parseInt(data.filas_validas) || 0;
                        const puedeConfirmar = permisosUsuario.length === 0 || permisosUsuario.includes('confirmar_carga');
                        if (st !== 4 && ok > 0 && puedeConfirmar) {
                            botones += `<button type="button" class="btn btn-outline-success btn-confirmar" data-id="${data.carga_id}" data-ok="${ok}" data-bs-toggle="tooltip" title="Confirmar e Importar (${ok} filas válidas)">
                                <i class="fas fa-check-double"></i>
                            </button>`;
                        }

                        // 3. Eliminar / descartar carga
                        const puedeEliminar = permisosUsuario.length === 0 || permisosUsuario.includes('eliminar_carga');
                        if (puedeEliminar) {
                            botones += `<button type="button" class="btn btn-outline-danger btn-eliminar" data-id="${data.carga_id}" data-bs-toggle="tooltip" title="Eliminar o Descartar Lote">
                                <i class="fas fa-trash-alt"></i>
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

            let optT = '<option value="">-- Todos los tipos --</option>';
            let optModal = '<option value="">-- Seleccionar Tipo --</option>';
            res.tipos.forEach(function (t) {
                optT += `<option value="${t.carga_tipo_id}">${t.nombre}</option>`;
                optModal += `<option value="${t.carga_tipo_id}">${t.nombre} (${t.codigo})</option>`;
            });
            $('#filtroTipo').html(optT);
            $('#modalCargaTipoId').html(optModal);

            if (!permisosUsuario.includes('subir_validar_carga')) {
                $('#btnNuevaCarga').hide();
            }
        });
    }

    // Eventos de filtro
    $('#filtroTipo, #filtroEstado').on('change', function () {
        tablaCargas.ajax.reload();
    });

    $('#btnRecargar').on('click', function () {
        tablaCargas.ajax.reload();
    });

    // Descarga de Plantilla
    $('.btn-descargar-plantilla').on('click', function (e) {
        e.preventDefault();
        const tipo = $(this).data('tipo');
        window.location.href = `${ajaxUrl}?accion=descargar_plantilla&tipo=${tipo}`;
    });

    // Abrir Modal Nueva Carga
    $('#btnNuevaCarga').on('click', function () {
        $('#formNuevaCarga')[0].reset();
        const modal = new bootstrap.Modal(document.getElementById('modalNuevaCarga'));
        modal.show();
    });

    // Subir y Validar Carga
    $('#formNuevaCarga').on('submit', function (e) {
        e.preventDefault();
        const tipoId = $('#modalCargaTipoId').val();
        const archivoInput = document.getElementById('modalArchivo');

        if (!tipoId || !archivoInput.files.length) {
            Swal.fire('Atención', 'Seleccione el tipo y adjunte un archivo CSV.', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('accion', 'subir_validar');
        formData.append('empresa_idx', empresaIdx);
        formData.append('carga_tipo_id', tipoId);
        formData.append('archivo', archivoInput.files[0]);

        const btn = $('#btnProcesarCarga');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Validando archivo...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html('<i class="fas fa-cogs me-1"></i>Subir y Validar');
                if (res.success) {
                    Swal.fire({
                        title: 'Validación Completada',
                        html: `${res.message}<br><br><strong>Filas analizadas:</strong> ${res.filas_totales}<br><strong>Válidas:</strong> <span class="text-success">${res.filas_validas}</span><br><strong>Con error:</strong> <span class="text-danger">${res.filas_con_error}</span>`,
                        icon: res.filas_con_error > 0 ? 'warning' : 'success'
                    });
                    bootstrap.Modal.getInstance(document.getElementById('modalNuevaCarga')).hide();
                    tablaCargas.ajax.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () {
                btn.prop('disabled', false).html('<i class="fas fa-cogs me-1"></i>Subir y Validar');
                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
            }
        });
    });

    // Ver Filas y Diagnóstico
    $(document).on('click', '.btn-ver-filas', function () {
        const cargaId = $(this).data('id');
        $('#tbodyVistaPrevia').html('<tr><td colspan="3" class="text-center py-3"><i class="fas fa-spinner fa-spin me-1"></i>Cargando filas...</td></tr>');
        const modal = new bootstrap.Modal(document.getElementById('modalVistaPrevia'));
        modal.show();

        $.getJSON(ajaxUrl, { accion: 'obtener_filas', empresa_idx: empresaIdx, carga_id: cargaId }, function (res) {
            if (!res.success || !res.filas) {
                $('#tbodyVistaPrevia').html('<tr><td colspan="3" class="text-center text-danger py-3">Error al cargar filas.</td></tr>');
                return;
            }

            let html = '';
            res.filas.forEach(function (f) {
                const tieneError = f.errores && f.errores.trim().length > 0;
                const badgeDiagnostico = tieneError 
                    ? `<span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Error</span> <div class="small text-danger mt-1">${f.errores}</div>`
                    : `<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Correcta</span>`;

                let datosStr = '';
                if (f.datos) {
                    datosStr = Object.entries(f.datos).map(([k, v]) => `<strong>${k}:</strong> ${v}`).join(' | ');
                } else {
                    datosStr = f.contenido;
                }

                html += `
                    <tr class="${tieneError ? 'table-danger' : ''}">
                        <td class="text-muted fw-bold small text-center">#${f.nro_fila}</td>
                        <td class="small font-monospace">${datosStr}</td>
                        <td>${badgeDiagnostico}</td>
                    </tr>
                `;
            });

            $('#tbodyVistaPrevia').html(html || '<tr><td colspan="3" class="text-center text-muted py-3">No hay filas registradas.</td></tr>');
        });
    });

    // Confirmar Carga Masiva (Transacción Atómica)
    $(document).on('click', '.btn-confirmar', function () {
        const cargaId = $(this).data('id');
        const filasOk = $(this).data('ok');

        Swal.fire({
            title: `¿Confirmar Carga #${cargaId}?`,
            html: `Se insertarán definitivamente <strong>${filasOk}</strong> registros válidos en base de datos bajo transacción atómica (rollback en caso de falla).`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Sí, confirmar e insertar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        accion: 'confirmar_carga',
                        empresa_idx: empresaIdx,
                        carga_id: cargaId
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            Swal.fire('Carga Confirmada', res.message, 'success');
                            tablaCargas.ajax.reload();
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

    // Eliminar / Descartar Lote de Carga
    $(document).on('click', '.btn-eliminar', function () {
        const cargaId = $(this).data('id');

        Swal.fire({
            title: `¿Eliminar Carga #${cargaId}?`,
            text: 'Se eliminará el registro de este lote, sus filas analizadas y el archivo temporal asociado.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Sí, eliminar lote',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        accion: 'eliminar_carga',
                        empresa_idx: empresaIdx,
                        carga_id: cargaId
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            Swal.fire('Lote Eliminado', res.message, 'success');
                            tablaCargas.ajax.reload();
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
