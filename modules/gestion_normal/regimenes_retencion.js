// Regímenes de Retención (página 124 / 624): catálogo global con vigencia y escala.
// Lo usa el cálculo de retenciones de las órdenes de pago (retenciones_practicadas_core_model.php).
$(function () {
    var URL_AJAX = 'regimenes_retencion_ajax.php';
    var catalogos = null;
    var tabla = null;

    function esc(t) { return $('<div>').text(t == null ? '' : t).html(); }
    function fecha(d) { return d ? Fmt.fecha(d) : ''; }
    function pct(v) { return v === null || v === '' || v === undefined ? '<span class="text-warning">a confirmar</span>' : esc(parseFloat(v)) + ' %'; }
    function monto(v) { return Fmt.numero((parseFloat(v) || 0), { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function error(r, def) { Swal.fire('Error', (r && (r.message || r.error)) || def, 'error'); }

    function opciones(sel, lista, valor, texto) {
        sel.empty();
        $.each(lista, function (k, v) {
            var val = valor ? v[valor] : k, txt = texto ? v[texto] : v;
            sel.append($('<option>').val(val).text(txt));
        });
    }

    function esIibb() { return $('#impuesto_tipo_id option:selected').data('iibb') == 1; }
    function alternarIibb() { $('.rreg-iibb').toggleClass('d-none', !esIibb()); }
    function alternarEscala() { $('#bloqueEscala').toggleClass('d-none', !$('#usa_escala').is(':checked')); }

    function cargarCatalogos(cb) {
        if (catalogos) return cb();
        $.getJSON(URL_AJAX, { accion: 'catalogos' }, function (r) {
            if (!r || !r.success) return error(r, 'No se pudieron leer los catálogos.');
            catalogos = r.data;
            var sel = $('#impuesto_tipo_id').empty();
            catalogos.impuestos.forEach(function (i) {
                sel.append($('<option>').val(i.impuesto_tipo_id).attr('data-iibb', i.es_iibb).text(i.impuesto_tipo));
            });
            opciones($('#jurisdiccion_id'), catalogos.jurisdicciones, 'jurisdiccion_id', 'jurisdiccion_nombre');
            opciones($('#base_calculo'), catalogos.bases);
            opciones($('#tipo_minimo'), catalogos.tipos_minimo);
            opciones($('#modo_minimo'), catalogos.modos_minimo);
            cb();
        });
    }

    function filaTramo(t) {
        t = t || {};
        var v = function (x) { return x === null || x === undefined ? '' : x; };
        $('#tablaEscala tbody').append(
            '<tr>' +
            '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm esc-desde" value="' + v(t.desde) + '"></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm esc-hasta" value="' + v(t.hasta) + '"></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm esc-fijo" value="' + v(t.importe_fijo) + '"></td>' +
            '<td><input type="number" step="0.0001" min="0" max="100" class="form-control form-control-sm esc-alic" value="' + v(t.alicuota_excedente) + '"></td>' +
            '<td class="text-center"><button type="button" class="btn btn-xs btn-outline-danger esc-quitar" title="Quitar"><i class="fas fa-times"></i></button></td>' +
            '</tr>');
    }

    function botonesHtml(row) {
        return (row.botones || []).map(function (b) {
            var clase = b.bg_clase && b.text_clase ? b.bg_clase + ' ' + b.text_clase : (b.color_clase || 'btn-outline-primary');
            return '<button type="button" class="btn btn-sm me-1 ' + esc(clase) + ' btn-accion" title="' + esc(b.descripcion || b.nombre_funcion) +
                '" data-id="' + row.regimen_id + '" data-accion="' + esc(b.accion_js) + '" data-confirmable="' + b.es_confirmable +
                '" data-nombre="' + esc(row.codigo + ' - ' + row.regimen) + '"><i class="' + esc(b.icono_clase || '') + '"></i></button>';
        }).join('') || '<span class="text-muted small">Sin acciones</span>';
    }

    function iniciarTabla() {
        tabla = $('#tablaRegimenes').DataTable({
            ajax: { url: URL_AJAX, data: { accion: 'listar' }, dataSrc: function (r) { return r && r.success ? r.data : []; } },
            order: [],
            pageLength: 25,
            language: { url: window.APP_ASSETS_URL + 'js/datatables-i18n-1.13.6/es-ES.json' },
            columns: [
                { data: 'impuesto_tipo', render: esc },
                { data: 'jurisdiccion_nombre', render: function (d) { return d ? esc(d) : '<span class="text-muted">Nacional</span>'; } },
                { data: 'codigo', render: esc },
                { data: 'regimen', render: function (d, t, r) { return esc(d) + (parseInt(r.usa_escala) === 1 ? ' <span class="badge bg-info">escala (' + r.tramos + ')</span>' : ''); } },
                { data: 'alicuota_inscripto', className: 'text-end', render: function (d, t, r) { return parseInt(r.usa_escala) === 1 ? 'escala' : pct(d); } },
                { data: 'alicuota_no_inscripto', className: 'text-end', render: pct },
                { data: 'minimo_no_sujeto', className: 'text-end', render: monto },
                { data: 'tipo_minimo', render: function (d, t, r) {
                    return esc(catalogos && catalogos.tipos_minimo[d] ? catalogos.tipos_minimo[d] : d) +
                        '<div class="small text-muted">' + esc(r.modo_minimo === 'UMBRAL' ? 'umbral' : 'deducible') + '</div>';
                } },
                { data: 'f_desde', render: function (d, t, r) { return fecha(d) + ' → ' + (r.f_hasta ? fecha(r.f_hasta) : '…'); } },
                { data: 'estado_registro', className: 'text-center', render: function (d, t, r) {
                    return '<span class="badge ' + (parseInt(r.tabla_estado_registro_id) === 1 ? 'bg-success' : 'bg-secondary') + '">' + esc(d) + '</span>';
                } },
                { data: null, orderable: false, searchable: false, className: 'text-center', render: function (d, t, r) { return botonesHtml(r); } }
            ]
        });
    }

    function abrir(datos) {
        cargarCatalogos(function () {
            var f = $('#formRegimen')[0];
            f.reset();
            $('#tablaEscala tbody').empty();
            $('#regimen_id').val(datos ? datos.regimen_id : '');
            $('#modalRegimenLabel').text(datos ? 'Editar régimen ' + datos.codigo : 'Nuevo régimen de retención');
            if (datos) {
                ['impuesto_tipo_id', 'jurisdiccion_id', 'codigo', 'regimen', 'base_calculo', 'alicuota_inscripto', 'alicuota_no_inscripto',
                 'minimo_no_sujeto', 'minimo_retencion', 'tipo_minimo', 'modo_minimo', 'f_desde', 'f_hasta', 'observaciones'].forEach(function (c) {
                    $('#' + c).val(datos[c] === null ? '' : datos[c]);
                });
                $('#usa_escala').prop('checked', parseInt(datos.usa_escala) === 1);
                (datos.escalas || []).forEach(filaTramo);
            } else {
                $('#f_desde').val(new Date().toISOString().substr(0, 10));
                $('#minimo_no_sujeto, #minimo_retencion').val(0);
            }
            alternarIibb();
            alternarEscala();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRegimen')).show();
        });
    }

    function cargarBotonAgregar() {
        $.getJSON(URL_AJAX, { accion: 'obtener_boton_agregar' }, function (r) {
            var b = r && r.success ? r.data : null;
            if (!b) return;
            var clase = b.bg_clase && b.text_clase ? b.bg_clase + ' ' + b.text_clase : (b.color_clase || 'btn-primary');
            $('#contenedor-boton-agregar').html('<button type="button" class="btn btn-sm ' + esc(clase) + '" id="btnNuevo"><i class="' +
                esc(b.icono_clase || 'fas fa-plus') + ' me-1"></i>' + esc(b.nombre_funcion) + '</button>');
        });
    }

    $(document).on('change', '#impuesto_tipo_id', alternarIibb);
    $(document).on('change', '#usa_escala', alternarEscala);
    $(document).on('click', '#btnAgregarTramo', function () { filaTramo(); });
    $(document).on('click', '.esc-quitar', function () { $(this).closest('tr').remove(); });
    $(document).on('click', '#btnNuevo', function () { abrir(null); });
    $('#btnRecargar').on('click', function () { tabla.ajax.reload(null, false); });

    $(document).on('click', '.btn-accion', function () {
        var id = $(this).data('id'), accion = $(this).data('accion'), nombre = $(this).data('nombre');
        if (accion === 'editar') {
            $.getJSON(URL_AJAX, { accion: 'obtener', regimen_id: id }, function (r) {
                if (!r || !r.success) return error(r, 'No se pudo leer el régimen.');
                abrir(r.data);
            });
            return;
        }
        var ejecutar = function () {
            $.post(URL_AJAX, { accion: 'ejecutar_accion', regimen_id: id, accion_js: accion }, function (r) {
                if (!r || !r.success) return error(r, 'No se pudo ejecutar la acción.');
                tabla.ajax.reload(null, false);
                Swal.fire({ icon: 'success', title: r.message, toast: true, position: 'top-end', timer: 1800, showConfirmButton: false });
            }, 'json').fail(function () { error(null, 'No se pudo conectar con el servidor.'); });
        };
        if (parseInt($(this).data('confirmable')) === 1) {
            Swal.fire({ title: '¿Confirmar?', text: nombre, icon: 'question', showCancelButton: true, confirmButtonText: 'Sí', cancelButtonText: 'Cancelar' })
                .then(function (res) { if (res.isConfirmed) ejecutar(); });
        } else {
            ejecutar();
        }
    });

    $('#btnGuardarRegimen').on('click', function () {
        var escalas = $('#tablaEscala tbody tr').map(function () {
            var tr = $(this);
            return { desde: tr.find('.esc-desde').val(), hasta: tr.find('.esc-hasta').val(),
                     importe_fijo: tr.find('.esc-fijo').val(), alicuota_excedente: tr.find('.esc-alic').val() };
        }).get();
        var datos = $('#formRegimen').serializeArray().reduce(function (o, x) { o[x.name] = x.value; return o; }, {});
        datos.usa_escala = $('#usa_escala').is(':checked') ? 1 : 0;
        if (!esIibb()) datos.jurisdiccion_id = '';
        datos.escalas = JSON.stringify(escalas);
        datos.accion = datos.regimen_id ? 'editar' : 'agregar';
        var btn = $(this).prop('disabled', true);
        $.post(URL_AJAX, datos, function (r) {
            if (!r || !r.success) return error(r, 'No se pudo guardar.');
            bootstrap.Modal.getInstance(document.getElementById('modalRegimen')).hide();
            tabla.ajax.reload(null, false);
            Swal.fire({ icon: 'success', title: r.message, toast: true, position: 'top-end', timer: 1800, showConfirmButton: false });
        }, 'json').fail(function () { error(null, 'No se pudo conectar con el servidor.'); })
          .always(function () { btn.prop('disabled', false); });
    });

    cargarCatalogos(function () { iniciarTabla(); });
    cargarBotonAgregar();
});
