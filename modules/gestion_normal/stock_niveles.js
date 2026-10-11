/**
 * stock_niveles.js — Stock mínimo / máximo por boca (página 112)
 * Botones calculados por el motor (conf__paginas_funciones): el backend manda `botones` por fila
 * y las funciones de creación (origen 0) en obtener_boton_agregar.
 */
$(function () {
    'use strict';

    const AJAX_URL = 'stock_niveles_ajax.php';
    const empresaIdx = typeof SNV_EMPRESA_ID !== 'undefined' ? SNV_EMPRESA_ID : 0;
    const fmt = new Intl.NumberFormat('es-AR', { maximumFractionDigits: 4 });

    let tabla = null;
    let bocas = [];
    let filtroBajoMinimo = false;
    let timerBusqueda = null;
    const modalNivel = new bootstrap.Modal(document.getElementById('modalNivel'));
    const modalCopiar = new bootstrap.Modal(document.getElementById('modalCopiar'));

    function esc(v) {
        return $('<div>').text(v == null ? '' : String(v)).html();
    }

    function num(v) {
        return v === null || v === undefined || v === '' ? '' : fmt.format(parseFloat(v));
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

    function opcionesBocas($sel, primera) {
        let html = primera ? `<option value="${primera.value}">${esc(primera.text)}</option>` : '';
        bocas.forEach(b => {
            html += `<option value="${b.boca_id}">${esc(b.sucursal_nombre)} — ${esc(b.boca_nombre)}</option>`;
        });
        $sel.html(html);
    }

    // ---------------------------------------------------------------- carga inicial
    function cargarBocas() {
        return llamar('obtener_bocas', {}, 'GET').then(r => {
            bocas = r.data || [];
            opcionesBocas($('#filtroBoca'), { value: 0, text: 'Todas las bocas' });
            opcionesBocas($('#boca_id'));
            opcionesBocas($('#boca_origen_id'));
            opcionesBocas($('#boca_destino_id'));
        });
    }

    function cargarBotonesCreacion() {
        llamar('obtener_boton_agregar', {}, 'GET').then(r => {
            const html = (r.data || []).map(b =>
                `<button type="button" class="btn btn-sm ${esc(b.color_clase)} btn-creacion" data-accion="${esc(b.accion_js)}">
                    ${b.icono_clase ? `<i class="${esc(b.icono_clase)} me-1"></i>` : ''}${esc(b.nombre_funcion)}
                 </button>`).join('');
            $('#contenedorBotonesCreacion').html(html);
        }, error);
    }

    // Filtro "solo bajo el mínimo" (del lado del cliente: la grilla ya trae el flag)
    $.fn.dataTable.ext.search.push(function (settings, data, idx, row) {
        if (settings.nTable.id !== 'tablaNiveles' || !filtroBajoMinimo) return true;
        return row.bajo_minimo == 1;
    });

    function inicializarTabla() {
        tabla = $('#tablaNiveles').DataTable({
            buttons: [], // sin Copy/Excel/CSV/PDF del default de header1.php
            ajax: {
                url: AJAX_URL,
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.empresa_idx = empresaIdx;
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
                { data: 'sucursal_nombre', render: esc },
                { data: 'boca_nombre', render: esc },
                { data: 'producto_codigo', render: esc },
                { data: 'producto_nombre', render: esc },
                {
                    data: 'stock_actual', className: 'text-end',
                    render: (d, t, row) => t === 'display'
                        ? `<span class="${row.bajo_minimo == 1 ? 'text-danger fw-bold' : ''}">${num(d)}</span>` : d
                },
                { data: 'stock_minimo', className: 'text-end', render: (d, t) => t === 'display' ? num(d) : d },
                {
                    data: 'stock_maximo', className: 'text-end',
                    render: (d, t) => t === 'display' ? (d === null ? '<span class="text-muted">—</span>' : num(d)) : (d || '')
                },
                {
                    data: 'stock_alerta_id', className: 'text-center',
                    render: (d, t) => t === 'display'
                        ? (d ? '<a href="stock_alertas.php" title="Alerta de reposición abierta"><i class="bi bi-exclamation-triangle-fill text-warning"></i></a>' : '')
                        : (d ? 'Sí' : '')
                },
                {
                    data: 'estado_nombre', className: 'text-center',
                    render: (d, t, row) => t === 'display'
                        ? `<span class="badge ${row.tabla_estado_registro_id == 1 ? 'text-bg-success' : 'text-bg-secondary'}">${esc(d)}</span>` : d
                },
                {
                    data: 'botones', orderable: false, searchable: false, className: 'text-center',
                    render: function (botones, t, row) {
                        if (!botones || !botones.length) return '<span class="text-muted small">Sin acciones</span>';
                        return '<div class="btn-group">' + botones.map(b =>
                            `<button type="button" class="btn btn-sm ${esc(b.color_clase)} btn-accion me-1"
                                title="${esc(b.descripcion)}" data-id="${row.stock_nivel_id}"
                                data-accion="${esc(b.accion_js)}" data-nombre="${esc(b.nombre_funcion)}"
                                data-confirmable="${b.es_confirmable}">
                                ${b.icono_clase ? `<i class="${esc(b.icono_clase)}"></i>` : esc(b.nombre_funcion)}
                             </button>`).join('') + '</div>';
                    }
                }
            ],
            order: [[0, 'asc'], [1, 'asc'], [3, 'asc']],
            pageLength: 25,
            language: { url: '//cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json' },
            createdRow: function (tr, row) {
                if (row.tabla_estado_registro_id != 1) $(tr).addClass('table-secondary');
            }
        });
    }

    function recargar() {
        if (tabla) tabla.ajax.reload(null, false);
    }

    $('#filtroBoca').on('change', recargar);
    $('#btnRecargar').on('click', recargar);
    $('#filtroBajoMinimo').on('change', function () {
        filtroBajoMinimo = this.checked;
        tabla.draw();
    });

    // ---------------------------------------------------------------- alta / edición
    function abrirAlta() {
        $('#formNivel')[0].reset();
        $('#stock_nivel_id').val('');
        $('#modalNivelTitulo').text('Nuevo mínimo / máximo');
        $('#boca_id').prop('disabled', false);
        const filtro = $('#filtroBoca').val();
        if (filtro && filtro !== '0') $('#boca_id').val(filtro);
        $('#producto_id').empty();
        $('#grupoBuscarProducto').removeClass('d-none');
        $('#grupoProductoFijo').addClass('d-none');
        modalNivel.show();
        setTimeout(() => $('#buscarProducto').trigger('focus'), 300);
    }

    function abrirEdicion(id) {
        llamar('obtener', { stock_nivel_id: id }, 'GET').then(r => {
            const n = r.data;
            $('#formNivel')[0].reset();
            $('#modalNivelTitulo').text('Editar mínimo / máximo');
            $('#stock_nivel_id').val(n.stock_nivel_id);
            $('#boca_id').val(n.boca_id).prop('disabled', true);
            $('#grupoBuscarProducto').addClass('d-none');
            $('#grupoProductoFijo').removeClass('d-none');
            $('#productoFijo').text(`${n.producto_codigo} — ${n.producto_nombre}`);
            $('#stock_minimo').val(parseFloat(n.stock_minimo));
            $('#stock_maximo').val(n.stock_maximo === null ? '' : parseFloat(n.stock_maximo));
            $('#observaciones').val(n.observaciones || '');
            modalNivel.show();
        }, error);
    }

    $('#buscarProducto').on('input', function () {
        const q = this.value.trim();
        clearTimeout(timerBusqueda);
        if (q.length < 2) {
            $('#producto_id').empty();
            return;
        }
        timerBusqueda = setTimeout(() => {
            llamar('buscar_productos', { q: q }, 'GET').then(r => {
                const items = r.data || [];
                $('#producto_id').html(items.length
                    ? items.map(p => `<option value="${p.producto_id}">${esc(p.producto_codigo)} — ${esc(p.producto_nombre)}</option>`).join('')
                    : '<option value="" disabled>Sin resultados (solo productos que controlan stock)</option>');
                if (items.length === 1) $('#producto_id').val(items[0].producto_id);
            }, error);
        }, 300);
    });

    $('#formNivel').on('submit', function (e) {
        e.preventDefault();
        const id = $('#stock_nivel_id').val();
        const datos = {
            stock_minimo: $('#stock_minimo').val(),
            stock_maximo: $('#stock_maximo').val(),
            observaciones: $('#observaciones').val()
        };
        if (datos.stock_minimo === '') return error('Cargue el stock mínimo.');
        if (id) {
            datos.stock_nivel_id = id;
        } else {
            datos.boca_id = $('#boca_id').val();
            datos.producto_id = $('#producto_id').val();
            if (!datos.boca_id) return error('Elija la boca.');
            if (!datos.producto_id) return error('Busque y elija el producto.');
        }
        const $btn = $('#btnGuardarNivel').prop('disabled', true);
        llamar(id ? 'editar' : 'agregar', datos).then(r => {
            modalNivel.hide();
            Swal.fire({ icon: 'success', title: r.message, timer: 1200, showConfirmButton: false });
            recargar();
        }, error).always(() => $btn.prop('disabled', false));
    });

    // ---------------------------------------------------------------- copiar de otra boca
    $('#formCopiar').on('submit', function (e) {
        e.preventDefault();
        const datos = {
            boca_origen_id: $('#boca_origen_id').val(),
            boca_destino_id: $('#boca_destino_id').val(),
            sobrescribir: $('#sobrescribir').is(':checked') ? 1 : 0
        };
        if (datos.boca_origen_id === datos.boca_destino_id) return error('Elija dos bocas distintas.');
        llamar('copiar_boca', datos).then(r => {
            modalCopiar.hide();
            Swal.fire({ icon: 'success', title: 'Niveles copiados', text: r.message });
            recargar();
        }, error);
    });

    // ---------------------------------------------------------------- botones
    $(document).on('click', '.btn-creacion', function () {
        const accion = $(this).data('accion');
        if (accion === 'agregar') abrirAlta();
        else if (accion === 'copiar_boca') modalCopiar.show();
    });

    $(document).on('click', '#tablaNiveles .btn-accion', function () {
        const id = $(this).data('id');
        const accion = $(this).data('accion');
        const nombre = $(this).data('nombre');
        if (accion === 'editar') return abrirEdicion(id);

        Swal.fire({
            icon: 'question',
            title: `¿${nombre}?`,
            text: accion === 'inhabilitar'
                ? 'El producto deja de controlarse en esta boca y se cierra su alerta abierta, si la tiene.'
                : 'Se vuelve a controlar el mínimo; si el stock ya está por debajo, se abre la alerta.',
            showCancelButton: true,
            confirmButtonText: nombre,
            cancelButtonText: 'Cancelar'
        }).then(res => {
            if (!res.isConfirmed) return;
            llamar('ejecutar_accion', { stock_nivel_id: id, accion_js: accion }).then(r => {
                Swal.fire({ icon: 'success', title: r.message, timer: 1200, showConfirmButton: false });
                recargar();
            }, error);
        });
    });

    cargarBocas().then(inicializarTabla, error);
    cargarBotonesCreacion();
});
