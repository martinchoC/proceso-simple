$(document).ready(function () {
    const AJAX = 'aprobaciones_reglas_ajax.php';
    const { esc } = Bco;
    const DOC = { OBLIGACION: 'Obligaciones', OP: 'Órdenes de pago' };
    let que = 'regla';
    const filas = { regla: {}, tope: {} };
    const vigencia = (r) => Bco.fecha(r.f_desde) + (r.f_hasta ? ' al ' + Bco.fecha(r.f_hasta) : ' en adelante');
    const importe = (v) => v == null || v === '' ? '' : '$ ' + Bco.num(v);
    const ajax = (q) => ({ url: AJAX, data: (d) => Object.assign(d, { accion: 'listar', que: q, empresa_idx: window.EMPRESA_ID }), dataSrc: Bco.datos });
    const botones = (q, idCampo) => ({ data: 'botones', orderable: false, className: 'text-center text-nowrap',
        render: (d, t, r) => Bco.botonesFila(d, r[idCampo], DOC[r.documento]).replaceAll('btn-accion', `btn-accion btn-${q}`) });

    const tablaReglas = $('#tablaReglas').DataTable({
        ajax: ajax('regla'), paging: false, info: false, order: [],
        columns: [
            { data: 'documento', render: (d) => DOC[d] || d },
            { data: 'tipo_nombre', render: (d, t, r) => r.documento === 'OP' ? '' : (d ? esc(d) : '<span class="text-muted">Todos</span>') },
            { data: 'requiere_aprobacion', render: (d) => +d === 1 ? 'Sí' : 'No' },
            { data: 'importe_desde', className: 'text-end', render: importe },
            { data: 'doble_aprobacion_desde', className: 'text-end', render: importe },
            { data: 'permite_autoaprobar', className: 'ui-detalle', render: (d) => +d === 1 ? 'Permitida' : 'No' },
            { data: 'f_desde', render: (d, t, r) => vigencia(r) },
            { data: 'estado_registro', className: 'text-center', render: (d, t, r) => Bco.estado(r) },
            botones('regla', 'regla_id')
        ],
        language: Bco.idioma()
    });
    const tablaTopes = $('#tablaTopes').DataTable({
        ajax: ajax('tope'), paging: false, info: false, order: [],
        columns: [
            { data: 'documento', render: (d) => DOC[d] || d },
            { data: 'tipo_nombre', render: (d, t, r) => r.documento === 'OP' ? '' : (d ? esc(d) : '<span class="text-muted">Todos</span>') },
            { data: 'empresa_perfil_nombre', render: (d) => esc(d) },
            { data: 'importe_maximo', className: 'text-end', render: (d) => d == null ? 'Sin tope' : importe(d) },
            { data: 'f_desde', render: (d, t, r) => vigencia(r) },
            { data: 'estado_registro', className: 'text-center', render: (d, t, r) => Bco.estado(r) },
            botones('tope', 'tope_id')
        ],
        language: Bco.idioma()
    });
    tablaReglas.on('xhr.dt', (e, s, j) => (j && j.data || []).forEach(r => { filas.regla[r.regla_id] = r; }));
    tablaTopes.on('xhr.dt', (e, s, j) => (j && j.data || []).forEach(r => { filas.tope[r.tope_id] = r; }));
    const recargar = () => { tablaReglas.ajax.reload(null, false); tablaTopes.ajax.reload(null, false); };

    $('[data-que]').on('shown.bs.tab', function () {
        que = $(this).data('que');
        (que === 'tope' ? tablaTopes : tablaReglas).columns.adjust();
    });

    $.getJSON(AJAX, { accion: 'obtener_boton_agregar', empresa_idx: window.EMPRESA_ID }, (res) => {
        $('#contenedor-boton-agregar').html((res.data || []).map(b => Bco.botonPagina(b, 'btnNuevo', 'btn-sm')).join(''));
    });
    Bco.pedir(AJAX, { accion: 'opciones' }, { metodo: 'GET' }).then((res) => {
        Bco.opcionesSelect($('#obligacion_tipo_id'), res.data.tipos || [], 'obligacion_tipo_id', 'nombre', 'Todos los tipos');
        Bco.opcionesSelect($('#empresa_perfil_id'), res.data.perfiles || [], 'empresa_perfil_id', 'empresa_perfil_nombre', 'Elegí el perfil');
    }).catch(() => {});

    function ajustar() {
        $('.solo-regla').toggle(que === 'regla');
        $('.solo-tope').toggle(que === 'tope');
        $('.solo-obligacion').toggle($('#documento').val() === 'OBLIGACION');
        $('#empresa_perfil_id').prop('required', que === 'tope');
    }
    $('#documento').on('change', ajustar);

    function abrir(d, soloVer) {
        const f = $('#formRegla');
        f[0].reset();
        f.removeClass('was-validated');
        $('#regla_id, #tope_id').val('');
        $('#modalReglaLabel').text((d ? (soloVer ? '' : 'Editar ') : 'Nueva ') + (que === 'tope' ? 'habilitación para aprobar' : 'regla de aprobación'));
        $('#f_desde').val(Fmt.hoyISO());
        if (d) {
            ['regla_id', 'tope_id', 'documento', 'obligacion_tipo_id', 'importe_desde', 'doble_aprobacion_desde', 'empresa_perfil_id', 'importe_maximo', 'f_desde', 'f_hasta']
                .forEach(c => { if (c in d) $('#' + c).val(d[c] == null ? '' : d[c]); });
            $('#requiere_aprobacion').prop('checked', +d.requiere_aprobacion === 1);
            $('#permite_autoaprobar').prop('checked', +d.permite_autoaprobar === 1);
        }
        ajustar();
        $('#formRegla :input').not('[data-bs-dismiss]').prop('disabled', !!soloVer);
        $('#btnGuardar').toggle(!soloVer);
        $('#modalRegla').modal('show');
    }

    $(document).on('click', '#btnNuevo', () => abrir(null));
    $(document).on('click', '.btn-regla, .btn-tope', function () {
        const q = $(this).hasClass('btn-tope') ? 'tope' : 'regla';
        que = q;
        const id = $(this).data('id');
        const accion = $(this).data('accion');
        if (accion === 'editar' || accion === 'visualizar') return abrir(filas[q][id], accion === 'visualizar');
        Bco.confirmar(`¿${$(this).attr('title')}?`, '')
            .then(() => Bco.pedir(AJAX, { accion: 'ejecutar_accion', que: q, id, accion_js: accion }))
            .then((res) => { Bco.ok(res.message); recargar(); }).catch(() => {});
    });

    $('#formRegla').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) { $(this).addClass('was-validated'); return; }
        const obj = { accion: ($('#regla_id').val() || $('#tope_id').val()) ? 'editar' : 'agregar', que };
        $(this).serializeArray().forEach(x => { obj[x.name] = x.value; });
        const btn = $('#btnGuardar').prop('disabled', true);
        Bco.pedir(AJAX, obj).then((res) => {
            $('#modalRegla').modal('hide');
            Bco.ok(res.message);
            recargar();
        }).catch(() => {}).finally(() => btn.prop('disabled', false));
    });
});
