$(document).ready(function () {
    const empresa_idx = window.EMPRESA_ID; // empresa activa (header1.php)
    // pagina_idx ya se define en facturas_proveedores.php (57 en gestion, 557 en gestion_normal)
    
    var tabla;
    var currentPage = 0;
    var currentOrder = [[1, 'asc']];
    var currentSearch = '';
    
    // Variables para manejo de detalles
    var detalles = [];
    var proveedorActualId = null;
    // Compra por presentación (doc 27): solo si la página cargó assets/js/compras-presentaciones.js (indicador)
    var CPRE = window.ComprasPres || null;
    var proveedorSucursalActualId = null;
    var timeoutBusqueda = null;
    var selectedIndex = -1;
    var impuestosFactura = [];
    // Línea en edición que venía de una OC: se conserva el vínculo si no cambia el producto
    var detalleEnEdicionOC = null;

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
        },
        dataType: 'json',
        success: function(data) {
            console.log("Impuestos config cargados:", data);
            window.impuestosConfig = data || [];
            
            // Si hay una factura cargada, mostrar los impuestos ya guardados
            var facturaId = $('#factura_proveedor_id').val();
            if (facturaId && facturaId > 0) {
                cargarImpuestosFactura(facturaId);
            } else if (window.fpLecturaEnCurso) {
                // Nueva factura desde archivo: las percepciones son las leídas (fpPrecargarDesdeArchivo)
                renderizarImpuestos();
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
    // Crear impuestos sugeridos desde la configuración (solo los configurados con aplica_siempre = 1)
    if (window.impuestosConfig && window.impuestosConfig.length > 0) {
        impuestosFactura = [];
        
        window.impuestosConfig.forEach(function(config) {
            // Solo cargar automáticamente por defecto si está configurado con aplica_siempre = 1
            if (parseInt(config.aplica_siempre || 0) !== 1) {
                return;
            }

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
                    aplica_siempre: parseInt(config.aplica_siempre || 0),
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
    
    // Guardar valores originales en los inputs hidden
    $('#subtotal').val(totalNeto.toFixed(2));
    $('#descuentos').val(totalDescuentos.toFixed(2));
    $('#no_gravado').val(totalNoGravado.toFixed(2));
    $('#exento').val(totalExento.toFixed(2));
    $('#impuestos').val(totalImpuestos.toFixed(2));
    
    // Mostrar valores formateados
    $('#total_neto_display').text(formatNumber(totalNeto, 2));
    $('#descuentos_display').text(formatNumber(totalDescuentos, 2));
    $('#no_gravado_display').text(formatNumber(totalNoGravado, 2));
    $('#exento_display').text(formatNumber(totalExento, 2));
    $('#impuestos_display').text(formatNumber(totalImpuestos, 2));
    
    // Recalcular impuestos adicionales basados en los nuevos totales
    recalcularImpuestosPorTotal();
    actualizarTotalConImpuestos();
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
        
        // Recalcular total general: subtotal ya tiene los descuentos aplicados
        var subtotal = parseFloat($('#subtotal').val()) || 0;
        var noGravado = parseFloat($('#no_gravado').val()) || 0;
        var exento = parseFloat($('#exento').val()) || 0;
        var impuestos = parseFloat($('#impuestos').val()) || 0;
        var totalGeneral = subtotal + noGravado + exento + impuestos + otrosImpuestos;
        
        $('#total').val(totalGeneral.toFixed(2));
        $('#total_display').text(formatNumber(totalGeneral, 2));
    }

    // Actualizar mostrarModalImpuesto para usar los tipos disponibles

function mostrarModalImpuesto(impuestoData, idx) {
    if ($('#modalImpuesto').length) $('#modalImpuesto').remove();
    
    // Obtener el neto gravado actual de la factura
    var netoFactura = parseFloat($('#subtotal').val()) || 0;
    if (netoFactura <= 0 && typeof detalles !== 'undefined' && Array.isArray(detalles)) {
        detalles.forEach(function(d) {
            netoFactura += parseFloat(d.neto_gravado || 0);
        });
    }
    
    // Si es edición usamos el valor previo; si es alta precargamos el neto de la factura
    var baseInicial = 0;
    if (impuestoData && impuestoData.base_imponible !== undefined) {
        baseInicial = parseFloat(impuestoData.base_imponible || 0);
    } else if (idx === undefined) {
        baseInicial = netoFactura;
    }
    
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
                            <input type="number" step="0.01" class="form-control" id="base_imponible" value="${baseInicial.toFixed(2)}">
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
                                        data-es-percepcion="${config.es_percepcion || 0}"
                                        data-base-calculo="${config.base_calculo || 'NETO_GRAVADO'}">
                                        ${escapeHtml(descripcion)} - Alícuota: ${config.alicuota || 0}%
                                    </option>`);
                });
            } else {
                select.append('<option value="">No hay configuraciones de impuestos disponibles</option>');
            }
            
            if (impuestoData) {
                $('#config_impuesto_id').val(impuestoData.empresa_impuesto_config_id);
                $('#base_imponible').val(parseFloat(impuestoData.base_imponible || 0).toFixed(2));
                $('#alicuota').val(parseFloat(impuestoData.alicuota || 0).toFixed(2));
                $('#importe').val(parseFloat(impuestoData.importe || 0).toFixed(2));
                $('#detalle_origen').val(impuestoData.detalle_origen || '');
            } else if (netoFactura > 0) {
                $('#base_imponible').val(netoFactura.toFixed(2));
            }
        },
        error: function(xhr, status, error) {
            console.error("Error cargando configuraciones:", error);
            $('#config_impuesto_id').html('<option value="">Error al cargar impuestos</option>');
        }
    });
    
    // Cuando se selecciona una configuración, cargar sus valores por defecto
    $(document).off('change', '#config_impuesto_id').on('change', '#config_impuesto_id', function() {
        var selected = $(this).find('option:selected');
        if (!selected.val()) {
            return;
        }
        var alicuota = parseFloat(selected.data('alicuota')) || 0;
        var minimo = parseFloat(selected.data('minimo')) || 0;
        var esPercepcion = parseInt(selected.data('es-percepcion') || 0) === 1 || 
                           (selected.data('impuesto-tipo') && /percepci[oó]n/i.test(selected.data('impuesto-tipo'))) ||
                           /percepci[oó]n/i.test(selected.text());
        var baseCalculo = selected.data('base-calculo') || 'NETO_GRAVADO';

        $('#alicuota').val(alicuota.toFixed(2));

        var esAlta = ($('#impuesto_idx').val() === '');
        // Cuando se da de alta un nuevo impuesto de percepción, precargar el neto de la factura
        if (esAlta || parseFloat($('#base_imponible').val()) === 0) {
            if (esPercepcion || baseCalculo === 'NETO_GRAVADO') {
                var neto = parseFloat($('#subtotal').val()) || 0;
                if (neto <= 0 && typeof detalles !== 'undefined' && Array.isArray(detalles)) {
                    detalles.forEach(function(d) {
                        neto += parseFloat(d.neto_gravado || 0);
                    });
                }
                var baseFinal = (minimo > 0 && neto < minimo) ? minimo : neto;
                $('#base_imponible').val(baseFinal.toFixed(2));
            } else if (minimo > 0) {
                $('#base_imponible').val(minimo.toFixed(2));
            }
        } else if (minimo > 0 && parseFloat($('#base_imponible').val()) < minimo) {
            $('#base_imponible').val(minimo.toFixed(2));
        }

        calcularImporteImpuesto();
    });
    
    // Calcular importe automáticamente
    $('#base_imponible, #alicuota').off('input').on('input', function() {
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

    // Agregar evento para cuando cambia el importe
    $('#importe').off('input').on('input', function() {
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
        return Fmt.numero(num, {
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
                data: function (d) {
                    return {
                        accion: 'listar',
                        empresa_idx: empresa_id,
                        pagina_idx: pagina_idx,
                        filtro_estado: $('#filtroEstado').val() || ''
                    };
                },
                dataSrc: ''
            },
            dom: '<"row align-items-center mb-2"<"col-12 text-end"f>>' +
                '<"row"<"col-sm-12"tr>>' +
                '<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>' +
                '<"clear">',
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            // Emisión más nueva primero (columna 6) y, en el mismo día, la última cargada
            order: [[6, 'desc'], [0, 'desc']],

            columns: [
                { data: 'factura_proveedor_id', className: 'ui-detalle' },
                { data: 'comprobante_tipo', defaultContent: '-' },
                { 
                    data: null,
                    render: function(data) {
                        var pv = data.comprobante_pv ? data.comprobante_pv + '-' : '';
                        return pv + (data.comprobante_nro || '-');
                    }
                },
                { 
                    data: 'entidad_fantasia',
                    defaultContent: '-',
                    render: function(data, type, row) {
                        return (row && (row.entidad_fantasia || row.entidad_nombre)) || data || '-';
                    }
                },
                { data: 'sucursal_nombre', defaultContent: '-' },
                { data: 'deposito_nombre', defaultContent: '-' },
                { 
                    data: 'f_emision',
                    render: function(data, type) {
                        if (type !== 'display') return data || '';   // ordena por la fecha ISO
                        if (!data) return '-';
                        return Fmt.fecha(data);
                    }
                },
                { 
                    data: 'f_vencimiento',
                    render: function(data) {
                        if (!data) return '-';
                        return Fmt.fecha(data);
                    }
                },
                { 
                    data: 'total',
                    className: 'text-end',
                    render: function(data) { return formatNumber(data, 2); }
                },
                { 
                    data: 'estado_info',
                    className: 'text-center',
                    render: function(data) {
                        if (!data || !data.estado_registro) {
                            return '<span>Sin estado</span>';
                        }                        
                        return `<span>${data.estado_registro}</span>`;
                    }
                },
                { 
                    data: 'botones',
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    render: function(data, type, row) {
                        if (type === 'export') return '';
                        if (!data || data.length === 0) return '<span class="text-muted small">-</span>';
                        
                        var botonesHtml = '';
                        data.forEach(function(boton) {
                            var colorClase = '';
                            if (boton.bg_clase && boton.text_clase) {
                                colorClase = boton.bg_clase + ' ' + boton.text_clase;
                            } else if (boton.color_clase) {
                                colorClase = boton.color_clase;
                            } else {
                                colorClase = 'btn-outline-primary';
                            }
                            
                            var icono = boton.icono_clase ? `<i class="${boton.icono_clase}"></i>` : '';
                            var titulo = boton.descripcion || boton.nombre_funcion;
                            var accion = boton.accion_js;
                            
                            botonesHtml += `<button type="button" class="btn btn-sm ${colorClase} me-1 btn-accion" 
                                                    title="${titulo}" 
                                                    data-id="${row.factura_proveedor_id}" 
                                                    data-accion="${accion}"
                                                    data-confirmable="${boton.es_confirmable || 0}">
                                                ${icono}
                                            </button>`;
                        });
                        
                        return `<div class="btn-group" role="group">${botonesHtml}</div>`;
                    }
                },
                { 
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    render: function(data, type, row) {
                        if (type === 'export') return '';
                        
                        // Permitir generar/actualizar asiento si tiene comprobante
                        var tieneComprobante = row.comprobante_id ? true : false;
                        
                        if (tieneComprobante) {
                            return `<button type="button" class="btn btn-sm btn-info btn-generar-asiento" 
                                            title="Generar/Actualizar Asiento Contable" 
                                            data-id="${row.factura_proveedor_id}">
                                        <i class="fas fa-book"></i> Asiento
                                    </button>`;
                        } else {
                            return `<span class="badge bg-secondary" title="Sin comprobante asociado">
                                        <i class="fas fa-clock"></i> Pendiente
                                    </span>`;
                        }
                    }
                }
            ],
            language: {
                url: window.APP_ASSETS_URL + 'js/datatables-i18n-1.13.4/es-ES.json',
                lengthMenu: 'Mostrar _MENU_ registros',
                info: 'Página _PAGE_ de _PAGES_',
                infoEmpty: 'Página 0 de 0',
                infoFiltered: '(filtrado de _MAX_ registros)',
                paginate: {
                    first: 'Primero',
                    previous: 'Anterior',
                    next: 'Siguiente',
                    last: 'Último'
                }
            },

            responsive: true
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
            // Fecha local (con 'T00:00:00'; sin hora se toma como UTC y en Argentina da un día menos)
            var fecha = new Date(fEmision + 'T00:00:00');
            fecha.setDate(fecha.getDate() + dias);

            // Formatear como YYYY-MM-DD
            var year = fecha.getFullYear();
            var month = String(fecha.getMonth() + 1).padStart(2, '0');
            var day = String(fecha.getDate()).padStart(2, '0');
            var fechaVencimiento = `${year}-${month}-${day}`;

            $('#f_vencimiento').val(fechaVencimiento);
            console.log("Fecha vencimiento calculada:", fechaVencimiento);
        } else {
            // La condición no tiene días: vence en el día
            $('#f_vencimiento').val(vencimientoDelDia());
        }
    }
    // Vencimiento cuando no sale de la condición de compra: la fecha del día (nunca antes de la emisión)
    function vencimientoDelDia() {
        var hoy = new Date();
        var iso = hoy.getFullYear() + '-' + String(hoy.getMonth() + 1).padStart(2, '0') + '-' + String(hoy.getDate()).padStart(2, '0');
        var fEmision = $('#f_emision').val();
        return (fEmision && fEmision > iso) ? fEmision : iso;
    }
    function inicializarEventos() {
        $('#filtroEstado').off('change').on('change', function () {
            if (tabla) {
                tabla.ajax.reload();
            }
        });

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

    // ========== FUNCIÓN PARA SELECCIONAR PROVEEDOR EN COMBO UNIFICADO CON FALLBACKS ==========
    function seleccionarProveedorEnCombo(entidadId, entidadSucursalId) {
        if (!entidadId) return;

        proveedorActualId = parseInt(entidadId);
        var targetVal = (entidadSucursalId && parseInt(entidadSucursalId) > 0)
            ? 'S-' + entidadSucursalId
            : 'P-' + entidadId;

        $('#entidad_combo').val(targetVal);

        // Fallback 1: Si no coincidió S-sucursal, intentar con P-entidad
        if (!$('#entidad_combo').val()) {
            $('#entidad_combo').val('P-' + entidadId);
        }

        // Fallback 2: Si el proveedor tiene sucursales y no hay opción P-, seleccionar la primera sucursal del proveedor
        if (!$('#entidad_combo').val()) {
            var matchedOpt = $('#entidad_combo option').filter(function () {
                return String($(this).data('entidad-id')) === String(entidadId);
            }).first();
            if (matchedOpt.length) {
                $('#entidad_combo').val(matchedOpt.val());
            }
        }

        var optSeleccionada = $('#entidad_combo option:selected');
        var sucId = optSeleccionada.data('sucursal-id');
        proveedorSucursalActualId = (sucId && parseInt(sucId) > 0) ? parseInt(sucId) : null;
        $('#entidad_id').val(entidadId);
        $('#entidad_sucursal_id').val(proveedorSucursalActualId || '');

        var textoSeleccionado = optSeleccionada.text();
        $('#proveedor_actual_nombre').text(textoSeleccionado || 'No seleccionado');
    }

    // ========== FUNCIÓN PARA CARGAR PROVEEDORES Y SUCURSALES EN COMBO UNIFICADO ==========
    function cargarProveedoresYSucursales(callback) {
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
                if (typeof callback === 'function') callback();
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("Error cargando proveedores y sucursales:", textStatus, errorThrown);
                $('#entidad_combo').html('<option value="">Error al cargar</option>');
                if (typeof callback === 'function') callback();
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
            desvincularOCSiOtroProveedor();
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
            desvincularOCSiOtroProveedor();
        }
    });

    // ========== ORDEN DE COMPRA -> FACTURA ==========
    // Una factura puede importar las líneas pendientes de UNA orden de compra del proveedor.
    // Cada línea importada guarda ordenes_compra_detalle_id; al confirmar la factura el backend
    // suma lo recibido en la OC y la pasa a Recibido parcial / Recibido.
    var ordenCompraEntidadId = null;
    var ocLineasActuales = [];
    var ocSeleccionada = null;

    function setOrdenCompraVinculada(ordenCompraId, ordenCompraNro, entidadId) {
        ordenCompraId = parseInt(ordenCompraId) || 0;
        if (ordenCompraId > 0) {
            $('#orden_compra_id').val(ordenCompraId);
            $('#oc_vinculada_texto').text('OC N° ' + (ordenCompraNro || ordenCompraId));
            $('#oc_vinculada_badge').removeClass('d-none');
            ordenCompraEntidadId = parseInt(entidadId) || null;
        } else {
            $('#orden_compra_id').val('');
            $('#oc_vinculada_texto').text('');
            $('#oc_vinculada_badge').addClass('d-none');
            ordenCompraEntidadId = null;
        }
    }

    function setCertificacionVinculada(certId, nroCert, periodoMes, montoNeto) {
        certId = parseInt(certId) || 0;
        if (certId > 0) {
            $('#certificacion_id').val(certId);
            var label = 'Cert. N° ' + (nroCert || certId);
            if (periodoMes) label += ' (' + periodoMes + ')';
            $('#cert_vinculada_texto').text(label);
            $('#cert_vinculada_badge').removeClass('d-none');
        } else {
            $('#certificacion_id').val('');
            $('#cert_vinculada_texto').text('');
            $('#cert_vinculada_badge').addClass('d-none');
        }
    }

    function quitarVinculoCert() {
        setCertificacionVinculada(null, null, null, null);
    }

    function quitarVinculoOC() {
        setOrdenCompraVinculada(null, null, null);
        quitarVinculoCert();
        detalles.forEach(function(d) { d.ordenes_compra_detalle_id = null; });
        detalleEnEdicionOC = null;
        renderizarDetalles();
    }

    // Si cambia el proveedor, la OC vinculada (que es del anterior) deja de valer
    function desvincularOCSiOtroProveedor() {
        if (!$('#orden_compra_id').val()) return;
        if (ordenCompraEntidadId && parseInt(proveedorActualId) === ordenCompraEntidadId) return;
        quitarVinculoOC();
        Swal.fire({
            icon: 'info',
            title: 'Se desvinculó la orden de compra',
            text: 'La OC era de otro proveedor. Las líneas quedan en la factura sin vínculo.',
            showConfirmButton: false,
            timer: 3000,
            toast: true,
            position: 'top-end'
        });
    }

    $(document).on('click', '#btnDesvincularOC', function(e) {
        e.preventDefault();
        Swal.fire({
            icon: 'question',
            title: '¿Desvincular la orden de compra?',
            text: 'Las líneas importadas quedan en la factura, pero no se descontarán de la OC.',
            showCancelButton: true,
            confirmButtonText: 'Desvincular',
            cancelButtonText: 'Cancelar'
        }).then(function(result) {
            if (result.isConfirmed) {
                quitarVinculoOC();
            }
        });
    });

    $(document).on('click', '#btnDesvincularCert', function(e) {
        e.preventDefault();
        Swal.fire({
            icon: 'question',
            title: '¿Desvincular certificación?',
            text: 'Se eliminará la asociación de la certificación de avance con esta factura.',
            showCancelButton: true,
            confirmButtonText: 'Desvincular',
            cancelButtonText: 'Cancelar'
        }).then(function(result) {
            if (result.isConfirmed) {
                quitarVinculoCert();
            }
        });
    });

    $('#btnImportarCertificacion').on('click', function() {
        if (!proveedorActualId) {
            Swal.fire({
                icon: 'warning',
                title: 'Seleccione contratista / proveedor',
                text: 'Debe seleccionar el proveedor antes de buscar certificaciones de contratos',
                confirmButtonText: 'Entendido'
            });
            return;
        }

        $.get('facturas_proveedores_ajax.php', {
            accion: 'listar_certificaciones_pendientes',
            entidad_id: proveedorActualId,
            orden_compra_id: $('#orden_compra_id').val() || 0,
            empresa_idx: empresa_idx
        }, function(res) {
            if (!res || !res.success) {
                Swal.fire({ icon: 'error', title: 'Error', text: (res && res.message) || 'No se pudieron consultar las certificaciones' });
                return;
            }
            var certs = res.data || [];
            if (certs.length === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'Sin certificaciones pendientes',
                    text: 'El contratista no tiene certificaciones de avance aprobadas y pendientes de facturar.'
                });
                return;
            }

            var html = '';
            certs.forEach(function(c) {
                var cJson = JSON.stringify(c).replace(/'/g, '&apos;');
                html += `<tr>
                    <td><strong>${escapeHtml(c.comprobante_nro || '')}</strong> ${c.contrato_nro_legal ? '<br><small class="text-muted">' + escapeHtml(c.contrato_nro_legal) + '</small>' : ''}</td>
                    <td class="text-center"><span class="badge bg-primary">N° ${c.nro_certificado}</span></td>
                    <td>${escapeHtml(c.periodo_mes || '-')}</td>
                    <td>${formatearFechaOC(c.fecha_medicion)}</td>
                    <td class="text-center fw-bold">${formatNumber(parseFloat(c.avance_acumulado_pct) || 0, 2)}%</td>
                    <td class="text-end">${formatNumber(parseFloat(c.monto_bruto) || 0, 2)}</td>
                    <td class="text-end text-danger">${parseFloat(c.monto_descuento_anticipo) > 0 ? '-' + formatNumber(parseFloat(c.monto_descuento_anticipo), 2) : '0.00'}</td>
                    <td class="text-end text-warning">${parseFloat(c.monto_fondo_reparo) > 0 ? '-' + formatNumber(parseFloat(c.monto_fondo_reparo), 2) : '0.00'}</td>
                    <td class="text-end fw-bold text-success">${formatNumber(parseFloat(c.monto_neto_facturable) || 0, 2)}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-success btn-cert-seleccionar" data-cert='${cJson}'>
                            <i class="fas fa-check me-1"></i>Vincular
                        </button>
                    </td>
                </tr>`;
            });

            $('#cert_tabla_disponibles').html(html);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalImportarCertificacion')).show();
        }, 'json').fail(function() {
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor' });
        });
    });

    $(document).on('click', '.btn-cert-seleccionar', function() {
        var c = $(this).data('cert');
        if (!c) return;

        setOrdenCompraVinculada(c.orden_compra_id, c.comprobante_nro, proveedorActualId);
        setCertificacionVinculada(c.certificacion_id, c.nro_certificado, c.periodo_mes, c.monto_neto_facturable);

        var netoFacturable = parseFloat(c.monto_neto_facturable) || 0;
        var ivaPct = parseFloat(c.iva_porcentaje) || 21;
        var ivaAlicuotaId = parseInt(c.iva_alicuota_id) || obtenerIdIva(ivaPct);
        var conceptoDesc = 'Certificación N° ' + c.nro_certificado + (c.periodo_mes ? ' (' + c.periodo_mes + ')' : '') + ' - ' + (c.comprobante_nro || 'Contrato');

        if (detalles.length === 0) {
            var ivaImporte = netoFacturable * (ivaPct / 100);
            detalles.push({
                detalle_idx: 'temp_cert_' + new Date().getTime(),
                factura_proveedor_detalle_id: 0,
                ordenes_compra_detalle_id: null,
                producto_id: parseInt(c.producto_id) || 1,
                producto_nombre: conceptoDesc,
                cantidad: 1,
                precio_unitario: netoFacturable,
                descuento_item_pct: 0,
                descuento_general_pct: 0,
                descuento_item: 0,
                descuento_general: 0,
                descuento: 0,
                no_gravado: 0,
                exento: 0,
                iva_alicuota_id: ivaAlicuotaId,
                iva_porcentaje: ivaPct,
                neto_gravado: netoFacturable,
                iva_importe: ivaImporte,
                total_linea: netoFacturable + ivaImporte
            });
            renderizarDetalles();
            actualizarTotales();
        }

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalImportarCertificacion')).hide();

        Swal.fire({
            icon: 'success',
            title: 'Certificación N° ' + c.nro_certificado + ' vinculada',
            text: 'Neto facturable sugerido en ' + formatNumber(netoFacturable, 2),
            showConfirmButton: false,
            timer: 2000,
            toast: true,
            position: 'top-end'
        });
    });

    $('#btnImportarOC').on('click', function() {
        if (!proveedorActualId) {
            Swal.fire({
                icon: 'warning',
                title: 'Seleccione proveedor',
                text: 'Debe seleccionar el proveedor antes de importar una orden de compra',
                confirmButtonText: 'Entendido'
            });
            return;
        }

        $.get('facturas_proveedores_ajax.php', {
            accion: 'listar_oc_pendientes',
            entidad_id: proveedorActualId,
            factura_proveedor_id: $('#factura_proveedor_id').val() || 0,
            empresa_idx: empresa_idx
        }, function(res) {
            if (!res || !res.success) {
                Swal.fire({ icon: 'error', title: 'Error', text: (res && res.message) || 'No se pudieron obtener las órdenes de compra' });
                return;
            }
            var ordenes = res.data || [];
            var ocVinculada = parseInt($('#orden_compra_id').val()) || 0;
            if (ocVinculada > 0) {
                // Una sola OC por factura: si ya hay una, solo se puede seguir importando de esa
                ordenes = ordenes.filter(function(o) { return o.orden_compra_id === ocVinculada; });
            }
            if (ordenes.length === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'Sin órdenes pendientes',
                    text: ocVinculada > 0
                        ? 'La orden de compra vinculada no tiene más líneas pendientes.'
                        : 'El proveedor no tiene órdenes de compra con mercadería pendiente de recibir.'
                });
                return;
            }
            renderOrdenesOC(ordenes);
            var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalImportarOC'));
            modal.show();
            if (ordenes.length === 1) {
                verLineasOC(ordenes[0].orden_compra_id);
            }
        }, 'json').fail(function() {
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor' });
        });
    });

    function formatearFechaOC(fecha) {
        return Fmt.fecha(fecha);
    }

    function renderOrdenesOC(ordenes) {
        var html = '';
        ordenes.forEach(function(o) {
            html += `<tr>
                <td>${escapeHtml(String(o.comprobante_nro || o.orden_compra_id))}</td>
                <td>${formatearFechaOC(o.f_emision)}</td>
                <td>${formatearFechaOC(o.f_entrega_estimada)}</td>
                <td>${escapeHtml(o.sucursal_nombre || '')}</td>
                <td><span class="badge bg-secondary">${escapeHtml(o.estado_registro || '')}</span></td>
                <td class="text-center">${o.lineas_pendientes} / ${o.lineas_total}</td>
                <td class="text-end">${formatNumber(parseFloat(o.total) || 0, 2)}</td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary btn-oc-ver" data-id="${o.orden_compra_id}">
                        <i class="fas fa-list me-1"></i>Ver líneas
                    </button>
                </td>
            </tr>`;
        });
        $('#oc_tabla_ordenes').html(html);
        $('#oc_paso_ordenes').removeClass('d-none');
        $('#oc_paso_lineas').addClass('d-none');
        $('#btnOcAgregarLineas').addClass('d-none');
    }

    $(document).on('click', '.btn-oc-ver', function() {
        verLineasOC($(this).data('id'));
    });

    $('#btnOcVolver').on('click', function() {
        $('#oc_paso_ordenes').removeClass('d-none');
        $('#oc_paso_lineas').addClass('d-none');
        $('#btnOcAgregarLineas').addClass('d-none');
    });

    function verLineasOC(ordenCompraId) {
        $.get('facturas_proveedores_ajax.php', {
            accion: 'obtener_oc_lineas',
            orden_compra_id: ordenCompraId,
            entidad_id: proveedorActualId,
            factura_proveedor_id: $('#factura_proveedor_id').val() || 0,
            empresa_idx: empresa_idx
        }, function(res) {
            if (!res || !res.success) {
                Swal.fire({ icon: 'error', title: 'Error', text: (res && res.message) || 'No se pudieron obtener las líneas' });
                return;
            }
            ocSeleccionada = res.data;
            // Las líneas de la OC que ya están en esta factura no se ofrecen de nuevo
            var yaEnFactura = {};
            detalles.forEach(function(d) {
                if (d.ordenes_compra_detalle_id) yaEnFactura[d.ordenes_compra_detalle_id] = true;
            });
            ocLineasActuales = (res.data.lineas || []).filter(function(l) {
                return !yaEnFactura[l.ordenes_compra_detalle_id];
            });

            var html = '';
            ocLineasActuales.forEach(function(l, i) {
                var pendiente = parseFloat(l.pendiente) || 0;
                var marcada = pendiente > 0;
                html += `<tr class="${marcada ? '' : 'text-muted'}">
                    <td class="text-center"><input type="checkbox" class="form-check-input oc-linea-check" data-i="${i}" ${marcada ? 'checked' : ''}></td>
                    <td>${escapeHtml(String(l.producto_codigo || ''))} - ${escapeHtml(l.producto_nombre || '')}</td>
                    <td class="text-end">${formatNumber(l.cantidad, 2)}</td>
                    <td class="text-end">${formatNumber(l.cantidad_recibida, 2)}</td>
                    <td class="text-end">${formatNumber(l.en_otras_facturas, 2)}</td>
                    <td class="text-end fw-bold">${formatNumber(pendiente, 2)}</td>
                    <td><input type="number" class="form-control form-control-sm no-spinner oc-linea-cantidad" data-i="${i}" step="0.01" min="0.01" value="${pendiente > 0 ? pendiente : ''}"></td>
                    <td><input type="number" class="form-control form-control-sm no-spinner oc-linea-precio" data-i="${i}" step="0.01" min="0" value="${l.precio_unitario > 0 ? l.precio_unitario : ''}"></td>
                </tr>`;
            });
            if (ocLineasActuales.length === 0) {
                html = '<tr><td colspan="8" class="text-center text-muted p-3">Todas las líneas de esta OC ya están en la factura</td></tr>';
            }
            $('#oc_tabla_lineas').html(html);
            $('#oc_check_todos').prop('checked', true);
            $('#oc_lineas_titulo').text('OC N° ' + (ocSeleccionada.comprobante_nro || ocSeleccionada.orden_compra_id)
                + ' — ' + (ocSeleccionada.estado_registro || ''));
            $('#oc_paso_ordenes').addClass('d-none');
            $('#oc_paso_lineas').removeClass('d-none');
            $('#btnOcAgregarLineas').toggleClass('d-none', ocLineasActuales.length === 0);
        }, 'json').fail(function() {
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor' });
        });
    }

    $('#oc_check_todos').on('change', function() {
        $('.oc-linea-check').prop('checked', $(this).is(':checked'));
    });

    $('#btnOcAgregarLineas').on('click', function() {
        if (!ocSeleccionada) return;
        var descuentoGeneralPct = parseFloat($('#descuento_general_pct').val()) || 0;
        var nuevas = [];
        var errores = [];

        $('.oc-linea-check:checked').each(function() {
            var i = $(this).data('i');
            var l = ocLineasActuales[i];
            var cantidad = parseFloat($('.oc-linea-cantidad[data-i="' + i + '"]').val()) || 0;
            var precio = parseFloat($('.oc-linea-precio[data-i="' + i + '"]').val()) || 0;
            var nombre = (l.producto_codigo || '') + ' - ' + (l.producto_nombre || '');
            if (cantidad <= 0) { errores.push(nombre + ': cantidad'); return; }
            if (precio <= 0) { errores.push(nombre + ': precio'); return; }

            // Mismo cálculo que #btnAgregarProducto
            var iva = parseFloat(l.iva_porcentaje) || 0;
            var noGravado = parseFloat(l.no_gravado) || 0;
            var exento = parseFloat(l.exento) || 0;
            var netoBase = cantidad * precio;
            var descuentoGeneral = netoBase * (descuentoGeneralPct / 100);
            var netoGravado = netoBase - descuentoGeneral;
            var ivaImporte = netoGravado * (iva / 100);

            nuevas.push($.extend({
                detalle_idx: 'temp_' + new Date().getTime() + '_' + i,
                factura_proveedor_detalle_id: 0,
                ordenes_compra_detalle_id: l.ordenes_compra_detalle_id,
                producto_id: parseInt(l.producto_id),
                producto_nombre: nombre,
                cantidad: cantidad,
                precio_unitario: precio,
                descuento_item_pct: 0,
                descuento_general_pct: descuentoGeneralPct,
                descuento_item: 0,
                descuento_general: descuentoGeneral,
                descuento: descuentoGeneral,
                no_gravado: noGravado,
                exento: exento,
                iva_alicuota_id: parseInt(l.iva_alicuota_id) || obtenerIdIva(iva),
                iva_porcentaje: iva,
                neto_gravado: netoGravado,
                iva_importe: ivaImporte,
                total_linea: netoGravado + ivaImporte + noGravado + exento
            }, CPRE && l.presentacion_id ? CPRE.fotoDesde(l, cantidad, precio) : {})); // hereda la presentación de la OC
        });

        if (errores.length > 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Revise las líneas marcadas',
                html: 'Cantidad y precio tienen que ser mayores a 0:<br><small>' + errores.map(escapeHtml).join('<br>') + '</small>'
            });
            return;
        }
        if (nuevas.length === 0) {
            Swal.fire({ icon: 'info', title: 'Sin líneas', text: 'Marque al menos una línea para agregar' });
            return;
        }

        detalles = detalles.concat(nuevas);
        setOrdenCompraVinculada(ocSeleccionada.orden_compra_id, ocSeleccionada.comprobante_nro, proveedorActualId);
        renderizarDetalles();
        actualizarTotales();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalImportarOC')).hide();

        Swal.fire({
            icon: 'success',
            title: nuevas.length + ' línea(s) agregadas de la OC',
            showConfirmButton: false,
            timer: 1500,
            toast: true,
            position: 'top-end'
        });
    });

    // Rechazo de una transición. Al confirmar se explica por qué no se confirmó: el stock, el asiento
    // y la aplicación de NC van en la misma transacción, así que si algo falla la factura queda como estaba.
    function mostrarErrorConfirmacion(accionJs, comprobanteInfo, error) {
        if (accionJs !== 'confirmar') {
            Swal.fire({ icon: "error", title: "Error", text: error, confirmButtonText: "Entendido" });
            return;
        }
        var esAsiento = /asiento contable/i.test(error);
        var motivo = escapeHtml(error.replace(/^No se pudo generar el asiento contable:\s*/i, ''));
        var html = '<p class="mb-2">La factura <strong>' + escapeHtml(comprobanteInfo || '') + '</strong> sigue en su estado anterior: no se movió stock ni se generaron asientos.</p>'
                 + '<div class="alert alert-warning text-start small mb-2"><strong>' + (esAsiento ? 'No se pudo generar el asiento contable' : 'Motivo') + ':</strong><br>' + motivo + '</div>'
                 + (esAsiento
                    ? '<p class="small text-muted text-start mb-0">Completá la configuración contable indicada (cuenta del proveedor, del producto o de su tipo, de la alícuota de IVA o del impuesto) y volvé a confirmar.</p>'
                    : '');
        Swal.fire({ icon: "warning", title: "La factura no se confirmó", html: html, confirmButtonText: "Entendido", width: 600 });
    }

    // ========== SOLAPA ASIENTO CONTABLE ==========
    function limpiarAsientoProveedor() {
        $('#badgeAsientoProv').addClass('d-none').text('');
        $('#asientoProvDetalle').hide();
        $('#asientoProvVacio').show();
        $('#tablaAsientoProv tbody').empty();
    }

    function cargarAsientoProveedor(facturaId) {
        limpiarAsientoProveedor();
        if (!facturaId) return;
        $.getJSON('facturas_proveedores_ajax.php', { accion: 'obtener_asiento', factura_proveedor_id: facturaId, empresa_idx: empresa_idx }, function (res) {
            if (!res || !res.success || !res.data) return;
            var a = res.data;
            $('#badgeAsientoProv').removeClass('d-none').text('#' + a.cont_asiento_id);
            $('#asientoProvId').text(a.cont_asiento_id);
            $('#asientoProvTipo').text(a.cont_tipo_asiento || 'Automático');
            $('#asientoProvEstado').text(a.estado_registro || '');
            $('#asientoProvFecha').text(a.f_asiento ? Fmt.fecha(a.f_asiento) : '-');
            $('#asientoProvDescripcion').text(a.descripcion || '');
            var filas = '';
            (a.detalles || []).forEach(function (d) {
                var debe = parseFloat(d.debe) || 0, haber = parseFloat(d.haber) || 0;
                filas += '<tr><td class="font-monospace text-muted">' + escapeHtml(d.cuenta_codigo || '-') + '</td>'
                       + '<td class="fw-semibold">' + escapeHtml(d.cuenta_nombre || ('Cuenta ' + d.cuenta_id)) + '</td>'
                       + '<td class="text-end font-monospace ' + (debe > 0 ? 'fw-bold text-success' : 'text-muted') + '">' + (debe > 0 ? '$' + formatNumber(debe, 2) : '-') + '</td>'
                       + '<td class="text-end font-monospace ' + (haber > 0 ? 'fw-bold text-danger' : 'text-muted') + '">' + (haber > 0 ? '$' + formatNumber(haber, 2) : '-') + '</td>'
                       + '<td class="small text-muted">' + escapeHtml(d.descripcion || '') + '</td></tr>';
            });
            $('#tablaAsientoProv tbody').html(filas);
            var td = parseFloat(a.total_debe) || 0, th = parseFloat(a.total_haber) || 0;
            $('#asientoProvTotalDebe').text('$' + formatNumber(td, 2));
            $('#asientoProvTotalHaber').text('$' + formatNumber(th, 2));
            $('#asientoProvBalance').html(Math.abs(td - th) < 0.01
                ? '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Balanceado</span>'
                : '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>Desbalanceado: $' + formatNumber(Math.abs(td - th), 2) + '</span>');
            $('#asientoProvVacio').hide();
            $('#asientoProvDetalle').show();
        });
    }

    // ========== NOTAS DE CRÉDITO / DÉBITO: COMPROBANTE ASOCIADO ==========
    // El combo aparece solo para tipos NC/ND (es_nota) y con proveedor elegido.
    function actualizarComprobanteAsociado(seleccionado) {
        var tipo = $('#comprobante_tipo_id').val();
        var wrap = $('#asociado_wrap'), sel = $('#comprobante_asociado_id');
        if (!tipo || !proveedorActualId) { wrap.addClass('d-none'); sel.html('<option value="">Sin comprobante asociado</option>'); return; }
        $.getJSON('facturas_proveedores_ajax.php', { accion: 'comprobantes_asociables', entidad_id: proveedorActualId, comprobante_tipo_id: tipo, empresa_idx: empresa_idx }, function (res) {
            if (!res || !res.success || !res.es_nota) { wrap.addClass('d-none'); sel.html('<option value="">Sin comprobante asociado</option>'); return; }
            var html = '<option value="">Sin comprobante asociado</option>';
            (res.data || []).forEach(function (c) {
                var nro = String(c.comprobante_pv).padStart(4, '0') + '-' + String(c.comprobante_nro).padStart(8, '0');
                var s = String(c.comprobante_id) === String(seleccionado || '') ? ' selected' : '';
                html += '<option value="' + c.comprobante_id + '"' + s + '>' + escapeHtml(c.comprobante_tipo) + ' ' + nro + ' · total $' + formatNumber(c.importe_total, 2)
                      + ' · saldo $' + formatNumber(c.importe_pendiente, 2) + '</option>';
            });
            sel.html(html).prop('disabled', $('#comprobante_tipo_id').prop('disabled'));
            wrap.removeClass('d-none');
        });
    }
    // ========== DÉBITO DIRECTO EN CUENTA BANCARIA ==========
    // Resumen bancario (tipo con requiere_debito_cuenta) o factura / ND debitada automáticamente: al confirmar se
    // cancela con una OP automática desde la cuenta elegida. Solo comprobantes a pagar (signo +).
    $.getJSON('facturas_proveedores_ajax.php', { accion: 'obtener_cuentas_debito', empresa_idx: empresa_idx }, function (res) {
        var html = '<option value="">Sin débito en cuenta (se paga con OP)</option>';
        ((res && res.data) || []).forEach(function (c) {
            html += '<option value="' + c.tesoreria_cuenta_id + '">' + escapeHtml(c.tesoreria_cuenta) + '</option>';
        });
        var actual = $('#tesoreria_cuenta_debito_id').val();
        $('#tesoreria_cuenta_debito_id').html(html).val(actual || '');
    });
    window.actualizarDebitoCuenta = function () {
        var opt = $('#comprobante_tipo_id option:selected');
        var wrap = $('#debito_wrap'), sel = $('#tesoreria_cuenta_debito_id');
        if (!opt.val() || String(opt.data('signo')) !== '+') { wrap.addClass('d-none'); sel.val('').prop('required', false); return; }
        var requiere = String(opt.data('debito')) === '1';
        sel.prop('required', requiere);
        $('#debito_ayuda').text(requiere ? '(obligatorio: cuenta del resumen)' : '(opcional: débito automático)');
        wrap.removeClass('d-none');
    };
    $('#comprobante_tipo_id').on('change', function () { actualizarComprobanteAsociado(); actualizarDebitoCuenta(); });
    $('#entidad_combo').on('change', function () { setTimeout(function () { actualizarComprobanteAsociado(); }, 0); });

    // Al confirmar una factura/ND con notas de crédito con saldo del proveedor: ofrecer imputarlas (OP en $0)
    function ofrecerNotasCredito(facturaId, disp) {
        if (!disp || !disp.notas || !disp.notas.length) return;
        var filas = disp.notas.map(function (n, i) {
            var nro = String(n.comprobante_pv).padStart(4, '0') + '-' + String(n.comprobante_nro).padStart(8, '0');
            return '<div class="form-check text-start"><input class="form-check-input nc-aplicar" type="checkbox" checked id="ncp_ap_' + i + '" value="' + n.comprobante_id + '">'
                 + '<label class="form-check-label" for="ncp_ap_' + i + '">' + escapeHtml(n.comprobante_tipo) + ' ' + nro + ' · saldo $' + formatNumber(n.importe_pendiente, 2) + '</label></div>';
        }).join('');
        Swal.fire({
            icon: 'question',
            title: 'El proveedor tiene notas de crédito con saldo',
            html: '<p class="mb-2">Saldo de la factura: <strong>$' + formatNumber(disp.saldo, 2) + '</strong>. ¿Imputar estas notas de crédito? Se genera una OP en $0.</p>' + filas,
            showCancelButton: true, confirmButtonText: 'Imputar', cancelButtonText: 'Ahora no',
            preConfirm: function () {
                var apl = $('.nc-aplicar:checked').map(function () { return { nc_comprobante_id: parseInt(this.value, 10), importe: 0 }; }).get();
                if (!apl.length) { Swal.showValidationMessage('Marcá al menos una nota de crédito'); return false; }
                return $.post('facturas_proveedores_ajax.php', { accion: 'aplicar_notas_credito', factura_proveedor_id: facturaId, empresa_idx: empresa_idx, aplicaciones: JSON.stringify(apl) }, null, 'json')
                    .then(function (r) { if (!r || !r.success) { Swal.showValidationMessage((r && r.error) || 'No se pudo imputar'); return false; } return r; },
                          function () { Swal.showValidationMessage('No se pudo conectar con el servidor'); return false; });
            }
        }).then(function (result) {
            if (result.isConfirmed) {
                tabla.ajax.reload(null, false);
                Swal.fire({ icon: 'success', title: 'Notas de crédito imputadas', text: result.value.message });
            }
        });
    }

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
            cargarProveedoresYSucursales(function() {
                if (res.entidad_id) {
                    seleccionarProveedorEnCombo(res.entidad_id, res.entidad_sucursal_id);
                }
            });
            
            $('#factura_proveedor_id').val(res.factura_proveedor_id);
            $('#comprobante_nro').val(res.comprobante_nro);
            $('#comprobante_pv').val(res.comprobante_pv || '0');
            $('#f_emision').val(res.f_emision);
            $('#f_vencimiento').val(res.f_vencimiento);
            $('#f_contabilidad').val(res.f_contabilidad || res.f_emision);
            $('#direccion').val(res.direccion);
            $('#observaciones').val(res.observaciones);
            $('#tipo_cambio').val(res.tipo_cambio || '1.000000');
            $('#modalidad_descuento_factura').val(res.modalidad_descuento_factura || 'EN_FACTURA_GLOBAL');
            $('#precio_compra_contempla_descuento').val(res.precio_compra_contempla_descuento !== undefined && res.precio_compra_contempla_descuento !== null ? res.precio_compra_contempla_descuento : 1);
            $('#descuento_nc_pct').val(res.descuento_nc_pct || 0);
            if (res.modalidad_descuento_factura === 'NOTA_CREDITO') {
                $('#col_descuento_nc').show();
            } else {
                $('#col_descuento_nc').hide();
            }
            $('#subtotal').val(res.subtotal || 0);
            $('#descuentos').val(res.descuentos || 0);
            $('#no_gravado').val(res.no_gravado || 0);
            $('#exento').val(res.exento || 0);
            $('#impuestos').val(res.impuestos || 0);
            $('#total').val(res.total || 0);
            
            $('#total_neto_display').text(Fmt.numero(parseFloat(res.subtotal || 0), 2));
            $('#descuentos_display').text(Fmt.numero(parseFloat(res.descuentos || 0), 2));
            $('#no_gravado_display').text(Fmt.numero(parseFloat(res.no_gravado || 0), 2));
            $('#exento_display').text(Fmt.numero(parseFloat(res.exento || 0), 2));
            $('#impuestos_display').text(Fmt.numero(parseFloat(res.impuestos || 0), 2));
            $('#total_display').text(Fmt.numero(parseFloat(res.total || 0), 2));

            $('#modalLabel').text('Visualizar Factura de Proveedor');
            setOrdenCompraVinculada(res.orden_compra_id, res.orden_compra_nro, res.entidad_id);
            if (res.certificacion_id) {
                setCertificacionVinculada(res.certificacion_id, res.nro_certificado, res.cert_periodo_mes, res.cert_monto_neto);
            } else {
                quitarVinculoCert();
            }

            // Asignar valores después de que los combos se hayan cargado
            setTimeout(function() {
                $('#comprobante_tipo_id').val(res.comprobante_tipo_id);
                // El proveedor se fija más abajo en este mismo bloque: el combo de comprobante asociado se carga después
                setTimeout(function () { actualizarComprobanteAsociado(res.comprobante_asociado_id); }, 50);
                $('#tesoreria_cuenta_debito_id').val(res.tesoreria_cuenta_debito_id || ''); actualizarDebitoCuenta();
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
                    seleccionarProveedorEnCombo(res.entidad_id, res.entidad_sucursal_id);
                }
                if (res.deposito_id) {
                    $('#deposito_id').val(res.deposito_id);
                }
                // Cargar detalles si existen
                if (res.detalles && res.detalles.length > 0) {
                    detalles = res.detalles.map(function(detalle, index) {
                        return $.extend({
                            detalle_idx: index,
                            factura_proveedor_detalle_id: detalle.factura_proveedor_detalle_id,
                            ordenes_compra_detalle_id: detalle.ordenes_compra_detalle_id || null,
                            producto_id: detalle.producto_id,
                            producto_nombre: detalle.producto_nombre,
                            producto_codigo: detalle.producto_codigo || '',
                            codigo_proveedor: detalle.codigo_proveedor || '',
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
                        }, CPRE ? CPRE.foto(detalle) : {});
                    });
                    renderizarDetalles();
                    actualizarTotales();
                }
                
                // Cargar impuestos de la factura si existen
                if (res.factura_proveedor_id) {
                    cargarImpuestosFactura(res.factura_proveedor_id);
                }

                // --- MODO SOLO LECTURA ---
                // Deshabilitar todos los inputs, selects y botones de acción dentro del modal
                $('#formFacturaProveedor :input').prop('disabled', true);
                // Las solapas son <button> dentro del form: quedaban deshabilitadas y no se podía ver Productos
                $('#facturaTabs .nav-link').prop('disabled', false);
                cargarAsientoProveedor(res.factura_proveedor_id);
                $('.btn-eliminar-detalle, .btn-editar-detalle, #btnAgregarProducto, #btnNuevoProductoRapido, #btnNuevoProveedor').prop('disabled', true);
                
                // OCULTAR botones de acción (en lugar de ocultar secciones enteras)
                $('#btnAgregarProducto').hide();
                $('#btnNuevoProductoRapido').hide();
                $('#btnAgregarImpuesto').hide();
                $('#btnNuevoProductoRapido').hide();
                $('#btnImportarOC, #btnDesvincularOC, #btnImportarCertificacion, #btnDesvincularCert').hide();
                
                // Ocultar el botón guardar y el cancelar de abajo (se cierra con la X del encabezado)
                $('#btnGuardar').hide();
                $('#btnCancelarFactura').hide();
                // Cambiar texto del botón Cancelar a "Cerrar"
                $('.modal-footer .btn-secondary').text('Cerrar');
                
                // Mantener habilitado el fullscreen
                $('#btnToggleFullscreen').prop('disabled', false);
                
                // --- PERMITIR NAVEGAR ENTRE PESTAÑAS ---
                // Las pestañas ya están habilitadas por defecto, solo deshabilitamos interacciones dentro de ellas
                
            }, 500);

            var modal = new bootstrap.Modal(document.getElementById('modalFacturaProveedor'));
            modal.show();

            // Al cerrar el modal, restaurar estado (para próximas aperturas)
            $('#modalFacturaProveedor').off('hidden.bs.modal').on('hidden.bs.modal', function () {
                // Restaurar habilitación y mostrar botones
                $('#formFacturaProveedor :input').prop('disabled', false);
                $('#btnGuardar').show();
                $('#btnCancelarFactura').show();
                $('.modal-footer .btn-secondary').text('Cancelar');
                $('.btn-eliminar-detalle, .btn-editar-detalle, #btnAgregarProducto, #btnNuevoProductoRapido, #btnNuevoProveedor').prop('disabled', false);
                
                // Restaurar visibilidad de botones
                $('#btnAgregarProducto').show();
                $('#btnNuevoProductoRapido').show();
                $('#btnAgregarImpuesto').show();
                $('#btnImportarOC, #btnDesvincularOC, #btnImportarCertificacion, #btnDesvincularCert').show();
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

    $(document).on('change', '#modalidad_descuento_factura', function() {
        var mod = $(this).val();
        if (mod === 'NOTA_CREDITO') {
            $('#col_descuento_nc').show();
            var valActual = parseFloat($('#descuento_general_pct').val()) || 0;
            if (valActual > 0) {
                $('#descuento_nc_pct').val(valActual);
                $('#descuento_general_pct').val(0).trigger('change');
            }
        } else {
            $('#col_descuento_nc').hide();
            if (mod === 'EN_FACTURA_GLOBAL') {
                var ncVal = parseFloat($('#descuento_nc_pct').val()) || 0;
                if (ncVal > 0) {
                    $('#descuento_general_pct').val(ncVal).trigger('change');
                    $('#descuento_nc_pct').val(0);
                }
            } else if (mod === 'EN_FACTURA_ITEM' || mod === 'SIN_DESCUENTO') {
                $('#descuento_nc_pct').val(0);
                $('#descuento_general_pct').val(0).trigger('change');
            }
        }
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
        } else if (accionJs === 'anular') {
            // Anular una confirmada: revierte stock, asiento, imputaciones y la OP automática; pide el motivo
            Swal.fire({
                title: '¿Anular el comprobante?',
                html: '<strong>' + escapeHtml(comprobanteInfo) + '</strong><br><small class="text-muted">Proveedor: ' + escapeHtml(proveedorInfo) + '</small>'
                    + '<p class="small text-start mt-2 mb-0">Se revierten el stock y el asiento. Los pagos o notas de crédito que lo cancelaban quedan con saldo a favor; '
                    + 'la OP automática (contado / débito en cuenta) se anula y devuelve la caja o el banco.</p>',
                icon: 'warning',
                input: 'textarea',
                inputPlaceholder: 'Motivo de la anulación',
                inputValidator: function (v) { return (!v || !v.trim()) ? 'Ingrese el motivo' : null; },
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, anular',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                allowOutsideClick: false
            }).then(function (result) {
                if (result.isConfirmed) ejecutarAccion(facturaId, accionJs, comprobanteInfo, result.value.trim());
            });
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
    
    $(document).on('click', '.btn-generar-asiento', function() {
        var facturaId = $(this).data('id');
        var $btn = $(this);
        
        console.log("Generar asiento para factura ID:", facturaId);
        
        Swal.fire({
            title: '¿Generar asiento contable?',
            html: `Se generará o actualizará el asiento contable para esta factura.<br><br>
                <strong>El asiento se creará con los siguientes movimientos:</strong><br>
                <ul class="text-start">
                    <li><span class="text-primary">DEBE</span> - Cuenta de Producto (Compra)</li>
                    <li><span class="text-primary">DEBE</span> - Cuenta de IVA (Crédito Fiscal)</li>
                    <li><span class="text-danger">HABER</span> - Cuenta del Proveedor (Acreedor)</li>
                </ul>
                <small class="text-muted">Si ya existe un asiento, se actualizará automáticamente.</small>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#d33',
            confirmButtonText: '<i class="fas fa-book me-1"></i>Sí, generar asiento',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Generando asiento...',
                    text: 'Por favor espere',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                $.ajax({
                    url: 'facturas_proveedores_ajax.php',
                    type: 'POST',
                    data: {
                        accion: 'generar_asiento_contable',
                        factura_proveedor_id: facturaId,
                        empresa_idx: empresa_id
                    },
                    dataType: 'json',
                    success: function(res) {
                        console.log("Respuesta generar asiento:", res);
                        Swal.close();
                        
                        if (res.success) {
                            Swal.fire({
                                icon: "success",
                                title: "¡Asiento generado!",
                                html: `${res.message}<br><br>
                                    <strong>ID del Asiento:</strong> ${res.asiento_id}<br>
                                    <small class="text-muted">Puede verlo en el módulo de Asientos Contables</small>`,
                                confirmButtonText: "Entendido"
                            });
                            
                            // Opcional: recargar la tabla para mostrar cambios
                            tabla.ajax.reload(null, false);
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                html: res.message || "Error al generar el asiento contable",
                                confirmButtonText: "Entendido"
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error AJAX:", error);
                        Swal.close();
                        Swal.fire({
                            icon: "error",
                            title: "Error de conexión",
                            text: "Error al comunicarse con el servidor: " + error,
                            confirmButtonText: "Entendido"
                        });
                    }
                });
            }
        });
    });
    // Función para ejecutar cualquier acción del backend
    function ejecutarAccion(facturaId, accionJs, comprobanteInfo, motivo) {
        var savedState = {
            page: tabla.page(),
            order: tabla.order(),
            search: tabla.search()
        };

        $.post('facturas_proveedores_ajax.php', {
            accion: 'ejecutar_accion',
            factura_proveedor_id: facturaId,
            accion_js: accionJs,
            motivo: motivo || '',
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

                    if (res.notas_credito_disponibles) {
                        ofrecerNotasCredito(facturaId, res.notas_credito_disponibles);
                        return;
                    }
                    Swal.fire({
                        icon: "success",
                        title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                        text: res.message || `Factura "${comprobanteInfo}" actualizada correctamente`,
                        showConfirmButton: false,
                        timer: 4000,
                        toast: true,
                        position: 'top-end'
                    });
                }, false);
            } else {
                mostrarErrorConfirmacion(accionJs, comprobanteInfo, res.error || `Error al ${accionJs} la factura`);
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
        $(this).removeData('codigos');
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
                            var texto = item.producto_codigo + ' - ' + item.producto_nombre;
                            var vinculado = parseInt(item.vinculado) === 1;
                            resultadosDiv.append(
                                `<a href="#" class="list-group-item list-group-item-action"
                                   data-index="${index}"
                                   data-id="${item.producto_id}"
                                   data-iva-id="${item.iva_alicuota_id}"
                                   data-iva="${item.iva_porcentaje || 21}"
                                   data-vinculado="${vinculado ? 1 : 0}"
                                   data-texto="${escapeHtml(texto)}"
                                   data-codigo="${escapeHtml(String(item.producto_codigo || ''))}"
                                   data-codigo-proveedor="${escapeHtml(String(vinculado ? (item.codigo_proveedor || '') : ''))}">
                                    <strong>${escapeHtml(String(item.producto_codigo))}</strong> - ${escapeHtml(item.producto_nombre)}
                                    ${vinculado
                                        ? (item.codigo_proveedor ? '<br><small>Código Proveedor: ' + escapeHtml(item.codigo_proveedor) + '</small>' : '')
                                        : '<span class="badge bg-warning text-dark ms-1">No vinculado al proveedor</span>'}
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
        $('#resultados_busqueda').hide();
        selectedIndex = -1;

        if (parseInt(item.data('vinculado')) === 1) {
            seleccionarProductoBusqueda(item);
            return;
        }

        var modalEl = document.getElementById('modalFacturaProveedor');
        var modalInst = modalEl ? (bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl)) : null;

        // Producto que el proveedor no tiene vinculado: ofrecer cargar su código
        Swal.fire({
            icon: 'question',
            title: 'Producto no vinculado al proveedor',
            html: `<p class="mb-2"><strong>${escapeHtml(String(item.data('texto')))}</strong></p>
                   <p class="mb-0 small">Ingrese el código que usa este proveedor para vincularlo, o continúe sin vincular.</p>`,
            input: 'text',
            inputPlaceholder: 'Código del proveedor',
            inputAttributes: { maxlength: 50 },
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: 'Vincular y usar',
            denyButtonText: 'Usar sin vincular',
            cancelButtonText: 'Cancelar',
            showLoaderOnConfirm: true,
            didOpen: function() {
                if (modalInst && modalInst._focustrap) {
                    modalInst._focustrap.deactivate();
                }
                $(document).off('focusin.bs.modal');
                if (modalEl) {
                    modalEl.removeAttribute('tabindex');
                }
                var input = Swal.getInput();
                if (input) {
                    setTimeout(function() {
                        input.focus();
                    }, 50);
                }
            },
            willClose: function() {
                if (modalEl) {
                    modalEl.setAttribute('tabindex', '-1');
                }
                if (modalInst && modalInst._focustrap) {
                    modalInst._focustrap.activate();
                }
            },
            inputValidator: function(valor) {
                if (!valor || !valor.trim()) {
                    return 'Ingrese el código del proveedor';
                }
            },
            preConfirm: function(codigo) {
                return $.post('facturas_proveedores_ajax.php', {
                    accion: 'vincular_producto_proveedor',
                    producto_id: item.data('id'),
                    entidad_id: proveedorActualId,
                    codigo_proveedor: codigo.trim(),
                    empresa_idx: empresa_idx
                }, null, 'json').then(function(res) {
                    if (!res || !res.success) {
                        Swal.showValidationMessage((res && (res.message || res.error)) || 'No se pudo vincular el producto');
                        return false;
                    }
                    return res;
                }, function() {
                    Swal.showValidationMessage('No se pudo conectar con el servidor');
                    return false;
                });
            },
            allowOutsideClick: function() { return !Swal.isLoading(); }
        }).then(function(result) {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Producto vinculado',
                    showConfirmButton: false,
                    timer: 1500,
                    toast: true,
                    position: 'top-end'
                });
                seleccionarProductoBusqueda(item);
            } else if (result.isDenied) {
                seleccionarProductoBusqueda(item);
            } else {
                $('#busqueda_producto').focus();
            }
        });
    });

    function seleccionarProductoBusqueda(item) {
        var productoId = item.data('id');
        var iva = item.data('iva');
        var ivaId = item.data('iva-id');
        var textoCompleto = String(item.data('texto') || item.text().trim());

        $('#busqueda_producto').val(textoCompleto);
        $('#producto_seleccionado_id').val(productoId);
        $('#producto_iva').val(iva);
        $('#producto_iva_id').val(ivaId);
        $('#busqueda_producto').data('codigos', { producto_codigo: String(item.attr('data-codigo') || ''), codigo_proveedor: String(item.attr('data-codigo-proveedor') || '') });
        
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
            if (CPRE) CPRE.cargar(productoId);
        }, 'json');
    }

    // Ocultar resultados al hacer clic fuera
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#busqueda_producto, #resultados_busqueda, #btnModalSelectorProductos').length) {
            $('#resultados_busqueda').hide();
            selectedIndex = -1;
        }
    });

    // ========== MODAL SELECTOR DE PRODUCTOS (CATÁLOGO) ==========
    var catalogoProductosCache = [];

    $('#btnModalSelectorProductos').on('click', function() {
        if (!proveedorActualId) {
            Swal.fire({
                icon: 'warning',
                title: 'Proveedor requerido',
                text: 'Por favor, seleccione un proveedor antes de elegir productos.',
                confirmButtonText: 'Entendido'
            });
            return;
        }

        $('#modalSelectorProductos').modal('show');
        cargarCatalogoProductosModal();
    });

    function cargarCatalogoProductosModal() {
        var tbody = $('#tbodyModalProductos');
        tbody.html('<tr><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-1"></i> Cargando catálogo de productos...</td></tr>');
        $('#filtroModalProductos').val('');

        $.ajax({
            url: 'facturas_proveedores_ajax.php',
            type: 'GET',
            data: {
                accion: 'obtener_catalogo_productos',
                entidad_id: proveedorActualId,
                empresa_idx: empresa_idx
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && Array.isArray(response.data)) {
                    catalogoProductosCache = response.data;
                    poblarCategoriasModal(catalogoProductosCache);
                    renderizarTablaProductosModal(catalogoProductosCache);
                } else {
                    tbody.html('<tr><td colspan="6" class="text-center py-3 text-danger">No se pudo cargar el catálogo</td></tr>');
                }
            },
            error: function() {
                tbody.html('<tr><td colspan="6" class="text-center py-3 text-danger">Error al conectar con el servidor</td></tr>');
            }
        });
    }

    function poblarCategoriasModal(productos) {
        var categorias = {};
        productos.forEach(function(p) {
            if (p.categoria_nombre) {
                categorias[p.categoria_nombre] = (categorias[p.categoria_nombre] || 0) + 1;
            }
        });

        var select = $('#filtroModalCategoria');
        select.find('option:not(:first)').remove();

        Object.keys(categorias).sort().forEach(function(cat) {
            select.append(`<option value="${escapeHtml(cat)}">${escapeHtml(cat)} (${categorias[cat]})</option>`);
        });
    }

    function renderizarTablaProductosModal(productos) {
        var tbody = $('#tbodyModalProductos');
        tbody.empty();

        if (productos.length === 0) {
            tbody.html('<tr><td colspan="6" class="text-center py-4 text-muted">No se encontraron productos o gastos</td></tr>');
            $('#txtTotalProductosModal').text('0 productos encontrados');
            return;
        }

        $('#txtTotalProductosModal').text(productos.length + ' producto(s) encontrado(s)');

        productos.forEach(function(item) {
            var vinculado = parseInt(item.vinculado) === 1;
            var esServicio = parseInt(item.es_servicio) === 1;
            var badTipo = esServicio ? '<span class="badge bg-info text-dark ms-1">Servicio</span>' : '<span class="badge bg-secondary ms-1">Bien</span>';
            var ctaInfo = item.cuenta_codigo ? `<code>${escapeHtml(item.cuenta_codigo)}</code> <small class="text-muted d-block text-truncate" style="max-width: 140px;" title="${escapeHtml(item.cuenta_nombre)}">${escapeHtml(item.cuenta_nombre)}</small>` : '<span class="text-muted small">-</span>';

            var tr = $(`
                <tr style="cursor: pointer;">
                    <td>
                        <strong class="text-primary">${escapeHtml(item.producto_codigo)}</strong>
                        ${vinculado && item.codigo_proveedor ? `<br><small class="text-success"><i class="fas fa-link me-1"></i>${escapeHtml(item.codigo_proveedor)}</small>` : ''}
                    </td>
                    <td>
                        <span class="fw-semibold">${escapeHtml(item.producto_nombre)}</span>
                        ${badTipo}
                    </td>
                    <td><span class="badge bg-light text-dark border">${escapeHtml(item.categoria_nombre || 'General')}</span></td>
                    <td>${ctaInfo}</td>
                    <td class="text-center"><span class="badge bg-light text-secondary border">${item.iva_porcentaje}%</span></td>
                    <td class="text-center">
                        <button type="button" class="btn btn-xs btn-primary btn-seleccionar-prod-modal" title="Seleccionar">
                            <i class="fas fa-check me-1"></i>Elegir
                        </button>
                    </td>
                </tr>
            `);

            tr.find('.btn-seleccionar-prod-modal').add(tr).on('click', function(e) {
                e.stopPropagation();
                aplicarSeleccionProductoModal(item);
            });

            tbody.append(tr);
        });
    }

    function aplicarSeleccionProductoModal(item) {
        var textoCompleto = item.producto_codigo + ' - ' + item.producto_nombre;
        $('#busqueda_producto').val(textoCompleto);
        $('#producto_seleccionado_id').val(item.producto_id);
        $('#producto_iva').val(item.iva_porcentaje);
        $('#producto_iva_id').val(item.iva_alicuota_id);
        $('#busqueda_producto').data('codigos', {
            producto_codigo: String(item.producto_codigo || ''),
            codigo_proveedor: String(item.codigo_proveedor || '')
        });

        $('#modalSelectorProductos').modal('hide');

        // Obtener último precio si aplica
        $.get('facturas_proveedores_ajax.php', {
            accion: 'obtener_ultimo_precio',
            producto_id: item.producto_id,
            entidad_id: proveedorActualId,
            empresa_idx: empresa_idx
        }, function(res) {
            if (res.success && res.precio) {
                $('#producto_precio').val(res.precio);
            }
            calcularDescuentos();
            if (CPRE) CPRE.cargar(item.producto_id);
        }, 'json');

        $('#producto_cantidad').focus().select();
    }

    // Filtros en vivo en el modal
    $('#filtroModalProductos, #filtroModalCategoria').on('input change', function() {
        var texto = $('#filtroModalProductos').val().toLowerCase().trim();
        var cat = $('#filtroModalCategoria').val();

        var filtrados = catalogoProductosCache.filter(function(p) {
            var coincideCat = !cat || p.categoria_nombre === cat;
            if (!coincideCat) return false;

            if (!texto) return true;

            var cod = (p.producto_codigo || '').toLowerCase();
            var nom = (p.producto_nombre || '').toLowerCase();
            var ctaCod = (p.cuenta_codigo || '').toLowerCase();
            var ctaNom = (p.cuenta_nombre || '').toLowerCase();
            var provCod = (p.codigo_proveedor || '').toLowerCase();

            return cod.indexOf(texto) !== -1 ||
                   nom.indexOf(texto) !== -1 ||
                   ctaCod.indexOf(texto) !== -1 ||
                   ctaNom.indexOf(texto) !== -1 ||
                   provCod.indexOf(texto) !== -1;
        });

        renderizarTablaProductosModal(filtrados);
    });

    // Calcular descuentos e IVA
    if (CPRE) CPRE.init({ ajaxUrl: 'facturas_proveedores_ajax.php', empresaIdx: empresa_idx, entidad: function () { return proveedorActualId; }, onCambio: function () { calcularDescuentos(); } });

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
    // Alícuotas de IVA de la empresa (id, porcentaje, no gravado / exento): los ids son por empresa
    var alicuotasIva = [];
    $.getJSON('facturas_proveedores_ajax.php', { accion: 'obtener_alicuotas_iva_marcas', empresa_idx: empresa_idx }, function (res) {
        alicuotasIva = (res && res.data) || [];
    });
    function alicuotaIva(id) {
        return alicuotasIva.find(function (a) { return String(a.iva_alicuota_id) === String(id); }) || null;
    }
    function obtenerIdIva(porcentaje) {
        var p = parseFloat(porcentaje) || 0;
        var a = alicuotasIva.find(function (x) { return parseFloat(x.porcentaje) === p && (p > 0 ? parseInt(x.es_gravado) === 1 : parseInt(x.es_no_gravado) === 1); });
        return a ? a.iva_alicuota_id : '';
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
    
    var cantidad = parseFloat($('#producto_cantidad').val()) || 0;
    var precio = parseFloat($('#producto_precio').val()) || 0;
    var descuentoItemPct = parseFloat($('#producto_descuento_item_pct').val()) || 0;
    var descuentoGeneralPct = parseFloat($('#descuento_general_pct').val()) || 0;
    var iva = parseFloat($('#producto_iva').val()) || 0;
    // El id del producto vale si coincide con el porcentaje elegido; si no, el de ese porcentaje en la empresa
    var ivaId = $('#producto_iva_id').val();
    var alicProd = alicuotaIva(ivaId);
    if (!alicProd || parseFloat(alicProd.porcentaje) !== iva) ivaId = obtenerIdIva(iva) || ivaId;
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
    
    // Calcular todos los valores del detalle
    var netoBase = cantidad * precio;
    var descuentoItem = netoBase * (descuentoItemPct / 100);
    var netoDespuesItem = netoBase - descuentoItem;
    var descuentoGeneral = netoDespuesItem * (descuentoGeneralPct / 100);
    var descuentoTotal = descuentoItem + descuentoGeneral;
    var netoGravado = netoBase - descuentoTotal;
    // Alícuota No gravado / Exento: la base va a ese importe, no a neto gravado (Libro IVA)
    var alicLinea = alicuotaIva(ivaId);
    if (alicLinea && parseInt(alicLinea.es_no_gravado) === 1) { noGravado += netoGravado; netoGravado = 0; }
    else if (alicLinea && parseInt(alicLinea.es_exento) === 1) { exento += netoGravado; netoGravado = 0; }
    var ivaImporte = netoGravado * (iva / 100);
    var totalLinea = netoGravado + ivaImporte + noGravado + exento;
    
    var productoText = $('#busqueda_producto').val();

    // Si se re-agrega una línea de OC editada con el mismo producto, conserva el vínculo
    var ocDetalleId = null;
    if (detalleEnEdicionOC && parseInt(detalleEnEdicionOC.producto_id) === parseInt(productoId)) {
        ocDetalleId = detalleEnEdicionOC.ordenes_compra_detalle_id;
    }
    detalleEnEdicionOC = null;

    var nuevoDetalle = {
        ordenes_compra_detalle_id: ocDetalleId,
        detalle_idx: 'temp_' + new Date().getTime(),
        factura_proveedor_detalle_id: 0,
        producto_id: parseInt(productoId),
        producto_nombre: productoText,
        producto_codigo: ($('#busqueda_producto').data('codigos') || {}).producto_codigo || '',
        codigo_proveedor: ($('#busqueda_producto').data('codigos') || {}).codigo_proveedor || '',
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
    
    if (CPRE) nuevoDetalle = CPRE.convertir(nuevoDetalle); // cajas → unidades + foto
    detalles.push(nuevoDetalle);
    renderizarDetalles();
    actualizarTotales();
    if (CPRE) CPRE.limpiar();
    
    // Limpiar campos
    $('#busqueda_producto').val('').removeData('codigos');
    $('#producto_seleccionado_id').val('');
    $('#producto_iva_id').val('');
    $('#producto_cantidad').val('1.00');
    $('#producto_precio').val('');
    $('#producto_descuento_item_pct').val('0');
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
                        <th style="min-width: 90px;">Nuestro cód.</th>
                        <th style="min-width: 90px;">Cód. prov.</th>
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
            // Código nuestro y del proveedor en sus columnas (el nombre puede traer el código: "Nombre (cód.)" o "cód. - Nombre")
            var codigoNuestro = detalle.producto_codigo ? String(detalle.producto_codigo) : '';
            var codigoProveedor = detalle.codigo_proveedor ? String(detalle.codigo_proveedor) : '';
            if (codigoNuestro) {
                var sufijoCodigo = ' (' + codigoNuestro + ')', prefijoCodigo = codigoNuestro + ' - ';
                if (nombreProducto.slice(-sufijoCodigo.length) === sufijoCodigo) nombreProducto = nombreProducto.slice(0, -sufijoCodigo.length);
                else if (nombreProducto.indexOf(prefijoCodigo) === 0) nombreProducto = nombreProducto.slice(prefijoCodigo.length);
            }
            
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
                <td class="text-nowrap">${escapeHtml(codigoNuestro)}</td>
                <td class="text-nowrap">${codigoProveedor ? escapeHtml(codigoProveedor) : '<span class="text-muted">-</span>'}</td>
                <td>
                    <div class="fw-bold">${escapeHtml(nombreProducto.substring(0, 50))}${nombreProducto.length > 50 ? '...' : ''}</div>
                    ${esNuevo ? '<span class="badge bg-info ms-2">Nuevo</span>' : ''}
                    ${detalle.ordenes_compra_detalle_id ? '<span class="badge bg-primary ms-1" title="Línea de la orden de compra vinculada">OC</span>' : ''}
                </td>
                <td class="text-center">${formatNumber(cantidad, 2)}${CPRE ? CPRE.etiqueta(detalle) : ''}</td>
                <td class="text-end">${formatNumber(precioUnitario, 2)}</td>
                <td class="text-center">${Fmt.numero(descuentoItemPct, 2)}%</td>
                <td class="text-end">${formatNumber(descuentoItem, 2)}</td>
                <td class="text-center">${Fmt.numero(descuentoGeneralPct, 2)}%</td>
                <td class="text-end">${formatNumber(descuentoGeneral, 2)}</td>
                <td class="text-end">${formatNumber(netoGravado, 2)}</td>
                <td class="text-center">${Fmt.numero(ivaPorcentaje, 2)}%</td>
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
            $('#busqueda_producto').data('codigos', { producto_codigo: detalle.producto_codigo || '', codigo_proveedor: detalle.codigo_proveedor || '' });
            $('#producto_seleccionado_id').val(detalle.producto_id);
            $('#producto_iva_id').val(detalle.iva_alicuota_id);
            $('#producto_cantidad').val(detalle.cantidad);
            $('#producto_precio').val(detalle.precio_unitario);
            $('#producto_descuento_item_pct').val(detalle.descuento_item_pct);
            $('#producto_iva').val(detalle.iva_porcentaje);
            $('#producto_iva_importe').val(detalle.iva_importe);
            $('#producto_no_gravado').val(detalle.no_gravado || 0);
            $('#producto_exento').val(detalle.exento || 0);
            // Compra por presentación: la línea vuelve al formulario en su presentación (o por unidad)
            if (CPRE) CPRE.cargar(detalle.producto_id, detalle.presentacion_id || null, detalle.presentacion_cantidad, detalle.presentacion_precio, true);
            detalleEnEdicionOC = detalle.ordenes_compra_detalle_id
                ? { producto_id: detalle.producto_id, ordenes_compra_detalle_id: detalle.ordenes_compra_detalle_id }
                : null;

            // Eliminar el detalle original
            detalles = detalles.filter(function(item) {
                return item.detalle_idx != idx;
            });
            renderizarDetalles();
            actualizarTotales();
            
            $('#busqueda_producto').focus();
        }
    });

    // En facturas_proveedores.js, agregar evento para botón de prueba
    $(document).on('click', '.btn-generar-asiento', function() {
        var facturaId = $(this).data('id');
        var $btn = $(this);
        
        console.log("Botón clickeado - facturaId:", facturaId);
        
        Swal.fire({
            title: 'Generando asiento...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        $.ajax({
            url: 'facturas_proveedores_ajax.php',
            type: 'POST',
            timeout: 30000,
            data: {
                accion: 'generar_asiento_contable',
                factura_proveedor_id: facturaId,
                empresa_idx: empresa_id
            },
            dataType: 'json',
            success: function(res) {
                console.log("Respuesta recibida:", res);
                Swal.close();
                
                if (res.success) {
                    Swal.fire({
                        icon: "success",
                        title: "¡Asiento generado!",
                        html: `${res.message}<br><br><strong>ID del Asiento:</strong> ${res.asiento_id}`,
                        confirmButtonText: "Entendido"
                    });
                    tabla.ajax.reload(null, false);
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        html: res.message || "Error al generar el asiento",
                        confirmButtonText: "Entendido"
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error("Error AJAX:", status, error);
                console.error("Respuesta:", xhr.responseText);
                Swal.close();
                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    html: `Error: ${status}<br>${xhr.responseText ? xhr.responseText.substring(0, 200) : 'Sin respuesta'}`,
                    confirmButtonText: "Entendido"
                });
            }
        });
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
                    var options = `<option value="${data[0].comprobante_tipo_id}" data-signo="${data[0].signo || ''}" data-debito="${data[0].requiere_debito_cuenta || 0}" selected>${data[0].comprobante_tipo}</option>`;
                    $('#comprobante_tipo_id').html(options);
                } else {
                    var options = '<option value="">Seleccionar</option>';
                    data.forEach(function(item) {
                        options += `<option value="${item.comprobante_tipo_id}" data-signo="${item.signo || ''}" data-debito="${item.requiere_debito_cuenta || 0}">${item.comprobante_tipo}</option>`;
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
        $('#otros_impuestos').val('0');
        $('#total').val('0');
        $('#otros_impuestos_display').text('0.00');
        $('#modalidad_descuento_factura').val('EN_FACTURA_GLOBAL');
        $('#precio_compra_contempla_descuento').val('1');
        $('#descuento_nc_pct').val('0');
        $('#col_descuento_nc').hide();
        $('#formFacturaProveedor').removeClass('was-validated');
        $('#deposito_id').val('');
        
        detalles = [];
        proveedorActualId = null;
        proveedorSucursalActualId = null;
        detalleEnEdicionOC = null;
        setOrdenCompraVinculada(null, null, null);
        quitarVinculoCert();
        $('#asociado_wrap').addClass('d-none');
        $('#comprobante_asociado_id').html('<option value="">Sin comprobante asociado</option>');
        $('#debito_wrap').addClass('d-none');
        $('#tesoreria_cuenta_debito_id').val('');
        limpiarAsientoProveedor();
        bootstrap.Tab.getOrCreateInstance(document.getElementById('datos-tab')).show();
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
        if (window.fpLecturaLimpiar) window.fpLecturaLimpiar();   // carga desde archivo (facturas_proveedores_lectura.js)
    }

    $(document).on('click', '#btnNuevo', function () {
        resetModal();
        $('#modalLabel').text('Nueva Factura de Proveedor');
        cargarCombosFormulario();
        cargarProveedoresYSucursales(); // Cargar combo unificado
        
        var today = Fmt.hoyISO();
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
                cargarProveedoresYSucursales(function() {
                    if (res.entidad_id) {
                        seleccionarProveedorEnCombo(res.entidad_id, res.entidad_sucursal_id);
                    }
                });
                
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
                $('#modalidad_descuento_factura').val(res.modalidad_descuento_factura || 'EN_FACTURA_GLOBAL');
                $('#precio_compra_contempla_descuento').val(res.precio_compra_contempla_descuento !== undefined && res.precio_compra_contempla_descuento !== null ? res.precio_compra_contempla_descuento : 1);
                $('#descuento_nc_pct').val(res.descuento_nc_pct || 0);
                if (res.modalidad_descuento_factura === 'NOTA_CREDITO') {
                    $('#col_descuento_nc').show();
                } else {
                    $('#col_descuento_nc').hide();
                }
                $('#subtotal').val(res.subtotal || 0);
                $('#descuentos').val(res.descuentos || 0);
                $('#no_gravado').val(res.no_gravado || 0);
                $('#exento').val(res.exento || 0);
                $('#impuestos').val(res.impuestos || 0);
                $('#otros_impuestos').val(res.otros_impuestos || 0);
                $('#total').val(res.total || 0);
                
                $('#total_neto_display').text(Fmt.numero(parseFloat(res.subtotal || 0), 2));
                $('#descuentos_display').text(Fmt.numero(parseFloat(res.descuentos || 0), 2));
                $('#no_gravado_display').text(Fmt.numero(parseFloat(res.no_gravado || 0), 2));
                $('#exento_display').text(Fmt.numero(parseFloat(res.exento || 0), 2));
                $('#impuestos_display').text(Fmt.numero(parseFloat(res.impuestos || 0), 2));
                $('#otros_impuestos_display').text(Fmt.numero(parseFloat(res.otros_impuestos || 0), 2));
                $('#total_display').text(Fmt.numero(parseFloat(res.total || 0), 2));
                $('#modalLabel').text('Editar Factura de Proveedor');
                setOrdenCompraVinculada(res.orden_compra_id, res.orden_compra_nro, res.entidad_id);
                if (res.certificacion_id) {
                    setCertificacionVinculada(res.certificacion_id, res.nro_certificado, res.cert_periodo_mes, res.cert_monto_neto);
                } else {
                    quitarVinculoCert();
                }
                cargarImpuestosFactura(res.factura_proveedor_id);
                cargarAsientoProveedor(res.factura_proveedor_id);

                setTimeout(function() {
                    $('#comprobante_tipo_id').val(res.comprobante_tipo_id);
                    // El proveedor se fija más abajo en este mismo bloque: el combo de comprobante asociado se carga después
                    setTimeout(function () { actualizarComprobanteAsociado(res.comprobante_asociado_id); }, 50);
                    $('#tesoreria_cuenta_debito_id').val(res.tesoreria_cuenta_debito_id || ''); actualizarDebitoCuenta();
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
                        seleccionarProveedorEnCombo(res.entidad_id, res.entidad_sucursal_id);
                    }
                    
                    // Después de cargar la condición de pago, recalcular vencimiento
                    // por si acaso la fecha de vencimiento original no coincide con los días
                    actualizarVencimientoPorCondicionPago();
                    
                    // Cargar detalles con todos los campos
                    if (res.detalles && res.detalles.length > 0) {
                        console.log("Detalles recibidos:", res.detalles);
                        
                        detalles = res.detalles.map(function(detalle, index) {
                            return $.extend({
                                detalle_idx: index,
                                factura_proveedor_detalle_id: detalle.factura_proveedor_detalle_id,
                                ordenes_compra_detalle_id: detalle.ordenes_compra_detalle_id || null,
                                producto_id: detalle.producto_id,
                                producto_nombre: detalle.producto_nombre,
                                producto_codigo: detalle.producto_codigo || '',
                                codigo_proveedor: detalle.codigo_proveedor || '',
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
                            }, CPRE ? CPRE.foto(detalle) : {});
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

    // ========== NUEVA FACTURA PRE-CARGADA DESDE ARCHIVO ==========
    // La llama facturas_proveedores_lectura.js con form = propuesta.form de facturas_proveedores_lectura_model.php.
    // Abre el formulario de siempre como alta (Borrador); el usuario revisa y guarda con "Guardar Factura".
    window.fpPrecargarDesdeArchivo = function (form, alListo) {
        resetModal();
        window.fpLecturaEnCurso = true;   // cargarImpuestosConfig no pone las percepciones por defecto
        $('#modalLabel').text('Nueva Factura de Proveedor (desde archivo)');
        cargarCombosFormulario();
        cargarProveedoresYSucursales(function () {
            if (form.entidad_id) {
                seleccionarProveedorEnCombo(form.entidad_id, form.entidad_sucursal_id);
                $('#entidad_combo').trigger('change');   // condición de pago del proveedor y comprobante asociado
            }
        });
        $('#comprobante_pv').val(form.comprobante_pv || 0);
        $('#comprobante_nro').val(form.comprobante_nro || 0);
        $('#f_emision').val(form.f_emision || Fmt.hoyISO());
        $('#f_contabilidad').val(form.f_contabilidad || form.f_emision || '');
        $('#observaciones').val(form.observaciones || '');

        // Los combos se cargan por AJAX: mismo intervalo que la edición
        setTimeout(function () {
            if (form.comprobante_tipo_id) $('#comprobante_tipo_id').val(String(form.comprobante_tipo_id)).trigger('change');
            if (!$('#sucursal_id').val() && $('#sucursal_id option').length === 2) {
                $('#sucursal_id').val($('#sucursal_id option').eq(1).val()).trigger('change');
            }
            detalles = (form.detalles || []).map(function (d) { return $.extend({}, d); });
            // Descuento general leído del pie (ya aplicado a las líneas de ítems; el campo no recalcula si no se cambia)
            $('#descuento_general_pct').val(form.descuento_general_pct || 0);
            if (form.modalidad_descuento_factura) {
                $('#modalidad_descuento_factura').val(form.modalidad_descuento_factura);
                if (form.modalidad_descuento_factura === 'NOTA_CREDITO') {
                    $('#col_descuento_nc').show();
                    $('#descuento_nc_pct').val(form.descuento_nc_pct || 0);
                } else {
                    $('#col_descuento_nc').hide();
                }
            }
            if (form.precio_compra_contempla_descuento !== undefined && form.precio_compra_contempla_descuento !== null) {
                $('#precio_compra_contempla_descuento').val(form.precio_compra_contempla_descuento);
            }
            impuestosFactura = (form.impuestos || []).map(function (i) { return $.extend({}, i); });
            renderizarDetalles();
            renderizarImpuestos();
            actualizarTotales();
            actualizarVencimientoPorCondicionPago();
            if (typeof alListo === 'function') alListo();
        }, 600);

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalFacturaProveedor')).show();
    };

    // Percepción elegida en el panel del documento (pendiente de provincia / configuración)
    window.fpAgregarImpuestoLectura = function (impuesto) {
        impuestosFactura.push(impuesto);
        renderizarImpuestos();
        actualizarTotales();
    };

    // ========== GUARDAR FACTURA ==========
    $('#btnGuardar').click(function() {
        var form = document.getElementById('formFacturaProveedor');
        var fEmision = $('#f_emision').val();
        if (!$('#f_vencimiento').val()) $('#f_vencimiento').val(vencimientoDelDia());
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
        formData.append('otros_impuestos', $('#otros_impuestos').val() || '0');
        formData.append('total', $('#total').val() || '0');
        formData.append('detalles', JSON.stringify(detalles));
        formData.append('orden_compra_id', $('#orden_compra_id').val() || '');
        formData.append('certificacion_id', $('#certificacion_id').val() || '');
        formData.append('comprobante_asociado_id', $('#comprobante_asociado_id').val() || '');
        formData.append('tesoreria_cuenta_debito_id', $('#debito_wrap').hasClass('d-none') ? '' : ($('#tesoreria_cuenta_debito_id').val() || ''));
        formData.append('descuento_general_pct', $('#descuento_general_pct').val() || '0');
        formData.append('modalidad_descuento_factura', $('#modalidad_descuento_factura').val() || 'EN_FACTURA_GLOBAL');
        formData.append('precio_compra_contempla_descuento', $('#precio_compra_contempla_descuento').val() !== undefined ? $('#precio_compra_contempla_descuento').val() : '1');
        formData.append('descuento_nc_pct', $('#descuento_nc_pct').val() || '0');
        formData.append('deposito_id', $('#deposito_id').val() || '');
        formData.append('impuestos_adicionales', JSON.stringify(impuestosFactura));
        // Cargada desde archivo: el servidor liga el archivo original y el CAE leído a la factura nueva
        if (!id && window.fpLecturaAdjuntoId) formData.append('lectura_adjunto_id', window.fpLecturaAdjuntoId);

        console.log("Impuestos a guardar:", impuestosFactura);
        

        // Log para depuración
        console.log("=== DATOS ENVIADOS ===");
        for (var pair of formData.entries()) {
            console.log(pair[0] + ': "' + pair[1] + '"');
        }
        
        var savedState = {
            page: tabla ? tabla.page() : 0,
            order: tabla ? tabla.order() : [[6, 'desc'], [0, 'desc']],
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
                    // Cargada desde archivo (lote): marca la fila como guardada (facturas_proveedores_lectura.js)
                    if (!id && window.fpLecturaAdjuntoId && window.fpLecturaGuardada) window.fpLecturaGuardada(res.factura_proveedor_id);
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
    if (!window.FP_EMBED) {
        inicializarDataTable();
        cargarBotonAgregar();
    }

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
                
                // Cargar modalidad y si contempla costo desde la entidad
                var modalidad = res.data.modalidad_descuento_factura || 'EN_FACTURA_GLOBAL';
                var contempla = (res.data.precio_compra_contempla_descuento !== undefined && res.data.precio_compra_contempla_descuento !== null) ? res.data.precio_compra_contempla_descuento : 1;
                $('#modalidad_descuento_factura').val(modalidad);
                $('#precio_compra_contempla_descuento').val(contempla);

                // Carga desde archivo: el descuento general es el del comprobante (fpPrecargarDesdeArchivo), no el del proveedor
                if (window.fpLecturaEnCurso) {
                    actualizarTotales();
                    return;
                }

                // Aplicar descuento general según modalidad
                var descuentoGeneral = parseFloat(res.data.proveedor_descuento_general) || 0;
                console.log("Aplicando condiciones proveedor - Modalidad:", modalidad, "Descuento:", descuentoGeneral);

                if (modalidad === 'NOTA_CREDITO') {
                    $('#col_descuento_nc').show();
                    $('#descuento_nc_pct').val(descuentoGeneral);
                    $('#descuento_general_pct').val(0);
                    descuentoGeneral = 0; // en la factura legal actual no va descuento general
                } else if (modalidad === 'EN_FACTURA_ITEM' || modalidad === 'SIN_DESCUENTO') {
                    $('#col_descuento_nc').hide();
                    $('#descuento_nc_pct').val(0);
                    $('#descuento_general_pct').val(0);
                    descuentoGeneral = 0;
                } else {
                    $('#col_descuento_nc').hide();
                    $('#descuento_nc_pct').val(0);
                    $('#descuento_general_pct').val(descuentoGeneral);
                }
                
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
                if (window.fpLecturaEnCurso) {   // carga desde archivo: las líneas traen el descuento del comprobante
                    actualizarTotales();
                    return;
                }
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

    // =========================================================================
    // IMPORTACIÓN DE COMPROBANTES RECIBIDOS DE ARCA / AFIP
    // El navegador solo lee las celdas del archivo; el servidor interpreta cada fila
    // (fpArcaNormalizarFila) tanto en la previsualización como al importar.
    // =========================================================================
    var filasArca = [];
    var previewArca = [];
    var arcaModalObj = null;

    $('#btnImportarArca').on('click', function() {
        abrirModalImportarArca();
    });

    function abrirModalImportarArca() {
        if (typeof XLSX === 'undefined') {
            Swal.fire('Error', 'No se pudo cargar el lector de Excel (xlsx).', 'error');
            return;
        }
        if (!arcaModalObj) {
            arcaModalObj = new bootstrap.Modal(document.getElementById('modalImportarArca'));
        }
        limpiarImportadorArca();
        cargarOpcionesImportacionArca();
        arcaModalObj.show();
    }

    function cargarOpcionesImportacionArca() {
        $.getJSON('facturas_proveedores_ajax.php', { accion: 'obtener_opciones_importacion_arca' }, function(res) {
            if (!res || !res.success) return;

            var selectSuc = $('#arca_sucursal_id').empty();
            (res.sucursales || []).forEach(function(s) {
                selectSuc.append($('<option>', { value: s.sucursal_id, text: s.sucursal_nombre }));
            });

            actualizarDepositosArca(res.depositos || []);

            var selectCp = $('#arca_condicion_pago_id').empty();
            (res.condiciones || []).forEach(function(c) {
                selectCp.append($('<option>', { value: c.condicion_pago_id, text: c.condicion_pago }));
            });
        });
    }

    $('#arca_sucursal_id').on('change', function() {
        var sucId = $(this).val();
        if (!sucId) return;
        $.getJSON('facturas_proveedores_ajax.php', { accion: 'obtener_depositos', sucursal_id: sucId }, function(res) {
            actualizarDepositosArca(res || []);
        });
    });

    function actualizarDepositosArca(depositos) {
        var selectDep = $('#arca_deposito_id').empty();
        selectDep.append($('<option>', { value: '', text: 'Sin depósito' }));
        (depositos || []).forEach(function(d) {
            selectDep.append($('<option>', { value: d.deposito_id, text: d.deposito_nombre }));
        });
    }

    $('#btnLimpiarArca').on('click', function() {
        limpiarImportadorArca();
    });

    // El alta automática cambia qué filas quedan listas: se vuelve a previsualizar
    $('#arca_crear_proveedores').on('change', function() {
        if (filasArca.length) enviarPrevisualizacionArca(filasArca);
    });

    function limpiarImportadorArca() {
        $('#inputArchivoArca').val('');
        filasArca = [];
        previewArca = [];
        $('#arca_resumen').addClass('d-none');
        $('#arca_tabla_contenedor').addClass('d-none');
        $('#arca_progreso_contenedor').addClass('d-none');
        $('#btnConfirmarImportarArca').prop('disabled', true);
        $('#txtCantAImportar').text('0');
        $('#tbodyPreviewArca').empty();
    }

    // Lectura del archivo con el lector común de Mis Comprobantes (assets/js/arca-mis-comprobantes.js, global ArcaMC),
    // el mismo que usa la importación del estudio contable.
    $('#inputArchivoArca').on('change', function(e) {
        var file = e.target.files[0];
        var $input = $(this);
        if (!file) return;
        ArcaMC.leerArchivo(file).then(function(r) {
            if (r.sentido !== 'RECIBIDOS') {
                $input.val('');
                Swal.fire('Archivo equivocado', 'Este archivo es de Mis Comprobantes Emitidos: acá va el de Recibidos.', 'warning');
                return;
            }
            filasArca = r.filas;
            enviarPrevisualizacionArca(filasArca);
        }).catch(function(err) {
            $input.val('');
            Swal.fire('Error al procesar archivo', err.message || 'Error desconocido al leer el archivo.', 'error');
        });
    });

    function enviarPrevisualizacionArca(filas) {
        Swal.fire({
            title: 'Analizando comprobantes...',
            text: 'Validando proveedores, duplicados y tipos de comprobantes...',
            allowOutsideClick: false,
            didOpen: function() { Swal.showLoading(); }
        });

        $.ajax({
            url: 'facturas_proveedores_ajax.php',
            type: 'POST',
            data: {
                accion: 'previsualizar_importacion_arca',
                filas: JSON.stringify(filas),
                crear_proveedores_auto: $('#arca_crear_proveedores').is(':checked') ? 1 : 0
            },
            dataType: 'json'
        }).done(function(res) {
            Swal.close();
            if (!res || !res.success) {
                return Swal.fire('Error', (res && res.error) || 'No se pudo analizar el archivo.', 'error');
            }
            previewArca = res.items || [];

            $('#cnt_arca_total').text(res.total_filas || 0);
            $('#cnt_arca_listos').text(res.listos || 0);
            $('#cnt_arca_duplicados').text(res.duplicados || 0);
            $('#cnt_arca_nuevos_prov').text(res.proveedores_nuevos || 0);
            $('#cnt_arca_errores').text(res.errores || 0);
            $('#cnt_arca_avisos').text(res.con_avisos || 0);
            $('#txtCantAImportar').text(res.listos || 0);

            $('#arca_resumen').removeClass('d-none');
            $('#arca_tabla_contenedor').removeClass('d-none');
            renderizarTablaPreviewArca(previewArca);

            $('#btnConfirmarImportarArca').prop('disabled', !(res.listos > 0));
        }).fail(function() {
            Swal.close();
            Swal.fire('Error', 'No se pudo analizar el archivo en el servidor.', 'error');
        });
    }

    function importeArca(v) {
        return '$' + Fmt.numero(Number(v || 0), { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderizarTablaPreviewArca(items) {
        var tbody = $('#tbodyPreviewArca').empty();
        if (!items || !items.length) {
            tbody.append('<tr><td colspan="12" class="text-center text-muted">No hay filas para mostrar.</td></tr>');
            return;
        }

        items.forEach(function(item, idx) {
            var badge, trClass = '';
            var mensaje = escapeHtml(String(item.mensaje || ''));
            if (item.estado === 'listo') {
                badge = '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Listo</span>';
            } else if (item.estado === 'nuevo_proveedor') {
                badge = '<span class="badge bg-primary" title="' + mensaje + '"><i class="fas fa-user-plus me-1"></i>Nuevo Prov.</span>';
            } else if (item.estado === 'duplicado') {
                badge = '<span class="badge bg-warning text-dark" title="' + mensaje + '"><i class="fas fa-ban me-1"></i>Duplicado</span>';
                trClass = 'table-light opacity-75';
            } else {
                badge = '<span class="badge bg-danger" title="' + mensaje + '"><i class="fas fa-exclamation-triangle me-1"></i>Error</span>';
                trClass = 'table-danger';
            }
            var notas = [];
            if (item.estado === 'error') notas.push('<div class="text-danger">' + mensaje + '</div>');
            (item.avisos || []).forEach(function(a) {
                notas.push('<div class="text-warning-emphasis"><i class="fas fa-info-circle me-1"></i>' + escapeHtml(String(a)) + '</div>');
            });
            var noIva = Number(item.no_gravado || 0) + Number(item.exento || 0) + Number(item.otros_tributos || 0);
            var imputacion = escapeHtml(String(item.producto_nombre || '')) +
                (item.imputacion_origen === 'ultima' ? '<div class="text-muted">Última compra del proveedor</div>' : '');

            var tr = $('<tr class="' + trClass + '">')
                .append('<td class="text-center small text-muted">' + (idx + 1) + '</td>')
                .append('<td>' + badge + '</td>')
                .append('<td class="small">' + escapeHtml(String(item.f_emision_formato || '')) + '</td>')
                .append('<td><span class="badge bg-secondary">' + escapeHtml(String(item.comprobante_tipo_nombre || '')) + '</span></td>')
                .append('<td class="font-monospace small">' + escapeHtml(item.pv_formato + '-' + item.nro_formato) + '</td>')
                .append('<td class="font-monospace small">' + escapeHtml(String(item.cuit || '')) + '</td>')
                .append('<td class="small"><span class="fw-semibold">' + escapeHtml(String(item.razon_social || '')) + '</span>' + notas.join('') + '</td>')
                .append('<td class="small">' + imputacion + '</td>')
                .append('<td class="text-end small">' + importeArca(item.neto) + '</td>')
                .append('<td class="text-end small">' + importeArca(item.iva) + '</td>')
                .append('<td class="text-end small" title="No gravado + exento + otros tributos">' + importeArca(noIva) + '</td>')
                .append('<td class="text-end fw-bold small">' + importeArca(item.total) + '</td>');
            tbody.append(tr);
        });
    }

    $('#btnConfirmarImportarArca').on('click', function() {
        var cantidad = previewArca.filter(function(i) {
            return i.estado === 'listo' || i.estado === 'nuevo_proveedor';
        }).length;
        if (!cantidad) {
            return Swal.fire('Sin comprobantes', 'No hay comprobantes listos para importar.', 'warning');
        }

        Swal.fire({
            title: '¿Confirmar importación?',
            text: 'Se cargarán ' + cantidad + ' comprobantes de compra en Borrador. Las filas con error o duplicadas se omiten.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, importar',
            cancelButtonText: 'Cancelar'
        }).then(function(result) {
            if (!result.isConfirmed) return;
            ejecutarImportacionArcaLote();
        });
    });

    function ejecutarImportacionArcaLote() {
        $('#btnConfirmarImportarArca').prop('disabled', true);
        $('#arca_progreso_contenedor').removeClass('d-none');
        $('#arca_progreso_barra').css('width', '50%').text('Importando comprobantes...');

        $.ajax({
            url: 'facturas_proveedores_ajax.php',
            type: 'POST',
            data: {
                accion: 'ejecutar_importacion_arca',
                filas: JSON.stringify(filasArca),
                sucursal_id: $('#arca_sucursal_id').val(),
                deposito_id: $('#arca_deposito_id').val(),
                condicion_pago_id: $('#arca_condicion_pago_id').val(),
                crear_proveedores_auto: $('#arca_crear_proveedores').is(':checked') ? 1 : 0
            },
            dataType: 'json'
        }).done(function(res) {
            $('#arca_progreso_barra').css('width', '100%').text('¡Completado!');

            if (res.success) {
                var omitidos = (res.errores || []).length;
                Swal.fire({
                    icon: 'success',
                    title: 'Importación terminada',
                    html: '<p>Comprobantes importados en Borrador: <strong>' + res.importados + '</strong></p>' +
                          (res.proveedores_creados > 0 ? '<p class="text-primary">Proveedores nuevos: <strong>' + res.proveedores_creados + '</strong></p>' : '') +
                          (res.duplicados > 0 ? '<p class="text-warning">Duplicados omitidos: <strong>' + res.duplicados + '</strong></p>' : '') +
                          (omitidos > 0 ? '<p class="text-danger">Filas con error omitidas: <strong>' + omitidos + '</strong></p>' : ''),
                    confirmButtonText: 'Aceptar'
                }).then(function() {
                    if (arcaModalObj) arcaModalObj.hide();
                    limpiarImportadorArca();
                    $('#tablaFacturasProveedor').DataTable().ajax.reload();
                });
            } else {
                $('#arca_progreso_contenedor').addClass('d-none');
                Swal.fire('Error en la importación', res.error || 'No se importó ningún comprobante.', 'error');
                $('#btnConfirmarImportarArca').prop('disabled', false);
            }
        }).fail(function() {
            $('#arca_progreso_contenedor').addClass('d-none');
            $('#btnConfirmarImportarArca').prop('disabled', false);
            Swal.fire('Error de comunicación', 'Error en el servidor al ejecutar la importación.', 'error');
        });
    }

});
