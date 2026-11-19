/**
 * obligaciones_rendiciones.js
 *
 * Frontend interactivo para Obligaciones Impositivas y Rendición de Gastos
 * Sistema gestion_multipyme - Módulo Tesorería
 */

document.addEventListener('DOMContentLoaded', function () {
    let tablaObligaciones = null;
    let tablaRendiciones = null;
    let maestros = {
        cuentas: [],
        sucursales: [],
        entidades: [],
        categorias_gasto: [],
        anticipos_pendientes: []
    };

    const AJAX_URL = 'obligaciones_rendiciones_ajax.php';

    const formatoMoneda = { format: (v) => Fmt.moneda(v) };

    // 1. Cargar Maestros
    function cargarMaestros() {
        fetch(`${AJAX_URL}?accion=maestros`)
            .then(res => res.json())
            .then(resp => {
                if (resp.success && resp.data) {
                    maestros = resp.data;

                    // Poblar Cuentas de Tesorería en modales
                    const selectsCuentas = ['pagarCuentaId', 'antCuenta', 'reemCuentaId'];
                    selectsCuentas.forEach(id => {
                        const el = document.getElementById(id);
                        if (!el) return;
                        el.innerHTML = '<option value="">-- Seleccionar Cuenta --</option>';
                        maestros.cuentas.forEach(c => {
                            const opt = document.createElement('option');
                            opt.value = c.id;
                            opt.textContent = `${c.nombre} [${c.tipo}] (${formatoMoneda.format(c.saldo)})`;
                            el.appendChild(opt);
                        });
                    });

                    // Poblar Organismos / Entidades
                    const selOrg = document.getElementById('liqOrganismo');
                    if (selOrg) {
                        selOrg.innerHTML = '<option value="">-- Seleccionar Organismo / Ente --</option>';
                        maestros.entidades.forEach(e => {
                            const opt = document.createElement('option');
                            opt.value = e.entidad_id;
                            opt.textContent = e.entidad_nombre;
                            selOrg.appendChild(opt);
                        });
                    }

                    // Poblar Responsables / Personal
                    const selectsResp = ['antResponsable', 'rendResponsable'];
                    selectsResp.forEach(id => {
                        const el = document.getElementById(id);
                        if (!el) return;
                        el.innerHTML = '<option value="">-- Seleccionar Personal / Empleado --</option>';
                        maestros.entidades.forEach(e => {
                            const opt = document.createElement('option');
                            opt.value = e.entidad_id;
                            opt.textContent = e.entidad_nombre;
                            el.appendChild(opt);
                        });
                    });

                    // Poblar Sucursales
                    const selSuc = document.getElementById('liqSucursal');
                    if (selSuc) {
                        selSuc.innerHTML = '';
                        maestros.sucursales.forEach(s => {
                            const opt = document.createElement('option');
                            opt.value = s.sucursal_id;
                            opt.textContent = s.sucursal;
                            selSuc.appendChild(opt);
                        });
                    }

                    // Poblar Anticipos pendientes en rendición
                    const selAnt = document.getElementById('rendAnticipo');
                    if (selAnt) {
                        selAnt.innerHTML = '<option value="">-- Sin Anticipo Previo --</option>';
                        maestros.anticipos_pendientes.forEach(a => {
                            const opt = document.createElement('option');
                            opt.value = a.rendicion_anticipo_id;
                            opt.dataset.monto = a.importe_pendiente;
                            opt.textContent = `Anticipo #${a.comprobante_pv}-${a.comprobante_nro}: ${a.entidad_nombre} (${formatoMoneda.format(a.importe_pendiente)})`;
                            selAnt.appendChild(opt);
                        });
                    }
                }
            })
            .catch(err => console.error("Error al cargar maestros:", err));
    }

    // 2. DataTables de Obligaciones Impositivas
    function inicializarTablaObligaciones() {
        if ($.fn.DataTable.isDataTable('#tablaObligaciones')) {
            $('#tablaObligaciones').DataTable().destroy();
        }

        tablaObligaciones = $('#tablaObligaciones').DataTable({
            processing: true,
            serverSide: false,
            responsive: true,
            ajax: {
                url: AJAX_URL,
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar_obligaciones';
                    d.periodo = $('#filtroPeriodo').val();
                    d.desde = $('#filtroVtoDesde').val();
                    d.hasta = $('#filtroVtoHasta').val();
                    d.estado_id = $('#filtroEstadoImp').val();
                },
                dataSrc: function (json) {
                    return json.data || [];
                }
            },
            columns: [
                { data: 'comprobante_nro' },
                { data: 'periodo_fiscal' },
                { data: 'descripcion' },
                { data: 'organismo' },
                { data: 'f_vto' },
                { data: 'nro_vep' },
                { data: 'importe_total', className: 'text-end' },
                { data: 'importe_pendiente', className: 'text-end' },
                { data: 'estado', className: 'text-center' },
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
            order: [[4, 'asc']]
        });
    }

    // 3. DataTables de Rendiciones
    function inicializarTablaRendiciones() {
        if ($.fn.DataTable.isDataTable('#tablaRendiciones')) {
            $('#tablaRendiciones').DataTable().destroy();
        }

        tablaRendiciones = $('#tablaRendiciones').DataTable({
            processing: true,
            serverSide: false,
            responsive: true,
            ajax: {
                url: AJAX_URL,
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar_rendiciones';
                },
                dataSrc: function (json) {
                    return json.data || [];
                }
            },
            columns: [
                { data: 'comprobante_nro' },
                { data: 'f_emision' },
                { data: 'responsable' },
                { data: 'descripcion' },
                { data: 'importe_total', className: 'text-end' },
                { data: 'importe_anticipos', className: 'text-end' },
                { data: 'importe_a_reembolsar', className: 'text-end' },
                { data: 'estado', className: 'text-center' },
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
                 '<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>'
        });
    }


    // 4. Modales de Impuestos
    const modalNuevaObligacion = new bootstrap.Modal(document.getElementById('modalNuevaObligacion'));
    document.getElementById('btnNuevaObligacion').addEventListener('click', function () {
        document.getElementById('formNuevaObligacion').reset();
        document.getElementById('liqPeriodo').value = Fmt.hoyISO().slice(0, 7);
        modalNuevaObligacion.show();
    });

    document.getElementById('btnConfirmarObligacion').addEventListener('click', function () {
        const orgId = $('#liqOrganismo').val();
        const per = $('#liqPeriodo').val().trim();
        const vto = $('#liqVto').val();
        const desc = $('#liqDescripcion').val().trim();
        const imp = parseFloat($('#liqImporte').val()) || 0;
        const vep = $('#liqVep').val().trim();
        const ddjj = $('#liqDdjj').val().trim();
        const suc = $('#liqSucursal').val();

        if (!orgId || !per || !vto || !desc || imp <= 0) {
            Swal.fire('Atención', 'Complete todos los campos obligatorios y un importe válido', 'warning');
            return;
        }

        fetch(`${AJAX_URL}?accion=guardar_obligacion`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                entidad_id: orgId,
                periodo_fiscal: per,
                f_vto: vto,
                descripcion: desc,
                importe_total: imp,
                nro_vep: vep,
                nro_ddjj: ddjj,
                sucursal_id: suc
            })
        })
            .then(res => res.json())
            .then(resp => {
                if (resp.success) {
                    modalNuevaObligacion.hide();
                    Swal.fire('Éxito', resp.mensaje, 'success');
                    tablaObligaciones.ajax.reload();
                } else {
                    Swal.fire('Error', resp.error || 'No se pudo guardar la obligación', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Fallo de comunicación con el servidor', 'error'));
    });

    // Pagar Obligación / VEP
    const modalPagarObligacion = new bootstrap.Modal(document.getElementById('modalPagarObligacion'));
    $('#tablaObligaciones').on('click', '.btn-pagar-imp', function () {
        const btn = $(this);
        $('#pagarObligacionId').val(btn.data('id'));
        $('#pagarDesc').text(btn.data('desc'));
        $('#pagarNro').text(btn.data('nro'));
        $('#pagarVep').text(btn.data('vep') || '-');
        const pendiente = parseFloat(btn.data('pendiente')) || 0;
        $('#pagarPendiente').text(formatoMoneda.format(pendiente));
        $('#pagarImporte').val(pendiente.toFixed(2));
        $('#pagarFecha').val(Fmt.hoyISO());
        modalPagarObligacion.show();
    });

    document.getElementById('btnConfirmarPagoImp').addEventListener('click', function () {
        const id = $('#pagarObligacionId').val();
        const cta = $('#pagarCuentaId').val();
        const fPago = $('#pagarFecha').val();
        const imp = parseFloat($('#pagarImporte').val()) || 0;
        const nroOp = $('#pagarNroOperacion').val().trim();

        if (!cta || imp <= 0) {
            Swal.fire('Atención', 'Seleccione la cuenta bancaria de egreso y el importe a pagar', 'warning');
            return;
        }

        Swal.fire({
            title: '¿Confirmar pago de obligación fiscal?',
            text: 'Se registrará el egreso bancario y la cancelación del VEP.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, pagar',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (result.isConfirmed) {
                fetch(`${AJAX_URL}?accion=pagar_obligacion`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        obligacion_id: id,
                        tesoreria_cuenta_id: cta,
                        f_pago: fPago,
                        importe_pago: imp,
                        nro_operacion: nroOp
                    })
                })
                    .then(res => res.json())
                    .then(resp => {
                        if (resp.success) {
                            modalPagarObligacion.hide();
                            Swal.fire('Éxito', resp.mensaje, 'success');
                            tablaObligaciones.ajax.reload();
                            cargarMaestros();
                        } else {
                            Swal.fire('Error', resp.error || 'No se pudo pagar la obligación', 'error');
                        }
                    })
                    .catch(() => Swal.fire('Error', 'Fallo de comunicación con el servidor', 'error'));
            }
        });
    });

    // 5. Modales de Rendiciones
    // Anticipo
    const modalNuevoAnticipo = new bootstrap.Modal(document.getElementById('modalNuevoAnticipo'));
    document.getElementById('btnNuevoAnticipo')?.addEventListener('click', function () {
        document.getElementById('formNuevoAnticipo').reset();
        document.getElementById('antFecha').value = Fmt.hoyISO();
        modalNuevoAnticipo.show();
    });

    document.getElementById('btnConfirmarAnticipo').addEventListener('click', function () {
        const respId = $('#antResponsable').val();
        const ctaId = $('#antCuenta').val();
        const fecha = $('#antFecha').val();
        const imp = parseFloat($('#antImporte').val()) || 0;
        const desc = $('#antDescripcion').val().trim();

        if (!respId || !ctaId || imp <= 0) {
            Swal.fire('Atención', 'Complete el responsable, la cuenta de origen y un importe válido', 'warning');
            return;
        }

        fetch(`${AJAX_URL}?accion=guardar_anticipo`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                entidad_id: respId,
                tesoreria_cuenta_id: ctaId,
                f_emision: fecha,
                importe_total: imp,
                descripcion: desc
            })
        })
            .then(res => res.json())
            .then(resp => {
                if (resp.success) {
                    modalNuevoAnticipo.hide();
                    Swal.fire('Éxito', resp.mensaje, 'success');
                    cargarMaestros();
                } else {
                    Swal.fire('Error', resp.error || 'No se pudo registrar el anticipo', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Fallo de comunicación con el servidor', 'error'));
    });

    // Rendición con Gastos Dinámicos
    const modalNuevaRendicion = new bootstrap.Modal(document.getElementById('modalNuevaRendicion'));
    const tbodyGastos = document.getElementById('tbodyDetalleGastos');

    function recalcularTotalesRendicion() {
        let total = 0;
        document.querySelectorAll('.input-gasto-importe').forEach(inp => {
            total += parseFloat(inp.value) || 0;
        });

        const optAnt = document.querySelector('#rendAnticipo option:checked');
        const montoAnt = optAnt ? (parseFloat(optAnt.dataset.monto) || 0) : 0;
        const aplicado = Math.min(montoAnt, total);
        const aReembolsar = Math.max(0, total - aplicado);

        document.getElementById('rendTotalGastos').textContent = formatoMoneda.format(total);
        document.getElementById('rendMontoAnticipo').textContent = formatoMoneda.format(aplicado);
        document.getElementById('rendSaldoReembolso').textContent = formatoMoneda.format(aReembolsar);
    }

    function agregarFilaGasto() {
        const tr = document.createElement('tr');
        let optionsCat = '';
        maestros.categorias_gasto.forEach(c => {
            optionsCat += `<option value="${c.categoria_gasto_id}" data-cuenta="${c.cont_cuenta_id || 0}">${c.categoria_gasto}</option>`;
        });

        tr.innerHTML = `
            <td><input type="date" class="form-control form-control-sm input-gasto-fecha" value="${Fmt.hoyISO()}"></td>
            <td><select class="form-select form-select-sm input-gasto-cat">${optionsCat}</select></td>
            <td><input type="text" class="form-control form-control-sm input-gasto-prov" placeholder="Comercio/Prov"></td>
            <td><input type="text" class="form-control form-control-sm input-gasto-ticket" placeholder="N° Ticket"></td>
            <td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm text-end fw-bold input-gasto-importe" placeholder="0.00"></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-fila"><i class="fas fa-trash"></i></button></td>
        `;

        tr.querySelector('.input-gasto-importe').addEventListener('input', recalcularTotalesRendicion);
        tr.querySelector('.btn-eliminar-fila').addEventListener('click', function () {
            tr.remove();
            recalcularTotalesRendicion();
        });

        tbodyGastos.appendChild(tr);
    }

    document.getElementById('btnAgregarFilaGasto').addEventListener('click', agregarFilaGasto);
    document.getElementById('rendAnticipo').addEventListener('change', recalcularTotalesRendicion);

    document.getElementById('btnNuevaRendicion')?.addEventListener('click', function () {
        document.getElementById('formNuevaRendicion').reset();
        document.getElementById('rendFecha').value = Fmt.hoyISO();
        tbodyGastos.innerHTML = '';
        agregarFilaGasto(); // Agregar al menos una fila
        recalcularTotalesRendicion();
        modalNuevaRendicion.show();
    });

    document.getElementById('btnConfirmarRendicion').addEventListener('click', function () {
        const respId = $('#rendResponsable').val();
        const fecha = $('#rendFecha').val();
        const antId = $('#rendAnticipo').val();
        const desc = $('#rendDescripcion').val().trim();

        if (!respId) {
            Swal.fire('Atención', 'Seleccione el responsable de la rendición', 'warning');
            return;
        }

        const items = [];
        tbodyGastos.querySelectorAll('tr').forEach(tr => {
            const fGasto = tr.querySelector('.input-gasto-fecha').value;
            const catId = tr.querySelector('.input-gasto-cat').value;
            const prov = tr.querySelector('.input-gasto-prov').value.trim();
            const ticket = tr.querySelector('.input-gasto-ticket').value.trim();
            const imp = parseFloat(tr.querySelector('.input-gasto-importe').value) || 0;

            if (imp > 0) {
                items.push({
                    f_gasto: fGasto,
                    categoria_gasto_id: catId,
                    proveedor_nombre: prov,
                    comprobante_externo_nro: ticket,
                    importe: imp
                });
            }
        });

        if (items.length === 0) {
            Swal.fire('Atención', 'Ingrese al menos un comprobante con importe mayor a cero', 'warning');
            return;
        }

        fetch(`${AJAX_URL}?accion=guardar_rendicion`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                entidad_id: respId,
                f_emision: fecha,
                anticipo_id: antId,
                descripcion: desc,
                items: items
            })
        })
            .then(res => res.json())
            .then(resp => {
                if (resp.success) {
                    modalNuevaRendicion.hide();
                    Swal.fire('Éxito', resp.mensaje, 'success');
                    tablaRendiciones.ajax.reload();
                    cargarMaestros();
                } else {
                    Swal.fire('Error', resp.error || 'No se pudo guardar la rendición', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Fallo de comunicación con el servidor', 'error'));
    });

    // Reembolso de Rendición
    const modalReembolso = new bootstrap.Modal(document.getElementById('modalReembolso'));
    $('#tablaRendiciones').on('click', '.btn-reembolsar-rend', function () {
        const btn = $(this);
        $('#reemRendicionId').val(btn.data('id'));
        $('#reemNro').text(btn.data('nro'));
        const monto = parseFloat(btn.data('monto')) || 0;
        $('#reemMonto').text(formatoMoneda.format(monto));
        $('#reemFecha').val(Fmt.hoyISO());
        modalReembolso.show();
    });

    document.getElementById('btnConfirmarReembolso').addEventListener('click', function () {
        const id = $('#reemRendicionId').val();
        const cta = $('#reemCuentaId').val();
        const fecha = $('#reemFecha').val();
        const monto = parseFloat($('#reemMonto').text().replace(/[^0-9,-]+/g, "").replace(",", ".")) || 0;

        if (!cta) {
            Swal.fire('Atención', 'Seleccione la cuenta de tesorería para realizar el reembolso', 'warning');
            return;
        }

        fetch(`${AJAX_URL}?accion=reembolsar_rendicion`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                rendicion_id: id,
                tesoreria_cuenta_id: cta,
                f_pago: fecha,
                importe_reembolso: monto
            })
        })
            .then(res => res.json())
            .then(resp => {
                if (resp.success) {
                    modalReembolso.hide();
                    Swal.fire('Éxito', resp.mensaje, 'success');
                    tablaRendiciones.ajax.reload();
                    cargarMaestros();
                } else {
                    Swal.fire('Error', resp.error || 'No se pudo reembolsar la rendición', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Fallo de comunicación con el servidor', 'error'));
    });

    // Filtros y Recargas
    document.getElementById('btnFiltrarImp').addEventListener('click', function () {
        tablaObligaciones.ajax.reload();
    });
    document.getElementById('btnRecargarImp').addEventListener('click', function () {
        tablaObligaciones.ajax.reload();
    });
    document.getElementById('btnRecargarRend').addEventListener('click', function () {
        tablaRendiciones.ajax.reload();
    });

    // Inicializar
    cargarMaestros();
    inicializarTablaObligaciones();
    inicializarTablaRendiciones();
});
