/**
 * modules/sindicatos/afiliados.js
 * Afiliados y grupo familiar (Página 1050).
 */
$(document).ready(function () {
    const URL_AJAX = 'afiliados_ajax.php';
    let combos = { tipos: [], parentescos: [], bajas_motivos: [], empleadores: [] };
    let fichaActual = null;
    const hoy = new Date().toISOString().substring(0, 10);

    function esc(v) {
        return $('<div>').text(v == null ? '' : String(v)).html();
    }
    function fecha(d) {
        return d ? d.substring(8, 10) + '/' + d.substring(5, 7) + '/' + d.substring(0, 4) : '-';
    }
    function badge(estado, nombre) {
        const cls = { 1: 'bg-success', 20: 'bg-warning text-dark', 2: 'bg-secondary' }[parseInt(estado)] || 'bg-light text-dark';
        return '<span class="badge ' + cls + '">' + esc(nombre || '') + '</span>';
    }

    const tabla = $('#tablaAfiliados').DataTable({
        ajax: {
            url: URL_AJAX,
            data: function (d) {
                d.accion = 'listar';
                d.texto = $('#filtroTexto').val();
                d.tipo_codigo = $('#filtroTipo').val();
                d.estado = $('#filtroEstado').val();
            }
        },
        order: [[1, 'asc']],
        columns: [
            { data: 'numero_afiliado', render: (d) => '<code>' + esc(d || '-') + '</code>' },
            { data: 'apellido', render: (d, t, r) => '<strong>' + esc(r.apellido + ' ' + (r.nombre || '')) + '</strong>' },
            { data: 'cuil' },
            { data: 'tipo_nombre' },
            { data: 'empleador_nombre', render: (d) => esc(d || '-') },
            { data: 'f_alta', render: (d) => fecha(d) },
            { data: 'familiares', className: 'text-center' },
            { data: 'estado_nombre', render: (d, t, r) => badge(r.tabla_estado_registro_id, d) },
            // Botones del motor de estados (página 1050), filtrados por estado y permisos en el servidor
            { data: 'acciones', className: 'text-end', orderable: false, render: (d) => d || '' }
        ]
    });

    $.get(URL_AJAX, { accion: 'combos' }, function (res) {
        combos = res.data || combos;
        combos.tipos.filter(t => t.grupo === 'TITULAR').forEach(function (t) {
            $('#filtroTipo').append($('<option>', { value: t.codigo, text: t.nombre }));
            $('#tipo_codigo').append($('<option>', { value: t.codigo, text: t.nombre, 'data-empleador': t.requiere_empleador }));
        });
        combos.parentescos.forEach(p => $('#parentesco_id').append($('<option>', { value: p.parentesco_id, text: p.nombre })));
        combos.bajas_motivos.forEach(m => $('#baja_motivo_id').append($('<option>', { value: m.baja_motivo_id, text: m.descripcion })));
        combos.empleadores.forEach(e => $('#empleador_id').append($('<option>', { value: e.empleador_id, text: e.razon_social + ' (' + e.cuit + ')' })));
    }, 'json');

    $.get(URL_AJAX, { accion: 'obtener_boton_agregar' }, function (res) {
        if (res && res.nombre_funcion) $('#btnNuevaAfiliacion').removeClass('d-none');
    }, 'json');

    let timer = null;
    $('#filtroTexto').on('input', function () {
        clearTimeout(timer);
        timer = setTimeout(() => tabla.ajax.reload(), 400);
    });
    $('#filtroTipo, #filtroEstado').on('change', () => tabla.ajax.reload());
    $('#btnRecargar').on('click', () => tabla.ajax.reload());

    $('#tipo_codigo').on('change', function () {
        const requiere = parseInt($(this).find(':selected').data('empleador')) === 1;
        $('.bloque-empleador').toggleClass('d-none', !requiere);
    });

    function abrirPersona(modo, titularId) {
        $('#formPersona')[0].reset();
        $('#modo').val(modo);
        $('#titular_afiliacion_id').val(titularId || '');
        $('#f_alta').val(hoy);
        $('.bloque-titular').toggleClass('d-none', modo !== 'titular');
        $('.bloque-familiar').toggleClass('d-none', modo !== 'familiar');
        $('#tipo_codigo').trigger('change');
        if (modo === 'titular') $('.bloque-empleador').toggleClass('d-none', parseInt($('#tipo_codigo :selected').data('empleador')) !== 1);
        $('#modalPersonaTitulo').text(modo === 'titular' ? 'Nueva afiliación' : 'Agregar familiar a cargo');
        $('#modalPersona').modal('show');
    }

    $('#btnNuevaAfiliacion').on('click', () => abrirPersona('titular'));

    $('#formPersona').on('submit', function (e) {
        e.preventDefault();
        const modo = $('#modo').val();
        const datos = $(this).serialize() + '&accion=' + (modo === 'titular' ? 'alta_titular' : 'alta_familiar');
        $.post(URL_AJAX, datos, function (res) {
            if (res.success) {
                $('#modalPersona').modal('hide');
                Swal.fire({ icon: 'success', title: 'Listo', text: res.message, timer: 1600, showConfirmButton: false });
                tabla.ajax.reload(null, false);
                if (modo === 'familiar' && fichaActual) abrirFicha(fichaActual);
            } else {
                Swal.fire({ icon: 'error', title: 'No se pudo guardar', text: res.message });
            }
        }, 'json');
    });

    function abrirFicha(id) {
        fichaActual = id;
        $.get(URL_AJAX, { accion: 'ficha', id: id }, function (res) {
            if (!res.success) {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                return;
            }
            const d = res.data, p = d.persona || {}, t = d.titular || {};
            $('#fichaTitulo').text('Afiliado N° ' + (t.numero_afiliado || '-') + ' - ' + p.apellido + ' ' + (p.nombre || ''));
            let html = '<div class="row mb-3"><div class="col-md-6">' +
                '<p class="mb-1"><strong>CUIL:</strong> ' + esc(p.cuil) + '</p>' +
                '<p class="mb-1"><strong>Nacimiento:</strong> ' + fecha(p.f_nacimiento) + '</p>' +
                '<p class="mb-1"><strong>Contacto:</strong> ' + esc([p.email, p.telefono].filter(Boolean).join(' / ') || '-') + '</p></div>' +
                '<div class="col-md-6"><h6 class="fw-bold">Afiliaciones</h6><ul class="small mb-0">' +
                (d.afiliaciones || []).map(a => '<li>' + esc(a.tipo_nombre) + (a.empleador_nombre ? ' - ' + esc(a.empleador_nombre) : '') +
                    ': desde ' + fecha(a.f_alta) + (a.f_baja ? ' hasta ' + fecha(a.f_baja) : '') + '</li>').join('') +
                '</ul></div></div>';
            html += '<h6 class="fw-bold">Grupo familiar</h6>';
            if (!(d.familiares || []).length) {
                html += '<p class="text-muted">Sin familiares a cargo.</p>';
            } else {
                html += '<table class="table table-sm align-middle"><thead class="table-light"><tr><th>N°</th><th>Familiar</th><th>Parentesco</th>' +
                    '<th>Nacimiento</th><th>Vigencia</th><th>Documento</th><th>Estado</th><th class="text-end"></th></tr></thead><tbody>';
                d.familiares.forEach(function (f) {
                    const estado = { 1: 'Activa', 20: 'Vencida', 2: 'Baja' }[parseInt(f.tabla_estado_registro_id)] || '';
                    let acc = '';
                    if (f.puede_documento) acc += '<button type="button" class="btn btn-sm btn-outline-info me-1 btn-doc-familiar" data-id="' + f.afiliacion_id + '" data-doc="' + esc(f.documento_excepcion) + '">Documento</button>';
                    if (f.puede_baja) acc += '<button type="button" class="btn btn-sm btn-outline-danger btn-baja-familiar" data-id="' + f.afiliacion_id + '">Baja</button>';
                    html += '<tr><td><code>' + esc(f.numero_afiliado) + '</code></td><td>' + esc(f.apellido + ' ' + (f.nombre || '')) + '<br><small class="text-muted">' + esc(f.cuil) + '</small></td>' +
                        '<td>' + esc(f.parentesco) + '</td><td>' + fecha(f.f_nacimiento) + '</td><td>' + (f.f_vigencia ? fecha(f.f_vigencia) : 'Sin límite') + '</td>' +
                        '<td class="small">' + (f.documento_vto ? 'Vence ' + fecha(f.documento_vto) : '-') + '</td>' +
                        '<td>' + badge(f.tabla_estado_registro_id, estado) + '</td><td class="text-end text-nowrap">' + acc + '</td></tr>';
                });
                html += '</tbody></table>';
            }
            $('#fichaCuerpo').html(html);
            $('#btnFichaFamiliar').toggleClass('d-none', !d.puede_agregar_familiar).data('titular', t.afiliacion_id);
            $('#modalFicha').modal('show');
        }, 'json');
    }

    function abrirBaja(id) {
        $('#formBaja')[0].reset();
        $('#baja_afiliacion_id').val(id);
        $('#f_baja').val(hoy);
        $('#modalBaja').modal('show');
    }

    function abrirDocumento(id, documento) {
        $('#formDocumento')[0].reset();
        $('#doc_afiliacion_id').val(id);
        $('#doc_documento').val(documento || '');
        $('#doc_f_presentacion').val(hoy);
        $('#modalDocumento').modal('show');
    }

    // Botones del motor en el listado
    $('#tablaAfiliados').on('click', '.btn-accion', function () {
        const id = $(this).data('id');
        switch ($(this).data('accion')) {
            case 'ver':
            case 'documento':
                abrirFicha(id);
                break;
            case 'agregar_familiar':
                fichaActual = id;
                abrirPersona('familiar', id);
                break;
            case 'baja':
                abrirBaja(id);
                break;
        }
    });

    $('#btnFichaFamiliar').on('click', function () {
        abrirPersona('familiar', $(this).data('titular'));
    });
    $('#fichaCuerpo').on('click', '.btn-doc-familiar', function () {
        abrirDocumento($(this).data('id'), $(this).data('doc'));
    });
    $('#fichaCuerpo').on('click', '.btn-baja-familiar', function () {
        abrirBaja($(this).data('id'));
    });

    $('#formBaja').on('submit', function (e) {
        e.preventDefault();
        $.post(URL_AJAX, $(this).serialize() + '&accion=baja', function (res) {
            if (res.success) {
                $('#modalBaja').modal('hide');
                Swal.fire({ icon: 'success', title: 'Baja registrada', text: res.message, timer: 1600, showConfirmButton: false });
                tabla.ajax.reload(null, false);
                if ($('#modalFicha').hasClass('show') && fichaActual) abrirFicha(fichaActual);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    $('#formDocumento').on('submit', function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        fd.append('accion', 'documento');
        $.ajax({
            url: URL_AJAX, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
            success: function (res) {
                if (res.success) {
                    $('#modalDocumento').modal('hide');
                    const v = res.data && res.data.f_vigencia ? fecha(res.data.f_vigencia) : 'sin límite';
                    Swal.fire({ icon: 'success', title: 'Documento registrado', text: 'Cobertura hasta ' + v + '.' });
                    tabla.ajax.reload(null, false);
                    if (fichaActual) abrirFicha(fichaActual);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                }
            }
        });
    });
});
