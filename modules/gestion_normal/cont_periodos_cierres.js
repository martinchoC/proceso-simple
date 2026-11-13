$(document).ready(function () {
    const empresa_idx = window.EMPRESA_ID;
    const AJAX = 'cont_periodos_cierres_ajax.php';
    let maestros = { meses: {}, ejercicios: [], boton_cerrar: null, boton_reabrir: null };
    let resumen = { ejercicio: null, meses: [] };
    let tabla;

    function esc(t) {
        return $('<div>').text(t == null ? '' : String(t)).html();
    }

    function ejercicio() {
        return parseInt($('#cpcEjercicio').val(), 10) || maestros.ejercicio_actual;
    }

    function fecha(d) {
        return Fmt.fechaHora(d);
    }

    function error(res, xhr) {
        let msg = (res && (res.message || res.error)) || 'No se pudo completar la operación';
        if (xhr) {
            try { const j = JSON.parse(xhr.responseText); msg = j.message || j.error || msg; } catch (e) { /* respuesta no JSON */ }
        }
        Swal.fire({ icon: 'error', title: 'Períodos contables', text: msg });
    }

    function claseBoton(b, defecto) {
        return (b.bg_clase && b.text_clase) ? `${b.bg_clase} ${b.text_clase}` : (b.color_clase || defecto);
    }

    // ------------------------------------------------------------------ meses del ejercicio
    function cargarResumen() {
        $.getJSON(AJAX, { accion: 'resumen', ejercicio: ejercicio(), empresa_idx: empresa_idx }, function (res) {
            if (!res.success) return error(res);
            resumen = res.data;
            const e = resumen.ejercicio;
            $('#cpcTituloEjercicio').text('Meses del ejercicio ' + e.ejercicio);
            $('#cpcInfoEjercicio').html(`${fecha(e.desde)} al ${fecha(e.hasta)} · cierra en ${esc(maestros.meses[e.mes_cierre] || '')}`
                + (e.irregular ? ` <span class="badge bg-warning text-dark">Irregular: ${resumen.meses.length} meses</span>` : ''));
            $('#cpcMeses').html(resumen.meses.map(m => {
                const estado = m.cerrado
                    ? '<span class="badge bg-success"><i class="fas fa-lock me-1"></i>Cerrado</span>'
                    : (m.futuro ? '<span class="badge bg-light text-muted border">Futuro</span>'
                                : '<span class="badge bg-light text-dark border"><i class="fas fa-lock-open me-1"></i>Abierto</span>');
                let accion = '';
                if (m.cerrado && maestros.boton_reabrir) {
                    accion = `<button type="button" class="btn btn-sm btn-outline-danger btn-reabrir" data-id="${m.cierre_id}"
                                 data-periodo="${esc(m.nombre)}">${esc(maestros.boton_reabrir.nombre_funcion)}</button>`;
                } else if (!m.cerrado && !m.futuro && maestros.boton_cerrar) {
                    accion = `<button type="button" class="btn btn-sm btn-outline-primary btn-cerrar-mes" data-periodo="${m.periodo}">Cerrar</button>`;
                }
                const detalle = m.cerrado
                    ? `Cerrado por ${esc(m.cerrado_por || '-')} el ${fecha(m.fecha_cierre)}`
                    : (m.borradores > 0 ? `<span class="text-warning">${m.borradores} asiento(s) en borrador</span>` : '');
                return `<div class="col-6 col-md-4 col-xl-2">
                    <div class="border rounded p-2 h-100 ${m.cerrado ? 'bg-success-subtle' : ''}">
                        <div class="d-flex justify-content-between align-items-start gap-1">
                            <span class="fw-semibold">${esc(m.nombre)}</span>${estado}
                        </div>
                        <div class="small text-muted mt-1">${m.asientos} asiento(s)</div>
                        <div class="small mt-1">${detalle}</div>
                        <div class="mt-2">${accion}</div>
                    </div></div>`;
            }).join(''));
        }).fail((xhr) => error(null, xhr));
    }

    // ------------------------------------------------------------------ historial
    function iniciarTabla() {
        tabla = $('#tablaCierres').DataTable({
            ajax: {
                url: AJAX,
                data: function (d) {
                    d.accion = 'listar';
                    d.ejercicio = ejercicio();
                    d.incluir_reabiertos = $('#cpcReabiertos').is(':checked') ? 1 : 0;
                    d.empresa_idx = empresa_idx;
                },
                dataSrc: 'data'
            },
            paging: false,
            info: false,
            order: [],
            columns: [
                { data: 'periodo_texto', render: (d, t, r) => t === 'sort' ? r.periodo : esc(d) },
                { data: 'estado', render: (d, t, r) => r.tabla_estado_registro_id == 5
                    ? `<span class="badge bg-success">${esc(d || 'Cerrado')}</span>`
                    : `<span class="badge bg-secondary">${esc(d || 'Reabierto')}</span>` },
                { data: 'usuario', render: (d) => esc(d || '-') },
                { data: 'fecha_creacion', render: (d, t) => t === 'sort' ? d : fecha(d) },
                { data: 'observaciones', className: 'ui-detalle', render: (d) => esc(d || '') },
                { data: 'motivo_reapertura', className: 'ui-detalle', render: (d, t, r) => r.fecha_reapertura
                    ? `${fecha(r.fecha_reapertura)} - ${esc(r.usuario_reapertura || '')}: ${esc(d || '')}` : '' },
                { data: null, orderable: false, className: 'text-center text-nowrap', render: (d, t, r) =>
                    (r.tabla_estado_registro_id == 5 && maestros.boton_reabrir)
                        ? `<button type="button" class="btn btn-sm ${claseBoton(maestros.boton_reabrir, 'btn-danger')} btn-reabrir"
                               data-id="${r.cierre_id}" data-periodo="${esc(r.periodo_texto)}" title="${esc(maestros.boton_reabrir.descripcion || '')}">
                               <i class="${maestros.boton_reabrir.icono_clase || 'fas fa-unlock'}"></i></button>`
                        : '' }
            ],
            language: { url: window.APP_ASSETS_URL + 'js/datatables-i18n-2.1.8/es-ES.json' }
        });
    }

    function recargar() {
        cargarResumen();
        if (tabla) tabla.ajax.reload(null, false);
    }

    // ------------------------------------------------------------------ cerrar
    // Meses del ejercicio elegido en el modal (los abiertos y no futuros), "" = todo el ejercicio
    function cargarMesesModal(ej, seleccion) {
        const pintar = (meses) => {
            $('#cerrarMes').html('<option value="">Todo el ejercicio</option>' + meses
                .filter(m => !m.cerrado && !m.futuro)
                .map(m => `<option value="${m.periodo}">${esc(m.nombre)}</option>`).join(''));
            $('#cerrarMes').val(seleccion || '');
        };
        if (resumen.ejercicio && resumen.ejercicio.ejercicio === ej) return pintar(resumen.meses);
        $.getJSON(AJAX, { accion: 'resumen', ejercicio: ej, empresa_idx: empresa_idx }, (res) => {
            if (!res.success) return error(res);
            pintar(res.data.meses);
        }).fail((xhr) => error(null, xhr));
    }

    function abrirCerrar(periodo) {
        $('#formCerrar')[0].reset();
        $('#cerrarEjercicio').val(String(ejercicio()));
        cargarMesesModal(ejercicio(), periodo);
        $('#modalCerrar').modal('show');
    }

    $('#cerrarEjercicio').on('change', function () {
        cargarMesesModal(parseInt($(this).val(), 10), '');
    });

    function enviarCierre(forzar) {
        const periodo = $('#cerrarMes').val();
        const datos = {
            accion: 'cerrar', empresa_idx: empresa_idx, ejercicio: $('#cerrarEjercicio').val(),
            anio: periodo ? periodo.split('-')[0] : 0, mes: periodo ? parseInt(periodo.split('-')[1], 10) : 0,
            observaciones: $('#cerrarObs').val(), forzar: forzar ? 1 : 0
        };
        const btn = $('#btnConfirmarCierre').prop('disabled', true);
        $.post(AJAX, datos, function (res) {
            btn.prop('disabled', false);
            if (!res.success) return error(res);
            if (res.data && res.data.requiere_confirmacion) {
                const lista = res.data.borradores.map(b => `<li>${esc(b.periodo)}: ${b.borradores}</li>`).join('');
                Swal.fire({
                    icon: 'warning', title: 'Hay asientos en borrador',
                    html: `<ul class="text-start mb-2">${lista}</ul>Con el período cerrado no se van a poder completar ni confirmar. ¿Cerrar igual?`,
                    showCancelButton: true, confirmButtonText: 'Cerrar igual', cancelButtonText: 'Cancelar'
                }).then(r => { if (r.isConfirmed) enviarCierre(true); });
                return;
            }
            $('#modalCerrar').modal('hide');
            Swal.fire({ icon: 'success', title: res.message, timer: 1800, showConfirmButton: false });
            recargar();
        }, 'json').fail((xhr) => { btn.prop('disabled', false); error(null, xhr); });
    }

    $('#formCerrar').on('submit', function (e) {
        e.preventDefault();
        if (!$('#cerrarEjercicio').val()) return Swal.fire({ icon: 'warning', title: 'Revisá los datos', text: 'Elegí el ejercicio.' });
        enviarCierre(false);
    });

    $(document).on('click', '#btnNuevo', () => abrirCerrar(''));
    $(document).on('click', '.btn-cerrar-mes', function () { abrirCerrar(String($(this).data('periodo'))); });

    // ------------------------------------------------------------------ reabrir
    $(document).on('click', '.btn-reabrir', function () {
        const id = $(this).data('id');
        Swal.fire({
            icon: 'question', title: 'Reabrir ' + $(this).data('periodo'),
            text: 'Se van a poder volver a cargar y modificar asientos de ese mes.',
            input: 'text', inputLabel: 'Motivo', inputAttributes: { maxlength: 255 },
            inputValidator: (v) => (!v || !v.trim()) ? 'Ingresá el motivo.' : undefined,
            showCancelButton: true, confirmButtonText: 'Reabrir', cancelButtonText: 'Cancelar'
        }).then(r => {
            if (!r.isConfirmed) return;
            $.post(AJAX, { accion: 'reabrir', cierre_id: id, motivo: r.value, empresa_idx: empresa_idx }, function (res) {
                if (!res.success) return error(res);
                Swal.fire({ icon: 'success', title: res.message, timer: 1800, showConfirmButton: false });
                recargar();
            }, 'json').fail((xhr) => error(null, xhr));
        });
    });

    // ------------------------------------------------------------------ inicio
    $('#cpcEjercicio').on('change', recargar);
    $('#cpcReabiertos').on('change', () => tabla.ajax.reload(null, false));
    $('#btnCpcRecargar').on('click', recargar);

    $.getJSON(AJAX, { accion: 'maestros', empresa_idx: empresa_idx }, function (res) {
        if (!res.success) return error(res);
        maestros = res.data;
        const opciones = maestros.ejercicios.map(e => `<option value="${e.ejercicio}">${esc(e.texto)}${e.irregular ? ' · irregular' : ''}</option>`).join('');
        $('#cpcEjercicio, #cerrarEjercicio').html(opciones);
        $('#cpcEjercicio').val(String(maestros.ejercicio_actual));
        const b = maestros.boton_cerrar;
        if (b) {
            $('#contenedor-boton-agregar').html(`<button type="button" class="btn btn-sm ${claseBoton(b, 'btn-primary')}" id="btnNuevo">
                <i class="${b.icono_clase || 'fas fa-lock'} me-1"></i>${esc(b.nombre_funcion)}</button>`);
        }
        iniciarTabla();
        cargarResumen();
    }).fail((xhr) => error(null, xhr));
});
