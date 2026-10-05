/**
 * Lógica JavaScript para Procedimientos y Soporte (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/procedimientos.js
 */

$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresa_idx = parseInt(urlParams.get('empresa_id') || '2', 10);
    const pagina_idx = 315;

    let tablaProc;
    let versionActualId = 0;

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

    // =========================================================================
    // 1. CARGA DE COMBOS (EMPRESAS Y NORMAS)
    // =========================================================================
    function cargarCombos() {
        $.ajax({
            url: 'procedimientos_ajax.php',
            type: 'GET',
            data: { accion: 'combos', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const d = res.data;

                    // Empresas
                    let optEmp = '<option value="">-- Aplica a todo el Grupo --</option>';
                    if (d.empresas) {
                        d.empresas.forEach(function (e) {
                            optEmp += `<option value="${e.empresa_id}">${e.empresa}</option>`;
                        });
                    }
                    $('#proc_empresa_id').html(optEmp);

                    // Normas
                    let optNormas = '<option value="">-- Sin norma asociada (Soporte interno) --</option>';
                    if (d.normas) {
                        d.normas.forEach(function (n) {
                            optNormas += `<option value="${n.norma_id}">[${n.codigo}] ${n.titulo}</option>`;
                        });
                    }
                    $('#proc_norma_id').html(optNormas);

                    if (d.permite_guardar) {
                        $('#btn-container-procedimiento').html(`
                            <button type="button" class="btn btn-sm btn-primary" id="btnNuevoProcedimiento">
                                <i class="fas fa-plus me-1"></i>Nuevo Procedimiento
                            </button>
                        `);
                    }
                }
            }
        });
    }

    // =========================================================================
    // 2. INICIALIZAR DATATABLE DE PROCEDIMIENTOS
    // =========================================================================
    function inicializarDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaProcedimientos')) {
            $('#tablaProcedimientos').DataTable().destroy();
            $('#tablaProcedimientos tbody').empty();
        }

        tablaProc = $('#tablaProcedimientos').DataTable({
            dom: dtDom,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: dtLang,
            responsive: true,
            ajax: {
                url: 'procedimientos_ajax.php',
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
                { data: 'procedimiento_id', className: 'text-center fw-bold text-muted' },
                {
                    data: 'codigo',
                    render: function (val) {
                        return `<strong class="text-primary">${val}</strong>`;
                    }
                },
                {
                    data: 'nombre',
                    render: function (val, type, row) {
                        let desc = row.descripcion ? `<small class="text-muted d-block text-truncate" style="max-width: 250px;">${row.descripcion}</small>` : '';
                        return `<div><span class="fw-semibold">${val}</span>${desc}</div>`;
                    }
                },
                {
                    data: 'empresa_nombre',
                    render: function (val) {
                        return val ? `<span class="badge bg-light text-dark border">${val}</span>` : '<span class="badge bg-secondary">Todo el Grupo</span>';
                    }
                },
                {
                    data: 'norma_codigo',
                    render: function (val, type, row) {
                        return val ? `<small><i class="fas fa-balance-scale me-1 text-primary"></i><strong>${val}</strong></small>` : '<span class="text-muted fs-8">Soporte general</span>';
                    }
                },
                {
                    data: 'version_vigente',
                    className: 'text-center',
                    render: function (val, type, row) {
                        return val ? `<span class="badge bg-success">v${val}</span>` : `<span class="badge bg-secondary">${row.total_versiones} ver.</span>`;
                    }
                },
                {
                    data: 'confidencial',
                    className: 'text-center',
                    render: function (val) {
                        return parseInt(val, 10) === 1 ? '<span class="badge bg-danger"><i class="fas fa-lock"></i> Sí</span>' : '<span class="badge bg-light text-muted border">No</span>';
                    }
                },
                {
                    data: 'tabla_estado_registro_id',
                    className: 'text-center',
                    render: function (val, type, row) {
                        const est = parseInt(val, 10);
                        if (est === 2053) return '<span class="badge bg-secondary">Borrador</span>';
                        if (est === 2054) return '<span class="badge bg-warning text-dark">En Aprobación</span>';
                        if (est === 2055) return '<span class="badge bg-success">Confirmado</span>';
                        if (est === 2056) return '<span class="badge bg-dark">Obsoleto</span>';
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

                        if (row.permite_enviar) {
                            btns += `<button type="button" class="btn btn-outline-warning btn-sm btnCambiarEstadoProc" data-id="${row.procedimiento_id}" data-estado="2054" title="Enviar a Aprobación"><i class="fas fa-paper-plane"></i></button>`;
                        }

                        if (row.permite_aprobar) {
                            btns += `<button type="button" class="btn btn-outline-success btn-sm btnCambiarEstadoProc" data-id="${row.procedimiento_id}" data-estado="2055" title="Aprobar Procedimiento"><i class="fas fa-check"></i></button>`;
                        }

                        btns += `<button type="button" class="btn btn-outline-info btn-sm btnVerVersionesProc" data-id="${row.procedimiento_id}" data-codigo="${row.codigo}" title="Versiones e Instrucciones Paso a Paso"><i class="fas fa-tasks"></i></button>`;

                        btns += '</div>';
                        return btns;
                    }
                }
            ],
            order: [[0, 'desc']]
        });
    }

    $('#filtroEstado').on('change', function () {
        if (tablaProc) tablaProc.ajax.reload();
    });

    $('#btnRecargar').on('click', function () {
        if (tablaProc) tablaProc.ajax.reload();
    });

    // =========================================================================
    // 3. ALTA DE PROCEDIMIENTO
    // =========================================================================
    $(document).on('click', '#btnNuevoProcedimiento', function () {
        $('#formProcedimiento')[0].reset();
        $('#proc_id').val('0');
        new bootstrap.Modal(document.getElementById('modalProcedimiento')).show();
    });

    $('#formProcedimiento').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) return;

        const data = $(this).serialize() + '&accion=guardar&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;
        $.post('procedimientos_ajax.php', data, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Guardado', text: res.message, timer: 1500, showConfirmButton: false });
                bootstrap.Modal.getInstance(document.getElementById('modalProcedimiento')).hide();
                tablaProc.ajax.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    // Cambiar estado procedimiento
    $(document).on('click', '.btnCambiarEstadoProc', function () {
        const id = $(this).data('id');
        const estado = $(this).data('estado');

        $.post('procedimientos_ajax.php', {
            accion: 'cambiar_estado',
            procedimiento_id: id,
            nuevo_estado: estado,
            empresa_idx: empresa_idx,
            pagina_idx: pagina_idx
        }, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Actualizado', text: res.message, timer: 1200, showConfirmButton: false });
                tablaProc.ajax.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    // =========================================================================
    // 4. GESTIÓN DE VERSIONES E INSTRUCCIONES PASO A PASO
    // =========================================================================
    $(document).on('click', '.btnVerVersionesProc', function () {
        const id = $(this).data('id');
        const codigo = $(this).data('codigo');

        $('#lblProcCodigo').text(codigo);
        $('#verproc_id').val(id);
        $('#verproc_desde').val(new Date().toISOString().split('T')[0]);
        $('#formNuevaInstruccion').hide();
        $('#bodyInstrucciones').html('<tr><td colspan="3" class="text-center text-muted py-2">Seleccione una versión a la izquierda</td></tr>');
        $('#lblVersionSeleccionada').text('-');

        cargarVersionesProc(id);
        new bootstrap.Modal(document.getElementById('modalVersionesProc')).show();
    });

    function cargarVersionesProc(procId) {
        $.getJSON('procedimientos_ajax.php', { accion: 'listar_versiones', procedimiento_id: procId, empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            if (res.success && res.data) {
                let html = '';
                res.data.forEach(function (v) {
                    html += `<tr>
                        <td><strong>v${v.version}</strong></td>
                        <td><small>${v.vigente_desde}</small></td>
                        <td><span class="badge bg-secondary">${v.estado_registro}</span></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-xs btn-outline-primary btnSelVersion" data-id="${v.procedimiento_version_id}" data-version="${v.version}">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </td>
                    </tr>`;
                });
                $('#bodyVersionesProc').html(html || '<tr><td colspan="4" class="text-center text-muted py-2">Sin versiones</td></tr>');
            }
        });
    }

    // Seleccionar versión para ver instrucciones
    $(document).on('click', '.btnSelVersion', function () {
        versionActualId = $(this).data('id');
        const verStr = $(this).data('version');

        $('#lblVersionSeleccionada').text('v' + verStr).removeClass('bg-secondary').addClass('bg-success');
        $('#ins_version_id').val(versionActualId);
        $('#formNuevaInstruccion').show();

        cargarInstrucciones(versionActualId);
    });

    function cargarInstrucciones(versionId) {
        $.getJSON('procedimientos_ajax.php', { accion: 'listar_instrucciones', procedimiento_version_id: versionId, empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            if (res.success && res.data) {
                let html = '';
                res.data.forEach(function (ins) {
                    html += `<tr>
                        <td class="text-center fw-bold text-muted">${ins.orden}</td>
                        <td>
                            <strong>${ins.titulo}</strong>
                            ${ins.descripcion ? `<small class="text-muted d-block">${ins.descripcion}</small>` : ''}
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-xs btn-outline-danger btnEliminarInstruccion" data-id="${ins.instruccion_id}">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>`;
                });
                $('#bodyInstrucciones').html(html || '<tr><td colspan="3" class="text-center text-muted py-2">Sin pasos registrados aún</td></tr>');
                $('#ins_orden').val((res.data.length || 0) + 1);
            }
        });
    }

    $('#formNuevaVersionProc').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) return;

        const data = $(this).serialize() + '&accion=guardar_version&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;
        $.post('procedimientos_ajax.php', data, function (res) {
            if (res.success) {
                $('#verproc_version').val('');
                cargarVersionesProc($('#verproc_id').val());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    $('#formNuevaInstruccion').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) return;

        const data = $(this).serialize() + '&accion=guardar_instruccion&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;
        $.post('procedimientos_ajax.php', data, function (res) {
            if (res.success) {
                $('#ins_titulo').val('');
                $('#ins_descripcion').val('');
                cargarInstrucciones(versionActualId);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    $(document).on('click', '.btnEliminarInstruccion', function () {
        const id = $(this).data('id');
        $.post('procedimientos_ajax.php', {
            accion: 'eliminar_instruccion',
            instruccion_id: id,
            empresa_idx: empresa_idx,
            pagina_idx: pagina_idx
        }, function (res) {
            if (res.success) {
                cargarInstrucciones(versionActualId);
            }
        }, 'json');
    });

    // =========================================================================
    // 5. INICIALIZACIÓN
    // =========================================================================
    cargarCombos();
    inicializarDataTable();
});
