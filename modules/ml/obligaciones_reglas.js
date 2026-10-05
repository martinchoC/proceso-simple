/**
 * Lógica JavaScript para Reglas y Periodicidades (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/obligaciones_reglas.js
 */

$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresa_idx = parseInt(urlParams.get('empresa_id') || '2', 10);
    const pagina_idx = 322;

    let tablaPer, tablaVenc, tablaCrit;

    const dtDom = '<"row mb-2"<"col-12 col-md-6"><"col-12 col-md-6 text-end"f>>' +
                  'rt' +
                  '<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>';

    const dtLang = {
        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json',
        lengthMenu: 'Mostrar _MENU_ registros',
        info: 'Página _PAGE_ de _PAGES_',
        infoEmpty: 'Página 0 de 0',
        infoFiltered: '(filtrado de _MAX_ registros)',
        paginate: {
            first: 'Primero',
            previous: 'Anterior',
            next: 'Siguiente',
            last: 'Último'
        }
    };

    // 1. Cargar permisos y botones
    function cargarPermisos() {
        $.getJSON('obligaciones_reglas_ajax.php', { accion: 'permisos', empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            if (res.success && res.permisos) {
                const p = res.permisos;
                if (p.permite_periodicidad) {
                    $('#btn-container-per').html('<button type="button" class="btn btn-sm btn-primary" id="btnNuevaPer"><i class="fas fa-plus me-1"></i>Nueva Periodicidad</button>');
                }
                if (p.permite_regla) {
                    $('#btn-container-venc').html('<button type="button" class="btn btn-sm btn-primary" id="btnNuevaRegla"><i class="fas fa-plus me-1"></i>Nueva Regla</button>');
                }
                if (p.permite_criticidad) {
                    $('#btn-container-crit').html('<button type="button" class="btn btn-sm btn-primary" id="btnNuevaCrit"><i class="fas fa-plus me-1"></i>Nueva Criticidad</button>');
                }
            }
        });
    }

    // 2. DataTables
    function initTablaPeriodicidades() {
        if ($.fn.DataTable.isDataTable('#tablaPeriodicidades')) {
            $('#tablaPeriodicidades').DataTable().destroy();
            $('#tablaPeriodicidades tbody').empty();
        }

        tablaPer = $('#tablaPeriodicidades').DataTable({
            dom: dtDom,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: dtLang,
            responsive: true,
            ajax: {
                url: 'obligaciones_reglas_ajax.php',
                type: 'GET',
                data: { accion: 'listar_periodicidades', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
                dataSrc: 'data'
            },
            columns: [
                { data: 'periodicidad_id', className: 'text-center fw-bold text-muted' },
                { data: 'codigo', render: function (val) { return `<span class="badge bg-dark">${val}</span>`; } },
                { data: 'nombre', render: function (val) { return `<span class="fw-semibold text-primary">${val}</span>`; } },
                {
                    data: 'intervalo_cantidad',
                    className: 'text-center',
                    render: function (val, type, row) {
                        return val ? `<strong>${val}</strong> <small class="text-muted">${row.intervalo_unidad || ''}</small>` : '<span class="text-muted fs-8">-</span>';
                    }
                },
                {
                    data: 'es_evento',
                    className: 'text-center',
                    render: function (val) {
                        return parseInt(val, 10) === 1 ? '<span class="badge bg-danger"><i class="fas fa-bolt me-1"></i>Por Evento (C07)</span>' : '<span class="badge bg-light text-muted border">Regular</span>';
                    }
                },
                {
                    data: 'fecha_fija_autoridad',
                    className: 'text-center',
                    render: function (val) {
                        return parseInt(val, 10) === 1 ? '<span class="badge bg-warning text-dark"><i class="fas fa-gavel me-1"></i>Fijada Autoridad</span>' : '<span class="badge bg-light text-muted border">Calendario</span>';
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function (data, type, row) {
                        return `<button type="button" class="btn btn-outline-primary btn-sm btnEditarPer" data-json='${JSON.stringify(row)}' title="Editar"><i class="fas fa-edit"></i></button>`;
                    }
                }
            ],
            order: [[0, 'asc']]
        });
    }

    function initTablaVencimientos() {
        if ($.fn.DataTable.isDataTable('#tablaVencimientos')) {
            $('#tablaVencimientos').DataTable().destroy();
            $('#tablaVencimientos tbody').empty();
        }

        tablaVenc = $('#tablaVencimientos').DataTable({
            dom: dtDom,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: dtLang,
            responsive: true,
            ajax: {
                url: 'obligaciones_reglas_ajax.php',
                type: 'GET',
                data: { accion: 'listar_reglas', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
                dataSrc: 'data'
            },
            columns: [
                { data: 'vencimiento_regla_id', className: 'text-center fw-bold text-muted' },
                { data: 'codigo', render: function (val) { return `<span class="badge bg-dark">${val}</span>`; } },
                { data: 'nombre', render: function (val) { return `<span class="fw-semibold">${val}</span>`; } },
                { data: 'tipo', render: function (val) { return `<span class="badge bg-info text-dark">${val}</span>`; } },
                {
                    data: 'desplazamiento_dias',
                    className: 'text-center',
                    render: function (val) {
                        const d = parseInt(val, 10);
                        return d !== 0 ? `<span class="badge bg-light text-dark border">${d > 0 ? '+' : ''}${d} días</span>` : '<span class="text-muted fs-8">Mismo día</span>';
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function (data, type, row) {
                        return `<button type="button" class="btn btn-outline-primary btn-sm btnEditarRegla" data-json='${JSON.stringify(row)}' title="Editar"><i class="fas fa-edit"></i></button>`;
                    }
                }
            ],
            order: [[0, 'asc']]
        });
    }

    function initTablaCriticidades() {
        if ($.fn.DataTable.isDataTable('#tablaCriticidades')) {
            $('#tablaCriticidades').DataTable().destroy();
            $('#tablaCriticidades tbody').empty();
        }

        tablaCrit = $('#tablaCriticidades').DataTable({
            dom: dtDom,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: dtLang,
            responsive: true,
            ajax: {
                url: 'obligaciones_reglas_ajax.php',
                type: 'GET',
                data: { accion: 'listar_criticidades', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
                dataSrc: 'data'
            },
            columns: [
                { data: 'criticidad_id', className: 'text-center fw-bold text-muted' },
                { data: 'nombre', render: function (val) { return `<span class="fw-semibold">${val}</span>`; } },
                {
                    data: 'peso',
                    className: 'text-center',
                    render: function (val) {
                        return `<span class="badge bg-warning text-dark fs-7 px-2">Peso: ${val}</span>`;
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function (data, type, row) {
                        return `<button type="button" class="btn btn-outline-primary btn-sm btnEditarCrit" data-json='${JSON.stringify(row)}' title="Editar"><i class="fas fa-edit"></i></button>`;
                    }
                }
            ],
            order: [[2, 'desc']]
        });
    }

    // Pestañas
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        const target = $(e.target).attr('data-bs-target');
        if (target === '#tab-periodicidades' && tablaPer) tablaPer.columns.adjust().responsive.recalc();
        if (target === '#tab-vencimientos' && !tablaVenc) initTablaVencimientos();
        else if (target === '#tab-vencimientos' && tablaVenc) tablaVenc.columns.adjust().responsive.recalc();
        if (target === '#tab-criticidades' && !tablaCrit) initTablaCriticidades();
        else if (target === '#tab-criticidades' && tablaCrit) tablaCrit.columns.adjust().responsive.recalc();
    });

    $('.btnRecargar').on('click', function () {
        const tab = $(this).data('tab');
        if (tab === 'periodicidades' && tablaPer) tablaPer.ajax.reload();
        if (tab === 'vencimientos' && tablaVenc) tablaVenc.ajax.reload();
        if (tab === 'criticidades' && tablaCrit) tablaCrit.ajax.reload();
    });

    // Modales y formularios
    $(document).on('click', '#btnNuevaPer', function () {
        $('#formPeriodicidad')[0].reset();
        $('#per_id').val('0');
        new bootstrap.Modal(document.getElementById('modalPeriodicidad')).show();
    });

    $(document).on('click', '.btnEditarPer', function () {
        const d = $(this).data('json');
        $('#per_id').val(d.periodicidad_id);
        $('#per_codigo').val(d.codigo);
        $('#per_nombre').val(d.nombre);
        $('#per_cantidad').val(d.intervalo_cantidad || '');
        $('#per_unidad').val(d.intervalo_unidad || '');
        $('#per_es_evento').prop('checked', parseInt(d.es_evento, 10) === 1);
        $('#per_fija_autoridad').prop('checked', parseInt(d.fecha_fija_autoridad, 10) === 1);
        new bootstrap.Modal(document.getElementById('modalPeriodicidad')).show();
    });

    $('#formPeriodicidad').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) return;
        const data = $(this).serialize() + '&accion=guardar_periodicidad&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;
        $.post('obligaciones_reglas_ajax.php', data, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Guardado', text: res.message, timer: 1200, showConfirmButton: false });
                bootstrap.Modal.getInstance(document.getElementById('modalPeriodicidad')).hide();
                tablaPer.ajax.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    // Regla Vencimiento
    $(document).on('click', '#btnNuevaRegla', function () {
        $('#formRegla')[0].reset();
        $('#venc_id').val('0');
        new bootstrap.Modal(document.getElementById('modalRegla')).show();
    });

    $(document).on('click', '.btnEditarRegla', function () {
        const d = $(this).data('json');
        $('#venc_id').val(d.vencimiento_regla_id);
        $('#venc_codigo').val(d.codigo);
        $('#venc_nombre').val(d.nombre);
        $('#venc_tipo').val(d.tipo);
        $('#venc_desplazamiento').val(d.desplazamiento_dias || 0);
        new bootstrap.Modal(document.getElementById('modalRegla')).show();
    });

    $('#formRegla').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) return;
        const data = $(this).serialize() + '&accion=guardar_regla&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;
        $.post('obligaciones_reglas_ajax.php', data, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Guardado', text: res.message, timer: 1200, showConfirmButton: false });
                bootstrap.Modal.getInstance(document.getElementById('modalRegla')).hide();
                tablaVenc.ajax.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    // Criticidad
    $(document).on('click', '#btnNuevaCrit', function () {
        $('#formCriticidad')[0].reset();
        $('#crit_id').val('0');
        new bootstrap.Modal(document.getElementById('modalCriticidad')).show();
    });

    $(document).on('click', '.btnEditarCrit', function () {
        const d = $(this).data('json');
        $('#crit_id').val(d.criticidad_id);
        $('#crit_nombre').val(d.nombre);
        $('#crit_peso').val(d.peso);
        new bootstrap.Modal(document.getElementById('modalCriticidad')).show();
    });

    $('#formCriticidad').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) return;
        const data = $(this).serialize() + '&accion=guardar_criticidad&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;
        $.post('obligaciones_reglas_ajax.php', data, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Guardado', text: res.message, timer: 1200, showConfirmButton: false });
                bootstrap.Modal.getInstance(document.getElementById('modalCriticidad')).hide();
                tablaCrit.ajax.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    cargarPermisos();
    initTablaPeriodicidades();
});
