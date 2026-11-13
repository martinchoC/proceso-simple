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
                    if (f.puede_carnet) acc += '<button type="button" class="btn btn-sm btn-outline-secondary me-1 btn-carnet-familiar" data-id="' + f.afiliacion_id + '" title="Carnet"><i class="fas fa-print"></i></button>';
                    if (f.puede_baja) acc += '<button type="button" class="btn btn-sm btn-outline-danger btn-baja-familiar" data-id="' + f.afiliacion_id + '">Baja</button>';
                    html += '<tr><td><code>' + esc(f.numero_afiliado) + '</code></td><td>' + esc(f.apellido + ' ' + (f.nombre || '')) + '<br><small class="text-muted">' + esc(f.cuil) + '</small></td>' +
                        '<td>' + esc(f.parentesco) + '</td><td>' + fecha(f.f_nacimiento) + '</td><td>' + (f.f_vigencia ? fecha(f.f_vigencia) : 'Sin límite') + '</td>' +
                        '<td class="small">' + (f.documento_vto ? 'Vence ' + fecha(f.documento_vto) : '-') + '</td>' +
                        '<td>' + badge(f.tabla_estado_registro_id, estado) + '</td><td class="text-end text-nowrap">' + acc + '</td></tr>';
                });
                html += '</tbody></table>';
            }
            html += cuentaHtml(d.cuenta || {}, d.puede_volante, t.afiliacion_id);
            $('#fichaCuerpo').html(html);
            $('#btnFichaFamiliar').toggleClass('d-none', !d.puede_agregar_familiar).data('titular', t.afiliacion_id);
            $('#btnFichaCarnet').toggleClass('d-none', !d.puede_carnet).data('titular', t.afiliacion_id);
            $('#modalFicha').modal('show');
        }, 'json');
    }

    function importe(n) {
        return (parseFloat(n) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Cuotas directas pendientes y volantes del titular
    function cuentaHtml(c, puedeVolante, titularId) {
        const deudas = c.deudas || [], volantes = c.volantes || [];
        let h = '<h6 class="fw-bold mt-3">Cuotas pendientes</h6>';
        if (!deudas.length) {
            h += '<p class="text-muted">Sin cuotas pendientes fuera de volantes vigentes.</p>';
        } else {
            h += '<table class="table table-sm align-middle"><thead class="table-light"><tr><th></th><th>Comprobante</th><th>Vencimiento</th><th class="text-end">Saldo</th></tr></thead><tbody>';
            deudas.forEach(function (x) {
                h += '<tr><td><input type="checkbox" class="form-check-input chk-cuota" value="' + x.comprobante_id + '" checked' + (puedeVolante ? '' : ' disabled') + '></td>' +
                    '<td>' + esc(x.tipo_nombre) + ' <code>' + esc(x.comprobante_codigo) + '</code></td><td>' + fecha(x.f_vto) + '</td><td class="text-end">' + importe(x.saldo_deuda) + '</td></tr>';
            });
            h += '</tbody></table>';
            if (puedeVolante) {
                h += '<div class="row g-2 align-items-end mb-3"><div class="col-md-4"><label class="form-label small">1er vencimiento</label><input type="date" class="form-control form-control-sm" id="vol_f_pago_1"></div>' +
                    '<div class="col-md-4"><label class="form-label small">2do vencimiento</label><input type="date" class="form-control form-control-sm" id="vol_f_pago_2"></div>' +
                    '<div class="col-md-4"><button type="button" class="btn btn-sm btn-primary w-100" id="btnEmitirVolante" data-titular="' + titularId + '"><i class="bi bi-upc-scan"></i> Emitir volante</button></div></div>';
            }
        }
        if (volantes.length) {
            h += '<h6 class="fw-bold">Volantes</h6><table class="table table-sm align-middle"><thead class="table-light"><tr><th>N°</th><th>Emisión</th><th>1er vto.</th><th class="text-end">Importe</th><th>2do vto.</th><th class="text-end">Importe</th><th>Estado</th></tr></thead><tbody>';
            volantes.forEach(function (v) {
                h += '<tr><td><code>' + esc(v.numero) + '</code></td><td>' + fecha(v.f_emision) + '</td><td>' + fecha(v.f_pago_1) + '</td><td class="text-end">' + importe(v.importe_1) + '</td>' +
                    '<td>' + fecha(v.f_pago_2) + '</td><td class="text-end">' + importe(v.importe_2) + '</td><td>' + esc(v.estado_nombre || '') + '</td></tr>';
            });
            h += '</tbody></table>';
        }
        return h;
    }

    $('#fichaCuerpo').on('click', '#btnEmitirVolante', function () {
        const ids = $('#fichaCuerpo .chk-cuota:checked').map(function () { return this.value; }).get();
        if (!ids.length) {
            Swal.fire({ icon: 'warning', title: 'Seleccione al menos una cuota' });
            return;
        }
        const datos = { accion: 'emitir_volante', afiliacion_id: $(this).data('titular'), comprobante_ids: ids };
        if ($('#vol_f_pago_1').val()) datos.f_pago_1 = $('#vol_f_pago_1').val();
        if ($('#vol_f_pago_2').val()) datos.f_pago_2 = $('#vol_f_pago_2').val();
        $.post(URL_AJAX, datos, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Volante emitido', text: res.message || '' });
                if (fichaActual) abrirFicha(fichaActual);
            } else {
                Swal.fire({ icon: 'error', title: 'No se pudo emitir', text: res.message });
            }
        }, 'json');
    });

    // Credencial imprimible (afiliados_carnet.php): grupo = titular y familiares activos
    function abrirCarnet(id, grupo) {
        window.open('afiliados_carnet.php?afiliacion_id=' + encodeURIComponent(id) + (grupo ? '&grupo=1' : ''), '_blank');
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
            case 'volante':
                abrirFicha(id);
                break;
            case 'agregar_familiar':
                fichaActual = id;
                abrirPersona('familiar', id);
                break;
            case 'baja':
                abrirBaja(id);
                break;
            case 'carnet':
                abrirCarnet(id, true);
                break;
        }
    });

    $('#btnFichaCarnet').on('click', function () {
        abrirCarnet($(this).data('titular'), true);
    });
    $('#fichaCuerpo').on('click', '.btn-carnet-familiar', function () {
        abrirCarnet($(this).data('id'), false);
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

    // ==========================================
    // EXPORTACIÓN A EXCEL Y REPORTES DEL PADRÓN
    // ==========================================

    // Inicializar mes actual en selector de cumpleaños
    const mesActual = new Date().getMonth() + 1;
    $('#repCumpleMes').val(mesActual);

    // Helpers de descarga Excel directa mediante query params
    function descargarExcel(params) {
        const query = $.param(params);
        window.location.href = URL_AJAX + '?' + query;
    }

    // Exportaciones desde el menú principal
    $('#btnExportarPadronExcel').on('click', function (e) {
        e.preventDefault();
        descargarExcel({
            accion: 'exportar_padron',
            texto: $('#filtroTexto').val(),
            tipo_codigo: $('#filtroTipo').val(),
            estado: $('#filtroEstado').val()
        });
    });

    $('#btnExportarGruposExcel').on('click', function (e) {
        e.preventDefault();
        descargarExcel({ accion: 'exportar_grupos' });
    });

    $('#btnExportarCumplesExcel').on('click', function (e) {
        e.preventDefault();
        descargarExcel({ accion: 'exportar_cumples', mes: mesActual, estado: 1 });
    });

    $('#btnExportarBajasExcel').on('click', function (e) {
        e.preventDefault();
        descargarExcel({ accion: 'exportar_bajas' });
    });

    // Exportaciones desde los modales de reportes
    $('#btnRepCumpleExcelModal').on('click', function () {
        descargarExcel({
            accion: 'exportar_cumples',
            mes: $('#repCumpleMes').val(),
            estado: $('#repCumpleEstado').val()
        });
    });

    $('#btnRepBajasExcelModal').on('click', function () {
        descargarExcel({ accion: 'exportar_bajas' });
    });

    $('#btnRepGruposExcelModal').on('click', function () {
        descargarExcel({ accion: 'exportar_grupos' });
    });

    // Apertura del modal de reportes
    $('#btnAbrirReportes').on('click', function () {
        $('#modalReportes').modal('show');
        const activa = $('#reportesTabs .nav-link.active').attr('id');
        if (activa === 'tab-cumples-btn') {
            cargarReporteCumpleanios();
        } else if (activa === 'tab-activos-btn') {
            cargarReporteActivosInactivos();
        } else if (activa === 'tab-grupos-btn') {
            cargarReporteGrupos();
        }
    });

    // Cambio de pestañas del modal
    $('#reportesTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        const targetId = $(e.target).attr('id');
        if (targetId === 'tab-cumples-btn') {
            cargarReporteCumpleanios();
        } else if (targetId === 'tab-activos-btn') {
            cargarReporteActivosInactivos();
        } else if (targetId === 'tab-grupos-btn') {
            cargarReporteGrupos();
        }
    });

    // Reporte Cumpleaños: Carga y renderizado
    function cargarReporteCumpleanios() {
        const mes = $('#repCumpleMes').val();
        const estado = $('#repCumpleEstado').val();
        const $tbody = $('#tablaRepCumple tbody');
        $tbody.html('<tr><td colspan="8" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando cumpleaños...</td></tr>');

        $.get(URL_AJAX, { accion: 'reporte_cumpleanios', mes: mes, estado: estado }, function (res) {
            if (!res.success || !res.data) {
                $tbody.html('<tr><td colspan="8" class="text-center py-4 text-danger">Error al cargar datos.</td></tr>');
                return;
            }
            const data = res.data;
            $('#repCumpleContador').text(data.length + ' cumpleañeros');

            if (data.length === 0) {
                $tbody.html('<tr><td colspan="8" class="text-center py-4 text-muted">No se encontraron afiliados con cumpleaños en el mes seleccionado.</td></tr>');
                return;
            }

            let html = '';
            data.forEach(function (r) {
                const dia = r.dia_cumple ? String(r.dia_cumple).padStart(2, '0') : '-';
                const edad = r.edad_a_cumplir != null ? r.edad_a_cumplir + ' años' : '-';
                const esFamiliar = !!r.titular_afiliacion_id;
                const tipoBadge = esFamiliar 
                    ? '<span class="badge bg-info text-dark">' + esc(r.parentesco || 'Familiar') + '</span>'
                    : '<span class="badge bg-primary">Titular</span>';

                const titularInfo = esFamiliar
                    ? '<small class="text-muted">Titular:</small> ' + esc(r.titular_nombre || '') + ' <small class="text-muted">(Af. ' + esc(r.titular_numero || '-') + ')</small>'
                    : '<span class="text-muted">-</span>';

                html += '<tr>' +
                    '<td class="text-center"><span class="badge bg-warning text-dark px-2 py-1 fs-7 fw-bold">' + esc(dia) + '</span></td>' +
                    '<td><strong>' + esc(r.apellido + ' ' + (r.nombre || '')) + '</strong></td>' +
                    '<td><code>' + esc(r.cuil || '-') + '</code></td>' +
                    '<td>' + tipoBadge + '</td>' +
                    '<td>' + titularInfo + '</td>' +
                    '<td class="text-center"><span class="badge bg-light text-dark border">' + esc(edad) + '</span></td>' +
                    '<td><small>' + esc(r.empleador_nombre || '-') + '</small></td>' +
                    '<td class="text-center">' + badge(r.tabla_estado_registro_id, r.estado_nombre) + '</td>' +
                '</tr>';
            });
            $tbody.html(html);
        }, 'json').fail(function () {
            $tbody.html('<tr><td colspan="8" class="text-center py-4 text-danger">Error en la solicitud.</td></tr>');
        });
    }

    $('#btnRepCumpleFiltrar').on('click', cargarReporteCumpleanios);
    $('#repCumpleMes, #repCumpleEstado').on('change', cargarReporteCumpleanios);

    // Reporte Activos / Inactivos: Carga y renderizado
    function cargarReporteActivosInactivos() {
        $.get(URL_AJAX, { accion: 'reporte_activos_inactivos' }, function (res) {
            if (!res.success || !res.data) return;
            const t = res.data.totales || {};
            $('#kpiTotal').text((t.total || 0).toLocaleString('es-AR'));
            $('#kpiActivos').text((t.activos || 0).toLocaleString('es-AR'));
            $('#kpiVencidos').text((t.vencidos || 0).toLocaleString('es-AR'));
            $('#kpiBajas').text((t.bajas || 0).toLocaleString('es-AR'));

            // Desglose por tipo
            const desglose = res.data.desglose || [];
            let htmlDesglose = '';
            if (desglose.length === 0) {
                htmlDesglose = '<tr><td colspan="6" class="text-center py-3 text-muted">Sin datos.</td></tr>';
            } else {
                desglose.forEach(function (d) {
                    const grupoBadge = d.grupo === 'TITULAR'
                        ? '<span class="badge bg-primary">Titular</span>'
                        : '<span class="badge bg-info text-dark">Familiar</span>';
                    htmlDesglose += '<tr>' +
                        '<td><strong>' + esc(d.tipo_nombre) + '</strong></td>' +
                        '<td>' + grupoBadge + '</td>' +
                        '<td class="text-center text-success fw-bold">' + (parseInt(d.activos) || 0).toLocaleString('es-AR') + '</td>' +
                        '<td class="text-center text-warning fw-bold">' + (parseInt(d.vencidos) || 0).toLocaleString('es-AR') + '</td>' +
                        '<td class="text-center text-secondary fw-bold">' + (parseInt(d.bajas) || 0).toLocaleString('es-AR') + '</td>' +
                        '<td class="text-end fw-bold">' + (parseInt(d.total) || 0).toLocaleString('es-AR') + '</td>' +
                    '</tr>';
                });
            }
            $('#tablaRepDesglose tbody').html(htmlDesglose);

            // Bajas
            const bajas = res.data.bajas || [];
            let htmlBajas = '';
            if (bajas.length === 0) {
                htmlBajas = '<tr><td colspan="5" class="text-center py-3 text-muted">No se registran bajas en el padrón.</td></tr>';
            } else {
                bajas.forEach(function (b) {
                    htmlBajas += '<tr>' +
                        '<td class="small text-nowrap">' + fecha(b.f_baja) + '</td>' +
                        '<td><strong>' + esc(b.apellido + ' ' + (b.nombre || '')) + '</strong><br><small class="text-muted">Af. ' + esc(b.numero_afiliado || '-') + '</small></td>' +
                        '<td><code>' + esc(b.cuil || '-') + '</code></td>' +
                        '<td><small>' + esc(b.tipo_nombre || '-') + '</small></td>' +
                        '<td><span class="badge bg-danger-subtle text-danger border border-danger-subtle">' + esc(b.motivo_baja || 'Baja') + '</span></td>' +
                    '</tr>';
                });
            }
            $('#tablaRepBajas tbody').html(htmlBajas);
        }, 'json');
    }

    // Reporte Grupos Familiares: Carga y renderizado
    function cargarReporteGrupos() {
        const texto = $('#repGruposTexto').val();
        const $tbody = $('#tablaRepGrupos tbody');
        $tbody.html('<tr><td colspan="8" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando grupos familiares...</td></tr>');

        $.get(URL_AJAX, { accion: 'reporte_grupos', texto: texto }, function (res) {
            if (!res.success || !res.data) {
                $tbody.html('<tr><td colspan="8" class="text-center py-4 text-danger">Error al cargar grupos familiares.</td></tr>');
                return;
            }
            const data = res.data;
            $('#repGruposContador').text(data.length + ' personas');

            if (data.length === 0) {
                $tbody.html('<tr><td colspan="8" class="text-center py-4 text-muted">No se encontraron afiliados o grupos con ese criterio.</td></tr>');
                return;
            }

            let html = '';
            data.forEach(function (r) {
                const esFamiliar = !!r.titular_afiliacion_id;
                const trClass = esFamiliar ? '' : 'table-light fw-semibold';
                const indent = esFamiliar ? '<span class="ms-3 text-muted">↳</span> ' : '<i class="fas fa-user-tie text-primary me-1"></i> ';
                const rolBadge = esFamiliar
                    ? '<span class="badge bg-info text-dark">' + esc(r.parentesco || 'Familiar') + '</span>'
                    : '<span class="badge bg-primary">Titular</span>';

                const vinculo = esFamiliar
                    ? '<small class="text-muted">A cargo de:</small> ' + esc(r.titular_nombre || '') + ' <small class="text-muted">(Af. ' + esc(r.titular_numero || '-') + ')</small>'
                    : '<small class="text-muted">Emp:</small> ' + esc(r.empleador_nombre || '-');

                const edad = r.edad != null ? r.edad + ' años' : '-';
                const docVigencia = r.doc_vencimiento ? fecha(r.doc_vencimiento) : (r.f_vigencia ? fecha(r.f_vigencia) : '<span class="text-muted">Sin límite</span>');

                html += '<tr class="' + trClass + '">' +
                    '<td><code>' + esc(r.numero_afiliado || '-') + '</code></td>' +
                    '<td>' + indent + rolBadge + '</td>' +
                    '<td>' + esc(r.apellido + ' ' + (r.nombre || '')) + '</td>' +
                    '<td><code>' + esc(r.cuil || '-') + '</code></td>' +
                    '<td class="text-center"><small>' + esc(edad) + '</small></td>' +
                    '<td>' + vinculo + '</td>' +
                    '<td><small>' + docVigencia + '</small></td>' +
                    '<td class="text-center">' + badge(r.tabla_estado_registro_id, r.estado_nombre) + '</td>' +
                '</tr>';
            });
            $tbody.html(html);
        }, 'json').fail(function () {
            $tbody.html('<tr><td colspan="8" class="text-center py-4 text-danger">Error al consultar el servidor.</td></tr>');
        });
    }

    $('#btnRepGruposBuscar').on('click', cargarReporteGrupos);
    $('#repGruposTexto').on('keypress', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            cargarReporteGrupos();
        }
    });
});
