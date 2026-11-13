/**
 * stock_consulta.js — Stock y Movimientos (página 638).
 * Filtros generales (sucursal, boca, producto, categoría, compatibilidad si la página la tiene, período) para las
 * dos grillas server-side: stock por producto (una columna por boca de la selección) y libro de movimientos. Los
 * totales de arriba son los de todo lo filtrado. Tocar un producto lo elige en el filtro y abre sus movimientos.
 * Con los mismos filtros: planilla de conteo (ventana para imprimir) y blanqueo de stock negativo (Ajustes de Stock).
 */
$(function () {
    'use strict';

    const AJAX_URL = 'stock_consulta_ajax.php';
    const empresaIdx = typeof SC_EMPRESA_ID !== 'undefined' ? SC_EMPRESA_ID : 0;
    const fmtCant = Fmt.numberFormat({ maximumFractionDigits: Fmt.decimalesStock() });
    const fmtCosto = Fmt.numberFormat({ minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const DT_IDIOMA = { url: window.APP_ASSETS_URL + 'js/datatables-i18n-2.1.8/es-ES.json' };
    const COL_PRIMERA_BOCA = 3; // Código, Producto, Categoría, [una por boca], Total, ...

    let bocas = [];
    let tablaStock = null;
    let tablaMov = null;

    // ------------------------------------------------------------------ utilidades
    function esc(v) {
        return $('<div>').text(v == null ? '' : String(v)).html();
    }

    function num(v) {
        return v === null || v === undefined || v === '' ? '' : fmtCant.format(parseFloat(v));
    }

    function cantidad(v) {
        const n = parseFloat(v || 0);
        const clase = n < 0 ? 'sc-neg' : (n === 0 ? 'sc-cero' : '');
        return `<span class="${clase}">${fmtCant.format(n)}</span>`;
    }

    function costo(v) {
        return v === null || v === undefined || v === '' ? '' : fmtCosto.format(parseFloat(v));
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

    function error(msg) {
        Swal.fire({ icon: 'error', title: 'No se pudo cargar', text: msg });
    }

    /** Filtros que valen para las dos grillas. */
    function filtros() {
        return {
            empresa_idx: empresaIdx,
            sucursal_id: $('#filtroSucursal').val() || 0,
            boca_id: $('#filtroBoca').val() || 0,
            producto_id: $('#filtroProducto').val() || 0,
            categoria_id: $('#filtroCategoria').val() || 0,
            marca_id: $('#filtroMarca').val() || 0,         // compatibilidad: solo existe en Gestión (módulo 2)
            modelo_id: $('#filtroModelo').val() || 0,
            submodelo_id: $('#filtroSubmodelo').val() || 0,
            desde: $('#filtroDesde').val(),
            hasta: $('#filtroHasta').val()
        };
    }

    /** Filtros de la grilla de stock (situación y búsqueda incluidas): los usan la planilla y el blanqueo. */
    function filtrosStock() {
        return Object.assign(filtros(), { filtro: $('#filtroStock').val(), buscar: $.trim($('#filtroBuscar').val() || '') });
    }

    function dataSrc(json) {
        if (!json.success) {
            error(json.message || 'No se pudo cargar la grilla');
            return [];
        }
        return json.data;
    }

    // ------------------------------------------------------------------ filtros
    /** Bocas de la sucursal elegida (todas si no hay sucursal). */
    function bocasVisibles() {
        const suc = parseInt($('#filtroSucursal').val() || 0, 10);
        return bocas.filter(b => suc === 0 || parseInt(b.sucursal_id, 10) === suc);
    }

    function llenarBocas() {
        const actual = $('#filtroBoca').val();
        const lista = bocasVisibles();
        $('#filtroBoca').html('<option value="0">Todas</option>' + lista
            .map(b => `<option value="${b.boca_id}">${esc(b.boca_nombre)}${$('#filtroSucursal').val() === '0' ? ' (' + esc(b.sucursal_nombre) + ')' : ''}</option>`).join(''));
        if (lista.some(b => String(b.boca_id) === String(actual))) $('#filtroBoca').val(actual);
    }

    function setRango(dias) {
        const hasta = new Date();
        const desde = new Date();
        desde.setDate(hasta.getDate() - (dias - 1));
        $('#filtroDesde').val(isoFecha(desde));
        $('#filtroHasta').val(isoFecha(hasta));
    }

    function inicializarProducto() {
        $('#filtroProducto').select2({
            theme: 'bootstrap-5',
            language: 'es',
            width: 'style',
            allowClear: true,
            placeholder: 'Todos los productos',
            minimumInputLength: 2,
            ajax: {
                url: AJAX_URL,
                dataType: 'json',
                delay: 300,
                data: p => ({ accion: 'buscar_productos', empresa_idx: empresaIdx, q: p.term || '' }),
                processResults: r => ({ results: (r && r.success) ? r.data : [] })
            }
        });
    }

    /** Elige un producto en el filtro (con su texto) sin buscarlo. */
    function elegirProducto(id, texto) {
        const sel = $('#filtroProducto');
        if (!sel.find(`option[value="${id}"]`).length) sel.append(new Option(texto, id, true, true));
        sel.val(String(id)).trigger('change');
    }

    // ------------------------------------------------------------------ totales
    function pintarTotalesStock(t) {
        if (!t) return;
        $('#kpiProductos').text(fmtCant.format(t.productos));
        $('#kpiNegativos').html(t.negativos > 0 ? `<span class="text-danger">${fmtCant.format(t.negativos)} con stock negativo</span>` : '');
        $('#kpiUnidades').text(fmtCant.format(t.unidades));
        $('#kpiValorizado').text(Fmt.moneda(t.valorizado, 0));
    }

    function pintarTotalesMov(t) {
        if (!t) return;
        $('#kpiMovimientos').text(fmtCant.format(t.movimientos));
        $('#kpiIngresos').text('+' + fmtCant.format(t.ingresos) + ' u.');
        $('#kpiEgresos').text('−' + fmtCant.format(t.egresos) + ' u.');
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
                className: 'text-end', visible: bocaVisible(b),
                render: (d, t, row) => {
                    const v = row.por_boca ? row.por_boca[b.boca_id] : undefined;
                    return v === undefined ? '<span class="sc-cero">—</span>' : cantidad(v);
                }
            });
        });
        columnas.push(
            {
                data: 'cantidad', name: 'cantidad', title: 'Total', className: 'text-end fw-bold',
                render: (d, t, row) => cantidad(d) + (row.unidad_abreviatura ? ` <small class="text-muted">${esc(row.unidad_abreviatura)}</small>` : '')
            },
            { data: 'valorizado', name: 'valorizado', title: 'Valorizado', className: 'text-end', render: d => Fmt.moneda(parseFloat(d || 0), 0) },
            { data: 'f_ultimo_movimiento', name: 'ultimo', title: 'Últ. movimiento', render: (d, t) => t === 'display' ? (d ? esc(Fmt.fechaHora(d)) : '') : (d || '') }
        );

        tablaStock = $('#tablaStock').DataTable({
            buttons: [],
            serverSide: true,
            processing: true,
            ajax: {
                url: AJAX_URL,
                type: 'GET',
                data: function (d) {
                    Object.assign(d, filtros());
                    d.accion = 'listar_stock';
                    d.filtro = $('#filtroStock').val();
                    d.search = { value: $.trim($('#filtroBuscar').val() || ''), regex: false };
                },
                dataSrc: function (json) {
                    pintarTotalesStock(json.totales);
                    return dataSrc(json);
                }
            },
            columns: columnas,
            order: [[1, 'asc']],
            pageLength: 25,
            searchDelay: 400,
            language: DT_IDIOMA,
            initComplete: function () { $('#tablaStock_filter').addClass('d-none'); }
        });
    }

    /** ¿Se ve la columna de esta boca? Solo las de la sucursal / boca elegida. */
    function bocaVisible(b) {
        const boca = parseInt($('#filtroBoca').val() || 0, 10);
        const id = parseInt(b.boca_id, 10);
        return boca > 0 ? id === boca : bocasVisibles().some(v => parseInt(v.boca_id, 10) === id);
    }

    /** Al cambiar sucursal o boca (la visibilidad inicial va en la definición de las columnas). */
    function ajustarColumnasBoca() {
        if (!tablaStock) return;
        bocas.forEach((b, i) => tablaStock.column(COL_PRIMERA_BOCA + i).visible(bocaVisible(b), false));
        tablaStock.columns.adjust();
    }

    // ------------------------------------------------------------------ libro de movimientos
    function inicializarTablaMovimientos() {
        tablaMov = $('#tablaMovimientos').DataTable({
            buttons: [],
            serverSide: true,
            processing: true,
            ajax: {
                url: AJAX_URL,
                type: 'GET',
                data: function (d) {
                    Object.assign(d, filtros());
                    d.accion = 'listar_movimientos';
                    d.direccion = $('#filtroDireccion').val();
                    d.comprobante_tipo_id = $('#filtroTipo').val() || 0;
                },
                dataSrc: function (json) {
                    pintarTotalesMov(json.totales);
                    return dataSrc(json);
                }
            },
            columns: [
                { data: 'f_movimiento', name: 'fecha', render: (d, t) => t === 'display' ? `<span class="text-nowrap">${esc(Fmt.fechaHora(d))}</span>` : d },
                { data: 'boca_nombre', name: 'boca', render: (d, t, row) => `${esc(d)}<br><small class="text-muted">${esc(row.sucursal_nombre)}</small>` },
                { data: 'producto_codigo', name: 'codigo', render: esc },
                { data: 'producto_nombre', name: 'producto', render: esc },
                { data: 'comprobante_tipo', name: 'comprobante', render: (d, t, row) => comprobante(row) + (row.observaciones ? `<br><small class="text-muted">${esc(row.observaciones)}</small>` : '') },
                { data: 'entidad_nombre', name: 'entidad', render: esc },
                {
                    data: 'cantidad', name: 'cantidad', className: 'text-end fw-bold text-nowrap',
                    render: (d, t, row) => row.tipo_movimiento === 'INGRESO'
                        ? `<span class="text-success">+${num(d)}</span>`
                        : `<span class="text-danger">−${num(d)}</span>`
                },
                { data: 'costo_unitario', orderable: false, className: 'text-end', render: costo },
                { data: 'usuario_nombre', orderable: false, className: 'ui-detalle', render: esc }
            ],
            order: [[0, 'desc']],
            pageLength: 25,
            searchDelay: 400,
            language: DT_IDIOMA
        });
    }

    // ------------------------------------------------------------------ eventos
    function recargarStock() { if (tablaStock) tablaStock.ajax.reload(); }
    // La grilla de movimientos se crea al abrir su solapa (oculta, DataTables no puede medir las columnas y falla);
    // mientras tanto los totales de arriba se piden aparte.
    function recargarMov() {
        if (tablaMov) { tablaMov.ajax.reload(); return; }
        $.getJSON(AJAX_URL, Object.assign(filtros(), {
            accion: 'listar_movimientos', draw: 0, start: 0, length: 1,
            direccion: $('#filtroDireccion').val(), comprobante_tipo_id: $('#filtroTipo').val() || 0
        })).done(r => { if (r && r.success) pintarTotalesMov(r.totales); });
    }
    $('#tabMovBtn').on('shown.bs.tab', function () {
        if (!tablaMov) inicializarTablaMovimientos();
        else tablaMov.columns.adjust();
    });
    function recargarTodo() { recargarStock(); recargarMov(); }

    $('#filtroSucursal').on('change', function () {
        llenarBocas();
        ajustarColumnasBoca();
        recargarTodo();
    });
    $('#filtroBoca').on('change', function () {
        ajustarColumnasBoca();
        recargarTodo();
    });
    $('#filtroProducto, #filtroCategoria').on('change', recargarTodo);

    $('.btn-rango').on('click', function () {
        $('.btn-rango').removeClass('active');
        $(this).addClass('active');
        setRango(parseInt($(this).data('dias'), 10));
        recargarMov();
    });
    $('#filtroDesde, #filtroHasta').on('change', function () {
        $('.btn-rango').removeClass('active');
        recargarMov();
    });

    $('#filtroStock').on('change', recargarStock);
    $('#filtroDireccion, #filtroTipo').on('change', recargarMov);
    $('#btnRecargar').on('click', recargarTodo);

    $('#btnLimpiar').on('click', function () {
        $('#filtroSucursal').val('0');
        llenarBocas();
        $('#filtroBoca, #filtroCategoria, #filtroTipo').val('0');
        $('#filtroDireccion').val('');
        $('#filtroStock').val('movidos');
        $('#filtroProducto').val(null).trigger('change.select2');
        $('#filtroBuscar').val('');
        if ($('#filtroMarca').length) {
            $('#filtroMarca').val('0');
            llenarCombo('#filtroModelo', [], '', '', 'Todos');
            llenarCombo('#filtroSubmodelo', [], '', '', 'Todos');
            $('#filtroModelo, #filtroSubmodelo').prop('disabled', true);
        }
        $('.btn-rango').removeClass('active').filter('[data-dias="30"]').addClass('active');
        setRango(30);
        ajustarColumnasBoca();
        recargarTodo();
    });

    // Tocar un producto: queda elegido en el filtro y se abren sus movimientos
    $('#tablaStock tbody').on('click', 'tr', function (e) {
        if ($(e.target).closest('.dtr-control').length) return;   // el ▸ del detalle responsive
        const row = tablaStock.row(this).data();
        if (!row) return;
        elegirProducto(row.producto_id, `${row.producto_codigo} — ${row.producto_nombre}`);
        bootstrap.Tab.getOrCreateInstance(document.getElementById('tabMovBtn')).show();
    });

    // ------------------------------------------------------------------ búsqueda de la grilla de stock
    let timerBuscar = null;
    $('#filtroBuscar').on('input search', function () {
        clearTimeout(timerBuscar);
        timerBuscar = setTimeout(recargarStock, 400);
    });

    // ------------------------------------------------------------------ compatibilidad (solo Gestión)
    function llenarCombo(sel, lista, id, nombre, vacio) {
        $(sel).html(`<option value="0">${vacio}</option>` + lista
            .map(x => `<option value="${x[id]}">${esc(x[nombre])}</option>`).join(''));
    }
    if ($('#filtroMarca').length) {
        $.getJSON(AJAX_URL, { accion: 'compat_marcas', empresa_idx: empresaIdx }).done(function (r) {
            if (r && r.success) llenarCombo('#filtroMarca', r.data, 'marca_id', 'marca_nombre', 'Todas');
        });
        $('#filtroMarca').on('change', function () {
            const marca = $(this).val() || '0';
            llenarCombo('#filtroModelo', [], '', '', 'Todos');
            llenarCombo('#filtroSubmodelo', [], '', '', 'Todos');
            $('#filtroModelo, #filtroSubmodelo').prop('disabled', true);
            recargarTodo();
            if (marca === '0') return;
            $.getJSON(AJAX_URL, { accion: 'compat_modelos', empresa_idx: empresaIdx, marca_id: marca }).done(function (r) {
                if (!r || !r.success) return;
                llenarCombo('#filtroModelo', r.data, 'modelo_id', 'modelo_nombre', 'Todos');
                $('#filtroModelo').prop('disabled', false);
            });
        });
        $('#filtroModelo').on('change', function () {
            const modelo = $(this).val() || '0';
            llenarCombo('#filtroSubmodelo', [], '', '', 'Todos');
            $('#filtroSubmodelo').prop('disabled', true);
            recargarTodo();
            if (modelo === '0') return;
            $.getJSON(AJAX_URL, { accion: 'compat_submodelos', empresa_idx: empresaIdx, modelo_id: modelo }).done(function (r) {
                if (!r || !r.success || !r.data.length) return;
                llenarCombo('#filtroSubmodelo', r.data, 'submodelo_id', 'submodelo_nombre', 'Todos');
                $('#filtroSubmodelo').prop('disabled', false);
            });
        });
        $('#filtroSubmodelo').on('change', recargarTodo);
    }

    // ------------------------------------------------------------------ planilla de conteo
    /** Texto de los filtros aplicados, para el encabezado de la planilla. */
    function resumenFiltros() {
        const partes = [];
        const txt = sel => $(sel + ' option:selected').text();
        if ($('#filtroSucursal').val() !== '0') partes.push('Sucursal: ' + txt('#filtroSucursal'));
        if ($('#filtroBoca').val() !== '0') partes.push('Boca: ' + txt('#filtroBoca'));
        if ($('#filtroProducto').val()) partes.push('Producto: ' + txt('#filtroProducto'));
        if ($('#filtroCategoria').val() !== '0') partes.push('Categoría: ' + txt('#filtroCategoria'));
        ['#filtroMarca', '#filtroModelo', '#filtroSubmodelo'].forEach(function (s, i) {
            if ($(s).length && ($(s).val() || '0') !== '0') partes.push(['Marca', 'Modelo', 'Submodelo'][i] + ': ' + txt(s));
        });
        if ($.trim($('#filtroBuscar').val() || '')) partes.push('Búsqueda: "' + $.trim($('#filtroBuscar').val()) + '"');
        partes.push('Situación: ' + txt('#filtroStock'));
        return partes.join(' · ');
    }

    /**
     * Una tabla por boca visible con los productos que tienen stock registrado en ella. Con la situación
     * "Todos los productos activos" y una sola boca elegida, entran también los que nunca tuvieron stock ahí.
     */
    function htmlPlanilla(filas, ciega, truncado, total) {
        const bocaSel = parseInt($('#filtroBoca').val() || 0, 10);
        const todos = $('#filtroStock').val() === 'todos' && bocaSel > 0;
        const ahora = new Date();
        let cuerpo = '';
        let hojas = 0;
        bocasVisibles().filter(b => bocaSel === 0 || parseInt(b.boca_id, 10) === bocaSel).forEach(function (b) {
            const lista = filas.filter(f => todos || (f.por_boca && f.por_boca[b.boca_id] !== undefined));
            if (!lista.length) return;
            hojas++;
            const filasHtml = lista.map(function (f, i) {
                const v = f.por_boca && f.por_boca[b.boca_id] !== undefined ? f.por_boca[b.boca_id] : 0;
                return `<tr><td class="n">${i + 1}</td><td>${esc(f.producto_codigo)}</td><td>${esc(f.producto_nombre)}</td>`
                    + `<td>${esc(f.unidad_abreviatura || '')}</td>`
                    + (ciega ? '' : `<td class="num">${esc(Fmt.cantidad(v))}</td>`)
                    + '<td class="anotar"></td><td class="anotar obs"></td></tr>';
            }).join('');
            cuerpo += `<section${hojas > 1 ? ' class="salto"' : ''}>
                <h2>${esc(b.boca_nombre)} <small>${esc(b.sucursal_nombre || '')}</small></h2>
                <table><thead><tr><th class="n">#</th><th>Código</th><th>Producto</th><th>Unidad</th>
                ${ciega ? '' : '<th class="num">Stock sistema</th>'}<th>Contado</th><th>Observaciones</th></tr></thead>
                <tbody>${filasHtml}</tbody></table>
                <p class="firmas"><span>Contó: ____________________</span><span>Controló: ____________________</span><span>Fecha: ____/____/________</span></p>
            </section>`;
        });
        if (!hojas) cuerpo = '<p>No hay productos con stock registrado en las bocas de lo filtrado.</p>';
        const aviso = truncado ? `<p class="aviso">La planilla tiene los primeros ${filas.length} de ${total} productos: acotá los filtros para el resto.</p>` : '';
        return `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Planilla de conteo de stock</title>
            <style>
                body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #000; margin: 16px; }
                header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #000; margin-bottom: 8px; }
                h1 { font-size: 16px; margin: 0; } h2 { font-size: 13px; margin: 14px 0 4px; } h2 small { font-weight: normal; color: #555; }
                .filtros { color: #333; margin: 4px 0 8px; } .aviso { color: #b00; font-weight: bold; }
                table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #999; padding: 4px 5px; text-align: left; }
                th { background: #eee; } .num { text-align: right; white-space: nowrap; } .n { width: 28px; text-align: right; color: #555; }
                td.anotar { width: 90px; } td.obs { width: 160px; } tr { page-break-inside: avoid; }
                .firmas { display: flex; justify-content: space-between; margin-top: 18px; }
                .acciones { margin-bottom: 10px; } .salto { page-break-before: always; }
                @media print { .acciones { display: none; } body { margin: 0; } }
            </style></head><body>
            <div class="acciones"><button onclick="window.print()">Imprimir</button></div>
            <header><div><h1>Planilla de conteo de stock</h1><div>${esc(typeof SC_EMPRESA_NOMBRE !== 'undefined' ? SC_EMPRESA_NOMBRE : '')}</div></div>
            <div>${esc(Fmt.fechaHora(ahora))}</div></header>
            <div class="filtros">${esc(resumenFiltros())}${ciega ? ' · Conteo a ciegas (sin stock del sistema)' : ''}</div>
            ${aviso}${cuerpo}</body></html>`;
    }

    $(document).on('click', '.btn-planilla', function (e) {
        e.preventDefault();
        const ciega = String($(this).data('ciega')) === '1';
        // La ventana se abre en el clic (si se abre después de la respuesta, el navegador la bloquea)
        const win = window.open('', '_blank');
        if (!win) { error('El navegador bloqueó la ventana de la planilla: permití las ventanas emergentes de este sitio.'); return; }
        win.document.write('<p style="font-family: Arial, sans-serif">Armando la planilla…</p>');
        $.getJSON(AJAX_URL, Object.assign(filtrosStock(), { accion: 'conteo_listado' })).done(function (r) {
            if (!r || !r.success) { win.close(); error((r && r.message) || 'No se pudo armar la planilla'); return; }
            win.document.open();
            win.document.write(htmlPlanilla(r.data.filas || [], ciega, r.data.truncado, r.data.total));
            win.document.close();
        }).fail(function (xhr) {
            win.close();
            error((xhr.responseJSON && xhr.responseJSON.message) || 'Error de comunicación con el servidor');
        });
    });

    // ------------------------------------------------------------------ blanqueo de stock negativo
    $('#btnBlanqueo').on('click', function () {
        const datos = filtrosStock();
        $.getJSON(AJAX_URL, Object.assign({}, datos, { accion: 'blanqueo_previa' })).done(function (r) {
            if (!r || !r.success) { error((r && r.message) || 'No se pudo calcular el blanqueo'); return; }
            const bocasNeg = r.data.bocas || [];
            if (!bocasNeg.length) {
                UiSimple.aviso('No hay productos con stock negativo en lo filtrado.');
                return;
            }
            if (!r.data.puede_agregar) {
                error('Tu perfil no puede crear Ajustes de Stock.');
                return;
            }
            const filasHtml = bocasNeg.map(b => `<tr><td class="text-start">${esc(b.boca_nombre)} <small class="text-muted">${esc(b.sucursal_nombre || '')}</small></td>`
                + `<td class="text-end">${fmtCant.format(b.productos)}</td><td class="text-end">+${esc(Fmt.cantidad(b.unidades))}</td></tr>`).join('');
            const totalProd = bocasNeg.reduce((a, b) => a + b.productos, 0);
            const conf = r.data.puede_confirmar
                ? `<div class="form-check text-start mt-3"><input class="form-check-input" type="checkbox" id="blqConfirmar" checked>
                   <label class="form-check-label" for="blqConfirmar">Confirmar los ajustes ahora (el stock queda en 0). Sin tildar quedan en Borrador para revisarlos en Ajustes de Stock.</label></div>`
                : '<p class="small text-muted mt-3 mb-0">Los ajustes quedan en Borrador: tu perfil no puede confirmarlos.</p>';
            Swal.fire({
                icon: 'warning',
                title: 'Blanquear stock negativo',
                html: `<p class="mb-2">Se genera un Ajuste de Stock por boca que lleva a 0 los <strong>${fmtCant.format(totalProd)}</strong> productos en negativo de lo filtrado.</p>
                       <p class="small text-muted">${esc(resumenFiltros().replace(/ · Situación: [^·]*$/, ''))}</p>
                       <table class="table table-sm mb-0"><thead><tr><th class="text-start">Boca</th><th class="text-end">Productos</th><th class="text-end">Unidades</th></tr></thead>
                       <tbody>${filasHtml}</tbody></table>${conf}`,
                showCancelButton: true,
                confirmButtonText: 'Generar ajustes',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                width: 640,
                preConfirm: () => ({ confirmar: r.data.puede_confirmar && $('#blqConfirmar').is(':checked') ? '1' : '0' })
            }).then(function (res) {
                if (!res.isConfirmed || !res.value) return;
                $.post(AJAX_URL, Object.assign({}, datos, { accion: 'blanqueo_generar', confirmar: res.value.confirmar }), null, 'json')
                    .done(function (g) {
                        const items = (g && g.data) || [];
                        const lineas = items.map(i => `<li class="text-start">${esc(i.boca_nombre)}: `
                            + (i.stock_ajuste_id ? `ajuste N° ${i.stock_ajuste_id} (${i.productos} productos) ${i.confirmado ? 'confirmado' : 'en Borrador'}` : '')
                            + (i.error ? ` <span class="text-danger">${esc(i.error)}</span>` : '') + '</li>').join('');
                        Swal.fire({
                            icon: g && g.success && !items.some(i => i.error) ? 'success' : 'warning',
                            title: (g && g.message) || 'Resultado del blanqueo',
                            html: `<ul class="mb-0">${lineas}</ul>`
                        });
                        recargarTodo();
                    })
                    .fail(function (xhr) {
                        error((xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Error de comunicación con el servidor');
                    });
            });
        }).fail(function (xhr) {
            error((xhr.responseJSON && xhr.responseJSON.message) || 'Error de comunicación con el servidor');
        });
    });

    // ------------------------------------------------------------------ arranque
    setRango(30);
    inicializarProducto();
    $.getJSON(AJAX_URL, { accion: 'obtener_filtros', empresa_idx: empresaIdx }).done(function (r) {
        if (!r || !r.success) { error((r && r.message) || 'No se pudieron cargar los filtros'); return; }
        bocas = r.data.bocas || [];
        const sucursales = [];
        bocas.forEach(b => {
            if (!sucursales.some(s => s.id === String(b.sucursal_id))) sucursales.push({ id: String(b.sucursal_id), nombre: b.sucursal_nombre });
        });
        $('#filtroSucursal').html('<option value="0">Todas</option>' + sucursales
            .map(s => `<option value="${s.id}">${esc(s.nombre)}</option>`).join(''));
        llenarBocas();
        $('#filtroCategoria').html('<option value="0">Todas</option>' + (r.data.categorias || [])
            .map(c => `<option value="${c.producto_categoria_id}">${esc(c.producto_categoria_nombre)}</option>`).join(''));
        $('#filtroTipo').html('<option value="0">Todos los comprobantes</option>' + (r.data.tipos || [])
            .map(t => `<option value="${t.comprobante_tipo_id}">${esc(t.comprobante_tipo)} (${esc(t.codigo)})</option>`).join(''));

        inicializarTablaStock();
        recargarMov();   // solo los totales: la grilla se arma al abrir la solapa
    }).fail(function (xhr) {
        error((xhr.responseJSON && xhr.responseJSON.message) || 'Error de comunicación con el servidor');
    });
});
