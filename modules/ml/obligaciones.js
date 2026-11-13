/**
 * Lógica JavaScript para Matriz de Obligaciones (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/obligaciones.js
 */

$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresa_idx = parseInt(urlParams.get('empresa_id') || '2', 10);
    const pagina_idx = 323;

    let tablaObl;
    let catalogoEntidadesTipos = [];
    let todasLasObligaciones = [];

    const dtDom = '<"row mb-2"<"col-12 col-md-6"><"col-12 col-md-6 text-end"f>>' +
                  'rt' +
                  '<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>';

    const dtLang = {
        url: window.APP_ASSETS_URL + 'js/datatables-i18n-1.13.6/es-ES.json',
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

    // =========================================================================
    // 1. CARGA DE COMBOS
    // =========================================================================
    function cargarCombos() {
        $.ajax({
            url: 'obligaciones_ajax.php',
            type: 'GET',
            data: { accion: 'combos', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const d = res.data;
                    catalogoEntidadesTipos = d.entidades_tipos || [];

                    // Normas
                    let optNormasFiltro = '<option value="">-- Todas las normas --</option>';
                    let optNormasModal = '<option value="">-- Seleccionar Norma --</option>';
                    if (d.normas) {
                        d.normas.forEach(function (n) {
                            optNormasFiltro += `<option value="${n.norma_id}">[${n.codigo}] ${n.titulo}</option>`;
                            optNormasModal += `<option value="${n.norma_id}">[${n.codigo}] ${n.titulo}</option>`;
                        });
                    }
                    $('#filtroNorma').html(optNormasFiltro);
                    $('#obl_norma_id').html(optNormasModal);

                    // Periodicidades
                    let optPerFiltro = '<option value="">-- Todas --</option>';
                    let optPerModal = '<option value="">-- Seleccionar --</option>';
                    if (d.periodicidades) {
                        d.periodicidades.forEach(function (p) {
                            optPerFiltro += `<option value="${p.periodicidad_id}">${p.nombre}</option>`;
                            optPerModal += `<option value="${p.periodicidad_id}">${p.codigo} - ${p.nombre}</option>`;
                        });
                    }
                    $('#filtroPeriodicidad').html(optPerFiltro);
                    $('#obl_periodicidad_id').html(optPerModal);

                    // Reglas Vencimiento
                    let optReg = '<option value="">-- Seleccionar --</option>';
                    if (d.vencimientos_reglas) {
                        d.vencimientos_reglas.forEach(function (vr) {
                            optReg += `<option value="${vr.vencimiento_regla_id}">[${vr.codigo}] ${vr.nombre}</option>`;
                        });
                    }
                    $('#obl_vencimiento_regla_id').html(optReg);

                    // Criticidades
                    let optCritFiltro = '<option value="">-- Todas --</option>';
                    let optCritModal = '<option value="">-- Sin criticidad asignada --</option>';
                    if (d.criticidades) {
                        d.criticidades.forEach(function (c) {
                            optCritFiltro += `<option value="${c.criticidad_id}">${c.nombre} (P:${c.peso})</option>`;
                            optCritModal += `<option value="${c.criticidad_id}">${c.nombre} (P:${c.peso})</option>`;
                        });
                    }
                    $('#filtroCriticidad').html(optCritFiltro);
                    $('#obl_criticidad_id').html(optCritModal);

                    // Tipos de Entidad modal
                    let optEnt = '<option value="">-- Seleccionar Tipo --</option>';
                    catalogoEntidadesTipos.forEach(function (et) {
                        optEnt += `<option value="${et.entidad_tipo_id}">[${et.codigo}] ${et.nombre}</option>`;
                    });
                    $('#ent_tipo_id').html(optEnt);

                    if (res.permisos && res.permisos.permite_crear) {
                        $('#btn-container-obligacion').html('<button type="button" class="btn btn-sm btn-primary" id="btnNuevaObligacion"><i class="fas fa-plus me-1"></i>Nueva Obligación</button>');
                    }
                }
            }
        });
    }

    // =========================================================================
    // 2. INICIALIZAR DATATABLE DE OBLIGACIONES
    // =========================================================================
    function inicializarDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaObligaciones')) {
            $('#tablaObligaciones').DataTable().destroy();
            $('#tablaObligaciones tbody').empty();
        }

        tablaObl = $('#tablaObligaciones').DataTable({
            dom: dtDom,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: dtLang,
            responsive: true,
            ajax: {
                url: 'obligaciones_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                    d.filtro_norma = $('#filtroNorma').val();
                    d.filtro_periodicidad = $('#filtroPeriodicidad').val();
                    d.filtro_criticidad = $('#filtroCriticidad').val();
                    d.filtro_estado = $('#filtroEstado').val();
                },
                dataSrc: function (json) {
                    todasLasObligaciones = json.data || [];
                    return todasLasObligaciones;
                }
            },
            columns: [
                { data: 'obligacion_id', className: 'text-center fw-bold text-muted' },
                {
                    data: 'codigo',
                    render: function (val, type, row) {
                        let sigla = row.sigla || val;
                        let icono = row.icono || 'fas fa-shield-alt';
                        let color = row.color_hex || '#0284c7';
                        let badgeSigla = `<span class="badge text-white shadow-sm" style="background-color: ${color};"><i class="${icono} me-1"></i>${sigla}</span>`;
                        let art = row.articulo_referencia ? `<small class="badge bg-light text-muted border d-block mt-1">${row.articulo_referencia}</small>` : '';
                        return `<div>${badgeSigla}<span class="small font-monospace text-muted d-block mt-1">${val}</span></div>${art}`;
                    }
                },
                {
                    data: 'descripcion',
                    render: function (val) {
                        return `<span class="text-truncate d-inline-block" style="max-width: 250px;" title="${val}">${val}</span>`;
                    }
                },
                {
                    data: 'norma_codigo',
                    render: function (val, type, row) {
                        return `<span class="fw-semibold">${val}</span><small class="d-block text-truncate text-muted" style="max-width: 180px;">${row.norma_titulo}</small>`;
                    }
                },
                {
                    data: 'periodicidad_nombre',
                    render: function (val, type, row) {
                        return parseInt(row.es_evento, 10) === 1 ? `<span class="badge bg-danger"><i class="fas fa-bolt me-1"></i>${val}</span>` : `<span class="badge bg-light text-dark border">${val}</span>`;
                    }
                },
                {
                    data: 'vencimiento_regla_nombre',
                    render: function (val) {
                        return `<small class="text-secondary"><i class="fas fa-calculator me-1"></i>${val}</small>`;
                    }
                },
                {
                    data: 'criticidad_nombre',
                    className: 'text-center',
                    render: function (val, type, row) {
                        if (!val) return '<span class="text-muted fs-8">-</span>';
                        let p = parseInt(row.criticidad_peso, 10);
                        let flagClass = 'cu-priority-normal';
                        let icon = 'far fa-flag';
                        let valLower = val.toLowerCase();
                        if (p >= 10 || valLower.includes('crítica') || valLower.includes('urgente')) {
                            flagClass = 'cu-priority-urgent';
                            icon = 'fas fa-flag';
                        } else if (p >= 5 || valLower.includes('alta') || valLower.includes('media')) {
                            flagClass = 'cu-priority-high';
                            icon = 'fas fa-flag';
                        } else if (p <= 2 || valLower.includes('baja')) {
                            flagClass = 'cu-priority-low';
                            icon = 'fas fa-flag';
                        }
                        return `<span class="cu-priority-flag ${flagClass}"><i class="${icon}"></i> ${val}</span>`;
                    }
                },
                {
                    data: 'requiere_evidencia',
                    className: 'text-center',
                    render: function (val) {
                        return parseInt(val, 10) === 1 ? '<span class="badge bg-success"><i class="fas fa-file-invoice"></i> Sí</span>' : '<span class="badge bg-light text-muted border">No</span>';
                    }
                },
                {
                    data: 'tabla_estado_registro_id',
                    className: 'text-center',
                    render: function (val, type, row) {
                        const est = parseInt(val, 10);
                        if (est === 2061) return '<span class="badge bg-secondary">Borrador</span>';
                        if (est === 2062) return '<span class="badge bg-warning text-dark">En Revisión</span>';
                        if (est === 2063) return '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Confirmada</span>';
                        if (est === 2064) return '<span class="badge bg-dark">Suspendida</span>';
                        return `<span class="badge bg-light text-dark">${row.estado_registro}</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        let btns = '<div class="btn-group btn-group-sm" role="group">';

                        if (row.permite_editar) {
                            btns += `<button type="button" class="btn btn-outline-primary btn-sm btnEditarObl" data-id="${row.obligacion_id}" title="Editar Obligación"><i class="fas fa-edit"></i></button>`;
                        }

                        if (row.permite_enviar) {
                            btns += `<button type="button" class="btn btn-outline-warning btn-sm btnCambiarEstadoObl" data-id="${row.obligacion_id}" data-estado="2062" title="Enviar a Revisión"><i class="fas fa-paper-plane"></i></button>`;
                        }

                        if (row.permite_aprobar) {
                            btns += `<button type="button" class="btn btn-outline-success btn-sm btnCambiarEstadoObl" data-id="${row.obligacion_id}" data-estado="2063" title="Aprobar Obligación"><i class="fas fa-check"></i></button>`;
                        }

                        if (row.permite_suspender) {
                            btns += `<button type="button" class="btn btn-outline-danger btn-sm btnCambiarEstadoObl" data-id="${row.obligacion_id}" data-estado="2064" title="Suspender / Dar de Baja"><i class="fas fa-ban"></i></button>`;
                        }

                        // Sub-modales
                        btns += `<button type="button" class="btn btn-outline-info btn-sm btnVerTiposEntidad" data-id="${row.obligacion_id}" data-codigo="${row.codigo}" title="Aplicabilidad a Tipos de Entidad (Caso C20)"><i class="fas fa-cubes"></i></button>`;
                        btns += `<button type="button" class="btn btn-outline-dark btn-sm btnVerDependencias" data-id="${row.obligacion_id}" data-codigo="${row.codigo}" title="Dependencias entre Obligaciones (Caso C24)"><i class="fas fa-link"></i></button>`;
                        btns += `<button type="button" class="btn btn-outline-warning btn-sm btnVerAlertas" data-id="${row.obligacion_id}" data-codigo="${row.codigo}" title="Alertas de Vencimiento"><i class="fas fa-bell"></i></button>`;

                        btns += '</div>';
                        return btns;
                    }
                }
            ],
            order: [[0, 'desc']]
        });
    }

    // Filtros
    $('#filtroNorma, #filtroPeriodicidad, #filtroCriticidad, #filtroEstado').on('change', function () {
        if (tablaObl) tablaObl.ajax.reload();
    });

    $('#btnRecargar').on('click', function () {
        if (tablaObl) tablaObl.ajax.reload();
    });

    // =========================================================================
    // 3. ALTA Y EDICIÓN DE OBLIGACIÓN
    // =========================================================================
    $(document).on('click', '#btnNuevaObligacion', function () {
        $('#formObligacion')[0].reset();
        $('#obl_id').val('0');
        $('#obl_codigo').prop('readonly', false);
        $('#obl_norma_id').prop('disabled', false);
        $('#obl_vigente_desde').val(new Date().toISOString().split('T')[0]);
        $('#modalOblLabel').html('<i class="fas fa-clipboard-check me-1"></i>Registrar Obligación');
        new bootstrap.Modal(document.getElementById('modalObligacion')).show();
    });

    $(document).on('click', '.btnEditarObl', function () {
        const id = $(this).data('id');
        $.getJSON('obligaciones_ajax.php', { accion: 'obtener', obligacion_id: id, empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            if (res.success && res.data) {
                const o = res.data;
                $('#obl_id').val(o.obligacion_id);
                $('#obl_norma_id').val(o.norma_id).prop('disabled', true);
                $('#obl_codigo').val(o.codigo).prop('readonly', true);
                $('#obl_articulo').val(o.articulo_referencia || '');
                $('#obl_descripcion').val(o.descripcion);
                $('#obl_periodicidad_id').val(o.periodicidad_id);
                $('#obl_vencimiento_regla_id').val(o.vencimiento_regla_id);
                $('#obl_criticidad_id').val(o.criticidad_id || '');
                $('#obl_vigente_desde').val(o.vigente_desde);
                $('#obl_vigente_hasta').val(o.vigente_hasta || '');
                $('#obl_retencion').val(o.retencion_evidencias_meses || 60);
                $('#obl_requiere_revision').prop('checked', parseInt(o.requiere_revision, 10) === 1);
                $('#obl_requiere_evidencia').prop('checked', parseInt(o.requiere_evidencia, 10) === 1);

                $('#modalOblLabel').html('<i class="fas fa-edit me-1"></i>Editar Obligación');
                new bootstrap.Modal(document.getElementById('modalObligacion')).show();
            }
        });
    });

    $('#formObligacion').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) return;

        const data = $(this).serialize() + '&accion=guardar&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;
        $.post('obligaciones_ajax.php', data, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Guardada', text: res.message, timer: 1500, showConfirmButton: false });
                bootstrap.Modal.getInstance(document.getElementById('modalObligacion')).hide();
                tablaObl.ajax.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    // Cambiar estado obligación
    $(document).on('click', '.btnCambiarEstadoObl', function () {
        const id = $(this).data('id');
        const estado = parseInt($(this).data('estado'), 10);

        $.post('obligaciones_ajax.php', {
            accion: 'cambiar_estado',
            obligacion_id: id,
            nuevo_estado: estado,
            empresa_idx: empresa_idx,
            pagina_idx: pagina_idx
        }, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Estado Actualizado', text: res.message, timer: 1200, showConfirmButton: false });
                tablaObl.ajax.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    // =========================================================================
    // 4. TIPOS DE ENTIDAD (CASO C20)
    // =========================================================================
    $(document).on('click', '.btnVerTiposEntidad', function () {
        const id = $(this).data('id');
        const cod = $(this).data('codigo');
        $('#lblOblCodigoEnt').text(cod);
        $('#ent_obligacion_id').val(id);
        $('#formTipoEntidad')[0].reset();
        cargarTiposEntidad(id);
        new bootstrap.Modal(document.getElementById('modalTiposEntidad')).show();
    });

    function cargarTiposEntidad(oblId) {
        $.getJSON('obligaciones_ajax.php', { accion: 'listar_tipos_entidad', obligacion_id: oblId, empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            if (res.success && res.data) {
                let html = '';
                res.data.forEach(function (t) {
                    html += `<tr>
                        <td><strong>${t.entidad_tipo_nombre}</strong></td>
                        <td><code>${t.regla_json || 'Aplica a todas'}</code></td>
                        <td class="text-center">${t.vigente_desde}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-xs btn-outline-danger btnEliminarTipoEnt" data-id="${t.obligacion_tipo_entidad_id}"><i class="fas fa-trash-alt"></i></button>
                        </td>
                    </tr>`;
                });
                $('#bodyTiposEntidad').html(html || '<tr><td colspan="4" class="text-center text-muted py-2">Sin tipos de entidad asignados aún</td></tr>');
            }
        });
    }

    $('#formTipoEntidad').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) return;
        const data = $(this).serialize() + '&accion=guardar_tipo_entidad&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;
        $.post('obligaciones_ajax.php', data, function (res) {
            if (res.success) {
                $('#ent_tipo_id').val('');
                $('#ent_regla_json').val('');
                cargarTiposEntidad($('#ent_obligacion_id').val());
                tablaObl.ajax.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    $(document).on('click', '.btnEliminarTipoEnt', function () {
        const id = $(this).data('id');
        $.post('obligaciones_ajax.php', { accion: 'eliminar_tipo_entidad', id: id, empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            if (res.success) cargarTiposEntidad($('#ent_obligacion_id').val());
        }, 'json');
    });

    // =========================================================================
    // 5. DEPENDENCIAS ENTRE OBLIGACIONES (CASO C24)
    // =========================================================================
    $(document).on('click', '.btnVerDependencias', function () {
        const id = $(this).data('id');
        const cod = $(this).data('codigo');
        $('#lblOblCodigoDep').text(cod);
        $('#dep_obligacion_id').val(id);
        $('#formDependencia')[0].reset();

        // Opciones predecesoras excluyendo la actual
        let optPred = '<option value="">-- Seleccionar Predecesora --</option>';
        todasLasObligaciones.forEach(function (o) {
            if (parseInt(o.obligacion_id, 10) !== parseInt(id, 10)) {
                optPred += `<option value="${o.obligacion_id}">[${o.codigo}] ${o.descripcion}</option>`;
            }
        });
        $('#dep_predecesora_id').html(optPred);

        cargarDependencias(id);
        new bootstrap.Modal(document.getElementById('modalDependencias')).show();
    });

    function cargarDependencias(oblId) {
        $.getJSON('obligaciones_ajax.php', { accion: 'listar_dependencias', obligacion_id: oblId, empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            if (res.success && res.data) {
                let html = '';
                res.data.forEach(function (d) {
                    html += `<tr>
                        <td><span class="badge bg-dark">${d.predecesora_codigo}</span></td>
                        <td><small>${d.predecesora_descripcion}</small></td>
                        <td class="text-center">${d.vigente_desde}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-xs btn-outline-danger btnEliminarDep" data-id="${d.obligacion_dependencia_id}"><i class="fas fa-trash-alt"></i></button>
                        </td>
                    </tr>`;
                });
                $('#bodyDependencias').html(html || '<tr><td colspan="4" class="text-center text-muted py-2">Sin dependencias configuradas</td></tr>');
            }
        });
    }

    $('#formDependencia').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) return;
        const data = $(this).serialize() + '&accion=guardar_dependencia&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;
        $.post('obligaciones_ajax.php', data, function (res) {
            if (res.success) {
                $('#dep_predecesora_id').val('');
                cargarDependencias($('#dep_obligacion_id').val());
                tablaObl.ajax.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    $(document).on('click', '.btnEliminarDep', function () {
        const id = $(this).data('id');
        $.post('obligaciones_ajax.php', { accion: 'eliminar_dependencia', id: id, empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            if (res.success) cargarDependencias($('#dep_obligacion_id').val());
        }, 'json');
    });

    // =========================================================================
    // 6. ALERTAS ANTICIPADAS
    // =========================================================================
    $(document).on('click', '.btnVerAlertas', function () {
        const id = $(this).data('id');
        const cod = $(this).data('codigo');
        $('#lblOblCodigoAle').text(cod);
        $('#ale_obligacion_id').val(id);
        $('#formAlerta')[0].reset();
        $('#ale_dias').val('15');
        cargarAlertas(id);
        new bootstrap.Modal(document.getElementById('modalAlertas')).show();
    });

    function cargarAlertas(oblId) {
        $.getJSON('obligaciones_ajax.php', { accion: 'listar_alertas', obligacion_id: oblId, empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            if (res.success && res.data) {
                let html = '';
                res.data.forEach(function (a) {
                    html += `<tr>
                        <td class="text-center"><span class="badge bg-warning text-dark">${a.dias_antes} días antes</span></td>
                        <td>Rol: <strong>${a.destinatario}</strong> <small class="text-muted">(${a.canal})</small></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-xs btn-outline-danger btnEliminarAlerta" data-id="${a.obligacion_alerta_id}"><i class="fas fa-trash-alt"></i></button>
                        </td>
                    </tr>`;
                });
                $('#bodyAlertas').html(html || '<tr><td colspan="3" class="text-center text-muted py-2">Sin alertas programadas</td></tr>');
            }
        });
    }

    $('#formAlerta').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) return;
        const data = $(this).serialize() + '&accion=guardar_alerta&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;
        $.post('obligaciones_ajax.php', data, function (res) {
            if (res.success) {
                $('#ale_dias').val('15');
                cargarAlertas($('#ale_obligacion_id').val());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    $(document).on('click', '.btnEliminarAlerta', function () {
        const id = $(this).data('id');
        $.post('obligaciones_ajax.php', { accion: 'eliminar_alerta', id: id, empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            if (res.success) cargarAlertas($('#ale_obligacion_id').val());
        }, 'json');
    });

    // =========================================================================
    // 7. INICIALIZACIÓN
    // =========================================================================
    cargarCombos();
    inicializarDataTable();
});
