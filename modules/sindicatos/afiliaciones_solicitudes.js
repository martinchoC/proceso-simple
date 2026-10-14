/**
 * modules/sindicatos/afiliaciones_solicitudes.js
 * Solicitudes de afiliación (Página 1051).
 */
$(document).ready(function () {
    const URL_AJAX = 'afiliaciones_solicitudes_ajax.php';
    let tipos = [];
    let titularesBuscados = {};

    function esc(v) {
        return $('<div>').text(v == null ? '' : String(v)).html();
    }
    function fecha(d) {
        return d ? d.substring(8, 10) + '/' + d.substring(5, 7) + '/' + d.substring(0, 4) : '-';
    }

    const tabla = $('#tablaSolicitudes').DataTable({
        ajax: { url: URL_AJAX, data: (d) => { d.accion = 'listar'; d.estado = $('#filtroEstado').val(); } },
        order: [[0, 'desc']],
        columns: [
            { data: 'solicitud_id' },
            { data: 'f_solicitud', render: (d) => fecha(d) },
            { data: 'apellido', render: (d, t, r) => '<strong>' + esc(r.apellido + ' ' + (r.nombre || '')) + '</strong><br><small class="text-muted">' + esc(r.cuil) + '</small>' },
            { data: 'tipo_nombre', render: (d, t, r) => esc(d) + (r.parentesco ? '<br><small class="text-muted">' + esc(r.parentesco) + '</small>' : '') },
            {
                data: null, render: (r) => r.grupo === 'FAMILIAR'
                    ? esc((r.titular_apellido || '') + ' ' + (r.titular_nombre || '')) + ' <code>' + esc(r.titular_numero || '') + '</code>'
                    : esc(r.empleador_nombre || '-')
            },
            { data: 'numero_afiliado', render: (d) => d ? '<code>' + esc(d) + '</code>' : '-' },
            {
                data: 'estado_nombre', render: (d, t, r) => {
                    const cls = { 3: 'bg-secondary', 4: 'bg-warning text-dark', 30: 'bg-success', 25: 'bg-danger', 38: 'bg-dark' }[parseInt(r.tabla_estado_registro_id)] || 'bg-light text-dark';
                    return '<span class="badge ' + cls + '">' + esc(d) + '</span>' + (r.motivo_rechazo ? '<br><small class="text-danger">' + esc(r.motivo_rechazo) + '</small>' : '');
                }
            },
            // Botones del motor de estados (página 1051), filtrados por estado y permisos en el servidor
            { data: 'acciones', className: 'text-end', orderable: false, render: (d) => d || '' }
        ]
    });

    $.get(URL_AJAX, { accion: 'combos' }, function (res) {
        const d = res.data || {};
        tipos = (d.tipos || []);
        // ACTIVIDAD no se pide por solicitud: la declara el empleador en la DDJJ
        tipos.filter(t => t.codigo !== 'ACTIVIDAD').forEach(t =>
            $('#tipo_codigo').append($('<option>', { value: t.codigo, text: t.nombre, 'data-grupo': t.grupo, 'data-empleador': t.requiere_empleador })));
        (d.parentescos || []).forEach(p => $('#parentesco_id').append($('<option>', { value: p.parentesco_id, text: p.nombre })));
        (d.empleadores || []).forEach(e => $('#empleador_id').append($('<option>', { value: e.empleador_id, text: e.razon_social + ' (' + e.cuit + ')' })));
    }, 'json');

    $.get(URL_AJAX, { accion: 'obtener_boton_agregar' }, function (res) {
        if (res && res.nombre_funcion) $('#btnNuevaSolicitud').removeClass('d-none');
    }, 'json');

    $('#filtroEstado').on('change', () => tabla.ajax.reload());
    $('#btnRecargar').on('click', () => tabla.ajax.reload());

    function ajustarBloques() {
        const op = $('#tipo_codigo :selected');
        const familiar = op.data('grupo') === 'FAMILIAR';
        $('.bloque-familiar').toggleClass('d-none', !familiar);
        $('.bloque-empleador').toggleClass('d-none', familiar || parseInt(op.data('empleador')) !== 1);
    }
    $('#tipo_codigo').on('change', ajustarBloques);

    // Titular: búsqueda por texto
    let timer = null;
    $('#titular_texto').on('input', function () {
        const texto = $(this).val();
        $('#titular_afiliacion_id').val(titularesBuscados[texto] || '');
        clearTimeout(timer);
        if (texto.length < 3 || titularesBuscados[texto]) return;
        timer = setTimeout(function () {
            $.get(URL_AJAX, { accion: 'buscar_titulares', q: texto }, function (res) {
                const lista = $('#listaTitulares').empty();
                (res.data || []).forEach(function (t) {
                    const etiqueta = t.numero_afiliado + ' - ' + t.apellido + ' ' + (t.nombre || '') + ' (' + t.cuil + ')';
                    titularesBuscados[etiqueta] = t.afiliacion_id;
                    lista.append($('<option>', { value: etiqueta }));
                });
            }, 'json');
        }, 300);
    });

    function abrir(s) {
        $('#formSolicitud')[0].reset();
        $('#solicitud_id').val(s ? s.solicitud_id : '');
        $('#titular_afiliacion_id').val(s ? (s.titular_afiliacion_id || '') : '');
        if (s) {
            $('#tipo_codigo').val(s.tipo_codigo);
            ['cuil', 'apellido', 'nombre', 'f_nacimiento', 'sexo', 'email', 'telefono', 'observaciones', 'empleador_id', 'parentesco_id']
                .forEach(k => $('#' + k).val(s[k] || ''));
            $('#titular_texto').val(s.titular_afiliacion_id ? 'Afiliación ' + s.titular_afiliacion_id : '');
        }
        ajustarBloques();
        $('#modalSolicitudTitulo').text(s ? 'Solicitud N° ' + s.solicitud_id : 'Nueva solicitud');
        $('#modalSolicitud').modal('show');
    }

    $('#btnNuevaSolicitud').on('click', () => abrir(null));

    $('#formSolicitud').on('submit', function (e) {
        e.preventDefault();
        $.post(URL_AJAX, $(this).serialize() + '&accion=guardar', function (res) {
            if (res.success) {
                $('#modalSolicitud').modal('hide');
                Swal.fire({ icon: 'success', title: 'Guardada', text: res.message, timer: 1500, showConfirmButton: false });
                tabla.ajax.reload(null, false);
            } else {
                Swal.fire({ icon: 'error', title: 'No se pudo guardar', text: res.message });
            }
        }, 'json');
    });

    function transicion(id, accion, motivo) {
        $.post(URL_AJAX, { accion: 'ejecutar_accion', id: id, accion_js: accion, motivo: motivo || '' }, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Listo', text: res.message, timer: 1600, showConfirmButton: false });
                tabla.ajax.reload(null, false);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    }

    $('#tablaSolicitudes').on('click', '.btn-accion', function () {
        const id = $(this).data('id');
        const accion = $(this).data('accion');
        if (accion === 'editar' || accion === 'ver') {
            $.get(URL_AJAX, { accion: 'obtener', id: id }, function (res) {
                if (res.success) abrir(res.data);
            }, 'json');
        } else if (accion === 'rechazar') {
            Swal.fire({ title: 'Motivo del rechazo', input: 'text', showCancelButton: true, confirmButtonText: 'Rechazar',
                inputValidator: (v) => !v ? 'Indique el motivo' : undefined })
                .then(r => { if (r.isConfirmed) transicion(id, 'rechazar', r.value); });
        } else {
            const textos = { enviar: 'Se envía para aprobación.', anular: 'La solicitud queda anulada.', aprobar: 'Se registra la afiliación.' };
            Swal.fire({ title: '¿Confirmar?', text: textos[accion] || '', icon: 'question', showCancelButton: true, confirmButtonText: 'Sí' })
                .then(r => { if (r.isConfirmed) transicion(id, accion); });
        }
    });
});
