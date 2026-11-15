/**
 * Ficha de producto completa del módulo 5 (doc 26): solapas de templates/productos/ficha_solapas.php.
 * Backend: acciones ficha_* de productos_ajax.php → core/productos_ficha/; Precios usa listas_precios_productos_ajax.php
 * tal como está (el mismo de la página Lista de Precios, que ya acepta llamadas desde Productos).
 * Uso (productos.js): ProdFicha.init({ ajaxUrl, empresaIdx, paginaIdx, soloLectura });
 * ProdFicha.cargar(productoId, { soloLectura }) al abrir un producto; ProdFicha.limpiar() en el alta (las solapas quedan
 * deshabilitadas hasta guardar). Sin las solapas en la página no hace nada.
 */
var ProdFicha = (function ($) {
    'use strict';

    var cfg = { ajaxUrl: 'productos_ajax.php', preciosUrl: 'listas_precios_productos_ajax.php', empresaIdx: 0, paginaIdx: 0, soloLectura: false };
    var productoId = 0, proveedores = null, vinculos = [], listas = null, precios = [], ubiOpciones = null, ro = false;   // ro: solo lectura (consulta o "ver")

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

    // ---------------------------------------------------------------- Precios (endpoint de Lista de Precios)
    function lp(accion, datos, metodo) {
        return $.ajax({ url: cfg.preciosUrl, type: metodo || 'GET', dataType: 'json', data: $.extend({ accion: accion }, datos || {}) });
    }
    function dinero(v) { return window.Fmt ? '$ ' + Fmt.numero(parseFloat(v) || 0, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : (parseFloat(v) || 0).toFixed(2); }
    function respuestaLp(r, siOk) {
        if (!r || !r.resultado) { error((r && r.error) || 'No se pudo guardar.'); return; }
        ok('Guardado'); siOk();
    }
    function cargarListas() {
        if (ro || listas) return;
        lp('obtener_listas').done(function (r) {
            listas = r || [];
            $('#pficPreLista').html('<option value="">Elegir…</option>' + listas.map(function (l) { return '<option value="' + l.lista_precio_id + '">' + esc(l.nombre) + '</option>'; }).join(''));
        });
    }
    function listarPrecios() {
        if (!productoId) { $('#pficPreFilas').html(''); return; }
        lp('obtener_precios_producto', { producto_id: productoId }).done(function (r) {
            precios = r || [];
            $('#pficPreFilas').html(precios.length ? precios.map(function (x) {
                return '<tr><td>' + esc(x.lista_nombre) + '</td><td class="text-end">' + dinero(x.precio_unitario) + '</td>'
                    + '<td class="text-end" title="IVA ' + esc(x.iva_porcentaje) + ' %">' + dinero(x.precio_con_iva) + '</td>'
                    + '<td class="small">' + (x.f_actualizacion ? esc(window.Fmt ? Fmt.fechaHora(x.f_actualizacion) : x.f_actualizacion) : '-') + '</td>'
                    + (ro ? '' : '<td class="text-end text-nowrap"><button type="button" class="btn btn-sm btn-outline-secondary pfic-pre-editar" data-id="' + x.lista_precio_producto_id + '" title="Editar"><i class="fas fa-pen"></i></button> '
                        + '<button type="button" class="btn btn-sm btn-outline-danger pfic-pre-quitar" data-id="' + x.lista_precio_producto_id + '" title="Quitar"><i class="fas fa-trash"></i></button></td>')
                    + '</tr>';
            }).join('') : '<tr><td colspan="' + (ro ? 4 : 5) + '" class="text-muted small p-3">El producto no tiene precios cargados.</td></tr>');
        }).fail(fallo);
    }
    function limpiarFormPrecio() {
        $('#pficPreId').val(''); $('#pficPreLista').val('').prop('disabled', false); $('#pficPrePrecio').val('');
        $('#pficPreGuardar').text('Agregar'); $('#pficPreCancelar').addClass('d-none');
    }
    function guardarPrecio() {
        var lista = $('#pficPreLista').val(), precio = parseFloat($('#pficPrePrecio').val());
        if (!lista || !(precio > 0)) { if (window.UiSimple) UiSimple.aviso('Elija la lista y cargue un precio mayor a cero.'); return; }
        var id = $('#pficPreId').val();
        lp(id ? 'editar' : 'agregar', { lista_precio_producto_id: id, lista_precio_id: lista, producto_id: productoId, precio_unitario: precio }, 'POST')
            .done(function (r) { respuestaLp(r, function () { limpiarFormPrecio(); listarPrecios(); }); }).fail(fallo);
    }
    function enlazarPrecios() {
        $('#pficPreGuardar').on('click', guardarPrecio);
        $('#pficPrePrecio').on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); guardarPrecio(); } });
        $('#pficPreCancelar').on('click', limpiarFormPrecio);
        $(document).on('click', '.pfic-pre-editar', function () {
            var id = String($(this).data('id'));
            var x = precios.find(function (p) { return String(p.lista_precio_producto_id) === id; });
            if (!x) return;
            $('#pficPreId').val(x.lista_precio_producto_id); $('#pficPreLista').val(String(x.lista_precio_id)).prop('disabled', true);
            $('#pficPrePrecio').val((parseFloat(x.precio_unitario) || 0).toFixed(2)).trigger('focus');
            $('#pficPreGuardar').text('Guardar'); $('#pficPreCancelar').removeClass('d-none');
        });
        $(document).on('click', '.pfic-pre-quitar', function () {
            var id = $(this).data('id');
            var seguir = function () { lp('eliminar', { lista_precio_producto_id: id }).done(function (r) { respuestaLp(r, listarPrecios); }).fail(fallo); };
            if (window.Swal) {
                Swal.fire({ icon: 'question', text: '¿Quitar el precio del producto en esta lista?', showCancelButton: true, confirmButtonText: 'Quitar', cancelButtonText: 'Cancelar' })
                    .then(function (x) { if (x.isConfirmed) seguir(); });
            } else if (window.confirm('¿Quitar el precio del producto en esta lista?')) { seguir(); }
        });
    }

    // ---------------------------------------------------------------- Ubicaciones
    function textoUbicacion(u) {
        return [u.sucursal_nombre, u.boca_nombre, 'Secc. ' + u.seccion, 'Estant. ' + u.estanteria, 'Estante ' + u.estante, 'Pos. ' + u.posicion]
            .filter(Boolean).join(' · ') + (u.descripcion ? ' (' + u.descripcion + ')' : '');
    }
    function cargarOpcionesUbicacion(forzar) {
        if (ro || (ubiOpciones && !forzar)) return;
        pedir('ficha_ubicaciones_opciones', {}, 'GET').done(function (r) {
            ubiOpciones = (r && r.success && r.data) || { ubicaciones: [], sucursales: [] };
            $('#pficUbiElegir').html('<option value="">Elegir…</option>' + ubiOpciones.ubicaciones.map(function (u) {
                return '<option value="' + u.sucursal_ubicacion_id + '">' + esc(textoUbicacion(u)) + '</option>';
            }).join(''));
            $('#pficUbiSucursal').html(ubiOpciones.sucursales.map(function (s) { return '<option value="' + s.sucursal_id + '">' + esc(s.sucursal_nombre) + '</option>'; }).join(''));
            pintarBocas();
        });
    }
    function pintarBocas() {
        var sid = String($('#pficUbiSucursal').val() || '');
        var s = ((ubiOpciones && ubiOpciones.sucursales) || []).find(function (x) { return String(x.sucursal_id) === sid; });
        $('#pficUbiBoca').html(((s && s.bocas) || []).map(function (b) { return '<option value="' + b.boca_id + '">' + esc(b.boca_nombre) + '</option>'; }).join(''));
    }
    function listarUbicaciones() {
        if (!productoId) { $('#pficUbiFilas').html(''); return; }
        pedir('ficha_ubicaciones_listar', {}, 'GET').done(function (r) {
            var us = (r && r.success && r.data) || [];
            $('#pficUbiFilas').html(us.length ? us.map(function (u) {
                return '<tr><td>' + esc(u.sucursal_nombre) + '</td><td>' + esc(u.boca_nombre) + '</td><td>' + esc(u.seccion) + '</td><td>' + esc(u.estanteria) + '</td>'
                    + '<td>' + esc(u.estante) + '</td><td>' + esc(u.posicion) + '</td><td class="small">' + esc(u.descripcion) + '</td>'
                    + (ro ? '' : '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger pfic-ubi-quitar" data-id="' + u.producto_ubicacion_id + '" title="Quitar"><i class="fas fa-trash"></i></button></td>')
                    + '</tr>';
            }).join('') : '<tr><td colspan="' + (ro ? 7 : 8) + '" class="text-muted small p-3">El producto no tiene ubicaciones asignadas.</td></tr>');
        }).fail(fallo);
    }
    function limpiarFormUbicacion() {
        $('#pficUbiElegir').val('');
        $('#pficUbiNuevaForm').addClass('d-none').find('input').val('');
    }
    function enlazarUbicaciones() {
        $('#pficUbiAsignar').on('click', function () {
            var su = $('#pficUbiElegir').val();
            if (!su) { if (window.UiSimple) UiSimple.aviso('Elija la ubicación.'); return; }
            pedir('ficha_ubicacion_asignar', { sucursal_ubicacion_id: su }).done(function (r) { respuesta(r, function () { limpiarFormUbicacion(); listarUbicaciones(); }); }).fail(fallo);
        });
        $('#pficUbiNueva').on('click', function () { $('#pficUbiNuevaForm').toggleClass('d-none'); pintarBocas(); $('#pficUbiSeccion').trigger('focus'); });
        $('#pficUbiNuevaCancelar').on('click', limpiarFormUbicacion);
        $('#pficUbiSucursal').on('change', pintarBocas);
        $('#pficUbiCrear').on('click', function () {
            pedir('ficha_ubicacion_crear', {
                sucursal_id: $('#pficUbiSucursal').val(), boca_id: $('#pficUbiBoca').val(), seccion: $.trim($('#pficUbiSeccion').val()),
                estanteria: $.trim($('#pficUbiEstanteria').val()), estante: $.trim($('#pficUbiEstante').val()),
                posicion: $.trim($('#pficUbiPosicion').val()), descripcion: $.trim($('#pficUbiDesc').val())
            }).done(function (r) { respuesta(r, function () { limpiarFormUbicacion(); cargarOpcionesUbicacion(true); listarUbicaciones(); }); }).fail(fallo);
        });
        $(document).on('click', '.pfic-ubi-quitar', function () {
            pedir('ficha_ubicacion_quitar', { producto_ubicacion_id: $(this).data('id') }).done(function (r) { respuesta(r, listarUbicaciones); }).fail(fallo);
        });
    }

    // ---------------------------------------------------------------- Imágenes
    function listarImagenes() {
        if (!productoId) { $('#pficImgGaleria').html(''); return; }
        pedir('ficha_imagenes_listar', {}, 'GET').done(function (r) {
            var is = (r && r.success && r.data) || [];
            $('#pficImgGaleria').html(is.length ? is.map(function (x, n) {
                var principal = parseInt(x.es_principal, 10) === 1;
                return '<div class="col-6 col-md-3"><div class="card h-100' + (principal ? ' border-primary' : '') + '">'
                    + '<a href="' + esc(x.imagen_url) + '" target="_blank" rel="noopener"><img src="' + esc(x.miniatura_url) + '" class="card-img-top" style="height: 120px; object-fit: contain; background: #f8f9fa;" alt="' + esc(x.descripcion || x.imagen_nombre) + '"></a>'
                    + '<div class="card-body p-2 small">' + (principal ? '<span class="badge bg-primary mb-1">Principal</span><br>' : '')
                    + '<span class="pfic-img-desc">' + esc(x.descripcion || '') + '</span></div>'
                    + (ro ? '' : '<div class="card-footer p-1 d-flex flex-wrap gap-1 justify-content-center">'
                        + (principal ? '' : '<button type="button" class="btn btn-sm btn-outline-primary pfic-img-principal" data-id="' + x.producto_imagen_id + '" title="Hacer principal"><i class="fas fa-star"></i></button>')
                        + '<button type="button" class="btn btn-sm btn-outline-secondary pfic-img-mover" data-id="' + x.producto_imagen_id + '" data-sentido="-1" title="Antes"' + (n === 0 ? ' disabled' : '') + '><i class="fas fa-arrow-left"></i></button>'
                        + '<button type="button" class="btn btn-sm btn-outline-secondary pfic-img-mover" data-id="' + x.producto_imagen_id + '" data-sentido="1" title="Después"' + (n === is.length - 1 ? ' disabled' : '') + '><i class="fas fa-arrow-right"></i></button>'
                        + '<button type="button" class="btn btn-sm btn-outline-secondary pfic-img-editar" data-id="' + x.producto_imagen_id + '" data-desc="' + esc(x.descripcion || '') + '" title="Descripción"><i class="fas fa-pen"></i></button>'
                        + '<button type="button" class="btn btn-sm btn-outline-danger pfic-img-quitar" data-id="' + x.producto_imagen_id + '" title="Quitar"><i class="fas fa-trash"></i></button></div>')
                    + '</div></div>';
            }).join('') : '<div class="col-12 text-muted small p-2">El producto no tiene imágenes.</div>');
        }).fail(fallo);
    }
    function limpiarFormImagen() { $('#pficImgArchivo, #pficImgDesc').val(''); $('#pficImgPrincipal').prop('checked', false); }
    function subirImagen() {
        var archivo = $('#pficImgArchivo')[0] && $('#pficImgArchivo')[0].files[0];
        if (!archivo) { if (window.UiSimple) UiSimple.aviso('Elija la imagen.'); return; }
        var fd = new FormData();
        fd.append('accion', 'ficha_imagen_subir'); fd.append('empresa_idx', cfg.empresaIdx); fd.append('pagina_idx', cfg.paginaIdx);
        fd.append('producto_id', productoId); fd.append('imagen', archivo);
        fd.append('descripcion', $.trim($('#pficImgDesc').val())); fd.append('es_principal', $('#pficImgPrincipal').is(':checked') ? 1 : 0);
        var $b = $('#pficImgSubir').prop('disabled', true);
        $.ajax({ url: cfg.ajaxUrl, type: 'POST', dataType: 'json', data: fd, processData: false, contentType: false })
            .done(function (r) { respuesta(r, function () { limpiarFormImagen(); listarImagenes(); }); }).fail(fallo)
            .always(function () { $b.prop('disabled', false); });
    }
    function enlazarImagenes() {
        $('#pficImgSubir').on('click', subirImagen);
        $(document).on('click', '.pfic-img-principal', function () {
            pedir('ficha_imagen_principal', { producto_imagen_id: $(this).data('id') }).done(function (r) { respuesta(r, listarImagenes); }).fail(fallo);
        });
        $(document).on('click', '.pfic-img-mover', function () {
            pedir('ficha_imagen_mover', { producto_imagen_id: $(this).data('id'), sentido: $(this).data('sentido') }).done(function (r) { respuesta(r, listarImagenes); }).fail(fallo);
        });
        $(document).on('click', '.pfic-img-editar', function () {
            var id = $(this).data('id'), actual = String($(this).data('desc') || '');
            var grabar = function (desc) { pedir('ficha_imagen_editar', { producto_imagen_id: id, descripcion: desc }).done(function (r) { respuesta(r, listarImagenes); }).fail(fallo); };
            if (window.Swal) {
                Swal.fire({ title: 'Descripción de la imagen', input: 'text', inputValue: actual, inputAttributes: { maxlength: 255 }, showCancelButton: true, confirmButtonText: 'Guardar', cancelButtonText: 'Cancelar' })
                    .then(function (x) { if (x.isConfirmed) grabar(x.value || ''); });
            } else { var d = window.prompt('Descripción de la imagen', actual); if (d !== null) grabar(d); }
        });
        $(document).on('click', '.pfic-img-quitar', function () {
            var id = $(this).data('id');
            var seguir = function () { pedir('ficha_imagen_quitar', { producto_imagen_id: id }).done(function (r) { respuesta(r, listarImagenes); }).fail(fallo); };
            if (window.Swal) {
                Swal.fire({ icon: 'question', text: '¿Quitar esta imagen del producto?', showCancelButton: true, confirmButtonText: 'Quitar', cancelButtonText: 'Cancelar' })
                    .then(function (x) { if (x.isConfirmed) seguir(); });
            } else if (window.confirm('¿Quitar esta imagen del producto?')) { seguir(); }
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
        $('#pficProvForm, #pficPreForm, #pficUbiForm, #pficImgForm').toggleClass('d-none', ro);
        habilitar(productoId > 0);
        limpiarFormProveedor();
        limpiarFormPrecio();
        limpiarFormUbicacion();
        cargarCombo();
        cargarListas();
        cargarOpcionesUbicacion();
        listarProveedores();
        listarPrecios();
        listarUbicaciones();
        limpiarFormImagen();
        listarImagenes();
    }
    function limpiar() {
        if (!activo()) return;
        productoId = 0;
        ro = !!cfg.soloLectura;
        $('#pficProvForm, #pficPreForm, #pficUbiForm, #pficImgForm').toggleClass('d-none', ro);
        habilitar(false);
        limpiarFormImagen();
        $('#pficImgGaleria').html('');
        limpiarFormProveedor();
        limpiarFormPrecio();
        limpiarFormUbicacion();
        $('#pficProvFilas, #pficPreFilas, #pficUbiFilas').html('');
        bootstrap.Tab.getOrCreateInstance(document.querySelector('#pficSolapas [data-bs-target="#pficInfo"]')).show();
    }
    function init(opciones) {
        $.extend(cfg, opciones || {});
        if (!activo()) return;
        enlazarProveedores();
        enlazarPrecios();
        enlazarUbicaciones();
        enlazarImagenes();
        habilitar(false);
    }

    return { init: init, activo: activo, cargar: cargar, limpiar: limpiar };
})(jQuery);
