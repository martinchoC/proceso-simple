/**
 * modules/sindicatos/ctacte_empleadores.js
 * Interacción y controladores reactivos para Cuenta Corriente de Empleadores (Página 1032).
 */

document.addEventListener('DOMContentLoaded', function () {
    const URL_AJAX = 'ctacte_empleadores_ajax.php';

    const formatoMoneda = (val) => {
        return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(val || 0);
    };

    function cargarComboEmpleadores() {
        fetch(URL_AJAX + '?accion=combo_empleadores')
            .then(res => res.json())
            .then(res => {
                if (!res.success) return;
                const opts = res.data.map(e => `<option value="${e.empleador_id}">${e.razon_social} (${e.cuit})</option>`).join('');
                $('#selectEmpleador').append(opts);
            })
            .catch(err => console.error("Error al cargar combo empleadores:", err));
    }

    $('#selectEmpleador').on('change', function () {
        const empId = $(this).val();
        if (!empId) {
            $('#seccionTotales').hide();
            $('#btnImprimirCtacte').prop('disabled', true);
            $('#tbodyCtacte').html('<tr><td colspan="8" class="text-center py-4 text-muted">Seleccione un empleador para visualizar su cuenta corriente</td></tr>');
            return;
        }

        $('#tbodyCtacte').html('<tr><td colspan="8" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Consultando movimientos de cuenta corriente...</td></tr>');

        fetch(URL_AJAX + `?accion=consultar&empleador_id=${empId}`)
            .then(res => res.json())
            .then(res => {
                if (!res.success || !res.data) {
                    $('#tbodyCtacte').html('<tr><td colspan="8" class="text-center text-warning py-4">No se pudieron recuperar los movimientos</td></tr>');
                    return;
                }

                const d = res.data;
                $('#cardTotalDebe').text(formatoMoneda(d.total_debe));
                $('#cardTotalHaber').text(formatoMoneda(d.total_haber));
                $('#cardSaldoActual').text(formatoMoneda(d.saldo_actual));
                $('#seccionTotales').show();
                $('#btnImprimirCtacte').prop('disabled', false);

                let html = '';
                (d.movimientos || []).forEach(m => {
                    const de = m.debe > 0 ? formatoMoneda(m.debe) : '-';
                    const ha = m.haber > 0 ? formatoMoneda(m.haber) : '-';
                    const saCls = m.saldo_acumulado > 0 ? 'text-danger' : 'text-success';
                    const pend = parseFloat(m.importe_pendiente) > 0 ? `<span class="badge bg-danger">${formatoMoneda(m.importe_pendiente)}</span>` : '<span class="badge bg-success">Saldado</span>';

                    html += `
                        <tr>
                            <td><small>${m.f_emision}</small></td>
                            <td><span class="font-monospace fw-bold">${m.comprobante_codigo || '-'}</span></td>
                            <td>${m.tipo_nombre || m.tipo_codigo}</td>
                            <td><small class="text-muted">${m.f_vto || '-'}</small></td>
                            <td class="text-end text-danger fw-semibold">${de}</td>
                            <td class="text-end text-success fw-semibold">${ha}</td>
                            <td class="text-end fw-bold ${saCls}">${formatoMoneda(m.saldo_acumulado)}</td>
                            <td class="text-end">${pend}</td>
                        </tr>
                    `;
                });

                $('#tbodyCtacte').html(html || '<tr><td colspan="8" class="text-center py-4 text-muted">El empleador no registra movimientos en cuenta corriente</td></tr>');
            })
            .catch(err => {
                console.error("Error al consultar cuenta corriente:", err);
                $('#tbodyCtacte').html('<tr><td colspan="8" class="text-center text-danger py-4">Error al consultar cuenta corriente</td></tr>');
            });
    });

    $('#btnImprimirCtacte').on('click', function () {
        window.print();
    });

    cargarComboEmpleadores();
});
