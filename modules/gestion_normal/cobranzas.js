/**
 * cobranzas.js
 *
 * Lógica frontend e interactividad para Cobranzas a Clientes (Recibos RCX)
 * Sistema gestion_multipyme - Módulo Tesorería
 */

let maestros = {
    clientes: [],
    medios_pago: [],
    cuentas_tesoreria: [],
    bancos: [],
    impuestos_retencion: [],
    jurisdicciones: [],
    sucursales: [],
    puntos_venta: []
};

let tablaDT = null;
let indiceMedio = 0;
let comprobantesCliente = [];


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
        if (inst) inst.hide();
        else if (window.jQuery && typeof $(el).modal === 'function') $(el).modal('hide');
    } else if (window.jQuery && typeof $(el).modal === 'function') {
        $(el).modal('hide');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    inicializarFechasFiltro();
    configurarEventos();
    inicializarDataTable();
    cargarMaestros();
});

/**
 * Inicializa fechas de filtro con el mes actual
 */
function inicializarFechasFiltro() {
    const hoy = new Date();
    const primerDia = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
    
    const formato = d => d.toISOString().split('T')[0];
    const filtroDesde = document.getElementById('filtroDesde');
    const filtroHasta = document.getElementById('filtroHasta');
    
    if (filtroDesde) filtroDesde.value = formato(primerDia);
    if (filtroHasta) filtroHasta.value = formato(hoy);
}

/**
 * Carga todos los maestros necesarios para la operación de cobranzas
 */
async function cargarMaestros() {
    try {
        const resp = await fetch('cobranzas_ajax.php?accion=maestros&empresa_idx=' + window.EMPRESA_ID);
        const res = await resp.json();
        if (res.success && res.data) {
            maestros = res.data;
            poblarSelectClientes();
            poblarSelectSucursales();
            poblarSelectPuntosVenta();
        } else {
            console.error('Error cargando maestros:', res.error);
        }
    } catch (e) {
        console.error('Excepción al cargar maestros:', e);
    }
}

/**
 * Llena los selects de clientes
 */
function poblarSelectClientes() {
    const selectFiltro = document.getElementById('filtroCliente');
    const selectModal = document.getElementById('selectCliente');

    if (selectFiltro) {
        let opts = '<option value="">-- Todos los Clientes --</option>';
        (maestros.clientes || []).forEach(c => {
            opts += `<option value="${c.entidad_id}">${escapeHtml(c.entidad_nombre)} (CUIT: ${c.cuit || 'S/D'})${(+c.es_proveedor === 1 && +c.es_cliente !== 1) ? ' · proveedor (devolución de NC)' : ''}</option>`;
        });
        selectFiltro.innerHTML = opts;
    }

    if (selectModal) {
        let opts = '<option value="">-- Seleccionar Cliente --</option>';
        (maestros.clientes || []).forEach(c => {
            opts += `<option value="${c.entidad_id}">${escapeHtml(c.entidad_nombre)} (CUIT: ${c.cuit || 'S/D'})${(+c.es_proveedor === 1 && +c.es_cliente !== 1) ? ' · proveedor (devolución de NC)' : ''}</option>`;
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
    (maestros.sucursales || []).forEach(s => {
        opts += `<option value="${s.sucursal_id}">${escapeHtml(s.sucursal_nombre)}</option>`;
    });
    select.innerHTML = opts;
}

/**
 * Llena los selects de puntos de venta (filtro y modal)
 */
function poblarSelectPuntosVenta(sucursalId = null) {
    const selectFiltro = document.getElementById('filtroPuntoVenta');
    const selectModal = document.getElementById('selectPuntoVenta');

    if (selectFiltro && (!selectFiltro.children || selectFiltro.children.length <= 1)) {
        let opts = '<option value="">-- Todos los PV --</option>';
        (maestros.puntos_venta || []).forEach(pv => {
            const boca = pv.boca_nombre ? ` (${pv.boca_nombre})` : '';
            opts += `<option value="${pv.punto_venta_id}">${escapeHtml(pv.nombre)}${escapeHtml(boca)} - PV ${String(pv.codigo_fiscal || pv.punto_venta_id).padStart(4, '0')}</option>`;
        });
        selectFiltro.innerHTML = opts;
    }

    if (selectModal) {
        let lista = maestros.puntos_venta || [];
        if (sucursalId && parseInt(sucursalId) > 0) {
            const filtrados = lista.filter(pv => parseInt(pv.sucursal_id) === parseInt(sucursalId));
            if (filtrados.length > 0) {
                lista = filtrados;
            }
        }
        let opts = '';
        lista.forEach(pv => {
            const boca = pv.boca_nombre ? ` (${pv.boca_nombre})` : '';
            opts += `<option value="${pv.punto_venta_id}">${escapeHtml(pv.nombre)}${escapeHtml(boca)} - PV ${String(pv.codigo_fiscal || pv.punto_venta_id).padStart(4, '0')}</option>`;
        });
        selectModal.innerHTML = opts;
    }
}

/**
 * Carga los tipos de comprobantes habilitados para el punto de venta seleccionado
 * según la tabla vinculada a la página (conf__paginas.tabla_id -> gestion__comprobantes_subgrupos -> tipos)
 */
async function cargarTiposPorPuntoVenta(tipoPreseleccionado = null) {
    const selectPV = document.getElementById('selectPuntoVenta');
    const selectTipo = document.getElementById('selectComprobanteTipo');
    if (!selectTipo) return;

    const pvId = selectPV ? selectPV.value : '';
    if (!pvId) {
        selectTipo.innerHTML = '<option value="">Seleccione PV primero</option>';
        return;
    }

    try {
        const empId = typeof EMPRESA_ID !== 'undefined' ? EMPRESA_ID : window.EMPRESA_ID;
        const pagId = typeof PAGINA_ID !== 'undefined' ? PAGINA_ID : 105;
        const resp = await fetch(`cobranzas_ajax.php?accion=tipos_por_punto_venta&empresa_idx=${empId}&pagina_idx=${pagId}&punto_venta_id=${pvId}`);
        const tipos = await resp.json();
        let opts = '';
        if (tipos && tipos.length > 0) {
            tipos.forEach(t => {
                const letra = t.letra ? ` (${t.letra})` : '';
                const sel = (tipoPreseleccionado && String(tipoPreseleccionado) === String(t.comprobante_tipo_id)) ? 'selected' : '';
                opts += `<option value="${t.comprobante_tipo_id}" data-fiscal="${t.comprobante_fiscal_id || 0}" ${sel}>${escapeHtml(t.comprobante_tipo)}${letra}</option>`;
            });
            selectTipo.innerHTML = opts;
            if (!tipoPreseleccionado && tipos.length === 1) {
                selectTipo.value = tipos[0].comprobante_tipo_id;
            }
        } else {
            selectTipo.innerHTML = '<option value="">Sin tipos habilitados para este PV</option>';
        }
    } catch (e) {
        console.error('Error al cargar tipos de comprobante:', e);
        selectTipo.innerHTML = '<option value="">Error al cargar tipos</option>';
    }
}

/**
 * Inicializa el DataTable principal
 */
function inicializarDataTable() {
    if ($.fn.DataTable.isDataTable('#tablaCobranzas')) {
        $('#tablaCobranzas').DataTable().destroy();
    }
    tablaDT = $('#tablaCobranzas').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: 'cobranzas_ajax.php',
            type: 'GET',
            dataSrc: 'data',
            data: function (d) {
                d.accion = 'listar';
                d.desde = document.getElementById('filtroDesde')?.value || '';
                d.hasta = document.getElementById('filtroHasta')?.value || '';
                d.entidad_id = document.getElementById('filtroCliente')?.value || '';
                d.estado_id = document.getElementById('filtroEstado')?.value || '';
                d.comprobante_pv = document.getElementById('filtroPuntoVenta')?.value || '';
            }
        },
        columns: [
            { data: 'f_emision', className: 'text-center' },
            { data: 'numero', className: 'fw-bold' },
            { data: 'cliente' },
            { data: 'sucursal' },
            { data: 'total', className: 'text-end' },
            { data: 'saldo_a_cuenta', className: 'text-center' },
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
function configurarEventos() {
    const recargarTabla = () => {
        if (tablaDT) {
            tablaDT.ajax.reload();
        }
    };

    // Prevenir submit estándar del formulario de filtros y recargar DataTable
    document.getElementById('formFiltros')?.addEventListener('submit', function (e) {
        e.preventDefault();
        recargarTabla();
    });

    // Filtros
    document.getElementById('btnFiltrar')?.addEventListener('click', function (e) {
        e.preventDefault();
        recargarTabla();
    });
    document.getElementById('btnRecargar')?.addEventListener('click', function (e) {
        e.preventDefault();
        recargarTabla();
    });
    document.getElementById('filtroDesde')?.addEventListener('change', recargarTabla);
    document.getElementById('filtroHasta')?.addEventListener('change', recargarTabla);
    document.getElementById('filtroCliente')?.addEventListener('change', recargarTabla);
    document.getElementById('filtroEstado')?.addEventListener('change', recargarTabla);
    document.getElementById('filtroPuntoVenta')?.addEventListener('change', recargarTabla);

    // Cambio de Sucursal en el Modal actualiza Puntos de Venta y Cajas de Efectivo
    document.getElementById('selectSucursal')?.addEventListener('change', function () {
        poblarSelectPuntosVenta(this.value);
        actualizarCajasMediosEfectivo();
        cargarTiposPorPuntoVenta();
    });

    // Cambio de Punto de Venta en el Modal sincroniza la Sucursal si es necesario y carga Tipos
    document.getElementById('selectPuntoVenta')?.addEventListener('change', function () {
        const pvId = parseInt(this.value);
        const pv = (maestros.puntos_venta || []).find(p => parseInt(p.punto_venta_id) === pvId);
        if (pv && pv.sucursal_id) {
            const selectSuc = document.getElementById('selectSucursal');
            if (selectSuc && parseInt(selectSuc.value) !== parseInt(pv.sucursal_id)) {
                selectSuc.value = pv.sucursal_id;
                actualizarCajasMediosEfectivo();
            }
        }
        cargarTiposPorPuntoVenta();
    });


    // Botón Nueva Cobranza
    document.getElementById('btnNuevaCobranza')?.addEventListener('click', abrirModalNuevaCobranza);

    // Botón Agregar Medio
    document.getElementById('btnAgregarMedio')?.addEventListener('click', () => agregarFilaMedio());

    // Cambio de Cliente en el Modal
    document.getElementById('selectCliente')?.addEventListener('change', function () {
        cargarComprobantesPendientes(this.value);
    });

    // Checkbox seleccionar todos los pendientes
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
        recalcularBalance();
    });

    // Auto Imputar y Limpiar Imputaciones
    document.getElementById('btnAutoImputar')?.addEventListener('click', autoImputarPendientes);
    document.getElementById('btnLimpiarImputaciones')?.addEventListener('click', limpiarImputaciones);

    // Guardar Borrador
    document.getElementById('btnGuardarBorrador')?.addEventListener('click', () => guardarCobranza(false));

    // Confirmar Emisión
    document.getElementById('btnConfirmarEmision')?.addEventListener('click', () => guardarCobranza(true));

    // Acciones delegadas en la tabla
    $('#tablaCobranzas tbody').on('click', '.btn-accion', function () {
        const id = $(this).data('id');
        const accion = $(this).data('accion');

        if (accion === 'visualizar') {
            verRecibo(id);
        } else if (accion === 'editar') {
            editarBorrador(id);
        } else if (accion === 'confirmar') {
            confirmarCobranzaDirecta(id);
        } else if (accion === 'anular') {
            abrirModalAnular(id);
        } else if (accion === 'eliminar') {
            eliminarBorrador(id);
        }
    });

    // Formulario de anulación
    document.getElementById('formAnular')?.addEventListener('submit', ejecutarAnulacion);

    // Imprimir recibo
    document.getElementById('btnImprimirRecibo')?.addEventListener('click', () => window.print());
}

/**
 * Abre el modal para una nueva cobranza desde cero
 */
async function abrirModalNuevaCobranza() {
    if (!maestros.medios_pago || maestros.medios_pago.length === 0) {
        await cargarMaestros();
    }

    const form = document.getElementById('formCobranza');
    if (form) form.reset();

    const cobranzaIdInput = document.getElementById('cobranza_id');
    if (cobranzaIdInput) cobranzaIdInput.value = '0';

    const modalLabel = document.getElementById('modalCobranzaLabel');
    if (modalLabel) modalLabel.innerHTML = '<i class="fas fa-hand-holding-usd me-2"></i>Nueva Cobranza (Recibo de Cobro)';

    const hoy = new Date().toISOString().split('T')[0];
    const inpEmision = document.getElementById('inputFechaEmision');
    const inpContable = document.getElementById('inputFechaContable');
    if (inpEmision) inpEmision.value = hoy;
    if (inpContable) inpContable.value = hoy;

    // Poblar puntos de venta según la sucursal activa
    poblarSelectPuntosVenta(document.getElementById('selectSucursal')?.value);
    await cargarTiposPorPuntoVenta();

    const nroInput = document.getElementById('inputComprobanteNro');
    if (nroInput) nroInput.value = 'Se asigna al confirmar';

    // Limpiar medios y agregar 1 fila de Efectivo por defecto
    const tbodyMedios = document.getElementById('tbodyMedios');
    if (tbodyMedios) tbodyMedios.innerHTML = '';
    indiceMedio = 0;
    agregarFilaMedio('EFECTIVO');

    // Limpiar pendientes
    limpiarSeccionPendientes();
    recalcularBalance();

    mostrarModal('modalCobranza');
}

/**
 * Limpia la sección de comprobantes pendientes
 */
function limpiarSeccionPendientes() {
    comprobantesCliente = [];
    document.getElementById('tbodyPendientes').innerHTML = '';
    document.getElementById('contenedorTablaPendientes').classList.add('d-none');
    document.getElementById('spinnerPendientes').classList.add('d-none');
    document.getElementById('vacioPendientes').classList.remove('d-none');
    document.getElementById('vacioPendientes').innerText = 'Seleccione un cliente para ver sus comprobantes pendientes de cobro.';
}

/**
 * Carga los comprobantes pendientes del cliente seleccionado
 */
async function cargarComprobantesPendientes(entidadId, imputacionesPrevias = []) {
    if (!entidadId || parseInt(entidadId) <= 0) {
        limpiarSeccionPendientes();
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
        const resp = await fetch(`cobranzas_ajax.php?accion=comprobantes_pendientes&empresa_idx=${window.EMPRESA_ID}&entidad_id=${entidadId}`);
        const res = await resp.json();

        spinner.classList.add('d-none');

        if (res.success && res.data && res.data.length > 0) {
            comprobantesCliente = res.data;
            contenedor.classList.remove('d-none');

            res.data.forEach(c => {
                const compId = c.comprobante_id;
                const pendiente = parseFloat(c.importe_pendiente);
                const nroFormateado = `${escapeHtml(c.comprobante_tipo)} ${String(c.comprobante_pv).padStart(4, '0')}-${String(c.comprobante_nro).padStart(8, '0')}`;

                // Verificar si hay imputación previa para esta factura (en modo edición)
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

                // Eventos del row
                const chk = tr.querySelector('.check-pendiente');
                const inp = tr.querySelector('.input-imputar');

                chk.addEventListener('change', function () {
                    if (this.checked) {
                        inp.value = parseFloat(this.dataset.pendiente).toFixed(2);
                    } else {
                        inp.value = '0.00';
                    }
                    recalcularBalance();
                });

                inp.addEventListener('input', function () {
                    const max = parseFloat(this.dataset.pendiente);
                    let val = parseFloat(this.value) || 0;
                    if (val > max) {
                        val = max;
                        this.value = max.toFixed(2);
                    }
                    chk.checked = (val > 0.001);
                    recalcularBalance();
                });

                tbody.appendChild(tr);
            });

            recalcularBalance();

        } else {
            vacio.classList.remove('d-none');
            vacio.innerHTML = `<i class="fas fa-check-circle text-success me-1"></i> El cliente no registra comprobantes pendientes de pago. Todo cobro ingresado quedará como <strong>cobro a cuenta</strong>.`;
            recalcularBalance();
        }
    } catch (e) {
        spinner.classList.add('d-none');
        vacio.classList.remove('d-none');
        vacio.innerText = 'Error al consultar comprobantes pendientes.';
        console.error(e);
    }
}

/**
 * Agrega una fila dinámica de medio de cobro
 */
function agregarFilaMedio(tipoDefecto = null) {
    indiceMedio++;
    const tbody = document.getElementById('tbodyMedios');
    const tr = document.createElement('tr');
    tr.id = `fila_medio_${indiceMedio}`;
    tr.dataset.indice = indiceMedio;

    // Generar opciones de medios de pago
    let optionsMedios = '';
    maestros.medios_pago.forEach(m => {
        const selected = (tipoDefecto && m.codigo === tipoDefecto) ? 'selected' : '';
        optionsMedios += `<option value="${m.medio_pago_id}" data-tipo="${m.medio_pago_tipo}" data-codigo="${m.codigo}" ${selected}>${escapeHtml(m.medio_pago)}</option>`;
    });

    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm select-medio-tipo" required>
                ${optionsMedios}
            </select>
        </td>
        <td class="contenedor-campos-especificos">
            <!-- Campos específicos según el tipo de medio -->
        </td>
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

    selectTipo.addEventListener('change', () => renderizarCamposEspecificosMedio(tr));
    inputImporte.addEventListener('input', recalcularBalance);
    btnQuitar.addEventListener('click', () => {
        tr.remove();
        recalcularBalance();
    });

    // Renderizar campos del medio seleccionado
    renderizarCamposEspecificosMedio(tr);
}

/**
 * Renderiza los campos específicos según el tipo de medio seleccionado en una fila
 */
function renderizarCamposEspecificosMedio(tr, datosPrevios = null) {
    const select = tr.querySelector('.select-medio-tipo');
    const contenedor = tr.querySelector('.contenedor-campos-especificos');
    if (!select || !contenedor) return;
    const opt = select.options && select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
    const tipo = opt ? (opt.dataset.tipo || 'EFECTIVO') : 'EFECTIVO';
    const codigo = opt ? (opt.dataset.codigo || '') : '';

    contenedor.innerHTML = '';

    if (tipo === 'EFECTIVO') {
        const sucursalId = parseInt(document.getElementById('selectSucursal')?.value || 1);
        const cajas = (maestros.cuentas_tesoreria || []).filter(c => c.cuenta_tipo === 'CAJA' && (!c.sucursal_id || parseInt(c.sucursal_id) === sucursalId));

        let c = cajas[0];
        if (datosPrevios && datosPrevios.tesoreria_cuenta_id) {
            const match = cajas.find(item => parseInt(item.tesoreria_cuenta_id) === parseInt(datosPrevios.tesoreria_cuenta_id));
            if (match) c = match;
        }

        if (cajas.length === 1 && c) {
            // Regla: Única caja de cobro por sucursal
            contenedor.innerHTML = `
                <div class="row g-1 align-items-center">
                    <div class="col-sm-4"><span class="small text-muted">Caja Sucursal:</span></div>
                    <div class="col-sm-8">
                        <span class="badge bg-light text-dark border p-2 w-100 text-start font-monospace">
                            <i class="fas fa-cash-register me-1 text-success"></i> ${escapeHtml(c.tesoreria_cuenta)}
                        </span>
                        <input type="hidden" class="input-tesoreria-cuenta" value="${c.tesoreria_cuenta_id}">
                    </div>
                </div>
            `;
        } else {
            let options = '';
            cajas.forEach(item => {
                const sel = (c && parseInt(c.tesoreria_cuenta_id) === parseInt(item.tesoreria_cuenta_id)) ? 'selected' : '';
                options += `<option value="${item.tesoreria_cuenta_id}" ${sel}>${escapeHtml(item.tesoreria_cuenta)}</option>`;
            });
            contenedor.innerHTML = `
                <div class="row g-1 align-items-center">
                    <div class="col-sm-4"><span class="small text-muted">Caja Destino:</span></div>
                    <div class="col-sm-8">
                        <select class="form-select form-select-sm input-tesoreria-cuenta" required>
                            ${options || '<option value="">-- Sin caja disponible --</option>'}
                        </select>
                    </div>
                </div>
            `;
        }
    } else if (tipo === 'TRANSFERENCIA') {
        // Cuentas Bancarias de la empresa + N° Operación
        const bancos = maestros.cuentas_tesoreria.filter(c => c.cuenta_tipo === 'BANCO');
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
    } else if (tipo === 'CHEQUE_TERCERO') {
        // Datos completos del cheque recibido
        let optionsBancos = '<option value="">-- Banco Emisor --</option>';
        maestros.bancos.forEach(b => {
            const sel = (datosPrevios && parseInt(datosPrevios.banco_id) === parseInt(b.banco_id)) ? 'selected' : '';
            optionsBancos += `<option value="${b.banco_id}" ${sel}>${escapeHtml(b.banco)}</option>`;
        });

        const nroChq = datosPrevios ? escapeHtml(datosPrevios.cheque_nro || datosPrevios.numero || '') : '';
        const fPago = datosPrevios ? (datosPrevios.cheque_f_pago || datosPrevios.f_pago || '') : '';
        const librador = datosPrevios ? escapeHtml(datosPrevios.librador_nombre || '') : '';

        contenedor.innerHTML = `
            <div class="row g-1">
                <div class="col-sm-4">
                    <select class="form-select form-select-sm input-cheque-banco" required>
                        ${optionsBancos}
                    </select>
                </div>
                <div class="col-sm-2">
                    <input type="text" class="form-control form-control-sm input-cheque-numero" placeholder="N° Cheque" value="${nroChq}" required>
                </div>
                <div class="col-sm-3">
                    <input type="date" class="form-control form-control-sm input-cheque-vto" title="Fecha de Pago / Vto." value="${fPago}" required>
                </div>
                <div class="col-sm-3">
                    <input type="text" class="form-control form-control-sm input-cheque-librador" placeholder="Librador / Titular" value="${librador}">
                </div>
            </div>
        `;
    } else if (tipo === 'RETENCION') {
        // Retenciones impositivas sufridas (IVA, Ganancias, Ingresos Brutos)
        let defaultImpTipoId = datosPrevios ? parseInt(datosPrevios.impuesto_tipo_id) : 0;
        if (!defaultImpTipoId && codigo) {
            if (codigo === 'RET_IVA') {
                const match = (maestros.impuestos_retencion || []).find(it => parseInt(it.impuesto_tipo_id) === 5 || it.impuesto_tipo.toUpperCase().includes('IVA'));
                if (match) defaultImpTipoId = parseInt(match.impuesto_tipo_id);
            } else if (codigo === 'RET_GAN') {
                const match = (maestros.impuestos_retencion || []).find(it => parseInt(it.impuesto_tipo_id) === 21 || it.impuesto_tipo.toUpperCase().includes('GANANCIA'));
                if (match) defaultImpTipoId = parseInt(match.impuesto_tipo_id);
            } else if (codigo === 'RET_IIBB') {
                const match = (maestros.impuestos_retencion || []).find(it => parseInt(it.impuesto_tipo_id) === 4 || it.impuesto_tipo.toUpperCase().includes('IIBB') || it.impuesto_tipo.toUpperCase().includes('INGRESOS BRUTOS'));
                if (match) defaultImpTipoId = parseInt(match.impuesto_tipo_id);
            }
        }

        let optionsImp = '<option value="">-- Tipo Impuesto --</option>';
        (maestros.impuestos_retencion || []).forEach(it => {
            const sel = (defaultImpTipoId && parseInt(defaultImpTipoId) === parseInt(it.impuesto_tipo_id)) ? 'selected' : '';
            optionsImp += `<option value="${it.impuesto_tipo_id}" ${sel}>${escapeHtml(it.impuesto_tipo)}</option>`;
        });

        let defaultJurId = datosPrevios ? parseInt(datosPrevios.jurisdiccion_id) : 0;
        let optionsJur = '<option value="">-- Jurisdicción --</option>';
        (maestros.jurisdicciones || []).forEach(j => {
            const sel = (defaultJurId && parseInt(defaultJurId) === parseInt(j.jurisdiccion_id)) ? 'selected' : '';
            optionsJur += `<option value="${j.jurisdiccion_id}" ${sel}>${escapeHtml(j.jurisdiccion_nombre)}</option>`;
        });

        const nroCert = datosPrevios ? escapeHtml(datosPrevios.retencion_certificado_nro || '') : '';
        const base = datosPrevios ? (datosPrevios.retencion_base_imponible || '') : '';
        const ali = datosPrevios ? (datosPrevios.retencion_alicuota || '') : '';

        const impObj = (maestros.impuestos_retencion || []).find(it => parseInt(it.impuesto_tipo_id) === parseInt(defaultImpTipoId));
        const esIIBBInicial = (codigo === 'RET_IIBB') || (impObj && (impObj.impuesto_tipo.toUpperCase().includes('IIBB') || impObj.impuesto_tipo.toUpperCase().includes('INGRESOS BRUTOS')));

        contenedor.innerHTML = `
            <div class="row g-1 align-items-center">
                <div class="col-sm-3">
                    <select class="form-select form-select-sm input-retencion-tipo" required title="Tipo de Retención">
                        ${optionsImp}
                    </select>
                </div>
                <div class="col-sm-3 col-jurisdiccion ${esIIBBInicial ? '' : 'd-none'}">
                    <select class="form-select form-select-sm input-retencion-jurisdiccion" title="Jurisdicción de Ingresos Brutos">
                        ${optionsJur}
                    </select>
                </div>
                <div class="${esIIBBInicial ? 'col-sm-2' : 'col-sm-3'} col-cert">
                    <input type="text" class="form-control form-control-sm input-retencion-cert" placeholder="N° Certificado" value="${nroCert}" title="Número de Certificado de Retención" required>
                </div>
                <div class="col-sm-2">
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm input-retencion-base" placeholder="Base Imp. ($)" value="${base}" title="Base Imponible ($)">
                </div>
                <div class="col-sm-2">
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm input-retencion-ali" placeholder="%" value="${ali}" title="Alícuota (%)">
                </div>
            </div>
        `;

        const selTipoRet = contenedor.querySelector('.input-retencion-tipo');
        const colJur = contenedor.querySelector('.col-jurisdiccion');
        const selJur = contenedor.querySelector('.input-retencion-jurisdiccion');
        const colCert = contenedor.querySelector('.col-cert');
        const inpBase = contenedor.querySelector('.input-retencion-base');
        const inpAli = contenedor.querySelector('.input-retencion-ali');

        const recalcularMontoRetencion = () => {
            const b = parseFloat(inpBase?.value) || 0;
            const a = parseFloat(inpAli?.value) || 0;
            if (b > 0 && a > 0) {
                const impInput = tr.querySelector('.input-medio-importe');
                if (impInput && (!impInput.value || parseFloat(impInput.value) === 0)) {
                    impInput.value = (Math.round((b * a / 100) * 100) / 100).toFixed(2);
                    recalcularBalance();
                }
            }
        };

        if (inpBase) inpBase.addEventListener('input', recalcularMontoRetencion);
        if (inpAli) inpAli.addEventListener('input', recalcularMontoRetencion);

        if (selTipoRet) {
            selTipoRet.addEventListener('change', function() {
                const selectedId = parseInt(this.value);
                const selectedObj = (maestros.impuestos_retencion || []).find(it => parseInt(it.impuesto_tipo_id) === selectedId);
                const esIIBB = selectedObj && (selectedObj.impuesto_tipo.toUpperCase().includes('IIBB') || selectedObj.impuesto_tipo.toUpperCase().includes('INGRESOS BRUTOS'));

                if (esIIBB) {
                    colJur?.classList.remove('d-none');
                    if (selJur) selJur.required = true;
                    if (colCert) colCert.className = 'col-sm-2 col-cert';
                } else {
                    colJur?.classList.add('d-none');
                    if (selJur) {
                        selJur.required = false;
                        selJur.value = '';
                    }
                    if (colCert) colCert.className = 'col-sm-3 col-cert';
                }
            });
        }
    }
}

/**
 * Actualiza la caja destino de las líneas de EFECTIVO al cambiar de sucursal
 */
function actualizarCajasMediosEfectivo() {
    const rows = document.querySelectorAll('#tbodyMedios tr');
    rows.forEach(tr => {
        const selMedio = tr.querySelector('.select-medio-tipo');
        if (selMedio) {
            const opt = selMedio.options[selMedio.selectedIndex];
            const tipo = opt?.dataset.tipo || '';
            if (tipo === 'EFECTIVO') {
                renderizarCamposEspecificosMedio(tr);
            }
        }
    });
}


/**
 * Auto imputa los valores recibidos entre los comprobantes más antiguos
 */
function autoImputarPendientes() {
    let disponible = calcularTotalMedios();
    if (disponible <= 0) {
        Swal.fire('Atención', 'Primero ingrese los importes de los medios de cobro recibidos.', 'warning');
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

    recalcularBalance();
}

/**
 * Limpia todas las imputaciones de la tabla de comprobantes pendientes
 */
/**
 * Limpia todas las imputaciones de la tabla de comprobantes pendientes
 */
function limpiarImputaciones() {
    const rows = document.querySelectorAll('#tbodyPendientes tr');
    rows.forEach(tr => {
        const inp = tr.querySelector('.input-imputar');
        const chk = tr.querySelector('.check-pendiente');
        if (inp) inp.value = '0.00';
        if (chk) chk.checked = false;
    });
    const checkTodos = document.getElementById('checkTodosPendientes');
    if (checkTodos) checkTodos.checked = false;
    recalcularBalance();
}

/**
 * Calcula el total de medios cargados
 */
function calcularTotalMedios() {
    let total = 0.0;
    const inputs = document.querySelectorAll('.input-medio-importe');
    inputs.forEach(inp => {
        total += parseFloat(inp.value) || 0.0;
    });
    return Math.round(total * 100) / 100;
}

/**
 * Calcula el total de imputaciones ingresadas
 */
function calcularTotalImputado() {
    let total = 0.0;
    const inputs = document.querySelectorAll('.input-imputar');
    inputs.forEach(inp => {
        total += parseFloat(inp.value) || 0.0;
    });
    return Math.round(total * 100) / 100;
}

/**
 * Recalcula el balance en tiempo real entre valores recibidos e imputaciones
 */
function recalcularBalance() {
    const totalMedios = calcularTotalMedios();
    const totalImputado = calcularTotalImputado();
    const diferencia = Math.round((totalMedios - totalImputado) * 100) / 100;

    // Actualizar visualizaciones
    const totalMediosDisp = document.getElementById('totalMediosDisplay');
    const resTotalMedios = document.getElementById('resumenTotalMedios');
    if (totalMediosDisp) totalMediosDisp.innerText = `$ ${formatearMoneda(totalMedios)}`;
    if (resTotalMedios) resTotalMedios.innerText = `$ ${formatearMoneda(totalMedios)}`;

    const totalImpDisp = document.getElementById('totalImputadoDisplay');
    const resTotalImp = document.getElementById('resumenTotalImputado');
    if (totalImpDisp) totalImpDisp.innerText = `$ ${formatearMoneda(totalImputado)}`;
    if (resTotalImp) resTotalImp.innerText = `$ ${formatearMoneda(totalImputado)}`;

    const elDif = document.getElementById('resumenDiferencia');
    const alertaDesbalance = document.getElementById('alertaDesbalance');
    const avisoACuenta = document.getElementById('avisoCobroACuenta');
    const btnGuardar = document.getElementById('btnGuardarBorrador');
    const btnConfirmar = document.getElementById('btnConfirmarEmision');

    if (totalImputado > totalMedios + 0.001) {
        // ERROR: Imputó más de lo cobrado
        if (elDif) {
            elDif.innerText = `-$ ${formatearMoneda(Math.abs(diferencia))}`;
            elDif.className = 'mb-0 fw-bold text-danger';
        }
        if (alertaDesbalance) alertaDesbalance.classList.remove('d-none');
        if (avisoACuenta) avisoACuenta.classList.add('d-none');
        // No deshabilitamos los botones para que al hacer click se le avise al usuario mediante SweetAlert
        if (btnGuardar) btnGuardar.disabled = false;
        if (btnConfirmar) btnConfirmar.disabled = false;
    } else if (diferencia > 0.001) {
        // VÁLIDO: Hay saldo a cuenta para el cliente (D6 / RT02)
        if (elDif) {
            elDif.innerText = `$ ${formatearMoneda(diferencia)}`;
            elDif.className = 'mb-0 fw-bold text-info';
        }
        if (alertaDesbalance) alertaDesbalance.classList.add('d-none');
        if (avisoACuenta) avisoACuenta.classList.remove('d-none');
        if (btnGuardar) btnGuardar.disabled = false;
        if (btnConfirmar) btnConfirmar.disabled = false;
    } else {
        // VÁLIDO: Balance exacto 0
        if (elDif) {
            elDif.innerText = `$ 0,00`;
            elDif.className = 'mb-0 fw-bold text-success';
        }
        if (alertaDesbalance) alertaDesbalance.classList.add('d-none');
        if (avisoACuenta) avisoACuenta.classList.add('d-none');
        if (btnGuardar) btnGuardar.disabled = false;
        if (btnConfirmar) btnConfirmar.disabled = false;
    }
}

/**
 * Recopila y valida los datos para guardar la cobranza
 */
function recopilarDatosCobranza() {
    const cobranzaId = parseInt(document.getElementById('cobranza_id')?.value) || 0;
    const entidadId = parseInt(document.getElementById('selectCliente')?.value) || 0;
    const sucursalId = parseInt(document.getElementById('selectSucursal')?.value) || 1;
    const comprobantePv = parseInt(document.getElementById('selectPuntoVenta')?.value) || 0;
    const inpEmision = document.getElementById('inputFechaEmision');
    const inpContable = document.getElementById('inputFechaContable');
    const fEmision = inpEmision ? inpEmision.value : new Date().toISOString().split('T')[0];
    const fContabilidad = (inpContable && inpContable.value) ? inpContable.value : fEmision;
    const observaciones = document.getElementById('inputObservaciones')?.value?.trim() || '';

    if (entidadId <= 0) {
        Swal.fire('Atención', 'Debe seleccionar un cliente.', 'warning');
        return null;
    }

    if (comprobantePv <= 0) {
        Swal.fire('Atención', 'Debe seleccionar un punto de venta.', 'warning');
        return null;
    }

    // Auto-seleccionar tipo de comprobante si no está seleccionado pero hay opciones válidas
    const selectTipoComp = document.getElementById('selectComprobanteTipo');
    let comprobanteTipoId = parseInt(selectTipoComp?.value) || 0;
    if (comprobanteTipoId <= 0 && selectTipoComp && selectTipoComp.options.length > 0) {
        for (let i = 0; i < selectTipoComp.options.length; i++) {
            const v = parseInt(selectTipoComp.options[i].value);
            if (v > 0) {
                selectTipoComp.selectedIndex = i;
                comprobanteTipoId = v;
                break;
            }
        }
    }

    // Recopilar medios
    const medios = [];
    const filasMedios = document.querySelectorAll('#tbodyMedios tr');
    if (filasMedios.length === 0) {
        Swal.fire('Atención', 'Debe ingresar al menos un medio de cobro.', 'warning');
        return null;
    }

    for (const tr of filasMedios) {
        const select = tr.querySelector('.select-medio-tipo');
        if (!select || select.selectedIndex < 0) {
            Swal.fire('Atención', 'Seleccione el medio de cobro en cada fila.', 'warning');
            return null;
        }
        const opt = select.options[select.selectedIndex];
        const medioPagoId = parseInt(select.value) || 0;
        if (medioPagoId <= 0) {
            Swal.fire('Atención', 'Seleccione un medio de pago válido.', 'warning');
            return null;
        }
        const tipo = opt ? (opt.dataset.tipo || '') : '';
        const inputImp = tr.querySelector('.input-medio-importe');
        const importe = parseFloat(inputImp?.value) || 0;

        if (importe <= 0) {
            Swal.fire('Atención', 'Todos los medios de cobro deben tener un importe mayor a cero.', 'warning');
            if (inputImp) inputImp.focus();
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
                medioObj.nro_operacion = medioObj.numero_operacion;
            }
        } else if (tipo === 'CHEQUE_TERCERO') {
            const selBanco = tr.querySelector('.input-cheque-banco');
            const inpNro = tr.querySelector('.input-cheque-numero');
            const inpVto = tr.querySelector('.input-cheque-vto');
            const inpLib = tr.querySelector('.input-cheque-librador');

            if (!selBanco?.value || !inpNro?.value || !inpVto?.value) {
                Swal.fire('Atención', 'Complete los datos obligatorios del cheque (Banco, Número y Fecha de Pago).', 'warning');
                return null;
            }

            medioObj.cheque = {
                banco_id: parseInt(selBanco.value),
                numero: inpNro.value.trim(),
                f_pago: inpVto.value,
                librador_nombre: inpLib ? inpLib.value.trim() : ''
            };
        } else if (tipo === 'RETENCION') {
            const selImp = tr.querySelector('.input-retencion-tipo');
            const selJur = tr.querySelector('.input-retencion-jurisdiccion');
            const inpCert = tr.querySelector('.input-retencion-cert');
            const inpBase = tr.querySelector('.input-retencion-base');
            const inpAli = tr.querySelector('.input-retencion-ali');

            if (!selImp?.value || !inpCert?.value) {
                Swal.fire('Atención', 'Complete el tipo y número de certificado de la retención.', 'warning');
                return null;
            }

            const impId = parseInt(selImp.value);
            const impObj = (maestros.impuestos_retencion || []).find(it => parseInt(it.impuesto_tipo_id) === impId);
            const esIIBB = impObj && (impObj.impuesto_tipo.toUpperCase().includes('IIBB') || impObj.impuesto_tipo.toUpperCase().includes('INGRESOS BRUTOS'));

            if (esIIBB && (!selJur || !selJur.value)) {
                Swal.fire('Atención', 'Para la retención de Ingresos Brutos (IIBB), debe seleccionar la Jurisdicción (ARBA, AGIP, etc.).', 'warning');
                return null;
            }

            medioObj.impuesto_tipo_id = impId;
            medioObj.jurisdiccion_id = selJur && selJur.value ? parseInt(selJur.value) : null;
            medioObj.retencion_certificado_nro = inpCert.value.trim();
            medioObj.retencion_base_imponible = parseFloat(inpBase?.value) || 0;
            medioObj.retencion_alicuota = parseFloat(inpAli?.value) || 0;
        }

        medios.push(medioObj);
    }

    // Recopilar imputaciones
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

    const totalMedios = calcularTotalMedios();
    const totalImputado = calcularTotalImputado();

    if (totalImputado > totalMedios + 0.001) {
        Swal.fire('Atención', `El total imputado ($ ${formatearMoneda(totalImputado)}) supera el total de medios cobrados ($ ${formatearMoneda(totalMedios)}).`, 'warning');
        return null;
    }

    return {
        cobranza_id: cobranzaId,
        entidad_id: entidadId,
        sucursal_id: sucursalId,
        comprobante_pv: comprobantePv,
        comprobante_tipo_id: comprobanteTipoId,
        f_emision: fEmision,
        f_contabilidad: fContabilidad,
        observaciones: observaciones,
        medios: medios,
        imputaciones: imputaciones
    };
}

/**
 * Guarda la cobranza como borrador o la confirma directamente
 */
async function guardarCobranza(confirmarInmediato = false) {
    const payload = recopilarDatosCobranza();
    if (!payload) return;

    if (confirmarInmediato) {
        const confirmacion = await Swal.fire({
            title: '¿Confirmar Recibo de Cobranza?',
            text: 'Se emitirá el recibo oficial, se afectarán los saldos de clientes y cuentas de tesorería, y se generará el asiento contable.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, Confirmar y Emitir',
            cancelButtonText: 'Cancelar'
        });
        if (!confirmacion.isConfirmed) return;
    }

    try {
        Swal.fire({
            title: 'Procesando...',
            text: 'Guardando datos de la cobranza...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        // 1. Guardar borrador
        const respGuardar = await fetch('cobranzas_ajax.php?accion=guardar&empresa_idx=' + window.EMPRESA_ID, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const resG = await respGuardar.json();

        if (!resG.success) {
            Swal.fire('Error', resG.error || 'No se pudo guardar la cobranza.', 'error');
            return;
        }

        const idGenerado = resG.cobranza_id;

        // 2. Si es confirmación, ejecutar confirmación atómica
        if (confirmarInmediato) {
            Swal.update({ text: 'Confirmando e imputando saldos en tesorería...' });

            const respConf = await fetch('cobranzas_ajax.php?accion=confirmar&empresa_idx=' + window.EMPRESA_ID, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: idGenerado })
            });
            const resC = await respConf.json();

            if (!resC.success) {
                Swal.fire('Error al Confirmar', resC.error || 'Ocurrió un error durante la confirmación.', 'error');
                tablaDT.ajax.reload();
                return;
            }

            Swal.fire({
                title: '¡Recibo Emitido!',
                html: `<strong>${resC.message}</strong>`,
                icon: 'success'
            });

            ocultarModal('modalCobranza');
            tablaDT.ajax.reload();
            verRecibo(idGenerado);
        } else {
            Swal.fire('Guardado', 'El borrador de cobranza fue guardado correctamente.', 'success');
            ocultarModal('modalCobranza');
            tablaDT.ajax.reload();
        }
    } catch (e) {
        Swal.fire('Error Inesperado', e.message || 'Error de comunicación.', 'error');
    }
}

/**
 * Carga un borrador para editar
 */
async function editarBorrador(id) {
    try {
        Swal.fire({
            title: 'Cargando...',
            text: 'Obteniendo borrador...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const resp = await fetch(`cobranzas_ajax.php?accion=obtener&empresa_idx=${window.EMPRESA_ID}&id=${id}`);
        const res = await resp.json();
        Swal.close();

        if (!res.success || !res.data) {
            Swal.fire('Error', res.error || 'No se pudo cargar la cobranza.', 'error');
            return;
        }

        const { cabecera, medios, imputaciones } = res.data;

        document.getElementById('formCobranza').reset();
        document.getElementById('cobranza_id').value = cabecera.cobranza_id;
        document.getElementById('modalCobranzaLabel').innerHTML = `<i class="fas fa-edit me-2"></i>Editar Borrador de Cobranza #${cabecera.cobranza_id}`;

        document.getElementById('selectCliente').value = cabecera.entidad_id;
        document.getElementById('selectSucursal').value = cabecera.sucursal_id;
        poblarSelectPuntosVenta(cabecera.sucursal_id);
        if (cabecera.comprobante_pv) {
            document.getElementById('selectPuntoVenta').value = cabecera.comprobante_pv;
        }
        await cargarTiposPorPuntoVenta(cabecera.comprobante_tipo_id);
        const nroInput = document.getElementById('inputComprobanteNro');
        if (nroInput) {
            nroInput.value = (cabecera.comprobante_nro > 0)
                ? `${String(cabecera.pv_nro ?? cabecera.comprobante_pv).padStart(4, '0')}-${String(cabecera.comprobante_nro).padStart(8, '0')}`
                : 'Borrador (Sin emitir)';
        }
        document.getElementById('inputFechaEmision').value = cabecera.f_emision;
        document.getElementById('inputFechaContable').value = cabecera.f_contabilidad || cabecera.f_emision;
        document.getElementById('inputObservaciones').value = cabecera.observaciones || '';

        // Cargar medios
        const tbodyMedios = document.getElementById('tbodyMedios');
        tbodyMedios.innerHTML = '';
        indiceMedio = 0;

        medios.forEach(m => {
            indiceMedio++;
            const tr = document.createElement('tr');
            tr.id = `fila_medio_${indiceMedio}`;
            tr.dataset.indice = indiceMedio;

            let optionsMedios = '';
            maestros.medios_pago.forEach(mp => {
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

            selectTipo.addEventListener('change', () => renderizarCamposEspecificosMedio(tr));
            inputImporte.addEventListener('input', recalcularBalance);
            btnQuitar.addEventListener('click', () => {
                tr.remove();
                recalcularBalance();
            });

            renderizarCamposEspecificosMedio(tr, m);
        });

        // Cargar comprobantes pendientes con las imputaciones previas
        await cargarComprobantesPendientes(cabecera.entidad_id, imputaciones);

        mostrarModal('modalCobranza');

    } catch (e) {
        Swal.fire('Error', e.message || 'Error al cargar borrador.', 'error');
    }
}

/**
 * Confirmación directa de un borrador desde la lista principal
 */
async function confirmarCobranzaDirecta(id) {
    const confirmacion = await Swal.fire({
        title: '¿Confirmar Recibo de Cobranza?',
        text: 'Se emitirá el recibo oficial, se registrarán los ingresos en tesorería y se afectarán los saldos pendientes de los comprobantes imputados.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, Confirmar Recibo',
        cancelButtonText: 'Cancelar'
    });
    if (!confirmacion.isConfirmed) return;

    try {
        Swal.fire({
            title: 'Confirmando...',
            text: 'Aplicando imputaciones y registrando libro...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const resp = await fetch('cobranzas_ajax.php?accion=confirmar&empresa_idx=' + window.EMPRESA_ID, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const res = await resp.json();

        if (res.success) {
            Swal.fire({
                title: '¡Recibo Confirmado!',
                html: `<strong>${res.message}</strong>`,
                icon: 'success'
            });
            tablaDT.ajax.reload();
            verRecibo(id);
        } else {
            Swal.fire('Error', res.error || 'No se pudo confirmar la cobranza.', 'error');
        }
    } catch (e) {
        Swal.fire('Error', e.message || 'Error de comunicación.', 'error');
    }
}

/**
 * Visualiza el recibo formal completo con comprobantes y medios
 */
async function verRecibo(id) {
    try {
        Swal.fire({
            title: 'Cargando...',
            text: 'Generando vista del recibo...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const resp = await fetch(`cobranzas_ajax.php?accion=obtener&empresa_idx=${window.EMPRESA_ID}&id=${id}`);
        const res = await resp.json();
        Swal.close();

        if (!res.success || !res.data) {
            Swal.fire('Error', res.error || 'No se pudo obtener el recibo.', 'error');
            return;
        }

        const { cabecera, medios, imputaciones } = res.data;
        const cuerpo = document.getElementById('cuerpoReciboImpresion');

        const tituloComprobante = cabecera.comprobante_tipo ? cabecera.comprobante_tipo.toUpperCase() : 'RECIBO DE COBRO';
        const nroRecibo = (cabecera.comprobante_nro > 0)
            ? `${String(cabecera.pv_nro ?? cabecera.comprobante_pv).padStart(4, '0')}-${String(cabecera.comprobante_nro).padStart(8, '0')}`
            : 'BORRADOR';

        let tablaMediosHtml = '';
        medios.forEach(m => {
            let detalle = m.medio_pago;
            if (m.medio_pago_tipo === 'EFECTIVO' && m.tesoreria_cuenta) {
                detalle += ` (${m.tesoreria_cuenta})`;
            } else if (m.medio_pago_tipo === 'TRANSFERENCIA') {
                detalle += ` (${m.tesoreria_cuenta || 'Banco'} - Op: ${m.numero_operacion || 'S/N'})`;
            } else if (m.medio_pago_tipo === 'CHEQUE_TERCERO') {
                detalle += ` (${m.cheque_banco || 'Banco'} N° ${m.cheque_nro || 'S/N'} Vto: ${m.cheque_f_pago || '-'})`;
            } else if (m.medio_pago_tipo === 'RETENCION') {
                let detRet = m.impuesto_tipo ? escapeHtml(m.impuesto_tipo) : 'Retención';
                if (m.jurisdiccion_nombre) {
                    detRet += ` - ${escapeHtml(m.jurisdiccion_nombre)}`;
                }
                detRet += ` (Certificado N° ${escapeHtml(m.retencion_certificado_nro || 'S/N')}`;
                if (parseFloat(m.retencion_base_imponible) > 0) {
                    detRet += ` · Base: $ ${formatearMoneda(m.retencion_base_imponible)}`;
                }
                if (parseFloat(m.retencion_alicuota) > 0) {
                    detRet += ` @ ${formatearMoneda(m.retencion_alicuota)}%`;
                }
                detRet += `)`;
                detalle = `${m.medio_pago}: ${detRet}`;
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
                        <em>Cobro ingresado sin comprobantes aplicados (Cobro a cuenta / Anticipo a favor del cliente).</em>
                    </td>
                </tr>
            `;
        }

        cuerpo.innerHTML = `
            <div class="border p-4 bg-white shadow-sm" style="font-family: Arial, sans-serif;">
                <!-- Membrete -->
                <div class="row align-items-center border-bottom pb-3 mb-3">
                    <div class="col-sm-7">
                        <h4 class="fw-bold mb-0 text-dark">CASA LUCHO</h4>
                        <small class="text-muted d-block">Distribuidora Mayorista de Autopartes</small>
                        <small class="text-muted">Sucursal: ${escapeHtml(cabecera.sucursal_nombre)}</small>
                    </div>
                    <div class="col-sm-5 text-end">
                        <div class="border border-dark p-2 d-inline-block text-center rounded" style="min-width: 180px;">
                            <span class="fs-5 fw-bold d-block">${escapeHtml(tituloComprobante)}</span>
                            <span class="fs-6 fw-bold text-primary">N° ${nroRecibo}</span>
                            <div class="small text-muted mt-1">Fecha: ${cabecera.f_emision}</div>
                        </div>
                    </div>
                </div>

                <!-- Datos del Cliente -->
                <div class="bg-light p-3 rounded mb-3 border">
                    <div class="row">
                        <div class="col-sm-8">
                            <span class="text-muted small d-block">CLIENTE:</span>
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

                <!-- Detalle de Medios Recibidos -->
                <div class="mb-3">
                    <h6 class="fw-bold text-uppercase small text-secondary border-bottom pb-1">
                        <i class="fas fa-money-bill-wave me-1"></i> Valores y Medios de Pago Recibidos
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
                                <td class="text-end">TOTAL RECIBIDO:</td>
                                <td class="text-end text-success fs-6">$ ${formatearMoneda(cabecera.importe_total)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Detalle de Comprobantes Imputados -->
                <div class="mb-3">
                    <h6 class="fw-bold text-uppercase small text-secondary border-bottom pb-1">
                        <i class="fas fa-file-invoice-dollar me-1"></i> Comprobantes Cancelados / Imputados
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

        mostrarModal('modalVerRecibo');

    } catch (e) {
        Swal.fire('Error', e.message || 'Error al visualizar recibo.', 'error');
    }
}

/**
 * Abre el modal para anular una cobranza confirmada con motivo obligatorio (§2.6 / RT08)
 */
function abrirModalAnular(id) {
    document.getElementById('anular_cobranza_id').value = id;
    document.getElementById('motivoAnulacion').value = '';
    mostrarModal('modalAnular');
}

/**
 * Ejecuta la anulación con el motivo ingresado
 */
async function ejecutarAnulacion(e) {
    e.preventDefault();
    const id = parseInt(document.getElementById('anular_cobranza_id').value);
    const motivo = document.getElementById('motivoAnulacion').value.trim();

    if (!motivo) {
        Swal.fire('Atención', 'Debe indicar el motivo de anulación.', 'warning');
        return;
    }

    try {
        Swal.fire({
            title: 'Anulando...',
            text: 'Revertiendo saldos e imputaciones...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const resp = await fetch('cobranzas_ajax.php?accion=anular&empresa_idx=' + window.EMPRESA_ID, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, motivo: motivo })
        });
        const res = await resp.json();

        if (res.success) {
            Swal.fire('Cobranza Anulada', res.message || 'Recibo anulado con éxito.', 'success');
            ocultarModal('modalAnular');
            tablaDT.ajax.reload();
        } else {
            Swal.fire('Error al Anular', res.error || 'No se pudo anular la cobranza.', 'error');
        }
    } catch (e) {
        Swal.fire('Error', e.message || 'Error al procesar anulación.', 'error');
    }
}

/**
 * Elimina un borrador de cobranza
 */
async function eliminarBorrador(id) {
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
        const resp = await fetch(`cobranzas_ajax.php?accion=eliminar&empresa_idx=${window.EMPRESA_ID}&id=${id}`, { method: 'POST' });
        const res = await resp.json();

        if (res.success) {
            Swal.fire('Eliminado', res.message || 'Borrador eliminado.', 'success');
            tablaDT.ajax.reload();
        } else {
            Swal.fire('Error', res.error || 'No se pudo eliminar el borrador.', 'error');
        }
    } catch (e) {
        Swal.fire('Error', e.message || 'Error de comunicación.', 'error');
    }
}

/**
 * Utilidad de formato de moneda
 */
function formatearMoneda(val) {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/**
 * Utilidad de escape HTML
 */
function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
