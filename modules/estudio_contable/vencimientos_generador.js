/**
 * JavaScript: Generador Masivo de Vencimientos
 * Módulo 4: Estudio Contable (vencimientos_generador.js)
 */

$(document).ready(function () {
    cargarCombos();

    function cargarCombos() {
        $.getJSON('vencimientos_generador_ajax.php', { accion: 'obtener_combos' }, function (res) {
            if (!res.success) return;
            let d = res.data;

            let optOb = '<option value="0">-- Todas las obligaciones del padrón --</option>';
            d.obligaciones.forEach(o => {
                optOb += `<option value="${o.impuesto_obligacion_id}">[${o.organismo}] ${o.nombre} (${o.codigo_oficial})</option>`;
            });
            $('#gen_impuesto_obligacion_id').html(optOb);

            let optCli = '<option value="0">-- Todo el padrón de clientes --</option>';
            d.clientes.forEach(c => {
                optCli += `<option value="${c.cliente_id}">${c.razon_social} (${c.cuit})</option>`;
            });
            $('#gen_cliente_id').html(optCli);
        });
    }

    // Botón Previsualizar
    $('#btnPreview').on('click', function () {
        let periodo = $('#gen_periodo_fiscal').val();
        let obligacion_id = $('#gen_impuesto_obligacion_id').val();
        let cliente_id = $('#gen_cliente_id').val();

        if (!periodo || periodo.length !== 6) {
            Swal.fire('Atención', 'Ingrese un período fiscal válido en formato YYYYMM (Ej: 202610).', 'warning');
            return;
        }

        Swal.fire({
            title: 'Calculando calendario...',
            text: 'Cruzando matriz impositiva con terminaciones de CUIT',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        $.getJSON('vencimientos_generador_ajax.php', {
            accion: 'preview',
            periodo_fiscal: periodo,
            impuesto_obligacion_id: obligacion_id,
            cliente_id: cliente_id
        }, function (res) {
            Swal.close();
            if (!res.success) {
                Swal.fire('Error', res.message, 'error');
                return;
            }

            let items = res.data || [];
            if (items.length === 0) {
                Swal.fire('Sin registros', 'No se encontraron obligaciones aplicables con los filtros seleccionados.', 'info');
                $('#cardResultados').hide();
                return;
            }

            let nuevos = 0;
            let ya_existentes = 0;
            let html = '';

            items.forEach(it => {
                let badgeEstado = '';
                if (it.ya_generado) {
                    ya_existentes++;
                    badgeEstado = '<span class="badge bg-secondary"><i class="fas fa-check-double me-1"></i>Ya Registrado</span>';
                } else {
                    nuevos++;
                    badgeEstado = '<span class="badge bg-primary"><i class="fas fa-plus-circle me-1"></i>A Generar</span>';
                }

                html += `
                    <tr>
                        <td>${badgeEstado}</td>
                        <td><strong>${it.razon_social}</strong></td>
                        <td>${it.cuit} <span class="badge bg-light text-dark border">Term: ${it.terminacion_cuit}</span></td>
                        <td><span class="badge bg-dark fs-8 me-1">${it.organismo}</span> <strong>${it.codigo_oficial}</strong></td>
                        <td>${it.jurisdiccion_nombre}</td>
                        <td>${it.periodo_fiscal}</td>
                        <td><strong class="text-primary">${it.f_limite}</strong></td>
                    </tr>
                `;
            });

            $('#preview_tbody').html(html);
            $('#resumen_conteo').html(`Total: <strong>${items.length}</strong> obligaciones evaluadas (<span class="text-success fw-bold">${nuevos} nuevas a emitir</span>, ${ya_existentes} ya registradas)`);
            $('#cardResultados').slideDown();
        }).fail(function () {
            Swal.fire('Error', 'Error de conexión al generar vista previa.', 'error');
        });
    });

    // Botón Ejecutar Lote Masivo
    $('#btnEjecutarLote').on('click', function () {
        let periodo = $('#gen_periodo_fiscal').val();
        let obligacion_id = $('#gen_impuesto_obligacion_id').val();
        let cliente_id = $('#gen_cliente_id').val();

        Swal.fire({
            title: '¿Confirmar generación masiva?',
            text: `Se generarán las instancias de vencimiento correspondientes al período fiscal ${periodo}.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, generar en lote',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Procesando lote...',
                    text: 'Creando registros en tabla de proceso largo con estado Borrador',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                $.post('vencimientos_generador_ajax.php', {
                    accion: 'ejecutar',
                    periodo_fiscal: periodo,
                    impuesto_obligacion_id: obligacion_id,
                    cliente_id: cliente_id
                }, function (res) {
                    if (res.success) {
                        Swal.fire({
                            title: '¡Generación Completada!',
                            text: res.message,
                            icon: 'success',
                            confirmButtonText: 'Ir al Tablero de Vencimientos'
                        }).then(() => {
                            window.location.href = 'vencimientos_tablero.php';
                        });
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }, 'json').fail(function () {
                    Swal.fire('Error', 'Error de comunicación durante la ejecución masiva.', 'error');
                });
            }
        });
    });
});
