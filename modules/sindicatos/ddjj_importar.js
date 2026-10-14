/**
 * modules/sindicatos/ddjj_importar.js
 * Importación de nóminas F.931 / planilla (Página 1021).
 */
$(document).ready(function () {
    const URL_AJAX = 'ddjj_importar_ajax.php';

    function esc(v) {
        return $('<div>').text(v == null ? '' : String(v)).html();
    }

    function periodoTexto(p) {
        p = String(p || '');
        return p.length === 6 ? p.substring(4) + '/' + p.substring(0, 4) : p;
    }

    const tabla = $('#tablaImportaciones').DataTable({
        ajax: URL_AJAX + '?accion=listar',
        order: [[0, 'desc']],
        columns: [
            { data: 'importacion_id' },
            { data: 'fecha_creacion', render: (d) => d ? d.substring(0, 16) : '' },
            { data: 'razon_social', render: (d, t, r) => '<strong>' + esc(d) + '</strong><br><small class="text-muted">' + esc(r.cuit) + '</small>' },
            { data: 'periodo', render: (d) => periodoTexto(d) },
            { data: 'formato', render: (d) => '<span class="badge bg-secondary">' + (d === 'F931_SICOSS' ? 'F.931' : 'Planilla') + '</span>' },
            { data: 'archivo_nombre', render: (d) => '<span class="small">' + esc(d) + '</span>' },
            { data: 'filas_aceptadas', className: 'text-center' },
            { data: 'filas_rechazadas', className: 'text-center', render: (d) => parseInt(d) > 0 ? '<span class="text-danger fw-bold">' + d + '</span>' : d },
            { data: 'ddjj_id', render: (d, t, r) => d ? 'N° ' + d + (parseInt(r.secuencia) > 0 ? ' (rect. ' + r.secuencia + ')' : '') : '-' },
            {
                data: 'estado_nombre', render: (d, t, r) => {
                    const cls = parseInt(r.tabla_estado_registro_id) === 5 ? 'bg-success' : 'bg-danger';
                    return '<span class="badge ' + cls + '">' + esc(d || '') + '</span>';
                }
            },
            // Botones del motor de estados (página 1021), filtrados por estado y permisos en el servidor
            { data: 'acciones', className: 'text-end', orderable: false, render: (d) => d || '' }
        ]
    });

    // Botón "Importar" solo con la función "agregar" de la página
    $.get(URL_AJAX, { accion: 'obtener_boton_agregar' }, function (res) {
        if (res && res.nombre_funcion) {
            $('#btnImportar').removeClass('d-none');
        }
    }, 'json');

    $('#btnRecargar').on('click', () => tabla.ajax.reload());

    $('#formato').on('change', function () {
        $('#ayudaFormato').text($(this).val() === 'F931_SICOSS'
            ? 'Exportación del F.931 desde el aplicativo de ARCA (ancho fijo).'
            : 'Columnas: CUIL, Nombre, Categoría, Remunerativo, No remunerativo, Media jornada, Afiliado, Jubilado.');
    });

    $('#btnImportar').on('click', function () {
        $('#formImportar')[0].reset();
        $.get(URL_AJAX, { accion: 'combos' }, function (res) {
            const emp = $('#empleador_id').find('option:not(:first)').remove().end();
            const cat = $('#categoria_id').find('option:not(:first)').remove().end();
            $.each((res.data || {}).empleadores || [], function (i, e) {
                emp.append($('<option>', { value: e.empleador_id, text: e.razon_social + ' (' + e.cuit + ')' }));
            });
            $.each((res.data || {}).categorias || [], function (i, c) {
                cat.append($('<option>', { value: c.categoria_id, text: c.codigo + ' - ' + c.nombre }));
            });
            $('#modalImportar').modal('show');
        }, 'json');
    });

    $('#formImportar').on('submit', function (e) {
        e.preventDefault();
        const mes = $('#periodo_mes').val(); // AAAA-MM
        if (!mes) return;
        const fd = new FormData(this);
        fd.append('accion', 'importar');
        fd.append('periodo', mes.replace('-', ''));
        const btn = $('#btnConfirmarImportar').prop('disabled', true);
        $.ajax({
            url: URL_AJAX, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false);
                tabla.ajax.reload();
                if (res.success) {
                    $('#modalImportar').modal('hide');
                    Swal.fire({ icon: (res.data && res.data.rechazadas > 0) ? 'warning' : 'success', title: 'Importación', text: res.message });
                } else {
                    Swal.fire({ icon: 'error', title: 'Importación', text: res.message });
                }
            },
            error: function () {
                btn.prop('disabled', false);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error al comunicarse con el servidor.' });
            }
        });
    });

    $('#tablaImportaciones').on('click', '.btn-accion-ver', function () {
        $.get(URL_AJAX, { accion: 'obtener', id: $(this).data('id') }, function (res) {
            if (!res.success) {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                return;
            }
            const d = res.data;
            $('#detalleTitulo').text('Importación N° ' + d.importacion_id + ' - ' + (d.razon_social || ''));
            let html = '<p class="mb-2"><strong>Período:</strong> ' + periodoTexto(d.periodo) +
                ' &nbsp; <strong>Archivo:</strong> ' + esc(d.archivo_nombre) + '</p>' +
                '<p><strong>Filas:</strong> ' + d.filas_total + ' leídas, ' + d.filas_aceptadas + ' aceptadas, ' + d.filas_rechazadas + ' rechazadas</p>';
            if (d.errores && d.errores.length) {
                html += '<h6 class="fw-bold text-danger">Filas rechazadas y avisos</h6><ul class="small">' +
                    d.errores.map(x => '<li>' + esc(x) + '</li>').join('') + '</ul>';
            } else {
                html += '<p class="text-success">Sin errores.</p>';
            }
            $('#detalleCuerpo').html(html);
            $('#detalleIrDdjj').toggleClass('d-none', !d.ddjj_id);
            $('#modalDetalle').modal('show');
        }, 'json');
    });
});
