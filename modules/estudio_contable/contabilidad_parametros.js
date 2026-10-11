/**
 * JavaScript: Parámetros Contables
 * Módulo 4: Estudio Contable (contabilidad_parametros.js)
 */

$(document).ready(function() {
    let listaCuentas = [];

    function cargarCuentasYParametros() {
        const empresaId = $('#selEmpresaParam').val();
        $('#param_empresa_id').val(empresaId);

        Swal.fire({
            title: 'Cargando cuentas...',
            didOpen: () => Swal.showLoading(),
            allowOutsideClick: false
        });

        // 1. Obtener cuentas
        $.ajax({
            url: 'contabilidad_parametros_ajax.php',
            type: 'GET',
            data: { accion: 'listar_cuentas', empresa_id: empresaId },
            dataType: 'json',
            success: function(respCuentas) {
                if (respCuentas.success) {
                    listaCuentas = respCuentas.data || [];
                    poblarSelectsCuentas();

                    // 2. Obtener parámetros existentes
                    $.ajax({
                        url: 'contabilidad_parametros_ajax.php',
                        type: 'GET',
                        data: { accion: 'obtener_parametros', empresa_id: empresaId },
                        dataType: 'json',
                        success: function(respParam) {
                            Swal.close();
                            if (respParam.success && respParam.data) {
                                const p = respParam.data;
                                $('#cuenta_deudores_ventas_id').val(p.cuenta_deudores_ventas_id);
                                $('#cuenta_ventas_defecto_id').val(p.cuenta_ventas_defecto_id);
                                $('#cuenta_iva_debito_id').val(p.cuenta_iva_debito_id);
                                $('#cuenta_proveedores_id').val(p.cuenta_proveedores_id);
                                $('#cuenta_gastos_defecto_id').val(p.cuenta_gastos_defecto_id);
                                $('#cuenta_iva_credito_id').val(p.cuenta_iva_credito_id);
                                $('#cuenta_caja_defecto_id').val(p.cuenta_caja_defecto_id);
                                $('#cuenta_banco_defecto_id').val(p.cuenta_banco_defecto_id);
                                $('#cuenta_resultado_ejercicio_id').val(p.cuenta_resultado_ejercicio_id);
                                $('#cuenta_resultados_acumulados_id').val(p.cuenta_resultados_acumulados_id);
                            } else {
                                $('#formParametrosContables')[0].reset();
                                $('#param_empresa_id').val(empresaId);
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'Fallo al consultar los parámetros de la empresa.', 'error');
                        }
                    });
                } else {
                    Swal.fire('Error', 'No se pudieron cargar las cuentas contables.', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Fallo al obtener cuentas contables.', 'error');
            }
        });
    }

    function poblarSelectsCuentas() {
        let optionsHtml = '<option value="">-- Seleccionar Cuenta --</option>';
        listaCuentas.forEach(function(c) {
            optionsHtml += `<option value="${c.cont_cuenta_id}">[${c.codigo}] ${c.nombre} (${c.naturaleza})</option>`;
        });
        $('.select-cuenta').html(optionsHtml);
    }

    cargarCuentasYParametros();

    $('#selEmpresaParam').on('change', function() {
        cargarCuentasYParametros();
    });

    $('#btnRecargarParam').on('click', function() {
        cargarCuentasYParametros();
    });

    // Guardar Parámetros
    $('#formParametrosContables').on('submit', function(e) {
        e.preventDefault();

        const formData = $(this).serialize();
        $('#btnGuardarParam').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: 'contabilidad_parametros_ajax.php?accion=guardar_parametros',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(resp) {
                $('#btnGuardarParam').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Parámetros Contables');
                if (resp.success) {
                    Swal.fire('Guardado', 'Los parámetros contables han sido actualizados con éxito.', 'success');
                } else {
                    Swal.fire('Error', resp.message || 'No se pudo guardar la configuración.', 'error');
                }
            },
            error: function() {
                $('#btnGuardarParam').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Parámetros Contables');
                Swal.fire('Error', 'Fallo de conexión al guardar parámetros.', 'error');
            }
        });
    });
});
