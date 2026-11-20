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
                    const selectOtroSuc = document.getElementById('otroSucursal');
                    selectSuc.innerHTML = '';
                    if (selectOtroSuc) selectOtroSuc.innerHTML = '';
                    sucursales.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s.sucursal_id;
                        opt.textContent = s.sucursal;
                        selectSuc.appendChild(opt);
                        if (selectOtroSuc) selectOtroSuc.appendChild(opt.cloneNode(true));
                    });

                    // Poblar combos de Otro Ingreso / Egreso
                    const otroCuentaTes = document.getElementById('otroCuentaTes');
                    if (otroCuentaTes) {
                        otroCuentaTes.innerHTML = '<option value="">-- Seleccionar Cuenta --</option>';
                        cuentasDisponibles.forEach(c => {
                            const opt = document.createElement('option');
                            opt.value = c.id;
                            opt.textContent = `${c.tipo === 'BANCO' ? '🏦 ' : '💵 '}${c.nombre} (${formatoMoneda.format(c.saldo)})`;
                            otroCuentaTes.appendChild(opt);
                        });
                    }
                    const otroCuentaCont = document.getElementById('otroCuentaCont');
                    if (otroCuentaCont && resp.data.cuentas_contables) {
                        otroCuentaCont.innerHTML = '<option value="">-- Seleccionar Cuenta Contable --</option>';
                        resp.data.cuentas_contables.forEach(cc => {
                            const opt = document.createElement('option');
                            opt.value = cc.cont_cuenta_id;
                            opt.textContent = `${cc.codigo} - ${cc.nombre}`;
                            otroCuentaCont.appendChild(opt);
                        });
                    }
                    const otroEntidad = document.getElementById('otroEntidad');
                    if (otroEntidad && resp.data.entidades) {
                        otroEntidad.innerHTML = '<option value="0">-- Sin entidad específica --</option>';
                        resp.data.entidades.forEach(ent => {
                            const opt = document.createElement('option');
                            opt.value = ent.entidad_id;
                            opt.textContent = ent.cuit ? `${ent.entidad_nombre} (${ent.cuit})` : ent.entidad_nombre;
                            otroEntidad.appendChild(opt);
                        });
                    }
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
        document.getElementById('transFecha').value = Fmt.hoyISO();
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

    // 6. Filtros, Recarga y Solapa Otros Ingresos / Egresos
    let tablaOtros = null;
    function inicializarTablaOtros() {
        if (!document.getElementById('tablaOtrosMovimientos')) return;
        if ($.fn.DataTable.isDataTable('#tablaOtrosMovimientos')) {
            $('#tablaOtrosMovimientos').DataTable().destroy();
        }
        tablaOtros = $('#tablaOtrosMovimientos').DataTable({
            processing: true, serverSide: false, responsive: true,
            ajax: {
                url: AJAX_URL, type: 'GET',
                data: function (d) {
                    d.accion = 'listar_otros';
                    d.desde = $('#filtroDesde').val();
                    d.hasta = $('#filtroHasta').val();
                    d.cuenta_id = $('#filtroCuenta').val();
                    d.estado_id = $('#filtroEstado').val();
                },
                dataSrc: json => json.data || []
            },
            columns: [
                { data: 'f_emision' },
                { data: 'numero', className: 'fw-semibold' },
                { data: 'tipo_movimiento', render: v => `<span class="badge ${v === 'INGRESO' ? 'bg-success' : 'bg-danger'}">${v}</span>` },
                { data: 'entidad_nombre', render: v => v || '<span class="text-muted">-</span>' },
                { data: 'cuenta_nombre' },
                { data: 'importe_total', className: 'text-end fw-bold', render: v => formatoMoneda.format(v) },
                { data: 'observaciones', render: (v, t, r) => v + (r.observacion_aprobacion ? `<div class="small text-warning">Obs: ${r.observacion_aprobacion}</div>` : '') },
                { data: 'estado_registro', render: (v, t, r) => {
                    const cls = r.estado_id === 5 ? 'bg-success' : (r.estado_id === 4 ? 'bg-warning text-dark' : (r.estado_id === 3 ? 'bg-secondary' : 'bg-danger'));
                    return `<span class="badge ${cls}">${v}</span>${r.conciliado ? ' <span class="badge bg-info text-dark">Conc.</span>' : ''}`;
                }},
                { data: null, orderable: false, className: 'text-center text-nowrap', render: (d, t, r) => {
                    const btns = [];
                    if (r.puede_ver) btns.push(`<a href="tesoreria_comprobante_imprimir.php?id=${r.comprobante_id}" target="_blank" class="btn btn-xs btn-outline-secondary" title="Ver / Imprimir comprobante soporte"><i class="fas fa-print"></i></a>`);
                    if (r.puede_editar) btns.push(`<button type="button" class="btn btn-xs btn-outline-primary btn-editar-otro" data-id="${r.comprobante_id}" title="Editar borrador"><i class="fas fa-edit"></i></button>`);
                    if (r.puede_enviar_aprobacion) btns.push(`<button type="button" class="btn btn-xs btn-outline-warning btn-enviar-aprob-otro" data-id="${r.comprobante_id}" title="Enviar a aprobación"><i class="fas fa-paper-plane"></i></button>`);
                    if (r.puede_confirmar) btns.push(`<button type="button" class="btn btn-xs btn-success btn-confirmar-otro" data-id="${r.comprobante_id}" title="Confirmar e impactar caja"><i class="fas fa-check"></i></button>`);
                    if (r.puede_aprobar) btns.push(`<button type="button" class="btn btn-xs btn-success btn-aprobar-otro" data-id="${r.comprobante_id}" title="Aprobar e impactar caja"><i class="fas fa-thumbs-up"></i></button>`);
                    if (r.puede_rechazar) btns.push(`<button type="button" class="btn btn-xs btn-outline-warning btn-rechazar-otro" data-id="${r.comprobante_id}" title="Rechazar y devolver a borrador"><i class="fas fa-thumbs-down"></i></button>`);
                    if (r.puede_eliminar) btns.push(`<button type="button" class="btn btn-xs btn-outline-danger btn-eliminar-otro" data-id="${r.comprobante_id}" title="Eliminar borrador"><i class="fas fa-trash"></i></button>`);
                    if (r.puede_anular) btns.push(`<button type="button" class="btn btn-xs btn-outline-danger btn-anular-otro" data-id="${r.comprobante_id}" title="Anular comprobante"><i class="fas fa-ban"></i></button>`);
                    return `<div class="btn-group btn-group-sm">${btns.join('')}</div>`;
                }}
            ],
            order: [[0, 'desc']]
        });
    }

    const modalOtroEl = document.getElementById('modalOtroMovimiento');
    const btnNuevoOtro = document.getElementById('btnNuevoOtroMovimiento');
    if (btnNuevoOtro && modalOtroEl) {
        btnNuevoOtro.addEventListener('click', function () {
            document.getElementById('formOtroMovimiento').reset();
            const hid = document.getElementById('otroComprobanteId');
            if (hid) hid.value = '0';
            bootstrap.Modal.getOrCreateInstance(modalOtroEl).show();
        });
    }

    function guardarOtroMovimiento(estadoInicial, btn) {
        const payload = {
            comprobante_id: parseInt(document.getElementById('otroComprobanteId')?.value || '0', 10) || 0,
            tipo_movimiento: document.getElementById('otroTipo').value,
            f_movimiento: document.getElementById('otroFecha').value,
            sucursal_id: document.getElementById('otroSucursal').value,
            entidad_id: parseInt(document.getElementById('otroEntidad')?.value || '0', 10) || 0,
            tesoreria_cuenta_id: document.getElementById('otroCuentaTes').value,
            cont_cuenta_contrapartida_id: document.getElementById('otroCuentaCont').value,
            importe: parseFloat(document.getElementById('otroImporte').value) || 0,
            concepto: document.getElementById('otroConcepto').value.trim(),
            estado_inicial: estadoInicial
        };
        if (!payload.tesoreria_cuenta_id || !payload.cont_cuenta_contrapartida_id || payload.importe <= 0 || !payload.concepto) {
            Swal.fire('Revisá los datos', 'Completá cuenta de tesorería, contrapartida contable, importe y concepto.', 'warning');
            return;
        }
        if (btn) btn.disabled = true;
        fetch(`${AJAX_URL}?accion=guardar_otro`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
        }).then(r => r.json()).then(resp => {
            if (btn) btn.disabled = false;
            if (resp.success) {
                bootstrap.Modal.getOrCreateInstance(modalOtroEl).hide();
                Swal.fire('Comprobante guardado', resp.message || 'Comprobante guardado.', 'success');
                if (tablaOtros) tablaOtros.ajax.reload();
                cargarMaestros();
            } else {
                Swal.fire('Error', resp.error || 'No se pudo guardar.', 'error');
            }
        }).catch(() => { if (btn) btn.disabled = false; Swal.fire('Error', 'Fallo de comunicación.', 'error'); });
    }

    const btnGuardarBorr = document.getElementById('btnGuardarOtroBorrador');
    if (btnGuardarBorr) btnGuardarBorr.addEventListener('click', () => guardarOtroMovimiento(3, btnGuardarBorr));
    const btnConfOtro = document.getElementById('btnConfirmarOtroMovimiento');
    if (btnConfOtro) btnConfOtro.addEventListener('click', () => guardarOtroMovimiento(4, btnConfOtro));

    function ejecutarAccionOtro(accion, payload, tituloOk) {
        fetch(`${AJAX_URL}?accion=${accion}`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
        }).then(r => r.json()).then(resp => {
            if (resp.success) {
                Swal.fire(tituloOk, resp.message || 'Operación realizada.', 'success');
                if (tablaOtros) tablaOtros.ajax.reload();
                cargarMaestros();
            } else {
                Swal.fire('Error', resp.error || 'No se pudo completar la operación.', 'error');
            }
        }).catch(() => Swal.fire('Error', 'Fallo de comunicación.', 'error'));
    }

    $('#tablaOtrosMovimientos tbody').on('click', '.btn-editar-otro', function () {
        const id = $(this).data('id');
        fetch(`${AJAX_URL}?accion=obtener_otro&comprobante_id=${id}`).then(r => r.json()).then(resp => {
            if (!resp.success || !resp.data) return Swal.fire('Error', resp.error || 'No se pudo cargar.', 'error');
            const d = resp.data;
            document.getElementById('otroComprobanteId').value = d.comprobante_id;
            document.getElementById('otroTipo').value = d.tipo_movimiento;
            document.getElementById('otroFecha').value = d.f_emision;
            document.getElementById('otroSucursal').value = d.sucursal_id;
            if (document.getElementById('otroEntidad')) document.getElementById('otroEntidad').value = d.entidad_id || 0;
            document.getElementById('otroCuentaTes').value = d.tesoreria_cuenta_id;
            document.getElementById('otroCuentaCont').value = d.cont_cuenta_contrapartida_id;
            document.getElementById('otroImporte').value = d.importe_total;
            document.getElementById('otroConcepto').value = d.observaciones;
            bootstrap.Modal.getOrCreateInstance(modalOtroEl).show();
        });
    });

    $('#tablaOtrosMovimientos tbody').on('click', '.btn-enviar-aprob-otro', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: '¿Enviar a aprobación?',
            text: 'El comprobante quedará Pendiente de Aprobación hasta que un responsable lo apruebe.',
            icon: 'question', showCancelButton: true, confirmButtonText: 'Enviar', cancelButtonText: 'Cancelar'
        }).then(res => { if (res.isConfirmed) ejecutarAccionOtro('enviar_aprobacion_otro', { comprobante_id: id }, 'Enviado'); });
    });

    $('#tablaOtrosMovimientos tbody').on('click', '.btn-confirmar-otro', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: '¿Confirmar comprobante?',
            text: 'Se impactará el saldo de tesorería y se generará el asiento contable automático.',
            icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, confirmar', cancelButtonText: 'Cancelar'
        }).then(res => { if (res.isConfirmed) ejecutarAccionOtro('confirmar_otro', { comprobante_id: id }, 'Confirmado'); });
    });

    $('#tablaOtrosMovimientos tbody').on('click', '.btn-aprobar-otro', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: '¿Aprobar comprobante?',
            text: 'Al aprobar se impactará el saldo de tesorería y se generará el asiento contable automático.',
            icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, aprobar', cancelButtonText: 'Cancelar'
        }).then(res => { if (res.isConfirmed) ejecutarAccionOtro('aprobar_otro', { comprobante_id: id }, 'Aprobado'); });
    });

    $('#tablaOtrosMovimientos tbody').on('click', '.btn-rechazar-otro', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Rechazar comprobante',
            input: 'text',
            inputLabel: 'Motivo (opcional)',
            inputPlaceholder: 'Ej: Revisar importe o cuenta contable',
            showCancelButton: true, confirmButtonText: 'Devolver a Borrador', cancelButtonText: 'Cancelar'
        }).then(res => { if (res.isConfirmed) ejecutarAccionOtro('rechazar_otro', { comprobante_id: id, motivo: res.value || '' }, 'Devuelto a Borrador'); });
    });

    $('#tablaOtrosMovimientos tbody').on('click', '.btn-eliminar-otro', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: '¿Eliminar borrador?',
            text: 'Este comprobante todavía no impactó caja ni contabilidad.',
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar'
        }).then(res => { if (res.isConfirmed) ejecutarAccionOtro('eliminar_otro', { comprobante_id: id }, 'Eliminado'); });
    });

    $('#tablaOtrosMovimientos tbody').on('click', '.btn-anular-otro', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: '¿Anular comprobante confirmado?',
            text: 'Se revertirá el saldo en tesorería (y la conciliación si existiera) y se anulará el asiento contable.',
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, anular', cancelButtonText: 'Cancelar'
        }).then(result => {
            if (!result.isConfirmed) return;
            ejecutarAccionOtro('anular_otro', { comprobante_id: id }, 'Anulado');
        });
    });

    document.getElementById('btnFiltrar').addEventListener('click', function () {
        tablaTransferencias.ajax.reload();
        if (tablaOtros) tablaOtros.ajax.reload();
    });

    document.getElementById('btnRecargar').addEventListener('click', function () {
        tablaTransferencias.ajax.reload();
        if (tablaOtros) tablaOtros.ajax.reload();
        cargarMaestros();
    });

    // Inicialización
    cargarMaestros();
    inicializarDataTable();
    inicializarTablaOtros();

    // Si viene con ?tab=otros (ej. desde el link del Asistente IA), abrir directo esa solapa
    const paramsUrl = new URLSearchParams(window.location.search);
    if (paramsUrl.get('tab') === 'otros' || window.location.hash === '#paneOtros') {
        const tabOtros = document.getElementById('tabOtrosBtn');
        if (tabOtros) bootstrap.Tab.getOrCreateInstance(tabOtros).show();
    }
});
