/**
 * Marca comercial del producto (doc 25). Campo: templates/productos/marca_campo.php (#marca_comercial_id, solo con el
 * indicador). Backend: acciones marcas_* de productos_ajax.php → core/productos_marcas.php.
 * Uso: ProdMarcas.init({ ajaxUrl: 'productos_ajax.php', empresaIdx, paginaIdx }); al abrir un producto
 * ProdMarcas.valor(producto.marca_comercial_id); al limpiar el formulario ProdMarcas.valor('').
 * Sin el campo en la página no hace nada.
 */
var ProdMarcas = (function ($) {
    'use strict';

    var cfg = { ajaxUrl: 'productos_ajax.php', empresaIdx: 0, paginaIdx: 0 };
    var marcas = [], pendiente = '', iniciado = false;

    function esc(t) { return t === null || t === undefined ? '' : String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    function hay() { return $('#marca_comercial_id').length > 0; }
    function ok(m) { if (window.UiSimple) UiSimple.ok(m || 'Guardado'); }
    function error(m) { if (window.UiSimple) UiSimple.error(m, 'No se pudo guardar'); else if (window.Swal) Swal.fire({ icon: 'error', text: m }); }
    function pedir(accion, datos, metodo) {
        return $.ajax({ url: cfg.ajaxUrl, type: metodo || 'POST', dataType: 'json',
            data: $.extend({ accion: accion, empresa_idx: cfg.empresaIdx, pagina_idx: cfg.paginaIdx }, datos || {}) });
    }
    function fallo(x) { error((x && x.responseJSON && (x.responseJSON.message || x.responseJSON.error)) || 'No se pudo completar.'); }

    function cargar() {
        return pedir('marcas_listar', { incluir_inactivas: 1 }, 'GET').done(function (r) {
            marcas = (r && r.success && r.data) || [];
            pintarSelect();
            valor(pendiente);
            pintarTabla();
        });
    }
    function pintarSelect() {
        $('#marca_comercial_id').html('<option value="">Sin marca</option>' + marcas.filter(function (m) { return parseInt(m.tabla_estado_registro_id, 10) === 1; })
            .map(function (m) { return '<option value="' + m.marca_comercial_id + '">' + esc(m.nombre) + '</option>'; }).join(''));
    }
    function valor(v) {
        pendiente = v ? String(v) : '';
        if (!hay()) return;
        if (pendiente && !$('#marca_comercial_id option[value="' + pendiente + '"]').length) {
            var m = marcas.find(function (x) { return String(x.marca_comercial_id) === pendiente; });
            if (m) $('#marca_comercial_id').append('<option value="' + pendiente + '">' + esc(m.nombre) + ' (inactiva)</option>');
        }
        $('#marca_comercial_id').val(pendiente);
    }

    // ---------------------------------------------------------------- Ventana de marcas
    function crearModal() {
        if ($('#pmarModal').length) return;
        $('body').append(
            '<div class="modal fade" id="pmarModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><div class="modal-content">'
            + '<div class="modal-header"><h5 class="modal-title">Marcas</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>'
            + '<div class="modal-body">'
            + '<ul class="nav ui-solapas mb-3"><li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pmarTabMarcas" type="button">Marcas</button></li>'
            + '<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pmarTabAsignar" type="button">Asignar a varios productos</button></li></ul>'
            + '<div class="tab-content"><div class="tab-pane fade show active" id="pmarTabMarcas">'
            + '<div class="d-flex gap-2 mb-2"><input type="hidden" id="pmarId"><input type="text" class="form-control form-control-sm" id="pmarNombre" maxlength="100" placeholder="Nombre de la marca">'
            + '<button type="button" class="btn btn-sm btn-primary text-nowrap" id="pmarGuardar">Agregar</button><button type="button" class="btn btn-sm btn-outline-secondary d-none" id="pmarCancelar">Cancelar</button></div>'
            + '<div class="table-responsive" style="max-height: 50vh; overflow-y: auto;"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>Marca</th><th class="text-end">Productos</th><th>Estado</th><th></th></tr></thead><tbody id="pmarFilas"></tbody></table></div>'
            + '</div><div class="tab-pane fade" id="pmarTabAsignar">'
            + '<div class="d-flex flex-wrap gap-2 mb-2"><input type="search" class="form-control form-control-sm" id="pmarBuscar" placeholder="Productos cuyo nombre o código contiene…" style="max-width: 320px;">'
            + '<button type="button" class="btn btn-sm btn-outline-secondary" id="pmarBuscarBtn"><i class="fas fa-search me-1"></i>Buscar</button>'
            + '<select class="form-select form-select-sm" id="pmarAsignarMarca" style="max-width: 220px;"></select>'
            + '<button type="button" class="btn btn-sm btn-primary" id="pmarAsignarBtn" disabled>Poner la marca a los marcados</button></div>'
            + '<div class="small text-muted mb-1" id="pmarAsignarResumen"></div>'
            + '<div class="table-responsive" style="max-height: 45vh; overflow-y: auto;"><table class="table table-sm align-middle mb-0"><thead><tr><th style="width: 32px;"><input type="checkbox" class="form-check-input" id="pmarTodos"></th><th>Código</th><th>Producto</th><th>Marca actual</th></tr></thead><tbody id="pmarProdFilas"></tbody></table></div>'
            + '</div></div></div></div></div></div>');
        $('#pmarGuardar').on('click', guardar);
        $('#pmarNombre').on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); guardar(); } });
        $('#pmarCancelar').on('click', limpiarEdicion);
        $(document).on('click', '.pmar-editar', function () {
            var m = marcas.find(function (x) { return String(x.marca_comercial_id) === String($(this).data('id')); }.bind(this));
            if (!m) return;
            $('#pmarId').val(m.marca_comercial_id); $('#pmarNombre').val(m.nombre).trigger('focus');
            $('#pmarGuardar').text('Guardar'); $('#pmarCancelar').removeClass('d-none');
        });
        $(document).on('click', '.pmar-estado', function () {
            pedir('marcas_estado', { marca_comercial_id: $(this).data('id'), estado: $(this).data('estado') }).done(function (r) {
                if (!r || !r.success) { error((r && r.message) || 'No se pudo guardar.'); return; }
                ok(r.message); cargar();
            }).fail(fallo);
        });
        $('#pmarBuscarBtn').on('click', buscarProductos);
        $('#pmarBuscar').on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); buscarProductos(); } });
        $('#pmarTodos').on('change', function () { $('#pmarProdFilas .pmar-prod').prop('checked', this.checked); actualizarAsignar(); });
        $(document).on('change', '.pmar-prod', actualizarAsignar);
        $('#pmarAsignarBtn').on('click', asignar);
    }
    function pintarTabla() {
        if (!$('#pmarFilas').length) return;
        $('#pmarFilas').html(marcas.length ? marcas.map(function (m) {
            var activa = parseInt(m.tabla_estado_registro_id, 10) === 1;
            return '<tr><td>' + esc(m.nombre) + '</td><td class="text-end">' + (parseInt(m.productos, 10) || 0) + '</td>'
                + '<td>' + (activa ? '<span class="badge bg-success">Activa</span>' : '<span class="badge bg-secondary">Inactiva</span>') + '</td>'
                + '<td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-secondary pmar-editar" data-id="' + m.marca_comercial_id + '" title="Cambiar el nombre"><i class="fas fa-pen"></i></button> '
                + '<button type="button" class="btn btn-sm btn-outline-' + (activa ? 'danger' : 'success') + ' pmar-estado" data-id="' + m.marca_comercial_id + '" data-estado="' + (activa ? 2 : 1) + '" title="' + (activa ? 'Inactivar' : 'Activar') + '"><i class="fas fa-' + (activa ? 'ban' : 'check') + '"></i></button></td></tr>';
        }).join('') : '<tr><td colspan="4" class="text-muted small p-3">Todavía no hay marcas.</td></tr>');
        $('#pmarAsignarMarca').html('<option value="">Marca…</option>' + marcas.filter(function (m) { return parseInt(m.tabla_estado_registro_id, 10) === 1; })
            .map(function (m) { return '<option value="' + m.marca_comercial_id + '">' + esc(m.nombre) + '</option>'; }).join(''));
    }
    function limpiarEdicion() { $('#pmarId').val(''); $('#pmarNombre').val(''); $('#pmarGuardar').text('Agregar'); $('#pmarCancelar').addClass('d-none'); }
    function guardar() {
        var nombre = $.trim($('#pmarNombre').val());
        if (!nombre) return;
        pedir('marcas_guardar', { marca_comercial_id: $('#pmarId').val() || 0, nombre: nombre }).done(function (r) {
            if (!r || !r.success) { error((r && r.message) || 'No se pudo guardar.'); return; }
            ok(r.message); limpiarEdicion(); cargar();
        }).fail(fallo);
    }
    function buscarProductos() {
        pedir('marcas_buscar_productos', { q: $('#pmarBuscar').val() }, 'GET').done(function (r) {
            if (!r || !r.success) { error((r && r.message) || 'No se pudo buscar.'); return; }
            var ps = r.data || [];
            $('#pmarTodos').prop('checked', false);
            $('#pmarAsignarResumen').text(ps.length + ' producto(s)' + (ps.length >= 500 ? ' (se muestran los primeros 500: afine la búsqueda)' : ''));
            $('#pmarProdFilas').html(ps.map(function (p) {
                return '<tr><td><input type="checkbox" class="form-check-input pmar-prod" value="' + p.producto_id + '"></td><td>' + esc(p.producto_codigo) + '</td><td>' + esc(p.producto_nombre) + '</td><td>' + esc(p.marca || '') + '</td></tr>';
            }).join(''));
            actualizarAsignar();
        }).fail(fallo);
    }
    function actualizarAsignar() { $('#pmarAsignarBtn').prop('disabled', !$('#pmarProdFilas .pmar-prod:checked').length); }
    function asignar() {
        var marca = $('#pmarAsignarMarca').val();
        var ids = $('#pmarProdFilas .pmar-prod:checked').map(function () { return parseInt(this.value, 10); }).get();
        if (!marca || !ids.length) { if (window.UiSimple) UiSimple.aviso('Elija la marca y marque los productos.'); return; }
        pedir('marcas_asignar', { marca_comercial_id: marca, productos: JSON.stringify(ids) }).done(function (r) {
            if (!r || !r.success) { error((r && r.message) || 'No se pudo guardar.'); return; }
            ok(r.message); buscarProductos(); cargar();
        }).fail(fallo);
    }

    function init(opciones) {
        $.extend(cfg, opciones || {});
        if (!hay() || iniciado) return;
        iniciado = true;
        $(document).on('click', '#pmarAdministrar', function () {
            crearModal(); pintarTabla();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('pmarModal')).show();
        });
        cargar();
    }

    return { init: init, valor: valor, recargar: cargar };
})(jQuery);
