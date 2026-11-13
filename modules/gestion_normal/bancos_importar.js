$(document).ready(function () {
    const AJAX = 'bancos_importar_ajax.php';
    const { esc, num, fecha } = Bco;
    const ESTADOS = { PENDIENTE: 'Sin concepto', CLASIFICADA: 'Clasificada', CONCILIADA: 'Conciliada', IGNORADA: 'Ignorada' };
    const ESTADO_CLASE = { PENDIENTE: 'bg-warning text-dark', CLASIFICADA: 'bg-info text-dark', CONCILIADA: 'bg-success', IGNORADA: 'bg-secondary' };
    let tabla;
    let conceptos = [];               // todos los de la empresa (el filtro incluye los inactivos)
    let puedeClasificar = false;      // función "clasificar" de Conciliación Bancaria
    let puedeAltaConcepto = false;    // función "agregar" de Conceptos Bancarios

    tabla = $('#tablaLotes').DataTable({
        ajax: { url: AJAX, data: (d) => Object.assign(d, { accion: 'listar', empresa_idx: window.EMPRESA_ID, cuenta_id: $('#filtroCuenta').val() || 0 }), dataSrc: Bco.datos },
        order: [], pageLength: 25,
        columns: [
            { data: 'importacion_id', className: 'text-center' },
            { data: 'tesoreria_cuenta', render: (d) => esc(d) },
            { data: 'f_desde', className: 'text-nowrap', render: (d, t, r) => `${fecha(d)} al ${fecha(r.f_hasta)}` },
            { data: 'cantidad_movimientos', className: 'text-center' },
            { data: 'sin_concepto', className: 'text-center', render: (d) => Number(d) ? `<span class="badge bg-warning text-dark">${d}</span>` : '0' },
            { data: 'conciliadas', className: 'text-center', render: (d) => Number(d || 0) },
            { data: 'saldo_final', className: 'text-end ui-detalle', render: (d) => Bco.importe(d) },
            { data: 'archivo_nombre', className: 'ui-detalle small', render: (d) => esc(d) },
            { data: 'formato', className: 'ui-detalle small', render: (d) => esc(d) },
            { data: 'estado_registro', className: 'text-center', render: (d, t, r) => Bco.estado(r) },
            { data: 'botones', orderable: false, className: 'text-center text-nowrap', render: (d, t, r) => Bco.botonesFila(d, r.importacion_id, `Lote ${r.importacion_id} - ${r.archivo_nombre}`) }
        ],
        language: Bco.idioma()
    });

    $.getJSON(AJAX, { accion: 'opciones', empresa_idx: window.EMPRESA_ID }, (res) => {
        const d = res.data || {};
        const texto = (c) => c.tesoreria_cuenta + (c.numero ? ` (${c.numero})` : '');
        Bco.opcionesSelect($('#cuenta_id'), d.cuentas || [], 'tesoreria_cuenta_id', texto, 'Elegí la cuenta');
        Bco.opcionesSelect($('#filtroCuenta'), d.cuentas || [], 'tesoreria_cuenta_id', texto, 'Todas las cuentas');
        if (!d.puede_importar) $('#cardImportar').hide();
        Bco.opcionesSelect($('#movCuenta'), d.cuentas || [], 'tesoreria_cuenta_id', texto, 'Todas las cuentas');
        Bco.opcionesSelect($('#resCuenta'), d.cuentas || [], 'tesoreria_cuenta_id', texto, 'Todas las cuentas');
        conceptos = d.conceptos || [];
        puedeClasificar = !!d.puede_clasificar;
        puedeAltaConcepto = !!d.alta_concepto;
        $('#btnNuevoConcepto').toggleClass('d-none', !puedeAltaConcepto);
        BcoConceptoRapido.opciones(d.productos, d.jurisdicciones);
        filtroConceptos();
        if (tablaMov) tablaMov.rows().invalidate().draw(false);
    });

    function filtroConceptos() {
        $('#movConcepto, #resConcepto').each(function () {
            const actual = $(this).val();
            Bco.opcionesSelect($(this), [{ concepto_id: -1, concepto: 'Sin concepto' }].concat(conceptos), 'concepto_id', 'concepto', 'Todos los conceptos');
            $(this).val(actual || '');
        });
    }

    // Solapas Movimientos (por defecto, desde el mes anterior) y Resumen (los últimos 12 meses): mismos filtros, en el servidor
    let tablaMov = null;
    let tablaRes = null;
    const hoy = new Date();
    const iso = (f) => `${f.getFullYear()}-${String(f.getMonth() + 1).padStart(2, '0')}-${String(f.getDate()).padStart(2, '0')}`;
    $('#movDesde').val(iso(new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1)));
    $('#resDesde').val(iso(new Date(hoy.getFullYear(), hoy.getMonth() - 11, 1)));
    $('#movHasta, #resHasta').val(iso(hoy));

    function filtros(px, accion) {
        return {
            accion, empresa_idx: window.EMPRESA_ID, cuenta_id: $(`#${px}Cuenta`).val() || 0,
            desde: $(`#${px}Desde`).val(), hasta: $(`#${px}Hasta`).val(), concepto_id: $(`#${px}Concepto`).val() || 0,
            estado: $(`#${px}Estado`).val(), sentido: $(`#${px}Sentido`).val(), texto: $(`#${px}Texto`).val()
        };
    }

    // Saldo inicial y final del banco (null = desconocido: las líneas no traen saldo); débitos y créditos, del filtro
    function mostrarTotales(px, t) {
        t = t || {};
        const saldo = (v) => v == null ? '—' : num(v);
        $(`#${px}SaldoInicial`).text(saldo(t.saldo_inicial)).toggleClass('text-danger', Number(t.saldo_inicial) < 0);
        $(`#${px}Debitos`).text(num(t.debitos || 0));
        $(`#${px}Creditos`).text(num(t.creditos || 0));
        $(`#${px}SaldoFinal`).text(saldo(t.saldo_final)).toggleClass('text-danger', Number(t.saldo_final) < 0);
        $(`#${px}Cantidad`).text(Fmt.numero(Number(t.cantidad || 0)));
        $(`#${px}SinConcepto`).text(t.sin_concepto ? ` · ${t.sin_concepto} sin concepto` : '');
        $(`#${px}Parcial`).toggleClass('d-none', !t.parcial);
    }

    const editable = (r) => r.estado === 'PENDIENTE' || r.estado === 'CLASIFICADA';

    // Concepto de la línea: lista desplegable (con "clasificar", si no está conciliada ni ignorada); se guarda al cambiarla
    function conceptoCelda(d, t, r) {
        if (t !== 'display') return d || '';
        if (!puedeClasificar || !editable(r)) return d ? esc(d) : '<span class="badge bg-warning text-dark">Sin concepto</span>';
        const lista = conceptos.filter(k => Number(k.tabla_estado_registro_id) === 1 || String(k.concepto_id) === String(r.concepto_id));
        if (r.concepto_id && !lista.some(k => String(k.concepto_id) === String(r.concepto_id))) lista.push({ concepto_id: r.concepto_id, concepto: d });
        const opciones = '<option value="">Sin concepto</option>' + lista.map(k => {
            const inactivo = Number(k.tabla_estado_registro_id) === 1 || k.tabla_estado_registro_id === undefined ? '' : ' (inactivo)';
            return `<option value="${k.concepto_id}" ${String(k.concepto_id) === String(r.concepto_id || '') ? 'selected' : ''}>${esc(k.concepto)}${inactivo}${k.naturaleza === 'GASTO_BANCARIO' ? ' (gasto)' : ''}</option>`;
        }).join('') + (puedeAltaConcepto ? '<option value="__nuevo">+ Nuevo concepto…</option>' : '');
        const origen = r.concepto_origen === 'MANUAL' ? 'Asignado a mano' : (r.concepto_id ? 'Asignado por una regla' : 'Elegí el concepto');
        return `<select class="form-select form-select-sm mov-concepto ${r.concepto_id ? '' : 'border-warning'}" style="min-width: 11rem"
                    data-id="${r.extracto_id}" data-previo="${r.concepto_id || ''}" title="${origen}" aria-label="Concepto">${opciones}</select>`;
    }

    // Estado: ignorar una línea pendiente o clasificada, o restaurar una ignorada (las conciliadas se deshacen en Conciliación Bancaria)
    function accionesCelda(d, t, r) {
        if (!puedeClasificar) return '';
        if (editable(r)) return `<button type="button" class="btn btn-sm btn-outline-secondary mov-estado" data-id="${r.extracto_id}" data-op="ignorar" title="Ignorar: no se concilia"><i class="bi bi-eye-slash"></i></button>`;
        if (r.estado === 'IGNORADA') return `<button type="button" class="btn btn-sm btn-outline-primary mov-estado" data-id="${r.extracto_id}" data-op="restaurar" title="Restaurar"><i class="bi bi-arrow-counterclockwise"></i></button>`;
        return '';
    }

    const recargarMov = () => tablaMov && tablaMov.ajax.reload(null, false);

    function clasificar(pedido) {
        return Bco.pedir(AJAX, Object.assign({ accion: 'clasificar' }, pedido));
    }

    $(document).on('change', '.mov-concepto', function () {
        const $s = $(this);
        const v = $s.val();
        if (v === '__nuevo') {
            $s.val($s.data('previo'));
            const r = tablaMov.rows().data().toArray().find(x => String(x.extracto_id) === String($s.data('id')));
            return abrirConcepto(r ? [r] : []);
        }
        $s.prop('disabled', true);
        clasificar(v ? { operacion: 'asignar', concepto_id: v, extracto_ids: [$s.data('id')] } : { operacion: 'quitar', extracto_ids: [$s.data('id')] })
            .then((res) => {
                if ((res.data || {}).mismas) Swal.fire({ icon: 'success', title: 'Concepto guardado', text: res.message });
                else Bco.ok(v ? 'Concepto guardado' : 'Concepto quitado');
                recargarMov();
            }).catch(() => $s.val($s.data('previo')).prop('disabled', false));
    });

    $(document).on('click', '.mov-estado', function () {
        const op = $(this).data('op');
        const btn = $(this).prop('disabled', true);
        clasificar({ operacion: op, extracto_ids: [$(this).data('id')] })
            .then(() => { Bco.ok(op === 'ignorar' ? 'Línea ignorada' : 'Línea restaurada'); recargarMov(); })
            .catch(() => btn.prop('disabled', false));
    });

    function abrirConcepto(lineas) {
        BcoConceptoRapido.abrir({
            ajax: AJAX, lineas: puedeClasificar ? lineas : [],
            alGuardar: (k) => {
                conceptos.push(k);
                conceptos.sort((a, b) => (Number(a.orden) - Number(b.orden)) || String(a.concepto).localeCompare(String(b.concepto)));
                filtroConceptos();
                recargarMov();
            }
        });
    }
    $('#btnNuevoConcepto').on('click', () => abrirConcepto([]));

    function iniciarMovimientos() {
        if (tablaMov) return tablaMov.columns.adjust();
        tablaMov = $('#tablaMovimientos').DataTable({
            // Por Bco.pedir: si el servidor falla se ve su mensaje (no el aviso genérico "Ajax error" de DataTables)
            ajax: (d, callback) => {
                Bco.pedir(AJAX, filtros('mov', 'movimientos'), { metodo: 'GET' }).then((res) => {
                    mostrarTotales('mov', res.data.totales);
                    $('#movLimitado').text(res.data.limitado ? `Se muestran los ${Fmt.numero(Number(res.data.maximo))} más recientes: acotá el filtro para ver el resto (los totales son de todo el filtro).` : '');
                    callback({ data: res.data.lineas || [] });
                }).catch(() => { mostrarTotales('mov'); $('#movLimitado').text(''); callback({ data: [] }); });
            },
            order: [[0, 'desc']], pageLength: 50,
            dom: '<"d-flex flex-wrap justify-content-between gap-2 mb-2"Bf>rt<"d-flex flex-wrap justify-content-between mt-2"ip>',
            buttons: [{ extend: 'excelHtml5', text: '<i class="fas fa-file-excel"></i> Excel', className: 'btn btn-success btn-sm', title: 'Movimientos bancarios',
                        exportOptions: { orthogonal: 'export', columns: ':not(.no-export)' } }],
            columns: [
                { data: 'f_movimiento', className: 'text-nowrap', render: (d, t) => t === 'display' || t === 'filter' ? fecha(d) : d },
                { data: 'tesoreria_cuenta', render: (d) => esc(d) },
                { data: 'descripcion', render: (d) => esc(d) },
                { data: 'referencia', className: 'small', render: (d) => esc(d || '') },
                { data: 'concepto', render: conceptoCelda },
                { data: 'importe', className: 'text-end', render: (d, t) => t === 'display' ? Bco.importe(d) : Number(d) },
                { data: 'saldo', className: 'text-end', render: (d, t) => t === 'display' ? `<span class="font-monospace">${num(d)}</span>` : (d == null ? '' : Number(d)) },
                { data: 'estado', className: 'text-center', render: (d, t) => t === 'display' ? `<span class="badge ${ESTADO_CLASE[d] || 'bg-secondary'}">${esc(ESTADOS[d] || d)}</span>` : (ESTADOS[d] || d) },
                { data: null, orderable: false, className: 'text-center text-nowrap no-export', render: accionesCelda },
                { data: 'importacion_id', className: 'text-center ui-detalle' },
                { data: 'nro_linea', className: 'text-center ui-detalle' },
                { data: 'f_valor', className: 'ui-detalle', render: (d, t) => t === 'display' || t === 'filter' ? fecha(d) : (d || '') },
                { data: 'concepto_origen', className: 'ui-detalle', render: (d) => d === 'REGLA' ? 'Regla' : (d === 'MANUAL' ? 'Manual' : '') }
            ],
            language: Bco.idioma()
        });
    }

    // Resumen: una fila por mes y cuenta; los totales suman los saldos de las cuentas al principio y al final del rango
    function totalesResumen(meses, parcial) {
        const t = { debitos: 0, creditos: 0, cantidad: 0, sin_concepto: 0, saldo_inicial: null, saldo_final: null, parcial };
        const porCuenta = {};
        meses.forEach((m) => {
            t.debitos += Number(m.debitos); t.creditos += Number(m.creditos);
            t.cantidad += Number(m.cantidad); t.sin_concepto += Number(m.sin_concepto);
            const c = porCuenta[m.tesoreria_cuenta_id] = porCuenta[m.tesoreria_cuenta_id] || { inicial: m.saldo_inicial, final: null };
            c.final = m.saldo_final;
        });
        const cuentas = Object.values(porCuenta);
        if (cuentas.length && cuentas.every(c => c.inicial != null)) t.saldo_inicial = cuentas.reduce((a, c) => a + Number(c.inicial), 0);
        if (cuentas.length && cuentas.every(c => c.final != null)) t.saldo_final = cuentas.reduce((a, c) => a + Number(c.final), 0);
        return t;
    }

    // "2026-10" -> "octubre de 2026" (para ordenar y exportar queda AAAA-MM)
    function nombreMes(d, t) {
        if (t !== 'display' && t !== 'filter') return d;
        const [a, m] = String(d).split('-');
        const texto = new Date(Number(a), Number(m) - 1, 1).toLocaleDateString('es', { month: 'long', year: 'numeric' });
        return texto.charAt(0).toUpperCase() + texto.slice(1);
    }

    function iniciarResumen() {
        if (tablaRes) return tablaRes.columns.adjust();
        const saldo = (d, t) => t === 'display' ? (d == null ? '—' : Bco.importe(d)) : (d == null ? '' : Number(d));
        tablaRes = $('#tablaResumen').DataTable({
            ajax: (d, callback) => {
                Bco.pedir(AJAX, filtros('res', 'resumen'), { metodo: 'GET' }).then((res) => {
                    const meses = res.data.meses || [];
                    mostrarTotales('res', totalesResumen(meses, res.data.parcial));
                    callback({ data: meses });
                }).catch(() => { mostrarTotales('res'); callback({ data: [] }); });
            },
            order: [[0, 'asc'], [1, 'asc']], pageLength: 50,
            dom: '<"d-flex flex-wrap justify-content-between gap-2 mb-2"Bf>rt<"d-flex flex-wrap justify-content-between mt-2"ip>',
            buttons: [{ extend: 'excelHtml5', text: '<i class="fas fa-file-excel"></i> Excel', className: 'btn btn-success btn-sm', title: 'Resumen mensual del extracto',
                        exportOptions: { orthogonal: 'export' } }],
            columns: [
                { data: 'mes', className: 'text-nowrap', render: nombreMes },
                { data: 'tesoreria_cuenta', render: (d) => esc(d) },
                { data: 'saldo_inicial', className: 'text-end', render: saldo },
                { data: 'debitos', className: 'text-end', render: (d, t) => t === 'display' ? Bco.importe(d) : Number(d) },
                { data: 'creditos', className: 'text-end', render: (d, t) => t === 'display' ? Bco.importe(d) : Number(d) },
                { data: 'saldo_final', className: 'text-end', render: saldo },
                { data: 'cantidad', className: 'text-center' },
                { data: 'sin_concepto', className: 'text-center ui-detalle', render: (d, t) => t === 'display' && Number(d) ? `<span class="badge bg-warning text-dark">${d}</span>` : Number(d) }
            ],
            language: Bco.idioma()
        });
    }

    $('#btnTabMovimientos').on('shown.bs.tab', iniciarMovimientos);
    $('#btnTabResumen').on('shown.bs.tab', iniciarResumen);
    $('#movBuscar').on('click', () => { if (tablaMov) tablaMov.ajax.reload(); else iniciarMovimientos(); });
    $('#resBuscar').on('click', () => { if (tablaRes) tablaRes.ajax.reload(); else iniciarResumen(); });
    $('#movCuenta, #movConcepto, #movEstado, #movSentido, #movDesde, #movHasta').on('change', () => tablaMov && tablaMov.ajax.reload());
    $('#resCuenta, #resConcepto, #resEstado, #resSentido, #resDesde, #resHasta').on('change', () => tablaRes && tablaRes.ajax.reload());
    $('#movTexto, #resTexto').on('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); $(this).closest('.ui-filtros').find('.btn-primary').click(); }
    });

    $('#btnRecargar').on('click', () => tabla.ajax.reload(null, false));
    $('#filtroCuenta').on('change', () => tabla.ajax.reload());

    $('#cuenta_id, #archivo').on('change', () => $('#previa').empty());

    function datosFormulario(accion) {
        const fd = new FormData();
        fd.append('accion', accion);
        fd.append('cuenta_id', $('#cuenta_id').val());
        fd.append('formato_id', 0);
        fd.append('archivo', $('#archivo')[0].files[0]);
        return fd;
    }

    $('#formImportar').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) { $(this).addClass('was-validated'); return; }
        const btn = $('#btnPrevisualizar').prop('disabled', true);
        $('#previa').html('<div class="text-muted"><span class="spinner-border spinner-border-sm me-1"></span>Analizando el extracto con IA…</div>');
        Bco.pedir(AJAX, datosFormulario('previsualizar'), { silencioso: true })
            .then((res) => mostrarPrevia(res.data))
            .catch((r) => $('#previa').html(Bco.listaAvisos([Bco.mensajeDe(r)], [])))
            .finally(() => btn.prop('disabled', false));
    });

    function mostrarPrevia(x) {
        const c = x.control || {};
        const okSaldos = !c.pares || c.pares === c.pares_ok;
        let h = `<div class="row g-2 mb-3">
            <div class="col-6 col-md-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">Movimientos nuevos</div>
                <div class="ui-kpi-valor">${x.cantidad_nuevas}</div><div class="ui-kpi-sub">${fecha(x.f_desde)} al ${fecha(x.f_hasta)}${x.duplicadas ? ` · ${x.duplicadas} ya importados` : ''}</div></div></div>
            <div class="col-6 col-md-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">Saldo inicial → final</div>
                <div class="ui-kpi-valor fs-6">${num(x.saldo_inicial)} → ${num(x.saldo_final)}</div><div class="ui-kpi-sub">Débitos ${num(x.debitos)} · Créditos ${num(x.creditos)}</div></div></div>
            <div class="col-6 col-md-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">Cadena de saldos</div>
                <div class="ui-kpi-valor ${okSaldos ? 'text-success' : 'text-danger'}">${c.pares ? `${c.pares_ok} / ${c.pares}` : '—'}</div><div class="ui-kpi-sub">${okSaldos ? 'No falta ningún movimiento' : 'Falta o sobra algún movimiento'}</div></div></div>
            <div class="col-6 col-md-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">Sin concepto</div>
                <div class="ui-kpi-valor ${x.sin_concepto ? 'text-warning' : ''}">${x.sin_concepto}</div><div class="ui-kpi-sub">Cuenta del archivo: ${esc(x.cuenta_archivo || '—')}</div></div></div>
        </div>`;
        h += Bco.listaAvisos(x.bloqueos, x.avisos);
        h += `<div class="row g-3"><div class="col-lg-4"><table class="table table-sm small"><thead><tr><th>Concepto</th><th class="text-center">Cant.</th><th class="text-end">Importe</th></tr></thead><tbody>` +
            (x.clasificacion || []).map(k => `<tr class="${k.naturaleza ? '' : 'table-warning'}"><td>${esc(k.concepto)}</td><td class="text-center">${k.cantidad}</td><td class="text-end">${Bco.importe(k.importe)}</td></tr>`).join('') +
            `</tbody></table></div><div class="col-lg-8"><div class="table-responsive" style="max-height: 22rem"><table class="table table-sm small mb-0"><thead class="sticky-top bg-white"><tr>
              <th>Fecha</th><th>Descripción</th><th>Concepto</th><th class="text-end">Importe</th><th class="text-end">Saldo</th></tr></thead><tbody>` +
            (x.nuevas || []).map(m => `<tr><td class="text-nowrap">${fecha(m.f_movimiento)}</td><td>${esc(m.descripcion)}</td>
                <td>${m.concepto ? esc(m.concepto) : '<span class="badge bg-warning text-dark">Sin concepto</span>'}</td>
                <td class="text-end">${Bco.importe(m.importe)}</td><td class="text-end font-monospace">${num(m.saldo)}</td></tr>`).join('') +
            `</tbody></table></div></div></div>`;
        if (!(x.bloqueos || []).length) {
            h += `<div class="text-end mt-3"><button type="button" class="btn btn-primary" id="btnConfirmar"><i class="bi bi-check2 me-1"></i>Importar ${x.cantidad_nuevas} movimientos</button></div>`;
        }
        $('#previa').html(h);
    }

    $(document).on('click', '#btnConfirmar', function () {
        const btn = $(this).prop('disabled', true);
        Bco.pedir(AJAX, datosFormulario('agregar')).then((res) => {
            Swal.fire({ icon: 'success', title: 'Extracto importado', text: res.message + ((res.data.avisos || []).length ? ' ' + res.data.avisos.join(' ') : '') });
            $('#previa').empty();
            $('#archivo').val('');
            tabla.ajax.reload();
        }).catch(() => btn.prop('disabled', false));
    });

    $(document).on('click', '.btn-accion', function () {
        const id = $(this).data('id');
        const accion = $(this).data('accion');
        if (accion === 'visualizar') {
            Bco.pedir(AJAX, { accion: 'obtener', importacion_id: id }, { metodo: 'GET' }).then((res) => {
                $('#modalLoteLabel').text('Lote ' + id);
                $('#detalleLote').html(`<table class="table table-sm small"><thead><tr><th>Fila</th><th>Fecha</th><th>Descripción</th><th>Referencia</th>
                    <th>Concepto</th><th class="text-end">Importe</th><th class="text-end">Saldo</th><th>Estado</th></tr></thead><tbody>` +
                    res.data.map(m => `<tr><td>${m.nro_linea}</td><td class="text-nowrap">${fecha(m.f_movimiento)}</td><td>${esc(m.descripcion)}</td><td>${esc(m.referencia || '')}</td>
                        <td>${esc(m.concepto || '')}</td><td class="text-end">${Bco.importe(m.importe)}</td><td class="text-end font-monospace">${num(m.saldo)}</td>
                        <td>${ESTADOS[m.estado] || m.estado}</td></tr>`).join('') + '</tbody></table>');
                $('#modalLote').modal('show');
            }).catch(() => {});
            return;
        }
        Bco.confirmar('¿Anular el lote?', 'Se quitan sus movimientos (si alguno está conciliado, primero hay que deshacer la conciliación).')
            .then(() => Bco.pedir(AJAX, { accion: 'ejecutar_accion', importacion_id: id, accion_js: accion }))
            .then((res) => { Bco.ok(res.message); tabla.ajax.reload(null, false); })
            .catch(() => {});
    });
});
