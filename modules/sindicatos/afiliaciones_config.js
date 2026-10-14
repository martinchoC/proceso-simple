/**
 * modules/sindicatos/afiliaciones_config.js
 * Tipos de afiliación, parentescos y vigencias por edad (Página 1054).
 */
$(document).ready(function () {
    const URL_AJAX = 'afiliaciones_config_ajax.php';
    let catalogos = { parentescos: [], vigencias: [] };
    let puedeEditar = false;
    let tiposCache = {};

    function esc(v) {
        return $('<div>').text(v == null ? '' : String(v)).html();
    }
    function estadoBadge(e) {
        return parseInt(e) === 1 ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>';
    }
    function aviso(res, recargar) {
        if (res.success) {
            Swal.fire({ icon: 'success', title: 'Guardado', text: res.message, timer: 1500, showConfirmButton: false });
            recargar();
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.message });
        }
    }

    const tablaTipos = $('#tablaTipos').DataTable({
        ajax: URL_AJAX + '?accion=listar',
        paging: false, searching: false, info: false,
        columns: [
            { data: 'codigo', render: (d) => '<code>' + esc(d) + '</code>' },
            { data: 'nombre', render: (d, t, r) => { tiposCache[r.afiliacion_tipo_id] = r; return esc(d); } },
            { data: 'grupo' },
            { data: 'alta' },
            { data: 'importe_cuota', className: 'text-end', render: (d, t, r) => parseInt(r.paga_cuota_directa) === 1 ? '$ ' + parseFloat(d).toLocaleString('es-AR', { minimumFractionDigits: 2 }) : '-' },
            { data: 'tabla_estado_registro_id', render: (d) => estadoBadge(d) },
            { data: 'acciones', className: 'text-end', orderable: false, render: (d) => d || '' }
        ]
    });

    $('#tablaTipos').on('click', '.btn-accion', function () {
        const id = $(this).data('id');
        const accion = $(this).data('accion');
        if (accion === 'editar') {
            const t = tiposCache[id];
            $('#afiliacion_tipo_id').val(id);
            $('#tipo_nombre').val(t.nombre);
            $('#paga_cuota_directa').prop('checked', parseInt(t.paga_cuota_directa) === 1).prop('disabled', t.grupo === 'FAMILIAR');
            $('#importe_cuota').val(t.importe_cuota).prop('disabled', t.grupo === 'FAMILIAR');
            $('#modalTipo').modal('show');
        } else {
            $.post(URL_AJAX, { accion: 'ejecutar_accion', id: id, accion_js: accion }, (res) => aviso(res, () => tablaTipos.ajax.reload()), 'json');
        }
    });

    $('#formTipo').on('submit', function (e) {
        e.preventDefault();
        $.post(URL_AJAX, $(this).serialize() + '&accion=guardar_tipo', function (res) {
            if (res.success) $('#modalTipo').modal('hide');
            aviso(res, () => tablaTipos.ajax.reload());
        }, 'json');
    });

    function cargarCatalogos() {
        $.get(URL_AJAX, { accion: 'catalogos' }, function (res) {
            catalogos = res.data || catalogos;
            puedeEditar = !!res.puede_editar;
            $('.solo-editor').toggleClass('d-none', !puedeEditar);
            const btnEd = (cls, id) => puedeEditar ? '<button type="button" class="btn btn-sm btn-outline-primary ' + cls + '" data-id="' + id + '">Editar</button>' : '';

            const tp = $('#tablaParentescos tbody').empty();
            catalogos.parentescos.forEach(p => tp.append('<tr><td><code>' + esc(p.codigo) + '</code></td><td>' + esc(p.nombre) + '</td><td>' +
                esc(p.inverso_nombre || '-') + '</td><td>' + estadoBadge(p.tabla_estado_registro_id) + '</td><td class="text-end">' + btnEd('btn-ed-par', p.parentesco_id) + '</td></tr>'));

            const tv = $('#tablaVigencias tbody').empty();
            catalogos.vigencias.forEach(v => tv.append('<tr><td>' + esc(v.parentesco) + '</td><td>' + (v.edad_maxima !== null ? v.edad_maxima + ' años' : 'Sin límite') +
                '</td><td>' + (v.edad_maxima_excepcion !== null ? v.edad_maxima_excepcion + ' años' : '-') + '</td><td>' + esc(v.documento_excepcion || '-') +
                '</td><td>' + v.meses_validez_documento + ' meses</td><td>' + estadoBadge(v.tabla_estado_registro_id) + '</td><td class="text-end">' +
                btnEd('btn-ed-vig', v.parentesco_id) + '</td></tr>'));

            const inv = $('#par_inverso').find('option:not(:first)').remove().end();
            const vp = $('#vig_parentesco').empty();
            catalogos.parentescos.forEach(p => {
                inv.append($('<option>', { value: p.parentesco_id, text: p.nombre }));
                vp.append($('<option>', { value: p.parentesco_id, text: p.nombre }));
            });
        }, 'json');
    }
    cargarCatalogos();

    $('#btnNuevoParentesco').on('click', function () {
        $('#formParentesco')[0].reset();
        $('#parentesco_id').val('');
        $('#modalParentesco').modal('show');
    });
    $('#tablaParentescos').on('click', '.btn-ed-par', function () {
        const p = catalogos.parentescos.find(x => parseInt(x.parentesco_id) === parseInt($(this).data('id')));
        $('#parentesco_id').val(p.parentesco_id);
        $('#par_codigo').val(p.codigo);
        $('#par_nombre').val(p.nombre);
        $('#par_inverso').val(p.parentesco_inverso_id || '');
        $('#par_estado').val(p.tabla_estado_registro_id);
        $('#modalParentesco').modal('show');
    });
    $('#formParentesco').on('submit', function (e) {
        e.preventDefault();
        $.post(URL_AJAX, $(this).serialize() + '&accion=guardar_parentesco', function (res) {
            if (res.success) $('#modalParentesco').modal('hide');
            aviso(res, cargarCatalogos);
        }, 'json');
    });

    $('#btnNuevaVigencia').on('click', function () {
        $('#formVigencia')[0].reset();
        $('#vig_parentesco').prop('disabled', false);
        $('#modalVigencia').modal('show');
    });
    $('#tablaVigencias').on('click', '.btn-ed-vig', function () {
        const v = catalogos.vigencias.find(x => parseInt(x.parentesco_id) === parseInt($(this).data('id')));
        $('#vig_parentesco').val(v.parentesco_id);
        $('#vig_edad').val(v.edad_maxima ?? '');
        $('#vig_edad_exc').val(v.edad_maxima_excepcion ?? '');
        $('#vig_doc').val(v.documento_excepcion || '');
        $('#vig_meses').val(v.meses_validez_documento);
        $('#vig_estado').val(v.tabla_estado_registro_id);
        $('#modalVigencia').modal('show');
    });
    $('#formVigencia').on('submit', function (e) {
        e.preventDefault();
        $.post(URL_AJAX, $(this).serialize() + '&accion=guardar_vigencia', function (res) {
            if (res.success) $('#modalVigencia').modal('hide');
            aviso(res, cargarCatalogos);
        }, 'json');
    });
});
