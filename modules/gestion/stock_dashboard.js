/**
 * stock_dashboard.js — Dashboard de stock (página 114). Solo lectura.
 * KPIs + gráfico del período, stock por producto (una columna por boca) y libro de
 * movimientos (ambas grillas server-side), y kardex por producto en un modal.
 */
$(function () {
    'use strict';

    const AJAX_URL = 'stock_dashboard_ajax.php';
    const empresaIdx = typeof SDB_EMPRESA_ID !== 'undefined' ? SDB_EMPRESA_ID : 0;
    const fmtCant = Fmt.numberFormat({ maximumFractionDigits: 4 });
    const fmtMoneda = { format: (v) => Fmt.moneda(v, 0) };
    const fmtCosto = Fmt.numberFormat({ minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const DT_IDIOMA = { url: window.APP_ASSETS_URL + 'js/datatables-i18n-2.1.8/es-ES.json' };
    const COL_PRIMERA_BOCA = 3; // Código, Producto, Categoría, [una por boca], Total, ...

    let bocas = [];
    let tablaStock = null;
    let tablaMov = null;
    let chart = null;
    let kardexProductoId = 0;

    // ------------------------------------------------------------------ utilidades
    function esc(v) {
        return $('<div>').text(v == null ? '' : String(v)).html();
    }

    function num(v) {
        return v === null || v === undefined || v === '' ? '' : fmtCant.format(parseFloat(v));
    }

    function cantidad(v) {
        const n = parseFloat(v || 0);
        const clase = n < 0 ? 'sdb-neg' : (n === 0 ? 'sdb-cero' : '');
        return `<span class="${clase}">${fmtCant.format(n)}</span>`;
    }

    function costo(v) {
        return v === null || v === undefined || v === '' ? '' : fmtCosto.format(parseFloat(v));
    }

    function fecha(v, conHora) {
        if (!v) return '';
        return esc(conHora === false ? Fmt.fecha(v) : Fmt.fechaHora(v));
    }

    function isoFecha(d) {
        const p = n => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
    }

    function comprobante(row) {
        const pv = row.comprobante_pv ? String(row.comprobante_pv).padStart(4, '0') + '-' : '';
        const nro = parseInt(row.comprobante_nro || 0, 10) > 0
            ? pv + String(row.comprobante_nro).padStart(8, '0')
            : '<span class="text-muted">s/n</span>';
        return `${esc(row.comprobante_tipo)} <span class="text-nowrap">${nro}</span>`;
    }

    function filtrosGenerales() {
        return {
            boca_id: $('#filtroBoca').val() || 0,
            desde: $('#filtroDesde').val(),
            hasta: $('#filtroHasta').val()
        };
    }

    function llamar(accion, datos) {
        return $.ajax({
            url: AJAX_URL,
            method: 'GET',
            dataType: 'json',
            data: Object.assign({ accion: accion, empresa_idx: empresaIdx }, datos || {})
        }).then(function (r) {
            if (!r || !r.success) return $.Deferred().reject(r && r.message ? r.message : 'Error inesperado');
            return r;
        }, function (xhr) {
            const r = xhr.responseJSON;
            return $.Deferred().reject(r && r.message ? r.message : 'Error de comunicación con el servidor');
        });
    }

    function error(msg) {
        Swal.fire({ icon: 'error', title: 'Atención', text: msg });
    }

    function dataSrc(json) {
        if (!json.success) {
            error(json.message || 'No se pudo cargar la grilla');
            return [];
        }
        return json.data;
    }

    // ------------------------------------------------------------------ resumen
    function cargarResumen() {
        llamar('resumen', filtrosGenerales()).then(r => {
            const d = r.data;
            const k = d.kpis;
            $('#kpiConStock').text(fmtCant.format(k.productos_con_stock));
            $('#kpiUnidades').text(fmtCant.format(k.unidades));
            $('#kpiValorizado').text(fmtMoneda.format(k.valorizado));
            $('#kpiAlertas').text(fmtCant.format(k.alertas_abiertas));
            $('#kpiNegativos').text(fmtCant.format(k.productos_negativos));
            $('#kpiMovimientos').text(fmtCant.format(k.movimientos));
            $('#kpiIngresos').text('+' + fmtCant.format(k.ingresos) + ' u.');
            $('#kpiEgresos').text('−' + fmtCant.format(k.egresos) + ' u.');
            dibujarGrafico(d.serie, d.desde, d.hasta);

            $('#listaTop').html(d.top_egresos.length
                ? d.top_egresos.map(p => `<li>
                        <a href="#" class="sdb-top-nombre link-body-emphasis btn-kardex" data-id="${p.producto_id}"
                           title="${esc(p.producto_codigo + ' — ' + p.producto_nombre)}">${esc(p.producto_nombre)}</a>
                        <span class="fw-bold text-nowrap">${num(p.egresos_netos)}</span></li>`).join('')
                : '<li class="text-muted">Sin egresos en el período.</li>');

            $('#tablaPorTipo').html(d.por_tipo.length
                ? d.por_tipo.map(t => `<tr>
                        <td>${esc(t.comprobante_tipo)} <small class="text-muted">${esc(t.codigo)}</small></td>
                        <td class="text-end text-nowrap ${t.tipo_movimiento === 'INGRESO' ? 'text-success' : 'text-danger'}">
                            ${t.tipo_movimiento === 'INGRESO' ? '+' : '−'}${num(t.cantidad)}</td>
                        <td class="text-end text-muted">${num(t.movimientos)} mov.</td></tr>`).join('')
                : '<tr><td class="text-muted">Sin movimientos en el período.</td></tr>');
        }, error);
    }

    function dibujarGrafico(serie, desde, hasta) {
        // Completa los días sin movimientos para que el eje sea continuo.
        const porDia = {};
        serie.forEach(s => { porDia[s.fecha] = s; });
        const etiquetas = [], ingresos = [], egresos = [];
        const fin = new Date(hasta + 'T00:00:00');
        for (let d = new Date(desde + 'T00:00:00'); d <= fin; d.setDate(d.getDate() + 1)) {
            const k = isoFecha(d);
            etiquetas.push(d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit' }));
            ingresos.push(porDia[k] ? parseFloat(porDia[k].ingresos) : 0);
            egresos.push(porDia[k] ? parseFloat(porDia[k].egresos) : 0);
        }
        if (typeof Chart === 'undefined') return;
        if (chart) chart.destroy();
        chart = new Chart(document.getElementById('chartMovimientos'), {
            type: 'bar',
            data: {
                labels: etiquetas,
                datasets: [
                    { label: 'Ingresos', data: ingresos, backgroundColor: 'rgba(25, 135, 84, .75)', borderRadius: 3 },
                    { label: 'Egresos', data: egresos, backgroundColor: 'rgba(220, 53, 69, .75)', borderRadius: 3 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: { callbacks: { label: c => `${c.dataset.label}: ${fmtCant.format(c.parsed.y)} u.` } }
                },
                scales: { y: { beginAtZero: true, ticks: { callback: v => fmtCant.format(v) } } }
            }
        });
    }

    // ------------------------------------------------------------------ stock por producto
    function inicializarTablaStock() {
        const columnas = [
            { data: 'producto_codigo', name: 'codigo', title: 'Código', render: esc },
            {
                data: 'producto_nombre', name: 'nombre', title: 'Producto',
                render: (d, t, row) => esc(d) + (parseInt(row.alertas, 10) > 0
                    ? ' <i class="bi bi-exclamation-triangle-fill text-warning" title="Bajo el mínimo"></i>' : '')
            },
            { data: 'producto_categoria_nombre', name: 'categoria', title: 'Categoría', render: esc }
        ];
        bocas.forEach(b => {
            columnas.push({
                data: null, name: 'boca_' + b.boca_id, title: esc(b.boca_nombre), orderable: false,
                className: 'text-end',
                render: (d, t, row) => {
                    const v = row.por_boca ? row.por_boca[b.boca_id] : undefined;
                    return v === undefined ? '<span class="sdb-cero">—</span>' : cantidad(v);
                }
            });
        });
        columnas.push(
            {
                data: 'cantidad', name: 'cantidad', title: 'Total', className: 'text-end fw-bold',
                render: (d, t, row) => cantidad(d) + (row.unidad_abreviatura ? ` <small class="text-muted">${esc(row.unidad_abreviatura)}</small>` : '')
            },
            { data: 'valorizado', name: 'valorizado', title: 'Valorizado', className: 'text-end', render: d => fmtMoneda.format(parseFloat(d || 0)) },
            { data: 'f_ultimo_movimiento', name: 'ultimo', title: 'Últ. movimiento', render: d => fecha(d) }
        );

        tablaStock = $('#tablaStock').DataTable({
            buttons: [], // sin Copy/Excel/CSV/PDF del default de header1.php
            serverSide: true,
            processing: true,
            ajax: {
                url: AJAX_URL,
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar_stock';
                    d.empresa_idx = empresaIdx;
                    d.boca_id = $('#filtroBoca').val() || 0;
                    d.filtro = $('#filtroStock').val();
                    d.categoria_id = $('#filtroCategoria').val() || 0;
                },
                dataSrc: dataSrc
            },
            columns: columnas,
            order: [[1, 'asc']],
            pageLength: 25,
            searchDelay: 400,
            language: DT_IDIOMA
        });
        ajustarColumnasBoca();
    }

    /** Con una boca elegida, solo se ve su columna. */
    function ajustarColumnasBoca() {
        if (!tablaStock) return;
        const sel = parseInt($('#filtroBoca').val() || 0, 10);
        bocas.forEach((b, i) => {
            tablaStock.column(COL_PRIMERA_BOCA + i).visible(sel === 0 || parseInt(b.boca_id, 10) === sel, false);
        });
        tablaStock.columns.adjust();
    }

    // ------------------------------------------------------------------ libro de movimientos
    function inicializarTablaMovimientos() {
        tablaMov = $('#tablaMovimientos').DataTable({
            buttons: [], // sin Copy/Excel/CSV/PDF del default de header1.php
            serverSide: true,
            processing: true,
            ajax: {
                url: AJAX_URL,
                type: 'GET',
                data: function (d) {
                    Object.assign(d, filtrosGenerales());
                    d.accion = 'listar_movimientos';
                    d.empresa_idx = empresaIdx;
                    d.direccion = $('#filtroDireccion').val();
                    d.comprobante_tipo_id = $('#filtroTipo').val() || 0;
                },
                dataSrc: dataSrc
            },
            columns: [
                { data: 'f_movimiento', name: 'fecha', render: d => `<span class="text-nowrap">${fecha(d)}</span>` },
                { data: 'boca_nombre', name: 'boca', render: (d, t, row) => `${esc(d)}<br><small class="text-muted">${esc(row.sucursal_nombre)}</small>` },
                { data: 'producto_codigo', name: 'codigo', render: esc },
                { data: 'producto_nombre', name: 'producto', render: esc },
                { data: 'comprobante_tipo', name: 'comprobante', render: (d, t, row) => comprobante(row) },
                { data: 'entidad_nombre', name: 'entidad', render: esc },
                {
                    data: 'cantidad', name: 'cantidad', className: 'text-end fw-bold text-nowrap',
                    render: (d, t, row) => row.tipo_movimiento === 'INGRESO'
                        ? `<span class="text-success">+${num(d)}</span>`
                        : `<span class="text-danger">−${num(d)}</span>`
                },
                { data: 'costo_unitario', orderable: false, className: 'text-end', render: costo },
                { data: 'usuario_nombre', orderable: false, render: esc }
            ],
            order: [[0, 'desc']],
            pageLength: 25,
            searchDelay: 400,
            language: DT_IDIOMA
        });
    }

    // ------------------------------------------------------------------ kardex
    function abrirKardex(productoId) {
        kardexProductoId = productoId;
        $('#kardexBoca').val(String($('#filtroBoca').val() || 0));
        cargarKardex().then(() => bootstrap.Modal.getOrCreateInstance(document.getElementById('modalKardex')).show());
    }

    function cargarKardex() {
        const datos = Object.assign(filtrosGenerales(), { producto_id: kardexProductoId, boca_id: $('#kardexBoca').val() || 0 });
        return llamar('kardex', datos).then(r => {
            const d = r.data;
            const p = d.producto;
            $('#modalKardexTitulo').text(`${p.producto_codigo} — ${p.producto_nombre}`);
            $('#kardexSubtitulo').html([
                p.producto_categoria_nombre ? `Categoría: ${esc(p.producto_categoria_nombre)}` : '',
                p.codigo_barras ? `Cód. barras: ${esc(p.codigo_barras)}` : '',
                parseInt(p.controla_stock, 10) === 1 ? '' : '<span class="badge text-bg-secondary">No controla stock</span>'
            ].filter(Boolean).join(' · '));

            $('#kardexBocas').html(d.bocas.length
                ? d.bocas.map(b => `<tr>
                        <td>${esc(b.sucursal_nombre)}</td><td>${esc(b.boca_nombre)}</td>
                        <td class="text-end fw-bold">${cantidad(b.cantidad)}</td>
                        <td class="text-end">${b.stock_minimo === null ? '<span class="sdb-cero">—</span>' : num(b.stock_minimo)}</td>
                        <td class="text-end">${b.stock_maximo === null ? '<span class="sdb-cero">—</span>' : num(b.stock_maximo)}</td>
                        <td class="text-end">${costo(b.costo_promedio)}</td>
                        <td class="text-end">${costo(b.costo_ultima_compra)}</td>
                        <td>${fecha(b.f_ultimo_movimiento)}</td></tr>`).join('')
                : '<tr><td colspan="8" class="text-muted">Sin stock registrado en ninguna boca.</td></tr>');

            $('#kardexPeriodo').text(`del ${fecha(d.desde, false)} al ${fecha(d.hasta, false)}`);
            let html = `<tr class="table-light"><td colspan="6" class="fw-bold">Saldo al inicio del período</td>
                        <td class="text-end fw-bold">${cantidad(d.saldo_inicial)}</td><td colspan="2"></td></tr>`;
            html += d.movimientos.map(m => {
                const ing = m.tipo_movimiento === 'INGRESO';
                return `<tr>
                    <td class="text-nowrap">${fecha(m.f_movimiento)}</td>
                    <td>${esc(m.boca_nombre)}</td>
                    <td>${comprobante(m)}${m.observaciones ? `<br><small class="text-muted">${esc(m.observaciones)}</small>` : ''}</td>
                    <td>${esc(m.entidad_nombre)}</td>
                    <td class="text-end text-success">${ing ? num(m.cantidad) : ''}</td>
                    <td class="text-end text-danger">${ing ? '' : num(m.cantidad)}</td>
                    <td class="text-end fw-bold">${cantidad(m.saldo)}</td>
                    <td class="text-end">${costo(m.costo_unitario)}</td>
                    <td>${esc(m.usuario_nombre)}</td></tr>`;
            }).join('');
            if (!d.movimientos.length) {
                html += '<tr><td colspan="9" class="text-muted">Sin movimientos en el período.</td></tr>';
            }
            if (d.truncado) {
                html += '<tr><td colspan="9" class="text-warning">Se muestran los primeros 2000 movimientos: acotá el período.</td></tr>';
            }
            $('#kardexMovimientos').html(html);
        }, error);
    }

    // ------------------------------------------------------------------ eventos
    function recargarTodo() {
        cargarResumen();
        if (tablaStock) tablaStock.ajax.reload(null, false);
        if (tablaMov) tablaMov.ajax.reload();
    }

    function setRango(dias) {
        const hasta = new Date();
        const desde = new Date();
        desde.setDate(hasta.getDate() - (dias - 1));
        $('#filtroDesde').val(isoFecha(desde));
        $('#filtroHasta').val(isoFecha(hasta));
    }

    $('.btn-rango').on('click', function () {
        $('.btn-rango').removeClass('active');
        $(this).addClass('active');
        setRango(parseInt($(this).data('dias'), 10));
        cargarResumen();
        if (tablaMov) tablaMov.ajax.reload();
    });

    $('#filtroDesde, #filtroHasta').on('change', function () {
        $('.btn-rango').removeClass('active');
        cargarResumen();
        if (tablaMov) tablaMov.ajax.reload();
    });

    $('#filtroBoca').on('change', function () {
        ajustarColumnasBoca();
        recargarTodo();
    });
    $('#btnRecargar').on('click', recargarTodo);
    $('#filtroStock, #filtroCategoria').on('change', () => tablaStock && tablaStock.ajax.reload());
    $('#filtroDireccion, #filtroTipo').on('change', () => tablaMov && tablaMov.ajax.reload());

    // KPIs clickeables: filtran la grilla de stock.
    $('.sdb-kpi.sdb-clic').on('click', function () {
        $('#filtroStock').val($(this).data('filtro'));
        bootstrap.Tab.getOrCreateInstance(document.getElementById('tabStockBtn')).show();
        tablaStock && tablaStock.ajax.reload();
        document.getElementById('tabStock').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    // La grilla de movimientos se crea la primera vez que se abre su solapa (ancho correcto de columnas).
    $('#tabMovBtn').on('shown.bs.tab', function () {
        if (!tablaMov) inicializarTablaMovimientos();
        else tablaMov.columns.adjust();
    });

    $('#tablaStock tbody').on('click', 'tr', function () {
        const row = tablaStock.row(this).data();
        if (row) abrirKardex(row.producto_id);
    });
    $('#tablaMovimientos tbody').on('click', 'tr', function () {
        const row = tablaMov.row(this).data();
        if (row) abrirKardex(row.producto_id);
    });
    $(document).on('click', '.btn-kardex', function (e) {
        e.preventDefault();
        abrirKardex($(this).data('id'));
    });
    $('#kardexBoca').on('change', cargarKardex);

    // ------------------------------------------------------------------ arranque
    setRango(30);
    llamar('obtener_filtros').then(r => {
        bocas = r.data.bocas || [];
        let html = '<option value="0">Todas las bocas</option>';
        bocas.forEach(b => {
            html += `<option value="${b.boca_id}">${esc(b.sucursal_nombre)} — ${esc(b.boca_nombre)}</option>`;
        });
        $('#filtroBoca, #kardexBoca').html(html);

        $('#filtroCategoria').html('<option value="0">Todas las categorías</option>' + (r.data.categorias || [])
            .map(c => `<option value="${c.producto_categoria_id}">${esc(c.producto_categoria_nombre)}</option>`).join(''));
        $('#filtroTipo').html('<option value="0">Todos los comprobantes</option>' + (r.data.tipos || [])
            .map(t => `<option value="${t.comprobante_tipo_id}">${esc(t.comprobante_tipo)} (${esc(t.codigo)})</option>`).join(''));

        cargarResumen();
        inicializarTablaStock();
    }, error);
});
