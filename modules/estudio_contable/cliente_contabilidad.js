/**
 * JavaScript: Contabilidad del Cliente (cliente_contabilidad.js) - Módulo 4 Estudio Contable, página 423.
 * Usa cliente_contabilidad_ajax.php. Cada solapa se carga al abrirla y se recarga al cambiar el filtro.
 * El detalle de un asiento (desde Asientos o desde un movimiento del mayor) ofrece "Editar" si el servidor lo permite
 * (período abierto, función y vinculación con escritura). Balance: cliente_contabilidad_balance.js (CnBalance).
 */
$(function () {
    const URL = 'cliente_contabilidad_ajax.php';
    const LANG = { url: window.APP_ASSETS_URL + 'js/datatables-i18n-1.13.7/es-ES.json' };
    let clientes = [];
    let cargadas = {};
    let balance = null;

    function esc(t) { return $('<div>').text(t == null ? '' : String(t)).html(); }
    function plata(v) { return Fmt.moneda(Number(v || 0)); }
    function num(v) { const n = Number(v || 0); return Math.abs(n) < 0.005 ? '' : Fmt.numero(n, 2); }
    function clienteId() { return parseInt($('#cnCliente').val() || '0', 10); }
    function filtro(extra) {
        return Object.assign({ cliente_id: clienteId(), desde: $('#cnDesde').val(), hasta: $('#cnHasta').val(),
                               borradores: $('#cnBorradores').is(':checked') ? 1 : 0 }, extra || {});
    }
    function get(accion, extra) {
        return $.getJSON(URL, Object.assign({ accion: accion }, filtro(extra)))
            .fail(function () { UiSimple.error('Revisá la conexión e intentá de nuevo.', 'No se pudo cargar'); });
    }
    function solapaActiva() { return $('#cnSolapas .nav-link.active').closest('li').data('solapa'); }

    const tSumas = $('#tablaSumas').DataTable({
        language: LANG, pageLength: 50, order: [[0, 'asc']], data: [],
        columns: [
            { data: 'codigo' }, { data: 'nombre' },
            { data: 'saldo_ant_neto', className: 'text-end', render: function (v, t) { return t === 'display' ? num(v) : v; } },
            { data: 'periodo_debe', className: 'text-end', render: function (v, t) { return t === 'display' ? num(v) : v; } },
            { data: 'periodo_haber', className: 'text-end', render: function (v, t) { return t === 'display' ? num(v) : v; } },
            { data: 'saldo_deudor', className: 'text-end', render: function (v, t) { return t === 'display' ? num(v) : v; } },
            { data: 'saldo_acreedor', className: 'text-end', render: function (v, t) { return t === 'display' ? num(v) : v; } }
        ]
    });
    const tCuentas = $('#tablaMayorCuentas').DataTable({
        language: LANG, pageLength: 25, order: [[0, 'asc']], data: [],
        columns: [
            { data: 'codigo' }, { data: 'nombre' },
            { data: 'saldo_final', className: 'text-end', render: function (v, t) { return t === 'display' ? Fmt.numero(Number(v || 0), 2) : v; } }
        ]
    });
    const tAsientos = $('#tablaAsientos').DataTable({
        language: LANG, pageLength: 25, order: [[0, 'desc'], [1, 'desc']], data: [],
        columns: [
            { data: 'f_asiento' },
            { data: 'cont_asiento_id' },
            { data: 'descripcion', render: function (v) { return esc(v); } },
            { data: 'tipo', render: function (v) { return esc(v || ''); } },
            { data: 'estado_id', render: function (v, t, r) {
                if (t !== 'display') { return v; }
                return (Number(v) === 3 ? '<span class="badge text-bg-warning">Borrador</span>' : '<span class="badge text-bg-success">Registrado</span>')
                    + (r.cuadrado ? '' : ' <span class="badge text-bg-danger">Descuadrado</span>');
            } },
            { data: 'debe', className: 'text-end', render: function (v, t) { return t === 'display' ? Fmt.numero(Number(v), 2) : v; } },
            { data: 'haber', className: 'text-end', render: function (v, t) { return t === 'display' ? Fmt.numero(Number(v), 2) : v; } }
        ]
    });

    const tPlan = $('#tablaPlan').DataTable({
        language: LANG, pageLength: 50, order: [[0, 'asc']], data: [],
        columns: [
            { data: 'codigo', render: function (v, t, r) { return t === 'display' && !Number(r.es_imputable) ? '<strong>' + esc(v) + '</strong>' : v; } },
            { data: 'nombre', render: function (v, t, r) {
                if (t !== 'display') { return v; }
                const pad = 'padding-left:' + (Math.max(0, Number(r.nivel || 1) - 1) * 1.1) + 'rem';
                return '<span style="' + pad + '"' + (Number(r.es_imputable) ? '' : ' class="fw-semibold"') + '>' + esc(v) + '</span>';
            } },
            { data: 'nivel', className: 'text-center' },
            { data: 'naturaleza', className: 'text-center', render: function (v, t) {
                if (t !== 'display') { return v; }
                return v === 'D' ? '<span class="badge bg-primary-subtle text-primary">D</span>' : '<span class="badge bg-danger-subtle text-danger">H</span>';
            } },
            { data: 'es_imputable', render: function (v, t, r) {
                const txt = Number(v) ? 'Imputable' : 'Rubro';
                if (t !== 'display') { return txt; }
                return (Number(v) ? '<span class="badge bg-light text-muted border">Imputable</span>' : '<span class="badge bg-secondary-subtle text-secondary">Rubro</span>')
                    + (Number(r.es_caja_banco) ? ' <span class="badge bg-info-subtle text-info">Caja / banco</span>' : '');
            } },
            { data: 'padre_codigo', className: 'ui-detalle', render: function (v, t, r) { return v ? esc(v + ' - ' + (r.padre_nombre || '')) : ''; } },
            { data: null, orderable: false, className: 'text-center', render: function (v, t, r) {
                return Number(r.es_imputable) ? '<button type="button" class="btn btn-sm btn-link p-0 cn-plan-mayor" title="Ver el mayor de la cuenta"><i class="bi bi-journal-text"></i></button>' : '';
            } }
        ]
    });
    $('#tablaPlan tbody').on('click', '.cn-plan-mayor', function () {
        const c = tPlan.row($(this).closest('tr')).data();
        if (c) { CnBalance.alAbrirMayor(c); }
    });

    // ---------- Clientes y período ----------
    $.getJSON(URL, { accion: 'clientes' }).done(function (r) {
        if (!r.success) { UiSimple.error(r.message); return; }
        clientes = r.data;
        const $s = $('#cnCliente');
        clientes.forEach(function (c) {
            const marca = c.propio ? ' (el estudio)' : (!c.lectura ? ' · sin permiso' : '');
            $s.append($('<option>').val(c.cliente_id).text(c.razon_social + marca));
        });
        const id = EstudioCliente.inicial();
        if (id > 0 && $s.find('option[value="' + id + '"]').length) { $s.val(String(id)).trigger('change'); }
    });

    $('#cnCliente').on('change', function () {
        $('#cnEjercicio').empty().prop('disabled', true);
        $('#cnAviso').addClass('d-none');
        if (!clienteId()) { $('#cnPanel').addClass('d-none'); $('#cnVacio').removeClass('d-none'); return; }
        EstudioCliente.guardar(clienteId());
        cargarPeriodo(0);
    });
    $('#cnEjercicio').on('change', function () { cargarPeriodo(parseInt($(this).val(), 10)); });
    $('#cnDesde, #cnHasta, #cnBorradores').on('change', recargar);

    function cargarPeriodo(ejercicio) {
        $.getJSON(URL, { accion: 'periodo', cliente_id: clienteId(), ejercicio: ejercicio || '' }).done(function (r) {
            if (!r.success) {
                $('#cnPanel').addClass('d-none'); $('#cnVacio').addClass('d-none');
                $('#cnAviso').text(r.message).removeClass('d-none');
                return;
            }
            const d = r.data;
            const $e = $('#cnEjercicio').empty().prop('disabled', false);
            d.ejercicios.forEach(function (x) { $e.append($('<option>').val(x.ejercicio).text(x.texto)); });
            $e.val(String(d.ejercicio));
            $('#cnDesde').val(d.desde);
            $('#cnHasta').val(d.hasta);
            $('#cnVacio').addClass('d-none'); $('#cnPanel').removeClass('d-none');
            recargar();
        });
    }

    function recargar() {
        if (!clienteId() || !$('#cnDesde').val()) { return; }
        cargadas = {};
        cuentaMayor = null;
        $('#mayorTitulo').text('Elegí una cuenta'); $('#tbMayor').empty(); $('#tfMayor').addClass('d-none');
        const s = solapaActiva();
        cargadas[s] = true;
        return CARGAS[s]();
    }
    $('#cnSolapas').on('shown.bs.tab', 'button', function () {
        const s = $(this).closest('li').data('solapa');
        if (!cargadas[s]) { cargadas[s] = true; CARGAS[s](); }
    });

    // ---------- Solapas ----------
    function cargarBalance(despues) {
        get('balance').done(function (r) {
            if (!r.success) { UiSimple.error(r.message); return; }
            balance = r.data;
            if (despues) { despues(); }
        });
    }

    const CARGAS = {
        // La misma vista que Gestión > Balance (cliente_contabilidad_balance.js)
        balance: function () { return CnBalance.cargar(filtro()); },
        sumas: function () {
            cargarBalance(function () {
                const imputables = balance.cuentas.filter(function (c) {
                    return c.es_imputable && (c.tiene_movimientos || Math.abs(c.saldo_ant_neto) > 0.004);
                });
                tSumas.clear().rows.add(imputables).draw();
                const t = balance.totales || {};
                $('#tsDebe').text(Fmt.numero(Number(t.total_periodo_debe || 0), 2));
                $('#tsHaber').text(Fmt.numero(Number(t.total_periodo_haber || 0), 2));
                $('#tsDeudor').text(Fmt.numero(Number(t.total_saldos_deudores || 0), 2));
                $('#tsAcreedor').text(Fmt.numero(Number(t.total_saldos_acreedores || 0), 2));
                $('#sumasDescuadre').toggleClass('d-none', !!t.esta_cuadrado)
                    .text('Las sumas no cierran: diferencia de sumas ' + plata(t.diferencia_sumas) + ', de saldos ' + plata(t.diferencia_saldos) + '. Revisá los asientos descuadrados.');
            });
        },
        mayores: function () {
            return get('mayor_cuentas').done(function (r) {
                if (!r.success) { UiSimple.error(r.message); return; }
                tCuentas.clear().rows.add(r.data).draw();
                marcarCuentaMayor();
            });
        },
        asientos: function () {
            get('asientos').done(function (r) {
                if (!r.success) { UiSimple.error(r.message); return; }
                tAsientos.clear().rows.add(r.data).draw();
            });
        },
        plan: function () {
            get('plan').done(function (r) {
                if (!r.success) { UiSimple.error(r.message); return; }
                tPlan.clear().rows.add(r.data).draw();
            });
        }
    };

    // ---------- Mayor de una cuenta (cada movimiento abre su asiento, que se edita con el período abierto) ----------
    let cuentaMayor = null;    // cuenta cuyo mayor se está mostrando

    function marcarCuentaMayor() {
        $('#tablaMayorCuentas tbody tr').removeClass('table-active');
        if (!cuentaMayor) { return; }
        tCuentas.rows().every(function () {
            const d = this.data();
            if (d && Number(d.cont_cuenta_id) === Number(cuentaMayor.cont_cuenta_id)) { $(this.node()).addClass('table-active'); }
        });
    }

    function mostrarMayor(c) {
        cuentaMayor = { cont_cuenta_id: c.cont_cuenta_id, codigo: c.codigo, nombre: c.nombre };
        marcarCuentaMayor();
        get('mayor_detalle', { cuenta_id: c.cont_cuenta_id }).done(function (r) {
            if (!r.success) { UiSimple.error(r.message); return; }
            const d = r.data;
            $('#mayorTitulo').text(c.codigo + ' - ' + c.nombre);
            let html = '<tr class="table-light"><td colspan="5" class="text-muted">Saldo inicial</td><td class="text-end">' + Fmt.numero(Number(d.saldo_inicial || 0), 2) + '</td></tr>';
            html += (d.detalle || []).map(function (m) {
                // La fecha ya viene con el formato de la empresa (obtenerDetalleCuenta)
                const id = Number(m.cont_asiento_id || 0);
                return '<tr' + (id ? ' class="cn-mov-asiento" role="button" data-asiento="' + id + '" title="Ver el asiento (y editarlo si su período está abierto)"' : '') + '>' +
                    '<td class="text-nowrap">' + esc(m.fecha) + '</td><td>' + (id ? '<span class="link-primary">' + esc(m.numero_asiento) + '</span>' : esc(m.numero_asiento)) + '</td>' +
                    '<td>' + esc(m.descripcion) + (m.comprobante && m.comprobante !== '-' ? '<br><small class="text-muted">' + esc(m.comprobante) + '</small>' : '') + '</td>' +
                    '<td class="text-end">' + num(m.debe) + '</td><td class="text-end">' + num(m.haber) + '</td>' +
                    '<td class="text-end">' + Fmt.numero(Number(m.saldo || 0), 2) + '</td></tr>';
            }).join('');
            if (!(d.detalle || []).length) { html += '<tr><td colspan="6" class="text-center text-muted py-3">Sin movimientos en el período.</td></tr>'; }
            $('#tbMayor').html(html);
            $('#tmDebe').text(Fmt.numero(Number(d.total_debe || 0), 2));
            $('#tmHaber').text(Fmt.numero(Number(d.total_haber || 0), 2));
            $('#tmSaldo').text(Fmt.numero(Number(d.saldo_final || 0), 2));
            $('#tfMayor').removeClass('d-none');
        });
    }
    $('#tablaMayorCuentas tbody').on('click', 'tr', function () {
        const c = tCuentas.row(this).data();
        if (c) { mostrarMayor(c); }
    });
    $('#tbMayor').on('click', 'tr.cn-mov-asiento', function () { abrirAsiento(parseInt($(this).data('asiento'), 10)); });

    // Desde el Balance (Sumas y Saldos): ir a la solapa Mayores con esa cuenta
    CnBalance.alAbrirMayor = function (c) {
        bootstrap.Tab.getOrCreateInstance($('#cnSolapas li[data-solapa="mayores"] button')[0]).show();
        mostrarMayor(c);
    };

    // ---------- Detalle de un asiento y edición (con el período contable abierto) ----------
    const modal = new bootstrap.Modal(document.getElementById('modalAsiento'));
    let asiento = null;        // respuesta de asiento_detalle del asiento abierto
    let cuentas = null;        // cuentas imputables del cliente (se piden la primera vez que se edita)
    let cuentasCliente = 0;

    $('#cnCliente').on('change', function () { cuentas = null; cuentasCliente = 0; });

    function abrirAsiento(id) {
        get('asiento_detalle', { asiento_id: id }).done(function (r) {
            if (!r.success) { UiSimple.error(r.message); return; }
            asiento = r.data;
            mostrarVista();
            modal.show();
        });
    }
    $('#tablaAsientos tbody').on('click', 'tr', function () {
        const a = tAsientos.row(this).data();
        if (a) { abrirAsiento(a.cont_asiento_id); }
    });

    function mostrarVista() {
        const d = asiento;
        let td = 0, th = 0;
        $('#maTitulo').text('Asiento ' + d.cabecera.cont_asiento_id + ' · ' + Fmt.fecha(d.cabecera.f_asiento) + ' · ' + (d.cabecera.descripcion || ''));
        $('#tbAsiento').html(d.lineas.map(function (l) {
            td += Number(l.debe); th += Number(l.haber);
            return '<tr><td class="text-nowrap">' + esc(l.codigo) + '</td><td>' + esc(l.cuenta) + '</td><td>' + esc(l.descripcion || '') + '</td>' +
                '<td class="text-end">' + num(l.debe) + '</td><td class="text-end">' + num(l.haber) + '</td></tr>';
        }).join(''));
        $('#maDebe').text(Fmt.numero(td, 2));
        $('#maHaber').text(Fmt.numero(th, 2));
        const ed = d.edicion || {};
        $('#maVista').removeClass('d-none');
        $('#maEdicion').addClass('d-none');
        $('#btnEditarAsiento').toggleClass('d-none', !ed.puede);
        $('#btnCancelarEdicion, #btnGuardarAsiento').addClass('d-none');
        $('#maMotivo').text(ed.puede ? '' : (ed.motivo || ''));
    }

    function opcionesCuentas(sel) {
        return '<option value="">Elegí una cuenta…</option>' + cuentas.map(function (c) {
            return '<option value="' + c.cont_cuenta_id + '"' + (Number(c.cont_cuenta_id) === Number(sel) ? ' selected' : '') + '>' +
                esc(c.codigo + ' - ' + c.nombre) + '</option>';
        }).join('');
    }
    function esAutomatico() { return !!(asiento && asiento.cabecera && asiento.cabecera.es_automatico); }
    function filaEdicion(l) {
        l = l || {};
        const v = function (x) { return Number(x) > 0 ? Number(x).toFixed(2) : ''; };
        // Asiento automático: solo se cambia la cuenta de los renglones de productos; el resto queda fijo
        const auto = esAutomatico();
        const fijo = auto ? ' disabled' : '';
        const cuentaFija = auto && !l.cuenta_editable ? ' disabled' : '';
        return '<tr data-clave="' + esc(l.clave || '') + '" data-cuenta-original="' + (l.cuenta_id || '') + '">' +
            '<td><select class="form-select form-select-sm me-cuenta"' + cuentaFija + '>' + opcionesCuentas(l.cuenta_id) + '</select></td>' +
            '<td><input type="text" class="form-control form-control-sm me-desc" maxlength="500" value="' + esc(l.descripcion || '') + '"' + fijo + '></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end me-debe" value="' + v(l.debe) + '"' + fijo + '></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end me-haber" value="' + v(l.haber) + '"' + fijo + '></td>' +
            '<td class="text-center">' + (auto ? (cuentaFija ? '<i class="bi bi-lock text-muted" title="Cuenta generada por el comprobante: no se modifica"></i>' : '')
                : '<button type="button" class="btn btn-sm btn-link text-danger p-0 me-quitar" title="Quitar renglón"><i class="bi bi-trash"></i></button>') + '</td>' +
            '</tr>';
    }
    function totalesEdicion() {
        let td = 0, th = 0;
        $('#tbEdicion tr').each(function () {
            td += Number($(this).find('.me-debe').val() || 0);
            th += Number($(this).find('.me-haber').val() || 0);
        });
        const dif = Math.round((td - th) * 100) / 100;
        $('#meDebe').text(Fmt.numero(td, 2));
        $('#meHaber').text(Fmt.numero(th, 2));
        $('#meDiferencia').text(dif === 0 ? '' : 'Diferencia ' + Fmt.numero(Math.abs(dif), 2) + (dif > 0 ? ' en el Debe' : ' en el Haber'))
            .toggleClass('text-danger', dif !== 0);
    }

    function iniciarEdicion() {
        const d = asiento;
        $('#maFecha').val(d.cabecera.f_asiento);
        $('#maDescripcion').val(d.cabecera.descripcion || '');
        const auto = esAutomatico();
        $('#maAvisoComprobante').toggleClass('d-none', !(Number(d.cabecera.comprobante_id) > 0 || auto));
        $('#maAvisoAutomatico').toggleClass('d-none', !auto);
        $('#maFecha, #maDescripcion').prop('disabled', auto);
        $('#btnRenglon').toggleClass('d-none', auto);
        $('#tbEdicion').html(d.lineas.map(filaEdicion).join(''));
        totalesEdicion();
        $('#maVista').addClass('d-none');
        $('#maEdicion').removeClass('d-none');
        $('#btnEditarAsiento').addClass('d-none');
        $('#btnCancelarEdicion, #btnGuardarAsiento').removeClass('d-none');
        $('#maMotivo').text('');
    }
    $('#btnEditarAsiento').on('click', function () {
        if (cuentas && cuentasCliente === clienteId()) { iniciarEdicion(); return; }
        get('cuentas').done(function (r) {
            if (!r.success) { UiSimple.error(r.message); return; }
            cuentas = r.data;
            cuentasCliente = clienteId();
            iniciarEdicion();
        });
    });
    $('#btnCancelarEdicion').on('click', mostrarVista);
    $('#btnRenglon').on('click', function () { $('#tbEdicion').append(filaEdicion()); });
    $('#tbEdicion').on('click', '.me-quitar', function () { $(this).closest('tr').remove(); totalesEdicion(); });
    $('#tbEdicion').on('input', '.me-debe, .me-haber', function () {
        // Un renglón va al Debe o al Haber: cargar uno limpia el otro
        if (Number($(this).val() || 0) > 0) {
            $(this).closest('tr').find($(this).hasClass('me-debe') ? '.me-haber' : '.me-debe').val('');
        }
        totalesEdicion();
    });

    function despuesDeGuardar(r) {
        if (!r.success) { UiSimple.error(r.message, 'No se pudo guardar'); return; }
        UiSimple.ok('Asiento guardado');
        modal.hide();
        balance = null;
        // Si se editó desde un mayor, se vuelve a mostrar el de la misma cuenta con los importes nuevos
        const enMayor = solapaActiva() === 'mayores' ? cuentaMayor : null;
        recargar();
        if (enMayor) { mostrarMayor(enMayor); }
    }
    function falloAlGuardar(x) {
        const msg = x.responseJSON && x.responseJSON.message;
        UiSimple.error(msg || 'Revisá la conexión e intentá de nuevo.', 'No se pudo guardar');
    }

    $('#btnGuardarAsiento').on('click', function () {
        if (esAutomatico()) {
            // Solo los renglones de productos cuya cuenta cambió
            const cambios = $('#tbEdicion tr').map(function () {
                const $f = $(this);
                const nueva = parseInt($f.find('.me-cuenta').val() || '0', 10);
                if ($f.find('.me-cuenta').prop('disabled') || !nueva || nueva === parseInt($f.data('cuenta-original') || '0', 10)) { return null; }
                return { clave: String($f.data('clave')), cuenta_id: nueva };
            }).get();
            if (!cambios.length) { UiSimple.aviso('No cambiaste ninguna cuenta.'); return; }
            const $ba = $(this).prop('disabled', true);
            $.post(URL, { accion: 'asiento_cuentas_guardar', cliente_id: clienteId(), asiento_id: asiento.cabecera.cont_asiento_id,
                          cambios: JSON.stringify(cambios) }, null, 'json')
                .done(despuesDeGuardar).fail(falloAlGuardar)
                .always(function () { $ba.prop('disabled', false); });
            return;
        }
        const lineas = $('#tbEdicion tr').map(function () {
            const $f = $(this);
            return { cuenta_id: parseInt($f.find('.me-cuenta').val() || '0', 10), descripcion: $f.find('.me-desc').val(),
                     debe: Number($f.find('.me-debe').val() || 0), haber: Number($f.find('.me-haber').val() || 0) };
        }).get();
        const $b = $(this).prop('disabled', true);
        $.post(URL, { accion: 'asiento_guardar', cliente_id: clienteId(), asiento_id: asiento.cabecera.cont_asiento_id,
                      fecha: $('#maFecha').val(), descripcion: $('#maDescripcion').val(), lineas: JSON.stringify(lineas) }, null, 'json')
            .done(despuesDeGuardar)
            .fail(falloAlGuardar)
            .always(function () { $b.prop('disabled', false); });
    });
});
