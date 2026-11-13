/**
 * clientes_ctacte.js — Cuenta Corriente de Clientes (página 103).
 * Listado de saldos por cliente + widget de detalle (ctacte_cliente.js).
 */
$(function () {
    'use strict';

    const UI = window.ComprobantesUI;
    const detalle = CtacteCliente.crear('#detalleCtacte', { empresaId: EMPRESA_ID, mostrarCabecera: true, csrfToken: CTACTE_CSRF });
    let tabla = null;

    function filtros() {
        return {
            accion: 'saldos',
            empresa_idx: EMPRESA_ID,
            texto: $('#filtroTexto').val(),
            solo_con_saldo: $('#filtroSoloSaldo').is(':checked') ? 1 : 0,
            solo_vencidos: $('#filtroSoloVencidos').is(':checked') ? 1 : 0
        };
    }

    function importe(clase) {
        return function (v, t) {
            if (t !== 'display') return v;
            return Math.abs(v) < 0.01 ? '<span class="text-muted">-</span>' : '<span class="' + (clase || '') + '">' + UI.moneda(v) + '</span>';
        };
    }

    // Días desde el primer vencimiento impago (negativo = todavía no venció; null = sin vencimiento)
    function diasAtraso(v) {
        if (!v) return null;
        const hoy = new Date(); hoy.setHours(0, 0, 0, 0);
        return Math.round((hoy - new Date(String(v).slice(0, 10) + 'T00:00:00')) / 86400000);
    }
    function atraso(v, t, r) {
        const dias = diasAtraso(r.primer_vencimiento);
        if (t !== 'display') return dias === null ? -99999 : dias;   // sin vencimiento: al final
        if (dias === null || dias <= 0) return '<span class="text-muted">-</span>';
        return '<span class="text-danger fw-semibold">' + (dias === 1 ? '1 día' : dias + ' días') + '</span>';
    }

    function cargarSaldos() {
        $.getJSON('clientes_ctacte_ajax.php', filtros()).done(function (res) {
            $('#avisoSaldos').toggleClass('d-none', !res.message).text(res.message || '');
            const filas = res.success ? res.data : [];

            let saldo = 0, vencido = 0;
            filas.forEach(function (f) { saldo += f.saldo; vencido += f.vencido; });
            $('#kpiSaldoTotal').text(UI.moneda(saldo));
            $('#kpiClientes').text(filas.length === 1 ? '1 cliente' : filas.length + ' clientes');
            $('#kpiVencidoTotal').text(UI.moneda(vencido));

            if (tabla) {
                tabla.clear().rows.add(filas).draw();
                return;
            }
            tabla = $('#tablaSaldos').DataTable(UI.opcionesTabla({
                data: filas,
                searching: false,
                // Todo en el renglón: deuda - a favor = saldo
                order: [[4, 'desc']],
                columns: [
                    { data: 'entidad_fantasia', render: function (v, t, r) {
                        const nombre = r.entidad_fantasia || r.entidad_nombre || '';
                        return t === 'display' ? '<span class="fw-medium">' + UI.esc(nombre) + '</span>' : nombre;
                    } },
                    { data: 'cuit', defaultContent: '', className: 'text-nowrap' },
                    { data: 'deuda', className: 'text-end ui-num', render: importe() },
                    { data: 'a_favor', className: 'text-end ui-num', render: importe('text-success') },
                    { data: 'saldo', className: 'text-end fw-semibold ui-num', render: function (v, t) {
                        if (t !== 'display') return v;
                        return '<span class="' + (v > 0.009 ? 'text-danger' : (v < -0.009 ? 'text-success' : 'text-muted')) + '">' + UI.moneda(v) + '</span>';
                    } },
                    { data: 'vencido', className: 'text-end ui-num', render: importe('text-danger') },
                    { data: 'primer_vencimiento', className: 'text-nowrap', render: function (v, t) { return t === 'display' ? (v ? UI.fecha(v) : '<span class="text-muted">-</span>') : (v || ''); } },
                    { data: 'primer_vencimiento', className: 'text-end text-nowrap ui-num', render: atraso }
                ]
            }));
        }).fail(function (xhr) {
            const msg = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Error de conexión con el servidor.';
            $('#avisoSaldos').removeClass('d-none').text(msg);
        });
    }

    $('#tablaSaldos tbody').on('click', 'tr', function () {
        if (!tabla) return;
        const fila = tabla.row(this).data();
        if (!fila) return;
        $('#tablaSaldos tbody tr').removeClass('table-active');
        $(this).addClass('table-active');
        $('#cardDetalleCtacte').removeClass('d-none');
        detalle.cargar(fila.entidad_id);
        document.getElementById('cardDetalleCtacte').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    let filtroTimeout = null;
    $('#filtroTexto').on('input', function () {
        clearTimeout(filtroTimeout);
        filtroTimeout = setTimeout(function () {
            cargarSaldos();
        }, 300);
    }).on('keydown', function (e) {
        if (e.key === 'Enter') {
            clearTimeout(filtroTimeout);
            cargarSaldos();
        }
    });

    $('#filtroSoloSaldo, #filtroSoloVencidos').on('change', function () {
        cargarSaldos();
    });

    // Acceso directo desde otra pantalla: clientes_ctacte.php?entidad_id=N
    const entidadUrl = parseInt(new URLSearchParams(window.location.search).get('entidad_id'), 10);
    if (entidadUrl > 0) {
        $('#cardDetalleCtacte').removeClass('d-none');
        detalle.cargar(entidadUrl);
    }

    cargarSaldos();
});
