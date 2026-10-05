/**
 * Javascript para Tablero de Cumplimiento (Página 355 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/tablero_cumplimiento.js
 */

document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresaIdx = urlParams.get('empresa_id') || 2;
    const ajaxUrl = 'tablero_cumplimiento_ajax.php';

    let tablaCierres = null;
    let permisosUsuario = [];

    // Precompletar fechas del mes actual en formulario de cierre
    const hoy = new Date();
    const primerDia = new Date(hoy.getFullYear(), hoy.getMonth(), 1).toISOString().split('T')[0];
    const ultimoDia = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0).toISOString().split('T')[0];
    $('#cierreDesde').val(primerDia);
    $('#cierreHasta').val(ultimoDia);

    // Cargar datos actuales del tablero
    function cargarTablero() {
        $.getJSON(ajaxUrl, { accion: 'indicadores_actuales', empresa_idx: empresaIdx }, function (res) {
            if (!res.success) return;

            permisosUsuario = res.funciones_permitidas || [];

            // 1. Tarjetas Superiores
            const act = res.actuales;
            $('#kpiTasaCumplimiento').text(act.porcentaje_cumplimiento + '%');
            $('#badgePonderado').text('Pond: ' + act.porcentaje_ponderado + '%');
            $('#barCumplimiento').css('width', act.porcentaje_cumplimiento + '%');
            
            if (act.porcentaje_cumplimiento < 70) {
                $('#barCumplimiento').removeClass('bg-success bg-warning').addClass('bg-danger');
                $('#kpiTasaCumplimiento').removeClass('text-success text-warning').addClass('text-danger');
            } else if (act.porcentaje_cumplimiento < 90) {
                $('#barCumplimiento').removeClass('bg-success bg-danger').addClass('bg-warning');
                $('#kpiTasaCumplimiento').removeClass('text-success text-danger').addClass('text-warning');
            } else {
                $('#barCumplimiento').removeClass('bg-warning bg-danger').addClass('bg-success');
                $('#kpiTasaCumplimiento').removeClass('text-warning text-danger').addClass('text-success');
            }

            $('#kpiATiempo').text(act.cumplidos_tiempo);
            $('#kpiFueraPlazo').text(act.cumplidos_plazo);
            $('#kpiIncumplidos').text(act.incumplidos);
            $('#kpiAbiertos').text(act.abiertos);
            $('#kpiPeriodoMes').text('Ciclos con vencimiento en ' + act.periodo_desde + ' al ' + act.periodo_hasta);

            // 2. Desglose por Criticidad
            let htmlCrit = '';
            if (res.criticidades && res.criticidades.length > 0) {
                res.criticidades.forEach(function (c) {
                    let colorBar = 'bg-primary';
                    if (c.porcentaje < 70) colorBar = 'bg-danger';
                    else if (c.porcentaje < 90) colorBar = 'bg-warning';

                    htmlCrit += `
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1 small">
                                <span class="fw-semibold">${c.criticidad} <span class="text-muted font-monospace">(${c.cumplidos}/${c.total})</span></span>
                                <span class="fw-bold">${c.porcentaje}%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar ${colorBar}" style="width: ${c.porcentaje}%"></div>
                            </div>
                        </div>
                    `;
                });
            } else {
                htmlCrit = '<p class="text-muted small text-center my-3">No hay ciclos registrados para este mes.</p>';
            }
            $('#contenedorCriticidades').html(htmlCrit);

            // 3. Desglose por Entidad
            let htmlEnt = '';
            if (res.entidades && res.entidades.length > 0) {
                res.entidades.forEach(function (e) {
                    let colorBar = 'bg-info';
                    if (e.porcentaje < 70) colorBar = 'bg-danger';
                    else if (e.porcentaje < 90) colorBar = 'bg-warning';

                    htmlEnt += `
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1 small">
                                <span class="fw-semibold text-truncate" style="max-width: 280px;" title="${e.entidad_nombre}">${e.entidad_nombre}</span>
                                <span class="fw-bold">${e.porcentaje}% <small class="text-muted font-monospace">(${e.cumplidos}/${e.total})</small></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar ${colorBar}" style="width: ${e.porcentaje}%"></div>
                            </div>
                        </div>
                    `;
                });
            } else {
                htmlEnt = '<p class="text-muted small text-center my-3">No hay ciclos vinculados a entidades para este mes.</p>';
            }
            $('#contenedorEntidades').html(htmlEnt);

            // Permisos
            if (!permisosUsuario.includes('calcular_cierre_periodo')) {
                $('#formCalcularCierre').parent().parent().hide();
            }
        });
    }

    // Inicializar DataTable Historial de Cierres
    function initDataTable() {
        tablaCierres = $('#tablaCierres').DataTable({
            responsive: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            dom: '<"row mb-2"<"col-12 col-md-6"><"col-12 col-md-6 text-end"f>>rt<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>',
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            ajax: {
                url: ajaxUrl,
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar_cierres';
                    d.empresa_idx = empresaIdx;
                },
                dataSrc: function (json) {
                    if (!json.success) {
                        Swal.fire('Error', json.message || 'Error al cargar los cierres', 'error');
                        return [];
                    }
                    return json.data || [];
                }
            },
            columns: [
                { data: 'indicador_cierre_id', className: 'text-muted small fw-bold' },
                { data: 'periodo_desde_fmt', className: 'small' },
                { data: 'periodo_hasta_fmt', className: 'small' },
                { data: 'ciclos_contados', className: 'fw-bold' },
                {
                    data: 'cumplidos_a_tiempo',
                    render: function (data) {
                        return `<span class="text-success fw-bold">${data}</span>`;
                    }
                },
                {
                    data: 'cumplidos_fuera_plazo',
                    render: function (data) {
                        return `<span class="text-warning fw-bold">${data}</span>`;
                    }
                },
                {
                    data: 'incumplidos',
                    render: function (data) {
                        return parseInt(data) > 0 ? `<span class="text-danger fw-bold">${data}</span>` : `<span class="text-muted">0</span>`;
                    }
                },
                { data: 'eximidos', className: 'text-muted' },
                {
                    data: null,
                    render: function (data) {
                        const pct = parseFloat(data.porcentaje_cumplimiento);
                        let badgeClass = 'bg-success';
                        if (pct < 70) badgeClass = 'bg-danger';
                        else if (pct < 90) badgeClass = 'bg-warning text-dark';
                        return `<span class="badge ${badgeClass} fs-6">${pct}%</span>`;
                    }
                },
                { data: 'calculado_en_fmt', className: 'small text-muted' },
                {
                    data: 'calculado_por_nombre',
                    render: function (data) {
                        return `<span class="small"><i class="far fa-user me-1 text-muted"></i>${data || 'Sistema'}</span>`;
                    }
                }
            ]
        });
    }

    // Refrescar
    $('#btnRefrescarTablero').on('click', function () {
        cargarTablero();
        tablaCierres.ajax.reload();
    });

    // Calcular y Guardar Cierre de Período
    $('#formCalcularCierre').on('submit', function (e) {
        e.preventDefault();
        const desde = $('#cierreDesde').val();
        const hasta = $('#cierreHasta').val();

        if (!desde || !hasta) {
            Swal.fire('Atención', 'Seleccione el rango de fechas a cerrar.', 'warning');
            return;
        }

        const btn = $('#btnCalcularCierre');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Consolidando...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                accion: 'calcular_cierre',
                empresa_idx: empresaIdx,
                periodo_desde: desde,
                periodo_hasta: hasta
            },
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Calcular y Grabar Cierre');
                if (res.success) {
                    Swal.fire('Cierre Consolidado', `${res.message}<br><br><strong>Ciclos evaluados:</strong> ${res.total_ciclos}<br><strong>Tasa de cumplimiento:</strong> ${res.porcentaje_cumplimiento}%<br><strong>Tasa ponderada:</strong> ${res.porcentaje_ponderado}%`, 'success');
                    cargarTablero();
                    tablaCierres.ajax.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () {
                btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Calcular y Grabar Cierre');
                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
            }
        });
    });

    // Iniciar
    cargarTablero();
    initDataTable();
});
