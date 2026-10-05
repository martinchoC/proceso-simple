/**
 * JavaScript: Tablero de Control de Vencimientos
 * Módulo 4: Estudio Contable (vencimientos_tablero.js)
 */

$(document).ready(function () {
    let tablaVencimientos = null;
    let modalLiquidacion = new bootstrap.Modal(document.getElementById('modalLiquidacion'));

    cargarCombos();
    cargarKpis();
    iniciarDataTable();

    $('#filtroPeriodo, #filtroCliente, #filtroObligacion, #filtroEstado').on('change', function () {
        cargarKpis();
        recargarTabla();
    });

    function cargarCombos() {
        $.getJSON('vencimientos_tablero_ajax.php', { accion: 'obtener_combos' }, function (res) {
            if (!res.success) return;
            let d = res.data;

            // Clientes
            let optCli = '<option value="">-- Todos los clientes --</option>';
            d.clientes.forEach(c => {
                optCli += `<option value="${c.cliente_id}">${c.razon_social} (${c.cuit})</option>`;
            });
            $('#filtroCliente').html(optCli);

            // Obligaciones
            let optOb = '<option value="">-- Todas las obligaciones --</option>';
            d.obligaciones.forEach(o => {
                optOb += `<option value="${o.impuesto_obligacion_id}">[${o.organismo}] ${o.nombre} (${o.codigo_oficial})</option>`;
            });
            $('#filtroObligacion').html(optOb);

            // Estados
            let optEst = '<option value="">Todos los estados</option>';
            d.estados.forEach(e => {
                optEst += `<option value="${e.estado_registro_id}">${e.tabla_estado_registro}</option>`;
            });
            $('#filtroEstado').html(optEst);
        });
    }

    function cargarKpis() {
        let periodo = $('#filtroPeriodo').val();
        $.getJSON('vencimientos_tablero_ajax.php', { accion: 'kpis', periodo_fiscal: periodo }, function (res) {
            if (!res.success) return;
            let k = res.data;
            $('#kpi_total').text(k.total_vencimientos || 0);
            $('#kpi_vencidos').text(k.vencidos_urgentes || 0);
            $('#kpi_en_liq').text(k.en_liquidacion || 0);
            $('#kpi_listos').text(k.listos_presentar || 0);
            $('#kpi_presentados').text(k.presentados || 0);
            $('#kpi_pagados').text(k.pagados || 0);
        });
    }

    function iniciarDataTable() {
        tablaVencimientos = $('#tablaVencimientos').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: 'vencimientos_tablero_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.periodo_fiscal = $('#filtroPeriodo').val();
                    d.cliente_id = $('#filtroCliente').val();
                    d.impuesto_obligacion_id = $('#filtroObligacion').val();
                    d.tabla_estado_registro_id = $('#filtroEstado').val();
                },
                dataSrc: function (json) {
                    return json.data || [];
                }
            },
            columns: [
                {
                    data: 'semaforo_urgencia',
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (data === 'VENCIDO') {
                            return `<span class="badge bg-danger p-2"><i class="fas fa-exclamation-circle me-1"></i>VENCIDO (${Math.abs(row.dias_restantes)}d)</span>`;
                        }
                        if (data === 'URGENTE') {
                            return `<span class="badge bg-warning text-dark p-2"><i class="fas fa-clock me-1"></i>URGENTE (${row.dias_restantes}d)</span>`;
                        }
                        if (data === 'PROXIMO') {
                            return `<span class="badge bg-info p-2">${row.dias_restantes} días</span>`;
                        }
                        if (data === 'FINALIZADO') {
                            return `<span class="badge bg-success p-2"><i class="fas fa-check"></i> Cumplido</span>`;
                        }
                        return `<span class="badge bg-light text-secondary border p-2">${row.dias_restantes} días</span>`;
                    }
                },
                {
                    data: 'f_limite',
                    render: function (data) {
                        return `<strong class="fs-7">${data}</strong>`;
                    }
                },
                {
                    data: 'cliente_nombre',
                    render: function (data, type, row) {
                        return `<div><strong>${data}</strong></div><div class="text-muted fs-8">CUIT: ${row.cliente_cuit} (Term: ${row.terminacion_cuit})</div>`;
                    }
                },
                {
                    data: 'obligacion_nombre',
                    render: function (data, type, row) {
                        return `<div><span class="badge bg-dark fs-8 me-1">${row.organismo}</span> <strong>${row.obligacion_codigo}</strong></div><div class="text-muted fs-8">${data}</div>`;
                    }
                },
                {
                    data: 'jurisdiccion_nombre',
                    render: function (data) {
                        return data ? `<span class="badge bg-light text-dark border">${data}</span>` : '<span class="badge bg-light text-muted border">Nacional</span>';
                    }
                },
                {
                    data: 'importe_a_pagar',
                    className: 'text-end',
                    render: function (data) {
                        let num = parseFloat(data || 0);
                        return `<strong class="text-primary fs-7">$ ${num.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>`;
                    }
                },
                {
                    data: 'vep_nro',
                    render: function (data, type, row) {
                        if (data) {
                            return `<span class="badge bg-light text-dark border"><i class="fas fa-barcode me-1"></i>${data}</span>`;
                        }
                        return '<span class="text-muted fs-8">Sin VEP</span>';
                    }
                },
                {
                    data: 'tabla_estado_registro',
                    render: function (data, type, row) {
                        let st = parseInt(row.tabla_estado_registro_id);
                        let bClass = 'bg-secondary';
                        if (st === 4) bClass = 'bg-warning text-dark';
                        if (st === 5) bClass = 'bg-info text-white';
                        if (st === 6) bClass = 'bg-primary text-white';
                        if (st === 7) bClass = 'bg-success text-white';
                        if (st === 8) bClass = 'bg-danger text-white';
                        return `<span class="badge ${bClass}">${data || 'Borrador'}</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        return `
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary btn-liquidar" data-id="${row.vencimiento_id}" title="Gestionar Liquidación">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end fs-7">
                                    <li><a class="dropdown-item btn-transicion" href="#" data-id="${row.vencimiento_id}" data-estado="4"><i class="fas fa-calculator text-warning me-2"></i>En Liquidación</a></li>
                                    <li><a class="dropdown-item btn-transicion" href="#" data-id="${row.vencimiento_id}" data-estado="5"><i class="fas fa-clipboard-check text-info me-2"></i>Listo p/ Presentar</a></li>
                                    <li><a class="dropdown-item btn-transicion" href="#" data-id="${row.vencimiento_id}" data-estado="6"><i class="fas fa-paper-plane text-primary me-2"></i>Presentado</a></li>
                                    <li><a class="dropdown-item btn-transicion" href="#" data-id="${row.vencimiento_id}" data-estado="7"><i class="fas fa-check-circle text-success me-2"></i>Pagado</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item btn-transicion text-danger" href="#" data-id="${row.vencimiento_id}" data-estado="8"><i class="fas fa-ban me-2"></i>Anular</a></li>
                                </ul>
                            </div>
                        `;
                    }
                }
            ],
            language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
            dom: '<"row align-items-center p-2 mb-0"<"col-12 col-md-6"l><"col-12 col-md-6 text-end"f>>' +
                 '<"row"<"col-12"tr>>' +
                 '<"row align-items-center p-2 mt-2"<"col-12 col-md-5"i><"col-12 col-md-7 d-flex justify-content-end"p>>',
            initComplete: function () {
                new $.fn.dataTable.Buttons(tablaVencimientos, {
                    buttons: [
                        { extend: 'copyHtml5', text: 'Copiar', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'excelHtml5', title: 'Tablero_Vencimientos', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'csvHtml5', title: 'Tablero_Vencimientos', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'pdfHtml5', title: 'Tablero_Vencimientos', orientation: 'landscape', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'print', exportOptions: { columns: ':not(:last-child)' } }
                    ]
                });
            },
            order: [[1, 'asc']]
        });
    }

    $(document).on('click', '#btnRecargar', function () {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        tablaVencimientos.ajax.reload(function () {
            btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
        }, false);
    });
    $(document).on('click', '#btnExportarCopy', function () { tablaVencimientos.button('.buttons-copy').trigger(); });
    $(document).on('click', '#btnExportarExcel', function () { tablaVencimientos.button('.buttons-excel').trigger(); });
    $(document).on('click', '#btnExportarPDF', function () { tablaVencimientos.button('.buttons-pdf').trigger(); });
    $(document).on('click', '#btnExportarCSV', function () { tablaVencimientos.button('.buttons-csv').trigger(); });
    $(document).on('click', '#btnExportarPrint', function () { tablaVencimientos.button('.buttons-print').trigger(); });

    function recargarTabla() {
        if (tablaVencimientos) tablaVencimientos.ajax.reload();
    }

    // Modal Liquidar
    $(document).on('click', '.btn-liquidar', function () {
        let id = $(this).data('id');
        $.getJSON('vencimientos_tablero_ajax.php', { accion: 'obtener', vencimiento_id: id }, function (res) {
            if (!res.success) return;
            let v = res.data;
            $('#ml_vencimiento_id').val(v.vencimiento_id);
            $('#ml_nuevo_estado_id').val(v.tabla_estado_registro_id);
            $('#ml_info_cliente').text(v.cliente_nombre + ' (' + v.cliente_cuit + ')');
            $('#ml_info_obligacion').text('[' + v.organismo + '] ' + v.obligacion_nombre);
            $('#ml_info_flimite').text(v.f_limite);
            $('#ml_importe_determinado').val(v.importe_determinado);
            $('#ml_importe_saldo_favor').val(v.importe_saldo_favor);
            $('#ml_importe_a_pagar').val(v.importe_a_pagar);
            $('#ml_vep_nro').val(v.vep_nro || '');
            modalLiquidacion.show();
        });
    });

    // Auto-calculo importe a pagar en modal
    $('#ml_importe_determinado, #ml_importe_saldo_favor').on('input', function () {
        let det = parseFloat($('#ml_importe_determinado').val() || 0);
        let sf = parseFloat($('#ml_importe_saldo_favor').val() || 0);
        let aPagar = Math.max(0, det - sf);
        $('#ml_importe_a_pagar').val(aPagar.toFixed(2));
    });

    // Submit Liquidar
    $('#formLiquidacion').on('submit', function (e) {
        e.preventDefault();
        let datos = $(this).serialize();
        $.post('vencimientos_tablero_ajax.php', datos + '&accion=cambiar_estado', function (res) {
            if (res.success) {
                modalLiquidacion.hide();
                recargarTabla();
                cargarKpis();
                Swal.fire('¡Actualizado!', res.message, 'success');
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    });

    // Transición directa desde menú dropdown
    $(document).on('click', '.btn-transicion', function (e) {
        e.preventDefault();
        let id = $(this).data('id');
        let nuevoEstado = $(this).data('estado');

        $.post('vencimientos_tablero_ajax.php', {
            accion: 'cambiar_estado',
            vencimiento_id: id,
            nuevo_estado_id: nuevoEstado
        }, function (res) {
            if (res.success) {
                recargarTabla();
                cargarKpis();
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 1500
                });
                Toast.fire({ icon: 'success', title: res.message });
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    });
});
