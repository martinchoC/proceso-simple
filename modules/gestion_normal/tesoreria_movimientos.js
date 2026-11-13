/**
 * tesoreria_movimientos.js
 *
 * Frontend interactivo para Movimientos Internos de Tesorería (Transferencias entre Cuentas)
 * Sistema gestion_multipyme - Módulo Tesorería
 */

document.addEventListener('DOMContentLoaded', function () {
    let tablaTransferencias = null;
    let cuentasDisponibles = [];
    const AJAX_URL = 'tesoreria_movimientos_ajax.php';

    // Formateador de moneda
    const formatoMoneda = { format: (v) => Fmt.moneda(v) };

    // 1. Cargar Maestros (Cuentas y Sucursales)
    function cargarMaestros() {
        fetch(`${AJAX_URL}?accion=maestros`)
            .then(res => res.json())
            .then(resp => {
                if (resp.success && resp.data) {
                    cuentasDisponibles = resp.data.cuentas || [];
                    const sucursales = resp.data.sucursales || [];

                    // Poblar combo filtro de cuentas
                    const filtroCuenta = document.getElementById('filtroCuenta');
                    const selectOrigen = document.getElementById('transOrigen');
                    const selectDestino = document.getElementById('transDestino');

                    filtroCuenta.innerHTML = '<option value="">-- Todas las Cuentas --</option>';
                    selectOrigen.innerHTML = '<option value="">-- Seleccionar Cuenta Origen --</option>';
                    selectDestino.innerHTML = '<option value="">-- Seleccionar Cuenta Destino --</option>';

                    cuentasDisponibles.forEach(c => {
                        const icon = (c.tipo === 'BANCO') ? '🏦 ' : '💵 ';
                        const label = `${icon}${c.nombre} (${formatoMoneda.format(c.saldo)})`;

                        // Filtro
                        const optF = document.createElement('option');
                        optF.value = c.id;
                        optF.textContent = `${c.nombre} [${c.tipo}]`;
                        filtroCuenta.appendChild(optF);

                        // Origen
                        const optO = document.createElement('option');
                        optO.value = c.id;
                        optO.textContent = label;
                        optO.dataset.saldo = c.saldo;
                        selectOrigen.appendChild(optO);

                        // Destino
                        const optD = document.createElement('option');
                        optD.value = c.id;
                        optD.textContent = label;
                        optD.dataset.saldo = c.saldo;
                        selectDestino.appendChild(optD);
                    });

                    // Poblar Sucursales
                    const selectSuc = document.getElementById('transSucursal');
                    selectSuc.innerHTML = '';
                    sucursales.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s.sucursal_id;
                        opt.textContent = s.sucursal;
                        selectSuc.appendChild(opt);
                    });
                }
            })
            .catch(err => console.error("Error al cargar maestros de tesorería:", err));
    }

    // 2. Control de saldos y selección de cuentas
    const selectOrigen = document.getElementById('transOrigen');
    const selectDestino = document.getElementById('transDestino');
    const lblSaldoOrigen = document.getElementById('transSaldoOrigen');
    const lblSaldoDestino = document.getElementById('transSaldoDestino');
    const inputImporte = document.getElementById('transImporte');
    const alertaSaldo = document.getElementById('alertaSaldoInsuficiente');

    function actualizarSaldosVisuales() {
        const ctaO = cuentasDisponibles.find(c => c.id == selectOrigen.value);
        const ctaD = cuentasDisponibles.find(c => c.id == selectDestino.value);

        if (ctaO) {
            lblSaldoOrigen.textContent = formatoMoneda.format(ctaO.saldo);
        } else {
            lblSaldoOrigen.textContent = '$ 0,00';
        }

        if (ctaD) {
            lblSaldoDestino.textContent = formatoMoneda.format(ctaD.saldo);
        } else {
            lblSaldoDestino.textContent = '$ 0,00';
        }

        validarImporteYSaldo();
    }

    function validarImporteYSaldo() {
        const ctaO = cuentasDisponibles.find(c => c.id == selectOrigen.value);
        const imp = parseFloat(inputImporte.value) || 0;

        if (ctaO && imp > ctaO.saldo) {
            alertaSaldo.classList.remove('d-none');
            inputImporte.classList.add('is-invalid');
        } else {
            alertaSaldo.classList.add('d-none');
            inputImporte.classList.remove('is-invalid');
        }
    }

    selectOrigen.addEventListener('change', function () {
        if (selectOrigen.value && selectOrigen.value === selectDestino.value) {
            Swal.fire('Atención', 'La cuenta de origen y destino no pueden ser iguales (Regla RT14)', 'warning');
            selectOrigen.value = '';
        }
        actualizarSaldosVisuales();
    });

    selectDestino.addEventListener('change', function () {
        if (selectDestino.value && selectDestino.value === selectOrigen.value) {
            Swal.fire('Atención', 'La cuenta de origen y destino no pueden ser iguales (Regla RT14)', 'warning');
            selectDestino.value = '';
        }
        actualizarSaldosVisuales();
    });

    inputImporte.addEventListener('input', validarImporteYSaldo);

    // 3. Inicializar DataTables
    function inicializarDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaTransferencias')) {
            $('#tablaTransferencias').DataTable().destroy();
        }

        tablaTransferencias = $('#tablaTransferencias').DataTable({
            processing: true,
            serverSide: false,
            responsive: true,
            ajax: {
                url: AJAX_URL,
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.desde = $('#filtroDesde').val();
                    d.hasta = $('#filtroHasta').val();
                    d.cuenta_id = $('#filtroCuenta').val();
                    d.estado_id = $('#filtroEstado').val();
                },
                dataSrc: function (json) {
                    return json.data || [];
                }
            },
            columns: [
                { data: 'f_emision' },
                { data: 'numero' },
                { data: 'sucursal' },
                { data: 'cuenta_origen' },
                { data: 'cuenta_destino' },
                { data: 'importe', className: 'text-end fw-bold' },
                { data: 'observaciones' },
                { data: 'estado' },
                { data: 'acciones', className: 'text-center', orderable: false }
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
            order: [[0, 'desc']]
        });
    }


    // 4. Modal Nueva Transferencia
    const modalTransferencia = new bootstrap.Modal(document.getElementById('modalTransferencia'));

    document.getElementById('btnNuevaTransferencia')?.addEventListener('click', function () {
        document.getElementById('formTransferencia').reset();
        document.getElementById('transFecha').value = new Date().toISOString().split('T')[0];
        actualizarSaldosVisuales();
        cargarMaestros(); // Refrescar saldos vivos
        modalTransferencia.show();
    });

    // Confirmar Registro de Transferencia
    document.getElementById('btnConfirmarTransferencia').addEventListener('click', function () {
        const origenId = selectOrigen.value;
        const destinoId = selectDestino.value;
        const importe = parseFloat(inputImporte.value) || 0;
        const fecha = document.getElementById('transFecha').value;
        const sucursalId = document.getElementById('transSucursal').value;
        const observaciones = document.getElementById('transObservaciones').value.trim();

        if (!origenId || !destinoId) {
            Swal.fire('Atención', 'Seleccione tanto la cuenta de origen como la de destino', 'warning');
            return;
        }

        if (origenId === destinoId) {
            Swal.fire('Atención', 'La cuenta de origen y destino no pueden ser iguales', 'warning');
            return;
        }

        if (importe <= 0) {
            Swal.fire('Atención', 'Ingrese un importe válido mayor a cero', 'warning');
            return;
        }

        const ctaO = cuentasDisponibles.find(c => c.id == origenId);
        if (ctaO && importe > ctaO.saldo) {
            Swal.fire('Saldo Insuficiente', `La cuenta de origen solo dispone de ${formatoMoneda.format(ctaO.saldo)}`, 'error');
            return;
        }

        Swal.fire({
            title: '¿Confirmar transferencia interna?',
            html: `Se transferirán <strong>${formatoMoneda.format(importe)}</strong> desde <strong>${ctaO.nombre}</strong> hacia la cuenta destino con asiento contable automático.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, transferir',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (result.isConfirmed) {
                fetch(`${AJAX_URL}?accion=guardar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        f_movimiento: fecha,
                        sucursal_id: sucursalId,
                        cuenta_origen_id: origenId,
                        cuenta_destino_id: destinoId,
                        importe: importe,
                        observaciones: observaciones
                    })
                })
                    .then(res => res.json())
                    .then(resp => {
                        if (resp.success) {
                            modalTransferencia.hide();
                            Swal.fire('Éxito', resp.mensaje, 'success');
                            tablaTransferencias.ajax.reload();
                            cargarMaestros();
                        } else {
                            Swal.fire('Error', resp.error || 'No se pudo registrar la transferencia', 'error');
                        }
                    })
                    .catch(err => {
                        Swal.fire('Error', 'Fallo de comunicación con el servidor', 'error');
                    });
            }
        });
    });

    // 5. Anulación de Transferencia
    $('#tablaTransferencias').on('click', '.btn-anular', function () {
        const id = $(this).data('id');
        const nro = $(this).data('nro');

        Swal.fire({
            title: `¿Anular Transferencia ${nro}?`,
            text: 'Se revertirán de inmediato los saldos en ambas cuentas y se anulará el asiento contable.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, anular',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (result.isConfirmed) {
                fetch(`${AJAX_URL}?accion=anular`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ comprobante_id: id })
                })
                    .then(res => res.json())
                    .then(resp => {
                        if (resp.success) {
                            Swal.fire('Anulado', resp.mensaje, 'success');
                            tablaTransferencias.ajax.reload();
                            cargarMaestros();
                        } else {
                            Swal.fire('Error', resp.error || 'No se pudo anular la transferencia', 'error');
                        }
                    })
                    .catch(err => {
                        Swal.fire('Error', 'Fallo de comunicación con el servidor', 'error');
                    });
            }
        });
    });

    // 6. Filtros y Recarga
    document.getElementById('btnFiltrar').addEventListener('click', function () {
        tablaTransferencias.ajax.reload();
    });

    document.getElementById('btnRecargar').addEventListener('click', function () {
        tablaTransferencias.ajax.reload();
        cargarMaestros();
    });

    // Inicialización
    cargarMaestros();
    inicializarDataTable();
});
