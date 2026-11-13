/**
 * proveedores_ctacte.js — Cuenta Corriente de Proveedores (página 118).
 * Listado de saldos por proveedor + widget de detalle (ctacte_proveedor.js).
 */
$(function () {
    'use strict';

    const UI = window.ComprobantesUI;
    const detalle = CtacteProveedor.crear('#detalleCtacte', { empresaId: EMPRESA_ID, mostrarCabecera: true });
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

    function cargarSaldos() {
        $.getJSON('proveedores_ctacte_ajax.php', filtros()).done(function (res) {
            $('#avisoSaldos').toggleClass('d-none', !res.message).text(res.message || '');
            const filas = res.success ? res.data : [];

            let saldo = 0, vencido = 0;
            filas.forEach(function (f) { saldo += f.saldo; vencido += f.vencido; });
            $('#totalesSaldos').html(filas.length + ' proveedor(es) · Saldo total <strong>' + UI.moneda(saldo) +
                '</strong> · Vencido <strong class="text-danger">' + UI.moneda(vencido) + '</strong>');

            if (tabla) {
                tabla.clear().rows.add(filas).draw();
                return;
            }
            tabla = $('#tablaSaldos').DataTable(UI.opcionesTabla({
                data: filas,
                searching: false,
                order: [[2, 'desc']],
                columns: [
                    { data: 'entidad_fantasia', render: function (v, t, r) {
                        const nombre = r.entidad_fantasia || r.entidad_nombre || '';
                        return t === 'display' ? UI.esc(nombre) : nombre;
                    } },
                    { data: 'cuit', defaultContent: '' },
                    { data: 'saldo', className: 'text-end fw-semibold', render: function (v, t) {
                        if (t !== 'display') return v;
                        return '<span class="' + (v > 0.009 ? 'text-danger' : (v < -0.009 ? 'text-success' : 'text-muted')) + '">' + UI.moneda(v) + '</span>';
                    } },
                    { data: 'deuda', className: 'text-end', render: importe() },
                    { data: 'vencido', className: 'text-end', render: importe('text-danger fw-semibold') },
                    { data: 'a_favor', className: 'text-end', render: importe('text-success') },
                    { data: 'primer_vencimiento', render: function (v, t) { return t === 'display' ? UI.fecha(v) : (v || ''); } },
                    { data: 'ultimo_movimiento', render: function (v, t) { return t === 'display' ? UI.fecha(v) : (v || ''); } }
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
        detalle.cargar(fila.entidad_id);
        document.getElementById('detalleCtacte').scrollIntoView({ behavior: 'smooth', block: 'start' });
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

    // Acceso directo desde otra pantalla: proveedores_ctacte.php?entidad_id=N
    const entidadUrl = parseInt(new URLSearchParams(window.location.search).get('entidad_id'), 10);
    if (entidadUrl > 0) detalle.cargar(entidadUrl);

    cargarSaldos();
});
