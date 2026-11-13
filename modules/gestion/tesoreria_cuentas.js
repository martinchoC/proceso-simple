$(document).ready(function () {
    const empresa_idx = window.EMPRESA_ID;
    const AJAX = 'tesoreria_cuentas_ajax.php';
    let tabla;
    let maestros = {};

    function esc(t) {
        return $('<div>').text(t == null ? '' : String(t)).html();
    }

    function importe(n) {
        return Fmt.numero(Number(n || 0), { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function error(res, xhr) {
        let msg = (res && (res.message || res.error)) || 'No se pudo completar la operación';
        if (xhr) {
            try { const j = JSON.parse(xhr.responseText); msg = j.message || j.error || msg; } catch (e) { /* respuesta no JSON */ }
        }
        Swal.fire({ icon: 'error', title: 'Cuentas de tesorería', text: msg });
    }

    function opciones($sel, filas, valor, texto, vacio) {
        $sel.empty();
        if (vacio !== undefined) $sel.append(`<option value="">${esc(vacio)}</option>`);
        filas.forEach(f => $sel.append(`<option value="${esc(f[valor])}">${esc(texto(f))}</option>`));
    }

    function cargarMaestros() {
        return $.getJSON(AJAX, { accion: 'maestros', empresa_idx: empresa_idx }, function (res) {
            if (!res.success) return error(res);
            maestros = res.data;
            opciones($('#cuenta_tipo'), Object.entries(maestros.tipos).map(([k, v]) => ({ k, v })), 'k', f => f.v);
            opciones($('#banco_cuenta_tipo'), Object.entries(maestros.banco_cuenta_tipos).map(([k, v]) => ({ k, v })), 'k', f => f.v, '-- Elegir --');
            opciones($('#moneda_id'), maestros.monedas, 'moneda_id', f => f.codigo + ' - ' + f.moneda + (f.es_moneda_base == 1 ? ' (base)' : ''));
            opciones($('#sucursal_id'), maestros.sucursales, 'sucursal_id', f => f.sucursal_nombre, '-- Toda la empresa --');
            opciones($('#banco_id'), maestros.bancos, 'banco_id', f => f.banco, '-- Elegir --');
            opciones($('#cont_cuenta_id'), maestros.cont_cuentas, 'cont_cuenta_id', f => f.codigo + ' - ' + f.nombre, '-- Elegir --');
        });
    }

    function botonHtml(b, row) {
        const clase = (b.bg_clase && b.text_clase) ? `${b.bg_clase} ${b.text_clase}` : (b.color_clase || 'btn-outline-primary');
        const icono = b.icono_clase ? `<i class="${b.icono_clase}"></i>` : esc(b.nombre_funcion);
        return `<button type="button" class="btn btn-sm ${clase} me-1 btn-accion" title="${esc(b.descripcion || b.nombre_funcion)}"
                    data-id="${row.tesoreria_cuenta_id}" data-accion="${esc(b.accion_js)}" data-confirmable="${b.es_confirmable || 0}"
                    data-nombre="${esc(row.tesoreria_cuenta)}">${icono}</button>`;
    }

    const COLOR_TIPO = { CAJA: 'bg-success', BANCO: 'bg-info text-dark', VALORES: 'bg-primary', CUPONES: 'bg-warning text-dark' };

    function iniciarTabla() {
        tabla = $('#tablaCuentas').DataTable({
            ajax: { url: AJAX, data: { accion: 'listar', empresa_idx: empresa_idx }, dataSrc: 'data' },
            pageLength: 25,
            order: [],
            columns: [
                { data: 'cuenta_tipo', className: 'text-center', render: (d) => `<span class="badge ${COLOR_TIPO[d] || 'bg-secondary'}">${esc(d)}</span>` },
                { data: 'codigo', className: 'text-center font-monospace', render: (d) => esc(d || '') },
                { data: 'tesoreria_cuenta', className: 'fw-semibold', render: (d) => esc(d) },
                { data: null, render: (d, t, r) => r.cuenta_tipo === 'BANCO'
                    ? `${esc(r.banco || '')}${r.banco_cuenta_tipo ? ' · ' + esc(r.banco_cuenta_tipo) : ''}${r.cbu ? '<br><small class="text-muted font-monospace">' + esc(r.cbu) + '</small>' : ''}`
                    : esc(r.sucursal_nombre || 'Toda la empresa') },
                { data: 'moneda_codigo', className: 'text-center', render: (d) => esc(d || '') },
                { data: 'cont_cuenta_codigo', render: (d, t, r) => d ? `<span class="font-monospace">${esc(d)}</span> <small class="text-muted">${esc(r.cont_cuenta_nombre)}</small>`
                    : '<span class="badge bg-danger">Sin cuenta contable</span>' },
                { data: 'saldo_actual', className: 'text-end font-monospace', render: (d, t, r) => t === 'display' ? `${esc(r.simbolo || '')} ${importe(d)}` : d },
                { data: 'estado_registro', className: 'text-center',
                  render: (d, t, r) => `<span class="badge ${r.bg_clase || 'bg-secondary'} ${r.text_clase || 'text-white'}">${esc(d || '')}</span>` },
                { data: 'botones', orderable: false, className: 'text-center text-nowrap',
                  render: (d, t, r) => (d || []).map(b => botonHtml(b, r)).join('') || '<span class="text-muted small">Sin acciones</span>' }
            ],
            createdRow: function (row, data) {
                if (data.codigo_estandar === 'INACTIVO') $(row).addClass('table-secondary text-muted');
            },
            language: { url: window.APP_ASSETS_URL + 'js/datatables-i18n-2.1.8/es-ES.json' }
        });
    }

    $.getJSON(AJAX, { accion: 'obtener_boton_agregar', empresa_idx: empresa_idx }, function (res) {
        const b = res && res.data;
        if (!b) return;   // sin la función Agregar configurada no se muestra el botón
        const clase = (b.bg_clase && b.text_clase) ? `${b.bg_clase} ${b.text_clase}` : (b.color_clase || 'btn-primary');
        $('#contenedor-boton-agregar').html(`<button type="button" class="btn ${clase}" id="btnNuevo">
            <i class="${b.icono_clase || 'fas fa-plus'} me-1"></i>${esc(b.nombre_funcion)}</button>`);
    });

    $('#btnRecargar').on('click', () => tabla.ajax.reload(null, false));

    function segunTipo() {
        const tipo = $('#cuenta_tipo').val();
        $('.seccion-banco').toggle(tipo === 'BANCO');
        $('#banco_id, #banco_cuenta_tipo').prop('required', tipo === 'BANCO');
        $('#sucursal_id').prop('required', tipo === 'CAJA');
        $('.campo-caja').toggle(tipo === 'CAJA');
    }
    $('#cuenta_tipo').on('change', segunTipo);

    // copia: alta precargada con los datos de d, sin lo propio de cada cuenta (código, número, CBU, alias)
    function abrir(d, copia) {
        const f = $('#formCuenta');
        f[0].reset();
        f.removeClass('was-validated');
        $('#tesoreria_cuenta_id').val(d && !copia ? d.tesoreria_cuenta_id : '');
        $('#modalCuentaLabel').html(copia ? '<i class="bi bi-copy me-2 text-primary"></i>Copiar cuenta de tesorería'
                                  : d ? '<i class="fas fa-edit me-2 text-primary"></i>Editar cuenta de tesorería'
                                      : '<i class="fas fa-plus me-2 text-primary"></i>Nueva cuenta de tesorería');
        $('#avisoMovimientos').text('');
        $('#cuenta_tipo, #moneda_id').prop('disabled', false);
        if (d && copia) {
            ['cuenta_tipo', 'moneda_id', 'sucursal_id', 'orden', 'cont_cuenta_id', 'banco_id', 'banco_cuenta_tipo']
                .forEach(c => $('#' + c).val(d[c] == null ? '' : d[c]));
            $('#tesoreria_cuenta').val(String(d.tesoreria_cuenta || '').substring(0, 92) + ' (copia)');
            $('#permite_saldo_negativo').prop('checked', d.permite_saldo_negativo == 1);
            $('#avisoMovimientos').text(`Copia de "${d.tesoreria_cuenta}": complete el código, el número, el CBU y el alias de la cuenta nueva.`);
        } else if (d) {
            ['cuenta_tipo', 'codigo', 'tesoreria_cuenta', 'moneda_id', 'sucursal_id', 'orden', 'cont_cuenta_id', 'banco_id', 'banco_cuenta_tipo',
             'numero', 'cbu', 'alias'].forEach(c => $('#' + c).val(d[c] == null ? '' : d[c]));
            $('#permite_saldo_negativo').prop('checked', d.permite_saldo_negativo == 1);
            if (parseInt(d.movimientos, 10) > 0) {
                $('#cuenta_tipo, #moneda_id').prop('disabled', true);
                $('#avisoMovimientos').text(`Tiene ${d.movimientos} movimientos: el tipo y la moneda no se cambian.`);
            }
        } else {
            const base = (maestros.monedas || []).find(m => m.es_moneda_base == 1);
            if (base) $('#moneda_id').val(base.moneda_id);
        }
        segunTipo();
        $('#modalCuenta').modal('show');
    }

    $(document).on('click', '#btnNuevo', function () {
        if (!(maestros.monedas || []).length) {
            return Swal.fire({ icon: 'warning', title: 'Falta la moneda', text: 'Cargue antes la moneda base de la empresa (Tesorería > Monedas).' });
        }
        abrir(null);
    });

    $(document).on('click', '.btn-accion', function () {
        const id = $(this).data('id');
        const accion = $(this).data('accion');
        if (accion === 'editar' || accion === 'visualizar' || accion === 'copiar') {
            $.getJSON(AJAX, { accion: 'obtener', tesoreria_cuenta_id: id, empresa_idx: empresa_idx }, function (res) {
                if (!res.success) return error(res);
                abrir(res.data, accion === 'copiar');
                if (accion === 'visualizar') {
                    $('#formCuenta :input').not('[data-bs-dismiss]').prop('disabled', true);
                    $('#modalCuenta').one('hidden.bs.modal', () => $('#formCuenta :input').prop('disabled', false));
                }
            }).fail((xhr) => error(null, xhr));
            return;
        }
        const ejecutar = () => $.post(AJAX, { accion: 'ejecutar_accion', tesoreria_cuenta_id: id, accion_js: accion, empresa_idx: empresa_idx }, function (res) {
            if (!res.success) return error(res);
            Swal.fire({ icon: 'success', title: res.message, timer: 1500, showConfirmButton: false });
            tabla.ajax.reload(null, false);
        }, 'json').fail((xhr) => error(null, xhr));
        if ($(this).data('confirmable') == 1) {
            Swal.fire({ icon: 'question', title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)}?`, text: $(this).data('nombre'),
                        showCancelButton: true, confirmButtonText: 'Sí', cancelButtonText: 'Cancelar' })
                .then(r => { if (r.isConfirmed) ejecutar(); });
        } else {
            ejecutar();
        }
    });

    $('#formCuenta').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) {
            $(this).addClass('was-validated');
            return;
        }
        // tipo y moneda deshabilitados (cuenta con movimientos) no viajan en el serialize: se agregan
        const datos = $(this).serializeArray().filter(x => x.name !== 'cuenta_tipo' && x.name !== 'moneda_id');
        datos.push({ name: 'cuenta_tipo', value: $('#cuenta_tipo').val() }, { name: 'moneda_id', value: $('#moneda_id').val() });
        datos.push({ name: 'accion', value: $('#tesoreria_cuenta_id').val() ? 'editar' : 'agregar' });
        datos.push({ name: 'empresa_idx', value: empresa_idx });
        const btn = $('#btnGuardarCuenta').prop('disabled', true);
        $.post(AJAX, datos, function (res) {
            btn.prop('disabled', false);
            if (!res.success) return error(res);
            $('#modalCuenta').modal('hide');
            Swal.fire({ icon: 'success', title: res.message, timer: 1500, showConfirmButton: false });
            tabla.ajax.reload(null, false);
        }, 'json').fail((xhr) => { btn.prop('disabled', false); error(null, xhr); });
    });

    cargarMaestros().always(iniciarTabla);
});
