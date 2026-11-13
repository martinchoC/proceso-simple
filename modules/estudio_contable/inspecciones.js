/**
 * JavaScript: Inspecciones de clientes (inspecciones.js)
 * Módulo 4: Estudio Contable. Usa inspecciones_ajax.php
 */
$(function () {
    const URL = 'inspecciones_ajax.php';
    const modal = new bootstrap.Modal(document.getElementById('modalInsp'));
    const ESTADOS = {
        ABIERTA: 'Abierta', EN_DESCARGO: 'En descargo', EN_APELACION: 'En apelación', PLAN_PAGOS: 'Plan de pagos',
        CERRADA_SIN_AJUSTE: 'Cerrada sin ajuste', CERRADA_CON_AJUSTE: 'Cerrada con ajuste', PRESCRIPTA: 'Prescripta'
    };
    let combos = null;
    let inspActual = 0;

    const nf = Fmt.numberFormat({ minimumFractionDigits: 2, maximumFractionDigits: 2 });
    function esc(t) { return $('<div>').text(t == null ? '' : t).html(); }
    function fecha(f) { return f ? Fmt.fecha(f) : '—'; }
    function opts($s, items, v, t, vacio) {
        $s.empty();
        if (vacio !== undefined) { $s.append($('<option>').val('').text(vacio)); }
        items.forEach(function (it) { $s.append($('<option>').val(it[v]).text(it[t])); });
    }
    function simples(arr, mapa) { return arr.map(function (x) { return { v: x, t: (mapa && mapa[x]) || x }; }); }
    function aviso(r, okTxt) {
        if (r.success) { Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: okTxt || r.message, showConfirmButton: false, timer: 2200 }); }
        else { Swal.fire('Atención', r.message || 'No se pudo completar la operación.', 'warning'); }
    }

    function cargarCombos() {
        return $.getJSON(URL, { accion: 'combos' }).then(function (r) {
            if (!r.success) { return; }
            combos = r.data;
            opts($('#filtroCliente'), combos.clientes, 'cliente_id', 'razon_social', '-- Todos --');
            // Cliente activo del estudio (estudio_cliente_activo.js): ?cliente_id=N o el último elegido
            const activo = window.EstudioCliente ? EstudioCliente.inicial() : 0;
            if (activo > 0 && $('#filtroCliente option[value="' + activo + '"]').length) {
                $('#filtroCliente').val(String(activo));
            }
            opts($('#filtroEstado'), simples(combos.estados, ESTADOS), 'v', 't', '-- Todos --');
            opts($('#i_cliente'), combos.clientes, 'cliente_id', 'razon_social');
            opts($('#i_organismo'), simples(combos.organismos), 'v', 't');
            opts($('#i_tipo'), simples(combos.tipos), 'v', 't');
            opts($('#i_estado'), simples(combos.estados, ESTADOS), 'v', 't');
            opts($('#i_impuesto'), combos.obligaciones.map(function (o) { return { id: o.impuesto_obligacion_id, t: o.codigo_oficial + ' - ' + o.nombre }; }), 'id', 't', '— Ninguno —');
            opts($('#i_resp'), combos.usuarios, 'usuario_id', 'usuario_nombre', '— Sin asignar —');
            opts($('#h_tipo'), simples(combos.tipos_hito), 'v', 't');
        });
    }

    function listar() {
        const params = { accion: 'listar', cliente_id: $('#filtroCliente').val(), estado_proceso: $('#filtroEstado').val() };
        if ($('#filtroAbiertas').is(':checked') && !params.estado_proceso) { params.solo_abiertas = 1; }
        $.getJSON(URL, params).then(function (r) {
            const $tb = $('#tablaInsp tbody').empty();
            if (!r.success) { $tb.append('<tr><td colspan="9" class="text-center text-danger py-3">' + esc(r.message) + '</td></tr>'); return; }
            $('#kpiAbiertas').text(r.data.kpis.abiertas);
            $('#kpiVencidos').text(r.data.kpis.plazos_vencidos);
            $('#kpiMonto').text('$ ' + nf.format(r.data.kpis.monto_en_juego));
            if (!r.data.items.length) { $tb.append('<tr><td colspan="9" class="text-center text-muted py-3">Sin inspecciones.</td></tr>'); return; }
            r.data.items.forEach(function (i) {
                const plazo = i.plazos_vencidos > 0
                    ? '<span class="badge text-bg-danger">' + i.plazos_vencidos + ' vencido(s)</span>'
                    : (i.proximo_plazo ? esc(fecha(i.proximo_plazo)) : '—');
                $tb.append('<tr><td>' + esc(i.razon_social) + '</td>' +
                    '<td>' + esc(i.organismo) + '<br><small class="text-muted">' + esc(i.numero_acta || '') + '</small></td>' +
                    '<td>' + esc(fecha(i.f_inicio)) + '</td><td>' + esc(ESTADOS[i.estado_proceso] || i.estado_proceso) + '</td>' +
                    '<td class="text-end">' + nf.format(i.monto_reclamado) + '</td><td class="text-end">' + nf.format(i.monto_pagado) + '</td>' +
                    '<td>' + plazo + '</td><td>' + esc(i.responsable || '') + '</td>' +
                    '<td class="text-end"><button class="btn btn-sm btn-outline-primary btn-abrir" data-id="' + parseInt(i.inspeccion_id, 10) + '"><i class="fas fa-folder-open"></i></button></td></tr>');
            });
        });
    }

    function limpiarForm() {
        $('#formInsp')[0].reset();
        $('#i_id').val(0);
        $('#i_inicio').val(new Date().toISOString().slice(0, 10));
        $('#bloqueHitos').addClass('d-none');
        $('#btnAnularInsp').addClass('d-none');
        $('#tbHitos').empty();
        inspActual = 0;
    }

    function pintarHitos(hitos) {
        const $tb = $('#tbHitos').empty();
        if (!hitos.length) { $tb.append('<tr><td colspan="6" class="text-center text-muted">Sin hitos.</td></tr>'); return; }
        hitos.forEach(function (h) {
            $tb.append('<tr><td>' + esc(fecha(h.f_hito)) + '</td><td>' + esc(h.tipo_hito) + '</td><td>' + esc(h.descripcion) +
                (h.tarea_id ? ' <span class="badge text-bg-info">tarea</span>' : '') + '</td><td>' + esc(fecha(h.f_limite)) + '</td>' +
                '<td class="text-end">' + (h.importe !== null ? nf.format(h.importe) : '') + '</td>' +
                '<td class="text-center"><input type="checkbox" class="form-check-input chk-cumplido" data-id="' + parseInt(h.hito_id, 10) + '" data-funcion="editar"' + (parseInt(h.cumplido, 10) ? ' checked' : '') + '></td></tr>');
        });
    }

    function abrir(id) {
        $.getJSON(URL, { accion: 'obtener', inspeccion_id: id }).then(function (r) {
            if (!r.success) { aviso(r); return; }
            limpiarForm();
            const i = r.data;
            inspActual = parseInt(i.inspeccion_id, 10);
            $('#i_id').val(inspActual);
            $('#i_cliente').val(i.cliente_id); $('#i_organismo').val(i.organismo); $('#i_tipo').val(i.tipo);
            $('#i_acta').val(i.numero_acta || ''); $('#i_impuesto').val(i.impuesto_obligacion_id || ''); $('#i_estado').val(i.estado_proceso);
            $('#i_inicio').val(i.f_inicio); $('#i_cierre').val(i.f_cierre || '');
            $('#i_pdesde').val(i.periodo_desde || ''); $('#i_phasta').val(i.periodo_hasta || '');
            $('#i_reclamado').val(i.monto_reclamado); $('#i_aceptado').val(i.monto_aceptado); $('#i_pagado').val(i.monto_pagado);
            $('#i_resp').val(i.usuario_r_id || ''); $('#i_obs').val(i.observaciones || '');
            $('#h_fecha').val(new Date().toISOString().slice(0, 10));
            pintarHitos(i.hitos || []);
            $('#bloqueHitos').removeClass('d-none');
            $('#btnAnularInsp').removeClass('d-none');
            $('#modalInspTitulo').text('Inspección #' + inspActual);
            modal.show();
        });
    }

    $('#btnNuevaInsp').on('click', function () {
        limpiarForm();
        $('#modalInspTitulo').text('Nueva inspección');
        modal.show();
    });
    $('#tablaInsp').on('click', '.btn-abrir', function () { abrir($(this).data('id')); });
    $('#filtroCliente, #filtroEstado, #filtroAbiertas').on('change', listar);
    $('#filtroCliente').on('change', function () {
        if (window.EstudioCliente) { EstudioCliente.guardar($(this).val()); }
    });

    $('#formInsp').on('submit', function (e) {
        e.preventDefault();
        $.post(URL + '?accion=guardar', $(this).serialize(), null, 'json').done(function (r) {
            aviso(r);
            if (r.success) {
                listar();
                if (!inspActual) { modal.hide(); } // al crear se cierra; al editar sigue abierta para cargar hitos
            }
        }).fail(function () { Swal.fire('Error', 'Fallo en la comunicación con el servidor.', 'error'); });
    });

    $('#btnAgregarHito').on('click', function () {
        if (!inspActual) { return; }
        $.post(URL + '?accion=guardar_hito', {
            inspeccion_id: inspActual, tipo_hito: $('#h_tipo').val(), f_hito: $('#h_fecha').val(), f_limite: $('#h_limite').val(),
            descripcion: $('#h_desc').val(), importe: $('#h_importe').val(), crear_tarea: $('#h_tarea').is(':checked') ? 1 : 0,
            usuario_r_id: $('#i_resp').val()
        }, null, 'json').done(function (r) {
            aviso(r);
            if (r.success) { $('#h_desc, #h_limite, #h_importe').val(''); abrirHitos(); listar(); }
        });
    });
    function abrirHitos() {
        $.getJSON(URL, { accion: 'obtener', inspeccion_id: inspActual }).then(function (r) { if (r.success) { pintarHitos(r.data.hitos || []); } });
    }

    $('#tbHitos').on('change', '.chk-cumplido', function () {
        $.post(URL + '?accion=cumplir_hito', { hito_id: $(this).data('id'), cumplido: this.checked ? 1 : 0 }, null, 'json').done(function (r) {
            if (!r.success) { aviso(r); }
            listar();
        });
    });

    $('#btnAnularInsp').on('click', function () {
        Swal.fire({ title: '¿Anular la inspección?', text: 'Deja de verse en el seguimiento.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Anular' })
            .then(function (c) {
                if (!c.isConfirmed) { return; }
                $.post(URL + '?accion=anular', { inspeccion_id: inspActual }, null, 'json').done(function (r) {
                    aviso(r);
                    if (r.success) { modal.hide(); listar(); }
                });
            });
    });

    cargarCombos().then(listar);
});
