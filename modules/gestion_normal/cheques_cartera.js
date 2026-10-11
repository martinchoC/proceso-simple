/**
 * cheques_cartera.js
 *
 * Frontend interactivo para Cartera de Cheques y Conciliación
 * Sistema gestion_multipyme - Módulo Tesorería
 */

document.addEventListener('DOMContentLoaded', function () {
    let tablaCheques = null;
    const AJAX_URL = 'cheques_cartera_ajax.php';

    // Formateador de moneda
    const formatoMoneda = new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
        minimumFractionDigits: 2
    });

    // 1. Cargar Resumen de Cartera (Widgets)
    function cargarResumen() {
        fetch(`${AJAX_URL}?accion=resumen`)
            .then(res => res.json())
            .then(resp => {
                if (resp.success && resp.data) {
                    const d = resp.data;
                    document.getElementById('widgetCarteraMonto').textContent = formatoMoneda.format(d.cartera?.total || 0);
                    document.getElementById('widgetCarteraCant').textContent = `${d.cartera?.cant || 0} cheques disponibles`;

                    document.getElementById('widgetDepositadosMonto').textContent = formatoMoneda.format(d.depositados?.total || 0);
                    document.getElementById('widgetDepositadosCant').textContent = `${d.depositados?.cant || 0} cheques en clearing`;

                    document.getElementById('widgetPropiosMonto').textContent = formatoMoneda.format(d.propios?.total || 0);
                    document.getElementById('widgetPropiosCant').textContent = `${d.propios?.cant || 0} cheques emitidos`;

                    document.getElementById('widgetRechazadosMonto').textContent = formatoMoneda.format(d.rechazados?.total || 0);
                    document.getElementById('widgetRechazadosCant').textContent = `${d.rechazados?.cant || 0} cheques observados`;
                }
            })
            .catch(err => console.error("Error al cargar resumen de cheques:", err));
    }

    // 2. Cargar Maestros (Bancos y Cuentas Bancarias de Empresa)
    function cargarMaestros() {
        fetch(`${AJAX_URL}?accion=maestros`)
            .then(res => res.json())
            .then(resp => {
                if (resp.success && resp.data) {
                    // Poblar combo bancos en filtros
                    const selectBanco = document.getElementById('filtroBanco');
                    if (selectBanco && resp.data.bancos) {
                        resp.data.bancos.forEach(b => {
                            const opt = document.createElement('option');
                            opt.value = b.banco_id;
                            opt.textContent = b.banco;
                            selectBanco.appendChild(opt);
                        });
                    }

                    // Poblar combo cuentas bancarias en modal depósito
                    const selectCuentas = document.getElementById('depositoCuentaId');
                    if (selectCuentas && resp.data.cuentas_bancarias) {
                        resp.data.cuentas_bancarias.forEach(c => {
                            const opt = document.createElement('option');
                            opt.value = c.tesoreria_cuenta_id;
                            opt.textContent = c.tesoreria_cuenta;
                            selectCuentas.appendChild(opt);
                        });
                    }
                }
            })
            .catch(err => console.error("Error al cargar maestros de cheques:", err));
    }

    // 3. Inicializar DataTables
    function inicializarDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaCheques')) {
            $('#tablaCheques').DataTable().destroy();
        }

        tablaCheques = $('#tablaCheques').DataTable({
            processing: true,
            serverSide: false,
            responsive: true,
            ajax: {
                url: AJAX_URL,
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.tipo = $('#filtroTipo').val();
                    d.estado_id = $('#filtroEstado').val();
                    d.banco_id = $('#filtroBanco').val();
                    d.desde = $('#filtroDesde').val();
                    d.hasta = $('#filtroHasta').val();
                },
                dataSrc: function (json) {
                    return json.data || [];
                }
            },
            columns: [
                {
                    data: 'cheque_tipo',
                    render: function (data) {
                        return data === 'TERCERO'
                            ? '<span class="badge bg-info-subtle text-info border border-info-subtle"><i class="fas fa-hand-holding-usd me-1"></i>Tercero</span>'
                            : '<span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="fas fa-check-double me-1"></i>Propio</span>';
                    }
                },
                {
                    data: 'numero',
                    render: function (data) {
                        return `<strong class="text-dark">#${data}</strong>`;
                    }
                },
                { data: 'banco' },
                {
                    data: null,
                    render: function (row) {
                        const titular = row.titular || row.entidad_nombre || '-';
                        const cuit = row.cuit || row.entidad_cuit || '';
                        return cuit ? `<div>${titular}</div><small class="text-muted">CUIT: ${cuit}</small>` : titular;
                    }
                },
                {
                    data: 'f_emision',
                    render: function (data) {
                        if (!data) return '-';
                        const p = data.split('-');
                        return (p.length === 3) ? `${p[2]}/${p[1]}/${p[0]}` : data;
                    }
                },
                {
                    data: 'f_pago',
                    render: function (data) {
                        if (!data) return '-';
                        const p = data.split('-');
                        return (p.length === 3) ? `<span class="fw-bold">${p[2]}/${p[1]}/${p[0]}</span>` : data;
                    }
                },
                {
                    data: 'importe',
                    className: 'text-end fw-bold',
                    render: function (data) {
                        return formatoMoneda.format(parseFloat(data) || 0);
                    }
                },
                {
                    data: 'tesoreria_cuenta',
                    render: function (data) {
                        return data ? `<span class="badge bg-light text-dark border">${data}</span>` : '<span class="text-muted small">Sin cuenta</span>';
                    }
                },
                {
                    data: 'estado_badge'
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function (row) {
                        const estadoId = parseInt(row.tabla_estado_registro_id);
                        const tipo = row.cheque_tipo;
                        let btns = '<div class="btn-group btn-group-sm" role="group">';

                        // Si es Tercero en Cartera (29) -> Depositar o Rechazar
                        if (tipo === 'TERCERO' && estadoId === 29) {
                            btns += `<button type="button" class="btn btn-warning btn-sm btn-depositar" 
                                        data-id="${row.cheque_id}" 
                                        data-numero="${row.numero}" 
                                        data-banco="${row.banco}" 
                                        data-importe="${row.importe}" 
                                        title="Depositar en Banco">
                                        <i class="fas fa-university"></i>
                                     </button>`;
                            btns += `<button type="button" class="btn btn-outline-danger btn-sm btn-rechazar" 
                                        data-id="${row.cheque_id}" 
                                        data-numero="${row.numero}" 
                                        data-tipo="Tercero" 
                                        data-importe="${row.importe}" 
                                        title="Registrar Rechazo">
                                        <i class="fas fa-ban"></i>
                                     </button>`;
                        }

                        // Si es Propio Emitido (31) -> Debitar o Rechazar
                        if (tipo === 'PROPIO' && estadoId === 31) {
                            btns += `<button type="button" class="btn btn-dark btn-sm btn-debitar" 
                                        data-id="${row.cheque_id}" 
                                        data-numero="${row.numero}" 
                                        data-cuenta="${row.tesoreria_cuenta || ''}" 
                                        data-importe="${row.importe}" 
                                        title="Registrar Débito Bancario">
                                        <i class="fas fa-check-circle"></i>
                                     </button>`;
                            btns += `<button type="button" class="btn btn-outline-danger btn-sm btn-rechazar" 
                                        data-id="${row.cheque_id}" 
                                        data-numero="${row.numero}" 
                                        data-tipo="Propio" 
                                        data-importe="${row.importe}" 
                                        title="Registrar Rechazo">
                                        <i class="fas fa-ban"></i>
                                     </button>`;
                        }

                        // Si es Tercero Depositado (32) -> Se puede Rechazar si rebotó
                        if (tipo === 'TERCERO' && estadoId === 32) {
                            btns += `<button type="button" class="btn btn-outline-danger btn-sm btn-rechazar" 
                                        data-id="${row.cheque_id}" 
                                        data-numero="${row.numero}" 
                                        data-tipo="Tercero (Depositado)" 
                                        data-importe="${row.importe}" 
                                        title="Rechazo Bancario">
                                        <i class="fas fa-ban"></i>
                                     </button>`;
                        }

                        btns += '</div>';
                        return btns;
                    }
                }
            ],
            language: {
                processing: "Procesando...",
                search: "Buscar:",
                lengthMenu: "Mostrar _MENU_ registros",
                info: "Página _PAGE_ de _PAGES_",
                infoEmpty: "Página 0 de 0",
                infoFiltered: "(filtrado de _MAX_ registros)",
                loadingRecords: "Cargando...",
                zeroRecords: "No se encontraron resultados",
                emptyTable: "Ningún dato disponible en esta tabla",
                paginate: {
                    first: "Primero",
                    previous: "Anterior",
                    next: "Siguiente",
                    last: "Último"
                }
            },
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            dom: '<"row align-items-center mb-2"<"col-12 text-end"f>>' +
                 '<"row"<"col-12"tr>>' +
                 '<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>',
            order: [[5, 'asc']] // Ordenar por fecha de cobro ascendente
        });
    }


    // 4. Modales y Acciones
    // Modal Depositar
    const modalDepositar = new bootstrap.Modal(document.getElementById('modalDepositar'));
    $('#tablaCheques').on('click', '.btn-depositar', function () {
        const btn = $(this);
        $('#depositoChequeId').val(btn.data('id'));
        $('#depositoNumero').text('#' + btn.data('numero'));
        $('#depositoBanco').text(btn.data('banco'));
        $('#depositoImporte').text(formatoMoneda.format(parseFloat(btn.data('importe')) || 0));
        $('#depositoFecha').val(new Date().toISOString().split('T')[0]);
        modalDepositar.show();
    });

    document.getElementById('btnConfirmarDeposito').addEventListener('click', function () {
        const chequeId = $('#depositoChequeId').val();
        const cuentaId = $('#depositoCuentaId').val();
        const fDeposito = $('#depositoFecha').val();

        if (!cuentaId) {
            Swal.fire('Atención', 'Seleccione la cuenta bancaria de destino', 'warning');
            return;
        }

        Swal.fire({
            title: '¿Confirmar depósito bancario?',
            text: 'El cheque pasará a estado Depositado y se registrará en el libro de banco.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, depositar',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (result.isConfirmed) {
                fetch(`${AJAX_URL}?accion=depositar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        cheque_id: chequeId,
                        banco_cuenta_id: cuentaId,
                        f_deposito: fDeposito
                    })
                })
                    .then(res => res.json())
                    .then(resp => {
                        if (resp.success) {
                            modalDepositar.hide();
                            Swal.fire('Éxito', resp.message || resp.mensaje, 'success');
                            tablaCheques.ajax.reload();
                            cargarResumen();
                        } else {
                            Swal.fire('Error', resp.error || 'No se pudo depositar el cheque', 'error');
                        }
                    })
                    .catch(err => {
                        Swal.fire('Error', 'Fallo de comunicación con el servidor', 'error');
                    });
            }
        });
    });

    // Modal Débito
    const modalDebitar = new bootstrap.Modal(document.getElementById('modalDebitar'));
    $('#tablaCheques').on('click', '.btn-debitar', function () {
        const btn = $(this);
        $('#debitoChequeId').val(btn.data('id'));
        $('#debitoNumero').text('#' + btn.data('numero'));
        $('#debitoCuenta').text(btn.data('cuenta'));
        $('#debitoImporte').text(formatoMoneda.format(parseFloat(btn.data('importe')) || 0));
        $('#debitoFecha').val(new Date().toISOString().split('T')[0]);
        modalDebitar.show();
    });

    document.getElementById('btnConfirmarDebito').addEventListener('click', function () {
        const chequeId = $('#debitoChequeId').val();
        const fDebito = $('#debitoFecha').val();

        Swal.fire({
            title: '¿Confirmar débito bancario?',
            text: 'Se registrará el egreso contable definitivo del banco y el cheque quedará Debitado.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, debitar',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (result.isConfirmed) {
                fetch(`${AJAX_URL}?accion=debitar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        cheque_id: chequeId,
                        f_debito: fDebito
                    })
                })
                    .then(res => res.json())
                    .then(resp => {
                        if (resp.success) {
                            modalDebitar.hide();
                            Swal.fire('Éxito', resp.message || resp.mensaje, 'success');
                            tablaCheques.ajax.reload();
                            cargarResumen();
                        } else {
                            Swal.fire('Error', resp.error || 'No se pudo debitar el cheque', 'error');
                        }
                    })
                    .catch(err => {
                        Swal.fire('Error', 'Fallo de comunicación con el servidor', 'error');
                    });
            }
        });
    });

    // Modal Rechazo
    const modalRechazar = new bootstrap.Modal(document.getElementById('modalRechazar'));
    $('#tablaCheques').on('click', '.btn-rechazar', function () {
        const btn = $(this);
        $('#rechazoChequeId').val(btn.data('id'));
        $('#rechazoNumero').text('#' + btn.data('numero'));
        $('#rechazoTipo').text(btn.data('tipo'));
        $('#rechazoImporte').text(formatoMoneda.format(parseFloat(btn.data('importe')) || 0));
        $('#rechazoFecha').val(new Date().toISOString().split('T')[0]);
        $('#rechazoMotivo').val('');
        modalRechazar.show();
    });

    document.getElementById('btnConfirmarRechazo').addEventListener('click', function () {
        const chequeId = $('#rechazoChequeId').val();
        const fRechazo = $('#rechazoFecha').val();
        const motivo = $('#rechazoMotivo').val().trim();

        if (!motivo) {
            Swal.fire('Atención', 'Debe especificar el motivo del rechazo bancario', 'warning');
            return;
        }

        Swal.fire({
            title: '¿Confirmar rechazo de cheque?',
            text: 'El cheque pasará a estado RECHAZADO con reversión de movimientos asociados.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, rechazar',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (result.isConfirmed) {
                fetch(`${AJAX_URL}?accion=rechazar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        cheque_id: chequeId,
                        f_rechazo: fRechazo,
                        motivo: motivo
                    })
                })
                    .then(res => res.json())
                    .then(resp => {
                        if (resp.success) {
                            modalRechazar.hide();
                            Swal.fire('Registrado', resp.message || resp.mensaje, 'info');
                            tablaCheques.ajax.reload();
                            cargarResumen();
                        } else {
                            Swal.fire('Error', resp.error || 'No se pudo rechazar el cheque', 'error');
                        }
                    })
                    .catch(err => {
                        Swal.fire('Error', 'Fallo de comunicación con el servidor', 'error');
                    });
            }
        });
    });

    // 5. Filtros y Recarga
    document.getElementById('btnFiltrar').addEventListener('click', function () {
        tablaCheques.ajax.reload();
    });

    document.getElementById('btnRecargar').addEventListener('click', function () {
        tablaCheques.ajax.reload();
        cargarResumen();
    });

    // Inicialización al cargar la página
    cargarResumen();
    cargarMaestros();
    inicializarDataTable();
});
