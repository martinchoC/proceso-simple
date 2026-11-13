/**
 * JavaScript: Importar de ARCA dentro de Mini-Gestión (minigestion_importar.js). Módulo 4, página 417.
 * Lee el archivo con ArcaMC (assets/js/arca-mis-comprobantes.js) y usa importar_arca_ajax.php:
 * combos, diagnostico, preparar, analizar, importar, borradores, confirmar_compras.
 * El cliente es el elegido en Mini-Gestión (#selCliente); el botón aparece solo si el cliente tiene empresa en el ERP
 * (y no es el propio estudio, que importa desde Gestión).
 */
$(function () {
    const URL = 'importar_arca_ajax.php';
    const modalEl = document.getElementById('modalImportarArca');
    if (!modalEl) { return; }
    const modal = new bootstrap.Modal(modalEl);
    let archivo = null;          // {sentido, cuit, filas, nombre}
    let clientes = [];           // clientes que admiten importación (ia_clientes)

    function esc(t) { return $('<div>').text(t == null ? '' : String(t)).html(); }
    function plata(v) { return Fmt.moneda(Number(v || 0)); }
    function clienteId() { return String($('#selCliente').val() || ''); }
    function cliente() { return clientes.find(function (x) { return String(x.cliente_id) === clienteId(); }) || null; }
    function post(accion, datos) {
        return $.ajax({ url: URL + '?accion=' + accion, method: 'POST', data: datos, dataType: 'json',
                        headers: { 'X-CSRF-Token': window.IA_CSRF || '' } });
    }
    function base() {
        return { cliente_id: clienteId(), sentido: archivo.sentido, cuit_archivo: archivo.cuit,
                 filas: JSON.stringify(archivo.filas), plantilla_id: $('#iaPlantilla').val() || 0 };
    }
    function falla(r) { Swal.fire('No se pudo completar', (r && r.message) || 'Fallo en la comunicación con el servidor.', 'warning'); }

    // ---- clientes que admiten importación y plantillas
    $.getJSON(URL, { accion: 'combos' }, function (r) {
        if (!r.success) { return; } // sin acceso a la importación: el botón no aparece
        clientes = r.data.clientes || [];
        const $p = $('#iaPlantilla').empty().append($('<option>').val('').text('— Sin plantilla —'));
        (r.data.plantillas || []).forEach(function (p) { $p.append($('<option>').val(p.plantilla_id).text(p.nombre + ' (' + p.empresa + ')')); });
        mostrarBoton();
    });

    function mostrarBoton() { $('#btnImportarArca').toggleClass('d-none', !cliente()); }
    $('#selCliente').on('change', mostrarBoton);

    $('#btnImportarArca').on('click', function () {
        const c = cliente();
        if (!c) { return; }
        archivo = null;
        $('#iaArchivo').val('');
        $('#iaArchivoInfo').empty();
        $('#iaCardPreview, #iaCardPrerreq').addClass('d-none');
        $('#iaClienteNombre').text(c.razon_social);
        $('#iaClienteInfo').text('CUIT ' + c.cuit + ' · Empresa ' + c.empresa_nombre
            + (c.escritura_ventas ? '' : ' · sin permiso de escritura en ventas (no se pueden importar Emitidos)')
            + (c.escritura_compras ? '' : ' · sin permiso de escritura en compras (no se pueden importar Recibidos)'));
        cargarBorradores();
        modal.show();
    });

    // Al cerrar, Mini-Gestión vuelve a leer los comprobantes del cliente (lo importado o confirmado)
    let huboCambios = false;
    modalEl.addEventListener('hidden.bs.modal', function () {
        if (huboCambios) { huboCambios = false; $('#btnActualizarDatos').trigger('click'); }
    });

    // ---- archivo
    $('#iaArchivo').on('change', function (e) {
        const f = e.target.files[0];
        archivo = null;
        $('#iaCardPreview, #iaCardPrerreq').addClass('d-none');
        $('#iaArchivoInfo').empty();
        if (!f) { return; }
        ArcaMC.leerArchivo(f).then(function (r) {
            archivo = r;
            $('#iaArchivoInfo').html('<span class="badge text-bg-info">' + esc(r.sentido === 'EMITIDOS' ? 'Comprobantes Emitidos' : 'Comprobantes Recibidos')
                + '</span> CUIT ' + esc(r.cuit || '¿?') + ' · ' + r.filas.length + ' comprobantes');
            diagnosticar();
        }).catch(function (err) {
            $('#iaArchivo').val('');
            Swal.fire('No se pudo leer el archivo', err.message || String(err), 'warning');
        });
    });

    // ---- prerrequisitos de la empresa del cliente
    function pintarDiagnostico(d, pasos) {
        const items = [];
        if (d.alicuotas && d.alicuotas.length) { items.push('Alícuotas de IVA: ' + d.alicuotas.join(', ')); }
        if (d.tipos && d.tipos.length) { items.push('Tipos de comprobante con código ARCA: ' + d.tipos.join(', ')); }
        if (d.sucursal) { items.push('Sucursal'); }
        if (d.pvs && d.pvs.length) { items.push('Puntos de venta: ' + d.pvs.join(', ')); }
        if (d.pv_tipos && d.pv_tipos.length) { items.push('Tipos habilitados en el punto de venta: ' + d.pv_tipos.join(', ')); }
        if (d.concepto_venta) { items.push('Concepto de venta (sin stock)'); }
        const $b = $('#iaPrerreq').empty();
        (pasos || []).forEach(function (p) {
            $b.append($('<div>').append($('<i>').addClass('fas me-1 ' + (p.ok ? 'fa-check-circle text-success' : 'fa-times-circle text-danger')))
                .append($('<strong>').text(p.paso + ': ')).append(document.createTextNode(p.detalle || '')));
        });
        if (d.ok) {
            $b.append($('<div class="text-success">').html('<i class="fas fa-check me-1"></i>La empresa tiene todo lo necesario para este archivo.'));
        } else {
            const $ul = $('<ul class="mb-0">');
            items.forEach(function (t) { $ul.append($('<li>').text(t)); });
            $b.append($('<div class="text-warning-emphasis">').text('Le falta a la empresa del cliente:')).append($ul);
        }
        $('#iaPreparar').toggleClass('d-none', !!d.ok);
        $('#iaCardPrerreq').removeClass('d-none');
    }

    function diagnosticar() {
        if (!archivo || !clienteId()) { return; }
        $.ajax({ url: URL, method: 'POST', data: $.extend({ accion: 'diagnostico' }, base()), dataType: 'json' }).done(function (r) {
            if (!r.success) { falla(r); return; }
            pintarDiagnostico(r.data, []);
            analizar();
        }).fail(function () { falla(); });
    }

    $('#iaPreparar').on('click', function () {
        const $btn = $(this).prop('disabled', true);
        post('preparar', base()).done(function (r) {
            if (!r.data) { falla(r); return; }
            pintarDiagnostico(r.data.diagnostico, r.data.pasos);
            if (r.success) { analizar(); }
        }).fail(function () { falla(); }).always(function () { $btn.prop('disabled', false); });
    });

    // ---- previsualización
    const ESTADOS = { listo: 'success', nuevo_proveedor: 'success', duplicado: 'secondary', error: 'danger' };
    function filaPreview(it) {
        const emit = archivo.sentido === 'EMITIDOS';
        const numero = emit ? (String(it.pv).padStart(5, '0') + '-' + String(it.nro).padStart(8, '0')) : (it.pv_formato + '-' + it.nro_formato);
        const contraparte = emit ? it.cliente_nombre : (it.razon_social || it.denominacion_arca);
        const avisos = (it.avisos || []).join(' ');
        return '<tr><td>' + esc(emit ? Fmt.fecha(it.fecha) : it.f_emision_formato) + '</td><td>' + esc(emit ? it.tipo_texto : it.tipo_raw) + '</td>'
            + '<td>' + esc(numero) + '</td><td>' + esc(contraparte) + '</td><td class="text-end">' + plata(it.total) + '</td>'
            + '<td><span class="badge text-bg-' + (ESTADOS[it.estado] || 'light') + '">' + esc(it.estado) + '</span></td>'
            + '<td class="small">' + esc(it.mensaje || '') + (avisos ? '<br><span class="text-warning-emphasis">' + esc(avisos) + '</span>' : '') + '</td></tr>';
    }

    function analizar() {
        $.ajax({ url: URL, method: 'POST', data: $.extend({ accion: 'analizar' }, base()), dataType: 'json' }).done(function (r) {
            if (!r.success) { falla(r); return; }
            const d = r.data;
            $('#iaResumen').text((d.sentido === 'EMITIDOS' ? 'Emitidos' : 'Recibidos') + ': ' + d.listos + ' listos · ' + d.duplicados + ' ya cargados · '
                + d.errores + ' con error' + (d.con_avisos ? ' · ' + d.con_avisos + ' con aviso' : ''));
            $('#iaTabla tbody').html((d.items || []).map(filaPreview).join(''));
            $('#iaImportar').prop('disabled', !(d.listos > 0 && d.diagnostico && d.diagnostico.ok));
            $('#iaCardPreview').removeClass('d-none');
        }).fail(function () { falla(); });
    }

    $('#iaImportar').on('click', function () {
        Swal.fire({ title: 'Importar comprobantes', text: archivo.sentido === 'EMITIDOS'
                ? 'Las ventas entran Confirmadas, con su CAE, comprobante y asiento.'
                : 'Las compras entran en Borrador: después se confirman abajo.',
            showCancelButton: true, confirmButtonText: 'Importar', cancelButtonText: 'Cancelar' }).then(function (q) {
            if (!q.isConfirmed) { return; }
            Swal.fire({ title: 'Importando...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
            post('importar', base()).done(function (r) {
                if (!r.success) { falla(r); return; }
                huboCambios = true;
                const d = r.data || {};
                const errores = Array.isArray(d.errores) ? d.errores : [];
                Swal.fire({ icon: 'success', title: 'Importación terminada', html: esc(r.message)
                    + (errores.length ? '<div class="text-start small mt-2"><strong>Con error:</strong><br>' + errores.map(esc).join('<br>') + '</div>' : '') });
                analizar();
                cargarBorradores();
            }).fail(function () { falla(); });
        });
    });

    // ---- compras en Borrador + confirmación masiva
    function cargarBorradores() {
        const id = clienteId();
        if (!id) { $('#iaCardBorradores').addClass('d-none'); return; }
        $.getJSON(URL, { accion: 'borradores', cliente_id: id }, function (r) {
            if (!r.success) { $('#iaCardBorradores').addClass('d-none'); return; }
            const filas = r.data || [];
            const listas = filas.filter(function (f) { return f.lista; }).length;
            $('#iaBorrResumen').text('(' + filas.length + ': ' + listas + ' para confirmar, ' + (filas.length - listas) + ' con Otros Tributos sin detallar)');
            $('#iaTablaBorr tbody').html(filas.map(function (f) {
                return '<tr><td>' + (f.lista ? '<input type="checkbox" class="ia-borr" value="' + parseInt(f.factura_proveedor_id, 10) + '">' : '') + '</td>'
                    + '<td>' + esc(Fmt.fecha(f.f_emision)) + '</td><td>' + esc(f.comprobante_tipo) + '</td>'
                    + '<td>' + esc(String(f.comprobante_pv).padStart(5, '0') + '-' + String(f.comprobante_nro).padStart(8, '0')) + '</td>'
                    + '<td>' + esc(f.entidad_nombre) + '</td><td class="text-end">' + plata(f.total) + '</td>'
                    + '<td class="text-end">' + (Number(f.otros_impuestos) ? plata(f.otros_impuestos) : '') + '</td>'
                    + '<td class="small">' + (f.lista ? '<span class="badge text-bg-light">Borrador</span>' : '<span class="text-warning-emphasis">' + esc(f.motivo) + '</span>') + '</td></tr>';
            }).join('') || '<tr><td colspan="8" class="text-center text-muted py-2">No hay compras en Borrador.</td></tr>');
            $('#iaBorrTodas').prop('checked', false);
            actualizarConfirmar();
            $('#iaCardBorradores').removeClass('d-none');
        });
    }
    function actualizarConfirmar() { $('#iaConfirmar').prop('disabled', !$('.ia-borr:checked').length); }
    $('#iaBorrRecargar').on('click', cargarBorradores);
    $('#iaBorrTodas').on('change', function () { $('.ia-borr').prop('checked', this.checked); actualizarConfirmar(); });
    $(document).on('change', '.ia-borr', actualizarConfirmar);

    $('#iaConfirmar').on('click', function () {
        const ids = $('.ia-borr:checked').map(function () { return parseInt(this.value, 10); }).get();
        Swal.fire({ title: 'Confirmar ' + ids.length + ' compras', text: 'Se aprueban y confirman con su asiento. No se puede deshacer en lote.',
            showCancelButton: true, confirmButtonText: 'Confirmar', cancelButtonText: 'Cancelar' }).then(function (q) {
            if (!q.isConfirmed) { return; }
            Swal.fire({ title: 'Confirmando...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
            post('confirmar_compras', { cliente_id: clienteId(), ids: JSON.stringify(ids) }).done(function (r) {
                if (!r.success) { falla(r); return; }
                huboCambios = true;
                const errores = (r.data.items || []).filter(function (i) { return i.estado !== 'confirmada'; });
                Swal.fire({ icon: errores.length ? 'warning' : 'success', title: 'Listo', html: esc(r.message)
                    + (errores.length ? '<div class="text-start small mt-2">' + errores.map(function (i) { return '#' + i.factura_proveedor_id + ': ' + esc(i.mensaje); }).join('<br>') + '</div>' : '') });
                cargarBorradores();
            }).fail(function () { falla(); });
        });
    });
});
