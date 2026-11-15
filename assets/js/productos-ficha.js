/**
 * Ficha de producto completa del módulo 5 (doc 26): solapas de templates/productos/ficha_solapas.php.
 * Backend: acciones ficha_* de productos_ajax.php → core/productos_ficha/.
 * Uso (productos.js): ProdFicha.init({ ajaxUrl, empresaIdx, paginaIdx, soloLectura });
 * ProdFicha.cargar(productoId, { soloLectura }) al abrir un producto; ProdFicha.limpiar() en el alta (las solapas quedan
 * deshabilitadas hasta guardar). Sin las solapas en la página no hace nada.
 */
var ProdFicha = (function ($) {
    'use strict';

    var cfg = { ajaxUrl: 'productos_ajax.php', empresaIdx: 0, paginaIdx: 0, soloLectura: false };
    var productoId = 0, proveedores = null, vinculos = [], ro = false;   // ro: solo lectura (consulta o "ver")

    function activo() { return $('#pficSolapas').length > 0; }
    function esc(t) { return t === null || t === undefined ? '' : String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    function ok(m) { if (window.UiSimple) UiSimple.ok(m || 'Guardado'); }
    function error(m) { if (window.UiSimple) UiSimple.error(m, 'No se pudo guardar'); else if (window.Swal) Swal.fire({ icon: 'error', text: m }); }
    function pedir(accion, datos, metodo) {
        return $.ajax({ url: cfg.ajaxUrl, type: metodo || 'POST', dataType: 'json',
            data: $.extend({ accion: accion, empresa_idx: cfg.empresaIdx, pagina_idx: cfg.paginaIdx, producto_id: productoId }, datos || {}) });
    }
    function fallo(x) { error((x && x.responseJSON && (x.responseJSON.message || x.responseJSON.error)) || 'No se pudo completar.'); }
    function respuesta(r, siOk) {
        if (!r || !r.success) { error((r && r.message) || 'No se pudo guardar.'); return; }
        ok(r.message); siOk();
    }

    // ---------------------------------------------------------------- Proveedores
    function cargarCombo() {
        if (ro || proveedores) return;
        pedir('ficha_proveedores_combo', {}, 'GET').done(function (r) {
            proveedores = (r && r.success && r.data) || [];
            $('#pficProvEntidad').html('<option value="">Elegir…</option>' + proveedores.map(function (p) {
                return '<option value="' + p.entidad_id + '">' + esc(p.entidad_nombre) + (p.cuit ? ' (' + esc(p.cuit) + ')' : '') + '</option>';
            }).join(''));
        });
    }
    function listarProveedores() {
        if (!productoId) { $('#pficProvFilas').html(''); return; }
        pedir('ficha_proveedores_listar', {}, 'GET').done(function (r) {
            vinculos = (r && r.success && r.data) || [];
            var cols = ro ? 4 : 5;
            $('#pficProvFilas').html(vinculos.length ? vinculos.map(function (v) {
                var principal = parseInt(v.es_principal, 10) === 1;
                return '<tr><td>' + (principal ? '<span class="badge bg-primary">Principal</span>'
                        : (ro ? '' : '<button type="button" class="btn btn-sm btn-link p-0 pfic-prov-principal" data-id="' + v.producto_proveedor_id + '">Hacer principal</button>')) + '</td>'
                    + '<td>' + esc(v.entidad_nombre) + '</td><td>' + esc(v.cuit) + '</td><td><code>' + esc(v.codigo_proveedor) + '</code></td>'
                    + (ro ? '' : '<td class="text-end text-nowrap"><button type="button" class="btn btn-sm btn-outline-secondary pfic-prov-editar" data-id="' + v.producto_proveedor_id + '" title="Editar"><i class="fas fa-pen"></i></button> '
                        + '<button type="button" class="btn btn-sm btn-outline-danger pfic-prov-quitar" data-id="' + v.producto_proveedor_id + '" title="Quitar"><i class="fas fa-trash"></i></button></td>')
                    + '</tr>';
            }).join('') : '<tr><td colspan="' + cols + '" class="text-muted small p-3">Sin proveedores vinculados.</td></tr>');
        }).fail(fallo);
    }
    function limpiarFormProveedor() {
        $('#pficProvId').val(''); $('#pficProvEntidad').val(''); $('#pficProvCodigo').val(''); $('#pficProvPrincipal').prop('checked', false);
        $('#pficProvGuardar').text('Agregar'); $('#pficProvCancelar').addClass('d-none');
    }
    function guardarProveedor() {
        var entidad = $('#pficProvEntidad').val();
        if (!entidad) { if (window.UiSimple) UiSimple.aviso('Elija el proveedor.'); return; }
        pedir('ficha_proveedor_guardar', {
            producto_proveedor_id: $('#pficProvId').val() || 0, entidad_id: entidad,
            codigo_proveedor: $.trim($('#pficProvCodigo').val()), es_principal: $('#pficProvPrincipal').is(':checked') ? 1 : 0
        }).done(function (r) { respuesta(r, function () { limpiarFormProveedor(); listarProveedores(); }); }).fail(fallo);
    }
    function enlazarProveedores() {
        $('#pficProvGuardar').on('click', guardarProveedor);
        $('#pficProvCodigo').on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); guardarProveedor(); } });
        $('#pficProvCancelar').on('click', limpiarFormProveedor);
        $(document).on('click', '.pfic-prov-editar', function () {
            var id = String($(this).data('id'));
            var v = vinculos.find(function (x) { return String(x.producto_proveedor_id) === id; });
            if (!v) return;
            $('#pficProvId').val(v.producto_proveedor_id); $('#pficProvEntidad').val(String(v.entidad_id));
            $('#pficProvCodigo').val(v.codigo_proveedor || '').trigger('focus');
            $('#pficProvPrincipal').prop('checked', parseInt(v.es_principal, 10) === 1);
            $('#pficProvGuardar').text('Guardar'); $('#pficProvCancelar').removeClass('d-none');
        });
        $(document).on('click', '.pfic-prov-quitar', function () {
            var id = $(this).data('id');
            var seguir = function () { pedir('ficha_proveedor_quitar', { producto_proveedor_id: id }).done(function (r) { respuesta(r, listarProveedores); }).fail(fallo); };
            if (window.Swal) {
                Swal.fire({ icon: 'question', text: '¿Quitar este proveedor del producto? También se da de baja su precio de lista.', showCancelButton: true, confirmButtonText: 'Quitar', cancelButtonText: 'Cancelar' })
                    .then(function (x) { if (x.isConfirmed) seguir(); });
            } else if (window.confirm('¿Quitar este proveedor del producto?')) { seguir(); }
        });
        $(document).on('click', '.pfic-prov-principal', function () {
            pedir('ficha_proveedor_principal', { producto_proveedor_id: $(this).data('id') }).done(function (r) { respuesta(r, listarProveedores); }).fail(fallo);
        });
    }

    // ---------------------------------------------------------------- Ficha
    function habilitar(si) {
        $('#pficSolapas .pfic-requiere-producto').toggleClass('disabled', !si).attr('title', si ? null : 'Guarde primero el producto');
    }
    function cargar(id, opciones) {
        if (!activo()) return;
        productoId = parseInt(id, 10) || 0;
        ro = !!cfg.soloLectura || !!(opciones && opciones.soloLectura);
        $('#pficProvForm').toggleClass('d-none', ro);
        habilitar(productoId > 0);
        limpiarFormProveedor();
        cargarCombo();
        listarProveedores();
    }
    function limpiar() {
        if (!activo()) return;
        productoId = 0;
        ro = !!cfg.soloLectura;
        $('#pficProvForm').toggleClass('d-none', ro);
        habilitar(false);
        limpiarFormProveedor();
        $('#pficProvFilas').html('');
        bootstrap.Tab.getOrCreateInstance(document.querySelector('#pficSolapas [data-bs-target="#pficInfo"]')).show();
    }
    function init(opciones) {
        $.extend(cfg, opciones || {});
        if (!activo()) return;
        enlazarProveedores();
        habilitar(false);
    }

    return { init: init, activo: activo, cargar: cargar, limpiar: limpiar };
})(jQuery);
