/**
 * Lógica JavaScript para Posiciones, Organigrama y Supervisores (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/posiciones.js
 */

$(function () {
    'use strict';

    let tablaPosiciones = null;
    let tablaSupervision = null;
    let datosPosicionesCache = [];
    let listaUsuariosCache = [];

    // -------------------------------------------------------------------------
    // 1. INICIALIZACIÓN DE DATATABLES
    // -------------------------------------------------------------------------

    tablaPosiciones = $('#tablaPosiciones').DataTable({
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
        order: [[1, 'asc']],
        columns: [
            {
                data: 'codigo',
                render: function (data) {
                    return `<span class="badge bg-secondary badge-puesto-codigo">${escapeHtml(data)}</span>`;
                }
            },
            {
                data: 'nombre',
                render: function (data, type, row) {
                    let desc = row.descripcion ? `<br><small class="text-muted text-truncate d-inline-block" style="max-width: 250px;" title="${escapeHtml(row.descripcion)}">${escapeHtml(row.descripcion)}</small>` : '';
                    return `<strong>${escapeHtml(data)}</strong>${desc}`;
                }
            },
            {
                data: 'area_departamento',
                render: function (data) {
                    return `<span class="badge bg-light text-dark border"><i class="fas fa-layer-group me-1 text-primary"></i>${escapeHtml(data)}</span>`;
                }
            },
            {
                data: 'superior_nombre',
                render: function (data, type, row) {
                    if (!data) {
                        return `<span class="badge bg-primary bg-opacity-10 text-primary border border-primary"><i class="fas fa-crown me-1"></i>Cúspide de la Empresa</span>`;
                    }
                    return `<span class="text-dark fw-semibold">${escapeHtml(data)}</span> <small class="text-muted">(${escapeHtml(row.superior_codigo)})</small>`;
                }
            },
            {
                data: 'ocupante_nombre',
                render: function (data, type, row) {
                    if (!data) {
                        return `<span class="badge bg-warning bg-opacity-25 text-warning border border-warning"><i class="fas fa-exclamation-triangle me-1"></i>Vacante</span>`;
                    }
                    let email = row.ocupante_email ? `<br><small class="text-muted"><i class="far fa-envelope me-1"></i>${escapeHtml(row.ocupante_email)}</small>` : '';
                    let subBadge = row.total_ocupantes > 1 ? ` <span class="badge bg-info text-white" title="${row.total_ocupantes} personas asignadas">+${row.total_ocupantes - 1}</span>` : '';
                    return `<div class="d-flex align-items-center"><i class="fas fa-user-circle fs-5 me-2 text-primary"></i><div><strong>${escapeHtml(data)}</strong>${subBadge}${email}</div></div>`;
                }
            },
            {
                data: 'supervisor_nombre',
                render: function (data, type, row) {
                    if (!row.posicion_superior_id) {
                        return `<span class="text-muted fst-italic">Máxima autoridad</span>`;
                    }
                    if (!data) {
                        return `<span class="badge bg-warning bg-opacity-10 text-dark border border-warning" title="Puesto superior vacante"><i class="fas fa-user-slash me-1 text-warning"></i>Sin titular en ${escapeHtml(row.superior_nombre)}</span>`;
                    }
                    return `<div class="d-flex align-items-center"><i class="fas fa-arrow-up fs-6 me-2 text-success"></i><div><strong>${escapeHtml(data)}</strong><br><small class="text-muted">${escapeHtml(row.supervisor_puesto || '')}</small></div></div>`;
                }
            },
            {
                data: 'estado_id',
                className: 'text-center',
                render: function (data) {
                    return data == 1
                        ? '<span class="badge bg-success">Activo</span>'
                        : '<span class="badge bg-danger">Inactivo</span>';
                }
            },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: function (data, type, row) {
                    let btnEstado = row.estado_id == 1
                        ? `<button type="button" class="btn btn-xs btn-outline-danger btn-inactivar ms-1" data-id="${row.posicion_id}" title="Inactivar"><i class="fas fa-ban"></i></button>`
                        : `<button type="button" class="btn btn-xs btn-outline-success btn-activar ms-1" data-id="${row.posicion_id}" title="Activar"><i class="fas fa-check"></i></button>`;

                    return `
                        <div class="btn-group btn-group-xs" role="group">
                            <button type="button" class="btn btn-xs btn-outline-success btn-asignar" data-id="${row.posicion_id}" title="Asignar Ocupante">
                                <i class="fas fa-user-plus"></i>
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-primary btn-editar ms-1" data-id="${row.posicion_id}" title="Editar Posición">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary btn-historial ms-1" data-id="${row.posicion_id}" title="Ver Historial">
                                <i class="fas fa-history"></i>
                            </button>
                            ${btnEstado}
                        </div>
                    `;
                }
            }
        ],
        language: {
            processing: "Cargando posiciones...",
            search: "Buscar posición:",
            lengthMenu: "Mostrar _MENU_ registros",
            info: "Mostrando _START_ a _END_ de _TOTAL_ cargos",
            infoEmpty: "Mostrando 0 a 0 de 0 cargos",
            infoFiltered: "(filtrado de _MAX_ totales)",
            zeroRecords: "No se encontraron posiciones",
            emptyTable: "No hay posiciones registradas"
        }
    });

    // -------------------------------------------------------------------------
    // 2. CARGA DE DATOS Y RENDERIZADO
    // -------------------------------------------------------------------------

    function cargarPosiciones() {
        const area = $('#filtroArea').val();
        const estado_id = $('#filtroEstado').val();

        $.ajax({
            url: 'posiciones_ajax.php',
            type: 'GET',
            data: {
                accion: 'listar',
                area: area,
                estado_id: estado_id
            },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    datosPosicionesCache = res.data || [];
                    tablaPosiciones.clear().rows.add(datosPosicionesCache).draw();
                    actualizarKPIs(datosPosicionesCache);
                    renderizarMatrizSupervision(datosPosicionesCache);
                } else {
                    Swal.fire('Atención', res.message || 'No se pudieron cargar las posiciones', 'warning');
                }
            },
            error: function () {
                Swal.fire('Error', 'Error de conexión al cargar posiciones', 'error');
            }
        });
    }

    function actualizarKPIs(datos) {
        let total = datos.length;
        let cubiertas = 0;
        let vacantes = 0;
        let areas = new Set();

        datos.forEach(p => {
            if (p.estado_id == 1) {
                if (p.ocupante_id) {
                    cubiertas++;
                } else {
                    vacantes++;
                }
                if (p.area_departamento) {
                    areas.add(p.area_departamento);
                }
            }
        });

        $('#kpiTotalPosiciones').text(total);
        $('#kpiCubiertas').text(cubiertas);
        $('#kpiVacantes').text(vacantes);
        $('#kpiAreas').text(areas.size);
    }

    function cargarAreasFiltro() {
        $.ajax({
            url: 'posiciones_ajax.php',
            type: 'GET',
            data: { accion: 'listar_areas' },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    let sel = $('#filtroArea');
                    let datalist = $('#listaAreasDatalist');
                    sel.find('option:not(:first)').remove();
                    datalist.empty();

                    res.data.forEach(a => {
                        sel.append(`<option value="${escapeHtml(a)}">${escapeHtml(a)}</option>`);
                        datalist.append(`<option value="${escapeHtml(a)}">`);
                    });
                }
            }
        });
    }

    function cargarUsuariosCandidatos() {
        $.ajax({
            url: 'posiciones_ajax.php',
            type: 'GET',
            data: { accion: 'listar_usuarios_candidatos' },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    listaUsuariosCache = res.data;
                    let selAsig = $('#asig_usuario_id');
                    let selConsulta = $('#selectConsultaUsuario');

                    selAsig.find('option:not(:first)').remove();
                    selConsulta.find('option:not(:first)').remove();

                    res.data.forEach(u => {
                        let opt = `<option value="${u.usuario_id}">${escapeHtml(u.usuario_nombre)} (${escapeHtml(u.usuario)})</option>`;
                        selAsig.append(opt);
                        selConsulta.append(opt);
                    });
                }
            }
        });
    }

    function cargarPosicionesSuperiores(posicionActualId = 0) {
        let sel = $('#pos_superior_id');
        sel.find('option:not(:first)').remove();

        datosPosicionesCache.forEach(p => {
            if (p.posicion_id != posicionActualId && p.estado_id == 1) {
                sel.append(`<option value="${p.posicion_id}">[${escapeHtml(p.codigo)}] ${escapeHtml(p.nombre)} - ${escapeHtml(p.area_departamento)}</option>`);
            }
        });
    }

    // -------------------------------------------------------------------------
    // 3. RENDERIZADO DEL ORGANIGRAMA VISUAL
    // -------------------------------------------------------------------------

    function cargarOrganigrama() {
        $('#contenedorOrganigrama').html(`
            <div class="text-center text-muted py-5">
                <i class="fas fa-spinner fa-spin fa-2x mb-2 text-primary"></i><br>Cargando organigrama...
            </div>
        `);

        $.ajax({
            url: 'posiciones_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_arbol' },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data && res.data.length > 0) {
                    renderizarArbolOrganigrama(res.data);
                } else {
                    $('#contenedorOrganigrama').html(`
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-sitemap fa-3x mb-3 text-secondary opacity-50"></i>
                            <h5>No hay árbol jerárquico configurado</h5>
                            <p class="small">Crea posiciones y asígnales una posición superior para ver el organigrama.</p>
                        </div>
                    `);
                }
            },
            error: function () {
                $('#contenedorOrganigrama').html('<div class="alert alert-danger m-3">Error al cargar organigrama.</div>');
            }
        });
    }

    function renderizarArbolOrganigrama(arbol) {
        let html = '<div class="org-tree"><ul>';
        arbol.forEach(nodo => {
            html += construirNodoHtml(nodo, 0);
        });
        html += '</ul></div>';
        $('#contenedorOrganigrama').html(html);
    }

    function construirNodoHtml(nodo, nivel) {
        let tipoCard = nivel === 0 ? 'root-card' : (nodo.subordinados_nodos && nodo.subordinados_nodos.length > 0 ? 'manager-card' : 'staff-card');
        let ocupanteHtml = '';

        if (nodo.ocupante_nombre) {
            ocupanteHtml = `
                <div class="mt-2 pt-2 border-top text-start">
                    <small class="text-muted d-block" style="font-size: 0.7rem;">TITULAR ACTUAL:</small>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-user-circle text-primary me-1 fs-6"></i>
                        <strong class="text-dark small text-truncate d-inline-block" style="max-width: 170px;" title="${escapeHtml(nodo.ocupante_nombre)}">
                            ${escapeHtml(nodo.ocupante_nombre)}
                        </strong>
                    </div>
                </div>
            `;
        } else {
            ocupanteHtml = `
                <div class="mt-2 pt-2 border-top">
                    <span class="badge bg-warning text-dark py-1 px-2" style="font-size: 0.72rem;">
                        <i class="fas fa-user-slash me-1"></i>Puesto Vacante
                    </span>
                </div>
            `;
        }

        let html = `
            <li>
                <div class="org-card ${tipoCard}">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="badge bg-secondary badge-puesto-codigo">${escapeHtml(nodo.codigo)}</span>
                        <button type="button" class="btn btn-xs btn-link p-0 text-muted btn-ver-detalle-org" data-id="${nodo.posicion_id}" title="Ver detalle">
                            <i class="fas fa-info-circle"></i>
                        </button>
                    </div>
                    <h6 class="mb-0 fw-bold text-dark text-truncate" title="${escapeHtml(nodo.nombre)}">${escapeHtml(nodo.nombre)}</h6>
                    <small class="text-muted d-block" style="font-size: 0.75rem;">${escapeHtml(nodo.area_departamento)}</small>
                    ${ocupanteHtml}
                </div>
        `;

        if (nodo.subordinados_nodos && nodo.subordinados_nodos.length > 0) {
            html += '<ul>';
            nodo.subordinados_nodos.forEach(hijo => {
                html += construirNodoHtml(hijo, nivel + 1);
            });
            html += '</ul>';
        }

        html += '</li>';
        return html;
    }

    // -------------------------------------------------------------------------
    // 4. MATRIZ DE SUPERVISIÓN DIRECTA POR EMPLEADO
    // -------------------------------------------------------------------------

    function renderizarMatrizSupervision(datos) {
        let tbody = $('#tablaSupervision tbody');
        tbody.empty();

        let puestosConOcupante = datos.filter(p => p.ocupante_id && p.estado_id == 1);

        if (puestosConOcupante.length === 0) {
            tbody.html('<tr><td colspan="6" class="text-center text-muted py-4">No hay puestos con empleados asignados actualmente.</td></tr>');
            return;
        }

        puestosConOcupante.forEach(p => {
            let superiorPuesto = p.superior_nombre
                ? `<strong>${escapeHtml(p.superior_nombre)}</strong> <small class="text-muted">(${escapeHtml(p.superior_codigo)})</small>`
                : `<span class="badge bg-primary bg-opacity-10 text-primary border border-primary"><i class="fas fa-crown me-1"></i>Cúspide de la empresa</span>`;

            let supervisorTexto = '';
            if (!p.posicion_superior_id) {
                supervisorTexto = `<span class="badge bg-light text-muted border">Sin supervisor (Máxima autoridad)</span>`;
            } else if (p.supervisor_nombre) {
                supervisorTexto = `<div class="d-flex align-items-center"><i class="fas fa-arrow-up text-success fs-5 me-2"></i><div><strong class="text-dark">${escapeHtml(p.supervisor_nombre)}</strong><br><small class="text-muted">${escapeHtml(p.supervisor_puesto || '')}</small></div></div>`;
            } else {
                supervisorTexto = `<span class="badge bg-warning bg-opacity-25 text-warning border border-warning"><i class="fas fa-user-slash me-1"></i>Puesto superior vacante</span>`;
            }

            let email = p.ocupante_email ? `<span class="text-muted small">${escapeHtml(p.ocupante_email)}</span>` : '<span class="text-muted small">-</span>';

            let tr = `
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-user-tie text-primary me-2 fs-5"></i>
                            <div>
                                <strong class="text-dark">${escapeHtml(p.ocupante_nombre)}</strong>
                                <br><small class="text-muted">Ocupante desde ${p.ocupante_desde_fmt || '-'}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-secondary badge-puesto-codigo me-1">${escapeHtml(p.codigo)}</span>
                        <strong>${escapeHtml(p.nombre)}</strong>
                    </td>
                    <td><span class="badge bg-light text-dark border">${escapeHtml(p.area_departamento)}</span></td>
                    <td>${superiorPuesto}</td>
                    <td class="bg-primary bg-opacity-10">${supervisorTexto}</td>
                    <td>${email}</td>
                </tr>
            `;
            tbody.append(tr);
        });
    }

    // -------------------------------------------------------------------------
    // 5. ACCIONES Y MODALES
    // -------------------------------------------------------------------------

    // Abrir modal nueva posición
    $('#btnNuevaPosicion').on('click', function () {
        $('#formPosicion')[0].reset();
        $('#pos_id').val(0);
        $('#modalPosicionTitulo').html('<i class="fas fa-plus me-2"></i>Nueva Posición');
        cargarPosicionesSuperiores(0);
        $('#modalPosicion').modal('show');
    });

    // Editar posición
    $(document).on('click', '.btn-editar', function () {
        const id = $(this).data('id');
        $.ajax({
            url: 'posiciones_ajax.php',
            type: 'GET',
            data: { accion: 'obtener', posicion_id: id },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const p = res.data;
                    $('#pos_id').val(p.posicion_id);
                    $('#pos_codigo').val(p.codigo);
                    $('#pos_nombre').val(p.nombre);
                    $('#pos_area').val(p.area_departamento);
                    $('#pos_descripcion').val(p.descripcion || '');

                    cargarPosicionesSuperiores(p.posicion_id);
                    $('#pos_superior_id').val(p.posicion_superior_id || '');

                    $('#modalPosicionTitulo').html('<i class="fas fa-edit me-2"></i>Editar Posición');
                    $('#modalPosicion').modal('show');
                } else {
                    Swal.fire('Error', res.message || 'No se pudo obtener la posición', 'error');
                }
            }
        });
    });

    // Guardar posición
    $('#formPosicion').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btnGuardarPosicion');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: 'posiciones_ajax.php',
            type: 'POST',
            data: $(this).serialize() + '&accion=guardar_posicion',
            dataType: 'json',
            success: function (res) {
                $btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Posición');
                if (res.success) {
                    $('#modalPosicion').modal('hide');
                    Swal.fire('Guardado', res.message, 'success');
                    cargarPosiciones();
                    cargarAreasFiltro();
                } else {
                    Swal.fire('Atención', res.message, 'warning');
                }
            },
            error: function () {
                $btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Posición');
                Swal.fire('Error', 'Error de conexión al guardar posición', 'error');
            }
        });
    });

    // Asignar ocupante
    $(document).on('click', '.btn-asignar', function () {
        const id = $(this).data('id');
        const pos = datosPosicionesCache.find(p => p.posicion_id == id);
        if (!pos) return;

        $('#asig_posicion_id').val(pos.posicion_id);
        $('#asig_posicion_nombre').text(`[${pos.codigo}] ${pos.nombre}`);
        $('#asig_posicion_area').text(`Área: ${pos.area_departamento}`);
        $('#asig_usuario_id').val('');
        $('#asig_es_titular').val(1);
        $('#asig_vigente_desde').val(new Date().toISOString().split('T')[0]);

        $('#modalAsignarUsuario').modal('show');
    });

    // Confirmar asignación
    $('#formAsignarUsuario').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btnConfirmarAsignacion');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Asignando...');

        $.ajax({
            url: 'posiciones_ajax.php',
            type: 'POST',
            data: $(this).serialize() + '&accion=asignar_usuario',
            dataType: 'json',
            success: function (res) {
                $btn.prop('disabled', false).html('<i class="fas fa-check me-1"></i>Asignar al Puesto');
                if (res.success) {
                    $('#modalAsignarUsuario').modal('hide');
                    Swal.fire('Asignado', res.message, 'success');
                    cargarPosiciones();
                } else {
                    Swal.fire('Atención', res.message, 'warning');
                }
            },
            error: function () {
                $btn.prop('disabled', false).html('<i class="fas fa-check me-1"></i>Asignar al Puesto');
                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
            }
        });
    });

    // Ver historial del puesto
    $(document).on('click', '.btn-historial, .btn-ver-detalle-org', function () {
        const id = $(this).data('id');
        $.ajax({
            url: 'posiciones_ajax.php',
            type: 'GET',
            data: { accion: 'obtener', posicion_id: id },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const p = res.data;
                    $('#hist_pos_nombre').text(p.nombre);
                    $('#hist_pos_codigo').text(p.codigo);
                    $('#hist_pos_area').text(p.area_departamento);
                    $('#hist_pos_superior').text(p.superior_nombre ? `${p.superior_nombre} (${p.superior_codigo})` : 'Cúspide de la empresa');

                    // Llenar tabla de ocupantes
                    let tbody = $('#tablaHistorialOcupantes tbody');
                    tbody.empty();

                    if (!p.ocupantes || p.ocupantes.length === 0) {
                        tbody.html('<tr><td colspan="7" class="text-center text-muted py-3">Este puesto nunca ha tenido ocupantes asignados.</td></tr>');
                    } else {
                        p.ocupantes.forEach(oc => {
                            let badgeCar = oc.es_titular == 1 ? '<span class="badge bg-primary">Titular</span>' : '<span class="badge bg-secondary">Adjunto</span>';
                            let badgeEst = oc.es_activo == 1 ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-light text-muted border">Finalizado</span>';
                            let btnFin = oc.es_activo == 1
                                ? `<button type="button" class="btn btn-xs btn-outline-danger btn-finalizar-ocupacion" data-id="${oc.usuario_posicion_id}" title="Finalizar ocupación"><i class="fas fa-door-open me-1"></i>Finalizar</button>`
                                : '-';

                            let tr = `
                                <tr>
                                    <td><strong>${escapeHtml(oc.usuario_nombre)}</strong></td>
                                    <td><small class="text-muted">${escapeHtml(oc.email || '-')}</small></td>
                                    <td>${badgeCar}</td>
                                    <td>${oc.vigente_desde_fmt || '-'}</td>
                                    <td>${oc.vigente_hasta_fmt || '<span class="text-success fst-italic">Actualidad</span>'}</td>
                                    <td class="text-center">${badgeEst}</td>
                                    <td class="text-center">${btnFin}</td>
                                </tr>
                            `;
                            tbody.append(tr);
                        });
                    }

                    // Puestos subordinados
                    let divSub = $('#listaSubordinadosDirectos');
                    divSub.empty();
                    if (!p.subordinados || p.subordinados.length === 0) {
                        divSub.html('<span class="text-muted fst-italic small">No tiene posiciones a cargo que le reporten directamente.</span>');
                    } else {
                        let subBadges = p.subordinados.map(s => `<span class="badge bg-light text-dark border me-2 mb-1 p-2"><i class="fas fa-level-down-alt me-1 text-primary"></i><strong>[${escapeHtml(s.codigo)}]</strong> ${escapeHtml(s.nombre)}</span>`).join('');
                        divSub.html(subBadges);
                    }

                    $('#modalHistorialPosicion').modal('show');
                }
            }
        });
    });

    // Finalizar ocupación
    $(document).on('click', '.btn-finalizar-ocupacion', function () {
        const up_id = $(this).data('id');
        const hoy = new Date().toISOString().split('T')[0];

        Swal.fire({
            title: '¿Finalizar ocupación del cargo?',
            text: 'Indique la fecha de salida o término en el puesto:',
            input: 'date',
            inputValue: hoy,
            showCancelButton: true,
            confirmButtonText: 'Finalizar Ocupación',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33'
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                $.ajax({
                    url: 'posiciones_ajax.php',
                    type: 'POST',
                    data: {
                        accion: 'finalizar_ocupacion',
                        usuario_posicion_id: up_id,
                        vigente_hasta: result.value
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            $('#modalHistorialPosicion').modal('hide');
                            Swal.fire('Completado', res.message, 'success');
                            cargarPosiciones();
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // Activar / Inactivar posición
    $(document).on('click', '.btn-inactivar', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: '¿Inactivar posición?',
            text: 'El cargo pasará a estado inactivo en el organigrama.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, inactivar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'posiciones_ajax.php',
                    type: 'POST',
                    data: { accion: 'inactivar', posicion_id: id },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            Swal.fire('Inactivada', res.message, 'success');
                            cargarPosiciones();
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                });
            }
        });
    });

    $(document).on('click', '.btn-activar', function () {
        const id = $(this).data('id');
        $.ajax({
            url: 'posiciones_ajax.php',
            type: 'POST',
            data: { accion: 'activar', posicion_id: id },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    Swal.fire('Activada', res.message, 'success');
                    cargarPosiciones();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }
        });
    });

    // -------------------------------------------------------------------------
    // 6. CONSULTA RÁPIDA DE SUPERVISOR POR USUARIO
    // -------------------------------------------------------------------------

    $('#btnConsultarSupervisor').on('click', function () {
        $('#selectConsultaUsuario').val('');
        $('#resultadoSupervisorCard').addClass('d-none');
        $('#modalConsultaSupervisor').modal('show');
    });

    $('#selectConsultaUsuario').on('change', function () {
        const uid = $(this).val();
        if (!uid) {
            $('#resultadoSupervisorCard').addClass('d-none');
            return;
        }

        $.ajax({
            url: 'posiciones_ajax.php',
            type: 'GET',
            data: { accion: 'consultar_supervisor_usuario', usuario_id: uid },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const d = res.data;
                    const u = listaUsuariosCache.find(x => x.usuario_id == uid);

                    $('#res_empleado_nombre').text(u ? u.usuario_nombre : 'Usuario #' + uid);
                    $('#res_empleado_puesto').text(`[${d.puesto_codigo}] ${d.puesto_nombre}`);
                    $('#res_empleado_area').text(`Área: ${d.area_departamento}`);

                    if (!d.posicion_superior_id) {
                        $('#res_supervisor_nombre').text('Sin supervisor (Máxima autoridad)');
                        $('#res_supervisor_puesto').text('Cúspide de la empresa');
                        $('#res_supervisor_email').text('-');
                    } else if (d.supervisor_nombre) {
                        $('#res_supervisor_nombre').text(d.supervisor_nombre);
                        $('#res_supervisor_puesto').text(`${d.superior_puesto_nombre} (${d.superior_puesto_codigo})`);
                        $('#res_supervisor_email').text(d.supervisor_email || '-');
                    } else {
                        $('#res_supervisor_nombre').text('Sin titular asignado en el puesto superior');
                        $('#res_supervisor_puesto').text(`${d.superior_puesto_nombre} (${d.superior_puesto_codigo})`);
                        $('#res_supervisor_email').text('Cargo superior vacante');
                    }

                    $('#resultadoSupervisorCard').removeClass('d-none');
                } else {
                    const u = listaUsuariosCache.find(x => x.usuario_id == uid);
                    $('#res_empleado_nombre').text(u ? u.usuario_nombre : 'Usuario #' + uid);
                    $('#res_empleado_puesto').text('Sin posición asignada');
                    $('#res_empleado_area').text('Este usuario aún no tiene ningún cargo en el organigrama');
                    $('#res_supervisor_nombre').text('Desconocido');
                    $('#res_supervisor_puesto').text('-');
                    $('#res_supervisor_email').text('-');
                    $('#resultadoSupervisorCard').removeClass('d-none');
                }
            }
        });
    });

    // -------------------------------------------------------------------------
    // 7. EVENTOS DE SOLAPAS Y RECARGA
    // -------------------------------------------------------------------------

    $('button[data-bs-toggle="pill"]').on('shown.bs.tab', function (e) {
        const target = $(e.target).attr('data-bs-target');
        if (target === '#panel-organigrama') {
            cargarOrganigrama();
        } else if (target === '#panel-lista') {
            tablaPosiciones.columns.adjust().responsive.recalc();
        }
    });

    $('#filtroArea, #filtroEstado').on('change', function () {
        cargarPosiciones();
    });

    $('#btnRecargarDatos').on('click', function () {
        cargarPosiciones();
        cargarAreasFiltro();
        cargarUsuariosCandidatos();
    });

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Inicialización al cargar la página
    cargarPosiciones();
    cargarAreasFiltro();
    cargarUsuariosCandidatos();
});
