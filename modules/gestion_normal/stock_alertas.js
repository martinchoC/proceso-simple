/**
 * stock_alertas.js — Alertas de reposición (página 113)
 * Las alertas las abre y las cierra por reposición el servicio de stock; acá solo se
 * Toman (17 -> 18) o Descartan (-> 26), con los botones que manda el motor.
 */
$(function () {
    'use strict';

    const AJAX_URL = 'stock_alertas_ajax.php';
    const empresaIdx = typeof SAL_EMPRESA_ID !== 'undefined' ? SAL_EMPRESA_ID : 0;
    const fmt = new Intl.NumberFormat('es-AR', { maximumFractionDigits: 4 });
    const MOTIVOS = {
        REPOSICION: 'Repuesta',
        DESCARTADA: 'Descartada',
        NIVEL_MODIFICADO: 'Cambió el mínimo',
        NIVEL_INACTIVO: 'Nivel inhabilitado'
    };
    let tabla = null;

    function esc(v) {
        return $('<div>').text(v == null ? '' : String(v)).html();
    }

    function num(v) {
        return v === null || v === undefined || v === '' ? '' : fmt.format(parseFloat(v));
    }

    function fecha(v) {
        if (!v) return '';
        const d = new Date(v.replace(' ', 'T'));
        return isNaN(d) ? esc(v) : d.toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' });
    }

    function vista() {
        return $('input[name="vista"]:checked').val();
    }

    function llamar(accion, datos, metodo) {
        return $.ajax({
            url: AJAX_URL,
            method: metodo || 'POST',
            dataType: 'json',
            data: Object.assign({ accion: accion, empresa_idx: empresaIdx }, datos || {})
        }).then(function (r) {
            if (!r || !r.success) return $.Deferred().reject(r && r.message ? r.message : 'Error inesperado');
            return r;
        }, function (xhr) {
            const r = xhr.responseJSON;
            return $.Deferred().reject(r && r.message ? r.message : 'Error de comunicación con el servidor');
        });
    }

    function error(msg) {
        Swal.fire({ icon: 'error', title: 'Atención', text: msg });
    }

    function actualizarContador() {
        llamar('contar_abiertas', {}, 'GET').then(r => $('#cantAbiertas').text(r.data));
    }

    function inicializarTabla() {
        tabla = $('#tablaAlertas').DataTable({
            buttons: [], // sin Copy/Excel/CSV/PDF del default de header1.php
            ajax: {
                url: AJAX_URL,
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.empresa_idx = empresaIdx;
                    d.vista = vista();
                    d.boca_id = $('#filtroBoca').val() || 0;
                },
                dataSrc: function (json) {
                    if (!json.success) {
                        error(json.message || 'No se pudo cargar la grilla');
                        return [];
                    }
                    return json.data;
                }
            },
            columns: [
                { data: 'f_disparo', render: (d, t) => t === 'display' ? fecha(d) : d },
                { data: 'sucursal_nombre', render: esc },
                { data: 'boca_nombre', render: esc },
                { data: 'producto_codigo', render: esc },
                { data: 'producto_nombre', render: esc },
                {
                    data: 'stock_actual', className: 'text-end',
                    render: (d, t, row) => t === 'display'
                        ? `<span class="${row.f_cierre ? '' : 'text-danger fw-bold'}">${num(d)}</span>` : d
                },
                { data: 'stock_minimo', className: 'text-end', render: (d, t) => t === 'display' ? num(d) : d },
                {
                    data: 'stock_maximo', className: 'text-end',
                    render: (d, t) => t === 'display' ? (d === null ? '<span class="text-muted">—</span>' : num(d)) : (d || '')
                },
                {
                    data: 'cantidad_sugerida', className: 'text-end fw-bold',
                    render: (d, t, row) => t === 'display' ? (row.f_cierre ? '' : num(d)) : d
                },
                {
                    data: 'estado_nombre', className: 'text-center',
                    render: function (d, t, row) {
                        if (t !== 'display') return d;
                        const clase = { 17: 'text-bg-warning', 18: 'text-bg-primary', 21: 'text-bg-success', 26: 'text-bg-secondary' }[row.tabla_estado_registro_id] || 'text-bg-light';
                        return `<span class="badge ${clase}">${esc(d)}</span>`;
                    }
                },
                {
                    data: null, orderable: false,
                    render: function (d, t, row) {
                        const partes = [];
                        if (row.usuario_toma) partes.push(`Tomada por ${esc(row.usuario_toma)} ${fecha(row.f_toma)}`);
                        if (row.f_cierre) {
                            partes.push(`${esc(MOTIVOS[row.motivo_cierre] || row.motivo_cierre || 'Cerrada')} ${fecha(row.f_cierre)}`
                                + (row.usuario_cierre ? ` (${esc(row.usuario_cierre)})` : ''));
                        }
                        return `<small class="text-muted">${partes.join('<br>')}</small>`;
                    }
                },
                {
                    data: 'botones', orderable: false, searchable: false, className: 'text-center',
                    render: function (botones, t, row) {
                        if (!botones || !botones.length || row.f_cierre) return '';
                        return '<div class="btn-group">' + botones.map(b =>
                            `<button type="button" class="btn btn-sm ${esc(b.color_clase)} btn-accion me-1"
                                title="${esc(b.descripcion)}" data-id="${row.stock_alerta_id}"
                                data-accion="${esc(b.accion_js)}" data-nombre="${esc(b.nombre_funcion)}"
                                data-producto="${esc(row.producto_nombre)}">
                                ${b.icono_clase ? `<i class="${esc(b.icono_clase)}"></i>` : esc(b.nombre_funcion)}
                             </button>`).join('') + '</div>';
                    }
                }
            ],
            order: [[0, 'desc']],
            pageLength: 25,
            language: { url: '//cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json' }
        });
    }

    function recargar() {
        if (tabla) tabla.ajax.reload(null, false);
        actualizarContador();
    }

    $('input[name="vista"], #filtroBoca').on('change', recargar);
    $('#btnRecargar').on('click', recargar);

    $(document).on('click', '#tablaAlertas .btn-accion', function () {
        const id = $(this).data('id');
        const accion = $(this).data('accion');
        const nombre = $(this).data('nombre');
        const producto = $(this).data('producto');
        Swal.fire({
            icon: 'question',
            title: `¿${nombre}?`,
            html: accion === 'descartar'
                ? `<b>${esc(producto)}</b><br>La alerta se cierra sin reponer y no vuelve a abrirse hasta que el stock se reponga y caiga de nuevo bajo el mínimo.`
                : `<b>${esc(producto)}</b><br>La alerta queda en gestión a su nombre. Se cierra sola cuando ingrese la mercadería.`,
            showCancelButton: true,
            confirmButtonText: nombre,
            cancelButtonText: 'Cancelar'
        }).then(res => {
            if (!res.isConfirmed) return;
            llamar('ejecutar_accion', { stock_alerta_id: id, accion_js: accion }).then(r => {
                Swal.fire({ icon: 'success', title: r.message, timer: 1200, showConfirmButton: false });
                recargar();
            }, error);
        });
    });

    llamar('obtener_bocas', {}, 'GET').then(r => {
        let html = '<option value="0">Todas las bocas</option>';
        (r.data || []).forEach(b => {
            html += `<option value="${b.boca_id}">${esc(b.sucursal_nombre)} — ${esc(b.boca_nombre)}</option>`;
        });
        $('#filtroBoca').html(html);
        inicializarTabla();
        actualizarContador();
    }, error);
});
