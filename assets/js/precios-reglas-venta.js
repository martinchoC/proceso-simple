/**
 * Escalas y promociones (página 146 / 646). Vista: templates/precios_reglas_venta/pagina.php.
 * Backend: precios_reglas_venta_ajax.php del módulo → prva_despachar (core/precios_venta_abm.php).
 * Uso: PrecioReglas.init({ ajaxUrl: 'precios_reglas_venta_ajax.php', empresaIdx })
 */
(function (window, $) {
    'use strict';

    var cfg = { ajaxUrl: 'precios_reglas_venta_ajax.php', empresaIdx: 0 };
    var cat = { categorias: [], listas: [], canales: [], sucursales: [] };
    var reglas = [];
    var timerBusqueda = null;

    function esc(t) {
        if (t === null || t === undefined) return '';
        return String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function num(v, d) { return window.Fmt && Fmt.numero ? Fmt.numero(v, d === undefined ? 2 : d) : String(v); }
    function fecha(v) { return v ? (window.Fmt && Fmt.fecha ? Fmt.fecha(v) : v) : ''; }
    function ok(m) { if (window.UiSimple) UiSimple.ok(m || 'Guardado'); }
    function error(m) { if (window.UiSimple) UiSimple.error(m, 'No se pudo guardar'); else Swal.fire({ icon: 'error', text: m }); }
    function hoy() { var d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }

    function pedir(accion, datos, metodo) {
        return $.ajax({ url: cfg.ajaxUrl, type: metodo || 'POST', dataType: 'json',
            data: $.extend({ accion: accion, empresa_idx: cfg.empresaIdx }, datos || {}) });
    }
    function fallo(x) {
        error((x && x.responseJSON && (x.responseJSON.message || x.responseJSON.error)) || 'No se pudo completar.');
    }

    function opciones(lista, vacio) {
        return '<option value="">' + esc(vacio) + '</option>' + lista.map(function (o) {
            return '<option value="' + o.id + '">' + esc(o.nombre) + '</option>';
        }).join('');
    }

    function cargarCatalogos() {
        return pedir('catalogos', {}, 'GET').done(function (r) {
            if (!r || !r.success) return;
            cat = r.data;
            $('#prvaCategoria').html(opciones(cat.categorias, 'Todas'));
            $('#prvaLista').html(opciones(cat.listas, 'Todas'));
            $('#prvaCanal').html(opciones(cat.canales, 'Todos'));
            $('#prvaSucursal').html(opciones(cat.sucursales, 'Todas'));
            $('#prvaPruebaLista').html(opciones(cat.listas, 'La del cliente'));
            $('#prvaPruebaSucursal').html(opciones(cat.sucursales, '—'));
            pintarTopesLista();
        });
    }

    // ---------------------------------------------------------------- Reglas
    function textoDescuento(r) {
        if (r.tipo === 'PROMOCION') return num(r.descuento_pct) + ' %';
        return (r.tramos || []).map(function (t) { return 'desde ' + num(t.cantidad_desde, 0) + ' u: ' + num(t.descuento_pct) + ' %'; }).join('<br>');
    }

    function listar() {
        pedir('listar', {
            tipo: $('#prvaFiltroTipo').val(), estado: $('#prvaFiltroEstado').val(),
            vigentes: $('#prvaFiltroVigentes').is(':checked') ? 1 : 0, q: $('#prvaFiltroQ').val()
        }, 'GET').done(function (r) {
            reglas = (r && r.success) ? r.data.reglas : [];
            if (!reglas.length) {
                $('#prvaFilas').html('<tr><td colspan="7" class="text-muted small p-3">No hay reglas con esos filtros.</td></tr>');
                return;
            }
            $('#prvaFilas').html(reglas.map(function (g) {
                var activa = parseInt(g.tabla_estado_registro_id, 10) === 1;
                return '<tr data-id="' + g.regla_venta_id + '">' +
                    '<td>' + esc(g.nombre) + '</td>' +
                    '<td>' + (g.tipo === 'ESCALA' ? 'Escala' : 'Promoción') + '</td>' +
                    '<td class="small">' + esc(g.alcance_texto) + '</td>' +
                    '<td class="small">' + textoDescuento(g) + '</td>' +
                    '<td class="small">' + fecha(g.f_desde) + (g.f_hasta ? ' a ' + fecha(g.f_hasta) : ' en adelante') + '</td>' +
                    '<td>' + (activa ? '<span class="badge bg-success">Activa</span>' : '<span class="badge bg-secondary">Inactiva</span>') + '</td>' +
                    '<td class="text-nowrap">' +
                    '<button type="button" class="btn btn-sm btn-outline-primary prva-editar" data-funcion="editar">Editar</button> ' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary prva-estado" data-funcion="inhabilitar" data-estado="' + (activa ? 2 : 1) + '">' + (activa ? 'Inactivar' : 'Activar') + '</button>' +
                    '</td></tr>';
            }).join(''));
        }).fail(fallo);
    }

    function filaTramo(t) {
        return '<tr><td><input type="number" class="form-control form-control-sm prva-desde" min="0" step="any" value="' + esc(t ? t.cantidad_desde : '') + '"></td>' +
            '<td><input type="number" class="form-control form-control-sm prva-tpct" min="0" max="100" step="0.01" value="' + esc(t ? t.descuento_pct : '') + '"></td>' +
            '<td><button type="button" class="btn btn-sm btn-link text-danger prva-tramo-quitar" title="Quitar"><i class="fas fa-times"></i></button></td></tr>';
    }

    function verTipo() {
        var esc_ = $('#prvaTipo').val() === 'ESCALA';
        $('#prvaBloqueTramos').toggle(esc_);
        $('#prvaBloquePct').toggle(!esc_);
    }

    function fijarProducto(p, presId) {
        $('#prvaProductoId').val(p ? p.producto_id : '');
        $('#prvaProductoTexto').val(p ? ((p.producto_codigo || '') + ' - ' + (p.producto_nombre || '')) : '');
        var html = '<option value="">Cualquiera</option>';
        ((p && p.presentaciones) || []).forEach(function (x) { html += '<option value="' + x.presentacion_id + '">' + esc(x.presentacion_nombre) + '</option>'; });
        if (presId && !(p && (p.presentaciones || []).some(function (x) { return String(x.presentacion_id) === String(presId); }))) {
            html += '<option value="' + presId + '">Presentación ' + esc(presId) + '</option>';
        }
        $('#prvaPres').html(html).val(presId ? String(presId) : '');
        $('#prvaCategoria').prop('disabled', !!p);
    }

    function abrir(r) {
        $('#prvaId').val(r ? r.regla_venta_id : 0);
        $('#prvaModalTitulo').text(r ? 'Editar regla' : 'Nueva regla');
        $('#prvaTipo').val(r ? r.tipo : 'ESCALA');
        $('#prvaNombre').val(r ? r.nombre : '');
        $('#prvaDesde').val(r ? r.f_desde : hoy());
        $('#prvaHasta').val(r && r.f_hasta ? r.f_hasta : '');
        $('#prvaPct').val(r && r.descuento_pct !== null ? r.descuento_pct : '');
        $('#prvaPrioridad').val(r ? r.prioridad : 0);
        $('#prvaObs').val(r ? (r.observaciones || '') : '');
        $('#prvaCategoria').val(r && r.producto_categoria_id ? r.producto_categoria_id : '');
        $('#prvaLista').val(r && r.lista_precio_id ? r.lista_precio_id : '');
        $('#prvaCanal').val(r && r.canal_venta_id ? r.canal_venta_id : '');
        $('#prvaSucursal').val(r && r.sucursal_id ? r.sucursal_id : '');
        var tramos = r && r.tramos && r.tramos.length ? r.tramos : [null];
        $('#prvaTramos').html(tramos.map(filaTramo).join(''));
        fijarProducto(r && r.producto_id ? { producto_id: r.producto_id, producto_codigo: r.producto_codigo, producto_nombre: r.producto_nombre, presentaciones: [] } : null,
            r ? r.presentacion_id : null);
        if (r && r.producto_id) {
            // Trae las presentaciones del producto para el combo
            pedir('buscar_productos', { q: r.producto_codigo || r.producto_nombre }, 'GET').done(function (x) {
                var p = ((x && x.data) || []).find(function (y) { return String(y.producto_id) === String(r.producto_id); });
                if (p) fijarProducto(p, r.presentacion_id);
            });
        }
        verTipo();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('prvaModal')).show();
    }

    function guardar() {
        var id = parseInt($('#prvaId').val(), 10) || 0;
        var tramos = [];
        $('#prvaTramos tr').each(function () {
            tramos.push({ cantidad_desde: $(this).find('.prva-desde').val(), descuento_pct: $(this).find('.prva-tpct').val() });
        });
        var $b = $('#prvaGuardar').prop('disabled', true);
        pedir(id ? 'editar' : 'agregar', {
            regla_venta_id: id, tipo: $('#prvaTipo').val(), nombre: $('#prvaNombre').val(), f_desde: $('#prvaDesde').val(),
            f_hasta: $('#prvaHasta').val(), descuento_pct: $('#prvaPct').val(), prioridad: $('#prvaPrioridad').val(),
            observaciones: $('#prvaObs').val(), producto_id: $('#prvaProductoId').val(), presentacion_id: $('#prvaPres').val(),
            producto_categoria_id: $('#prvaProductoId').val() ? '' : $('#prvaCategoria').val(), lista_precio_id: $('#prvaLista').val(),
            canal_venta_id: $('#prvaCanal').val(), sucursal_id: $('#prvaSucursal').val(), tramos: JSON.stringify(tramos)
        }).done(function (r) {
            if (r && r.success) {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('prvaModal')).hide();
                ok(); listar();
            } else error((r && r.message) || 'No se pudo guardar.');
        }).fail(fallo).always(function () { $b.prop('disabled', false); });
    }

    // Buscador de productos (en el modal y en Probar)
    function buscador($input, $lista, alElegir) {
        $input.on('input', function () {
            clearTimeout(timerBusqueda);
            var q = $input.val().trim();
            if (q.length < 2) { $lista.empty(); return; }
            timerBusqueda = setTimeout(function () {
                pedir('buscar_productos', { q: q }, 'GET').done(function (r) {
                    var prods = (r && r.data) || [];
                    $lista.html(prods.map(function (p, i) {
                        return '<button type="button" class="list-group-item list-group-item-action small py-1" data-i="' + i + '">' + esc(p.producto_codigo) + ' - ' + esc(p.producto_nombre) + '</button>';
                    }).join('')).data('prods', prods);
                });
            }, 250);
        });
        $lista.on('click', 'button', function () {
            alElegir(($lista.data('prods') || [])[parseInt($(this).data('i'), 10)]);
            $lista.empty();
        });
    }

    // ---------------------------------------------------------------- Topes
    function pintarTopes(topes) {
        $('#prvaTopes').html((topes || []).map(function (t) {
            return '<tr data-perfil="' + t.empresa_perfil_id + '"><td>' + esc(t.empresa_perfil_nombre) + '</td>' +
                '<td><input type="number" class="form-control form-control-sm prva-tope" min="0" max="100" step="0.01" value="' + (t.tope_pct === null ? '' : t.tope_pct) + '" placeholder="0"></td>' +
                '<td><button type="button" class="btn btn-sm btn-primary prva-tope-guardar" data-funcion="editar">Guardar</button></td></tr>';
        }).join('') || '<tr><td colspan="3" class="text-muted small p-3">No hay perfiles en este módulo.</td></tr>');
    }
    function cargarTopes() { pedir('topes_listar', {}, 'GET').done(function (r) { if (r && r.success) pintarTopes(r.data); }); }

    function pintarTopesLista() {
        $('#prvaTopesLista').html((cat.listas || []).map(function (l) {
            return '<tr data-lista="' + l.id + '"><td>' + esc(l.nombre) + '</td>' +
                '<td><input type="number" class="form-control form-control-sm prva-tope-lista" min="0" max="100" step="0.01" value="' + (l.descuento_tope_pct === null ? '' : l.descuento_tope_pct) + '" placeholder="Sin tope"></td>' +
                '<td><button type="button" class="btn btn-sm btn-primary prva-tope-lista-guardar" data-funcion="editar">Guardar</button></td></tr>';
        }).join(''));
    }

    // ---------------------------------------------------------------- Probar
    var pruebaProducto = null;
    function calcular() {
        if (!pruebaProducto) { if (window.UiSimple) UiSimple.aviso('Elegí un producto.'); return; }
        pedir('vista_previa', {
            producto_id: pruebaProducto.producto_id, presentacion_id: $('#prvaPruebaPres').val(), cantidad: $('#prvaPruebaCant').val(),
            lista_precio_id: $('#prvaPruebaLista').val(), sucursal_id: $('#prvaPruebaSucursal').val(), fecha: $('#prvaPruebaFecha').val(),
            cliente_descuento_pct: $('#prvaPruebaCliente').val()
        }, 'GET').done(function (r) {
            if (!r || !r.success) { error((r && r.message) || 'No se pudo calcular.'); return; }
            var d = r.data;
            var html = '<div class="ui-kpi d-inline-block me-2"><div class="ui-kpi-etiqueta">Descuento automático</div><div class="ui-kpi-valor">' + num(d.auto_pct) + ' %</div>' +
                '<div class="ui-kpi-sub">' + esc(d.regla_nombre || (d.regla_tipo === 'NINGUNA' ? 'Sin descuento' : 'Descuento del cliente')) + (d.tope_lista !== null ? ' · tope de la lista ' + num(d.tope_lista) + ' %' : '') + '</div></div>';
            html += '<table class="table table-sm mt-3" style="max-width:520px"><thead><tr><th>Candidato</th><th>%</th></tr></thead><tbody>' +
                (d.candidatos || []).map(function (c, i) {
                    return '<tr' + (i === 0 ? ' class="table-success"' : '') + '><td>' + esc(c.nombre) + ' <span class="text-muted small">(' + esc(c.tipo.toLowerCase()) + ')</span></td><td>' + num(c.pct) + '</td></tr>';
                }).join('') + '</tbody></table>';
            $('#prvaPruebaResultado').html(html);
        }).fail(fallo);
    }

    var PrecioReglas = {
        init: function (o) {
            $.extend(cfg, o || {});
            $('#prvaPruebaFecha').val(hoy());
            cargarCatalogos().always(listar);
            cargarTopes();
            $('#prvaFiltroTipo, #prvaFiltroEstado, #prvaFiltroVigentes').on('change', listar);
            $('#prvaFiltroQ').on('input', function () { clearTimeout(timerBusqueda); timerBusqueda = setTimeout(listar, 300); });
            $('#prvaNueva').on('click', function () { abrir(null); });
            $('#prvaFilas').on('click', '.prva-editar', function () {
                var id = $(this).closest('tr').data('id');
                pedir('obtener', { regla_venta_id: id }, 'GET').done(function (r) { if (r && r.success) abrir(r.data); else error((r && r.message) || 'No se encontró.'); });
            });
            $('#prvaFilas').on('click', '.prva-estado', function () {
                pedir('estado', { regla_venta_id: $(this).closest('tr').data('id'), estado: $(this).data('estado') })
                    .done(function (r) { if (r && r.success) { ok(); listar(); } else error(r && r.message); }).fail(fallo);
            });
            $('#prvaTipo').on('change', verTipo);
            $('#prvaTramoMas').on('click', function () { $('#prvaTramos').append(filaTramo(null)); });
            $('#prvaTramos').on('click', '.prva-tramo-quitar', function () { $(this).closest('tr').remove(); });
            $('#prvaProductoQuitar').on('click', function () { fijarProducto(null, null); });
            buscador($('#prvaProductoTexto'), $('#prvaProductos'), function (p) { fijarProducto(p, null); });
            buscador($('#prvaPruebaProducto'), $('#prvaPruebaProductos'), function (p) {
                pruebaProducto = p;
                $('#prvaPruebaProducto').val(p.producto_codigo + ' - ' + p.producto_nombre);
                $('#prvaPruebaPres').html('<option value="0">Unidad</option>' + (p.presentaciones || []).map(function (x) {
                    return '<option value="' + x.presentacion_id + '">' + esc(x.presentacion_nombre) + '</option>';
                }).join(''));
            });
            $('#prvaGuardar').on('click', guardar);
            $('#prvaTopes').on('click', '.prva-tope-guardar', function () {
                var $tr = $(this).closest('tr');
                pedir('tope_guardar', { empresa_perfil_id: $tr.data('perfil'), tope_pct: $tr.find('.prva-tope').val() })
                    .done(function (r) { if (r && r.success) { pintarTopes(r.data); ok(); } else error(r && r.message); }).fail(fallo);
            });
            $('#prvaTopesLista').on('click', '.prva-tope-lista-guardar', function () {
                var $tr = $(this).closest('tr');
                pedir('lista_tope_guardar', { lista_precio_id: $tr.data('lista'), descuento_tope_pct: $tr.find('.prva-tope-lista').val() })
                    .done(function (r) { if (r && r.success) { cat.listas = r.data; pintarTopesLista(); ok(); } else error(r && r.message); }).fail(fallo);
            });
            $('#prvaPruebaCalcular').on('click', calcular);
        }
    };

    window.PrecioReglas = PrecioReglas;
})(window, jQuery);
