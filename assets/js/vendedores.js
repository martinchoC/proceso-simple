/**
 * Vendedores y carteras (página 147 / 647). Vista: templates/vendedores/pagina.php.
 * Backend: vendedores_ajax.php del módulo → vabm_despachar (core/vendedores_abm.php).
 * Uso: Vendedores.init({ ajaxUrl: 'vendedores_ajax.php', empresaIdx })
 */
(function (window, $) {
    'use strict';

    var cfg = { ajaxUrl: 'vendedores_ajax.php', empresaIdx: 0 };
    var vendedores = [];
    var usuarios = [];
    var perfiles = [];
    var altaEntidad = null;
    var usuarioVendedor = 0;
    var timers = {};

    function esc(t) {
        if (t === null || t === undefined) return '';
        return String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function fecha(v) { return v ? (window.Fmt && Fmt.fecha ? Fmt.fecha(v) : v) : ''; }
    function ok(m) { if (window.UiSimple) UiSimple.ok(m || 'Guardado'); }
    function error(m) { if (window.UiSimple) UiSimple.error(m, 'No se pudo guardar'); else Swal.fire({ icon: 'error', text: m }); }
    function aviso(m) { if (window.UiSimple) UiSimple.aviso(m); else Swal.fire({ icon: 'warning', text: m }); }
    function hoy() { var d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
    function nombre(e) { return (e.entidad_nombre || '') + (e.entidad_fantasia && e.entidad_fantasia !== e.entidad_nombre ? ' (' + e.entidad_fantasia + ')' : ''); }
    function modal(id) { return bootstrap.Modal.getOrCreateInstance(document.getElementById(id)); }
    function demorar(clave, fn) { clearTimeout(timers[clave]); timers[clave] = setTimeout(fn, 300); }

    function pedir(accion, datos, metodo) {
        return $.ajax({ url: cfg.ajaxUrl, type: metodo || 'POST', dataType: 'json',
            data: $.extend({ accion: accion, empresa_idx: cfg.empresaIdx }, datos || {}) });
    }
    function fallo(x) {
        error((x && x.responseJSON && (x.responseJSON.message || x.responseJSON.error)) || 'No se pudo completar.');
    }
    // Escritura: avisa, refresca y devuelve la promesa
    function grabar(accion, datos, despues) {
        return pedir(accion, datos).done(function (r) {
            if (!r || !r.success) { error((r && r.message) || 'No se pudo guardar.'); return; }
            ok(r.message);
            if (despues) despues(r);
        }).fail(fallo);
    }

    // ---------------------------------------------------------------- Vendedores
    function listar() {
        return pedir('listar', { incluir_bajas: $('#vabmBajas').is(':checked') ? 1 : 0 }, 'GET').done(function (r) {
            if (!r || !r.success) { $('#vabmFilas').html('<tr><td colspan="6" class="text-danger small p-3">' + esc((r && r.message) || 'Error') + '</td></tr>'); return; }
            vendedores = r.data.vendedores || [];
            pintarVendedores();
            llenarSelects();
        }).fail(fallo);
    }

    function pintarVendedores() {
        if (!vendedores.length) {
            $('#vabmFilas').html('<tr><td colspan="6" class="text-muted small p-3">Todavía no hay vendedores. Use "Nuevo vendedor".</td></tr>');
            return;
        }
        $('#vabmFilas').html(vendedores.map(function (v) {
            var activo = parseInt(v.activo, 10) === 1;
            var usuario = v.usuario_id ? esc(v.usuario_nombre || v.usuario) + ' <small class="text-muted">' + esc(v.usuario) + '</small>' : '<span class="text-muted">Sin usuario</span>';
            var acciones = activo
                ? '<button type="button" class="btn btn-sm btn-outline-primary vabm-ver-cartera" data-id="' + v.vendedor_entidad_id + '" title="Ver la cartera"><i class="fas fa-users"></i></button> '
                  + '<button type="button" class="btn btn-sm btn-outline-secondary vabm-usuario" data-id="' + v.vendedor_entidad_id + '" data-funcion="editar" title="Usuario del vendedor"><i class="fas fa-user-gear"></i></button> '
                  + '<button type="button" class="btn btn-sm btn-outline-danger vabm-baja" data-id="' + v.vendedor_entidad_id + '" data-funcion="inhabilitar" title="Dar de baja"><i class="fas fa-user-slash"></i></button>'
                : '';
            return '<tr><td>' + esc(nombre(v)) + '</td><td>' + esc(v.cuit || '') + '</td><td>' + usuario + '</td>'
                + '<td class="text-end">' + (parseInt(v.clientes, 10) || 0) + '</td>'
                + '<td>' + (activo ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Baja ' + esc(fecha(v.f_baja)) + '</span>') + '</td>'
                + '<td class="text-nowrap">' + acciones + '</td></tr>';
        }).join(''));
    }

    function opcionesVendedores(vacio, excluir) {
        return '<option value="">' + esc(vacio) + '</option>' + vendedores.filter(function (v) {
            return parseInt(v.activo, 10) === 1 && String(v.vendedor_entidad_id) !== String(excluir || '');
        }).map(function (v) { return '<option value="' + v.vendedor_entidad_id + '">' + esc(nombre(v)) + '</option>'; }).join('');
    }

    function llenarSelects() {
        var actual = $('#vabmCarteraVendedor').val();
        $('#vabmCarteraVendedor').html(opcionesVendedores('Elegir vendedor…')).val(actual || '');
        var sin = $('#vabmSinVendedor').val();
        $('#vabmSinVendedor').html(opcionesVendedores('Asignar a…')).val(sin || '');
    }

    function cargarUsuarios() {
        return pedir('usuarios', {}, 'GET').done(function (r) { if (r && r.success) usuarios = r.data || []; });
    }
    function cargarPerfiles() {
        return pedir('perfiles', {}, 'GET').done(function (r) { if (r && r.success) perfiles = r.data || []; });
    }
    function opcionesPerfiles() {
        if (!perfiles.length) return '<option value="">Sin perfiles disponibles en la empresa</option>';
        return '<option value="">Elegir perfil…</option>' + perfiles.map(function (p) {
            return '<option value="' + p.empresa_perfil_id + '">' + esc(p.empresa_perfil_nombre) + '</option>';
        }).join('');
    }
    function opcionesUsuarios(vendedorId) {
        return '<option value="0">Sin usuario</option>' + usuarios.map(function (u) {
            var otro = u.vendedor_entidad_id && String(u.vendedor_entidad_id) !== String(vendedorId || '');
            return '<option value="' + u.usuario_id + '">' + esc((u.usuario_nombre || u.usuario) + ' (' + u.usuario + ')') + (otro ? ' · ya es otro vendedor' : '') + '</option>';
        }).join('');
    }

    // Alta
    function abrirAlta() {
        altaEntidad = null;
        $('#vabmAltaBuscar, #vabmAltaNombre, #vabmAltaCuit, #vabmAltaUsuLogin, #vabmAltaUsuNombre, #vabmAltaUsuEmail, #vabmAltaUsuPass').val('');
        $('#vabmAltaResultados').empty();
        $('#vabmAltaElegida').empty();
        $('#vabmAltaNuevaDet, #vabmAltaNuevoUsuDet').prop('open', false);
        $('#vabmAltaUsuario').prop('disabled', false);
        cargarUsuarios().always(function () { $('#vabmAltaUsuario').html(opcionesUsuarios(0)); });
        cargarPerfiles().always(function () {
            $('#vabmAltaUsuPerfil').html(opcionesPerfiles());
            if (perfiles.length === 1) $('#vabmAltaUsuPerfil').val(String(perfiles[0].empresa_perfil_id));
            else {
                var ventas = perfiles.find(function (p) { return String(p.empresa_perfil_nombre).toLowerCase() === 'ventas'; });
                if (ventas) $('#vabmAltaUsuPerfil').val(String(ventas.empresa_perfil_id));
            }
        });
        modal('vabmModalAlta').show();
    }
    function buscarAlta() {
        var q = $('#vabmAltaBuscar').val();
        if (q.length < 2) { $('#vabmAltaResultados').empty(); return; }
        pedir('buscar_entidades', { q: q }, 'GET').done(function (r) {
            var filas = (r && r.success && r.data) || [];
            $('#vabmAltaResultados').html(filas.length ? filas.map(function (e) {
                var ya = parseInt(e.es_vendedor, 10) === 1;
                return '<button type="button" class="list-group-item list-group-item-action py-1 small vabm-alta-elegir" data-id="' + e.entidad_id + '"' + (ya ? ' disabled' : '') + '>'
                    + esc(nombre(e)) + ' <span class="text-muted">' + esc(e.cuit || '') + '</span>' + (ya ? ' <span class="badge bg-secondary">ya es vendedor</span>' : '') + '</button>';
            }).join('') : '<div class="list-group-item small text-muted">Sin resultados: puede crear una entidad nueva.</div>');
            $('#vabmAltaResultados').data('filas', filas);
        });
    }
    function guardarAlta() {
        var datos = { usuario_id: $('#vabmAltaUsuario').val() || 0, fecha: hoy() };
        if (altaEntidad) {
            datos.entidad_id = altaEntidad.entidad_id;
        } else {
            datos.entidad_nombre = $.trim($('#vabmAltaNombre').val());
            datos.cuit = $.trim($('#vabmAltaCuit').val());
            if (!datos.entidad_nombre) { aviso('Elija una entidad o escriba el nombre del vendedor.'); return; }
        }
        if ($('#vabmAltaNuevoUsuDet').prop('open')) {
            datos.crear_usuario = 1;
            datos.usuario_id = 0;
            datos.nuevo_usuario = $.trim($('#vabmAltaUsuLogin').val());
            datos.nuevo_usuario_nombre = $.trim($('#vabmAltaUsuNombre').val());
            datos.nuevo_usuario_email = $.trim($('#vabmAltaUsuEmail').val());
            datos.nuevo_usuario_password = $('#vabmAltaUsuPass').val() || '';
            datos.nuevo_usuario_perfil_id = $('#vabmAltaUsuPerfil').val() || 0;
            if (!datos.nuevo_usuario || /\s/.test(datos.nuevo_usuario)) { aviso('Escriba el usuario de ingreso sin espacios.'); return; }
            if (!datos.nuevo_usuario_nombre) { aviso('Escriba el nombre completo del nuevo usuario.'); return; }
            if (!datos.nuevo_usuario_email) { aviso('Escriba el email del nuevo usuario.'); return; }
            if (datos.nuevo_usuario_password.length < 6) { aviso('La contraseña debe tener al menos 6 caracteres.'); return; }
            if (!parseInt(datos.nuevo_usuario_perfil_id, 10)) { aviso('Elija el perfil del nuevo usuario en la empresa.'); return; }
        }
        grabar('alta', datos, function () { modal('vabmModalAlta').hide(); listar(); });
    }

    // Usuario
    function abrirUsuario(id) {
        var v = vendedores.find(function (x) { return String(x.vendedor_entidad_id) === String(id); });
        if (!v) return;
        usuarioVendedor = v.vendedor_entidad_id;
        $('#vabmUsuarioNombre').text(nombre(v));
        $('#vabmUsuarioFecha').val(hoy());
        cargarUsuarios().always(function () { $('#vabmUsuarioSel').html(opcionesUsuarios(v.vendedor_entidad_id)).val(v.usuario_id || 0); });
        modal('vabmModalUsuario').show();
    }
    function guardarUsuario() {
        grabar('ligar_usuario', { vendedor_entidad_id: usuarioVendedor, usuario_id: $('#vabmUsuarioSel').val() || 0, fecha: $('#vabmUsuarioFecha').val() },
            function () { modal('vabmModalUsuario').hide(); listar(); });
    }

    function baja(id) {
        var v = vendedores.find(function (x) { return String(x.vendedor_entidad_id) === String(id); });
        if (!v) return;
        Swal.fire({ icon: 'question', title: 'Dar de baja a ' + nombre(v) + '?', text: 'Deja de ser vendedor desde hoy y se desliga su usuario. Si tiene clientes, primero hay que pasarlos a otro vendedor.',
            showCancelButton: true, confirmButtonText: 'Dar de baja', cancelButtonText: 'Cancelar' }).then(function (res) {
            if (res.isConfirmed) grabar('baja', { vendedor_entidad_id: id, fecha: hoy() }, listar);
        });
    }

    // ---------------------------------------------------------------- Cartera
    function vendedorCartera() { return $('#vabmCarteraVendedor').val(); }

    function cargarCartera() {
        var id = vendedorCartera();
        $('#vabmMarcarTodos').prop('checked', false);
        $('#vabmQuitar').prop('disabled', true);
        if (!id) { $('#vabmCarteraFilas').html('<tr><td colspan="5" class="text-muted small p-3">Elija un vendedor.</td></tr>'); $('#vabmCarteraResumen').text(''); return; }
        pedir('cartera', { vendedor_entidad_id: id, fecha: $('#vabmCarteraFecha').val() }, 'GET').done(function (r) {
            var filas = (r && r.success && r.data) || [];
            $('#vabmCarteraResumen').text(filas.length + ' cliente(s)');
            $('#vabmCarteraFilas').html(filas.length ? filas.map(function (c) {
                return '<tr><td><input type="checkbox" class="form-check-input vabm-cli" value="' + c.cliente_entidad_id + '"></td>'
                    + '<td>' + esc(nombre(c)) + '</td><td>' + esc(c.cuit || '') + '</td><td>' + esc(fecha(c.f_desde)) + '</td>'
                    + '<td><button type="button" class="btn btn-sm btn-outline-secondary vabm-historial" data-id="' + c.cliente_entidad_id + '" data-nombre="' + esc(nombre(c)) + '" title="Historial de vendedores"><i class="fas fa-clock-rotate-left"></i></button></td></tr>';
            }).join('') : '<tr><td colspan="5" class="text-muted small p-3">Sin clientes a esa fecha.</td></tr>');
        }).fail(fallo);
    }

    function buscarCliente() {
        var q = $('#vabmBuscarCliente').val();
        if (q.length < 2) { $('#vabmBuscarResultados').empty(); return; }
        pedir('buscar_entidades', { q: q, rol: 'CLI' }, 'GET').done(function (r) {
            var filas = (r && r.success && r.data) || [];
            $('#vabmBuscarResultados').html(filas.length ? filas.map(function (e) {
                return '<button type="button" class="list-group-item list-group-item-action py-1 small vabm-asignar-uno" data-id="' + e.entidad_id + '">'
                    + '<i class="fas fa-plus text-primary me-1"></i>' + esc(nombre(e)) + ' <span class="text-muted">' + esc(e.cuit || '') + '</span></button>';
            }).join('') : '<div class="list-group-item small text-muted">Sin clientes con ese nombre o CUIT.</div>');
        });
    }

    function asignar(clientes, vendedor, desde, despues) {
        if (!vendedor) { aviso('Elija un vendedor.'); return; }
        if (!clientes.length) { aviso('Marque al menos un cliente.'); return; }
        grabar('asignar', { vendedor_entidad_id: vendedor, clientes: JSON.stringify(clientes), fecha: desde || hoy() }, despues);
    }

    function marcados(selector) {
        return $(selector + ':checked').map(function () { return parseInt(this.value, 10); }).get();
    }

    function quitar() {
        var ids = marcados('#vabmCarteraFilas .vabm-cli');
        if (!ids.length) return;
        Swal.fire({ icon: 'question', title: 'Quitar ' + ids.length + ' cliente(s) de la cartera?', text: 'Quedan sin vendedor desde la fecha elegida arriba (o hoy).',
            showCancelButton: true, confirmButtonText: 'Quitar', cancelButtonText: 'Cancelar' }).then(function (res) {
            if (res.isConfirmed) grabar('quitar', { clientes: JSON.stringify(ids), fecha: $('#vabmCarteraFecha').val() || hoy() }, function () { cargarCartera(); listar(); });
        });
    }

    function abrirPasar() {
        var id = vendedorCartera();
        if (!id) { aviso('Elija el vendedor cuya cartera quiere pasar.'); return; }
        $('#vabmPasarA').html(opcionesVendedores('Elegir vendedor…', id));
        $('#vabmPasarFecha').val(hoy());
        modal('vabmModalPasar').show();
    }
    function guardarPasar() {
        var a = $('#vabmPasarA').val();
        if (!a) { aviso('Elija el vendedor que recibe la cartera.'); return; }
        grabar('reasignar', { de: vendedorCartera(), a: a, fecha: $('#vabmPasarFecha').val() }, function () { modal('vabmModalPasar').hide(); cargarCartera(); listar(); });
    }

    function historial(id, nom) {
        $('#vabmHistorialNombre').text(nom);
        $('#vabmHistorialFilas').html('<tr><td colspan="4" class="text-muted small">Cargando…</td></tr>');
        modal('vabmModalHistorial').show();
        pedir('historial', { cliente_entidad_id: id }, 'GET').done(function (r) {
            var filas = (r && r.success && r.data) || [];
            $('#vabmHistorialFilas').html(filas.length ? filas.map(function (h) {
                var anulada = parseInt(h.tabla_estado_registro_id, 10) !== 1;
                return '<tr' + (anulada ? ' class="text-muted"' : '') + '><td>' + esc(h.vendedor_nombre) + '</td><td>' + esc(fecha(h.f_desde)) + '</td><td>'
                    + (h.f_hasta ? esc(fecha(h.f_hasta)) : 'vigente') + '</td><td>' + (anulada ? 'Anulada' : '') + '</td></tr>';
            }).join('') : '<tr><td colspan="4" class="text-muted small">Sin asignaciones.</td></tr>');
        });
    }

    // ---------------------------------------------------------------- Clientes sin vendedor
    function cargarSin() {
        $('#vabmSinTodos').prop('checked', false);
        $('#vabmSinAsignar').prop('disabled', true);
        pedir('sin_vendedor', { q: $('#vabmSinQ').val(), fecha: $('#vabmSinFecha').val() }, 'GET').done(function (r) {
            var filas = (r && r.success && r.data) || [];
            $('#vabmSinFilas').html(filas.length ? filas.map(function (c) {
                return '<tr><td><input type="checkbox" class="form-check-input vabm-sin" value="' + c.cliente_entidad_id + '"></td><td>' + esc(nombre(c)) + '</td><td>' + esc(c.cuit || '') + '</td></tr>';
            }).join('') : '<tr><td colspan="3" class="text-muted small p-3">Todos los clientes tienen vendedor a esa fecha.</td></tr>');
        }).fail(fallo);
    }

    // ---------------------------------------------------------------- Inicio
    function init(opciones) {
        $.extend(cfg, opciones || {});
        $('#vabmCarteraFecha, #vabmAsignarFecha, #vabmSinFecha').val(hoy());

        $('#vabmBajas').on('change', listar);
        $('#vabmNuevo').on('click', abrirAlta);
        $('#vabmAltaBuscar').on('input', function () { altaEntidad = null; $('#vabmAltaElegida').empty(); demorar('alta', buscarAlta); });
        $(document).on('click', '.vabm-alta-elegir', function () {
            var id = String($(this).data('id'));
            altaEntidad = ($('#vabmAltaResultados').data('filas') || []).find(function (e) { return String(e.entidad_id) === id; }) || null;
            $('#vabmAltaResultados').empty();
            $('#vabmAltaElegida').html(altaEntidad ? '<i class="fas fa-check text-success me-1"></i>' + esc(nombre(altaEntidad)) : '');
        });
        $('#vabmAltaNuevoUsuDet').on('toggle', function () {
            var abierto = $(this).prop('open');
            $('#vabmAltaUsuario').prop('disabled', abierto);
            if (abierto) {
                $('#vabmAltaUsuario').val('0');
                if (!$.trim($('#vabmAltaUsuNombre').val())) {
                    var sug = (altaEntidad && altaEntidad.entidad_nombre) || $.trim($('#vabmAltaNombre').val());
                    if (sug) $('#vabmAltaUsuNombre').val(sug);
                }
            }
        });
        $('#vabmAltaGuardar').on('click', guardarAlta);
        $(document).on('click', '.vabm-usuario', function () { abrirUsuario($(this).data('id')); });
        $('#vabmUsuarioGuardar').on('click', guardarUsuario);
        $(document).on('click', '.vabm-baja', function () { baja($(this).data('id')); });
        $(document).on('click', '.vabm-ver-cartera', function () {
            $('#vabmCarteraVendedor').val(String($(this).data('id')));
            bootstrap.Tab.getOrCreateInstance(document.getElementById('vabmTabCarteraBtn')).show();
            cargarCartera();
        });

        $('#vabmCarteraVendedor, #vabmCarteraFecha').on('change', cargarCartera);
        $('#vabmBuscarCliente').on('input', function () { demorar('cli', buscarCliente); });
        $(document).on('click', '.vabm-asignar-uno', function () {
            asignar([parseInt($(this).data('id'), 10)], vendedorCartera(), $('#vabmAsignarFecha').val(), function () {
                $('#vabmBuscarCliente').val(''); $('#vabmBuscarResultados').empty(); cargarCartera(); listar();
            });
        });
        $('#vabmMarcarTodos').on('change', function () { $('#vabmCarteraFilas .vabm-cli').prop('checked', this.checked).trigger('change'); });
        $(document).on('change', '.vabm-cli', function () { $('#vabmQuitar').prop('disabled', !marcados('#vabmCarteraFilas .vabm-cli').length); });
        $('#vabmQuitar').on('click', quitar);
        $('#vabmPasar').on('click', abrirPasar);
        $('#vabmPasarGuardar').on('click', guardarPasar);
        $(document).on('click', '.vabm-historial', function () { historial($(this).data('id'), $(this).data('nombre')); });

        $('#vabmTabSinBtn').on('shown.bs.tab', cargarSin);
        $('#vabmSinQ').on('input', function () { demorar('sin', cargarSin); });
        $('#vabmSinFecha').on('change', cargarSin);
        $('#vabmSinTodos').on('change', function () { $('#vabmSinFilas .vabm-sin').prop('checked', this.checked).trigger('change'); });
        $(document).on('change', '.vabm-sin', function () { $('#vabmSinAsignar').prop('disabled', !marcados('#vabmSinFilas .vabm-sin').length); });
        $('#vabmSinAsignar').on('click', function () {
            asignar(marcados('#vabmSinFilas .vabm-sin'), $('#vabmSinVendedor').val(), $('#vabmSinFecha').val(), function () { cargarSin(); listar(); });
        });

        listar();
    }

    window.Vendedores = { init: init };
})(window, jQuery);
