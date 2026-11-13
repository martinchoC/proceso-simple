/**
 * comprobantes_consulta.js — Consulta de Comprobantes (página 102). Solo lectura.
 */
$(function () {
    'use strict';

    const UI = window.ComprobantesUI;
    const URL_AJAX = 'comprobantes_consulta_ajax.php';
    let tabla = null;
    let tipos = [];
    let entidadesBuscadas = [];

    function opcion(valor, texto) {
        return $('<option>').val(valor).text(texto);
    }

    function cargarTipos() {
        const grupo = $('#fGrupo').val();
        const $t = $('#fTipo').empty().append(opcion('', 'Todos'));
        tipos.filter(function (t) { return !grupo || String(t.comprobante_grupo_id) === grupo; })
             .forEach(function (t) { $t.append(opcion(t.comprobante_tipo_id, t.codigo + ' - ' + t.comprobante_tipo)); });
    }

    function valoresPorDefecto() {
        $('#formFiltros')[0].reset();
        $('#fEntidad').val('');
        $('#fDesde').val(UI.hoyMenosDias(90));
        cargarTipos();
    }

    function filtros() {
        return {
            accion: 'listar',
            empresa_idx: EMPRESA_ID,
            desde: $('#fDesde').val(),
            hasta: $('#fHasta').val(),
            grupo_id: $('#fGrupo').val(),
            tipo_id: $('#fTipo').val(),
            estado_id: $('#fEstado').val(),
            entidad_id: $('#fEntidad').val(),
            sucursal_id: $('#fSucursal').val(),
            pv: $('#fPv').val(),
            numero: $('#fNumero').val(),
            moneda_id: $('#fMoneda').val(),
            texto: $('#fTexto').val(),
            solo_con_saldo: $('#fSoloSaldo').is(':checked') ? 1 : 0
        };
    }

    function actualizarTotales() {
        let total = 0, saldo = 0;
        tabla.rows({ search: 'applied' }).data().each(function (r) {
            total += r.importe_total * r.tipo_cambio;
            if (r.saldo !== null) saldo += r.saldo * r.tipo_cambio;
        });
        $('#totTotal').text(UI.moneda(total));
        $('#totSaldo').text(UI.moneda(saldo));
    }

    function buscar() {
        $('#btnBuscar').prop('disabled', true);
        $.getJSON(URL_AJAX, filtros()).done(function (res) {
            $('#avisoListado').toggleClass('d-none', !res.message).text(res.message || '');
            const filas = res.success ? res.data : [];
            if (tabla) {
                tabla.clear().rows.add(filas).draw();
                return;
            }
            tabla = $('#tablaComprobantes').DataTable(UI.opcionesTabla({
                data: filas,
                order: [[0, 'desc']],
                columns: [
                    { data: 'f_emision', render: function (v, t) { return t === 'display' ? UI.fecha(v) : v; } },
                    { data: 'f_vto', render: function (v, t) { return t === 'display' ? UI.fecha(v) : (v || ''); } },
                    { data: null, render: function (r, t) {
                        const n = UI.numero(r);
                        return t === 'display' ? UI.esc(n) + '<div class="text-muted">' + UI.esc(r.comprobante_tipo) + '</div>' : n;
                    } },
                    { data: 'comprobante_grupo', defaultContent: '' },
                    { data: 'entidad_nombre', defaultContent: '', render: function (v, t, r) {
                        const nom = (r && r.entidad_fantasia) || v || '';
                        return t === 'display' ? UI.esc(nom) + (r.cuit ? '<div class="text-muted">' + UI.esc(r.cuit) + '</div>' : '') : nom + ' ' + (r.cuit || '');
                    } },
                    { data: 'sucursal_nombre', defaultContent: '' },
                    { data: 'importe_total', className: 'text-end', render: function (v, t, r) { return t === 'display' ? UI.moneda(v, r.moneda_simbolo) : v; } },
                    { data: 'saldo', className: 'text-end', render: function (v, t, r) {
                        if (v === null) return t === 'display' ? '<span class="text-muted">-</span>' : '';
                        return t === 'display' ? '<span class="' + (v > 0.009 ? 'text-danger fw-semibold' : 'text-muted') + '">' + UI.moneda(v, r.moneda_simbolo) + '</span>' : v;
                    } },
                    { data: 'estado', className: 'text-center', render: function (v, t, r) { return t === 'display' ? UI.badgeEstado(r) : v; } },
                    { data: 'comprobante_id', orderable: false, className: 'text-center no-export', render: function (id) {
                        return '<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1 btn-ver" data-id="' + id + '" title="Ver detalle"><i class="fas fa-eye"></i></button>';
                    } }
                ],
                drawCallback: function () { if (tabla) actualizarTotales(); }
            }));
            actualizarTotales();
        }).fail(function (xhr) {
            const msg = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Error de conexión con el servidor.';
            $('#avisoListado').removeClass('d-none').text(msg);
        }).always(function () {
            $('#btnBuscar').prop('disabled', false);
        });
    }

    // Maestros de filtros
    $.getJSON(URL_AJAX, { accion: 'maestros', empresa_idx: EMPRESA_ID }).done(function (res) {
        if (!res.success) return;
        const d = res.data;
        d.grupos.forEach(function (g) { $('#fGrupo').append(opcion(g.comprobante_grupo_id, g.comprobante_grupo)); });
        d.sucursales.forEach(function (s) { $('#fSucursal').append(opcion(s.sucursal_id, s.sucursal_nombre)); });
        d.estados.forEach(function (e) { $('#fEstado').append(opcion(e.estado_registro_id, e.estado_registro)); });
        d.monedas.forEach(function (m) { $('#fMoneda').append(opcion(m.moneda_id, m.codigo)); });
        tipos = d.tipos;
        cargarTipos();
    });

    // Entidad: búsqueda por texto, se resuelve el id al elegir de la lista
    let temporizador = null;
    $('#fEntidadTexto').on('input', function () {
        const texto = $(this).val().trim();
        const elegida = entidadesBuscadas.find(function (e) { return e.etiqueta === texto; });
        $('#fEntidad').val(elegida ? elegida.entidad_id : '');
        if (elegida || texto.length < 3) return;
        clearTimeout(temporizador);
        temporizador = setTimeout(function () {
            $.getJSON(URL_AJAX, { accion: 'buscar_entidades', empresa_idx: EMPRESA_ID, q: texto }).done(function (res) {
                if (!res.success) return;
                entidadesBuscadas = res.data.map(function (e) {
                    e.etiqueta = e.entidad_nombre + (e.cuit ? ' (' + e.cuit + ')' : '');
                    return e;
                });
                const $l = $('#fEntidadLista').empty();
                entidadesBuscadas.forEach(function (e) { $l.append($('<option>').val(e.etiqueta)); });
            });
        }, 300);
    });

    $('#fGrupo').on('change', cargarTipos);
    $('#btnBuscar').on('click', buscar);
    $('#formFiltros').on('keyup', 'input', function (e) { if (e.key === 'Enter') buscar(); });
    $('#btnLimpiar').on('click', function () { valoresPorDefecto(); buscar(); });
    $('#tablaComprobantes tbody').on('click', '.btn-ver', function () {
        UI.mostrarDetalle(URL_AJAX, { accion: 'obtener', empresa_idx: EMPRESA_ID, comprobante_id: $(this).data('id') });
    });

    valoresPorDefecto();
    buscar();
});
