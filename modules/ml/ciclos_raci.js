/**
 * Javascript para Matriz RACI y Asignaciones (Página 344 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/ciclos_raci.js
 */

document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresaIdx = urlParams.get('empresa_id') || 2;
    const ajaxUrl = window.location.pathname.includes('/modules/ml/') ? 'ciclos_raci_ajax.php' : 'modules/ml/ciclos_raci_ajax.php';

    let tablaRaci = null;
    let permisosUsuario = [];

    // Inicializar Tooltips
    function initTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // Inicializar DataTable
    function initDataTable() {
        tablaRaci = $('#tablaRaci').DataTable({
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
                    d.accion = 'listar';
                    d.empresa_idx = empresaIdx;
                    d.filtro_usuario = $('#filtroUsuario').val();
                    d.filtro_rol = $('#filtroRol').val();
                    d.filtro_entidad = $('#filtroEntidad').val();
                },
                dataSrc: function (json) {
                    if (!json.success) {
                        alert(json.message || 'Error al cargar asignaciones RACI');
                        return [];
                    }
                    if (json.permisos) {
                        permisosUsuario = json.permisos;
                    }
                    return json.data || [];
                }
            },
            columns: [
                { data: 'ciclo_tarea_raci_id', className: 'text-muted small fw-bold' },
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
                        return `<div><span class="fw-bold">${data.tarea_titulo}</span></div>
                                <div class="small text-muted">Vence: ${data.tarea_vencimiento_fmt || '--'} | Estado: ${data.tarea_estado_nombre}</div>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        let colorBadge = 'secondary';
                        switch (parseInt(data.raci_rol_id, 10)) {
                            case 1: colorBadge = 'primary'; break; // R
                            case 2: colorBadge = 'success'; break; // A
                            case 3: colorBadge = 'warning text-dark'; break; // C
                            case 4: colorBadge = 'info text-dark'; break; // I
                        }
                        return `<span class="badge bg-${colorBadge} fw-bold">${data.rol_nombre}</span>`;
                    }
                },
                {
                    data: null,
                    render: function (data) {
                        return `<div><i class="fas fa-user text-muted me-1"></i><span class="fw-semibold">${data.usuario_nombre}</span></div>
                                <div class="small text-muted">${data.usuario_email || ''}</div>`;
                    }
                },
                {
                    data: 'vigente_desde_fmt',
                    className: 'small text-muted'
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function (data) {
                        const id = data.ciclo_tarea_raci_id;
                        return `<div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-outline-danger btn-eliminar-raci" data-id="${id}" data-bs-toggle="tooltip" title="Eliminar Asignación"><i class="fas fa-trash-alt"></i></button>
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

                // Usuarios filtro
                const $usr = $('#filtroUsuario');
                $usr.empty().append('<option value="">-- Todos los usuarios --</option>');
                res.usuarios.forEach(u => {
                    $usr.append(`<option value="${u.usuario_id}">${u.nombre_completo}</option>`);
                });

                // Usuarios modals
                const $uRaci = $('#raciUsuarioId');
                const $uOrig = $('#reasigOrigenId');
                const $uDest = $('#reasigDestinoId');
                $uRaci.empty().append('<option value="">-- Seleccionar usuario --</option>');
                $uOrig.empty().append('<option value="">-- Seleccionar usuario actual --</option>');
                $uDest.empty().append('<option value="">-- Seleccionar usuario sustituto --</option>');

                res.usuarios.forEach(u => {
                    $uRaci.append(`<option value="${u.usuario_id}">${u.nombre_completo}</option>`);
                    $uOrig.append(`<option value="${u.usuario_id}">${u.nombre_completo}</option>`);
                    $uDest.append(`<option value="${u.usuario_id}">${u.nombre_completo}</option>`);
                });

                // Tareas abiertas
                const $tSelect = $('#raciTareaId');
                $tSelect.empty().append('<option value="">-- Seleccionar tarea abierta --</option>');
                res.tareas.forEach(t => {
                    $tSelect.append(`<option value="${t.ciclo_tarea_id}">#${t.ciclo_tarea_id} - ${t.titulo} (Ciclo #${t.ciclo_id} / ${t.obligacion_codigo})</option>`);
                });
            }
        });
    }

    // Filtros
    $('#filtroUsuario, #filtroRol, #filtroEntidad').on('change', function () {
        tablaRaci.ajax.reload();
    });

    // Abrir Modal Asignar Rol RACI
    $('#btnNuevaAsignacion').on('click', function () {
        $('#raciTareaId').val('');
        $('#raciRolId').val('1');
        $('#raciUsuarioId').val('');
        $('#modalAsignarRaci').modal('show');
    });

    // Guardar Asignación RACI
    $('#formAsignarRaci').on('submit', function (e) {
        e.preventDefault();
        const data = $(this).serialize() + '&accion=guardar&empresa_idx=' + empresaIdx;
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    $('#modalAsignarRaci').modal('hide');
                    alert(res.message);
                    tablaRaci.ajax.reload();
                } else {
                    alert('Error: ' + res.message);
                }
            }
        });
    });

    // Eliminar Asignación
    $('#tablaRaci').on('click', '.btn-eliminar-raci', function () {
        const id = $(this).data('id');
        if (!confirm('¿Confirma que desea eliminar esta asignación de rol RACI?')) return;

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { accion: 'eliminar', ciclo_tarea_raci_id: id, empresa_idx: empresaIdx },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    tablaRaci.ajax.reload();
                } else {
                    alert('Error: ' + res.message);
                }
            }
        });
    });

    // Abrir Modal Reasignar en Bloque (Caso C27)
    $('#btnReasignarBloque').on('click', function () {
        $('#reasigOrigenId').val('');
        $('#reasigDestinoId').val('');
        $('#reasigMotivo').val('');
        $('#modalReasignarBloque').modal('show');
    });

    // Guardar Reasignación en Bloque
    $('#formReasignarBloque').on('submit', function (e) {
        e.preventDefault();
        const data = $(this).serialize() + '&accion=reasignar_bloque&empresa_idx=' + empresaIdx;
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    $('#modalReasignarBloque').modal('hide');
                    alert(res.message);
                    tablaRaci.ajax.reload();
                } else {
                    alert('Error: ' + res.message);
                }
            }
        });
    });

    // Ver Historial Reasignaciones
    $('#btnHistorialReasig').on('click', function () {
        $.ajax({
            url: ajaxUrl,
            type: 'GET',
            data: { accion: 'listar_reasignaciones', empresa_idx: empresaIdx },
            dataType: 'json',
            success: function (res) {
                if (!res.success) return;
                const $tbody = $('#tbodyHistorialReasig');
                $tbody.empty();
                if (!res.data || res.data.length === 0) {
                    $tbody.append('<tr><td colspan="6" class="text-center text-muted">Sin registros de reasignaciones masivas</td></tr>');
                } else {
                    res.data.forEach(r => {
                        $tbody.append(`<tr>
                            <td class="text-muted small">${r.reasignacion_id}</td>
                            <td><strong class="text-danger">${r.usuario_origen_nombre}</strong></td>
                            <td><strong class="text-success">${r.usuario_destino_nombre}</strong></td>
                            <td>${r.fecha_efecto_fmt}</td>
                            <td><small>${r.motivo || ''}</small></td>
                            <td><small class="text-muted">${r.aplicada_por_nombre || '--'}<br>${r.aplicada_en_fmt || ''}</small></td>
                        </tr>`);
                    });
                }
                $('#modalHistorialReasig').modal('show');
            }
        });
    });

    // Iniciar
    cargarCombos();
    initDataTable();
});
