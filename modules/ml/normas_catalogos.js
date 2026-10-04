/**
 * Lógica JavaScript para Catálogos Normativos (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/normas_catalogos.js
 */

$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresa_idx = parseInt(urlParams.get('empresa_id') || '2', 10);
    const pagina_idx = 312;

    const dtDom = '<"row mb-2"<"col-12 col-md-6"><"col-12 col-md-6 text-end"f>>' +
                  'rt' +
                  '<"row mt-3 align-items-center"<"col-12 col-md-6 d-flex flex-wrap align-items-center gap-3"li><"col-12 col-md-6 d-flex justify-content-md-end justify-content-center"p>>';

    const dtLang = { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' };

    let tablaOrg, tablaTipos, tablaJer, tablaRel;

    // =========================================================================
    // 1. CARGA DE COMBOS AUXILIARES
    // =========================================================================
    function cargarCombosAuxiliares() {
        $.ajax({
            url: 'normas_catalogos_ajax.php',
            type: 'GET',
            data: { accion: 'combos_auxiliares', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const d = res.data;
                    let optFiltro = '<option value="">-- Todos los territorios / Jurisdicción --</option>';
                    let optModal = '<option value="">-- Internacional / Sin territorio único --</option>';

                    if (d.territorios) {
                        d.territorios.forEach(function (t) {
                            optFiltro += `<option value="${t.territorio_id}">[${t.tipo_nombre}] ${t.nombre}</option>`;
                            optModal += `<option value="${t.territorio_id}">[${t.tipo_nombre}] ${t.nombre}</option>`;
                        });
                    }
                    $('#filtroOrganismoTerritorio').html(optFiltro);
                    $('#org_territorio_id').html(optModal);

                    if (d.permite_organismo) {
                        $('#btn-container-organismo').html(`
                            <button type="button" class="btn btn-sm btn-primary" id="btnNuevoOrganismo">
                                <i class="fas fa-plus me-1"></i>Nuevo Organismo
                            </button>
                        `);
                    }

                    if (d.permite_norma_tipo) {
                        $('#btn-container-tipo').html(`
                            <button type="button" class="btn btn-sm btn-primary" id="btnNuevoTipo">
                                <i class="fas fa-plus me-1"></i>Nuevo Tipo
                            </button>
                        `);
                    }

                    if (d.permite_jerarquia) {
                        $('#btn-container-jerarquia').html(`
                            <button type="button" class="btn btn-sm btn-primary" id="btnNuevaJerarquia">
                                <i class="fas fa-plus me-1"></i>Nueva Jerarquía
                            </button>
                        `);
                    }
                }
            }
        });
    }

    // =========================================================================
    // 2. TABLAS DATATABLES
    // =========================================================================

    // Organismos
    function initTablaOrganismos() {
        if ($.fn.DataTable.isDataTable('#tablaOrganismos')) {
            $('#tablaOrganismos').DataTable().destroy();
            $('#tablaOrganismos tbody').empty();
        }

        tablaOrg = $('#tablaOrganismos').DataTable({
            dom: dtDom,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: dtLang,
            responsive: true,
            ajax: {
                url: 'normas_catalogos_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar_organismos';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                    d.filtro_territorio = $('#filtroOrganismoTerritorio').val();
                    d.filtro_estado = $('#filtroOrganismoEstado').val();
                },
                dataSrc: 'data'
            },
            columns: [
                { data: 'organismo_id', className: 'text-center fw-bold text-muted' },
                {
                    data: 'nombre',
                    render: function (val) {
                        return `<span class="fw-semibold text-primary"><i class="fas fa-university me-1"></i>${val}</span>`;
                    }
                },
                {
                    data: 'territorio_nombre',
                    render: function (val) {
                        return `<span class="badge bg-light text-dark border">${val}</span>`;
                    }
                },
                {
                    data: 'tabla_estado_registro_id',
                    className: 'text-center',
                    render: function (val) {
                        return parseInt(val, 10) === 1 
                            ? '<span class="badge bg-success">Activo</span>' 
                            : '<span class="badge bg-secondary">Inactivo</span>';
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function (data, type, row) {
                        return `<div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-primary btn-sm btnEditarOrganismo" data-json='${JSON.stringify(row)}' title="Editar Organismo">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>`;
                    }
                }
            ],
            order: [[1, 'asc']]
        });
    }

    // Tipos de Normas
    function initTablaTipos() {
        if ($.fn.DataTable.isDataTable('#tablaTipos')) {
            $('#tablaTipos').DataTable().destroy();
            $('#tablaTipos tbody').empty();
        }

        tablaTipos = $('#tablaTipos').DataTable({
            dom: dtDom,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: dtLang,
            responsive: true,
            ajax: {
                url: 'normas_catalogos_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar_normas_tipos';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                    d.filtro_estado = $('#filtroTipoEstado').val();
                },
                dataSrc: 'data'
            },
            columns: [
                { data: 'norma_tipo_id', className: 'text-center fw-bold text-muted' },
                {
                    data: 'codigo',
                    render: function (val) {
                        return `<span class="badge bg-dark">${val}</span>`;
                    }
                },
                {
                    data: 'nombre',
                    render: function (val) {
                        return `<span class="fw-semibold">${val}</span>`;
                    }
                },
                {
                    data: 'es_legal',
                    className: 'text-center',
                    render: function (val) {
                        return parseInt(val, 10) === 1 
                            ? '<span class="badge bg-primary"><i class="fas fa-gavel me-1"></i>Legal</span>' 
                            : '<span class="badge bg-secondary">Corporativa</span>';
                    }
                },
                {
                    data: 'genera_obligaciones',
                    className: 'text-center',
                    render: function (val) {
                        return parseInt(val, 10) === 1 
                            ? '<span class="badge bg-info text-dark"><i class="fas fa-tasks me-1"></i>Sí genera</span>' 
                            : '<span class="badge bg-light text-muted border">No</span>';
                    }
                },
                {
                    data: 'tabla_estado_registro_id',
                    className: 'text-center',
                    render: function (val) {
                        return parseInt(val, 10) === 1 
                            ? '<span class="badge bg-success">Activo</span>' 
                            : '<span class="badge bg-secondary">Inactivo</span>';
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function (data, type, row) {
                        return `<div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-primary btn-sm btnEditarTipo" data-json='${JSON.stringify(row)}' title="Editar Tipo">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>`;
                    }
                }
            ],
            order: [[0, 'asc']]
        });
    }

    // Jerarquías
    function initTablaJerarquias() {
        if ($.fn.DataTable.isDataTable('#tablaJerarquias')) {
            $('#tablaJerarquias').DataTable().destroy();
            $('#tablaJerarquias tbody').empty();
        }

        tablaJer = $('#tablaJerarquias').DataTable({
            dom: dtDom,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: dtLang,
            responsive: true,
            ajax: {
                url: 'normas_catalogos_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar_jerarquias';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                    d.filtro_estado = $('#filtroJerarquiaEstado').val();
                },
                dataSrc: 'data'
            },
            columns: [
                { data: 'norma_jerarquia_id', className: 'text-center fw-bold text-muted' },
                {
                    data: 'nivel',
                    className: 'text-center',
                    render: function (val) {
                        return `<span class="badge bg-warning text-dark fs-7 px-2">Nivel ${val}</span>`;
                    }
                },
                {
                    data: 'nombre',
                    render: function (val) {
                        return `<span class="fw-semibold">${val}</span>`;
                    }
                },
                {
                    data: 'tabla_estado_registro_id',
                    className: 'text-center',
                    render: function (val) {
                        return parseInt(val, 10) === 1 
                            ? '<span class="badge bg-success">Activo</span>' 
                            : '<span class="badge bg-secondary">Inactivo</span>';
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function (data, type, row) {
                        return `<div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-primary btn-sm btnEditarJerarquia" data-json='${JSON.stringify(row)}' title="Editar Jerarquía">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>`;
                    }
                }
            ],
            order: [[1, 'asc']]
        });
    }

    // Tipos de Relaciones
    function initTablaRelaciones() {
        if ($.fn.DataTable.isDataTable('#tablaRelacionesTipos')) {
            $('#tablaRelacionesTipos').DataTable().destroy();
            $('#tablaRelacionesTipos tbody').empty();
        }

        tablaRel = $('#tablaRelacionesTipos').DataTable({
            dom: dtDom,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: dtLang,
            responsive: true,
            ajax: {
                url: 'normas_catalogos_ajax.php',
                type: 'GET',
                data: {
                    accion: 'listar_relaciones_tipos',
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                },
                dataSrc: 'data'
            },
            columns: [
                { data: 'norma_relacion_tipo_id', className: 'text-center fw-bold text-muted' },
                {
                    data: 'codigo',
                    render: function (val) {
                        return `<span class="badge bg-info text-dark">${val}</span>`;
                    }
                },
                {
                    data: 'nombre',
                    render: function (val) {
                        return `<span class="fw-semibold text-secondary">${val}</span>`;
                    }
                },
                {
                    data: 'tabla_estado_registro_id',
                    className: 'text-center',
                    render: function () {
                        return '<span class="badge bg-success">Activo</span>';
                    }
                }
            ],
            order: [[0, 'asc']]
        });
    }

    // =========================================================================
    // 3. CAMBIO DE PESTAÑAS Y RECARGA
    // =========================================================================
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        const target = $(e.target).attr('data-bs-target');
        if (target === '#tab-organismos' && tablaOrg) tablaOrg.columns.adjust().responsive.recalc();
        if (target === '#tab-tipos' && !tablaTipos) initTablaTipos();
        else if (target === '#tab-tipos' && tablaTipos) tablaTipos.columns.adjust().responsive.recalc();
        if (target === '#tab-jerarquias' && !tablaJer) initTablaJerarquias();
        else if (target === '#tab-jerarquias' && tablaJer) tablaJer.columns.adjust().responsive.recalc();
        if (target === '#tab-relaciones' && !tablaRel) initTablaRelaciones();
        else if (target === '#tab-relaciones' && tablaRel) tablaRel.columns.adjust().responsive.recalc();
    });

    $('.btnRecargarTab').on('click', function () {
        const tab = $(this).data('tab');
        if (tab === 'organismos' && tablaOrg) tablaOrg.ajax.reload();
        if (tab === 'tipos' && tablaTipos) tablaTipos.ajax.reload();
        if (tab === 'jerarquias' && tablaJer) tablaJer.ajax.reload();
    });

    $('#filtroOrganismoTerritorio, #filtroOrganismoEstado').on('change', function () {
        if (tablaOrg) tablaOrg.ajax.reload();
    });
    $('#filtroTipoEstado').on('change', function () {
        if (tablaTipos) tablaTipos.ajax.reload();
    });
    $('#filtroJerarquiaEstado').on('change', function () {
        if (tablaJer) tablaJer.ajax.reload();
    });

    // =========================================================================
    // 4. MODALES: ALTA Y EDICIÓN
    // =========================================================================

    // Organismos
    $(document).on('click', '#btnNuevoOrganismo', function () {
        $('#formOrganismo')[0].reset();
        $('#org_id').val('0');
        $('#modalOrgTitle').html('<i class="fas fa-university me-1"></i>Nuevo Organismo Emisor');
        new bootstrap.Modal(document.getElementById('modalOrganismo')).show();
    });

    $(document).on('click', '.btnEditarOrganismo', function () {
        const d = $(this).data('json');
        $('#org_id').val(d.organismo_id);
        $('#org_nombre').val(d.nombre);
        $('#org_territorio_id').val(d.territorio_id || '');
        $('#org_estado').val(d.tabla_estado_registro_id);
        $('#modalOrgTitle').html('<i class="fas fa-edit me-1"></i>Editar Organismo Emisor');
        new bootstrap.Modal(document.getElementById('modalOrganismo')).show();
    });

    $('#formOrganismo').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) return;

        const data = $(this).serialize() + '&accion=guardar_organismo&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;
        $.post('normas_catalogos_ajax.php', data, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Guardado', text: res.message, timer: 1200, showConfirmButton: false });
                bootstrap.Modal.getInstance(document.getElementById('modalOrganismo')).hide();
                tablaOrg.ajax.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    // Tipos de Normas
    $(document).on('click', '#btnNuevoTipo', function () {
        $('#formTipoNorma')[0].reset();
        $('#tipo_id').val('0');
        $('#tipo_genera_obligaciones').prop('checked', true);
        $('#modalTipoTitle').html('<i class="fas fa-tag me-1"></i>Nuevo Tipo de Norma');
        new bootstrap.Modal(document.getElementById('modalTipoNorma')).show();
    });

    $(document).on('click', '.btnEditarTipo', function () {
        const d = $(this).data('json');
        $('#tipo_id').val(d.norma_tipo_id);
        $('#tipo_codigo').val(d.codigo);
        $('#tipo_nombre').val(d.nombre);
        $('#tipo_es_legal').prop('checked', parseInt(d.es_legal, 10) === 1);
        $('#tipo_genera_obligaciones').prop('checked', parseInt(d.genera_obligaciones, 10) === 1);
        $('#tipo_estado').val(d.tabla_estado_registro_id);
        $('#modalTipoTitle').html('<i class="fas fa-edit me-1"></i>Editar Tipo de Norma');
        new bootstrap.Modal(document.getElementById('modalTipoNorma')).show();
    });

    $('#formTipoNorma').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) return;

        const data = $(this).serialize() + '&accion=guardar_norma_tipo&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;
        $.post('normas_catalogos_ajax.php', data, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Guardado', text: res.message, timer: 1200, showConfirmButton: false });
                bootstrap.Modal.getInstance(document.getElementById('modalTipoNorma')).hide();
                tablaTipos.ajax.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    // Jerarquías
    $(document).on('click', '#btnNuevaJerarquia', function () {
        $('#formJerarquia')[0].reset();
        $('#jer_id').val('0');
        $('#modalJerTitle').html('<i class="fas fa-layer-group me-1"></i>Nueva Jerarquía');
        new bootstrap.Modal(document.getElementById('modalJerarquia')).show();
    });

    $(document).on('click', '.btnEditarJerarquia', function () {
        const d = $(this).data('json');
        $('#jer_id').val(d.norma_jerarquia_id);
        $('#jer_nivel').val(d.nivel);
        $('#jer_nombre').val(d.nombre);
        $('#jer_estado').val(d.tabla_estado_registro_id);
        $('#modalJerTitle').html('<i class="fas fa-edit me-1"></i>Editar Jerarquía');
        new bootstrap.Modal(document.getElementById('modalJerarquia')).show();
    });

    $('#formJerarquia').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) return;

        const data = $(this).serialize() + '&accion=guardar_jerarquia&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;
        $.post('normas_catalogos_ajax.php', data, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Guardado', text: res.message, timer: 1200, showConfirmButton: false });
                bootstrap.Modal.getInstance(document.getElementById('modalJerarquia')).hide();
                tablaJer.ajax.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    // =========================================================================
    // 5. INICIALIZACIÓN
    // =========================================================================
    cargarCombosAuxiliares();
    initTablaOrganismos();
});
