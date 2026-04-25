$(document).ready(function () {
    const empresa_idx = 2;
    const pagina_idx = 57; // ID para facturas proveedor
    
    var tabla;
    var currentPage = 0;
    var currentOrder = [[1, 'asc']];
    var currentSearch = '';
    
    // Variables para manejo de detalles
    var detalles = [];
    var proveedorActualId = null;
    var proveedorSucursalActualId = null;
    var timeoutBusqueda = null;
    var selectedIndex = -1;
    var impuestosFactura = [];

    // ========== FUNCIONES DE DATATABLE ==========
    function calcularFechaVencimiento(fechaEmision, condicionPagoId) {
        if (!fechaEmision || !condicionPagoId) return '';
        
        // Buscar en el combo de condiciones de pago los días
        var selectedOption = $('#condicion_pago_id option:selected');
        var dias = selectedOption.data('dias') || 0;
        
        if (dias <= 0) return '';
        
        var fecha = new Date(fechaEmision);
        fecha.setDate(fecha.getDate() + dias);
        
        // Formatear como YYYY-MM-DD
        var year = fecha.getFullYear();
        var month = String(fecha.getMonth() + 1).padStart(2, '0');
        var day = String(fecha.getDate()).padStart(2, '0');
        
        return `${year}-${month}-${day}`;
    }
    // ========== FUNCIONES DE OTROS IMPUESTOS ==========
    // En facturas_proveedores.js, reemplazar la función cargarImpuestosConfig

function cargarImpuestosConfig() {
    console.log("Cargando configuración de impuestos para empresa:", empresa_idx);
    
    $.ajax({
        url: 'facturas_proveedores_ajax.php',
        type: 'GET',
        data: {
            accion: 'obtener_impuestos_config',
            empresa_idx: empresa_idx,
            comprobante_subgrupo_id: 5  // Compras/Proveedores
        },
        dataType: 'json',
        success: function(data) {
            console.log("Impuestos config cargados:", data);
            window.impuestosConfig = data || [];
            
            // Si hay una factura cargada, mostrar los impuestos ya guardados
            var facturaId = $('#factura_proveedor_id').val();
            if (facturaId && facturaId > 0) {
                cargarImpuestosFactura(facturaId);
            } else {
                // Para nueva factura, mostrar impuestos por defecto desde configuración
                cargarImpuestosPorDefecto();
            }
        },
        error: function(xhr, status, error) {
            console.error("Error cargando configuración de impuestos:", error);
            window.impuestosConfig = [];
        }
    });
}



// Reemplazar la función cargarImpuestosPorDefecto

function cargarImpuestosPorDefecto() {
    // Crear impuestos sugeridos desde la configuración
    if (window.impuestosConfig && window.impuestosConfig.length > 0) {
        impuestosFactura = [];
        
        window.impuestosConfig.forEach(function(config) {
            // Verificar si ya existe un impuesto de este tipo
            var existe = impuestosFactura.some(function(imp) {
                return imp.impuesto_tipo_id === config.impuesto_tipo_id;
            });
            
            if (!existe) {
                // Crear descripción combinada: "Impuesto - Jurisdicción"
                var descripcionCompleta = config.impuesto_tipo;
                if (config.jurisdiccion_nombre) {
                    descripcionCompleta += ' - ' + config.jurisdiccion_nombre;
                }
                
                impuestosFactura.push({
                    
                    empresa_impuesto_config_id: config.empresa_impuesto_config_id,
                    impuesto_tipo_id: config.impuesto_tipo_id,
                    impuesto_tipo: config.impuesto_tipo,
                    impuesto_tipo_descripcion: descripcionCompleta,
                    impuesto_codigo_afip: config.codigo_afip,
                    jurisdiccion_id: config.jurisdiccion_id,
                    jurisdiccion_nombre: config.jurisdiccion_nombre || '',
                    jurisdiccion_codigo: config.jurisdiccion_codigo || '',
                    condicion_fiscal_id: config.condicion_fiscal_id,
                    condicion_fiscal: config.condicion_fiscal,
                    condicion_fiscal_codigo: config.condicion_fiscal_codigo,
                    base_calculo: config.base_calculo || 'NETO_GRAVADO',
                    base_imponible: 0,
                    alicuota: parseFloat(config.alicuota || 0),
                    minimo_imponible: parseFloat(config.minimo_imponible || 0),
                    monto_fijo: parseFloat(config.monto_fijo || 0),
                    importe: 0,
                    aplica_siempre: config.aplica_siempre || 1,
                    prioridad: config.prioridad || 1,
                    tipo_calculo: config.tipo_calculo || 'manual',
                    f_desde: config.f_desde,
                    f_hasta: config.f_hasta,
                    detalle_origen: 'auto',
                    tipo_origen: 'manual'
                });
            }
        });
        
        // Ordenar por prioridad
        impuestosFactura.sort(function(a, b) {
            return (a.prioridad || 999) - (b.prioridad || 999);
        });
        
        renderizarImpuestos();
        console.log("Impuestos cargados por defecto:", impuestosFactura.length);
    } else {
        console.log("No hay configuración de impuestos para cargar");
        impuestosFactura = [];
        renderizarImpuestos();
    }
}

function cargarImpuestosFactura(facturaId) {
    console.log("Cargando impuestos de factura ID:", facturaId);
    
    $.ajax({
        url: 'facturas_proveedores_ajax.php',
        type: 'GET',
        data: {
            accion: 'obtener_impuestos_factura',
            factura_proveedor_id: facturaId
        },
        dataType: 'json',
        success: function(data) {
            console.log("Impuestos de factura cargados:", data);
            
            if (data && data.length > 0) {
                impuestosFactura = data.map(function(imp) {
                    // Crear descripción combinada
                    var descripcionCompleta = imp.impuesto_tipo || '';
                    if (imp.jurisdiccion_nombre) {
                        descripcionCompleta += ' - ' + imp.jurisdiccion_nombre;
                    }
                    if (imp.condicion_fiscal) {
                        descripcionCompleta += ' (' + imp.condicion_fiscal + ')';
                    }
                    
                    return {
                        empresa_impuesto_config_id: imp.empresa_impuesto_config_id,
                        impuesto_tipo_id: imp.impuesto_tipo_id,
                        impuesto_tipo: imp.impuesto_tipo,
                        impuesto_tipo_descripcion: descripcionCompleta,
                        jurisdiccion_id: imp.jurisdiccion_id || null,
                        jurisdiccion_nombre: imp.jurisdiccion_nombre || '',
                        condicion_fiscal: imp.condicion_fiscal || '',
                        base_calculo: imp.base_calculo || 'NETO_GRAVADO',
                        base_imponible: parseFloat(imp.base_imponible || 0),
                        alicuota: parseFloat(imp.alicuota || 0),
                        minimo_imponible: parseFloat(imp.minimo_imponible || 0),
                        importe: parseFloat(imp.importe || 0),
                        detalle_origen: imp.detalle_origen || '',
                        tipo_origen: imp.tipo_origen || 'manual'
                    };
                });
            } else {
                cargarImpuestosPorDefecto();
            }
            
            renderizarImpuestos();
            actualizarTotalConImpuestos();
        },
        error: function(xhr, status, error) {
            console.error("Error cargando impuestos de factura:", error);
            cargarImpuestosPorDefecto();
        }
    });
}
// Actualizar renderizarImpuestos

function renderizarImpuestos() {
    $('#contenedor-impuestos').empty();
    
    if (!impuestosFactura || impuestosFactura.length === 0) {
        var htmlVacio = `
        <div class="impuestos-vacio text-center p-4 border rounded bg-light">
            <i class="fas fa-receipt fa-2x text-muted mb-2"></i>
            <p class="mb-0">No hay impuestos adicionales</p>
            <small class="text-muted">Los impuestos se cargarán automáticamente desde la configuración</small>
        </div>`;
        $('#contenedor-impuestos').html(htmlVacio);
        return;
    }
    
    var html = `
    <div style="overflow-x: auto;">
        <table class="table table-sm table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Impuesto</th>
                    <th class="text-end">Base Imponible</th>
                    <th class="text-center">Alícuota %</th>
                    <th class="text-end">Importe</th>
                    <th class="text-center" width="80">Acciones</th>
                </tr>
            </thead>
            <tbody>`;
    
    impuestosFactura.forEach(function(impuesto, index) {
        var descripcion = impuesto.impuesto_tipo_descripcion || impuesto.impuesto_tipo;
        if (!impuesto.impuesto_tipo_descripcion && impuesto.jurisdiccion_nombre) {
            descripcion += ' - ' + impuesto.jurisdiccion_nombre;
        }
        
        var baseImponible = impuesto.base_imponible || 0;
        var alicuota = impuesto.alicuota || 0;
        var importe = impuesto.importe || 0;
        
        html += `
            <tr data-impuesto-idx="${index}" data-config-id="${impuesto.empresa_impuesto_config_id || ''}">
                <td>
                    <strong>${escapeHtml(descripcion)}</strong>
                    ${impuesto.minimo_imponible > 0 ? `<br><small class="text-muted">Mínimo: ${formatNumber(impuesto.minimo_imponible, 2)}</small>` : ''}
                    ${impuesto.base_calculo && impuesto.base_calculo !== 'NETO_GRAVADO' ? `<br><small class="text-muted">Base: ${escapeHtml(impuesto.base_calculo)}</small>` : ''}
                 </td>
                <td class="text-end">
                    <input type="number" step="0.01" class="form-control form-control-sm text-end impuesto-base" 
                           data-idx="${index}" value="${baseImponible.toFixed(2)}"
                           style="min-width: 150px;">
                 </td>
                <td class="text-center">
                    <input type="number" step="0.01" class="form-control form-control-sm text-center impuesto-alicuota" 
                           data-idx="${index}" value="${alicuota.toFixed(2)}"
                           style="min-width: 80px;">
                 </td>
                <td class="text-end">
                    <input type="number" step="0.01" class="form-control form-control-sm text-end impuesto-importe" 
                           data-idx="${index}" value="${importe.toFixed(2)}"
                           style="min-width: 120px;">
                 </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger btn-eliminar-impuesto" 
                            data-idx="${index}" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                 </td>
             </tr>`;
    });
    
    html += `
            </tbody>
            <tfoot class="table-secondary">
                <tr>
                    <th colspan="3" class="text-end">Total Otros Impuestos:</th>
                    <th class="text-end text-info" id="total-otros-impuestos-display">${formatNumber(calcularTotalOtrosImpuestos(), 2)}</th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>`;
    
    $('#contenedor-impuestos').html(html);
    
    // Evento: cambio en BASE IMPONIBLE -> recalcula importe (solo si base > 0)
    $('.impuesto-base').off('input').on('input', function() {
        var idx = $(this).data('idx');
        var base = parseFloat($(this).val()) || 0;
        if (impuestosFactura[idx]) {
            impuestosFactura[idx].base_imponible = base;
            
            // Si base_imponible > 0, recalcular importe desde alicuota
            if (base > 0) {
                var alicuota = impuestosFactura[idx].alicuota || 0;
                var importe = base * (alicuota / 100);
                impuestosFactura[idx].importe = importe;
                $(this).closest('tr').find('.impuesto-importe').val(importe.toFixed(2));
            }
            // Si base_imponible es 0, mantener el importe actual (no recalcular)
            
            actualizarTotalConImpuestos();
        }
    });
    
    // Evento: cambio en ALICUOTA -> recalcula importe (solo si base > 0)
    $('.impuesto-alicuota').off('input').on('input', function() {
        var idx = $(this).data('idx');
        var alicuota = parseFloat($(this).val()) || 0;
        if (impuestosFactura[idx]) {
            impuestosFactura[idx].alicuota = alicuota;
            var base = impuestosFactura[idx].base_imponible || 0;
            
            // Solo recalcular si base_imponible > 0
            if (base > 0) {
                var importe = base * (alicuota / 100);
                impuestosFactura[idx].importe = importe;
                $(this).closest('tr').find('.impuesto-importe').val(importe.toFixed(2));
            }
            // Si base_imponible es 0, NO recalcar el importe (dejar el que tiene)
            
            actualizarTotalConImpuestos();
        }
    });
    
    // Evento: cambio en IMPORTE -> recalcular alicuota (solo si base > 0)
    $('.impuesto-importe').off('input').on('input', function() {
        var idx = $(this).data('idx');
        var importe = parseFloat($(this).val()) || 0;
        if (impuestosFactura[idx]) {
            impuestosFactura[idx].importe = importe;
            var base = impuestosFactura[idx].base_imponible || 0;
            
            // Solo recalcular alicuota si base > 0
            if (base > 0 && importe > 0) {
                var alicuota = (importe / base) * 100;
                impuestosFactura[idx].alicuota = alicuota;
                $(this).closest('tr').find('.impuesto-alicuota').val(alicuota.toFixed(2));
            }
            // Si base_imponible es 0, no podemos calcular alicuota
            
            actualizarTotalConImpuestos();
        }
    });
}


// Agregar esta función para recalcular automáticamente los impuestos

function recalcularImpuestosPorTotal() {
    if (!impuestosFactura || impuestosFactura.length === 0) return;
    
    var totalNetoGravado = parseFloat($('#subtotal').val()) || 0;
    var totalFactura = parseFloat($('#total').val()) || 0;
    
    impuestosFactura.forEach(function(impuesto, index) {
        // Determinar base imponible según el tipo de cálculo
        var baseImponible = 0;
        var baseCalculo = impuesto.base_calculo || 'NETO_GRAVADO';
        
        switch (baseCalculo) {
            case 'NETO_GRAVADO':
                baseImponible = totalNetoGravado;
                break;
            case 'TOTAL':
                baseImponible = totalFactura;
                break;
            case 'MONTO_FIJO':
                baseImponible = impuesto.monto_fijo || 0;
                break;
            default:
                baseImponible = totalNetoGravado;
        }
        
        // Verificar mínimo imponible
        if (impuesto.minimo_imponible > 0 && baseImponible < impuesto.minimo_imponible) {
            baseImponible = impuesto.minimo_imponible;
        }
        
        var importe = baseImponible * (impuesto.alicuota / 100);
        
        impuestosFactura[index].base_imponible = baseImponible;
        impuestosFactura[index].importe = importe;
    });
    
    renderizarImpuestos();
    actualizarTotalConImpuestos();
}

// Llamar a recalcularImpuestosPorTotal cuando cambien los totales
function actualizarTotales() {
    var totalNeto = 0;
    var totalDescuentoItem = 0;
    var totalDescuentoGeneral = 0;
    var totalNoGravado = 0;
    var totalExento = 0;
    var totalImpuestos = 0;
    
    detalles.forEach(function(detalle) {
        totalNeto += detalle.neto_gravado || 0;
        totalDescuentoItem += detalle.descuento_item || 0;
        totalDescuentoGeneral += detalle.descuento_general || 0;
        totalImpuestos += detalle.iva_importe || 0;
        totalNoGravado += detalle.no_gravado || 0;
        totalExento += detalle.exento || 0;
    });
    
    var totalDescuentos = totalDescuentoItem + totalDescuentoGeneral;
    var totalGeneral = totalNeto + totalImpuestos + totalNoGravado + totalExento;
    
    // Guardar valores originales en los inputs hidden
    $('#subtotal').val(totalNeto.toFixed(2));
    $('#descuentos').val(totalDescuentos.toFixed(2));
    $('#no_gravado').val(totalNoGravado.toFixed(2));
    $('#exento').val(totalExento.toFixed(2));
    $('#impuestos').val(totalImpuestos.toFixed(2));
    $('#total').val(totalGeneral.toFixed(2));
    
    // Mostrar valores formateados
    $('#total_neto_display').text(formatNumber(totalNeto, 2));
    $('#descuentos_display').text(formatNumber(totalDescuentos, 2));
    $('#no_gravado_display').text(formatNumber(totalNoGravado, 2));
    $('#exento_display').text(formatNumber(totalExento, 2));
    $('#impuestos_display').text(formatNumber(totalImpuestos, 2));
    $('#total_display').text(formatNumber(totalGeneral, 2));
    
    // Recalcular impuestos adicionales basados en los nuevos totales
    recalcularImpuestosPorTotal();
}
    function calcularTotalOtrosImpuestos() {
    var total = 0;
    if (impuestosFactura && impuestosFactura.length > 0) {
        impuestosFactura.forEach(function(impuesto) {
            var importe = parseFloat(impuesto.importe) || 0;
            total += importe;
        });
    }
    return total;
}

    function actualizarTotalConImpuestos() {
        var otrosImpuestos = calcularTotalOtrosImpuestos();
        $('#otros_impuestos').val(otrosImpuestos.toFixed(2));
        $('#otros_impuestos_display').text(formatNumber(otrosImpuestos, 2));
        
        // Recalcular total general
        var subtotal = parseFloat($('#subtotal').val()) || 0;
        var descuentos = parseFloat($('#descuentos').val()) || 0;
        var noGravado = parseFloat($('#no_gravado').val()) || 0;
        var exento = parseFloat($('#exento').val()) || 0;
        var impuestos = parseFloat($('#impuestos').val()) || 0;
        var totalGeneral = subtotal - descuentos + noGravado + exento + impuestos + otrosImpuestos;
        
        $('#total').val(totalGeneral.toFixed(2));
        $('#total_display').text(formatNumber(totalGeneral, 2));
    }

    // Actualizar mostrarModalImpuesto para usar los tipos disponibles

function mostrarModalImpuesto(impuestoData, idx) {
    if ($('#modalImpuesto').length) $('#modalImpuesto').remove();
    
    var modalHtml = `
    <div class="modal fade" id="modalImpuesto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">${idx !== undefined ? 'Editar' : 'Agregar'} Impuesto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formImpuesto">
                        <input type="hidden" id="impuesto_idx" value="${idx !== undefined ? idx : ''}">
                        <div class="mb-3">
                            <label class="form-label">Configuración de Impuesto *</label>
                            <select class="form-select" id="config_impuesto_id" required>
                                <option value="">Seleccionar impuesto configurado...</option>
                            </select>
                            <div class="invalid-feedback">Seleccione una configuración de impuesto</div>
                            <small class="form-text text-muted">Impuestos configurados en el sistema</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Base Imponible</label>
                            <input type="number" step="0.01" class="form-control" id="base_imponible" value="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alícuota %</label>
                            <input type="number" step="0.01" class="form-control" id="alicuota" value="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Importe</label>
                            <input type="number" step="0.01" class="form-control" id="importe" value="0">
                            <small class="form-text text-muted">Se calcula automáticamente: Base × Alícuota ÷ 100</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Detalle/Origen</label>
                            <input type="text" class="form-control" id="detalle_origen" placeholder="Opcional">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarImpuesto">Guardar</button>
                </div>
            </div>
        </div>
    </div>`;
    
    $('body').append(modalHtml);
    
    // Cargar configuraciones de impuestos desde la consulta SQL
    $.ajax({
        url: 'facturas_proveedores_ajax.php',
        type: 'GET',
        data: { 
            accion: 'obtener_impuestos_config',
            empresa_idx: empresa_idx,
            comprobante_subgrupo_id: 5
        },
        dataType: 'json',
        success: function(configuraciones) {
            console.log("Configuraciones de impuestos para selector:", configuraciones);
            var select = $('#config_impuesto_id');
            select.empty();
            
            if (configuraciones && configuraciones.length > 0) {
                select.append('<option value="">Seleccionar impuesto...</option>');
                configuraciones.forEach(function(config) {
                    var descripcion = config.impuesto_tipo;
                    if (config.jurisdiccion_nombre) {
                        descripcion += ' - ' + config.jurisdiccion_nombre;
                    }
                    if (config.condicion_fiscal) {
                        descripcion += ' (' + config.condicion_fiscal + ')';
                    }
                    select.append(`<option value="${config.empresa_impuesto_config_id}" 
                                        data-impuesto-tipo-id="${config.impuesto_tipo_id}"
                                        data-impuesto-tipo="${config.impuesto_tipo}"
                                        data-jurisdiccion-id="${config.jurisdiccion_id || ''}"
                                        data-jurisdiccion-nombre="${config.jurisdiccion_nombre || ''}"
                                        data-alicuota="${config.alicuota || 0}"
                                        data-minimo="${config.minimo_imponible || 0}"
                                        data-base-calculo="${config.base_calculo || 'NETO_GRAVADO'}">
                                        ${escapeHtml(descripcion)} - Alícuota: ${config.alicuota || 0}%
                                    </option>`);
                });
            } else {
                select.append('<option value="">No hay configuraciones de impuestos disponibles</option>');
            }
            
            if (impuestoData) {
                $('#config_impuesto_id').val(impuestoData.empresa_impuesto_config_id);
                $('#base_imponible').val(impuestoData.base_imponible || 0);
                $('#alicuota').val(impuestoData.alicuota || 0);
                $('#importe').val(impuestoData.importe || 0);
                $('#detalle_origen').val(impuestoData.detalle_origen || '');
            }
        },
        error: function(xhr, status, error) {
            console.error("Error cargando configuraciones:", error);
            $('#config_impuesto_id').html('<option value="">Error al cargar impuestos</option>');
        }
    });
    
    // Cuando se selecciona una configuración, cargar sus valores por defecto
    $(document).on('change', '#config_impuesto_id', function() {
        var selected = $(this).find('option:selected');
        var alicuota = selected.data('alicuota') || 0;
        var minimo = selected.data('minimo') || 0;
        $('#alicuota').val(alicuota);
        if (minimo > 0 && parseFloat($('#base_imponible').val()) < minimo) {
            $('#base_imponible').val(minimo);
        }
        calcularImporteImpuesto();
    });
    
    // Calcular importe automáticamente
    $('#base_imponible, #alicuota').on('input', function() {
        calcularImporteImpuesto();
    });
    
    function calcularImporteImpuesto() {
        var base = parseFloat($('#base_imponible').val()) || 0;
        var alicuota = parseFloat($('#alicuota').val()) || 0;
        var importe = base * (alicuota / 100);
        $('#importe').val(importe.toFixed(2));
    }
    function calcularAlicuotaDesdeImporte() {
        var base = parseFloat($('#base_imponible').val()) || 0;
        var importe = parseFloat($('#importe').val()) || 0;
        if (base > 0) {
            var alicuota = (importe / base) * 100;
            $('#alicuota').val(alicuota.toFixed(2));
        }
        }

        // Modificar la función calcularImporteImpuesto para que también pueda calcular desde importe:
        function calcularImporteImpuesto() {
        var base = parseFloat($('#base_imponible').val()) || 0;
        var alicuota = parseFloat($('#alicuota').val()) || 0;
        var importe = base * (alicuota / 100);
        $('#importe').val(importe.toFixed(2));
        }

        // Agregar evento para cuando cambia el importe
        $('#importe').on('input', function() {
        calcularAlicuotaDesdeImporte();
        });
    var modal = new bootstrap.Modal(document.getElementById('modalImpuesto'));
    modal.show();
    
    $('#btnGuardarImpuesto').off('click').on('click', function() {
        var configId = $('#config_impuesto_id').val();
        if (!configId) {
            $('#config_impuesto_id').addClass('is-invalid');
            Swal.fire({ icon: "warning", title: "Validación", text: "Debe seleccionar un impuesto", confirmButtonText: "Entendido" });
            return false;
        }
        
        var selectedOption = $('#config_impuesto_id option:selected');
        var descripcionCompleta = selectedOption.text().split(' - Alícuota:')[0];
        
        var nuevoImpuesto = {
            empresa_impuesto_config_id: parseInt(configId),
            impuesto_tipo_id: selectedOption.data('impuesto-tipo-id'),
            impuesto_tipo: selectedOption.data('impuesto-tipo'),
            impuesto_tipo_descripcion: descripcionCompleta,
            jurisdiccion_id: selectedOption.data('jurisdiccion-id') || null,
            jurisdiccion_nombre: selectedOption.data('jurisdiccion-nombre') || '',
            base_calculo: selectedOption.data('base-calculo') || 'NETO_GRAVADO',
            base_imponible: parseFloat($('#base_imponible').val()) || 0,
            alicuota: parseFloat($('#alicuota').val()) || 0,
            minimo_imponible: selectedOption.data('minimo') || 0,
            importe: parseFloat($('#importe').val()) || 0,
            detalle_origen: $('#detalle_origen').val(),
            tipo_origen: 'manual'
        };
        
        var editIdx = $('#impuesto_idx').val();
        if (editIdx !== '') {
            impuestosFactura[parseInt(editIdx)] = nuevoImpuesto;
        } else {
            impuestosFactura.push(nuevoImpuesto);
        }
        
        // Ordenar por prioridad
        impuestosFactura.sort(function(a, b) {
            return (a.prioridad || 999) - (b.prioridad || 999);
        });
        
        renderizarImpuestos();
        actualizarTotalConImpuestos();
        
        var modalInstance = bootstrap.Modal.getInstance(document.getElementById('modalImpuesto'));
        modalInstance.hide();
        
        $('#modalImpuesto').on('hidden.bs.modal', function() {
            $(this).remove();
        });
        
        

        // Crear un array limpio para enviar - RESPETANDO LOS VALORES ORIGINALES
        var impuestosParaEnviar = [];
        if (impuestosFactura && impuestosFactura.length > 0) {
            impuestosParaEnviar = impuestosFactura.map(function(imp) {
                return {
                    empresa_impuesto_config_id: imp.empresa_impuesto_config_id || 0,
                    tipo_origen: imp.tipo_origen || 'manual',
                    // Enviar los valores TAL CUAL están, sin modificar
                    base_imponible: parseFloat(imp.base_imponible) || 0,
                    alicuota: parseFloat(imp.alicuota) || 0,
                    importe: parseFloat(imp.importe) || 0,
                    detalle_origen: imp.detalle_origen || null
                };
            });
        }
        
        formData.append('impuestos_adicionales', JSON.stringify(impuestosParaEnviar));
        console.log("Impuestos a enviar (respetando valores originales):", impuestosParaEnviar);


    });
}
    // Eventos de impuestos
    $(document).on('click', '#btnAgregarImpuesto', function() {
        if (!proveedorActualId) {
            Swal.fire({ icon: "warning", title: "Seleccione proveedor", text: "Debe seleccionar un proveedor primero" });
            return;
        }
        mostrarModalImpuesto(null);
    });

    $(document).on('click', '.btn-editar-impuesto', function() {
        var idx = $(this).data('idx');
        mostrarModalImpuesto(impuestosFactura[idx], idx);
    });

    $(document).on('click', '.btn-eliminar-impuesto', function() {
        var idx = $(this).data('idx');
        Swal.fire({
            title: '¿Eliminar impuesto?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                impuestosFactura.splice(idx, 1);
                renderizarImpuestos();
                actualizarTotalConImpuestos();
            }
        });
    });

    
    
    // ========== ACTUALIZAR FECHA DE CONTABILIDAD ==========
    function actualizarFechaContabilidad() {
        var fEmision = $('#f_emision').val();
        if (fEmision) {
            $('#f_contabilidad').val(fEmision);
        }
    }

    function cargarDepositosPorSucursal(sucursalId, callback) {
        if (!sucursalId) {
            $('#deposito_id').html('<option value="">Seleccionar depósito</option>');
            if (callback) callback();
            return;
        }
        
        $.get('facturas_proveedores_ajax.php', {
            accion: 'obtener_depositos',
            sucursal_id: sucursalId,
            empresa_idx: empresa_idx
        }, function(data) {
            var options = '<option value="">Seleccionar depósito</option>';
            if (data && data.length > 0) {
                data.forEach(function(deposito) {
                    var selected = deposito.es_principal == 1 ? 'selected' : '';
                    options += `<option value="${deposito.deposito_id}" ${selected}>${deposito.deposito_nombre} (${deposito.codigo})</option>`;
                });
            } else {
                options = '<option value="">No hay depósitos disponibles</option>';
            }
            $('#deposito_id').html(options);
            if (callback) callback();
        }, 'json');
    }
    // Función para formatear número con separador de miles y decimales
   // Función para formatear número con separador de miles y decimales   
    function formatNumber(number, decimals = 2) {
        if (number === null || number === undefined || number === '') return '0.00';
        var num = parseFloat(number);
        if (isNaN(num)) return '0.00';
        return num.toLocaleString('es-AR', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    // Función para parsear un número formateado (eliminar separadores)
    function parseFormattedNumber(value) {
        if (!value) return 0;
        var cleanValue = value.toString().replace(/[^\d.,-]/g, '');
        cleanValue = cleanValue.replace(/\./g, '').replace(/,/g, '.');
        var num = parseFloat(cleanValue);
        return isNaN(num) ? 0 : num;
    }

    // Función para escapar HTML y prevenir XSS
    function escapeHtml(text) {
        if (!text) return '';
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
    function inicializarDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaFacturasProveedor')) {
            $('#tablaFacturasProveedor').DataTable().destroy();
            $('#tablaFacturasProveedor tbody').empty();
        }

        tabla = $('#tablaFacturasProveedor').DataTable({
            ajax: {
                url: 'facturas_proveedores_ajax.php',
                type: 'GET',
                data: {
                    accion: 'listar',
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                },
                dataSrc: ''
            },
            stateSave: true,
            stateSaveParams: function (settings, data) {
                data.page = currentPage;
                data.order = currentOrder;

                if (currentSearch !== '-1' && currentSearch !== '') {
                    data.search = { search: currentSearch };
                } else {
                    data.search = { search: '' };
                }

                delete data.columns;
                return data;
            },
            stateLoadParams: function (settings, data) {
                if (data.page !== undefined) currentPage = data.page;
                if (data.order !== undefined && data.order.length > 0) currentOrder = data.order;

                if (data.search && data.search.search !== undefined) {
                    var searchValue = data.search.search;
                    if (searchValue === '-1' || searchValue === '-1' || searchValue === '') {
                        currentSearch = '';
                    } else {
                        currentSearch = searchValue;
                    }
                } else {
                    currentSearch = '';
                }

                data.search = { search: currentSearch };
            },
            stateLoadCallback: function (settings) {
                var savedData = localStorage.getItem('DataTables_' + settings.sInstance);
                if (savedData) {
                    var data = JSON.parse(savedData);

                    if (data.search && (data.search.search === '-1' || data.search.search === '')) {
                        data.search.search = '';
                    }

                    if (data.columns) {
                        $.each(data.columns, function (i, col) {
                            if (col.search && col.search.search === '-1') {
                                col.search.search = '';
                            }
                        });
                    }

                    return data;
                }
                return null;
            },
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>' +
                '<"clear">',
            pageLength: 50,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Excel',
                    className: 'btn btn-success btn-sm',
                    title: 'Facturas de Proveedores',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9,10],
                        orthogonal: 'export'
                    }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> PDF',
                    className: 'btn btn-danger btn-sm',
                    title: 'Facturas de Proveedores',
                    orientation: 'portrait',
                    pageSize: 'A4',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9,10],
                        orthogonal: 'export'
                    }
                },
                {
                    extend: 'csvHtml5',
                    text: '<i class="fas fa-file-csv"></i> CSV',
                    className: 'btn btn-primary btn-sm',
                    title: 'Facturas_Proveedores',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7,8, 9,10]
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print"></i> Imprimir',
                    className: 'btn btn-secondary btn-sm',
                    title: 'Facturas de Proveedores',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9,10],
                        stripHtml: false
                    }
                }
            ],
            columns: [
            { data: 'factura_proveedor_id', className: 'text-center' },
            { data: 'comprobante_tipo', className: 'text-center' },
            { 
                data: null, 
                className: 'text-center',
                render: function(data, type, row) {
                    let numero = '';
                    if (row.comprobante_pv && row.comprobante_pv != 0) {
                        numero = row.comprobante_pv + '-';
                    }
                    numero += row.comprobante_nro || '';
                    if (type === 'export') return numero;
                    return `<span>${numero}</span>`;
                }
            },
            { 
                data: null, 
                render: function(data, type, row) {
                    if (type === 'export') return data.entidad_nombre || '';
                    return `<div>${data.entidad_nombre || ''}</div>
                            <small class="text-muted">${data.entidad_fantasia || ''}</small>`;
                }
            },
            { 
                data: 'sucursal_nombre', 
                className: 'text-center',
                defaultContent: '',
                render: function(data, type, row) {
                    if (type === 'export') return data || '';
                    return data || '<span class="text-muted">-</span>';
                }
            },
            { 
                data: 'deposito_nombre', 
                className: 'text-center',
                defaultContent: '',
                render: function(data, type, row) {
                    if (type === 'export') return data || '';
                    return data || '<span class="text-muted">-</span>';
                }
            },
            { 
                data: 'f_emision', 
                className: 'text-center',
                render: function(data, type, row) {
                    if (type === 'export') return data;
                    if (!data) return '';
                    let parts = data.split('-');
                    if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
                    return data;
                }
            },
            { 
                data: 'f_vencimiento', 
                className: 'text-center',
                render: function(data, type, row) {
                    if (type === 'export' || !data) return data || '';
                    let parts = data.split('-');
                    if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
                    return data;
                }
            },
            { 
                data: 'total', 
                className: 'text-end',
                render: function(data, type, row) {
                    if (type === 'export') return parseFloat(data).toFixed(2);
                    return `<span class="text-primary">${formatNumber(data, 2)}</span>`;
                }
            },
            { 
                data: 'estado_info',   // ← Importante: debe ser 'estado_info'
                className: 'text-center',
                render: function(data, type, row) {
                    if (!data || !data.estado_registro) {
                        if (type === 'export') return 'Sin estado';
                        return '<span class="text-dark">Sin estado</span>';
                    }
                    var estado = data.estado_registro;
                    var colorClass = data.bg_clase || 'bg-secondary';
                    var textClass = data.text_clase || 'text-white';
                    if (type === 'export') return estado;
                    return estado;
                }
            },
            { 
                data: 'botones', 
                orderable: false, 
                searchable: false, 
                className: "text-center", 
                width: '250px',
                render: function(data, type, row) {
                    var botones = '';
                    if (data && data.length > 0) {
                        var editarBoton = '';
                        var otrosBotones = '';
                        data.forEach(function(boton) {
                            var claseBoton = 'btn-sm me-1 ';
                            if (boton.bg_clase && boton.text_clase) {
                                claseBoton += boton.bg_clase + ' ' + boton.text_clase;
                            } else if (boton.color_clase) {
                                claseBoton += boton.color_clase;
                            } else {
                                claseBoton += 'btn-outline-primary';
                            }
                            var titulo = boton.descripcion || boton.nombre_funcion;
                            var accionJs = boton.accion_js;
                            var icono = boton.icono_clase ? `<i class="${boton.icono_clase}"></i>` : '';
                            var esConfirmable = boton.es_confirmable || 0;
                            var comprobanteInfo = `${row.comprobante_tipo || ''} ${row.comprobante_pv || ''}-${row.comprobante_nro || ''}`;
                            var proveedorInfo = row.entidad_nombre || row.entidad_fantasia || '';
                            var botonHtml = `<button type="button" class="btn ${claseBoton} btn-accion" 
                                                title="${titulo}" 
                                                data-id="${row.factura_proveedor_id}" 
                                                data-accion="${accionJs}"
                                                data-confirmable="${esConfirmable}"
                                                data-comprobante="${comprobanteInfo}"
                                                data-proveedor="${proveedorInfo}">
                                                ${icono}
                                            </button>`;
                            if (accionJs === 'editar') {
                                editarBoton = botonHtml;
                            } else {
                                otrosBotones += botonHtml;
                            }
                        });
                        botones = editarBoton + otrosBotones;
                    } else {
                        botones = '<span class="text-muted small">Sin acciones</span>';
                    }
                    return `<div class="btn-group" role="group">${botones}</div>`;
                }
            }
        ],
            language: {
                url: '//cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json'                
            },
            order: currentOrder,
            responsive: true,
            createdRow: function (row, data, dataIndex) {
                if (data.estado_info && data.estado_info.codigo_estandar === 'CERRADO') {
                    $(row).addClass('table-success');
                } else if (data.estado_info && data.estado_info.codigo_estandar === 'CANCELADO') {
                    $(row).addClass('table-danger');
                } else if (data.estado_info && data.estado_info.codigo_estandar === 'PENDIENTE') {
                    $(row).addClass('table-warning');
                }
            },
            initComplete: function () {
                var buttons = new $.fn.dataTable.Buttons(tabla, {
                    buttons: ['excelHtml5', 'pdfHtml5', 'csvHtml5', 'print']
                }).container().appendTo($('#tablaFacturasProveedor_wrapper .col-md-6:eq(1)'));

                $(tabla.table().container()).on('page.dt', function (e) {
                    currentPage = tabla.page();
                });

                $(tabla.table().container()).on('order.dt', function (e, settings, details) {
                    currentOrder = tabla.order();
                });

                $(tabla.table().container()).on('search.dt', function (e, settings) {
                    currentSearch = tabla.search();
                });

                setTimeout(function () {
                    var searchInput = $('.dataTables_filter input');
                    if (searchInput.val() === '-1' || searchInput.val() === '') {
                        searchInput.val('');
                        currentSearch = '';

                        var savedData = localStorage.getItem('DataTables_' + tabla.settings()[0].sInstance);
                        if (savedData) {
                            var data = JSON.parse(savedData);
                            if (data.search && (data.search.search === '-1' || data.search.search === '')) {
                                data.search.search = '';
                                localStorage.setItem('DataTables_' + tabla.settings()[0].sInstance, JSON.stringify(data));
                            }
                        }
                    }
                }, 100);
            }
        });

        inicializarEventos();
    }
    
    // ========== FUNCIÓN PARA CARGAR CONDICIONES DE PAGO ==========
    function cargarCondicionesPago() {
        $.get('facturas_proveedores_ajax.php', { 
            accion: 'obtener_condiciones_pago',
            empresa_idx: empresa_idx 
        }, function(data) {
            if (data && data.length > 0) {
                var options = '<option value="">Seleccionar</option>';
                data.forEach(function(item) {
                    // Guardar los días como data attribute
                    var dias = item.dias_primer_vencimiento || 0;
                    options += `<option value="${item.condicion_pago_id}" data-dias="${dias}">
                                    ${item.codigo} - ${item.condicion_pago}
                                </option>`;
                });
                $('#condicion_pago_id').html(options);
                
                // Si hay un valor seleccionado, actualizar vencimiento
                var condicionPagoId = $('#condicion_pago_id').val();
                if (condicionPagoId) {
                    actualizarVencimientoPorCondicionPago();
                }
            } else {
                $('#condicion_pago_id').html('<option value="">No hay condiciones</option>');
            }
        }, 'json');
    }
    // ========== FUNCIÓN PARA ACTUALIZAR VENCIMIENTO SEGÚN CONDICIÓN DE PAGO ==========
    function actualizarVencimientoPorCondicionPago() {
        var fEmision = $('#f_emision').val();
        var condicionPagoId = $('#condicion_pago_id').val();
        
        if (!fEmision || !condicionPagoId) {
            return;
        }
        
        // Obtener los días de la opción seleccionada
        var selectedOption = $('#condicion_pago_id option:selected');
        var dias = parseInt(selectedOption.data('dias')) || 0;
        
        console.log("Actualizando vencimiento - Días:", dias, "Fecha emisión:", fEmision);
        
        if (dias > 0) {
            var fecha = new Date(fEmision);
            fecha.setDate(fecha.getDate() + dias);
            
            // Formatear como YYYY-MM-DD
            var year = fecha.getFullYear();
            var month = String(fecha.getMonth() + 1).padStart(2, '0');
            var day = String(fecha.getDate()).padStart(2, '0');
            var fechaVencimiento = `${year}-${month}-${day}`;
            
            $('#f_vencimiento').val(fechaVencimiento);
            console.log("Fecha vencimiento calculada:", fechaVencimiento);
        } else {
            // Si no hay días configurados, dejar vacío
            $('#f_vencimiento').val('');
            console.log("No hay días configurados para esta condición de pago");
        }
    }
    function inicializarEventos() {
        $('#btnRecargar').off('click').on('click', function () {
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            var savedState = {
                page: tabla.page(),
                order: tabla.order(),
                search: tabla.search()
            };

            tabla.ajax.reload(function (json) {
                if (savedState.page !== undefined) {
                    tabla.page(savedState.page).draw('page');
                }
                if (savedState.search && savedState.search !== '') {
                    tabla.search(savedState.search).draw();
                }
                btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
            }, false);
        });
    }

    function cargarBotonAgregar() {
        $.get('facturas_proveedores_ajax.php', {
            accion: 'obtener_boton_agregar',
            pagina_idx: pagina_idx
        }, function (botonAgregar) {
            if (botonAgregar && botonAgregar.nombre_funcion) {
                var icono = botonAgregar.icono_clase ? `<i class="${botonAgregar.icono_clase} me-1"></i>` : '';

                var colorClase = 'btn-primary';
                if (botonAgregar.bg_clase && botonAgregar.text_clase) {
                    colorClase = botonAgregar.bg_clase + ' ' + botonAgregar.text_clase;
                } else if (botonAgregar.color_clase) {
                    colorClase = botonAgregar.color_clase;
                }

                $('#contenedor-boton-agregar').html(
                    `<button type="button" class="btn ${colorClase}" id="btnNuevo">
                ${icono}${botonAgregar.nombre_funcion}
             </button>`
                );
            } else {
                $('#contenedor-boton-agregar').html(
                    '<button type="button" class="btn btn-primary" id="btnNuevo">' +
                    '<i class="fas fa-plus me-1"></i>Nueva Factura</button>'
                );
            }
        }, 'json');
    }

    // ========== FUNCIÓN PARA CARGAR PROVEEDORES Y SUCURSALES EN COMBO UNIFICADO ==========
    function cargarProveedoresYSucursales() {
        $.ajax({
            url: 'facturas_proveedores_ajax.php',
            type: 'GET',
            data: {
                accion: 'obtener_proveedores_con_sucursales',
                empresa_idx: empresa_idx
            },
            dataType: 'json',
            success: function(data) {
                var options = '<option value="">Seleccionar proveedor o sucursal</option>';
                
                if (data && data.length > 0) {
                    data.forEach(function(item) {
                        // Si el proveedor tiene sucursales, mostramos cada sucursal con el formato "Proveedor - Sucursal"
                        if (item.sucursales && item.sucursales.length > 0) {
                            item.sucursales.forEach(function(sucursal) {
                                options += `<option value="S-${sucursal.sucursal_id}" data-entidad-id="${item.entidad_id}" data-sucursal-id="${sucursal.sucursal_id}">${item.entidad_nombre} - ${sucursal.sucursal_nombre}</option>`;
                            });
                        } else {
                            // Si no tiene sucursales, mostramos solo el proveedor
                            options += `<option value="P-${item.entidad_id}" data-entidad-id="${item.entidad_id}" data-sucursal-id="">${item.entidad_nombre}</option>`;
                        }
                    });
                } else {
                    options = '<option value="">No hay proveedores disponibles</option>';
                }
                
                $('#entidad_combo').html(options);
                console.log("Proveedores y sucursales cargados:", data);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("Error cargando proveedores y sucursales:", textStatus, errorThrown);
                $('#entidad_combo').html('<option value="">Error al cargar</option>');
            }
        });
    }

    $('#f_emision').on('change', function() {
        var fechaEmision = $(this).val();
        console.log("Fecha emisión cambiada a:", fechaEmision);
        
        // Actualizar fecha de contabilidad
        if (fechaEmision) {
            $('#f_contabilidad').val(fechaEmision);
        }
        
        // Recalcular vencimiento si hay condición de pago seleccionada
        var condicionPagoId = $('#condicion_pago_id').val();
        if (condicionPagoId) {
            actualizarVencimientoPorCondicionPago();
        }
    });

    $('#sucursal_id').on('change', function() {
        var sucursalId = $(this).val();
        cargarDepositosPorSucursal(sucursalId);
    });
    // Cuando cambia la condición de pago, recalcular vencimiento
    $('#condicion_pago_id').on('change', function() {
        console.log("Condición de pago cambiada a:", $(this).val());
        actualizarVencimientoPorCondicionPago();
    });
    // ========== EVENTO CHANGE DEL COMBO UNIFICADO ==========
    $('#entidad_combo').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var entidadId = selectedOption.data('entidad-id');
        var sucursalId = selectedOption.data('sucursal-id');
        var valorCombo = $(this).val();
        
        if (valorCombo) {
            // Extraer tipo y ID del valor del combo
            var partes = valorCombo.split('-');
            var tipo = partes[0];
            var id = partes[1];
            
            if (tipo === 'P') {
                // Es un proveedor principal (sin sucursales)
                $('#entidad_id').val(entidadId);
                $('#entidad_sucursal_id').val('');
                proveedorActualId = parseInt(entidadId);
                proveedorSucursalActualId = null; // Explícitamente null
                
                // Mostrar nombre del proveedor en el span
                var proveedorNombre = selectedOption.text();
                $('#proveedor_actual_nombre').text(proveedorNombre);
                
            } else if (tipo === 'S') {
                // Es una sucursal de proveedor
                $('#entidad_id').val(entidadId);
                $('#entidad_sucursal_id').val(sucursalId);
                proveedorActualId = parseInt(entidadId);
                proveedorSucursalActualId = parseInt(sucursalId);
                
                // Mostrar texto completo (Proveedor - Sucursal)
                var textoCompleto = selectedOption.text();
                $('#proveedor_actual_nombre').text(textoCompleto);
            }
             if (entidadId) {
                console.log("Llamando a cargarCondicionesProveedor con ID:", entidadId);
                cargarCondicionesProveedor(entidadId);
            }
            console.log("Proveedor seleccionado:", proveedorActualId, "Sucursal:", proveedorSucursalActualId);
            
        } else {
            // Limpiar selección
            $('#entidad_id').val('');
            $('#entidad_sucursal_id').val('');
            proveedorActualId = null;
            proveedorSucursalActualId = null;
            $('#proveedor_actual_nombre').text('No seleccionado');
        }
    });

    // ========== FUNCIÓN PARA IMPRIMIR COMPROBANTE ==========
    function imprimirComprobante(facturaId) {
        // Abrir una nueva ventana con el comprobante
        var url = 'facturas_proveedores_print.php?factura_proveedor_id=' + facturaId + '&empresa_idx=' + empresa_idx;
        window.open(url, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
    }

    // ========== VISUALIZAR FACTURA (SOLO LECTURA) ==========
    function cargarFacturaParaVisualizar(facturaId) {
        $.get('facturas_proveedores_ajax.php', {
            accion: 'obtener',
            factura_proveedor_id: facturaId,
            empresa_idx: empresa_idx
        }, function (res) {
            console.log("Factura recibida para visualizar:", res);
            
            if (res && res.factura_proveedor_id) {
                resetModal();
                
                // Guardar el ID de sucursal para asignarlo después de cargar los combos
                var sucursalIdParaEditar = res.sucursal_id || null;
                
                cargarCombosFormulario();
                cargarProveedoresYSucursales(); // Cargar combo unificado
                
                $('#factura_proveedor_id').val(res.factura_proveedor_id);
                $('#comprobante_nro').val(res.comprobante_nro);
                $('#comprobante_pv').val(res.comprobante_pv || '0');
                $('#f_emision').val(res.f_emision);
                $('#f_vencimiento').val(res.f_vencimiento);
                $('#f_contabilidad').val(res.f_contabilidad || res.f_emision);
                $('#direccion').val(res.direccion);
                $('#observaciones').val(res.observaciones);
                $('#tipo_cambio').val(res.tipo_cambio || '1.000000');
                $('#subtotal').val(res.subtotal || 0);
                $('#descuentos').val(res.descuentos || 0);
                $('#no_gravado').val(res.no_gravado || 0);
                $('#exento').val(res.exento || 0);
                $('#impuestos').val(res.impuestos || 0);
                $('#total').val(res.total || 0);
                
                $('#total_neto_display').text(parseFloat(res.subtotal || 0).toFixed(2));
                $('#descuentos_display').text(parseFloat(res.descuentos || 0).toFixed(2));
                $('#no_gravado_display').text(parseFloat(res.no_gravado || 0).toFixed(2));
                $('#exento_display').text(parseFloat(res.exento || 0).toFixed(2));
                $('#impuestos_display').text(parseFloat(res.impuestos || 0).toFixed(2));
                $('#total_display').text(parseFloat(res.total || 0).toFixed(2));

                $('#modalLabel').text('Visualizar Factura de Proveedor');

                // Asignar valores después de que los combos se hayan cargado
                setTimeout(function() {
                    $('#comprobante_tipo_id').val(res.comprobante_tipo_id);
                    $('#moneda_id').val(res.moneda_id);
                    $('#condicion_pago_id').val(res.condicion_pago_id);
                    
                    // ASIGNAR SUCURSAL_ID DESPUÉS DE CARGAR EL COMBO
                    if (sucursalIdParaEditar) {
                        $('#sucursal_id').val(sucursalIdParaEditar);
                        // Cargar depósitos y luego asignar el depósito guardado
                        cargarDepositosPorSucursal(sucursalIdParaEditar, function() {
                            if (res.deposito_id) {
                                $('#deposito_id').val(res.deposito_id);
                            }
                        });
                    } else {
                        // Si no hay sucursal, limpiar depósitos
                        cargarDepositosPorSucursal(null);
                    }
                    
                    // Seleccionar el valor correcto en el combo unificado
                    if (res.entidad_id) {
                        proveedorActualId = res.entidad_id;
                        
                        if (res.entidad_sucursal_id && res.entidad_sucursal_id > 0) {
                            // Tiene sucursal específica
                            $('#entidad_combo').val('S-' + res.entidad_sucursal_id);
                            proveedorSucursalActualId = parseInt(res.entidad_sucursal_id);
                        } else {
                            // Solo proveedor principal
                            $('#entidad_combo').val('P-' + res.entidad_id);
                            proveedorSucursalActualId = null;
                        }
                        
                        // Obtener el texto de la opción seleccionada para mostrarlo
                        var textoSeleccionado = $('#entidad_combo option:selected').text();
                        $('#proveedor_actual_nombre').text(textoSeleccionado || 'No seleccionado');
                        
                        console.log("Proveedor cargado para edición:", proveedorActualId, "Sucursal:", proveedorSucursalActualId);
                    }
                    if (res.deposito_id) {
                        $('#deposito_id').val(res.deposito_id);
                    }
                    // Cargar detalles si existen
                    if (res.detalles && res.detalles.length > 0) {
                        detalles = res.detalles.map(function(detalle, index) {
                            return {
                                detalle_idx: index,
                                factura_proveedor_detalle_id: detalle.factura_proveedor_detalle_id,
                                producto_id: detalle.producto_id,
                                producto_nombre: detalle.producto_nombre,
                                cantidad: detalle.cantidad,
                                precio_unitario: detalle.precio_unitario,
                                descuento_item_pct: detalle.descuento_item_pct || 0,
                                descuento: detalle.descuento || 0,
                                no_gravado: detalle.no_gravado || 0,
                                exento: detalle.exento || 0,
                                iva_alicuota_id: detalle.iva_alicuota_id,
                                iva_porcentaje: detalle.iva_porcentaje,
                                neto_gravado: detalle.neto_gravado,
                                iva_importe: detalle.iva_importe,
                                total_linea: detalle.total_linea
                            };
                        });
                        renderizarDetalles();
                        actualizarTotales();
                    }

                    // --- MODO SOLO LECTURA ---
                    // Deshabilitar todos los inputs, selects y botones de acción dentro del modal
                    $('#formFacturaProveedor :input').prop('disabled', true);
                    $('.btn-eliminar-detalle, .btn-editar-detalle, #btnAgregarProducto, #btnNuevoProductoRapido, #btnNuevoProveedor').prop('disabled', true);
                    
                    // Ocultar secciones completas en modo visualizar
                    $('.card-primary').hide(); // Oculta la sección de Agregar Producto
                    $('#btnNuevoProductoRapido').hide(); // Oculta el botón de Nuevo Producto Rápido
                    
                    // Ocultar el botón guardar y el botón cancelar del footer
                    $('#btnGuardar').hide();
                    $('.modal-footer .btn-secondary').hide(); // Oculta el botón Cancelar del footer
                    
                    // Mantener habilitado el fullscreen
                    $('#btnToggleFullscreen').prop('disabled', false);
                    
                }, 500);

                var modal = new bootstrap.Modal(document.getElementById('modalFacturaProveedor'));
                modal.show();

                // Al cerrar el modal, restaurar estado (para próximas aperturas)
                $('#modalFacturaProveedor').off('hidden.bs.modal').on('hidden.bs.modal', function () {
                    // Restaurar habilitación y mostrar botones
                    $('#formFacturaProveedor :input').prop('disabled', false);
                    $('#btnGuardar').show();
                    $('.modal-footer .btn-secondary').show(); // Restaurar botón Cancelar
                    $('.btn-eliminar-detalle, .btn-editar-detalle, #btnAgregarProducto, #btnNuevoProductoRapido, #btnNuevoProveedor').prop('disabled', false);
                    
                    // Restaurar secciones visibles
                    $('.card-primary').show();
                    $('#btnNuevoProductoRapido').show();
                });

            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos de la factura",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    }

    // ========== MANEJADOR DE ACCIONES DE BOTONES ==========
    $(document).on('change', '#descuento_general_pct', function() {
        var descuentoGeneral = parseFloat($(this).val()) || 0;
        
        // Actualizar todos los detalles con el nuevo descuento general
        detalles.forEach(function(detalle, index) {
            var netoBase = detalle.cantidad * detalle.precio_unitario;
            var descuentoItem = netoBase * (detalle.descuento_item_pct / 100);
            var netoDespuesItem = netoBase - descuentoItem;
            var descuentoGeneralMonto = netoDespuesItem * (descuentoGeneral / 100);
            var descuentoTotal = descuentoItem + descuentoGeneralMonto;
            
            detalle.descuento_general_pct = descuentoGeneral;
            detalle.descuento_general = descuentoGeneralMonto;
            detalle.descuento = descuentoTotal;
            detalle.neto_gravado = netoBase - descuentoTotal;
            detalle.iva_importe = detalle.neto_gravado * (detalle.iva_porcentaje / 100);
            detalle.total_linea = detalle.neto_gravado + detalle.iva_importe + detalle.no_gravado + detalle.exento;
            
            detalles[index] = detalle;
        });
        
        renderizarDetalles();
        actualizarTotales();
    });
    $(document).on('click', '.btn-accion', function () {
        var facturaId = $(this).data('id');
        var accionJs = $(this).data('accion');
        var confirmable = $(this).data('confirmable');
        var comprobanteInfo = $(this).data('comprobante') || 'Factura #' + facturaId;
        var proveedorInfo = $(this).data('proveedor') || '';

        if (accionJs === 'editar') {
            cargarFacturaParaEditar(facturaId);
        } else if (accionJs === 'visualizar') {
            cargarFacturaParaVisualizar(facturaId);
        } else if (accionJs === 'imprimir') {
            imprimirComprobante(facturaId);
        } else if (confirmable == 1) {
            Swal.fire({
                title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                html: `¿Está seguro de <strong>${accionJs}</strong> la factura<br>
                    <strong>${comprobanteInfo}</strong>?<br>
                    <small class="text-muted">Proveedor: ${proveedorInfo}</small>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: `Sí, ${accionJs}`,
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    ejecutarAccion(facturaId, accionJs, comprobanteInfo);
                }
            });
        } else {
            ejecutarAccion(facturaId, accionJs, comprobanteInfo);
        }
    });

    // Función para ejecutar cualquier acción del backend
    function ejecutarAccion(facturaId, accionJs, comprobanteInfo) {
        var savedState = {
            page: tabla.page(),
            order: tabla.order(),
            search: tabla.search()
        };

        $.post('facturas_proveedores_ajax.php', {
            accion: 'ejecutar_accion',
            factura_proveedor_id: facturaId,
            accion_js: accionJs,
            empresa_idx: empresa_idx,
            pagina_idx: pagina_idx
        }, function (res) {
            if (res.success) {
                tabla.ajax.reload(function (json) {
                    if (savedState.page !== undefined) {
                        tabla.page(savedState.page).draw('page');
                    }
                    if (savedState.search && savedState.search !== '') {
                        tabla.search(savedState.search).draw();
                    }

                    Swal.fire({
                        icon: "success",
                        title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                        text: res.message || `Factura "${comprobanteInfo}" actualizada correctamente`,
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
                }, false);
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: res.error || `Error al ${accionJs} la factura`,
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json').fail(function(xhr) {
            Swal.fire({
                icon: "error",
                title: "Error de conexión",
                text: "No se pudo conectar con el servidor",
                confirmButtonText: "Entendido"
            });
            console.error('Error en ejecutarAccion:', xhr.responseText);
        });
    }

    // ========== FUNCIONES DE BÚSQUEDA DE PRODUCTOS ==========
    $('#busqueda_producto').on('input', function() {
        var q = $(this).val().trim();
        var resultadosDiv = $('#resultados_busqueda');
        
        if (!proveedorActualId) {
            resultadosDiv.hide();
            return;
        }
        
        if (q.length < 2) {
            resultadosDiv.hide();
            return;
        }
        
        clearTimeout(timeoutBusqueda);
        timeoutBusqueda = setTimeout(function() {
            $.ajax({
                url: 'facturas_proveedores_ajax.php',
                type: 'GET',
                data: {
                    accion: 'buscar_productos_proveedor',
                    entidad_id: proveedorActualId,
                    q: q,
                    empresa_idx: empresa_idx
                },
                dataType: 'json',
                success: function(data) {
                    resultadosDiv.empty().hide();
                    selectedIndex = -1;
                    
                    if (data && data.length > 0) {
                        data.forEach(function(item, index) {
                            resultadosDiv.append(
                                `<a href="#" class="list-group-item list-group-item-action" 
                                   data-index="${index}"
                                   data-id="${item.producto_id}"
                                   data-iva-id="${item.iva_alicuota_id}"
                                   data-iva="${item.iva_porcentaje || 21}">
                                    <strong>${item.producto_codigo}</strong> - ${item.producto_nombre}
                                    ${item.codigo_proveedor ? '<br><small>Código Proveedor: ' + item.codigo_proveedor + '</small>' : ''}
                                </a>`
                            );
                        });
                        resultadosDiv.show();
                    }
                }
            });
        }, 300);
    });

    // Navegación con teclas (flechas arriba/abajo y Enter)
    $('#busqueda_producto').on('keydown', function(e) {
        var resultados = $('#resultados_busqueda .list-group-item');
        
        if (resultados.length === 0) return;
        
        // Flecha abajo
        if (e.keyCode === 40) {
            e.preventDefault();
            if (selectedIndex < resultados.length - 1) {
                selectedIndex++;
            } else {
                selectedIndex = 0;
            }
            actualizarSeleccion(resultados);
        }
        // Flecha arriba
        else if (e.keyCode === 38) {
            e.preventDefault();
            if (selectedIndex > 0) {
                selectedIndex--;
            } else {
                selectedIndex = resultados.length - 1;
            }
            actualizarSeleccion(resultados);
        }
        // Enter
        else if (e.keyCode === 13 && selectedIndex >= 0) {
            e.preventDefault();
            $(resultados[selectedIndex]).click();
        }
    });

    function actualizarSeleccion(resultados) {
        resultados.removeClass('active');
        $(resultados[selectedIndex]).addClass('active');
        
        // Scroll al elemento seleccionado si es necesario
        var container = $('#resultados_busqueda');
        var selectedElement = $(resultados[selectedIndex]);
        var containerScrollTop = container.scrollTop();
        var containerHeight = container.height();
        var elementTop = selectedElement.position().top;
        var elementHeight = selectedElement.outerHeight();
        
        if (elementTop < 0) {
            container.scrollTop(containerScrollTop + elementTop);
        } else if (elementTop + elementHeight > containerHeight) {
            container.scrollTop(containerScrollTop + (elementTop + elementHeight - containerHeight));
        }
    }

    // Seleccionar producto de la búsqueda
    $(document).on('click', '#resultados_busqueda .list-group-item', function(e) {
        e.preventDefault();
        
        var item = $(this);
        var productoId = item.data('id');
        var iva = item.data('iva');
        var ivaId = item.data('iva-id');
        var textoCompleto = item.text().trim();
        
        $('#busqueda_producto').val(textoCompleto);
        $('#producto_seleccionado_id').val(productoId);
        $('#producto_iva').val(iva);
        $('#producto_iva_id').val(ivaId);
        
        // Cargar último precio
        $.get('facturas_proveedores_ajax.php', {
            accion: 'obtener_ultimo_precio',
            producto_id: productoId,
            entidad_id: proveedorActualId,
            empresa_idx: empresa_idx
        }, function(res) {
            if (res.success && res.precio) {
                $('#producto_precio').val(res.precio);
            }
            calcularDescuentos();
        }, 'json');
        
        $('#resultados_busqueda').hide();
        selectedIndex = -1;
    });

    // Ocultar resultados al hacer clic fuera
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#busqueda_producto, #resultados_busqueda').length) {
            $('#resultados_busqueda').hide();
            selectedIndex = -1;
        }
    });

    // Calcular descuentos e IVA
    function calcularDescuentos() {
        var cantidad = parseFloat($('#producto_cantidad').val()) || 0;
        var precio = parseFloat($('#producto_precio').val()) || 0;
        var descuentoPorcentaje = parseFloat($('#producto_descuento_item_pct').val()) || 0;
        var iva = parseFloat($('#producto_iva').val()) || 0;
        
        var netoBase = cantidad * precio;
        var descuentoCalculado = netoBase * (descuentoPorcentaje / 100);
        var netoGravado = netoBase - descuentoCalculado;
        var ivaImporte = netoGravado * (iva / 100);
        
        $('#producto_descuento').val(descuentoCalculado.toFixed(2));
        $('#producto_iva_importe').val(ivaImporte.toFixed(2));
    }

    $('#producto_cantidad, #producto_precio, #producto_iva, #producto_descuento_item_pct, #producto_descuento').on('input', function() {
        calcularDescuentos();
    });

    // ========== FUNCIONES DE AGREGADO DE PRODUCTOS ==========
    function obtenerIdIva(porcentaje) {
        switch(parseFloat(porcentaje)) {
            case 21: return 1;
            case 10.5: return 2;
            case 27: return 3;
            case 0: return 4;
            default: return 1;
        }
    }

    $('#btnAgregarProducto').click(function() {
    if (!proveedorActualId) {
        Swal.fire({
            icon: "warning",
            title: "Seleccione proveedor",
            text: "Debe seleccionar un proveedor primero",
            confirmButtonText: "Entendido"
        });
        return;
    }
    
    var productoId = $('#producto_seleccionado_id').val();
    if (!productoId) {
        Swal.fire({
            icon: "warning",
            title: "Producto requerido",
            text: "Debe seleccionar un producto de la lista",
            confirmButtonText: "Entendido"
        });
        return;
    }
    
    var cantidad = parseFloat($('#producto_cantidad').val());
    var precio = parseFloat($('#producto_precio').val());
    var descuentoItemPct = parseFloat($('#producto_descuento_item_pct').val()) || 0;
    var descuentoGeneralPct = parseFloat($('#descuento_general_pct').val()) || 0;
    var iva = parseFloat($('#producto_iva').val());
    var ivaId = $('#producto_iva_id').val() || obtenerIdIva(iva);
    var ivaImporte = parseFloat($('#producto_iva_importe').val()) || 0;
    var noGravado = parseFloat($('#producto_no_gravado').val()) || 0;
    var exento = parseFloat($('#producto_exento').val()) || 0;
    
    if (cantidad <= 0) {
        Swal.fire({
            icon: "warning",
            title: "Cantidad inválida",
            text: "La cantidad debe ser mayor a 0",
            confirmButtonText: "Entendido"
        });
        return;
    }
    
    if (precio <= 0) {
        Swal.fire({
            icon: "warning",
            title: "Precio inválido",
            text: "El precio debe ser mayor a 0",
            confirmButtonText: "Entendido"
        });
        return;
    }
    
    var productoText = $('#busqueda_producto').val();
    
    // Calcular valores
    var netoBase = cantidad * precio;
    var descuentoItem = netoBase * (descuentoItemPct / 100);
    var netoDespuesItem = netoBase - descuentoItem;
    var descuentoGeneral = netoDespuesItem * (descuentoGeneralPct / 100);
    var descuentoTotal = descuentoItem + descuentoGeneral;
    var netoGravado = netoBase - descuentoTotal;
    var totalLinea = netoGravado + ivaImporte + noGravado + exento;
    
    var nuevoDetalle = {
        detalle_idx: 'temp_' + new Date().getTime(),
        factura_proveedor_detalle_id: 0,
        producto_id: parseInt(productoId),
        producto_nombre: productoText,
        cantidad: cantidad,
        precio_unitario: precio,
        descuento_item_pct: descuentoItemPct,
        descuento_general_pct: descuentoGeneralPct,
        descuento_item: descuentoItem,
        descuento_general: descuentoGeneral,
        descuento: descuentoTotal,
        no_gravado: noGravado,
        exento: exento,
        iva_alicuota_id: parseInt(ivaId),
        iva_porcentaje: iva,
        neto_gravado: netoGravado,
        iva_importe: ivaImporte,
        total_linea: totalLinea
    };
    
    detalles.push(nuevoDetalle);
    renderizarDetalles();
    actualizarTotales();
    
    // Limpiar campos
    $('#busqueda_producto').val('');
    $('#producto_seleccionado_id').val('');
    $('#producto_iva_id').val('');
    $('#producto_cantidad').val('1.00');
    $('#producto_precio').val('');
    $('#producto_descuento_item_pct').val('0');
    $('#producto_descuento').val('0.00');
    $('#producto_iva').val('21');
    $('#producto_iva_importe').val('0.00');
    $('#producto_no_gravado').val('0.00');
    $('#producto_exento').val('0.00');
    
    $('#busqueda_producto').focus();
});

    // ========== FUNCIONES DE RENDERIZADO DE DETALLES ==========
    function renderizarDetalles() {
        $('#contenedor-detalles').empty();
        
        if (detalles.length === 0) {
            var htmlVacio = `
            <div class="detalles-vacio text-center p-4 border rounded bg-light">
                <i class="fas fa-box-open fa-2x text-muted mb-2"></i>
                <p class="mb-0">No hay productos agregados</p>
                <small class="text-muted">Seleccione un producto para comenzar</small>
            </div>`;
            $('#contenedor-detalles').html(htmlVacio);
            return;
        }
        
        var html = `
        <div style="overflow-x: auto;">
            <table class="table table-sm table-bordered table-hover mb-0" style="min-width: 1200px;">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 200px;">Producto</th>
                        <th class="text-center" style="min-width: 70px;">Cant.</th>
                        <th class="text-end" style="min-width: 100px;">Precio</th>
                        <th class="text-center" style="min-width: 80px;">Dto. Item %</th>
                        <th class="text-end" style="min-width: 100px;">Dto. Item</th>
                        <th class="text-center" style="min-width: 80px;">Dto. Gral %</th>
                        <th class="text-end" style="min-width: 100px;">Dto. Gral</th>
                        <th class="text-end" style="min-width: 100px;">Neto</th>
                        <th class="text-center" style="min-width: 70px;">IVA %</th>
                        <th class="text-end" style="min-width: 100px;">IVA</th>
                        <th class="text-end" style="min-width: 100px;">No Grav.</th>
                        <th class="text-end" style="min-width: 100px;">Exento</th>
                        <th class="text-end" style="min-width: 100px;">Total</th>
                        <th class="text-center" style="min-width: 90px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>`;
        
        detalles.forEach(function(detalle) {
            var esNuevo = detalle.factura_proveedor_detalle_id === 0;
            var claseFila = esNuevo ? 'table-info' : '';
            
            var nombreProducto = detalle.producto_nombre || '';
            
            // Asegurar que todos los valores sean números
            var cantidad = typeof detalle.cantidad === 'number' ? detalle.cantidad : parseFloat(detalle.cantidad) || 0;
            var precioUnitario = typeof detalle.precio_unitario === 'number' ? detalle.precio_unitario : parseFloat(detalle.precio_unitario) || 0;
            var descuentoItemPct = typeof detalle.descuento_item_pct === 'number' ? detalle.descuento_item_pct : parseFloat(detalle.descuento_item_pct) || 0;
            var descuentoItem = typeof detalle.descuento_item === 'number' ? detalle.descuento_item : parseFloat(detalle.descuento_item) || 0;
            var descuentoGeneralPct = typeof detalle.descuento_general_pct === 'number' ? detalle.descuento_general_pct : parseFloat(detalle.descuento_general_pct) || 0;
            var descuentoGeneral = typeof detalle.descuento_general === 'number' ? detalle.descuento_general : parseFloat(detalle.descuento_general) || 0;
            var netoGravado = typeof detalle.neto_gravado === 'number' ? detalle.neto_gravado : parseFloat(detalle.neto_gravado) || 0;
            var ivaPorcentaje = typeof detalle.iva_porcentaje === 'number' ? detalle.iva_porcentaje : parseFloat(detalle.iva_porcentaje) || 0;
            var ivaImporte = typeof detalle.iva_importe === 'number' ? detalle.iva_importe : parseFloat(detalle.iva_importe) || 0;
            var noGravado = typeof detalle.no_gravado === 'number' ? detalle.no_gravado : parseFloat(detalle.no_gravado) || 0;
            var exento = typeof detalle.exento === 'number' ? detalle.exento : parseFloat(detalle.exento) || 0;
            var totalLinea = typeof detalle.total_linea === 'number' ? detalle.total_linea : parseFloat(detalle.total_linea) || 0;
            
            html += `
            <tr class="${claseFila}" data-idx="${detalle.detalle_idx}">
                <td>
                    <div class="fw-bold">${escapeHtml(nombreProducto.substring(0, 50))}${nombreProducto.length > 50 ? '...' : ''}</div>
                    ${esNuevo ? '<span class="badge bg-info ms-2">Nuevo</span>' : ''}
                </td>
                <td class="text-center">${formatNumber(cantidad, 2)}</td>
                <td class="text-end">${formatNumber(precioUnitario, 2)}</td>
                <td class="text-center">${descuentoItemPct.toFixed(2)}%</td>
                <td class="text-end">${formatNumber(descuentoItem, 2)}</td>
                <td class="text-center">${descuentoGeneralPct.toFixed(2)}%</td>
                <td class="text-end">${formatNumber(descuentoGeneral, 2)}</td>
                <td class="text-end">${formatNumber(netoGravado, 2)}</td>
                <td class="text-center">${ivaPorcentaje.toFixed(2)}%</td>
                <td class="text-end">${formatNumber(ivaImporte, 2)}</td>
                <td class="text-end">${formatNumber(noGravado, 2)}</td>
                <td class="text-end">${formatNumber(exento, 2)}</td>
                <td class="text-end fw-bold text-success">${formatNumber(totalLinea, 2)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-warning btn-editar-detalle" 
                            data-idx="${detalle.detalle_idx}" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger btn-eliminar-detalle" 
                            data-idx="${detalle.detalle_idx}" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
        });
        
        html += `
                </tbody>
            </table>
        </div>`;
        
        $('#contenedor-detalles').html(html);
    }

    // ========== FUNCIÓN ACTUALIZAR TOTALES ==========
   function actualizarTotales() {
    var totalNeto = 0;
    var totalDescuentoItem = 0;
    var totalDescuentoGeneral = 0;
    var totalNoGravado = 0;
    var totalExento = 0;
    var totalImpuestos = 0;
    
    detalles.forEach(function(detalle) {
        totalNeto += detalle.neto_gravado || 0;
        totalDescuentoItem += detalle.descuento_item || 0;
        totalDescuentoGeneral += detalle.descuento_general || 0;
        totalImpuestos += detalle.iva_importe || 0;
        totalNoGravado += detalle.no_gravado || 0;
        totalExento += detalle.exento || 0;
    });
    
    var totalDescuentos = totalDescuentoItem + totalDescuentoGeneral;
    var totalGeneral = totalNeto + totalImpuestos + totalNoGravado + totalExento;
    
    // Guardar valores originales en los inputs hidden
    $('#subtotal').val(totalNeto.toFixed(2));
    $('#descuentos').val(totalDescuentos.toFixed(2));
    $('#no_gravado').val(totalNoGravado.toFixed(2));
    $('#exento').val(totalExento.toFixed(2));
    $('#impuestos').val(totalImpuestos.toFixed(2));
    $('#total').val(totalGeneral.toFixed(2));
    
    // Mostrar valores formateados
    $('#total_neto_display').text(formatNumber(totalNeto, 2));
    $('#descuentos_display').text(formatNumber(totalDescuentos, 2));
    $('#no_gravado_display').text(formatNumber(totalNoGravado, 2));
    $('#exento_display').text(formatNumber(totalExento, 2));
    $('#impuestos_display').text(formatNumber(totalImpuestos, 2));
    $('#total_display').text(formatNumber(totalGeneral, 2));
}
    // ========== FUNCIONES DE PANTALLA COMPLETA ==========
    $('#btnToggleFullscreen').click(function() {
        var modalDialog = $('#modalFacturaProveedor .modal-dialog');
        var btnIcon = $(this).find('i');
        
        if (modalDialog.hasClass('modal-fullscreen')) {
            modalDialog.removeClass('modal-fullscreen');
            btnIcon.removeClass('fa-compress').addClass('fa-expand');
        } else {
            modalDialog.addClass('modal-fullscreen');
            btnIcon.removeClass('fa-expand').addClass('fa-compress');
        }
    });

    // Eliminar detalle
    $(document).on('click', '.btn-eliminar-detalle', function() {
        var idx = $(this).data('idx');
        
        Swal.fire({
            title: '¿Eliminar producto?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                detalles = detalles.filter(function(item) {
                    return item.detalle_idx != idx;
                });
                renderizarDetalles();
                actualizarTotales();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Eliminado',
                    text: 'Producto eliminado del detalle',
                    showConfirmButton: false,
                    timer: 1500,
                    toast: true,
                    position: 'top-end'
                });
            }
        });
    });

    // Editar detalle
    $(document).on('click', '.btn-editar-detalle', function() {
        var idx = $(this).data('idx');
        var detalle = detalles.find(function(item) {
            return item.detalle_idx == idx;
        });

        if (detalle) {
            // Cargar datos en el formulario
            $('#busqueda_producto').val(detalle.producto_nombre);
            $('#producto_seleccionado_id').val(detalle.producto_id);
            $('#producto_iva_id').val(detalle.iva_alicuota_id);
            $('#producto_cantidad').val(detalle.cantidad);
            $('#producto_precio').val(detalle.precio_unitario);
            $('#producto_descuento_item_pct').val(detalle.descuento_item_pct);
            $('#producto_iva').val(detalle.iva_porcentaje);
            $('#producto_iva_importe').val(detalle.iva_importe);
            $('#producto_no_gravado').val(detalle.no_gravado || 0);
            $('#producto_exento').val(detalle.exento || 0);
            
            // Eliminar el detalle original
            detalles = detalles.filter(function(item) {
                return item.detalle_idx != idx;
            });
            renderizarDetalles();
            actualizarTotales();
            
            $('#busqueda_producto').focus();
        }
    });

    // ========== FUNCIONES DE CARGA DE COMBOS ==========
    function cargarCombosFormulario() {
        // Cargar sucursales de la empresa
        $.get('facturas_proveedores_ajax.php', { 
            accion: 'obtener_sucursales_empresa',
            empresa_idx: empresa_idx 
        }, function(data) {
            let options = '<option value="">Seleccionar sucursal</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.sucursal_id}">${item.sucursal_nombre}</option>`;
                });
            }
            $('#sucursal_id').html(options);
            console.log("Sucursales cargadas:", data);
        }, 'json');

        // Cargar tipos de comprobante
        $.get('facturas_proveedores_ajax.php', { accion: 'obtener_comprobantes_tipos' }, function(data) {
            if (data && data.length > 0) {
                if (data.length === 1) {
                    var options = `<option value="${data[0].comprobante_tipo_id}" selected>${data[0].comprobante_tipo}</option>`;
                    $('#comprobante_tipo_id').html(options);
                } else {
                    var options = '<option value="">Seleccionar</option>';
                    data.forEach(function(item) {
                        options += `<option value="${item.comprobante_tipo_id}">${item.comprobante_tipo}</option>`;
                    });
                    $('#comprobante_tipo_id').html(options);
                }
            }
        }, 'json');

        // Cargar monedas
        $.get('facturas_proveedores_ajax.php', { accion: 'obtener_monedas' }, function(data) {
            var options = '<option value="">Seleccionar moneda</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    var selected = item.es_moneda_base == 1 ? 'selected' : '';
                    options += `<option value="${item.moneda_id}" data-cotizacion="${item.cotizacion_actual}" ${selected}>${item.moneda} (${item.simbolo})</option>`;
                });
            }
            $('#moneda_id').html(options);
            
            var selectedMoneda = $('#moneda_id').find('option:selected');
            var cotizacion = selectedMoneda.data('cotizacion');
            if (cotizacion) {
                $('#tipo_cambio').val(cotizacion);
            }
            
            $('#moneda_id').off('change').on('change', function() {
                var selected = $(this).find('option:selected');
                var cotizacion = selected.data('cotizacion');
                if (cotizacion) {
                    $('#tipo_cambio').val(cotizacion);
                }
            });
        }, 'json');

        // Cargar condiciones de pago (esta función ya tiene su propio manejador)
        cargarCondicionesPago();
    }

    // ========== FUNCIONES DEL MODAL ==========
    // En facturas_proveedores.js, modificar resetModal para recargar impuestos

    function resetModal() {
        $('#formFacturaProveedor')[0].reset();
        $('#factura_proveedor_id').val('');
        $('#entidad_id').val('');
        $('#entidad_sucursal_id').val('');
        $('#tipo_cambio').val('1.000000');
        $('#subtotal').val('0');
        $('#descuentos').val('0');
        $('#no_gravado').val('0');
        $('#exento').val('0');
        $('#impuestos').val('0');
        $('#total').val('0');
        $('#formFacturaProveedor').removeClass('was-validated');
        $('#deposito_id').val('');
        
        detalles = [];
        proveedorActualId = null;
        proveedorSucursalActualId = null;
        renderizarDetalles();
        actualizarTotales();
        
        // Limpiar impuestos y recargar configuración
        impuestosFactura = [];
        
        // Recargar configuración de impuestos si hay proveedor seleccionado
        if (proveedorActualId) {
            cargarImpuestosConfig();
        } else {
            // Cargar configuración por defecto
            cargarImpuestosConfig();
        }
        
        $('#entidad_combo').html('<option value="">Seleccionar proveedor o sucursal</option>');
        $('#proveedor_actual_nombre').text('No seleccionado');
        
        window.sucursalIdEditar = null;
        $('#f_contabilidad').val('');
    }

    $(document).on('click', '#btnNuevo', function () {
        resetModal();
        $('#modalLabel').text('Nueva Factura de Proveedor');
        cargarCombosFormulario();
        cargarProveedoresYSucursales(); // Cargar combo unificado
        
        var today = new Date().toISOString().split('T')[0];
        $('#f_emision').val(today);
        $('#f_contabilidad').val(today); // Inicializar contabilidad con misma fecha

        var modal = new bootstrap.Modal(document.getElementById('modalFacturaProveedor'));
        modal.show();
    });

    // ========== CARGA DE FACTURA PARA EDITAR ==========
    function cargarFacturaParaEditar(facturaId) {
        $.get('facturas_proveedores_ajax.php', {
            accion: 'obtener',
            factura_proveedor_id: facturaId,
            empresa_idx: empresa_idx
        }, function (res) {
            console.log("Factura recibida:", res);
            
            if (res && res.factura_proveedor_id) {
                resetModal();
                
                var sucursalIdParaEditar = res.sucursal_id || null;
                
                cargarCombosFormulario();
                cargarProveedoresYSucursales();
                
                $('#factura_proveedor_id').val(res.factura_proveedor_id);
                $('#comprobante_nro').val(res.comprobante_nro);
                $('#comprobante_pv').val(res.comprobante_pv || '0');
                $('#f_emision').val(res.f_emision);
                $('#f_contabilidad').val(res.f_contabilidad || res.f_emision);
                $('#f_vencimiento').val(res.f_vencimiento);
                $('#direccion').val(res.direccion);
                $('#observaciones').val(res.observaciones);
                $('#tipo_cambio').val(res.tipo_cambio || '1.000000');
                $('#descuento_general_pct').val(res.descuento_general_pct || 0);
                $('#subtotal').val(res.subtotal || 0);
                $('#descuentos').val(res.descuentos || 0);
                $('#no_gravado').val(res.no_gravado || 0);
                $('#exento').val(res.exento || 0);
                $('#impuestos').val(res.impuestos || 0);
                $('#total').val(res.total || 0);
                
                $('#total_neto_display').text(parseFloat(res.subtotal || 0).toFixed(2));
                $('#descuentos_display').text(parseFloat(res.descuentos || 0).toFixed(2));
                $('#no_gravado_display').text(parseFloat(res.no_gravado || 0).toFixed(2));
                $('#exento_display').text(parseFloat(res.exento || 0).toFixed(2));
                $('#impuestos_display').text(parseFloat(res.impuestos || 0).toFixed(2));
                $('#total_display').text(parseFloat(res.total || 0).toFixed(2));
                $('#modalLabel').text('Editar Factura de Proveedor');

                setTimeout(function() {
                    $('#comprobante_tipo_id').val(res.comprobante_tipo_id);
                    $('#moneda_id').val(res.moneda_id);
                    $('#condicion_pago_id').val(res.condicion_pago_id);
                    
                    if (sucursalIdParaEditar) {
                        $('#sucursal_id').val(sucursalIdParaEditar);
                        // Cargar depósitos y luego asignar el depósito guardado
                        cargarDepositosPorSucursal(sucursalIdParaEditar, function() {
                            if (res.deposito_id) {
                                $('#deposito_id').val(res.deposito_id);
                            }
                        });
                    } else {
                        // Si no hay sucursal, limpiar depósitos
                        cargarDepositosPorSucursal(null);
                    }
                    if (res.deposito_id) {
                        $('#deposito_id').val(res.deposito_id);
                    }
                    if (res.entidad_id) {
                        proveedorActualId = res.entidad_id;
                        
                        if (res.entidad_sucursal_id && res.entidad_sucursal_id > 0) {
                            $('#entidad_combo').val('S-' + res.entidad_sucursal_id);
                            proveedorSucursalActualId = parseInt(res.entidad_sucursal_id);
                        } else {
                            $('#entidad_combo').val('P-' + res.entidad_id);
                            proveedorSucursalActualId = null;
                        }
                        
                        var textoSeleccionado = $('#entidad_combo option:selected').text();
                        $('#proveedor_actual_nombre').text(textoSeleccionado || 'No seleccionado');
                    }
                    
                    // Después de cargar la condición de pago, recalcular vencimiento
                    // por si acaso la fecha de vencimiento original no coincide con los días
                    actualizarVencimientoPorCondicionPago();
                    
                    // Cargar detalles con todos los campos
                    if (res.detalles && res.detalles.length > 0) {
                        console.log("Detalles recibidos:", res.detalles);
                        
                        detalles = res.detalles.map(function(detalle, index) {
                            return {
                                detalle_idx: index,
                                factura_proveedor_detalle_id: detalle.factura_proveedor_detalle_id,
                                producto_id: detalle.producto_id,
                                producto_nombre: detalle.producto_nombre,
                                cantidad: detalle.cantidad,
                                precio_unitario: detalle.precio_unitario,
                                descuento_item_pct: detalle.descuento_item_pct || 0,
                                descuento_general_pct: detalle.descuento_general_pct || 0,
                                descuento_item: detalle.descuento_item || 0,
                                descuento_general: detalle.descuento_general || 0,
                                descuento: detalle.descuento || 0,
                                precio_unitario_bruto: detalle.precio_unitario_bruto || detalle.precio_unitario,
                                no_gravado: detalle.no_gravado || 0,
                                exento: detalle.exento || 0,
                                iva_alicuota_id: detalle.iva_alicuota_id,
                                iva_porcentaje: detalle.iva_porcentaje,
                                neto_gravado: detalle.neto_gravado,
                                iva_importe: detalle.iva_importe,
                                total_linea: detalle.total_linea
                            };
                        });
                        
                        console.log("Detalles procesados:", detalles);
                        renderizarDetalles();
                        actualizarTotales();
                    }
                }, 500);

                var modal = new bootstrap.Modal(document.getElementById('modalFacturaProveedor'));
                modal.show();
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos de la factura",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    }

    // ========== GUARDAR FACTURA ==========
    $('#btnGuardar').click(function() {
        var form = document.getElementById('formFacturaProveedor');
        var fEmision = $('#f_emision').val();
        var fVencimiento = $('#f_vencimiento').val();

        if (fVencimiento && fVencimiento < fEmision) {
            Swal.fire({
                icon: "warning",
                title: "Fecha inválida",
                text: "La fecha de vencimiento debe ser mayor o igual a la fecha de emisión",
                confirmButtonText: "Entendido"
            });
            return false;
        }
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        
        if (detalles.length === 0) {
            Swal.fire({
                icon: "warning",
                title: "Detalles requeridos",
                text: "Debe agregar al menos un producto al detalle",
                confirmButtonText: "Entendido"
            });
            return false;
        }
        
        var id = $('#factura_proveedor_id').val();
        var accionBackend = id ? 'editar' : 'agregar';
        
        var btnGuardar = $(this);
        var originalText = btnGuardar.html();
        btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
        
        // Validar que se haya seleccionado un proveedor
        if (!proveedorActualId) {
            Swal.fire({
                icon: "warning",
                title: "Proveedor requerido",
                text: "Debe seleccionar un proveedor",
                confirmButtonText: "Entendido"
            });
            btnGuardar.prop('disabled', false).html(originalText);
            return false;
        }
        
        // Crear FormData manualmente
        var formData = new FormData();
        formData.append('accion', accionBackend);
        formData.append('empresa_idx', empresa_idx);
        formData.append('pagina_idx', pagina_idx);
        formData.append('factura_proveedor_id', $('#factura_proveedor_id').val() || '');
        formData.append('sucursal_id', $('#sucursal_id').val() || '');
        formData.append('entidad_id', proveedorActualId);
        formData.append('entidad_sucursal_id', proveedorSucursalActualId !== null ? proveedorSucursalActualId : '');
        formData.append('comprobante_tipo_id', $('#comprobante_tipo_id').val() || '');
        formData.append('comprobante_pv', $('#comprobante_pv').val() || '0');
        formData.append('comprobante_nro', $('#comprobante_nro').val() || '0');
        formData.append('f_emision', $('#f_emision').val() || '');
        formData.append('f_vencimiento', $('#f_vencimiento').val() || '');
        formData.append('condicion_pago_id', $('#condicion_pago_id').val() || '');
        formData.append('moneda_id', $('#moneda_id').val() || '');
        formData.append('tipo_cambio', $('#tipo_cambio').val() || '1.000000');
        formData.append('direccion', $('#direccion').val() || '');
        formData.append('observaciones', $('#observaciones').val() || '');
        formData.append('subtotal', $('#subtotal').val() || '0');
        formData.append('descuentos', $('#descuentos').val() || '0');
        formData.append('no_gravado', $('#no_gravado').val() || '0');
        formData.append('exento', $('#exento').val() || '0');
        formData.append('impuestos', $('#impuestos').val() || '0');
        formData.append('total', $('#total').val() || '0');
        formData.append('detalles', JSON.stringify(detalles));
        formData.append('descuento_general_pct', $('#descuento_general_pct').val() || '0');
        formData.append('deposito_id', $('#deposito_id').val() || '');
        formData.append('impuestos_adicionales', JSON.stringify(impuestosFactura));
        
        console.log("Impuestos a guardar:", impuestosFactura);
        

        // Log para depuración
        console.log("=== DATOS ENVIADOS ===");
        for (var pair of formData.entries()) {
            console.log(pair[0] + ': "' + pair[1] + '"');
        }
        
        var savedState = {
            page: tabla ? tabla.page() : 0,
            order: tabla ? tabla.order() : [[1, 'asc']],
            search: tabla ? tabla.search() : ''
        };
        
        $.ajax({
            url: 'facturas_proveedores_ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                // SIEMPRE habilitar el botón primero
                btnGuardar.prop('disabled', false).html(originalText);
                
                if (res.resultado) {
                    if (tabla) {
                        tabla.ajax.reload(function(json) {
                            if (savedState.page !== undefined) {
                                tabla.page(savedState.page).draw('page');
                            }
                            if (savedState.search && savedState.search !== '') {
                                tabla.search(savedState.search).draw();
                            }
                        }, false);
                    }
                    
                    Swal.fire({
                        icon: "success",
                        title: "¡Guardado!",
                        text: "Factura de proveedor guardada correctamente",
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
                    
                    // Cerrar modal
                    var modalEl = document.getElementById('modalFacturaProveedor');
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) {
                        modal.hide();
                    } else {
                        modal = new bootstrap.Modal(modalEl);
                        modal.hide();
                    }
                    
                    // También podemos forzar la eliminación de la clase modal-open del body
                    $('body').removeClass('modal-open');
                    $('.modal-backdrop').remove();
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || "Error al guardar los datos",
                        confirmButtonText: "Entendido"
                    });
                }
            },
            error: function(xhr, status, error) {
                btnGuardar.prop('disabled', false).html(originalText);
                
                console.error("Error AJAX:", error);
                console.error("Respuesta:", xhr.responseText);
                
                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    text: "Error al comunicarse con el servidor",
                    confirmButtonText: "Entendido"
                });
            }
        });
    });

    // ========== PRODUCTO RÁPIDO ==========
    function cargarDatosProductoRapido() {
        $.get('facturas_proveedores_ajax.php', { 
            accion: 'obtener_categorias_productos',
            empresa_idx: empresa_idx 
        }, function(data) {
            var options = '<option value="">Seleccionar categoría</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.producto_categoria_id}">${item.categoria_nombre}</option>`;
                });
            }
            $('#producto_categoria_id_rapido').html(options);
        }, 'json');

        $.get('facturas_proveedores_ajax.php', { accion: 'obtener_unidades_medida' }, function(data) {
            var options = '<option value="">Seleccionar unidad</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.unidad_medida_id}">${item.unidad_nombre}</option>`;
                });
            }
            $('#unidad_medida_id_rapido').html(options);
        }, 'json');
        
        // Cargar alícuotas de IVA
        $.get('facturas_proveedores_ajax.php', { accion: 'obtener_alicuotas_iva' }, function(data) {
            var options = '';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    var selected = item.iva_alicuota_id == 1 ? 'selected' : '';
                    options += `<option value="${item.iva_alicuota_id}" data-porcentaje="${item.porcentaje}" ${selected}>${item.iva_alicuota} (${item.porcentaje}%)</option>`;
                });
            }
            $('#iva_alicuota_id_rapido').html(options);
        }, 'json');
    }

    $(document).on('click', '#btnNuevoProductoRapido', function() {
        if (!proveedorActualId) {
            Swal.fire({
                icon: "warning",
                title: "Seleccione proveedor",
                text: "Debe seleccionar un proveedor primero",
                confirmButtonText: "Entendido"
            });
            return;
        }

        $('#formNuevoProductoRapido')[0].reset();
        $('#formNuevoProductoRapido').removeClass('was-validated');
        
        cargarDatosProductoRapido();
        
        var modal = new bootstrap.Modal(document.getElementById('modalNuevoProductoRapido'));
        modal.show();
    });

    $(document).on('click', '#btnGuardarProductoRapido', function() {
        var form = document.getElementById('formNuevoProductoRapido');
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }

        var btn = $(this);
        var originalText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        var data = {
            accion: 'agregar_producto_rapido',
            empresa_idx: empresa_idx,
            producto_codigo: $('#producto_codigo_rapido').val(),
            producto_nombre: $('#producto_nombre_rapido').val(),
            codigo_barras: $('#codigo_barras_rapido').val(),
            producto_descripcion: $('#producto_descripcion_rapido').val(),
            producto_categoria_id: $('#producto_categoria_id_rapido').val(),
            iva_alicuota_id: $('#iva_alicuota_id_rapido').val(),
            unidad_medida_id: $('#unidad_medida_id_rapido').val() || null,
            codigo_proveedor: $('#codigo_proveedor_rapido').val(),
            entidad_id: proveedorActualId
        };

        $.ajax({
            url: 'facturas_proveedores_ajax.php',
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    var modalProducto = bootstrap.Modal.getInstance(document.getElementById('modalNuevoProductoRapido'));
                    modalProducto.hide();
                    
                    Swal.fire({
                        icon: "success",
                        title: "Producto creado",
                        text: "El producto fue creado exitosamente",
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || "Error al crear el producto",
                        confirmButtonText: "Entendido"
                    });
                }
                btn.prop('disabled', false).html(originalText);
            },
            error: function() {
                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    text: "Error al comunicarse con el servidor",
                    confirmButtonText: "Entendido"
                });
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // ========== INICIALIZACIÓN ==========
    inicializarDataTable();
    cargarBotonAgregar();

    $('[title]').tooltip({
        trigger: 'hover',
        placement: 'top'
    });
    
    function cargarCondicionesProveedor(entidadId) {
        if (!entidadId) {
            console.log("No hay entidadId para cargar condiciones");
            return;
        }
        
        console.log("Cargando condiciones para entidadId:", entidadId);
        
        $.get('facturas_proveedores_ajax.php', {
            accion: 'obtener_condiciones_proveedor',
            entidad_id: entidadId,
            empresa_idx: empresa_idx
        }, function(res) {
            console.log("Respuesta de condiciones:", res);
            
            if (res.success && res.data) {
                console.log("Condiciones encontradas:", res.data);
                
                // Aplicar condición de pago si existe
                if (res.data.condicion_pago_id && res.data.condicion_pago_id > 0) {
                    console.log("Aplicando condición de pago ID:", res.data.condicion_pago_id);
                    
                    function setCondicionPago() {
                        if ($('#condicion_pago_id option[value="' + res.data.condicion_pago_id + '"]').length) {
                            $('#condicion_pago_id').val(res.data.condicion_pago_id);
                            console.log("Condición de pago aplicada");
                            // Forzar actualización del vencimiento después de cambiar la condición
                            actualizarVencimientoPorCondicionPago();
                        } else {
                            console.log("Esperando que cargue el combo de condiciones...");
                            setTimeout(setCondicionPago, 100);
                        }
                    }
                    setCondicionPago();
                } else {
                    console.log("No hay condición de pago para este proveedor");
                }
                
                // Aplicar descuento general
                var descuentoGeneral = parseFloat(res.data.proveedor_descuento_general) || 0;
                console.log("Aplicando descuento general:", descuentoGeneral);
                
                $('#descuento_general_pct').val(descuentoGeneral);
                
                if (detalles.length > 0) {
                    detalles.forEach(function(detalle, index) {
                        var netoBase = detalle.cantidad * detalle.precio_unitario;
                        var descuentoItem = netoBase * (detalle.descuento_item_pct / 100);
                        var netoDespuesItem = netoBase - descuentoItem;
                        var descuentoGeneralMonto = netoDespuesItem * (descuentoGeneral / 100);
                        var descuentoTotal = descuentoItem + descuentoGeneralMonto;
                        
                        detalle.descuento_general_pct = descuentoGeneral;
                        detalle.descuento_general = descuentoGeneralMonto;
                        detalle.descuento = descuentoTotal;
                        detalle.neto_gravado = netoBase - descuentoTotal;
                        detalle.iva_importe = detalle.neto_gravado * (detalle.iva_porcentaje / 100);
                        detalle.total_linea = detalle.neto_gravado + detalle.iva_importe + detalle.no_gravado + detalle.exento;
                        
                        detalles[index] = detalle;
                    });
                    
                    renderizarDetalles();
                    actualizarTotales();
                } else {
                    actualizarTotales();
                }
            } else {
                console.log("No hay condiciones para este proveedor o error:", res);
                $('#descuento_general_pct').val(0);
                
                if (detalles.length > 0) {
                    detalles.forEach(function(detalle, index) {
                        var netoBase = detalle.cantidad * detalle.precio_unitario;
                        var descuentoItem = netoBase * (detalle.descuento_item_pct / 100);
                        var descuentoTotal = descuentoItem;
                        
                        detalle.descuento_general_pct = 0;
                        detalle.descuento_general = 0;
                        detalle.descuento = descuentoTotal;
                        detalle.neto_gravado = netoBase - descuentoTotal;
                        detalle.iva_importe = detalle.neto_gravado * (detalle.iva_porcentaje / 100);
                        detalle.total_linea = detalle.neto_gravado + detalle.iva_importe + detalle.no_gravado + detalle.exento;
                        
                        detalles[index] = detalle;
                    });
                    
                    renderizarDetalles();
                    actualizarTotales();
                } else {
                    actualizarTotales();
                }
            }
        }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
            console.error("Error al cargar condiciones:", textStatus, errorThrown);
            console.error("Respuesta:", jqXHR.responseText);
        });
    }
    $('#modalFacturaProveedor').on('shown.bs.modal', function () {
    $('#datos-tab').tab('show');
});

});
