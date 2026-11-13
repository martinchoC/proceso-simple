/**
 * JavaScript: Gestión de Licencias y Delegación Automática
 * Módulo 4: Estudio Contable (licencias_gestion.js)
 */

$(document).ready(function() {
    let tabla = null;

    cargarCombos();

    function cargarCombos() {
        $.getJSON('licencias_gestion_ajax.php', { action: 'combos' }, function(res) {
            if (!res.success) {
                Swal.fire('Error', res.message, 'error');
                return;
            }

            let optU = '<option value="">-- Todos los Usuarios --</option>';
            let optModU = '<option value="">Seleccione Titular...</option>';
            let optSupR = '<option value="">-- Sin suplente R --</option>';
            let optSupA = '<option value="">-- Sin suplente A --</option>';

            res.data.usuarios.forEach(u => {
                const txt = `${u.usuario_nombre} (${u.usuario})`;
                optU += `<option value="${u.usuario_id}">${txt}</option>`;
                optModU += `<option value="${u.usuario_id}">${txt}</option>`;
                optSupR += `<option value="${u.usuario_id}">${txt}</option>`;
                optSupA += `<option value="${u.usuario_id}">${txt}</option>`;
            });

            $('#filtroUsuario').html(optU);
            $('#modal_usuario_id').html(optModU);
            $('#modal_suplente_r_id').html(optSupR);
            $('#modal_suplente_a_id').html(optSupA);

            inicializarDataTable();
        });
    }

    function inicializarDataTable() {
        tabla = $('#tablaLicencias').DataTable({
            responsive: true,
            language: {
                url: window.APP_ASSETS_URL + "js/datatables-i18n-1.13.6/es-ES.json"
            },
            ajax: {
                url: 'licencias_gestion_ajax.php',
                type: 'POST',
                data: function(d) {
                    d.action = 'listar';
                    d.usuario_id = $('#filtroUsuario').val();
                    d.tipo_licencia = $('#filtroTipo').val();
                },
                dataSrc: 'data'
            },
            columns: [
                { data: 'licencia_id', className: 'text-center' },
                { 
                    data: 'titular_nombre',
                    render: function(data, type, row) {
                        return `<strong>${data}</strong><br><small class="text-muted">${row.titular_login}</small>`;
                    }
                },
                { 
                    data: 'tipo_licencia',
                    render: function(data) {
                        let badge = 'bg-primary';
                        if (data === 'VACACIONES') badge = 'bg-success';
                        if (data === 'MEDICA') badge = 'bg-danger';
                        if (data === 'ESTUDIO') badge = 'bg-info text-dark';
                        return `<span class="badge ${badge}">${data}</span>`;
                    }
                },
                { data: 'f_desde' },
                { data: 'f_hasta' },
                { 
                    data: 'suplente_r_nombre',
                    render: function(data) {
                        return data ? `<span class="badge bg-light text-dark border">R: ${data}</span>` : '<span class="text-muted">-</span>';
                    }
                },
                { 
                    data: 'suplente_a_nombre',
                    render: function(data) {
                        return data ? `<span class="badge bg-light text-dark border">A: ${data}</span>` : '<span class="text-muted">-</span>';
                    }
                },
                { 
                    data: 'cant_delegaciones', 
                    className: 'text-center fw-bold',
                    render: function(data) {
                        return `<span class="badge bg-secondary">${data} tareas</span>`;
                    }
                },
                { 
                    data: 'tabla_estado_registro_id',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (parseInt(data) === 18) { // En curso / Delegada
                            return '<span class="badge bg-success">Vigente / Activa</span>';
                        } else {
                            return '<span class="badge bg-secondary">Revertida / Concluida</span>';
                        }
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function(data, type, row) {
                        if (parseInt(row.tabla_estado_registro_id) === 18) {
                            return `
                                <button class="btn btn-warning btn-sm btn-revertir py-0 px-2" data-funcion="inhabilitar" data-id="${row.licencia_id}" title="Finalizar Licencia y Revertir Tareas">
                                    <i class="fas fa-undo me-1"></i>Revertir
                                </button>
                            `;
                        } else {
                            return '<span class="text-muted small">Finalizada</span>';
                        }
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
                        { extend: 'excelHtml5', title: 'Padron_Licencias', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'csvHtml5', title: 'Padron_Licencias', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'pdfHtml5', title: 'Padron_Licencias', orientation: 'landscape', exportOptions: { columns: ':not(:last-child)' } },
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
    $('#btnFiltrar').on('click', function() {
        if (tabla) tabla.ajax.reload();
    });

    $('#btnLimpiar').on('click', function() {
        $('#filtroUsuario').val('');
        $('#filtroTipo').val('');
        if (tabla) tabla.ajax.reload();
    });

    // Abrir Modal
    $('#btnNuevaLicencia').on('click', function() {
        $('#formLicencia')[0].reset();
        $('#contenedorPreviewImpacto').html('<p class="text-muted text-center my-2 small">Haga clic en "Calcular Tareas Afectadas" para ver las tareas que se transferirán.</p>');
        $('#modalLicencia').modal('show');
    });

    // Calcular Impacto
    $('#btnPreviewImpacto').on('click', function() {
        const uId = $('#modal_usuario_id').val();
        const fDesde = $('#modal_f_desde').val();
        const fHasta = $('#modal_f_hasta').val();

        if (!uId || !fDesde || !fHasta) {
            Swal.fire('Atención', 'Seleccione titular y fechas desde/hasta para previsualizar.', 'warning');
            return;
        }

        $.post('licencias_gestion_ajax.php', {
            action: 'preview_afectadas',
            usuario_id: uId,
            f_desde: fDesde,
            f_hasta: fHasta
        }, function(res) {
            if (!res.success) {
                Swal.fire('Error', res.message, 'error');
                return;
            }

            if (res.data.length === 0) {
                $('#contenedorPreviewImpacto').html('<div class="alert alert-info py-1 mb-0 small"><i class="fas fa-check-circle me-1"></i>No hay tareas activas en este período para el usuario seleccionado.</div>');
                return;
            }

            let html = `
                <table class="table table-sm table-bordered mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Tarea</th>
                            <th>Cliente</th>
                            <th>Límite</th>
                            <th>Rol Afectado</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            res.data.forEach(t => {
                html += `
                    <tr>
                        <td><strong>${t.titulo}</strong></td>
                        <td>${t.cliente_nombre}</td>
                        <td>${t.f_limite}</td>
                        <td><span class="badge bg-warning text-dark">${t.rol_afectado}</span></td>
                    </tr>
                `;
            });
            html += '</tbody></table>';
            $('#contenedorPreviewImpacto').html(html);
        }, 'json');
    });

    // Guardar Licencia
    $('#formLicencia').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize() + '&action=registrar';

        Swal.fire({
            title: '¿Confirmar Licencia?',
            text: 'Las tareas del titular en el rango de fechas serán reasignadas a los suplentes correspondientes.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, confirmar y delegar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('licencias_gestion_ajax.php', formData, function(res) {
                    if (res.success) {
                        Swal.fire('Proceso Completado', res.message, 'success');
                        $('#modalLicencia').modal('hide');
                        tabla.ajax.reload();
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }, 'json');
            }
        });
    });

    // Revertir Licencia
    $('#tablaLicencias').on('click', '.btn-revertir', function() {
        const id = $(this).data('id');

        Swal.fire({
            title: '¿Revertir y Finalizar Licencia?',
            text: 'Las tareas delegadas volverán a ser asignadas a su titular original.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            confirmButtonText: 'Sí, revertir titularidad',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('licencias_gestion_ajax.php', { action: 'revertir', licencia_id: id }, function(res) {
                    if (res.success) {
                        Swal.fire('Revertida', res.message, 'success');
                        tabla.ajax.reload();
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }, 'json');
            }
        });
    });
});
