/**
 * Javascript para Consola del Generador y Eventos (Página 345 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/ciclos_generador.js
 */

document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresaIdx = urlParams.get('empresa_id') || 2;
    const ajaxUrl = 'modules/ml/ciclos_generador_ajax.php';

    // Cargar Métricas y KPIs
    function cargarMetricas() {
        $.ajax({
            url: ajaxUrl,
            type: 'GET',
            data: { accion: 'metricas', empresa_idx: empresaIdx },
            dataType: 'json',
            success: function (res) {
                if (!res.success) return;
                const m = res.metricas;
                $('#kpiObligaciones').text(m.total_obligaciones_asignadas);
                $('#kpiCiclosAbiertos').text(m.total_ciclos_abiertos);
                $('#kpiCiclosCumplidos').text(m.total_ciclos_cumplidos);
                $('#kpiTareas').text(m.total_tareas_instanciadas);
            }
        });
    }

    // Cargar Combos
    function cargarCombos() {
        $.ajax({
            url: ajaxUrl,
            type: 'GET',
            data: { accion: 'combos', empresa_idx: empresaIdx },
            dataType: 'json',
            success: function (res) {
                if (!res.success) return;

                // Entidades Proyección
                const $entProy = $('#proyEntidadId');
                $entProy.empty().append('<option value="">-- Todas las entidades asignadas --</option>');
                res.entidades.forEach(e => {
                    $entProy.append(`<option value="${e.entidad_id}">${e.nombre} (${e.codigo})</option>`);
                });

                // Obligaciones Gatillo por Evento
                const $obEvt = $('#eventoObligacionId');
                $obEvt.empty().append('<option value="">-- Seleccionar obligación gatillada --</option>');
                res.obligaciones_evento.forEach(o => {
                    $obEvt.append(`<option value="${o.obligacion_asignada_id}">[${o.entidad_nombre}] ${o.obligacion_codigo} - ${o.obligacion_titulo}</option>`);
                });
            }
        });
    }

    // Cargar Últimos Ciclos
    function cargarUltimosCiclos() {
        $.ajax({
            url: ajaxUrl,
            type: 'GET',
            data: { accion: 'ultimos_ciclos', empresa_idx: empresaIdx },
            dataType: 'json',
            success: function (res) {
                if (!res.success) return;
                const $tbody = $('#tbodyUltimosCiclos');
                $tbody.empty();
                if (!res.data || res.data.length === 0) {
                    $tbody.append('<tr><td colspan="8" class="text-center text-muted">Sin ciclos generados recientemente</td></tr>');
                    return;
                }

                res.data.forEach(c => {
                    $tbody.append(`<tr>
                        <td class="text-muted small fw-bold">#${c.ciclo_id}</td>
                        <td><span class="fw-semibold">${c.entidad_nombre}</span></td>
                        <td><span class="badge bg-secondary me-1">${c.obligacion_codigo}</span><span class="small">${c.obligacion_titulo}</span></td>
                        <td><small>${c.periodo_desde_fmt} al ${c.periodo_hasta_fmt}</small></td>
                        <td><small class="text-danger fw-semibold">${c.fecha_vencimiento_fmt}</small></td>
                        <td class="text-center"><span class="badge bg-light text-dark border">${c.total_tareas}</span></td>
                        <td><span class="badge bg-info text-dark">${c.estado_nombre}</span></td>
                        <td><small class="text-muted">${c.creado_en_fmt}</small></td>
                    </tr>`);
                });
            }
        });
    }

    // Formulario de Proyección Masiva (Caso C02)
    $('#formProyeccion').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btnEjecutarProy');
        const $alerta = $('#alertaProy');

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Proyectando...');
        $alerta.hide();

        const data = $(this).serialize() + '&accion=proyectar&empresa_idx=' + empresaIdx;
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function (res) {
                $btn.prop('disabled', false).html('<i class="fas fa-magic me-1"></i>Proyectar e Instanciar Ciclos');
                if (res.success) {
                    $alerta.removeClass('alert-danger').addClass('alert-success').html(`<i class="fas fa-check-circle me-1"></i>${res.message}`).show();
                    cargarMetricas();
                    cargarUltimosCiclos();
                } else {
                    $alerta.removeClass('alert-success').addClass('alert-danger').html(`<i class="fas fa-times-circle me-1"></i>${res.message}`).show();
                }
            },
            error: function () {
                $btn.prop('disabled', false).html('<i class="fas fa-magic me-1"></i>Proyectar e Instanciar Ciclos');
                $alerta.removeClass('alert-success').addClass('alert-danger').text('Error de comunicación con el servidor.').show();
            }
        });
    });

    // Formulario de Disparo por Evento (Casos C07 y C31)
    $('#formDispararEvento').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btnDispararEvento');
        const $alerta = $('#alertaEvento');

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Gatillando...');
        $alerta.hide();

        const data = $(this).serialize() + '&accion=disparar_evento&empresa_idx=' + empresaIdx;
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function (res) {
                $btn.prop('disabled', false).html('<i class="fas fa-exclamation-circle me-1"></i>Gatillar Ciclo Inmediato');
                if (res.success) {
                    $alerta.removeClass('alert-danger').addClass('alert-success').html(`<i class="fas fa-check-circle me-1"></i>${res.message}`).show();
                    $('#eventoDescripcion').val('');
                    cargarMetricas();
                    cargarUltimosCiclos();
                } else {
                    $alerta.removeClass('alert-success').addClass('alert-danger').html(`<i class="fas fa-times-circle me-1"></i>${res.message}`).show();
                }
            },
            error: function () {
                $btn.prop('disabled', false).html('<i class="fas fa-exclamation-circle me-1"></i>Gatillar Ciclo Inmediato');
                $alerta.removeClass('alert-success').addClass('alert-danger').text('Error de comunicación con el servidor.').show();
            }
        });
    });

    // Iniciar
    cargarMetricas();
    cargarCombos();
    cargarUltimosCiclos();
});
