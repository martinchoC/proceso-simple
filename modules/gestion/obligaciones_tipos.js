$(document).ready(function () {
    const AJAX = 'obligaciones_tipos_ajax.php';
    const { esc } = Bco;
    let roles = {};
    const COMP = { OBL: 'Obligación de Pago', I_LIQ: 'Liquidación Impositiva', SOL_ANT: 'Solicitud de Anticipo' };
    const cuenta = (c, n) => c ? `<span class="small">${esc(c)} ${esc(n)}</span>` : '<span class="text-muted small">por defecto</span>';

    const tabla = $('#tablaTipos').DataTable({
        ajax: { url: AJAX, data: (d) => Object.assign(d, { accion: 'listar', empresa_idx: window.EMPRESA_ID }), dataSrc: Bco.datos },
        paging: false, info: false, order: [],
        columns: [
            { data: 'codigo', render: (d) => `<code>${esc(d)}</code>` },
            { data: 'nombre', render: (d) => esc(d) },
            { data: 'clase', render: (d) => d === 'ANTICIPO' ? 'Anticipo' : 'Deuda' },
            { data: 'rol_entidad_codigo', render: (d) => d ? esc(roles[d] || d) : 'Cualquiera' },
            { data: 'comprobante_tipo_codigo', render: (d) => esc(COMP[d] || d) },
            { data: 'cuenta_debe', render: (d, t, r) => cuenta(r.cuenta_debe_codigo, d) },
            { data: 'cuenta_pasivo', render: (d, t, r) => r.clase === 'ANTICIPO' ? '' : cuenta(r.cuenta_pasivo_codigo, d) },
            { data: 'permite_manual', className: 'text-center ui-detalle', render: (d, t, r) => +d === 1 ? 'Manual / importación' : 'Sistema: ' + esc(r.origen_sistema || '') },
            { data: 'estado_registro', className: 'text-center', render: (d, t, r) => Bco.estado(r) },
            { data: 'botones', orderable: false, className: 'text-center text-nowrap', render: (d, t, r) => Bco.botonesFila(d, r.obligacion_tipo_id, r.nombre) }
        ],
        createdRow: (row, data) => { if (data.codigo_estandar === 'INACTIVO') $(row).addClass('table-secondary text-muted'); },
        language: Bco.idioma()
    });
    const filas = {};
    tabla.on('xhr.dt', (e, s, json) => { (json && json.data || []).forEach(r => { filas[r.obligacion_tipo_id] = r; }); });

    $.getJSON(AJAX, { accion: 'obtener_boton_agregar', empresa_idx: window.EMPRESA_ID }, (res) => {
        $('#contenedor-boton-agregar').html((res.data || []).map(b => Bco.botonPagina(b, 'btnNuevo', 'btn-sm')).join(''));
    });
    Bco.pedir(AJAX, { accion: 'opciones' }, { metodo: 'GET' }).then((res) => {
        roles = res.data.roles || {};
        $('#rol_entidad_codigo').html('<option value="">Cualquiera</option>' + Object.keys(roles).map(k => `<option value="${k}">${esc(roles[k])}</option>`).join(''));
        const ctas = (sel) => Bco.opcionesSelect(sel, res.data.cuentas || [], 'cont_cuenta_id', (c) => `${c.codigo} ${c.nombre}`, 'Por defecto');
        ctas($('#cont_cuenta_debe_id'));
        ctas($('#cont_cuenta_pasivo_id'));
        tabla.ajax.reload(null, false);
    }).catch(() => {});

    function ajustarClase() {
        const anticipo = $('#clase').val() === 'ANTICIPO';
        $('#comprobante_tipo_codigo').prop('disabled', anticipo || $('#formTipo').data('sistema'));
        $('#cont_cuenta_pasivo_id').closest('.col-md-6').toggle(!anticipo);
    }
    $('#clase').on('change', ajustarClase);

    function abrir(d, soloVer) {
        const f = $('#formTipo');
        f[0].reset();
        f.removeClass('was-validated');
        const sistema = !!(d && d.origen_sistema);
        f.data('sistema', sistema);
        $('#obligacion_tipo_id').val(d ? d.obligacion_tipo_id : '');
        $('#modalTipoLabel').text(d ? (soloVer ? 'Tipo de obligación' : 'Editar tipo de obligación') : 'Nuevo tipo de obligación');
        if (d) ['codigo', 'nombre', 'clase', 'rol_entidad_codigo', 'comprobante_tipo_codigo', 'cont_cuenta_debe_id', 'cont_cuenta_pasivo_id', 'orden']
            .forEach(c => $('#' + c).val(d[c] == null ? '' : d[c]));
        $('#formTipo :input').not('[data-bs-dismiss]').prop('disabled', !!soloVer);
        if (!soloVer && sistema) $('#codigo, #clase, #rol_entidad_codigo').prop('disabled', true);
        ajustarClase();
        $('#btnGuardar').toggle(!soloVer);
        $('#modalTipo').modal('show');
    }

    $(document).on('click', '#btnNuevo', () => abrir(null));
    $(document).on('click', '#tablaTipos .btn-accion', function () {
        const id = $(this).data('id');
        const accion = $(this).data('accion');
        if (accion === 'editar' || accion === 'visualizar') return abrir(filas[id], accion === 'visualizar');
        Bco.confirmar(`¿${$(this).attr('title')}?`, $(this).data('nombre'))
            .then(() => Bco.pedir(AJAX, { accion: 'ejecutar_accion', obligacion_tipo_id: id, accion_js: accion }))
            .then((res) => { Bco.ok(res.message); tabla.ajax.reload(null, false); }).catch(() => {});
    });

    $('#formTipo').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) { $(this).addClass('was-validated'); return; }
        const obj = { accion: $('#obligacion_tipo_id').val() ? 'editar' : 'agregar' };
        $(this).find(':input[name]').each(function () { obj[this.name] = $(this).val(); });   // incluye los deshabilitados
        const btn = $('#btnGuardar').prop('disabled', true);
        Bco.pedir(AJAX, obj).then((res) => {
            $('#modalTipo').modal('hide');
            Bco.ok(res.message);
            tabla.ajax.reload(null, false);
        }).catch(() => {}).finally(() => btn.prop('disabled', false));
    });
});
