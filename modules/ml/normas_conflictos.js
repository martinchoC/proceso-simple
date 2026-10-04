/**
 * Lógica JavaScript para Conflictos Normativos (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/normas_conflictos.js
 */

$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresa_idx = parseInt(urlParams.get('empresa_id') || '2', 10);
    const pagina_idx = 314;

    let tablaConflictos;

    const dtDom = '<"row mb-2"<"col-12 col-md-6"><"col-12 col-md-6 text-end"f>>' +
                  'rt' +
                  '<"row mt-3 align-items-center"<"col-12 col-md-6 d-flex flex-wrap align-items-center gap-3"li><"col-12 col-md-6 d-flex justify-content-md-end justify-content-center"p>>';

    const dtLang = { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' };

    // =========================================================================
    // 1. CARGA DE COMBOS (ABOGADOS Y NORMAS)
    // =========================================================================
    function cargarCombos() {
        $.ajax({
            url: 'normas_conflictos_ajax.php',
            type: 'GET',
            data: { accion: 'combos', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const d = res.data;

                    // Combo Abogados
                    let optAbogados = '<option value="">-- Seleccionar Abogado / Especialista --</option>';
                    if (d.abogados) {
                        d.abogados.forEach(function (u) {
                            optAbogados += `<option value="${u.usuario_id}">${u.usuario}</option>`;
                        });
                    }
                    $('#conf_escalar_a').html('<option value="">-- Dejar abierto sin asignación aún --</option>' + optAbogados);
                    $('#esc_abogado_id').html(optAbogados);

                    // Combo Normas
                    let optNormas = '<option value="">-- Seleccionar Norma --</option>';
                    if (d.normas) {
                        d.normas.forEach(function (n) {
                            optNormas += `<option value="${n.norma_id}">[${n.codigo}] ${n.titulo}</option>`;
                        });
                    }
                    $('#conf_norma_a').html(optNormas);
                    $('#conf_norma_b').html(optNormas);

                    if (d.permite_registrar) {
                        $('#btn-container-conflicto').html(`
                            <button type="button" class="btn btn-sm btn-warning" id="btnNuevoConflicto">
                                <i class="fas fa-exclamation-triangle me-1"></i>Declarar Incompatibilidad
                            </button>
                        `);
                    }
                }
            }
        });
    }

    // =========================================================================
    // 2. INICIALIZAR DATATABLE DE CONFLICTOS
    // =========================================================================
    function inicializarDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaConflictos')) {
            $('#tablaConflictos').DataTable().destroy();
            $('#tablaConflictos tbody').empty();
        }

        tablaConflictos = $('#tablaConflictos').DataTable({
            dom: dtDom,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: dtLang,
            responsive: true,
            ajax: {
                url: 'normas_conflictos_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                    d.filtro_estado = $('#filtroEstado').val();
                },
                dataSrc: 'data'
            },
            columns: [
                { data: 'norma_conflicto_id', className: 'text-center fw-bold text-muted' },
                {
                    data: 'norma_a_codigo',
                    render: function (val, type, row) {
                        return `<span class="badge bg-danger me-1">A</span><strong class="text-primary">${val}</strong><small class="d-block text-truncate" style="max-width: 200px;">${row.norma_a_titulo}</small>`;
                    }
                },
                {
                    data: 'norma_b_codigo',
                    render: function (val, type, row) {
                        return `<span class="badge bg-dark me-1">B</span><strong class="text-danger">${val}</strong><small class="d-block text-truncate" style="max-width: 200px;">${row.norma_b_titulo}</small>`;
                    }
                },
                {
                    data: 'descripcion',
                    render: function (val) {
                        return `<span class="text-truncate d-inline-block" style="max-width: 260px;" title="${val}">${val}</span>`;
                    }
                },
                {
                    data: 'escalado_a_login',
                    render: function (val) {
                        return val ? `<span class="badge bg-info text-dark"><i class="fas fa-user-tie me-1"></i>${val}</span>` : '<span class="text-muted fst-italic fs-8">Sin escalar</span>';
                    }
                },
                {
                    data: 'decision',
                    render: function (val, type, row) {
                        if (val) {
                            return `<div><span class="text-success fw-semibold"><i class="fas fa-gavel me-1"></i>Dictamen:</span> <small title="${val}">${val}</small><small class="text-muted d-block fs-8">Por: ${row.decidido_por_login} (${row.decidido_en})</small></div>`;
                        }
                        return '<span class="text-muted fst-italic fs-8">Pendiente de dictamen</span>';
                    }
                },
                {
                    data: 'tabla_estado_registro_id',
                    className: 'text-center',
                    render: function (val, type, row) {
                        const est = parseInt(val, 10);
                        if (est === 2084) return '<span class="badge bg-warning text-dark"><i class="fas fa-exclamation-circle me-1"></i>Abierto</span>';
                        if (est === 2085) return '<span class="badge bg-primary"><i class="fas fa-user-clock me-1"></i>En Legales</span>';
                        if (est === 2086) return '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Resuelto</span>';
                        return `<span class="badge bg-secondary">${row.estado_registro}</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        let btns = '<div class="btn-group btn-group-sm" role="group">';

                        if (row.permite_escalar) {
                            btns += `<button type="button" class="btn btn-outline-primary btn-sm btnEscalar" data-id="${row.norma_conflicto_id}" title="Escalar a Abogado"><i class="fas fa-user-plus"></i></button>`;
                        }

                        if (row.permite_resolver) {
                            btns += `<button type="button" class="btn btn-outline-success btn-sm btnResolver" data-id="${row.norma_conflicto_id}" title="Emitir Dictamen Legal (Caso C19)"><i class="fas fa-gavel"></i></button>`;
                        }

                        btns += '</div>';
                        return btns;
                    }
                }
            ],
            order: [[0, 'desc']]
        });
    }

    $('#filtroEstado').on('change', function () {
        if (tablaConflictos) tablaConflictos.ajax.reload();
    });

    $('#btnRecargar').on('click', function () {
        if (tablaConflictos) tablaConflictos.ajax.reload();
    });

    // =========================================================================
    // 3. REGISTRAR CONFLICTO
    // =========================================================================
    $(document).on('click', '#btnNuevoConflicto', function () {
        $('#formConflicto')[0].reset();
        new bootstrap.Modal(document.getElementById('modalConflicto')).show();
    });

    $('#formConflicto').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) return;

        const normaA = $('#conf_norma_a').val();
        const normaB = $('#conf_norma_b').val();

        if (normaA === normaB) {
            Swal.fire({ icon: 'warning', title: 'Normas idénticas', text: 'Debe seleccionar dos normas distintas para establecer un conflicto.' });
            return;
        }

        const data = $(this).serialize() + '&accion=guardar&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;
        $.post('normas_conflictos_ajax.php', data, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Registrado', text: res.message, timer: 1500, showConfirmButton: false });
                bootstrap.Modal.getInstance(document.getElementById('modalConflicto')).hide();
                tablaConflictos.ajax.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    // =========================================================================
    // 4. ESCALAR A LEGALES
    // =========================================================================
    $(document).on('click', '.btnEscalar', function () {
        const id = $(this).data('id');
        $('#esc_conflicto_id').val(id);
        new bootstrap.Modal(document.getElementById('modalEscalar')).show();
    });

    $('#formEscalar').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) return;

        const data = $(this).serialize() + '&accion=escalar&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;
        $.post('normas_conflictos_ajax.php', data, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Escalado', text: res.message, timer: 1200, showConfirmButton: false });
                bootstrap.Modal.getInstance(document.getElementById('modalEscalar')).hide();
                tablaConflictos.ajax.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    // =========================================================================
    // 5. RESOLVER CONFLICTO (DICTAMEN LEGAL)
    // =========================================================================
    $(document).on('click', '.btnResolver', function () {
        const id = $(this).data('id');
        $('#res_conflicto_id').val(id);
        $('#res_decision').val('');
        new bootstrap.Modal(document.getElementById('modalResolver')).show();
    });

    $('#formResolver').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) return;

        const data = $(this).serialize() + '&accion=resolver&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;
        $.post('normas_conflictos_ajax.php', data, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Dictamen Registrado', text: res.message, timer: 1500, showConfirmButton: false });
                bootstrap.Modal.getInstance(document.getElementById('modalResolver')).hide();
                tablaConflictos.ajax.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    // =========================================================================
    // 6. INICIALIZACIÓN
    // =========================================================================
    cargarCombos();
    inicializarDataTable();
});
