$(document).ready(function () {
    const AJAX = 'bancos_conceptos_ajax.php';
    const { esc } = Bco;
    const ROLES = { ITEM: 'Línea con producto', IVA: 'IVA', PERCEPCION_IVA: 'Percepción de IVA', PERCEPCION_IIBB: 'Percepción de IIBB', PERCEPCION_GANANCIAS: 'Percepción de Ganancias', RETENCION_IIBB: 'IIBB sobre créditos (SIRCREB)' };
    let tabla;

    tabla = $('#tablaConceptos').DataTable({
        ajax: { url: AJAX, data: { accion: 'listar', empresa_idx: window.EMPRESA_ID }, dataSrc: Bco.datos },
        paging: false, info: false, order: [],
        columns: [
            { data: 'codigo', className: 'text-center font-monospace fw-bold', render: (d) => esc(d) },
            { data: 'concepto', render: (d) => esc(d) },
            { data: 'naturaleza', render: (d) => d === 'GASTO_BANCARIO' ? '<span class="badge bg-warning text-dark">Gasto del banco</span>' : '<span class="badge bg-info text-dark">Movimiento propio</span>' },
            { data: 'rol', render: (d, t, r) => {
                if (!d) return '<span class="text-muted small">Se concilia contra tesorería</span>' + (Number(r.es_cobranza) ? ' <span class="badge bg-success" title="Se asigna a las líneas de las que se genera un recibo de cobranza">Cobranzas</span>' : '');
                const extra = d === 'ITEM' ? (r.producto_nombre ? ' · ' + esc(r.producto_nombre) : ' · <span class="text-danger">sin producto</span>')
                                           : (r.impuesto_tipo ? ' · ' + esc(r.impuesto_tipo) : '')
                                             + (['PERCEPCION_IIBB', 'RETENCION_IIBB'].includes(d) ? (r.jurisdiccion_nombre ? ' · ' + esc(r.jurisdiccion_nombre) : ' · <span class="text-danger">sin provincia</span>') : '');
                return esc(ROLES[d] || d) + extra;
            } },
            { data: 'reglas', className: 'text-center ui-detalle' },
            { data: 'estado_registro', className: 'text-center', render: (d, t, r) => Bco.estado(r) },
            { data: 'botones', orderable: false, className: 'text-center text-nowrap', render: (d, t, r) => Bco.botonesFila(d, r.concepto_id, r.codigo + ' - ' + r.concepto) }
        ],
        createdRow: (row, data) => { if (data.codigo_estandar === 'INACTIVO') $(row).addClass('table-secondary text-muted'); },
        language: Bco.idioma()
    });

    $.getJSON(AJAX, { accion: 'obtener_boton_agregar', empresa_idx: window.EMPRESA_ID }, (res) => {
        if (res && res.data) $('#contenedor-boton-agregar').html(Bco.botonPagina(res.data, 'btnNuevo'));
    });
    $.getJSON(AJAX, { accion: 'opciones', empresa_idx: window.EMPRESA_ID }, (res) => {
        const d = res.data || {};
        Bco.opcionesSelect($('#producto_id'), d.productos || [], 'producto_id',
            (p) => `${p.producto_codigo} - ${p.producto_nombre}` + (p.iva_porcentaje != null ? ` (IVA ${Number(p.iva_porcentaje)}%)` : ''), 'Elegí el producto');
        Bco.opcionesSelect($('#jurisdiccion_id'), d.jurisdicciones || [], 'jurisdiccion_id', 'jurisdiccion_nombre', 'Elegí la provincia');
    });

    $('#btnRecargar').on('click', () => tabla.ajax.reload(null, false));

    function sincronizar() {
        const gasto = $('input[name="naturaleza"]:checked').val() === 'GASTO_BANCARIO';
        const rol = $('#rol').val();
        $('.solo-gasto').toggle(gasto);
        $('.solo-propio').toggle(!gasto);
        $('.solo-item').toggle(gasto && rol === 'ITEM');
        $('.solo-jurisdiccion').toggle(gasto && ['PERCEPCION_IIBB', 'RETENCION_IIBB'].includes(rol));
    }
    $('input[name="naturaleza"], #rol').on('change', sincronizar);

    function abrir(d, soloVer) {
        const f = $('#formConcepto');
        f[0].reset();
        f.removeClass('was-validated');
        $('#concepto_id').val(d ? d.concepto_id : '');
        $('#modalConceptoLabel').text(d ? (soloVer ? 'Concepto' : 'Editar concepto') : 'Nuevo concepto');
        if (d) {
            ['codigo', 'concepto', 'orden', 'rol', 'producto_id', 'jurisdiccion_id'].forEach(c => $('#' + c).val(d[c] == null ? '' : d[c]));
            $(`input[name="naturaleza"][value="${d.naturaleza}"]`).prop('checked', true);
            $('#es_cobranza').prop('checked', Number(d.es_cobranza) === 1);
        } else {
            $('#nat_gasto').prop('checked', true);
        }
        sincronizar();
        $('#formConcepto :input').not('[data-bs-dismiss]').prop('disabled', !!soloVer);
        $('#btnGuardar').toggle(!soloVer);
        $('#modalConcepto').modal('show');
    }

    $(document).on('click', '#btnNuevo', () => abrir(null));

    $(document).on('click', '.btn-accion', function () {
        const id = $(this).data('id');
        const accion = $(this).data('accion');
        if (accion === 'editar' || accion === 'visualizar') {
            Bco.pedir(AJAX, { accion: 'obtener', concepto_id: id }, { metodo: 'GET' }).then((res) => abrir(res.data, accion === 'visualizar')).catch(() => {});
            return;
        }
        Bco.confirmar(`¿${$(this).attr('title')}?`, $(this).data('nombre'))
            .then(() => Bco.pedir(AJAX, { accion: 'ejecutar_accion', concepto_id: id, accion_js: accion }))
            .then((res) => { Bco.ok(res.message); tabla.ajax.reload(null, false); })
            .catch(() => {});
    });

    $('#formConcepto').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) { $(this).addClass('was-validated'); return; }
        const obj = { accion: $('#concepto_id').val() ? 'editar' : 'agregar' };
        $(this).serializeArray().forEach(x => { obj[x.name] = x.value; });
        obj.es_cobranza = $('#es_cobranza').is(':checked') && $('#nat_propio').is(':checked') ? 1 : 0;
        const btn = $('#btnGuardar').prop('disabled', true);
        Bco.pedir(AJAX, obj).then((res) => {
            $('#modalConcepto').modal('hide');
            Bco.ok(res.message);
            tabla.ajax.reload(null, false);
        }).catch(() => {}).finally(() => btn.prop('disabled', false));
    });
});
