/**
 * JavaScript: Matriz RACI y Time Tracking
 * Módulo 4: Estudio Contable (tareas_raci.js)
 */

$(document).ready(function() {
    let tabla = null;
    let cacheCombos = null;

    cargarCombos();

    function cargarCombos() {
        $.getJSON('tareas_raci_ajax.php', { action: 'combos' }, function(res) {
            if (!res.success) {
                Swal.fire('Error', res.message, 'error');
                return;
            }
            cacheCombos = res.data;

            // Llenar selectores de filtro y modales
            let optU = '<option value="">-- Todos los Responsables --</option>';
            let optA = '<option value="">-- Todos los Accountable --</option>';
            let optModU = '<option value="">Seleccione operador...</option>';
            let optModA = '<option value="">Seleccione supervisor...</option>';

            res.data.usuarios.forEach(u => {
                const txt = `${u.usuario_nombre} (${u.usuario})`;
                optU += `<option value="${u.usuario_id}">${txt}</option>`;
                optA += `<option value="${u.usuario_id}">${txt}</option>`;
                optModU += `<option value="${u.usuario_id}">${txt}</option>`;
                optModA += `<option value="${u.usuario_id}">${txt}</option>`;
            });

            $('#filtroUsuarioR').html(optU);
            $('#filtroUsuarioA').html(optA);
            $('#modal_usuario_r_id').html(optModU);
            $('#modal_usuario_a_id').html(optModA);

            // Clientes
            let optC = '<option value="">-- Todos los Clientes --</option>';
            let optModC = '<option value="">Seleccione Cliente...</option>';
            res.data.clientes.forEach(c => {
                const txt = `${c.razon_social} (${c.cuit})`;
                optC += `<option value="${c.cliente_id}">${txt}</option>`;
                optModC += `<option value="${c.cliente_id}">${txt}</option>`;
            });
            $('#filtroCliente').html(optC);
            // Cliente activo del estudio (estudio_cliente_activo.js): ?cliente_id=N o el último elegido
            const activo = window.EstudioCliente ? EstudioCliente.inicial() : 0;
            if (activo > 0 && $('#filtroCliente option[value="' + activo + '"]').length) {
                $('#filtroCliente').val(String(activo));
            }
            $('#modal_cliente_id').html(optModC);

            // Estados
            let optE = '<option value="">-- Todos los Estados --</option>';
            let optModE = '';
            res.data.estados.forEach(e => {
                optE += `<option value="${e.estado_registro_id}">${e.tabla_estado_registro}</option>`;
                optModE += `<option value="${e.estado_registro_id}">${e.tabla_estado_registro}</option>`;
            });
            $('#filtroEstado').html(optE);
            $('#modal_estado_id').html(optModE);

            inicializarDataTable();
        });
    }

    function inicializarDataTable() {
        tabla = $('#tablaTareas').DataTable({
            responsive: true,
            language: {
                url: window.APP_ASSETS_URL + "js/datatables-i18n-1.13.6/es-ES.json"
            },
            ajax: {
                url: 'tareas_raci_ajax.php',
                type: 'POST',
                data: function(d) {
                    d.action = 'listar';
                    d.usuario_r_id = $('#filtroUsuarioR').val();
                    d.usuario_a_id = $('#filtroUsuarioA').val();
                    d.cliente_id = $('#filtroCliente').val();
                    d.tabla_estado_registro_id = $('#filtroEstado').val();
                },
                dataSrc: 'data'
            },
            columns: [
                { data: 'tarea_id', className: 'text-center' },
                { 
                    data: 'titulo',
                    render: function(data, type, row) {
                        let html = `<strong>${data}</strong>`;
                        if (row.descripcion) {
                            html += `<br><small class="text-muted">${row.descripcion}</small>`;
                        }
                        return html;
                    }
                },
                { 
                    data: 'cliente_nombre',
                    render: function(data, type, row) {
                        return `${data}<br><small class="badge bg-light text-dark">${row.cliente_cuit}</small>`;
                    }
                },
                { 
                    data: 'f_limite',
                    render: function(data, type, row) {
                        const dias = parseInt(row.dias_restantes);
                        let badge = 'bg-secondary';
                        if (dias < 0) badge = 'bg-danger';
                        else if (dias <= 2) badge = 'bg-warning text-dark';
                        else badge = 'bg-success';

                        let badgeTxt = dias < 0 ? `Vencido hace ${Math.abs(dias)}d` : (dias === 0 ? 'Vence hoy' : `En ${dias}d`);
                        return `${data}<br><span class="badge ${badge}">${badgeTxt}</span>`;
                    }
                },
                { 
                    data: 'responsable_r_nombre',
                    render: function(data, type, row) {
                        return `<span class="badge bg-primary text-white"><i class="fas fa-user-edit me-1"></i>R: ${data}</span>`;
                    }
                },
                { 
                    data: 'supervisor_a_nombre',
                    render: function(data, type, row) {
                        return `<span class="badge bg-secondary text-white"><i class="fas fa-user-check me-1"></i>A: ${data}</span>`;
                    }
                },
                { 
                    data: 'horas_reales',
                    className: 'text-end',
                    render: function(data, type, row) {
                        const real = parseFloat(data || 0);
                        const est = parseFloat(row.horas_estimadas || 0);
                        const pct = est > 0 ? Math.round((real / est) * 100) : 0;
                        let barColor = pct > 100 ? 'bg-danger' : (pct >= 80 ? 'bg-warning' : 'bg-info');
                        return `<div>${real.toFixed(2)}h / ${est.toFixed(2)}h</div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar ${barColor}" style="width: ${Math.min(pct, 100)}%"></div>
                                </div>`;
                    }
                },
                { 
                    data: 'tabla_estado_registro',
                    className: 'text-center',
                    render: function(data, type, row) {
                        const est = parseInt(row.tabla_estado_registro_id);
                        let cls = 'bg-secondary';
                        if (est === 17) cls = 'bg-secondary'; // Pendiente
                        if (est === 18) cls = 'bg-warning text-dark'; // En Proceso
                        if (est === 19) cls = 'bg-info text-dark'; // En Revision
                        if (est === 21) cls = 'bg-success'; // Finalizada
                        if (est === 26) cls = 'bg-danger'; // Cancelada
                        return `<span class="badge ${cls}">${data || 'N/A'}</span>`;
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function(data, type, row) {
                        return `
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-success btn-tiempo" data-funcion="editar" data-id="${row.tarea_id}" data-titulo="${row.titulo}" title="Cargar Minutos">
                                    <i class="fas fa-stopwatch"></i>
                                </button>
                                <button class="btn btn-outline-dark btn-historial" data-id="${row.tarea_id}" data-titulo="${row.titulo}" title="Historial Tiempos">
                                    <i class="fas fa-history"></i>
                                </button>
                                <button class="btn btn-outline-primary btn-estado" data-funcion="editar" data-id="${row.tarea_id}" data-estado="${row.tabla_estado_registro_id}" title="Cambiar Estado">
                                    <i class="fas fa-exchange-alt"></i>
                                </button>
                            </div>
                        `;
                    }
                }
            ],
            dom: '<"row align-items-center p-2 mb-0"<"col-12 col-md-6"l><"col-12 col-md-6 text-end"f>>' +
                 '<"row"<"col-12"tr>>' +
                 '<"row align-items-center p-2 mt-2"<"col-12 col-md-5"i><"col-12 col-md-7 d-flex justify-content-end"p>>',
            initComplete: function () {
                new $.fn.dataTable.Buttons(tabla, {
                    buttons: [
                        { extend: 'copyHtml5', text: 'Copiar', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'excelHtml5', title: 'Tareas_RACI', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'csvHtml5', title: 'Tareas_RACI', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'pdfHtml5', title: 'Tareas_RACI', orientation: 'landscape', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'print', exportOptions: { columns: ':not(:last-child)' } }
                    ]
                });
            }
        });
    }

    $(document).on('click', '#btnRecargar', function () {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        tabla.ajax.reload(function () {
            btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
        }, false);
    });
    $(document).on('click', '#btnExportarCopy', function () { tabla.button('.buttons-copy').trigger(); });
    $(document).on('click', '#btnExportarExcel', function () { tabla.button('.buttons-excel').trigger(); });
    $(document).on('click', '#btnExportarPDF', function () { tabla.button('.buttons-pdf').trigger(); });
    $(document).on('click', '#btnExportarCSV', function () { tabla.button('.buttons-csv').trigger(); });
    $(document).on('click', '#btnExportarPrint', function () { tabla.button('.buttons-print').trigger(); });

    // Filtros
    $('#filtroCliente').on('change', function () {
        if (window.EstudioCliente) { EstudioCliente.guardar($(this).val()); }
    });

    $('#btnFiltrar').on('click', function() {
        if (tabla) tabla.ajax.reload();
    });

    $('#btnLimpiar').on('click', function() {
        $('#filtroUsuarioR').val('');
        $('#filtroUsuarioA').val('');
        $('#filtroCliente').val('');
        $('#filtroEstado').val('');
        if (tabla) tabla.ajax.reload();
    });

    // Nueva Tarea
    $('#btnNuevaTarea').on('click', function() {
        $('#formTarea')[0].reset();
        $('#tarea_id').val(0);
        $('#modalTareaTitle').text('Nueva Tarea RACI');
        $('#modal_f_inicio').val(new Date().toISOString().substring(0, 10));
        $('#modal_estado_id').val(17); // Pendiente
        $('#modalTarea').modal('show');
    });

    // Guardar Tarea
    $('#formTarea').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize() + '&action=guardar';

        $.post('tareas_raci_ajax.php', formData, function(res) {
            if (res.success) {
                Swal.fire('Guardado', res.message, 'success');
                $('#modalTarea').modal('hide');
                tabla.ajax.reload();
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    });

    // Cargar Minutos Modal
    $('#tablaTareas').on('click', '.btn-tiempo', function() {
        const id = $(this).data('id');
        const tit = $(this).data('titulo');
        $('#tiempo_tarea_id').val(id);
        $('#tiempo_tarea_titulo').val(tit);
        $('#tiempo_minutos').val('');
        $('#tiempo_descripcion').val('');
        $('#modalTiempo').modal('show');
    });

    // Submit Minutos
    $('#formTiempo').on('submit', function(e) {
        e.preventDefault();
        const postData = $(this).serialize() + '&action=registrar_tiempo';

        $.post('tareas_raci_ajax.php', postData, function(res) {
            if (res.success) {
                Swal.fire('Registrado', res.message, 'success');
                $('#modalTiempo').modal('hide');
                tabla.ajax.reload();
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    });

    // Historial de Tiempos
    $('#tablaTareas').on('click', '.btn-historial', function() {
        const id = $(this).data('id');
        const tit = $(this).data('titulo');
        $('#historialTituloTarea').text(`Tarea: ${tit} (ID: ${id})`);

        $.getJSON('tareas_raci_ajax.php', { action: 'listar_tiempos', tarea_id: id }, function(res) {
            let html = '';
            if (res.success && res.data.length > 0) {
                res.data.forEach(t => {
                    const hs = (parseInt(t.minutos) / 60).toFixed(2);
                    html += `
                        <tr>
                            <td>${t.f_registro}</td>
                            <td>${t.usuario_nombre} (${t.usuario})</td>
                            <td class="text-end fw-bold">${t.minutos} min</td>
                            <td class="text-end">${hs} hs</td>
                            <td>${t.descripcion}</td>
                        </tr>
                    `;
                });
            } else {
                html = '<tr><td colspan="5" class="text-center text-muted">Sin tiempos registrados para esta tarea.</td></tr>';
            }
            $('#tablaHistorialTiempos tbody').html(html);
            $('#modalHistorialTiempos').modal('show');
        });
    });

    // Cambiar Estado
    $('#tablaTareas').on('click', '.btn-estado', function() {
        const id = $(this).data('id');
        const estadoActual = $(this).data('estado');

        let selectHtml = '<select id="swalNuevoEstado" class="form-select">';
        if (cacheCombos && cacheCombos.estados) {
            cacheCombos.estados.forEach(e => {
                const sel = (e.estado_registro_id == estadoActual) ? 'selected' : '';
                selectHtml += `<option value="${e.estado_registro_id}" ${sel}>${e.tabla_estado_registro}</option>`;
            });
        }
        selectHtml += '</select>';

        Swal.fire({
            title: 'Cambiar Estado de Tarea',
            html: `<p>Seleccione el nuevo estado para la tarea ID ${id}:</p>${selectHtml}`,
            showCancelButton: true,
            confirmButtonText: 'Actualizar',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                return $('#swalNuevoEstado').val();
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const nuevoEstado = result.value;
                $.post('tareas_raci_ajax.php', {
                    action: 'cambiar_estado',
                    tarea_id: id,
                    nuevo_estado_id: nuevoEstado
                }, function(res) {
                    if (res.success) {
                        Swal.fire('Actualizado', res.message, 'success');
                        tabla.ajax.reload();
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }, 'json');
            }
        });
    });
});
