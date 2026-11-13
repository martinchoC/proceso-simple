$(document).ready(function () {
    const empresa_idx = window.EMPRESA_ID;
    const AJAX = 'monedas_ajax.php';
    let tabla;

    function esc(t) {
        return $('<div>').text(t == null ? '' : String(t)).html();
    }

    function botonHtml(b, row) {
        const clase = (b.bg_clase && b.text_clase) ? `${b.bg_clase} ${b.text_clase}` : (b.color_clase || 'btn-outline-primary');
        const icono = b.icono_clase ? `<i class="${b.icono_clase}"></i>` : esc(b.nombre_funcion);
        return `<button type="button" class="btn btn-sm ${clase} me-1 btn-accion" title="${esc(b.descripcion || b.nombre_funcion)}"
                    data-id="${row.moneda_id}" data-accion="${esc(b.accion_js)}" data-confirmable="${b.es_confirmable || 0}"
                    data-nombre="${esc(row.codigo + ' - ' + row.moneda)}">${icono}</button>`;
    }

    function error(res, xhr) {
        let msg = (res && (res.message || res.error)) || 'No se pudo completar la operación';
        if (xhr) {
            try { const j = JSON.parse(xhr.responseText); msg = j.message || j.error || msg; } catch (e) { /* respuesta no JSON */ }
        }
        Swal.fire({ icon: 'error', title: 'Monedas', text: msg });
    }

    tabla = $('#tablaMonedas').DataTable({
        ajax: { url: AJAX, data: { accion: 'listar', empresa_idx: empresa_idx }, dataSrc: 'data' },
        paging: false,
        info: false,
        order: [],
        columns: [
            { data: 'codigo', className: 'text-center font-monospace fw-bold' },
            { data: 'moneda', render: (d) => esc(d) },
            { data: 'simbolo', className: 'text-center', render: (d) => esc(d) },
            { data: 'es_moneda_base', className: 'text-center', render: (d) => d == 1 ? '<span class="badge bg-primary">Base</span>' : '' },
            { data: 'cotizacion_actual', className: 'text-end font-monospace',
              render: (d) => Fmt.numero(Number(d), { minimumFractionDigits: 2, maximumFractionDigits: 6 }) },
            { data: 'cantidad_decimales', className: 'text-center' },
            { data: 'cuentas_tesoreria', className: 'text-center' },
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

    $.getJSON(AJAX, { accion: 'obtener_boton_agregar', empresa_idx: empresa_idx }, function (res) {
        const b = res && res.data;
        if (!b) return;   // sin la función Agregar configurada no se muestra el botón
        const clase = (b.bg_clase && b.text_clase) ? `${b.bg_clase} ${b.text_clase}` : (b.color_clase || 'btn-primary');
        $('#contenedor-boton-agregar').html(`<button type="button" class="btn ${clase}" id="btnNuevo">
            <i class="${b.icono_clase || 'fas fa-plus'} me-1"></i>${esc(b.nombre_funcion)}</button>`);
    });

    $('#btnRecargar').on('click', () => tabla.ajax.reload(null, false));

    function sincronizarBase() {
        const base = $('#es_moneda_base').is(':checked');
        $('#cotizacion_actual').prop('readonly', base);
        $('#permite_editar_cotizacion').prop('disabled', base);
        if (base) {
            $('#cotizacion_actual').val(1);
            $('#permite_editar_cotizacion').prop('checked', false);
        }
    }
    $('#es_moneda_base').on('change', sincronizarBase);

    function abrir(d) {
        const f = $('#formMoneda');
        f[0].reset();
        f.removeClass('was-validated');
        $('#moneda_id').val(d ? d.moneda_id : '');
        $('#modalMonedaLabel').html(d ? '<i class="fas fa-edit me-2 text-primary"></i>Editar moneda' : '<i class="fas fa-plus me-2 text-primary"></i>Nueva moneda');
        $('#avisoUsoMoneda').text('');
        if (d) {
            ['codigo', 'moneda', 'simbolo', 'cantidad_decimales', 'cotizacion_actual', 'orden'].forEach(c => $('#' + c).val(d[c]));
            $('#es_moneda_base').prop('checked', d.es_moneda_base == 1);
            $('#permite_editar_cotizacion').prop('checked', d.permite_editar_cotizacion == 1);
            const u = d.uso || {};
            const usada = (u.comprobantes || 0) + (u.cuentas || 0) > 0;
            $('#codigo, #cantidad_decimales').prop('readonly', usada);
            if (usada) $('#avisoUsoMoneda').text(`Tiene ${u.comprobantes} comprobantes y ${u.cuentas} cuentas de tesorería: el código y los decimales no se cambian.`);
        } else {
            $('#codigo, #cantidad_decimales').prop('readonly', false);
        }
        sincronizarBase();
        $('#modalMoneda').modal('show');
    }

    $(document).on('click', '#btnNuevo', () => abrir(null));

    $(document).on('click', '.btn-accion', function () {
        const id = $(this).data('id');
        const accion = $(this).data('accion');
        if (accion === 'editar' || accion === 'visualizar') {
            $.getJSON(AJAX, { accion: 'obtener', moneda_id: id, empresa_idx: empresa_idx }, function (res) {
                if (!res.success) return error(res);
                abrir(res.data);
                if (accion === 'visualizar') {
                    $('#formMoneda :input').not('[data-bs-dismiss]').prop('disabled', true);
                    $('#modalMoneda').one('hidden.bs.modal', () => $('#formMoneda :input').prop('disabled', false));
                }
            }).fail((xhr) => error(null, xhr));
            return;
        }
        const ejecutar = () => $.post(AJAX, { accion: 'ejecutar_accion', moneda_id: id, accion_js: accion, empresa_idx: empresa_idx }, function (res) {
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

    $('#formMoneda').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) {
            $(this).addClass('was-validated');
            return;
        }
        const datos = $(this).serializeArray();
        datos.push({ name: 'accion', value: $('#moneda_id').val() ? 'editar' : 'agregar' });
        datos.push({ name: 'empresa_idx', value: empresa_idx });
        if ($('#es_moneda_base').is(':checked')) datos.push({ name: 'cotizacion_actual', value: 1 });
        const btn = $('#btnGuardarMoneda').prop('disabled', true);
        $.post(AJAX, datos, function (res) {
            btn.prop('disabled', false);
            if (!res.success) return error(res);
            $('#modalMoneda').modal('hide');
            Swal.fire({ icon: 'success', title: res.message, timer: 1500, showConfirmButton: false });
            tabla.ajax.reload(null, false);
        }, 'json').fail((xhr) => { btn.prop('disabled', false); error(null, xhr); });
    });
});
