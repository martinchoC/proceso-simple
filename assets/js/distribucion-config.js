/**
 * Distribución - Configuración (página 148 / 648). Vista: templates/distribucion/config.php.
 * Backend: distribucion_config_ajax.php del módulo → vdcf_despachar (core/distribucion/distribucion_config_abm.php).
 * Uso: DistConfig.init({ ajaxUrl: 'distribucion_config_ajax.php', empresaIdx })
 */
(function (window, $) {
    'use strict';

    var cfg = { ajaxUrl: 'distribucion_config_ajax.php', empresaIdx: 0 };
    var zonas = [], camiones = [], depositos = [];
    var clienteSuc = null;
    var timers = {};
    var DIAS = [[1, 'Lun'], [2, 'Mar'], [3, 'Mié'], [4, 'Jue'], [5, 'Vie'], [6, 'Sáb'], [7, 'Dom']];

    function esc(t) {
        if (t === null || t === undefined) return '';
        return String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function num(v, d) { return v === null || v === undefined || v === '' ? '' : (window.Fmt && Fmt.numero ? Fmt.numero(v, d) : String(v)); }
    function fecha(v) { return v ? (window.Fmt && Fmt.fecha ? Fmt.fecha(v) : v) : ''; }
    function ok(m) { if (window.UiSimple) UiSimple.ok(m || 'Guardado'); }
    function error(m) { if (window.UiSimple) UiSimple.error(m, 'No se pudo guardar'); else Swal.fire({ icon: 'error', text: m }); }
    function aviso(m) { if (window.UiSimple) UiSimple.aviso(m); else Swal.fire({ icon: 'warning', text: m }); }
    function hoy() { var d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
    function nombre(e) { return (e.entidad_nombre || '') + (e.entidad_fantasia && e.entidad_fantasia !== e.entidad_nombre ? ' (' + e.entidad_fantasia + ')' : ''); }
    function modal(id) { return bootstrap.Modal.getOrCreateInstance(document.getElementById(id)); }
    function demorar(k, fn) { clearTimeout(timers[k]); timers[k] = setTimeout(fn, 300); }
    function marcados(sel) { return $(sel + ':checked').map(function () { return parseInt(this.value, 10); }).get(); }

    function pedir(accion, datos, metodo) {
        return $.ajax({ url: cfg.ajaxUrl, type: metodo || 'POST', dataType: 'json', data: $.extend({ accion: accion, empresa_idx: cfg.empresaIdx }, datos || {}) });
    }
    function fallo(x) { error((x && x.responseJSON && (x.responseJSON.message || x.responseJSON.error)) || 'No se pudo completar.'); }
    function grabar(accion, datos, despues) {
        return pedir(accion, datos).done(function (r) {
            if (!r || !r.success) { error((r && r.message) || 'No se pudo guardar.'); return; }
            ok(r.message);
            if (despues) despues(r);
        }).fail(fallo);
    }

    // ---------------------------------------------------------------- Zonas
    function listarZonas() {
        return pedir('zonas_listar', { incluir_inactivas: $('#dcfZonasInactivas').is(':checked') ? 1 : 0 }, 'GET').done(function (r) {
            zonas = (r && r.success && r.data) || [];
            $('#dcfZonasFilas').html(zonas.length ? zonas.map(function (z) {
                var activa = parseInt(z.tabla_estado_registro_id, 10) === 1;
                return '<tr><td>' + esc(z.nombre) + '</td><td>' + esc(z.codigo || '') + '</td><td>' + esc(z.dias_texto || '') + '</td>'
                    + '<td class="text-end">' + (parseInt(z.clientes, 10) || 0) + '</td>'
                    + '<td>' + (activa ? '<span class="badge bg-success">Activa</span>' : '<span class="badge bg-secondary">Inactiva</span>') + '</td>'
                    + '<td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-secondary dcf-zona-editar" data-id="' + z.zona_id + '" data-funcion="editar" title="Editar"><i class="fas fa-pen"></i></button> '
                    + '<button type="button" class="btn btn-sm btn-outline-' + (activa ? 'danger' : 'success') + ' dcf-zona-estado" data-id="' + z.zona_id + '" data-estado="' + (activa ? 2 : 1) + '" data-funcion="inhabilitar" title="' + (activa ? 'Inactivar' : 'Activar') + '"><i class="fas fa-' + (activa ? 'ban' : 'check') + '"></i></button></td></tr>';
            }).join('') : '<tr><td colspan="6" class="text-muted small p-3">Todavía no hay zonas.</td></tr>');
            var activas = zonas.filter(function (z) { return parseInt(z.tabla_estado_registro_id, 10) === 1; });
            var opts = activas.map(function (z) { return '<option value="' + z.zona_id + '">' + esc(z.nombre) + '</option>'; }).join('');
            var sel = $('#dcfZonaSel').val(), sin = $('#dcfSinZona').val();
            $('#dcfZonaSel').html('<option value="">Elegir zona…</option>' + opts).val(sel || '');
            $('#dcfSinZona').html('<option value="">Zona…</option>' + opts).val(sin || '');
        }).fail(fallo);
    }
    function abrirZona(z) {
        $('#dcfZonaTitulo').text(z ? 'Editar zona' : 'Nueva zona');
        $('#dcfZonaId').val(z ? z.zona_id : '');
        $('#dcfZonaNombre').val(z ? z.nombre : '');
        $('#dcfZonaCodigo').val(z ? (z.codigo || '') : '');
        $('#dcfZonaOrden').val(z ? z.orden : 0);
        var dias = z && z.dias_reparto ? String(z.dias_reparto).split(',') : [];
        $('#dcfZonaDias').html(DIAS.map(function (d) {
            return '<div class="form-check form-check-inline"><input class="form-check-input dcf-dia" type="checkbox" id="dcfDia' + d[0] + '" value="' + d[0] + '"' + (dias.indexOf(String(d[0])) >= 0 ? ' checked' : '') + '>'
                + '<label class="form-check-label small" for="dcfDia' + d[0] + '">' + d[1] + '</label></div>';
        }).join(''));
        modal('dcfModalZona').show();
    }
    function guardarZona() {
        var id = $('#dcfZonaId').val();
        grabar(id ? 'zona_editar' : 'zona_agregar', {
            zona_id: id || 0, nombre: $.trim($('#dcfZonaNombre').val()), codigo: $.trim($('#dcfZonaCodigo').val()),
            orden: $('#dcfZonaOrden').val() || 0, dias_reparto: marcados('#dcfZonaDias .dcf-dia').join(',')
        }, function () { modal('dcfModalZona').hide(); listarZonas(); });
    }

    // ---------------------------------------------------------------- Clientes por zona
    function cargarZonaClientes() {
        var z = $('#dcfZonaSel').val();
        $('#dcfMarcarTodos').prop('checked', false);
        $('#dcfQuitar').prop('disabled', true);
        if (!z) { $('#dcfZonaClientesFilas').html('<tr><td colspan="4" class="text-muted small p-3">Elija una zona.</td></tr>'); $('#dcfZonaResumen').text(''); return; }
        pedir('zona_clientes', { zona_id: z }, 'GET').done(function (r) {
            var filas = (r && r.success && r.data) || [];
            $('#dcfZonaResumen').text(filas.length + ' asignación(es)');
            $('#dcfZonaClientesFilas').html(filas.length ? filas.map(function (c) {
                return '<tr><td><input type="checkbox" class="form-check-input dcf-cli" value="' + c.cliente_entidad_id + '"' + (c.entidad_sucursal_id ? ' disabled title="Asignación de una sucursal: se cambia agregando el cliente a otra zona"' : '') + '></td>'
                    + '<td>' + esc(nombre(c)) + '</td><td>' + (c.entidad_sucursal_id ? esc(c.sucursal_nombre) : '<span class="text-muted">Todas</span>') + '</td><td>' + esc(fecha(c.f_desde)) + '</td></tr>';
            }).join('') : '<tr><td colspan="4" class="text-muted small p-3">Sin clientes en la zona.</td></tr>');
        }).fail(fallo);
    }
    function buscarCliente() {
        var q = $('#dcfBuscarCliente').val();
        if (q.length < 2) { $('#dcfBuscarResultados').empty(); return; }
        pedir('buscar_clientes', { q: q }, 'GET').done(function (r) {
            var filas = (r && r.success && r.data) || [];
            $('#dcfBuscarResultados').data('filas', filas).html(filas.length ? filas.map(function (e) {
                return '<button type="button" class="list-group-item list-group-item-action py-1 small dcf-agregar-cli" data-id="' + e.entidad_id + '">'
                    + '<i class="fas fa-plus text-primary me-1"></i>' + esc(nombre(e)) + ' <span class="text-muted">' + esc(e.cuit || '') + '</span>'
                    + (e.zona_nombre ? ' <span class="badge bg-light text-dark border">' + esc(e.zona_nombre) + '</span>' : '') + '</button>';
            }).join('') : '<div class="list-group-item small text-muted">Sin clientes con ese nombre o CUIT.</div>');
        });
    }
    function elegirCliente(id) {
        if (!$('#dcfZonaSel').val()) { aviso('Elija la zona.'); return; }
        var e = ($('#dcfBuscarResultados').data('filas') || []).find(function (x) { return String(x.entidad_id) === String(id); });
        clienteSuc = id;
        $('#dcfSucCliente').text(e ? nombre(e) : '');
        pedir('sucursales_cliente', { cliente_entidad_id: id }, 'GET').done(function (r) {
            var sucs = (r && r.success && r.data) || [];
            if (sucs.length <= 1) { asignar([id], 0); return; }
            $('#dcfSucSel').html('<option value="0">Todas sus sucursales</option>' + sucs.map(function (s) {
                return '<option value="' + s.sucursal_id + '">Solo ' + esc(s.sucursal_nombre) + (s.sucursal_direccion ? ' · ' + esc(s.sucursal_direccion) : '') + '</option>';
            }).join(''));
            modal('dcfModalSucursal').show();
        });
    }
    function asignar(ids, sucursal, zona, desde, despues) {
        grabar('zona_asignar', { zona_id: zona || $('#dcfZonaSel').val(), clientes: JSON.stringify(ids), entidad_sucursal_id: sucursal || 0,
            fecha: desde || $('#dcfAsignarFecha').val() || hoy() }, despues || function () {
            $('#dcfBuscarCliente').val(''); $('#dcfBuscarResultados').empty(); cargarZonaClientes(); listarZonas();
        });
    }
    function quitar() {
        var ids = marcados('#dcfZonaClientesFilas .dcf-cli');
        if (!ids.length) return;
        Swal.fire({ icon: 'question', title: 'Quitar ' + ids.length + ' cliente(s) de la zona?', text: 'Quedan sin zona desde hoy.', showCancelButton: true,
            confirmButtonText: 'Quitar', cancelButtonText: 'Cancelar' }).then(function (res) {
            if (res.isConfirmed) grabar('zona_quitar', { clientes: JSON.stringify(ids), fecha: hoy() }, function () { cargarZonaClientes(); listarZonas(); });
        });
    }

    // ---------------------------------------------------------------- Sin zona
    function cargarSin() {
        $('#dcfSinTodos').prop('checked', false);
        $('#dcfSinAsignar').prop('disabled', true);
        pedir('sin_zona', { q: $('#dcfSinQ').val() }, 'GET').done(function (r) {
            var filas = (r && r.success && r.data) || [];
            $('#dcfSinFilas').html(filas.length ? filas.map(function (c) {
                return '<tr><td><input type="checkbox" class="form-check-input dcf-sin" value="' + c.cliente_entidad_id + '"></td><td>' + esc(nombre(c)) + '</td><td>' + esc(c.cuit || '') + '</td></tr>';
            }).join('') : '<tr><td colspan="3" class="text-muted small p-3">Todos los clientes tienen zona.</td></tr>');
        }).fail(fallo);
    }

    // ---------------------------------------------------------------- Camiones
    function listarCamiones() {
        return pedir('camiones_listar', { incluir_inactivos: $('#dcfCamionesInactivos').is(':checked') ? 1 : 0 }, 'GET').done(function (r) {
            camiones = (r && r.success && r.data) || [];
            $('#dcfCamionesFilas').html(camiones.length ? camiones.map(function (c) {
                var activo = parseInt(c.tabla_estado_registro_id, 10) === 1;
                return '<tr><td>' + esc(c.nombre) + '</td><td>' + esc(c.patente || '') + '</td><td class="text-end">' + num(c.capacidad_kg, 0) + '</td><td class="text-end">' + num(c.capacidad_m3, 2) + '</td>'
                    + '<td>' + esc(c.boca_nombre || '') + '</td><td>' + esc(c.chofer || '') + '</td>'
                    + '<td>' + (activo ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>') + '</td>'
                    + '<td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-secondary dcf-camion-editar" data-id="' + c.camion_id + '" data-funcion="editar" title="Editar"><i class="fas fa-pen"></i></button> '
                    + '<button type="button" class="btn btn-sm btn-outline-' + (activo ? 'danger' : 'success') + ' dcf-camion-estado" data-id="' + c.camion_id + '" data-estado="' + (activo ? 2 : 1) + '" data-funcion="inhabilitar" title="' + (activo ? 'Inactivar' : 'Activar') + '"><i class="fas fa-' + (activo ? 'ban' : 'check') + '"></i></button></td></tr>';
            }).join('') : '<tr><td colspan="8" class="text-muted small p-3">Todavía no hay camiones.</td></tr>');
        }).fail(fallo);
    }
    function abrirCamion(c) {
        $('#dcfCamionTitulo').text(c ? 'Editar camión' : 'Nuevo camión');
        $('#dcfCamionId').val(c ? c.camion_id : '');
        $('#dcfCamionNombre').val(c ? c.nombre : '');
        $('#dcfCamionPatente').val(c ? (c.patente || '') : '');
        $('#dcfCamionKg').val(c ? (c.capacidad_kg || '') : '');
        $('#dcfCamionM3').val(c ? (c.capacidad_m3 || '') : '');
        $('#dcfCamionChofer').val(c ? (c.chofer || '') : '');
        $('#dcfCamionBoca').html('<option value="">—</option>' + depositos.map(function (d) {
            return '<option value="' + d.boca_id + '">' + esc(d.boca_nombre) + (d.sucursal_nombre ? ' · ' + esc(d.sucursal_nombre) : '') + '</option>';
        }).join('')).val(c && c.boca_id ? String(c.boca_id) : '');
        modal('dcfModalCamion').show();
    }
    function guardarCamion() {
        var id = $('#dcfCamionId').val();
        grabar(id ? 'camion_editar' : 'camion_agregar', {
            camion_id: id || 0, nombre: $.trim($('#dcfCamionNombre').val()), patente: $.trim($('#dcfCamionPatente').val()),
            capacidad_kg: $('#dcfCamionKg').val(), capacidad_m3: $('#dcfCamionM3').val(), boca_id: $('#dcfCamionBoca').val() || 0, chofer: $.trim($('#dcfCamionChofer').val())
        }, function () { modal('dcfModalCamion').hide(); listarCamiones(); });
    }

    // ---------------------------------------------------------------- Inicio
    function init(opciones) {
        $.extend(cfg, opciones || {});
        $('#dcfAsignarFecha').val(hoy());
        pedir('catalogos', {}, 'GET').done(function (r) { if (r && r.success) depositos = r.data.depositos || []; });

        $('#dcfZonasInactivas').on('change', listarZonas);
        $('#dcfZonaNueva').on('click', function () { abrirZona(null); });
        $(document).on('click', '.dcf-zona-editar', function () { var id = String($(this).data('id')); abrirZona(zonas.find(function (z) { return String(z.zona_id) === id; })); });
        $(document).on('click', '.dcf-zona-estado', function () { grabar('zona_estado', { zona_id: $(this).data('id'), estado: $(this).data('estado') }, listarZonas); });
        $('#dcfZonaGuardar').on('click', guardarZona);

        $('#dcfZonaSel').on('change', cargarZonaClientes);
        $('#dcfTabClientesBtn').on('shown.bs.tab', cargarZonaClientes);
        $('#dcfBuscarCliente').on('input', function () { demorar('cli', buscarCliente); });
        $(document).on('click', '.dcf-agregar-cli', function () { elegirCliente($(this).data('id')); });
        $('#dcfSucGuardar').on('click', function () { modal('dcfModalSucursal').hide(); asignar([clienteSuc], parseInt($('#dcfSucSel').val(), 10) || 0); });
        $('#dcfMarcarTodos').on('change', function () { $('#dcfZonaClientesFilas .dcf-cli:not(:disabled)').prop('checked', this.checked).trigger('change'); });
        $(document).on('change', '.dcf-cli', function () { $('#dcfQuitar').prop('disabled', !marcados('#dcfZonaClientesFilas .dcf-cli').length); });
        $('#dcfQuitar').on('click', quitar);

        $('#dcfTabSinBtn').on('shown.bs.tab', cargarSin);
        $('#dcfSinQ').on('input', function () { demorar('sin', cargarSin); });
        $('#dcfSinTodos').on('change', function () { $('#dcfSinFilas .dcf-sin').prop('checked', this.checked).trigger('change'); });
        $(document).on('change', '.dcf-sin', function () { $('#dcfSinAsignar').prop('disabled', !marcados('#dcfSinFilas .dcf-sin').length); });
        $('#dcfSinAsignar').on('click', function () {
            var z = $('#dcfSinZona').val();
            if (!z) { aviso('Elija la zona.'); return; }
            asignar(marcados('#dcfSinFilas .dcf-sin'), 0, z, hoy(), function () { cargarSin(); listarZonas(); });
        });

        $('#dcfTabCamionesBtn').on('shown.bs.tab', listarCamiones);
        $('#dcfCamionesInactivos').on('change', listarCamiones);
        $('#dcfCamionNuevo').on('click', function () { abrirCamion(null); });
        $(document).on('click', '.dcf-camion-editar', function () { var id = String($(this).data('id')); abrirCamion(camiones.find(function (c) { return String(c.camion_id) === id; })); });
        $(document).on('click', '.dcf-camion-estado', function () { grabar('camion_estado', { camion_id: $(this).data('id'), estado: $(this).data('estado') }, listarCamiones); });
        $('#dcfCamionGuardar').on('click', guardarCamion);

        listarZonas();
    }

    window.DistConfig = { init: init };
})(window, jQuery);
