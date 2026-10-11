/**
 * pagos.js
 *
 * Lógica frontend e interactividad para Pagos a Proveedores (Órdenes de Pago OP)
 * Sistema gestion_multipyme - Módulo Tesorería
 */

let maestrosPago = {
    proveedores: [],
    medios_pago: [],
    cuentas_tesoreria: [],
    bancos: [],
    cheques_en_cartera: [],
    chequeras: [],
    impuestos_ret: [],
    sucursales: []
};

let tablaPagosDT = null;
let indiceMedioPago = 0;
let comprobantesProveedor = [];

function getEmpresaId() {
    return typeof EMPRESA_ID !== 'undefined' ? EMPRESA_ID : (window.EMPRESA_ID || 6);
}

function mostrarModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (window.bootstrap && window.bootstrap.Modal) {
        const inst = window.bootstrap.Modal.getOrCreateInstance ? window.bootstrap.Modal.getOrCreateInstance(el) : new window.bootstrap.Modal(el);
        inst.show();
    } else if (window.jQuery && typeof $(el).modal === 'function') {
        $(el).modal('show');
    }
}

function ocultarModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (window.bootstrap && window.bootstrap.Modal) {
        const inst = window.bootstrap.Modal.getInstance ? window.bootstrap.Modal.getInstance(el) : null;
        if (inst) {
            inst.hide();
        } else if (window.bootstrap.Modal.getOrCreateInstance) {
            window.bootstrap.Modal.getOrCreateInstance(el).hide();
        } else if (window.jQuery && typeof $(el).modal === 'function') {
            $(el).modal('hide');
        }
    } else if (window.jQuery && typeof $(el).modal === 'function') {
        $(el).modal('hide');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    inicializarFechasFiltro();
    configurarEventosPagos();
    inicializarDataTablePagos();
    cargarMaestrosPago();
});

/**
 * Inicializa fechas de filtro con el mes actual
 */
function inicializarFechasFiltro() {
    // Por defecto no restringimos fechas para listar todos los comprobantes emitidos de la empresa
    const filtroDesde = document.getElementById('filtroDesde');
    const filtroHasta = document.getElementById('filtroHasta');
    
    if (filtroDesde) filtroDesde.value = '';
    if (filtroHasta) filtroHasta.value = '';
}

/**
 * Carga todos los maestros necesarios para la operación de pagos
 */
async function cargarMaestrosPago() {
    try {
        const empId = typeof EMPRESA_ID !== 'undefined' ? EMPRESA_ID : (window.EMPRESA_ID || '');
        const resp = await fetch('pagos_ajax.php?accion=maestros&empresa_idx=' + empId);
        const res = await resp.json();
        if (res.success && res.data) {
            maestrosPago = res.data;
            poblarSelectProveedores();
            poblarSelectSucursales();
        } else {
            console.error('Error cargando maestros de pagos:', res.error);
        }
    } catch (e) {
        console.error('Excepción al cargar maestros:', e);
    }
}

/**
 * Llena los selects de proveedores
 */
function poblarSelectProveedores() {
    const selectFiltro = document.getElementById('filtroProveedor');
    const selectModal = document.getElementById('selectProveedor');

    if (selectFiltro) {
        let opts = '<option value="">-- Todos los Proveedores --</option>';
        (maestrosPago.proveedores || []).forEach(p => {
            opts += `<option value="${p.entidad_id}">${escapeHtml(p.entidad_nombre)} (CUIT: ${p.cuit || 'S/D'})${(+p.es_cliente === 1 && +p.es_proveedor !== 1) ? ' · cliente (devolución de NC)' : ''}</option>`;
        });
        selectFiltro.innerHTML = opts;
    }

    if (selectModal) {
        let opts = '<option value="">-- Seleccionar Proveedor --</option>';
        (maestrosPago.proveedores || []).forEach(p => {
            opts += `<option value="${p.entidad_id}">${escapeHtml(p.entidad_nombre)} (CUIT: ${p.cuit || 'S/D'})${(+p.es_cliente === 1 && +p.es_proveedor !== 1) ? ' · cliente (devolución de NC)' : ''}</option>`;
        });
        selectModal.innerHTML = opts;
    }
}

/**
 * Llena el select de sucursales en el modal
 */
function poblarSelectSucursales() {
    const select = document.getElementById('selectSucursal');
    if (!select) return;

    let opts = '';
    (maestrosPago.sucursales || []).forEach(s => {
        opts += `<option value="${s.sucursal_id}">${escapeHtml(s.sucursal_nombre)}</option>`;
    });
    select.innerHTML = opts;
}

/**
 * Inicializa el DataTable principal de pagos
 */
function inicializarDataTablePagos() {
    if ($.fn.DataTable.isDataTable('#tablaPagos')) {
        $('#tablaPagos').DataTable().destroy();
    }
    tablaPagosDT = $('#tablaPagos').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: 'pagos_ajax.php',
            type: 'GET',
            dataSrc: function (json) {
                return json.data || (Array.isArray(json) ? json : []);
            },
            data: function (d) {
                d.accion = 'listar';
                d.empresa_idx = typeof EMPRESA_ID !== 'undefined' ? EMPRESA_ID : (window.EMPRESA_ID || '');
                d.desde = document.getElementById('filtroDesde')?.value || '';
                d.hasta = document.getElementById('filtroHasta')?.value || '';
                d.entidad_id = document.getElementById('filtroProveedor')?.value || '';
                d.estado_id = document.getElementById('filtroEstado')?.value || '';
            }
        },
        columns: [
            { data: 'f_emision', className: 'text-center' },
            { data: 'numero', className: 'fw-bold' },
            { data: 'proveedor' },
            { data: 'sucursal' },
            { data: 'total', className: 'text-end' },
            { data: 'saldo_anticipo', className: 'text-center' },
            { data: 'estado', className: 'text-center' },
            { data: 'acciones', className: 'text-center', orderable: false }
        ],
        order: [[0, 'desc']],
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


/**
 * Configura los eventos del DOM
 */
function configurarEventosPagos() {
    const recargarTablaPagos = () => {
        if (tablaPagosDT) {
            tablaPagosDT.ajax.reload();
        }
    };

    document.getElementById('formFiltros')?.addEventListener('submit', function (e) {
        e.preventDefault();
        recargarTablaPagos();
    });

    document.getElementById('btnFiltrar')?.addEventListener('click', function (e) {
        e.preventDefault();
        recargarTablaPagos();
    });
    document.getElementById('btnLimpiarFiltros')?.addEventListener('click', function (e) {
        e.preventDefault();
        if (document.getElementById('filtroDesde')) document.getElementById('filtroDesde').value = '';
        if (document.getElementById('filtroHasta')) document.getElementById('filtroHasta').value = '';
        if (document.getElementById('filtroProveedor')) $(document.getElementById('filtroProveedor')).val('').trigger('change');
        if (document.getElementById('filtroEstado')) document.getElementById('filtroEstado').value = '';
        recargarTablaPagos();
    });
    document.getElementById('btnRecargar')?.addEventListener('click', function (e) {
        e.preventDefault();
        recargarTablaPagos();
    });
    document.getElementById('filtroDesde')?.addEventListener('change', recargarTablaPagos);
    document.getElementById('filtroHasta')?.addEventListener('change', recargarTablaPagos);
    document.getElementById('filtroProveedor')?.addEventListener('change', recargarTablaPagos);
    document.getElementById('filtroEstado')?.addEventListener('change', recargarTablaPagos);

    document.getElementById('btnNuevoPago')?.addEventListener('click', abrirModalNuevoPago);
    document.getElementById('btnAgregarMedio')?.addEventListener('click', () => agregarFilaMedioPago());

    document.getElementById('selectProveedor')?.addEventListener('change', function () {
        cargarComprobantesPendientesProveedor(this.value);
    });

    document.getElementById('checkTodosPendientes')?.addEventListener('change', function () {
        const checks = document.querySelectorAll('.check-pendiente');
        checks.forEach(chk => {
            chk.checked = this.checked;
            const row = chk.closest('tr');
            const input = row.querySelector('.input-imputar');
            if (this.checked) {
                input.value = parseFloat(chk.dataset.pendiente).toFixed(2);
            } else {
                input.value = '0.00';
            }
        });
        recalcularBalancePago();
    });

    document.getElementById('btnAutoImputar')?.addEventListener('click', autoImputarPendientesPago);
    document.getElementById('btnLimpiarImputaciones')?.addEventListener('click', limpiarImputacionesPago);

    document.getElementById('btnGuardarBorrador')?.addEventListener('click', () => guardarPago(false));
    document.getElementById('btnConfirmarEmision')?.addEventListener('click', () => guardarPago(true));

    $('#tablaPagos tbody').on('click', '.btn-accion', function () {
        const id = $(this).data('id');
        const accion = $(this).data('accion');

        if (accion === 'visualizar') {
            verPago(id);
        } else if (accion === 'editar') {
            editarBorradorPago(id);
        } else if (accion === 'confirmar') {
            confirmarPagoDirecto(id);
        } else if (accion === 'anular') {
            abrirModalAnularPago(id);
        } else if (accion === 'eliminar') {
            eliminarBorradorPago(id);
        }
    });

    document.getElementById('formAnular')?.addEventListener('submit', ejecutarAnulacionPago);
    document.getElementById('btnImprimirPago')?.addEventListener('click', () => window.print());
}

/**
 * Abre el modal para un nuevo pago desde cero
 */
function abrirModalNuevoPago() {
    document.getElementById('formPago').reset();
    document.getElementById('pago_id').value = '0';
    document.getElementById('modalPagoLabel').innerHTML = '<i class="fas fa-money-check-alt me-2"></i>Nueva Orden de Pago (OP)';

    const hoy = new Date().toISOString().split('T')[0];
    document.getElementById('inputFechaEmision').value = hoy;
    document.getElementById('inputFechaContable').value = hoy;

    const tbodyMedios = document.getElementById('tbodyMedios');
    tbodyMedios.innerHTML = '';
    indiceMedioPago = 0;
    agregarFilaMedioPago('TRANSFER');

    limpiarSeccionPendientesProveedor();
    recalcularBalancePago();

    mostrarModal('modalPago');
}

/**
 * Limpia la sección de comprobantes pendientes de compra
 */
function limpiarSeccionPendientesProveedor() {
    comprobantesProveedor = [];
    document.getElementById('tbodyPendientes').innerHTML = '';
    document.getElementById('contenedorTablaPendientes').classList.add('d-none');
    document.getElementById('spinnerPendientes').classList.add('d-none');
    document.getElementById('vacioPendientes').classList.remove('d-none');
    document.getElementById('vacioPendientes').innerText = 'Seleccione un proveedor para ver sus facturas pendientes de pago.';
}

/**
 * Carga los comprobantes pendientes del proveedor
 */
async function cargarComprobantesPendientesProveedor(entidadId, imputacionesPrevias = []) {
    if (!entidadId || parseInt(entidadId) <= 0) {
        limpiarSeccionPendientesProveedor();
        return;
    }

    const spinner = document.getElementById('spinnerPendientes');
    const vacio = document.getElementById('vacioPendientes');
    const contenedor = document.getElementById('contenedorTablaPendientes');
    const tbody = document.getElementById('tbodyPendientes');

    spinner.classList.remove('d-none');
    vacio.classList.add('d-none');
    contenedor.classList.add('d-none');
    tbody.innerHTML = '';

    try {
        const empId = getEmpresaId();
        const resp = await fetch(`pagos_ajax.php?accion=comprobantes_pendientes&empresa_idx=${empId}&entidad_id=${entidadId}`);
        const res = await resp.json();

        spinner.classList.add('d-none');

        if (res.success && res.data && res.data.length > 0) {
            comprobantesProveedor = res.data;
            contenedor.classList.remove('d-none');

            res.data.forEach(c => {
                const compId = c.comprobante_id;
                const pendiente = parseFloat(c.importe_pendiente);
                const nroFormateado = `${escapeHtml(c.comprobante_tipo)} ${String(c.comprobante_pv).padStart(4, '0')}-${String(c.comprobante_nro).padStart(8, '0')}`;

                let montoImputarPrev = 0.0;
                if (imputacionesPrevias.length > 0) {
                    const match = imputacionesPrevias.find(i => parseInt(i.comp_destino_id) === compId);
                    if (match) montoImputarPrev = parseFloat(match.importe_imputado);
                }

                const checkedAttr = montoImputarPrev > 0 ? 'checked' : '';
                const valInput = montoImputarPrev > 0 ? montoImputarPrev.toFixed(2) : '0.00';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="text-center">
                        <input class="form-check-input check-pendiente" type="checkbox" data-id="${compId}" data-pendiente="${pendiente}" ${checkedAttr}>
                    </td>
                    <td><strong class="text-dark">${nroFormateado}</strong></td>
                    <td class="text-center small">${c.f_emision}</td>
                    <td class="text-center small text-muted">${c.f_vto || '-'}</td>
                    <td class="text-end">$ ${formatearMoneda(c.importe_total)}</td>
                    <td class="text-end fw-bold text-danger">$ ${formatearMoneda(pendiente)}</td>
                    <td class="text-end">
                        <input type="number" step="0.01" min="0" max="${pendiente + montoImputarPrev}" 
                               class="form-control form-control-sm text-end input-imputar" 
                               data-id="${compId}" data-pendiente="${pendiente + montoImputarPrev}" value="${valInput}">
                    </td>
                `;

                const chk = tr.querySelector('.check-pendiente');
                const inp = tr.querySelector('.input-imputar');

                chk.addEventListener('change', function () {
                    if (this.checked) {
                        inp.value = parseFloat(this.dataset.pendiente).toFixed(2);
                    } else {
                        inp.value = '0.00';
                    }
                    recalcularBalancePago();
                });

                inp.addEventListener('input', function () {
                    const max = parseFloat(this.dataset.pendiente);
                    let val = parseFloat(this.value) || 0;
                    if (val > max) {
                        val = max;
                        this.value = max.toFixed(2);
                    }
                    chk.checked = (val > 0.001);
                    recalcularBalancePago();
                });

                tbody.appendChild(tr);
            });

            recalcularBalancePago();

        } else {
            vacio.classList.remove('d-none');
            vacio.innerHTML = `<i class="fas fa-check-circle text-success me-1"></i> El proveedor no registra facturas pendientes de pago. Todo pago ingresado quedará como <strong>anticipo</strong>.`;
            recalcularBalancePago();
        }
    } catch (e) {
        spinner.classList.add('d-none');
        vacio.classList.remove('d-none');
        vacio.innerText = 'Error al consultar facturas pendientes.';
        console.error(e);
    }
}

/**
 * Agrega una fila de medio de pago
 */
function agregarFilaMedioPago(tipoDefecto = null) {
    indiceMedioPago++;
    const tbody = document.getElementById('tbodyMedios');
    const tr = document.createElement('tr');
    tr.id = `fila_medio_pago_${indiceMedioPago}`;
    tr.dataset.indice = indiceMedioPago;

    let optionsMedios = '';
    maestrosPago.medios_pago.forEach(m => {
        const selected = (tipoDefecto && m.codigo === tipoDefecto) ? 'selected' : '';
        optionsMedios += `<option value="${m.medio_pago_id}" data-tipo="${m.medio_pago_tipo}" data-codigo="${m.codigo}" ${selected}>${escapeHtml(m.medio_pago)}</option>`;
    });

    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm select-medio-tipo" required>
                ${optionsMedios}
            </select>
        </td>
        <td class="contenedor-campos-especificos"></td>
        <td>
            <input type="number" step="0.01" min="0.01" class="form-control form-control-sm text-end input-medio-importe" placeholder="0.00" required>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-xs btn-quitar-medio" title="Quitar medio">
                <i class="fas fa-times"></i>
            </button>
        </td>
    `;

    tbody.appendChild(tr);

    const selectTipo = tr.querySelector('.select-medio-tipo');
    const inputImporte = tr.querySelector('.input-medio-importe');
    const btnQuitar = tr.querySelector('.btn-quitar-medio');

    selectTipo.addEventListener('change', () => renderizarCamposEspecificosMedioPago(tr));
    inputImporte.addEventListener('input', recalcularBalancePago);
    btnQuitar.addEventListener('click', () => {
        tr.remove();
        recalcularBalancePago();
    });

    renderizarCamposEspecificosMedioPago(tr);
}

/**
 * Renderiza los campos dinámicos según el tipo de medio de pago
 */
function renderizarCamposEspecificosMedioPago(tr, datosPrevios = null) {
    const select = tr.querySelector('.select-medio-tipo');
    const contenedor = tr.querySelector('.contenedor-campos-especificos');
    const inputImporte = tr.querySelector('.input-medio-importe');
    const opt = select.options[select.selectedIndex];
    const tipo = opt.dataset.tipo || 'TRANSFERENCIA';
    const codigo = opt.dataset.codigo || '';

    contenedor.innerHTML = '';

    if (tipo === 'EFECTIVO') {
        const cajas = maestrosPago.cuentas_tesoreria.filter(c => c.cuenta_tipo === 'CAJA');
        let options = '';
        cajas.forEach(c => {
            const sel = (datosPrevios && parseInt(datosPrevios.tesoreria_cuenta_id) === c.tesoreria_cuenta_id) ? 'selected' : '';
            options += `<option value="${c.tesoreria_cuenta_id}" ${sel}>${escapeHtml(c.tesoreria_cuenta)}</option>`;
        });
        contenedor.innerHTML = `
            <div class="row g-1 align-items-center">
                <div class="col-sm-5"><span class="small text-muted">Caja Origen:</span></div>
                <div class="col-sm-7">
                    <select class="form-select form-select-sm input-tesoreria-cuenta" required>
                        ${options}
                    </select>
                </div>
            </div>
        `;
    } else if (tipo === 'TRANSFERENCIA') {
        const bancos = maestrosPago.cuentas_tesoreria.filter(c => c.cuenta_tipo === 'BANCO');
        let options = '';
        bancos.forEach(c => {
            const sel = (datosPrevios && parseInt(datosPrevios.tesoreria_cuenta_id) === c.tesoreria_cuenta_id) ? 'selected' : '';
            options += `<option value="${c.tesoreria_cuenta_id}" ${sel}>${escapeHtml(c.tesoreria_cuenta)}</option>`;
        });
        const nroOp = datosPrevios ? escapeHtml(datosPrevios.numero_operacion || '') : '';
        contenedor.innerHTML = `
            <div class="row g-1">
                <div class="col-sm-6">
                    <select class="form-select form-select-sm input-tesoreria-cuenta" required>
                        ${options}
                    </select>
                </div>
                <div class="col-sm-6">
                    <input type="text" class="form-control form-control-sm input-nro-operacion" placeholder="N° Operación / Transf." value="${nroOp}">
                </div>
            </div>
        `;
    } else if (tipo === 'CHEQUE_PROPIO') {
        // Chequera propia de la empresa
        let optionsChequeras = '<option value="">-- Cuenta Bancaria / Chequera --</option>';
        maestrosPago.chequeras.forEach(chq => {
            const sel = (datosPrevios && parseInt(datosPrevios.tesoreria_cuenta_id) === parseInt(chq.tesoreria_cuenta_id)) ? 'selected' : '';
            optionsChequeras += `<option value="${chq.tesoreria_cuenta_id}" data-banco="${chq.banco_id}" ${sel}>${escapeHtml(chq.tesoreria_cuenta)}</option>`;
        });

        const nroChq = datosPrevios ? escapeHtml(datosPrevios.cheque_nro || '') : '';
        const fPago = datosPrevios ? (datosPrevios.cheque_f_pago || '') : document.getElementById('inputFechaEmision').value;

        contenedor.innerHTML = `
            <div class="row g-1">
                <div class="col-sm-5">
                    <select class="form-select form-select-sm input-propio-cuenta" required>
                        ${optionsChequeras}
                    </select>
                </div>
                <div class="col-sm-3">
                    <input type="text" class="form-control form-control-sm input-propio-nro" placeholder="N° Cheque" value="${nroChq}" required>
                </div>
                <div class="col-sm-4">
                    <input type="date" class="form-control form-control-sm input-propio-vto" title="Fecha de Pago (si es mayor a emisión, será Diferido)" value="${fPago}" required>
                </div>
            </div>
        `;
    } else if (tipo === 'CHEQUE_TERCERO') {
        // Endoso de cheque de tercero en cartera (C2 / RT06)
        let optionsCartera = '<option value="">-- Seleccionar Cheque de Cartera a Endosar --</option>';
        maestrosPago.cheques_en_cartera.forEach(ch => {
            const sel = (datosPrevios && parseInt(datosPrevios.cheque_id) === parseInt(ch.cheque_id)) ? 'selected' : '';
            optionsCartera += `<option value="${ch.cheque_id}" data-importe="${ch.importe}" ${sel}>N° ${escapeHtml(ch.numero)} - ${escapeHtml(ch.banco)} - Vto: ${ch.f_pago} ($ ${formatearMoneda(ch.importe)})</option>`;
        });

        contenedor.innerHTML = `
            <div class="row g-1">
                <div class="col-12">
                    <select class="form-select form-select-sm input-endoso-cheque" required>
                        ${optionsCartera}
                    </select>
                </div>
            </div>
        `;

        const selEndoso = contenedor.querySelector('.input-endoso-cheque');
        selEndoso.addEventListener('change', function () {
            const optEnd = this.options[this.selectedIndex];
            if (optEnd && optEnd.dataset.importe) {
                inputImporte.value = parseFloat(optEnd.dataset.importe).toFixed(2);
                recalcularBalancePago();
            }
        });
    } else if (tipo === 'RETENCION') {
        let optionsImp = '<option value="">-- Retención Practicada --</option>';
        maestrosPago.impuestos_ret.forEach(it => {
            const sel = (datosPrevios && parseInt(datosPrevios.impuesto_tipo_id) === parseInt(it.impuesto_tipo_id)) ? 'selected' : '';
            optionsImp += `<option value="${it.impuesto_tipo_id}" ${sel}>${escapeHtml(it.impuesto_tipo)}</option>`;
        });

        const nroCert = datosPrevios ? escapeHtml(datosPrevios.retencion_certificado_nro || '') : '';
        const base = datosPrevios ? (datosPrevios.retencion_base_imponible || '') : '';
        const ali = datosPrevios ? (datosPrevios.retencion_alicuota || '') : '';

        contenedor.innerHTML = `
            <div class="row g-1">
                <div class="col-sm-4">
                    <select class="form-select form-select-sm input-retencion-tipo" required>
                        ${optionsImp}
                    </select>
                </div>
                <div class="col-sm-3">
                    <input type="text" class="form-control form-control-sm input-retencion-cert" placeholder="N° Certificado" value="${nroCert}" required>
                </div>
                <div class="col-sm-3">
                    <input type="number" step="0.01" class="form-control form-control-sm input-retencion-base" placeholder="Base Imp." value="${base}">
                </div>
                <div class="col-sm-2">
                    <input type="number" step="0.01" class="form-control form-control-sm input-retencion-ali" placeholder="%" value="${ali}">
                </div>
            </div>
        `;
    }
}

/**
 * Auto imputa los fondos emitidos entre las facturas de proveedores más antiguas
 */
function autoImputarPendientesPago() {
    let disponible = calcularTotalMediosPago();
    if (disponible <= 0) {
        Swal.fire('Atención', 'Primero ingrese los importes de los medios de pago salientes.', 'warning');
        return;
    }

    const rows = document.querySelectorAll('#tbodyPendientes tr');
    rows.forEach(tr => {
        const chk = tr.querySelector('.check-pendiente');
        const inp = tr.querySelector('.input-imputar');
        const pendiente = parseFloat(inp.dataset.pendiente);

        if (disponible > 0.001) {
            const aplicar = Math.min(disponible, pendiente);
            inp.value = aplicar.toFixed(2);
            chk.checked = (aplicar > 0);
            disponible -= aplicar;
        } else {
            inp.value = '0.00';
            chk.checked = false;
        }
    });

    recalcularBalancePago();
}

/**
 * Limpia todas las imputaciones a facturas
 */
function limpiarImputacionesPago() {
    const rows = document.querySelectorAll('#tbodyPendientes tr');
    rows.forEach(tr => {
        tr.querySelector('.input-imputar').value = '0.00';
        tr.querySelector('.check-pendiente').checked = false;
    });
    const checkTodos = document.getElementById('checkTodosPendientes');
    if (checkTodos) checkTodos.checked = false;
    recalcularBalancePago();
}

/**
 * Calcula total de medios cargados
 */
function calcularTotalMediosPago() {
    let total = 0.0;
    const inputs = document.querySelectorAll('.input-medio-importe');
    inputs.forEach(inp => {
        total += parseFloat(inp.value) || 0.0;
    });
    return Math.round(total * 100) / 100;
}

/**
 * Calcula total imputado
 */
function calcularTotalImputadoPago() {
    let total = 0.0;
    const inputs = document.querySelectorAll('.input-imputar');
    inputs.forEach(inp => {
        total += parseFloat(inp.value) || 0.0;
    });
    return Math.round(total * 100) / 100;
}

/**
 * Recalcula el balance en tiempo real
 */
function recalcularBalancePago() {
    const totalMedios = calcularTotalMediosPago();
    const totalImputado = calcularTotalImputadoPago();
    const diferencia = Math.round((totalMedios - totalImputado) * 100) / 100;

    document.getElementById('totalMediosDisplay').innerText = `$ ${formatearMoneda(totalMedios)}`;
    document.getElementById('resumenTotalMedios').innerText = `$ ${formatearMoneda(totalMedios)}`;

    document.getElementById('totalImputadoDisplay').innerText = `$ ${formatearMoneda(totalImputado)}`;
    document.getElementById('resumenTotalImputado').innerText = `$ ${formatearMoneda(totalImputado)}`;

    const elDif = document.getElementById('resumenDiferencia');
    const alertaDesbalance = document.getElementById('alertaDesbalance');
    const avisoAnticipo = document.getElementById('avisoAnticipo');
    const btnGuardar = document.getElementById('btnGuardarBorrador');
    const btnConfirmar = document.getElementById('btnConfirmarEmision');

    if (totalImputado > totalMedios + 0.001) {
        elDif.innerText = `-$ ${formatearMoneda(Math.abs(diferencia))}`;
        elDif.className = 'mb-0 fw-bold text-danger';
        alertaDesbalance.classList.remove('d-none');
        avisoAnticipo.classList.add('d-none');
        btnGuardar.disabled = true;
        btnConfirmar.disabled = true;
    } else if (diferencia > 0.001) {
        elDif.innerText = `$ ${formatearMoneda(diferencia)}`;
        elDif.className = 'mb-0 fw-bold text-info';
        alertaDesbalance.classList.add('d-none');
        avisoAnticipo.classList.remove('d-none');
        btnGuardar.disabled = false;
        btnConfirmar.disabled = false;
    } else {
        elDif.innerText = `$ 0,00`;
        elDif.className = 'mb-0 fw-bold text-success';
        alertaDesbalance.classList.add('d-none');
        avisoAnticipo.classList.add('d-none');
        btnGuardar.disabled = false;
        btnConfirmar.disabled = false;
    }
}

/**
 * Recopila y valida datos para la OP
 */
function recopilarDatosPago() {
    const pagoId = parseInt(document.getElementById('pago_id').value) || 0;
    const entidadId = parseInt(document.getElementById('selectProveedor').value) || 0;
    const sucursalId = parseInt(document.getElementById('selectSucursal').value) || 1;
    const fEmision = document.getElementById('inputFechaEmision').value;
    const fContabilidad = document.getElementById('inputFechaContable').value || fEmision;
    const observaciones = document.getElementById('inputObservaciones').value.trim();

    if (entidadId <= 0) {
        Swal.fire('Atención', 'Debe seleccionar un proveedor.', 'warning');
        return null;
    }

    const medios = [];
    const filasMedios = document.querySelectorAll('#tbodyMedios tr');
    if (filasMedios.length === 0) {
        Swal.fire('Atención', 'Debe ingresar al menos un medio de pago.', 'warning');
        return null;
    }

    for (const tr of filasMedios) {
        const select = tr.querySelector('.select-medio-tipo');
        const opt = select.options[select.selectedIndex];
        const medioPagoId = parseInt(select.value);
        const tipo = opt.dataset.tipo;
        const importe = parseFloat(tr.querySelector('.input-medio-importe').value) || 0;

        if (importe <= 0) {
            Swal.fire('Atención', 'Todos los medios de pago deben tener un importe mayor a cero.', 'warning');
            return null;
        }

        const medioObj = {
            medio_pago_id: medioPagoId,
            importe: importe,
            importe_comprobante: importe
        };

        if (tipo === 'EFECTIVO' || tipo === 'TRANSFERENCIA') {
            const selectCuenta = tr.querySelector('.input-tesoreria-cuenta');
            medioObj.tesoreria_cuenta_id = selectCuenta ? parseInt(selectCuenta.value) : null;
            if (tipo === 'TRANSFERENCIA') {
                const inpOp = tr.querySelector('.input-nro-operacion');
                medioObj.numero_operacion = inpOp ? inpOp.value.trim() : null;
            }
        } else if (tipo === 'CHEQUE_PROPIO') {
            const selCuenta = tr.querySelector('.input-propio-cuenta');
            const inpNro = tr.querySelector('.input-propio-nro');
            const inpVto = tr.querySelector('.input-propio-vto');
            const optC = selCuenta.options[selCuenta.selectedIndex];

            if (!selCuenta.value || !inpNro.value || !inpVto.value) {
                Swal.fire('Atención', 'Complete los datos obligatorios del cheque propio (Chequera, Número y Fecha de Pago).', 'warning');
                return null;
            }

            medioObj.tesoreria_cuenta_id = parseInt(selCuenta.value);
            medioObj.cheque_propio = {
                tesoreria_cuenta_id: parseInt(selCuenta.value),
                banco_id: parseInt(optC.dataset.banco || 1),
                numero: inpNro.value.trim(),
                f_pago: inpVto.value
            };
        } else if (tipo === 'CHEQUE_TERCERO') {
            const selEndoso = tr.querySelector('.input-endoso-cheque');
            if (!selEndoso.value) {
                Swal.fire('Atención', 'Seleccione el cheque de cartera a endosar.', 'warning');
                return null;
            }
            medioObj.cheque_tercero_id = parseInt(selEndoso.value);
        } else if (tipo === 'RETENCION') {
            const selImp = tr.querySelector('.input-retencion-tipo');
            const inpCert = tr.querySelector('.input-retencion-cert');
            const inpBase = tr.querySelector('.input-retencion-base');
            const inpAli = tr.querySelector('.input-retencion-ali');

            if (!selImp.value || !inpCert.value) {
                Swal.fire('Atención', 'Complete el tipo y número de certificado de la retención.', 'warning');
                return null;
            }

            medioObj.impuesto_tipo_id = parseInt(selImp.value);
            medioObj.retencion_certificado_nro = inpCert.value.trim();
            medioObj.retencion_base_imponible = parseFloat(inpBase.value) || 0;
            medioObj.retencion_alicuota = parseFloat(inpAli.value) || 0;
        }

        medios.push(medioObj);
    }

    const imputaciones = [];
    const inputsImputar = document.querySelectorAll('.input-imputar');
    inputsImputar.forEach(inp => {
        const monto = parseFloat(inp.value) || 0;
        if (monto > 0.001) {
            imputaciones.push({
                comprobante_destino_id: parseInt(inp.dataset.id),
                importe_imputado: monto
            });
        }
    });

    const totalMedios = calcularTotalMediosPago();
    const totalImputado = calcularTotalImputadoPago();

    if (totalImputado > totalMedios + 0.001) {
        Swal.fire('Atención', 'El total imputado supera el total pagado.', 'warning');
        return null;
    }

    return {
        pago_id: pagoId,
        entidad_id: entidadId,
        sucursal_id: sucursalId,
        f_emision: fEmision,
        f_contabilidad: fContabilidad,
        observaciones: observaciones,
        medios: medios,
        imputaciones: imputaciones
    };
}

/**
 * Guarda el pago como borrador o lo confirma y emite
 */
async function guardarPago(confirmarInmediato = false) {
    const payload = recopilarDatosPago();
    if (!payload) return;

    if (confirmarInmediato) {
        const confirmacion = await Swal.fire({
            title: '¿Confirmar Orden de Pago?',
            text: 'Se emitirá la Orden de Pago oficial, se debitarán las cuentas bancarias/cajas y se aplicarán las cancelaciones de facturas.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, Confirmar y Emitir OP',
            cancelButtonText: 'Cancelar'
        });
        if (!confirmacion.isConfirmed) return;
    }

    try {
        Swal.fire({
            title: 'Procesando...',
            text: 'Guardando datos de la orden de pago...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const empId = getEmpresaId();
        const respGuardar = await fetch('pagos_ajax.php?accion=guardar&empresa_idx=' + empId, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const resG = await respGuardar.json();

        if (!resG.success) {
            Swal.fire('Error', resG.error || 'No se pudo guardar la orden de pago.', 'error');
            return;
        }

        const idGenerado = resG.pago_id;

        if (confirmarInmediato) {
            Swal.update({ text: 'Confirmando e impactando tesorería y contabilidad...' });

            const respConf = await fetch('pagos_ajax.php?accion=confirmar&empresa_idx=' + empId, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: idGenerado })
            });
            const resC = await respConf.json();

            if (!resC.success) {
                Swal.fire('Error al Confirmar', resC.error || 'Ocurrió un error durante la confirmación.', 'error');
                tablaPagosDT.ajax.reload();
                return;
            }

            Swal.fire({
                title: '¡Orden de Pago Emitida!',
                html: `<strong>${resC.message}</strong>`,
                icon: 'success'
            });

            ocultarModal('modalPago');
            tablaPagosDT.ajax.reload();
            verPago(idGenerado);
        } else {
            Swal.fire('Guardado', 'El borrador de Orden de Pago fue guardado correctamente.', 'success');
            ocultarModal('modalPago');
            tablaPagosDT.ajax.reload();
        }
    } catch (e) {
        Swal.fire('Error Inesperado', e.message || 'Error de comunicación.', 'error');
    }
}

/**
 * Carga un borrador para editar
 */
async function editarBorradorPago(id) {
    try {
        Swal.fire({
            title: 'Cargando...',
            text: 'Obteniendo borrador...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const empId = getEmpresaId();
        const resp = await fetch(`pagos_ajax.php?accion=obtener&empresa_idx=${empId}&id=${id}`);
        const res = await resp.json();
        if (typeof Swal !== 'undefined' && Swal.close) Swal.close();

        if (!res.success || !res.data) {
            Swal.fire('Error', res.error || 'No se pudo cargar la orden de pago.', 'error');
            return;
        }

        const { cabecera, medios, imputaciones } = res.data;

        document.getElementById('formPago').reset();
        document.getElementById('pago_id').value = cabecera.pago_id;
        document.getElementById('modalPagoLabel').innerHTML = `<i class="fas fa-edit me-2"></i>Editar Borrador de OP #${cabecera.pago_id}`;

        document.getElementById('selectProveedor').value = cabecera.entidad_id;
        document.getElementById('selectSucursal').value = cabecera.sucursal_id;
        document.getElementById('inputFechaEmision').value = cabecera.f_emision;
        document.getElementById('inputFechaContable').value = cabecera.f_contabilidad || cabecera.f_emision;
        document.getElementById('inputObservaciones').value = cabecera.observaciones || '';

        const tbodyMedios = document.getElementById('tbodyMedios');
        tbodyMedios.innerHTML = '';
        indiceMedioPago = 0;

        medios.forEach(m => {
            indiceMedioPago++;
            const tr = document.createElement('tr');
            tr.id = `fila_medio_pago_${indiceMedioPago}`;
            tr.dataset.indice = indiceMedioPago;

            let optionsMedios = '';
            maestrosPago.medios_pago.forEach(mp => {
                const sel = (parseInt(mp.medio_pago_id) === parseInt(m.medio_pago_id)) ? 'selected' : '';
                optionsMedios += `<option value="${mp.medio_pago_id}" data-tipo="${mp.medio_pago_tipo}" data-codigo="${mp.codigo}" ${sel}>${escapeHtml(mp.medio_pago)}</option>`;
            });

            tr.innerHTML = `
                <td>
                    <select class="form-select form-select-sm select-medio-tipo" required>
                        ${optionsMedios}
                    </select>
                </td>
                <td class="contenedor-campos-especificos"></td>
                <td>
                    <input type="number" step="0.01" min="0.01" class="form-control form-control-sm text-end input-medio-importe" value="${parseFloat(m.importe_comprobante).toFixed(2)}" required>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-outline-danger btn-xs btn-quitar-medio" title="Quitar medio">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            `;

            tbodyMedios.appendChild(tr);

            const selectTipo = tr.querySelector('.select-medio-tipo');
            const inputImporte = tr.querySelector('.input-medio-importe');
            const btnQuitar = tr.querySelector('.btn-quitar-medio');

            selectTipo.addEventListener('change', () => renderizarCamposEspecificosMedioPago(tr));
            inputImporte.addEventListener('input', recalcularBalancePago);
            btnQuitar.addEventListener('click', () => {
                tr.remove();
                recalcularBalancePago();
            });

            renderizarCamposEspecificosMedioPago(tr, m);
        });

        await cargarComprobantesPendientesProveedor(cabecera.entidad_id, imputaciones);

        mostrarModal('modalPago');

    } catch (e) {
        Swal.fire('Error', e.message || 'Error al cargar borrador.', 'error');
    }
}

/**
 * Confirmación directa desde el listado
 */
async function confirmarPagoDirecto(id) {
    const confirmacion = await Swal.fire({
        title: '¿Confirmar Orden de Pago?',
        text: 'Se emitirá la Orden de Pago definitiva, se registrarán las salidas en tesorería y se afectarán los saldos pendientes de las facturas.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, Confirmar OP',
        cancelButtonText: 'Cancelar'
    });
    if (!confirmacion.isConfirmed) return;

    try {
        Swal.fire({
            title: 'Confirmando...',
            text: 'Aplicando egresos e imputaciones...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const empId = getEmpresaId();
        const resp = await fetch('pagos_ajax.php?accion=confirmar&empresa_idx=' + empId, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const res = await resp.json();

        if (res.success) {
            Swal.fire({
                title: '¡Orden de Pago Confirmada!',
                html: `<strong>${res.message}</strong>`,
                icon: 'success'
            });
            tablaPagosDT.ajax.reload();
            verPago(id);
        } else {
            Swal.fire('Error', res.error || 'No se pudo confirmar la orden de pago.', 'error');
        }
    } catch (e) {
        Swal.fire('Error', e.message || 'Error de comunicación.', 'error');
    }
}

/**
 * Visualiza la orden de pago con membrete formal
 */
async function verPago(id) {
    try {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Cargando...',
                text: 'Generando vista de la Orden de Pago...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
        }

        const empId = getEmpresaId();
        const resp = await fetch(`pagos_ajax.php?accion=obtener&empresa_idx=${empId}&id=${id}`);
        const res = await resp.json();
        if (typeof Swal !== 'undefined' && Swal.close) Swal.close();

        if (!res.success || !res.data) {
            const msg = res.error || 'No se pudo obtener la orden de pago.';
            if (typeof Swal !== 'undefined') Swal.fire('Error', msg, 'error');
            else alert(msg);
            return;
        }

        const { cabecera, medios, imputaciones } = res.data;
        const cuerpo = document.getElementById('cuerpoPagoImpresion');

        const nroOP = (cabecera.comprobante_nro > 0)
            ? `${String(cabecera.pv_nro ?? cabecera.comprobante_pv).padStart(4, '0')}-${String(cabecera.comprobante_nro).padStart(8, '0')}`
            : 'BORRADOR';

        let tablaMediosHtml = '';
        medios.forEach(m => {
            let detalle = m.medio_pago;
            if (m.medio_pago_tipo === 'EFECTIVO' && m.tesoreria_cuenta) {
                detalle += ` (${m.tesoreria_cuenta})`;
            } else if (m.medio_pago_tipo === 'TRANSFERENCIA') {
                detalle += ` (${m.tesoreria_cuenta || 'Banco'} - Op: ${m.numero_operacion || 'S/N'})`;
            } else if (m.medio_pago_tipo === 'CHEQUE_PROPIO') {
                detalle += ` (Cheque Propio N° ${m.cheque_nro || 'S/N'} Vto: ${m.cheque_f_pago || '-'})`;
            } else if (m.medio_pago_tipo === 'CHEQUE_TERCERO') {
                detalle += ` (Endoso Cheque Tercero: ${m.cheque_banco || 'Banco'} N° ${m.cheque_nro || 'S/N'} Vto: ${m.cheque_f_pago || '-'})`;
            } else if (m.medio_pago_tipo === 'RETENCION') {
                detalle += ` (Retención Certificado: ${m.retencion_certificado_nro || 'S/N'})`;
            }

            tablaMediosHtml += `
                <tr>
                    <td>${escapeHtml(detalle)}</td>
                    <td class="text-end fw-bold">$ ${formatearMoneda(m.importe_comprobante)}</td>
                </tr>
            `;
        });

        let tablaImputacionesHtml = '';
        if (imputaciones && imputaciones.length > 0) {
            imputaciones.forEach(imp => {
                const nroFactura = `${imp.comprobante_tipo_codigo || 'COMP'} ${String(imp.comprobante_pv).padStart(4, '0')}-${String(imp.comprobante_nro).padStart(8, '0')}`;
                tablaImputacionesHtml += `
                    <tr>
                        <td><strong>${escapeHtml(nroFactura)}</strong></td>
                        <td class="text-center">${imp.destino_f_emision || '-'}</td>
                        <td class="text-end">$ ${formatearMoneda(imp.destino_total)}</td>
                        <td class="text-end fw-bold text-primary">$ ${formatearMoneda(imp.importe_imputado)}</td>
                    </tr>
                `;
            });
        } else {
            tablaImputacionesHtml = `
                <tr>
                    <td colspan="4" class="text-center text-muted py-2">
                        <em>Pago emitido como anticipo / pago a cuenta sin comprobantes aplicados.</em>
                    </td>
                </tr>
            `;
        }

        cuerpo.innerHTML = `
            <div class="border p-4 bg-white shadow-sm" style="font-family: Arial, sans-serif;">
                <div class="row align-items-center border-bottom pb-3 mb-3">
                    <div class="col-sm-7">
                        <h4 class="fw-bold mb-0 text-dark">CASA LUCHO</h4>
                        <small class="text-muted d-block">Distribuidora Mayorista de Autopartes</small>
                        <small class="text-muted">Sucursal: ${escapeHtml(cabecera.sucursal_nombre)}</small>
                    </div>
                    <div class="col-sm-5 text-end">
                        <div class="border border-dark p-2 d-inline-block text-center rounded" style="min-width: 180px;">
                            <span class="fs-5 fw-bold d-block">ORDEN DE PAGO</span>
                            <span class="fs-6 fw-bold text-primary">OP N° ${nroOP}</span>
                            <div class="small text-muted mt-1">Fecha: ${cabecera.f_emision}</div>
                        </div>
                    </div>
                </div>

                <div class="bg-light p-3 rounded mb-3 border">
                    <div class="row">
                        <div class="col-sm-8">
                            <span class="text-muted small d-block">PROVEEDOR:</span>
                            <strong class="fs-6 text-dark">${escapeHtml(cabecera.entidad_nombre)}</strong>
                            <div class="small text-muted">${escapeHtml(cabecera.domicilio_legal || 'Domicilio S/D')}</div>
                        </div>
                        <div class="col-sm-4 text-sm-end">
                            <span class="text-muted small d-block">CUIT:</span>
                            <strong class="text-dark">${escapeHtml(cabecera.cuit || 'S/D')}</strong>
                            <div class="small text-muted mt-1">Estado: <span class="badge bg-secondary">${escapeHtml(cabecera.estado_registro)}</span></div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <h6 class="fw-bold text-uppercase small text-secondary border-bottom pb-1">
                        <i class="fas fa-wallet me-1"></i> Detalle de Fondos y Valores Entregados
                    </h6>
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr class="small">
                                <th>Descripción / Medio</th>
                                <th class="text-end" style="width: 150px;">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${tablaMediosHtml}
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-bold">
                                <td class="text-end">TOTAL ORDEN DE PAGO:</td>
                                <td class="text-end text-success fs-6">$ ${formatearMoneda(cabecera.importe_total)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mb-3">
                    <h6 class="fw-bold text-uppercase small text-secondary border-bottom pb-1">
                        <i class="fas fa-file-invoice-dollar me-1"></i> Facturas y Comprobantes Cancelados
                    </h6>
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr class="small">
                                <th>Comprobante</th>
                                <th class="text-center" style="width: 100px;">Emisión</th>
                                <th class="text-end" style="width: 120px;">Total</th>
                                <th class="text-end" style="width: 130px;">Imputado</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${tablaImputacionesHtml}
                        </tbody>
                    </table>
                </div>

                ${cabecera.observaciones ? `
                    <div class="small text-muted border-top pt-2">
                        <strong>Observaciones:</strong> ${escapeHtml(cabecera.observaciones)}
                    </div>
                ` : ''}
            </div>
        `;

        mostrarModal('modalVerPago');

    } catch (e) {
        console.error('Error al ver orden de pago:', e);
        if (typeof Swal !== 'undefined') {
            Swal.fire('Error', e.message || 'Error al visualizar orden de pago.', 'error');
        } else {
            alert(e.message || 'Error al visualizar orden de pago.');
        }
    }
}

/**
 * Abre modal de anulación
 */
function abrirModalAnularPago(id) {
    document.getElementById('anular_pago_id').value = id;
    document.getElementById('motivoAnulacion').value = '';
    mostrarModal('modalAnular');
}

/**
 * Ejecuta la anulación con motivo obligatorio
 */
async function ejecutarAnulacionPago(e) {
    e.preventDefault();
    const id = parseInt(document.getElementById('anular_pago_id').value);
    const motivo = document.getElementById('motivoAnulacion').value.trim();

    if (!motivo) {
        Swal.fire('Atención', 'Debe indicar el motivo de anulación.', 'warning');
        return;
    }

    try {
        Swal.fire({
            title: 'Anulando...',
            text: 'Revertiendo egresos, facturas y cheques...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const empId = getEmpresaId();
        const resp = await fetch('pagos_ajax.php?accion=anular&empresa_idx=' + empId, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, motivo: motivo })
        });
        const res = await resp.json();

        if (res.success) {
            Swal.fire('Orden de Pago Anulada', res.message || 'OP anulada con éxito.', 'success');
            ocultarModal('modalAnular');
            tablaPagosDT.ajax.reload();
        } else {
            Swal.fire('Error al Anular', res.error || 'No se pudo anular la orden de pago.', 'error');
        }
    } catch (e) {
        Swal.fire('Error', e.message || 'Error al procesar anulación.', 'error');
    }
}

/**
 * Elimina un borrador de pago
 */
async function eliminarBorradorPago(id) {
    const confirmacion = await Swal.fire({
        title: '¿Eliminar Borrador?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, Eliminar Borrador',
        cancelButtonText: 'Cancelar'
    });
    if (!confirmacion.isConfirmed) return;

    try {
        const empId = getEmpresaId();
        const resp = await fetch(`pagos_ajax.php?accion=eliminar&empresa_idx=${empId}&id=${id}`, { method: 'POST' });
        const res = await resp.json();

        if (res.success) {
            Swal.fire('Eliminado', res.message || 'Borrador eliminado.', 'success');
            tablaPagosDT.ajax.reload();
        } else {
            Swal.fire('Error', res.error || 'No se pudo eliminar el borrador.', 'error');
        }
    } catch (e) {
        Swal.fire('Error', e.message || 'Error de comunicación.', 'error');
    }
}

function formatearMoneda(val) {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
