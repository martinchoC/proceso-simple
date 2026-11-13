/**
 * Libros Societarios (Gestión 140, Gestión Normal 640, Estudio Contable 424).
 * Vista: templates/libros_societarios/libros_societarios_vista.php. Endpoint: window.LSOC_AJAX (libros_societarios_ajax.php del módulo).
 * window.LSOC_MODO = 'empresa' | 'estudio'; window.LSOC_CSRF = token para las escrituras.
 * Los botones llevan data-funcion: header1.php oculta los que el perfil no tiene y el servidor los vuelve a exigir.
 */
window.LibrosSoc = (function () {
    const AJAX = window.LSOC_AJAX || 'libros_societarios_ajax.php';
    const ESTUDIO = window.LSOC_MODO === 'estudio';
    let cat = { tipos_libro: {}, tipos_acto: [], materias: [], caracteres: {}, estados_libro: {}, estados_inscripcion: {}, clientes: [] };
    let tActas = null, tLibros = null, tAutoridades = null;
    let librosCache = [];
    let archivoActa = 0;
    let clientesOrigen = {};   // cliente_id => CLIENTE_ERP (libros de su empresa) | ESTUDIO

    // ------------------------------------------------------------------ utilidades
    function esc(t) { return $('<div>').text(t == null ? '' : String(t)).html(); }
    function fecha(v) { return v ? Fmt.fecha(v) : ''; }
    function hoy() { const d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
    function cliente() { return ESTUDIO ? (parseInt($('#lsocCliente').val(), 10) || 0) : 0; }
    function err(msg) { UiSimple.error(msg || 'No se pudo completar la operación.'); }
    function msgXhr(xhr) {
        try { const j = JSON.parse(xhr.responseText); return j.message || j.error; } catch (e) { return null; }
    }

    function get(accion, params) {
        return $.ajax({ url: AJAX, method: 'GET', dataType: 'json', data: Object.assign({ accion: accion }, params || {}) })
            .then(r => r.success ? r.data : $.Deferred().reject(r.message).promise(),
                  xhr => $.Deferred().reject(msgXhr(xhr)).promise());
    }

    function post(accion, data) {
        const opts = { url: AJAX + '?accion=' + encodeURIComponent(accion), method: 'POST', dataType: 'json',
                       headers: { 'X-CSRF-Token': window.LSOC_CSRF || '' } };
        if (data instanceof FormData) {
            data.append('accion', accion);
            Object.assign(opts, { data: data, processData: false, contentType: false });
        } else {
            opts.data = Object.assign({ accion: accion }, data || {});
        }
        return $.ajax(opts).then(r => r.success ? r : $.Deferred().reject(r.message).promise(),
                                 xhr => $.Deferred().reject(msgXhr(xhr)).promise());
    }

    function opciones($sel, pares, vacio) {
        $sel.empty();
        if (vacio !== undefined) $sel.append($('<option>').val('').text(vacio));
        pares.forEach(p => $sel.append($('<option>').val(p[0]).text(p[1])));
    }

    function badgeEstadoActa(a) {
        if (a.anulada || parseInt(a.tabla_estado_registro_id, 10) === 38) return '<span class="badge bg-danger-subtle text-danger-emphasis">Anulada</span>';
        return a.estado_acta === 'TRANSCRIPTA'
            ? '<span class="badge bg-success-subtle text-success-emphasis">En el libro</span>'
            : '<span class="badge bg-warning-subtle text-warning-emphasis">Borrador</span>';
    }

    // Gestión: quién cargó el registro si no fue la empresa (el estudio). Estudio: lo que quedó guardado solo en el estudio.
    function marca(x) {
        if (!ESTUDIO) {
            return x.cargado_por ? ` <span class="badge bg-info-subtle text-info-emphasis" title="Cargado por">${esc(x.cargado_por)}</span>` : '';
        }
        return (x.origen === 'ESTUDIO' && clientesOrigen[x.cliente_ref] === 'CLIENTE_ERP')
            ? ' <span class="badge bg-warning-subtle text-warning-emphasis" title="Todavía no se pasó a los libros de la empresa">Solo en el estudio</span>' : '';
    }

    function badgeInscripcion(estado, nombre) {
        const cls = { NO_REQUIERE: 'bg-light text-muted border', PENDIENTE: 'bg-warning-subtle text-warning-emphasis',
                      EN_TRAMITE: 'bg-info-subtle text-info-emphasis', OBSERVADA: 'bg-danger-subtle text-danger-emphasis',
                      INSCRIPTA: 'bg-success-subtle text-success-emphasis' }[estado] || 'bg-light';
        return `<span class="badge ${cls}">${esc(nombre || cat.estados_inscripcion[estado] || estado)}</span>`;
    }

    // ------------------------------------------------------------------ carga inicial
    function iniciar() {
        get('combos').then(d => {
            cat = d;
            opciones($('#fActaTipo'), d.tipos_acto.map(t => [t.codigo, t.nombre]), 'Todos los actos');
            opciones($('#fActaMateria'), d.materias.map(m => [m.codigo, m.nombre]), 'Todos los temas');
            opciones($('#lb_tipo'), Object.entries(d.tipos_libro), 'Elegí...');
            opciones($('#lb_estado'), Object.entries(d.estados_libro));
            opciones($('#ac_tipo'), d.tipos_acto.map(t => [t.codigo, t.nombre]), 'Elegí...');
            opciones($('#in_estado'), Object.entries(d.estados_inscripcion));
            $('#ac_materias').html(d.materias.map(m => `
                <div class="col-md-4 col-sm-6"><div class="form-check">
                    <input class="form-check-input lsoc-contenido lsoc-materia" type="checkbox" value="${esc(m.codigo)}" id="mat_${esc(m.codigo)}"
                           data-inscribible="${m.inscribible ? 1 : 0}">
                    <label class="form-check-label small" for="mat_${esc(m.codigo)}">${esc(m.nombre)}</label></div></div>`).join(''));
            if (ESTUDIO) {
                d.clientes.forEach(c => { clientesOrigen[c.cliente_id] = c.origen; });
                const cl = d.clientes.map(c => [c.cliente_id, c.razon_social + (c.cuit ? ' (' + c.cuit + ')' : '')
                    + (c.origen === 'CLIENTE_ERP' ? (c.escritura ? ' · libros de su empresa' : ' · solo lectura') : '')]);
                opciones($('#lsocCliente'), cl, 'Todos los clientes');
                opciones($('#lb_cliente'), cl, 'Elegí...');
                opciones($('#ac_cliente'), cl, 'Elegí...');
                // Para cargar, solo los clientes con escritura
                d.clientes.filter(c => !c.escritura).forEach(c => {
                    $(`#lb_cliente option[value="${c.cliente_id}"], #ac_cliente option[value="${c.cliente_id}"]`).prop('disabled', true);
                });
                const ini = window.EstudioCliente ? EstudioCliente.inicial() : 0;
                if (ini && d.clientes.some(c => parseInt(c.cliente_id, 10) === ini)) $('#lsocCliente').val(String(ini));
            }
            crearTablas();
            recargar();
        }, err);
    }

    function crearTablas() {
        const colsActas = [
            { data: 'f_acto', render: (v, t) => t === 'display' ? fecha(v) : v },
        ];
        if (ESTUDIO) colsActas.push({ data: 'cliente', defaultContent: '' , render: v => esc(v) });
        colsActas.push(
            { data: 'tipo_acto_nombre', render: (v, t, a) => esc(v) + marca(a) },
            { data: null, render: a => a.libro_id ? esc(a.libro_nombre) + (a.numero_acta ? ' · Acta ' + esc(a.numero_acta) : '') : '<span class="text-muted">—</span>' },
            { data: null, render: a => badgeEstadoActa(a) },
            { data: null, render: a => badgeInscripcion(a.inscripcion_estado, a.inscripcion_nombre) },
            { data: 'materias_nombre', render: v => esc(v) },
            { data: null, render: a => a.folio_desde ? `${esc(a.folio_desde)} a ${esc(a.folio_hasta)}` : '' },
            { data: null, render: a => esc(a.resumen) + (a.motivo_anulacion ? `<div class="text-danger small">Anulada: ${esc(a.motivo_anulacion)}</div>` : '') },
            { data: null, orderable: false, className: 'text-center text-nowrap', render: botonesActa }
        );
        tActas = $('#tablaActas').DataTable({ data: [], columns: colsActas, order: [[0, 'desc']], pageLength: 25 });

        const colsLibros = [];
        if (ESTUDIO) colsLibros.push({ data: 'cliente', defaultContent: '', render: v => esc(v) });
        colsLibros.push(
            { data: 'nombre', render: (v, t, l) => esc(v) + marca(l) + (parseInt(l.tabla_estado_registro_id, 10) !== 1 ? ' <span class="badge bg-secondary">Baja</span>' : '') },
            { data: null, render: l => [l.organismo, l.rubrica_numero ? 'N° ' + l.rubrica_numero : '', fecha(l.rubrica_fecha)].filter(Boolean).map(esc).join(' · ') },
            { data: null, render: (l, t) => {
                if (l.medio !== 'PAPEL' || !l.folios_total) return t === 'display' ? '<span class="text-muted">Digital</span>' : -1;
                const pct = Math.min(100, Math.round(l.folios_usados * 100 / l.folios_total));
                if (t !== 'display') return pct;
                const cls = pct >= 90 ? 'bg-danger' : (pct >= 75 ? 'bg-warning' : 'bg-success');
                return `<div class="small">${l.folios_usados} de ${l.folios_total} (${l.folios_libres} libres)</div>
                        <div class="progress" style="height:5px"><div class="progress-bar ${cls}" style="width:${pct}%"></div></div>`;
            } },
            { data: null, render: l => l.ultimo_numero ? 'N° ' + esc(l.ultimo_numero) + (l.ultimo_acto ? ' · ' + fecha(l.ultimo_acto) : '') : '' },
            { data: 'estado_nombre', render: (v, t, l) => `<span class="badge ${l.estado_libro === 'EN_USO' ? 'bg-success-subtle text-success-emphasis' : 'bg-light text-dark border'}">${esc(v)}</span>` },
            { data: 'medio', render: v => v === 'DIGITAL' ? 'Digital' : 'Papel' },
            { data: 'ubicacion', defaultContent: '', render: v => esc(v) },
            { data: 'observaciones', defaultContent: '', render: v => esc(v) },
            { data: null, orderable: false, className: 'text-center text-nowrap', render: l => (parseInt(l.tabla_estado_registro_id, 10) !== 1 || !l.editable) ? '' :
                `<button type="button" class="btn btn-sm btn-outline-primary lsoc-libro-editar" data-id="${l.libro_id}" data-funcion="editar" title="Editar"><i class="fas fa-pen"></i></button>
                 <button type="button" class="btn btn-sm btn-outline-danger lsoc-libro-baja" data-id="${l.libro_id}" data-funcion="inhabilitar" title="Dar de baja"><i class="fas fa-trash-alt"></i></button>` }
        );
        tLibros = $('#tablaLibros').DataTable({ data: [], columns: colsLibros, order: [], pageLength: 25 });

        const colsAut = [];
        if (ESTUDIO) colsAut.push({ data: 'cliente', defaultContent: '', render: v => esc(v) });
        colsAut.push(
            { data: 'cargo_designado', render: v => esc(v) },
            { data: 'nombre', render: v => esc(v) },
            { data: 'documento', defaultContent: '', render: v => esc(v) },
            { data: 'f_acto', render: (v, t) => t === 'display' ? fecha(v) : v },
            { data: 'mandato_hasta', defaultContent: '', render: (v, t, r) => t !== 'display' ? (v || '') :
                (v ? `<span class="${r.vencido ? 'text-danger fw-semibold' : ''}">${fecha(v)}${r.vencido ? ' (vencido)' : ''}</span>` : '') },
            { data: 'inscripcion_nombre', defaultContent: '', render: v => esc(v) },
            { data: null, orderable: false, className: 'text-center', render: r =>
                `<button type="button" class="btn btn-sm btn-outline-secondary lsoc-acta-ver" data-id="${r.acta_id}" title="Ver acta"><i class="fas fa-eye"></i></button>` }
        );
        tAutoridades = $('#tablaAutoridades').DataTable({ data: [], columns: colsAut, order: [], pageLength: 25 });
    }

    function botonesActa(a) {
        const id = a.acta_id;
        let h = `<button type="button" class="btn btn-sm btn-outline-secondary lsoc-acta-ver" data-id="${id}" title="Ver / editar"><i class="fas fa-eye"></i></button> `;
        if (a.anulada || !a.editable) return h + (a.tiene_archivo ? botonDescarga(id) : '');
        if (a.estado_acta === 'BORRADOR') {
            h += `<button type="button" class="btn btn-sm btn-outline-primary lsoc-acta-transcribir" data-id="${id}" data-funcion="editar" title="Pasar al libro"><i class="bi bi-journal-text"></i></button> `;
        }
        if (a.inscripcion_estado !== 'NO_REQUIERE') {
            h += `<button type="button" class="btn btn-sm btn-outline-info lsoc-acta-inscripcion" data-id="${id}" data-funcion="editar" title="Inscripción"><i class="fas fa-stamp"></i></button> `;
        }
        h += `<button type="button" class="btn btn-sm btn-outline-secondary lsoc-acta-archivo" data-id="${id}" data-funcion="editar" title="${a.tiene_archivo ? 'Reemplazar copia firmada' : 'Adjuntar copia firmada'}"><i class="fas fa-paperclip"></i></button> `;
        if (a.tiene_archivo) h += botonDescarga(id);
        return h;
    }

    function botonDescarga(id) {
        return `<a class="btn btn-sm btn-outline-success" href="${AJAX}?accion=descargar&acta_id=${id}" target="_blank" rel="noopener" title="Ver copia"><i class="fas fa-file-pdf"></i></a>`;
    }

    // ------------------------------------------------------------------ recargas
    function recargar() {
        cargarResumen();
        cargarActas();
        cargarLibros();
        cargarAutoridades();
    }

    function cargarResumen() {
        get('resumen', { cliente_id: cliente() }).then(d => {
            $('#kpiLibros').text(d.kpis.libros_en_uso);
            $('#kpiBorradores').text(d.kpis.borradores);
            $('#kpiInscripciones').text(d.kpis.inscripciones);
            $('#kpiAlertas').text(d.kpis.alertas).toggleClass('text-danger', d.alertas.some(a => a.nivel === 'danger'));
            const iconos = { danger: 'fas fa-exclamation-circle text-danger', warning: 'fas fa-exclamation-triangle text-warning', info: 'fas fa-info-circle text-info' };
            $('#lsocAlertas').html(d.alertas.map(a => `
                <li class="list-group-item py-2 d-flex gap-2 align-items-start">
                    <i class="${iconos[a.nivel] || iconos.info} mt-1"></i>
                    <div class="flex-grow-1">${ESTUDIO && a.cliente ? '<span class="fw-semibold">' + esc(a.cliente) + ':</span> ' : ''}${esc(a.texto)}
                        ${a.fecha && a.tipo !== 'EECC' ? '<span class="text-muted small ms-1">' + fecha(a.fecha) + '</span>' : ''}
                        ${a.tipo === 'EECC' && a.nivel !== 'danger' ? '<span class="fw-semibold ms-1">' + fecha(a.fecha) + '</span>' : ''}</div>
                    ${a.acta_id ? `<button type="button" class="btn btn-sm btn-link p-0 lsoc-acta-ver" data-id="${a.acta_id}">Ver</button>` : ''}
                    ${a.tipo === 'EECC' || a.tipo === 'MANDATO' ? `<button type="button" class="btn btn-sm btn-link p-0 lsoc-alerta-acta" data-funcion="agregar"
                        data-tipo="${a.tipo}" data-cliente="${a.cliente_id || 0}" data-ejercicio="${esc(a.ejercicio || '')}">Cargar acta</button>` : ''}
                </li>`).join(''));
            $('#lsocAlertasCard').toggleClass('d-none', d.alertas.length === 0);
            mostrarFuente(d.fuente);
        }, err);
    }

    // Estudio con un cliente elegido: de dónde salen sus libros
    function mostrarFuente(f) {
        const $b = $('#lsocFuente');
        if (!$b.length) return;
        if (!f) { $b.addClass('d-none'); return; }
        let txt, cls;
        if (f.origen === 'CLIENTE_ERP') {
            txt = f.propio ? 'Son los libros de la empresa del estudio en Gestión.'
                : (f.escritura ? 'Libros de la empresa del cliente: lo que cargues acá lo ve la empresa en Gestión.' : f.aviso);
            cls = f.escritura ? 'alert-info' : 'alert-secondary';
            if (f.pendientes_estudio > 0) {
                txt += ` Hay ${f.pendientes_estudio} registro(s) cargados solo en el estudio (marcados "Solo en el estudio").`;
            }
        } else {
            txt = f.aviso || 'El cliente no tiene empresa en el sistema: los libros quedan en el estudio.';
            cls = f.aviso ? 'alert-warning' : 'alert-light border';
        }
        $('#lsocFuenteTexto').text(txt);
        $b.removeClass('d-none alert-info alert-secondary alert-warning alert-light border').addClass(cls);
        $('#btnLsocPasar').toggleClass('d-none', !(f.origen === 'CLIENTE_ERP' && f.escritura && f.pendientes_estudio > 0));
    }

    function pasarAEmpresa() {
        const cid = cliente();
        if (!cid) return;
        Swal.fire({ title: 'Pasar a la empresa', icon: 'question', showCancelButton: true, confirmButtonText: 'Pasar', cancelButtonText: 'Cancelar',
                    text: 'Los libros y actas que el estudio cargó para este cliente pasan a los libros de su empresa y los va a ver en Gestión.' })
            .then(res => {
                if (!res.isConfirmed) return;
                post('pasar_a_empresa', { cliente_id: cid }).then(r => {
                    Swal.fire({ icon: 'success', title: 'Listo', text: r.message });
                    recargar();
                }, err);
            });
    }

    function cargarActas() {
        get('listar_actas', {
            cliente_id: cliente(), tipo_acto: $('#fActaTipo').val(), materia: $('#fActaMateria').val(), estado: $('#fActaEstado').val(),
            desde: $('#fActaDesde').val(), hasta: $('#fActaHasta').val(), incluir_anuladas: $('#fActaAnuladas').is(':checked') ? 1 : 0
        }).then(d => tActas.clear().rows.add(d).draw(), err);
    }

    function cargarLibros() {
        get('listar_libros', { cliente_id: cliente(), incluir_inactivos: $('#fLibrosInactivos').is(':checked') ? 1 : 0 }).then(d => {
            librosCache = d;
            tLibros.clear().rows.add(d).draw();
        }, err);
    }

    function cargarAutoridades() {
        get('autoridades', { cliente_id: cliente() }).then(d => tAutoridades.clear().rows.add(d).draw(), err);
    }

    // ------------------------------------------------------------------ libros
    function abrirLibro(l) {
        const f = $('#formLibro')[0];
        f.reset();
        $('#lb_id').val(l ? l.libro_id : 0);
        $('#modalLibroTitulo').text(l ? 'Editar libro' : 'Nuevo libro');
        if (ESTUDIO) $('#lb_cliente').val(l ? String(l.cliente_ref) : (cliente() ? String(cliente()) : '')).prop('disabled', !!l);
        if (l) {
            ['tipo_libro', 'numero_libro', 'denominacion', 'medio', 'organismo', 'rubrica_numero', 'rubrica_fecha', 'folio_desde',
             'folio_hasta', 'f_inicio_uso', 'f_cierre', 'estado_libro', 'ubicacion', 'observaciones'].forEach(k => {
                $(f).find(`[name="${k}"]`).val(l[k] == null ? '' : l[k]);
            });
        } else {
            $('#lb_estado').val('EN_USO');
            $('#lb_fdesde').val(1);
        }
        $('.lsoc-papel').toggleClass('d-none', $('#lb_medio').val() === 'DIGITAL');
        bootstrap.Modal.getOrCreateInstance('#modalLibro').show();
    }

    // Número sugerido: siguiente al último libro del mismo tipo
    function sugerirNumeroLibro() {
        if (parseInt($('#lb_id').val(), 10) > 0) return;
        const tipo = $('#lb_tipo').val();
        const cid = ESTUDIO ? parseInt($('#lb_cliente').val(), 10) || 0 : 0;
        const nums = librosCache.filter(l => l.tipo_libro === tipo
                && (!ESTUDIO || (parseInt(l.cliente_ref, 10) === cid && !(clientesOrigen[cid] === 'CLIENTE_ERP' && l.origen === 'ESTUDIO'))))
                                .map(l => parseInt(l.numero_libro, 10));
        $('#lb_numero').val(nums.length ? Math.max(...nums) + 1 : 1);
    }

    function guardarLibro(e) {
        e.preventDefault();
        const datos = $('#formLibro').serializeArray().reduce((o, x) => (o[x.name] = x.value, o), {});
        if (ESTUDIO) datos.cliente_id = $('#lb_cliente').val();
        post('guardar_libro', datos).then(r => {
            bootstrap.Modal.getInstance('#modalLibro').hide();
            UiSimple.ok(r.message || 'Guardado');
            recargar();
        }, err);
    }

    // ------------------------------------------------------------------ actas
    function filaPersona(p) {
        p = p || {};
        const car = Object.entries(cat.caracteres).map(([k, v]) => `<option value="${k}"${p.caracter === k ? ' selected' : ''}>${esc(v)}</option>`).join('');
        return `<tr>
            <td><input type="text" class="form-control form-control-sm lsoc-contenido p-nombre" maxlength="150" value="${esc(p.nombre)}"></td>
            <td><input type="text" class="form-control form-control-sm lsoc-contenido p-documento" maxlength="20" value="${esc(p.documento)}"></td>
            <td><select class="form-select form-select-sm lsoc-contenido p-caracter">${car}</select></td>
            <td><input type="text" class="form-control form-control-sm lsoc-contenido p-cargo" maxlength="80" value="${esc(p.cargo_designado)}" list="lsocCargos"></td>
            <td><input type="number" step="any" min="0" class="form-control form-control-sm lsoc-contenido p-part" value="${p.participacion == null ? '' : esc(parseFloat(p.participacion))}"></td>
            <td><input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm lsoc-contenido p-pct" value="${p.participacion_pct == null ? '' : esc(parseFloat(p.participacion_pct))}"></td>
            <td class="text-center"><input type="checkbox" class="form-check-input lsoc-contenido p-firma"${parseInt(p.firma, 10) ? ' checked' : ''}></td>
            <td><button type="button" class="btn btn-sm btn-link text-danger lsoc-contenido p-quitar" title="Quitar"><i class="fas fa-times"></i></button></td>
        </tr>`;
    }

    function actualizarBloquesMaterias() {
        const sel = $('.lsoc-materia:checked').map((i, el) => el.value).get();
        $('#ac_bloque_ejercicio').toggleClass('d-none', !sel.includes('APROBACION_EECC'));
        $('#ac_bloque_mandato').toggleClass('d-none', !sel.includes('DESIGNACION_AUTORIDADES'));
    }

    function abrirActa(a, preset) {
        const f = $('#formActa')[0];
        f.reset();
        $('#ac_id').val(a ? a.acta_id : 0);
        $('#ac_personas').empty();
        $('.lsoc-materia').prop('checked', false);
        $('#ac_requiere').data('tocado', false);
        const transcripta = !!a && a.estado_acta === 'TRANSCRIPTA';
        const anulada = !!a && parseInt(a.tabla_estado_registro_id, 10) === 38;
        const lectura = anulada || (!!a && a.editable === false);
        $('#modalActaTitulo').text(a ? (a.tipo_acto_nombre || 'Acta') + ' del ' + fecha(a.f_acto) : 'Nueva acta');
        $('#actaEstadoBadge').html(a ? badgeEstadoActa(Object.assign({ anulada: anulada }, a)) : '');
        if (ESTUDIO) $('#ac_cliente').val(a ? String(a.cliente_ref) : String((preset && preset.cliente_id) || cliente() || ''));
        if (a) {
            ['tipo_acto', 'f_acto', 'lugar', 'f_convocatoria', 'quorum_pct', 'orden_del_dia', 'resoluciones', 'ejercicio_cierre',
             'mandato_hasta', 'observaciones'].forEach(k => $(f).find(`[name="${k}"]`).val(a[k] == null ? '' : a[k]));
            $('#ac_hora').val(a.hora_acto ? String(a.hora_acto).substring(0, 5) : '');
            $('#ac_unanime').prop('checked', parseInt(a.es_unanime, 10) === 1);
            (a.materias_lista || []).forEach(m => $('#mat_' + m).prop('checked', true));
            $('#ac_requiere').prop('checked', a.inscripcion_estado !== 'NO_REQUIERE').data('tocado', true);
            (a.personas || []).forEach(p => $('#ac_personas').append(filaPersona(p)));
        } else {
            $('#ac_fecha').val(hoy());
            if (preset && preset.tipo === 'EECC') {
                $('#ac_tipo').val('ASAMBLEA_ORDINARIA');
                ['APROBACION_EECC', 'HONORARIOS', 'DISTRIBUCION_RESULTADOS'].forEach(m => $('#mat_' + m).prop('checked', true));
                $('#ac_ejercicio').val(preset.ejercicio || '');
            } else if (preset && preset.tipo === 'MANDATO') {
                $('#ac_tipo').val('ASAMBLEA_ORDINARIA');
                $('#mat_DESIGNACION_AUTORIDADES').prop('checked', true);
                $('#ac_requiere').prop('checked', true);
            }
            $('#ac_personas').append(filaPersona());
        }
        actualizarBloquesMaterias();
        // Transcripta o anulada: solo observaciones (y nada si está anulada)
        $('#formActa .lsoc-contenido').prop('disabled', transcripta || lectura);
        if (ESTUDIO) $('#ac_cliente').prop('disabled', !!a);
        $('#ac_obs').prop('disabled', lectura);
        $('#btnActaGuardar').toggleClass('d-none', lectura);
        $('#actaAvisoTranscripta').toggleClass('d-none', !transcripta || lectura);
        $('#btnActaAnular').toggleClass('d-none', !a || lectura || a.inscripcion_estado === 'INSCRIPTA');
        $('#btnActaBorrador').toggleClass('d-none', !transcripta || lectura || ['EN_TRAMITE', 'INSCRIPTA'].includes(a.inscripcion_estado));
        if (a && a.libro_id) {
            const partes = [esc(a.libro_nombre), a.numero_acta ? 'Acta N° ' + esc(a.numero_acta) : '',
                            a.folio_desde ? 'folios ' + esc(a.folio_desde) + ' a ' + esc(a.folio_hasta) : '',
                            a.f_transcripcion ? 'transcripta el ' + fecha(a.f_transcripcion) : ''].filter(Boolean);
            let txt = partes.join(' · ');
            if (a.inscripcion_estado !== 'NO_REQUIERE') {
                txt += '<br>Inscripción: ' + badgeInscripcion(a.inscripcion_estado) + (a.inscripcion_tramite ? ' trámite ' + esc(a.inscripcion_tramite) : '')
                     + (a.f_inscripcion ? ' · inscripta el ' + fecha(a.f_inscripcion) : '') + (a.inscripcion_datos ? ' · ' + esc(a.inscripcion_datos) : '');
            }
            if (anulada && a.motivo_anulacion) txt += `<br><span class="text-danger">Anulada: ${esc(a.motivo_anulacion)}</span>`;
            if (a.cargado_por) txt += `<br><span class="text-muted">Cargada por ${esc(a.cargado_por)}</span>`;
            $('#ac_libro_texto').html(txt);
            $('#ac_bloque_libro').removeClass('d-none');
        } else {
            $('#ac_bloque_libro').toggleClass('d-none', !anulada);
            $('#ac_libro_texto').html(anulada && a.motivo_anulacion ? `<span class="text-danger">Anulada: ${esc(a.motivo_anulacion)}</span>` : '');
        }
        bootstrap.Modal.getOrCreateInstance('#modalActa').show();
    }

    function verActa(id) {
        get('obtener_acta', { acta_id: id }).then(a => abrirActa(a), err);
    }

    function guardarActa(e) {
        e.preventDefault();
        const id = parseInt($('#ac_id').val(), 10) || 0;
        const datos = {
            acta_id: id, tipo_acto: $('#ac_tipo').val(), f_acto: $('#ac_fecha').val(), hora_acto: $('#ac_hora').val(),
            lugar: $('#ac_lugar').val(), f_convocatoria: $('#ac_convocatoria').val(), quorum_pct: $('#ac_quorum').val(),
            es_unanime: $('#ac_unanime').is(':checked') ? 1 : 0, orden_del_dia: $('#ac_odd').val(), resoluciones: $('#ac_res').val(),
            ejercicio_cierre: $('#ac_ejercicio').val(), mandato_hasta: $('#ac_mandato').val(), observaciones: $('#ac_obs').val(),
            requiere_inscripcion: $('#ac_requiere').is(':checked') ? 1 : 0,
            materias: $('.lsoc-materia:checked').map((i, el) => el.value).get().join(','),
            personas: JSON.stringify($('#ac_personas tr').map((i, tr) => {
                const $t = $(tr);
                return { nombre: $t.find('.p-nombre').val(), documento: $t.find('.p-documento').val(), caracter: $t.find('.p-caracter').val(),
                         cargo_designado: $t.find('.p-cargo').val(), participacion: $t.find('.p-part').val(),
                         participacion_pct: $t.find('.p-pct').val(), firma: $t.find('.p-firma').is(':checked') ? 1 : 0 };
            }).get())
        };
        if (ESTUDIO) datos.cliente_id = $('#ac_cliente').val();
        if (!id && ESTUDIO && !datos.cliente_id) return UiSimple.aviso('Elegí un cliente.');
        post('guardar_acta', datos).then(r => {
            bootstrap.Modal.getInstance('#modalActa').hide();
            UiSimple.ok(r.message || 'Guardado');
            recargar();
        }, err);
    }

    function anularActa() {
        const id = parseInt($('#ac_id').val(), 10);
        Swal.fire({ title: 'Anular el acta', input: 'text', inputLabel: 'Motivo', showCancelButton: true, confirmButtonText: 'Anular',
                    cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545',
                    inputValidator: v => (!v || !v.trim()) ? 'Indicá el motivo.' : undefined })
            .then(res => {
                if (!res.isConfirmed) return;
                post('anular_acta', { acta_id: id, motivo: res.value }).then(r => {
                    bootstrap.Modal.getInstance('#modalActa').hide();
                    UiSimple.ok(r.message || 'Anulada');
                    recargar();
                }, err);
            });
    }

    function volverBorrador() {
        const id = parseInt($('#ac_id').val(), 10);
        Swal.fire({ title: 'Volver a borrador', text: 'Se quitan el libro, el número y los folios para poder corregir el acta.', icon: 'question',
                    showCancelButton: true, confirmButtonText: 'Volver a borrador', cancelButtonText: 'Cancelar' })
            .then(res => {
                if (!res.isConfirmed) return;
                post('volver_borrador', { acta_id: id }).then(r => {
                    UiSimple.ok(r.message || 'Listo');
                    recargar();
                    verActa(id);
                }, err);
            });
    }

    // ------------------------------------------------------------------ transcribir
    function abrirTranscribir(id) {
        get('obtener_acta', { acta_id: id }).then(a => {
            const tipo = cat.tipos_acto.find(t => t.codigo === a.tipo_acto) || { libros: [] };
            const libros = librosCache.filter(l => parseInt(l.empresa_id, 10) === parseInt(a.empresa_id, 10)
                                                  && parseInt(l.estudio_cliente_id, 10) === parseInt(a.estudio_cliente_id, 10)
                                                  && l.estado_libro === 'EN_USO' && parseInt(l.tabla_estado_registro_id, 10) === 1);
            const sugeridos = libros.filter(l => tipo.libros.includes(l.tipo_libro));
            const otros = libros.filter(l => !tipo.libros.includes(l.tipo_libro));
            const $sel = $('#tr_libro').empty();
            if (!libros.length) {
                return UiSimple.aviso(ESTUDIO ? 'El cliente no tiene libros en uso: cargá primero el libro.' : 'No hay libros en uso: cargá primero el libro.');
            }
            sugeridos.concat(otros).forEach(l => $sel.append($('<option>').val(l.libro_id).text(l.nombre)));
            $('#tr_acta').val(id);
            $('#tr_titulo').text((a.tipo_acto_nombre || 'Acta') + ' del ' + fecha(a.f_acto));
            $('#tr_fecha').val(hoy());
            sugerirTranscripcion();
            bootstrap.Modal.getOrCreateInstance('#modalTranscribir').show();
        }, err);
    }

    function sugerirTranscripcion() {
        const libro = $('#tr_libro').val();
        if (!libro) return;
        get('sugerencia', { libro_id: libro }).then(s => {
            $('#tr_numero').val(s.numero_acta);
            const papel = s.medio === 'PAPEL';
            $('.lsoc-tr-papel').toggleClass('d-none', !papel);
            $('#tr_fdesde').val(papel ? s.folio_desde : '');
            $('#tr_fhasta').val(papel ? s.folio_desde : '');
            $('#tr_info').text(papel ? `El libro va del folio ${s.libro_folio_desde} al ${s.libro_folio_hasta}.` : 'Libro digital: sin folios.');
        }, err);
    }

    function transcribir(e) {
        e.preventDefault();
        const datos = $('#formTranscribir').serializeArray().reduce((o, x) => (o[x.name] = x.value, o), {});
        post('transcribir', datos).then(r => {
            bootstrap.Modal.getInstance('#modalTranscribir').hide();
            UiSimple.ok(r.message || 'Listo');
            recargar();
        }, err);
    }

    // ------------------------------------------------------------------ inscripción
    function abrirInscripcion(id) {
        get('obtener_acta', { acta_id: id }).then(a => {
            const f = $('#formInscripcion')[0];
            f.reset();
            $('#in_acta').val(id);
            $('#in_titulo').text((a.tipo_acto_nombre || 'Acta') + ' del ' + fecha(a.f_acto) + (a.numero_acta ? ' · Acta N° ' + a.numero_acta : ''));
            ['inscripcion_estado', 'inscripcion_organismo', 'inscripcion_tramite', 'f_presentacion', 'f_inscripcion', 'inscripcion_datos']
                .forEach(k => $(f).find(`[name="${k}"]`).val(a[k] == null ? '' : a[k]));
            bootstrap.Modal.getOrCreateInstance('#modalInscripcion').show();
        }, err);
    }

    function guardarInscripcion(e) {
        e.preventDefault();
        const datos = $('#formInscripcion').serializeArray().reduce((o, x) => (o[x.name] = x.value, o), {});
        post('inscripcion', datos).then(r => {
            bootstrap.Modal.getInstance('#modalInscripcion').hide();
            UiSimple.ok(r.message || 'Guardado');
            recargar();
        }, err);
    }

    // ------------------------------------------------------------------ archivo
    function subirArchivo() {
        const file = this.files && this.files[0];
        const id = archivoActa;
        this.value = '';
        if (!file || !id) return;
        if (file.size > 15 * 1024 * 1024) return UiSimple.aviso('El archivo supera los 15 MB.');
        const fd = new FormData();
        fd.append('acta_id', id);
        fd.append('archivo', file);
        post('subir_archivo', fd).then(r => { UiSimple.ok(r.message || 'Adjuntado'); cargarActas(); }, err);
    }

    // ------------------------------------------------------------------ eventos
    $(function () {
        $('body').append(`<datalist id="lsocCargos"><option value="Presidente"><option value="Vicepresidente"><option value="Director titular">
            <option value="Director suplente"><option value="Gerente"><option value="Síndico titular"><option value="Síndico suplente"></datalist>`);

        $('#lsocCliente').on('change', function () {
            if (window.EstudioCliente) EstudioCliente.guardar(this.value);
            recargar();
        });
        $('#fActaTipo, #fActaMateria, #fActaEstado, #fActaDesde, #fActaHasta, #fActaAnuladas').on('change', cargarActas);
        $('#fLibrosInactivos').on('change', cargarLibros);

        $('#btnLsocNuevoLibro').on('click', () => abrirLibro(null));
        $('#btnLsocNuevaActa').on('click', () => abrirActa(null));
        $('#btnLsocPasar').on('click', pasarAEmpresa);
        $('#lb_medio').on('change', function () { $('.lsoc-papel').toggleClass('d-none', this.value === 'DIGITAL'); });
        $('#lb_tipo, #lb_cliente').on('change', sugerirNumeroLibro);
        $('#formLibro').on('submit', guardarLibro);

        $(document).on('click', '.lsoc-libro-editar', function () {
            get('obtener_libro', { libro_id: $(this).data('id') }).then(l => abrirLibro(l), err);
        });
        $(document).on('click', '.lsoc-libro-baja', function () {
            const id = $(this).data('id');
            Swal.fire({ title: 'Dar de baja el libro', text: 'Solo se puede si no tiene actas.', icon: 'warning', showCancelButton: true,
                        confirmButtonText: 'Dar de baja', cancelButtonText: 'Cancelar' })
                .then(res => res.isConfirmed && post('inhabilitar_libro', { libro_id: id }).then(r => { UiSimple.ok(r.message); recargar(); }, err));
        });

        $(document).on('click', '.lsoc-acta-ver', function () { verActa($(this).data('id')); });
        $(document).on('click', '.lsoc-acta-transcribir', function () { abrirTranscribir($(this).data('id')); });
        $(document).on('click', '.lsoc-acta-inscripcion', function () { abrirInscripcion($(this).data('id')); });
        $(document).on('click', '.lsoc-acta-archivo', function () { archivoActa = $(this).data('id'); $('#lsocArchivo').trigger('click'); });
        $(document).on('click', '.lsoc-alerta-acta', function () {
            abrirActa(null, { tipo: $(this).data('tipo'), cliente_id: $(this).data('cliente'), ejercicio: $(this).data('ejercicio') });
        });
        $('#lsocArchivo').on('change', subirArchivo);

        $('#btnAgregarPersona').on('click', () => $('#ac_personas').append(filaPersona()));
        $(document).on('click', '#ac_personas .p-quitar', function () { $(this).closest('tr').remove(); });
        $(document).on('change', '.lsoc-materia', function () {
            actualizarBloquesMaterias();
            // Sugerir inscripción mientras el usuario no la haya tocado
            if (!$('#ac_requiere').data('tocado')) {
                $('#ac_requiere').prop('checked', $('.lsoc-materia:checked').filter((i, el) => $(el).data('inscribible') === 1).length > 0);
            }
        });
        $('#ac_requiere').on('change', function () { $(this).data('tocado', true); });
        $('#formActa').on('submit', guardarActa);
        $('#btnActaAnular').on('click', anularActa);
        $('#btnActaBorrador').on('click', volverBorrador);

        $('#tr_libro').on('change', sugerirTranscripcion);
        $('#tr_fdesde').on('change', function () {
            if ((parseInt($('#tr_fhasta').val(), 10) || 0) < (parseInt(this.value, 10) || 0)) $('#tr_fhasta').val(this.value);
        });
        $('#formTranscribir').on('submit', transcribir);
        $('#formInscripcion').on('submit', guardarInscripcion);

        iniciar();
    });

    return { recargar: recargar };
})();
