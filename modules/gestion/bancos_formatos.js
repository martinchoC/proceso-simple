$(document).ready(function () {
    const AJAX = 'bancos_formatos_ajax.php';
    const { esc, num, fecha, letra } = Bco;
    let tabla;

    tabla = $('#tablaFormatos').DataTable({
        ajax: { url: AJAX, data: { accion: 'listar', empresa_idx: window.EMPRESA_ID }, dataSrc: Bco.datos },
        paging: false, info: false, order: [],
        columns: [
            { data: 'banco', render: (d) => esc(d) },
            { data: 'formato', render: (d) => `<span class="fw-semibold">${esc(d)}</span>` },
            { data: 'tipo_archivo', className: 'text-center', render: (d) => `<span class="badge bg-light text-dark border">${esc(d)}</span>` },
            { data: 'columnas', className: 'ui-detalle small', render: (d) => esc(d || '') },
            { data: 'f_desde', className: 'text-center text-nowrap', render: (d, t, r) => fecha(d) + (r.f_hasta ? ' al ' + fecha(r.f_hasta) : ' en adelante') },
            { data: 'importaciones', className: 'text-center ui-detalle' },
            { data: 'estado_registro', className: 'text-center', render: (d, t, r) => Bco.estado(r) },
            { data: 'botones', orderable: false, className: 'text-center text-nowrap', render: (d, t, r) => Bco.botonesFila(d, r.formato_id, r.banco + ' - ' + r.formato) }
        ],
        createdRow: (row, data) => { if (data.codigo_estandar === 'INACTIVO') $(row).addClass('table-secondary text-muted'); },
        language: Bco.idioma()
    });

    $.getJSON(AJAX, { accion: 'obtener_boton_agregar', empresa_idx: window.EMPRESA_ID }, (res) => {
        if (res && res.data) $('#contenedor-boton-agregar').html(Bco.botonPagina(res.data, 'btnNuevo'));
    });
    $.getJSON(AJAX, { accion: 'opciones', empresa_idx: window.EMPRESA_ID }, (res) => {
        Bco.opcionesSelect($('#banco_id'), (res.data && res.data.bancos) || [], 'banco_id', 'banco', 'Elegí el banco');
    });

    $('#btnRecargar').on('click', () => tabla.ajax.reload(null, false));

    function sincronizar() {
        const csv = $('#tipo_archivo').val() === 'CSV';
        $('.solo-csv').toggle(csv);
        const signo = $('#modo_importe').val() === 'SIGNO';
        $('.modo-signo').toggle(signo);
        $('.modo-dc').toggle(!signo);
        $('#col_importe').prop('required', signo);
        $('#col_debito, #col_credito').prop('required', !signo);
    }
    $('#tipo_archivo, #modo_importe').on('change', sincronizar);
    $(document).on('input', '.col-letra', function () { this.value = this.value.toUpperCase().replace(/[^A-Z]/g, ''); });

    function abrir(d, soloVer) {
        const f = $('#formFormato');
        f[0].reset();
        f.removeClass('was-validated');
        $('#resultadoPrueba').empty();
        $('#formato_id').val(d ? d.formato_id : '');
        $('#modalFormatoLabel').text(d ? (soloVer ? 'Formato' : 'Editar formato') : 'Nuevo formato');
        if (d) {
            ['banco_id', 'formato', 'tipo_archivo', 'hoja', 'fila_inicio', 'delimitador', 'codificacion', 'formato_fecha', 'idioma_fecha',
             'orden_archivo', 'modo_importe', 'sep_decimal', 'cuenta_fila', 'cuenta_regex', 'referencia_regex', 'f_desde', 'f_hasta']
                .forEach(c => $('#' + c).val(d[c] == null ? '' : d[c]));
            $('#sep_miles').val(d.sep_miles == null ? '' : d.sep_miles);
            $('#saldo_anterior').prop('checked', Number(d.saldo_posterior) === 0);
            $('#cuenta_columna').val(letra(d.cuenta_columna));
            const cols = d.columnas || {};
            Object.keys(cols).forEach(k => $('#col_' + k.toLowerCase()).val(letra(cols[k])));
        } else {
            $('#f_desde').val(Fmt.hoyISO());
        }
        sincronizar();
        $('#formFormato :input').not('[data-bs-dismiss], #archivoPrueba, #btnProbar').prop('disabled', !!soloVer);
        $('#btnGuardar').toggle(!soloVer);
        $('#modalFormato').modal('show');
    }

    $(document).on('click', '#btnNuevo', () => abrir(null));

    $(document).on('click', '.btn-accion', function () {
        const id = $(this).data('id');
        const accion = $(this).data('accion');
        if (['editar', 'visualizar', 'probar'].includes(accion)) {
            Bco.pedir(AJAX, { accion: 'obtener', formato_id: id }, { metodo: 'GET' }).then((res) => {
                abrir(res.data, accion === 'visualizar');
                if (accion === 'probar') setTimeout(() => $('#archivoPrueba').trigger('focus'), 400);
            }).catch(() => {});
            return;
        }
        Bco.confirmar(`¿${$(this).attr('title')}?`, $(this).data('nombre'))
            .then(() => Bco.pedir(AJAX, { accion: 'ejecutar_accion', formato_id: id, accion_js: accion }))
            .then((res) => { Bco.ok(res.message); tabla.ajax.reload(null, false); })
            .catch(() => {});
    });

    $('#formFormato').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) { $(this).addClass('was-validated'); return; }
        const datos = $(this).serializeArray();
        datos.push({ name: 'accion', value: $('#formato_id').val() ? 'editar' : 'agregar' });
        const obj = {};
        datos.forEach(x => { obj[x.name] = x.value; });
        const btn = $('#btnGuardar').prop('disabled', true);
        Bco.pedir(AJAX, obj).then((res) => {
            $('#modalFormato').modal('hide');
            Bco.ok(res.message);
            tabla.ajax.reload(null, false);
        }).catch(() => {}).finally(() => btn.prop('disabled', false));
    });

    $('#btnProbar').on('click', function () {
        const archivo = $('#archivoPrueba')[0].files[0];
        if (!archivo) return Swal.fire({ icon: 'info', title: 'Elegí un archivo del banco.' });
        const fd = new FormData($('#formFormato')[0]);
        $('#formFormato :disabled[name]').each(function () { fd.set(this.name, $(this).val()); });
        if ($('#saldo_anterior').is(':checked')) fd.set('saldo_anterior', '1');
        fd.append('accion', 'probar');
        fd.append('archivo', archivo);
        const btn = $(this).prop('disabled', true);
        $('#resultadoPrueba').html('<div class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Leyendo…</div>');
        Bco.pedir(AJAX, fd, { silencioso: true }).then((res) => mostrarPrueba(res.data))
            .catch((r) => $('#resultadoPrueba').html(Bco.listaAvisos([Bco.mensajeDe(r)], [])))
            .finally(() => btn.prop('disabled', false));
    });

    function mostrarPrueba(x) {
        const c = x.control || {};
        const okSaldos = c.pares && c.pares === c.pares_ok;
        let h = `<div class="row g-2 mb-2">
            <div class="col-6 col-md-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">Movimientos</div><div class="ui-kpi-valor">${x.cantidad}</div>
                <div class="ui-kpi-sub">${fecha(x.f_desde)} al ${fecha(x.f_hasta)}</div></div></div>
            <div class="col-6 col-md-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">Saldo inicial / final</div>
                <div class="ui-kpi-valor fs-6">${num(x.saldo_inicial)} / ${num(x.saldo_final)}</div><div class="ui-kpi-sub">Movimientos: ${num(x.total)}</div></div></div>
            <div class="col-6 col-md-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">Cadena de saldos</div>
                <div class="ui-kpi-valor ${okSaldos ? 'text-success' : 'text-danger'}">${c.pares_ok || 0} / ${c.pares || 0}</div><div class="ui-kpi-sub">pares que cierran</div></div></div>
            <div class="col-6 col-md-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">N° de cuenta</div>
                <div class="ui-kpi-valor fs-6">${esc(x.cuenta_archivo || '—')}</div></div></div>
        </div>`;
        h += Bco.listaAvisos(c.errores, x.avisos);
        h += `<div class="table-responsive"><table class="table table-sm small mb-0"><thead><tr><th>Fila</th><th>Fecha</th><th>Descripción</th>
              <th>Referencia</th><th class="text-end">Importe</th><th class="text-end">Saldo</th></tr></thead><tbody>` +
            (x.muestra || []).map(m => `<tr><td>${m.nro_linea}</td><td class="text-nowrap">${fecha(m.f_movimiento)}</td><td>${esc(m.descripcion)}</td>
                <td>${esc(m.referencia || '')}</td><td class="text-end">${Bco.importe(m.importe)}</td><td class="text-end font-monospace">${num(m.saldo)}</td></tr>`).join('') +
            `</tbody></table></div><div class="text-muted small mt-1">Primeros ${(x.muestra || []).length} movimientos, en orden cronológico.</div>`;
        $('#resultadoPrueba').html(h);
    }
});
