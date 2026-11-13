$(document).ready(function () {
    const AJAX = 'bancos_reglas_ajax.php';
    const { esc } = Bco;
    const TIPOS = { CONTIENE: 'contiene', EMPIEZA: 'empieza con', REGEX: 'cumple', IGUAL: 'es igual a' };
    const APLICA = { AMBOS: 'Ambos', DEBITO: 'Débitos', CREDITO: 'Créditos' };
    let tabla;

    tabla = $('#tablaReglas').DataTable({
        ajax: { url: AJAX, data: (d) => Object.assign(d, { accion: 'listar', empresa_idx: window.EMPRESA_ID, banco_id: $('#filtroBanco').val() || 0 }), dataSrc: Bco.datos },
        paging: false, info: false, order: [],
        columns: [
            { data: 'banco', render: (d) => esc(d) },
            { data: 'prioridad', className: 'text-center' },
            { data: 'patron', render: (d, t, r) => `<span class="text-muted">${TIPOS[r.tipo_coincidencia]}</span> <code>${esc(d)}</code>`
                + (r.origen === 'APRENDIDA' ? ' <span class="badge bg-light text-dark border" title="Se creó al asignar el concepto a mano en Conciliación Bancaria">aprendida</span>' : '') },
            { data: 'aplica_a', className: 'text-center', render: (d) => APLICA[d] || d },
            { data: 'concepto', render: (d, t, r) => esc(d) + (r.naturaleza === 'GASTO_BANCARIO' ? ' <span class="badge bg-warning text-dark">Gasto</span>' : '') },
            { data: 'lineas', className: 'text-center ui-detalle' },
            { data: 'estado_registro', className: 'text-center', render: (d, t, r) => Bco.estado(r) },
            { data: 'botones', orderable: false, className: 'text-center text-nowrap', render: (d, t, r) => Bco.botonesFila(d, r.regla_id, r.patron) }
        ],
        createdRow: (row, data) => { if (data.codigo_estandar === 'INACTIVO') $(row).addClass('table-secondary text-muted'); },
        language: Bco.idioma()
    });

    // Botones de página: Agregar y Aplicar reglas
    $.getJSON(AJAX, { accion: 'obtener_boton_agregar', empresa_idx: window.EMPRESA_ID }, (res) => {
        $('#contenedor-boton-agregar').html((res.data || []).map(b =>
            Bco.botonPagina(b, b.accion_js === 'agregar' ? 'btnNuevo' : 'btnAplicar', b.accion_js === 'agregar' ? '' : 'btn-sm ms-1')).join(''));
    });
    $.getJSON(AJAX, { accion: 'opciones', empresa_idx: window.EMPRESA_ID }, (res) => {
        const d = res.data || {};
        Bco.opcionesSelect($('#banco_id'), d.bancos || [], 'banco_id', 'banco', 'Elegí el banco');
        Bco.opcionesSelect($('#filtroBanco'), d.bancos || [], 'banco_id', 'banco', 'Todos los bancos');
        Bco.opcionesSelect($('#concepto_id'), d.conceptos || [], 'concepto_id',
            (k) => `${k.concepto} (${k.naturaleza === 'GASTO_BANCARIO' ? 'gasto del banco' : 'movimiento propio'})`, 'Elegí el concepto');
    });

    $('#btnRecargar').on('click', () => tabla.ajax.reload(null, false));
    $('#filtroBanco').on('change', () => tabla.ajax.reload());

    $('#btnProbarTexto').on('click', function () {
        const banco = $('#filtroBanco').val();
        if (!banco) return $('#resultadoTexto').html('<span class="text-warning">Elegí un banco en el filtro.</span>');
        Bco.pedir(AJAX, { accion: 'probar_texto', banco_id: banco, descripcion: $('#textoPrueba').val(), importe: $('#signoPrueba').val() }, { metodo: 'GET' })
            .then((res) => {
                const r = res.data;
                $('#resultadoTexto').html(r ? `<span class="text-success"><i class="bi bi-check-circle me-1"></i>La clasifica la regla de prioridad ${r.prioridad}
                    (${TIPOS[r.tipo_coincidencia]} <code>${esc(r.patron)}</code>).</span>`
                    : '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Ninguna regla activa del banco la clasifica: quedaría sin concepto.</span>');
            }).catch(() => {});
    });

    function abrir(d, soloVer) {
        const f = $('#formRegla');
        f[0].reset();
        f.removeClass('was-validated');
        $('#regla_id').val(d ? d.regla_id : '');
        $('#modalReglaLabel').text(d ? (soloVer ? 'Regla' : 'Editar regla') : 'Nueva regla');
        if (d) ['banco_id', 'prioridad', 'aplica_a', 'tipo_coincidencia', 'patron', 'concepto_id'].forEach(c => $('#' + c).val(d[c]));
        else if ($('#filtroBanco').val()) $('#banco_id').val($('#filtroBanco').val());
        $('#formRegla :input').not('[data-bs-dismiss]').prop('disabled', !!soloVer);
        $('#btnGuardar').toggle(!soloVer);
        $('#modalRegla').modal('show');
    }

    $(document).on('click', '#btnNuevo', () => abrir(null));

    $(document).on('click', '#btnAplicar', function () {
        Bco.confirmar('¿Aplicar las reglas?', 'Vuelve a clasificar las líneas sin conciliar que no tienen un concepto puesto a mano.' +
                      ($('#filtroBanco').val() ? ' Solo del banco elegido.' : ''))
            .then(() => Bco.pedir(AJAX, { accion: 'aplicar', banco_id: $('#filtroBanco').val() || 0 }))
            .then((res) => { Swal.fire({ icon: 'success', title: 'Listo', text: res.message }); tabla.ajax.reload(null, false); })
            .catch(() => {});
    });

    $(document).on('click', '.btn-accion', function () {
        const id = $(this).data('id');
        const accion = $(this).data('accion');
        if (accion === 'editar' || accion === 'visualizar') {
            Bco.pedir(AJAX, { accion: 'obtener', regla_id: id }, { metodo: 'GET' }).then((res) => abrir(res.data, accion === 'visualizar')).catch(() => {});
            return;
        }
        Bco.confirmar(`¿${$(this).attr('title')}?`, $(this).data('nombre'))
            .then(() => Bco.pedir(AJAX, { accion: 'ejecutar_accion', regla_id: id, accion_js: accion }))
            .then((res) => { Bco.ok(res.message); tabla.ajax.reload(null, false); })
            .catch(() => {});
    });

    $('#formRegla').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) { $(this).addClass('was-validated'); return; }
        const obj = { accion: $('#regla_id').val() ? 'editar' : 'agregar' };
        $(this).serializeArray().forEach(x => { obj[x.name] = x.value; });
        const btn = $('#btnGuardar').prop('disabled', true);
        Bco.pedir(AJAX, obj).then((res) => {
            $('#modalRegla').modal('hide');
            Bco.ok(res.message);
            tabla.ajax.reload(null, false);
        }).catch(() => {}).finally(() => btn.prop('disabled', false));
    });
});
