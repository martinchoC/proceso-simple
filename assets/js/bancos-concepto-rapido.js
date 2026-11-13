/**
 * Conciliación bancaria: alta rápida de un concepto en un modal (templates/bancos/concepto_rapido_modal.php).
 * Global BcoConceptoRapido. Lo usan Conciliación Bancaria y Extractos Bancarios (gestion y gestion_normal).
 * El _ajax.php de la página atiende 'agregar_concepto' (función "agregar" de Conceptos Bancarios) y, si se pasan
 * líneas, 'clasificar' / asignar: el concepto nuevo se les asigna (y a las sin concepto con la misma descripción).
 */
window.BcoConceptoRapido = (function () {
    let cfg = { ajax: '', lineas: [], alGuardar: null };
    let codigoTocado = false;

    const codigoDe = (t) => String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toUpperCase()
        .replace(/[^A-Z0-9]+/g, '_').replace(/^_+|_+$/g, '').substring(0, 30);

    function sincronizar() {
        const gasto = $('#formConcepto input[name="naturaleza"]:checked').val() === 'GASTO_BANCARIO';
        const rol = $('#k_rol').val();
        $('.k-solo-gasto').toggle(gasto);
        $('.k-solo-item').toggle(gasto && rol === 'ITEM');
        $('.k-solo-jurisdiccion').toggle(gasto && ['PERCEPCION_IIBB', 'RETENCION_IIBB'].includes(rol));
    }

    /** Carga los productos de servicio y las provincias (respuesta 'opciones' del _ajax.php). */
    function opciones(productos, jurisdicciones) {
        Bco.opcionesSelect($('#k_producto_id'), productos || [], 'producto_id',
            (p) => `${p.producto_codigo} - ${p.producto_nombre}` + (p.iva_porcentaje != null ? ` (IVA ${Number(p.iva_porcentaje)}%)` : ''), 'Elegí el producto');
        Bco.opcionesSelect($('#k_jurisdiccion_id'), jurisdicciones || [], 'jurisdiccion_id', 'jurisdiccion_nombre', 'Elegí la provincia');
    }

    /**
     * Abre el modal. o.ajax: endpoint; o.lineas: líneas del extracto a las que se asigna ({extracto_id, descripcion, importe});
     * o.alGuardar(concepto): después de crear (y asignar, si había líneas), para que la página recargue.
     */
    function abrir(o) {
        cfg = Object.assign({ ajax: '', lineas: [], alGuardar: null }, o);
        const f = $('#formConcepto');
        f[0].reset();
        f.removeClass('was-validated');
        f.find('details.ui-mas-opciones').prop('open', false);
        codigoTocado = false;
        const primera = cfg.lineas[0];
        const nombre = primera ? String(primera.descripcion || '').trim().substring(0, 120) : '';
        $('#k_concepto').val(nombre);
        $('#k_codigo').val(codigoDe(nombre));
        $(primera && Number(primera.importe) > 0 ? '#k_nat_propio' : '#k_nat_gasto').prop('checked', true);
        $('#kLinea').toggleClass('d-none', !cfg.lineas.length)
            .text(cfg.lineas.length === 1 ? `Se asigna a la línea "${primera ? primera.descripcion : ''}" y a las sin concepto con la misma descripción.`
                                          : `Se asigna a las ${cfg.lineas.length} líneas marcadas.`);
        sincronizar();
        $('#modalConcepto').modal('show');
    }

    $(function () {
        $('#formConcepto input[name="naturaleza"], #k_rol').on('change', sincronizar);
        $('#k_concepto').on('input', function () { if (!codigoTocado) $('#k_codigo').val(codigoDe(this.value)); });
        $('#k_codigo').on('input', () => { codigoTocado = true; });
        $('#modalConcepto').on('shown.bs.modal', () => $('#k_concepto').trigger('focus'));

        $('#formConcepto').on('submit', function (e) {
            e.preventDefault();
            if (!$('#k_codigo').val()) $('#k_codigo').val(codigoDe($('#k_concepto').val()));
            if (!this.checkValidity()) {
                $(this).addClass('was-validated');
                if (!this.querySelector('#k_codigo').checkValidity()) $(this).find('details.ui-mas-opciones').prop('open', true);
                return;
            }
            const obj = { accion: 'agregar_concepto' };
            $(this).serializeArray().forEach(x => { obj[x.name] = x.value; });
            const btn = $('#btnGuardarConcepto').prop('disabled', true);
            Bco.pedir(cfg.ajax, obj).then((res) => {
                const k = res.data;
                $('#modalConcepto').modal('hide');
                const fin = () => { if (cfg.alGuardar) cfg.alGuardar(k); };
                if (!cfg.lineas.length) { Bco.ok('Concepto creado'); return fin(); }
                return Bco.pedir(cfg.ajax, { accion: 'clasificar', operacion: 'asignar', concepto_id: k.concepto_id, extracto_ids: cfg.lineas.map(l => l.extracto_id) })
                    .then((r) => { Swal.fire({ icon: 'success', title: 'Concepto creado y asignado', text: r.message }); })
                    .finally(fin);
            }).catch(() => {}).finally(() => btn.prop('disabled', false));
        });
    });

    return { abrir, opciones };
})();
