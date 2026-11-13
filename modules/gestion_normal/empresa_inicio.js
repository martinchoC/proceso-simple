/**
 * JavaScript: Asistente de Inicio de Empresa y Clonación Autorizada
 */

$(document).ready(function () {
    const AJAX_URL = 'empresa_inicio_ajax.php';
    let empresaActivaId = typeof EMPRESA_ACTIVA_ID !== 'undefined' ? parseInt(EMPRESA_ACTIVA_ID, 10) : parseInt($('#selectEmpresaFiltro').val() || '0', 10);
    let empresasAutorizadasData = [];

    const modalPrevEl = document.getElementById('modalPrevisualizacion');
    function getModalPrev() {
        if (window.bootstrap && bootstrap.Modal) {
            return bootstrap.Modal.getOrCreateInstance(modalPrevEl);
        }
        return {
            show: function () { $('#modalPrevisualizacion').modal('show'); },
            hide: function () { $('#modalPrevisualizacion').modal('hide'); }
        };
    }

    // 1. Cambio de Empresa Activa en selector superior
    $('#selectEmpresaFiltro').on('change', function () {
        const nuevaEmpresaId = $(this).val();
        window.location.href = `empresa_inicio.php?empresa_id=${nuevaEmpresaId}`;
    });

    // 2. Botón Refrescar
    $('#btnActualizarDiagnostico').on('click', function () {
        cargarDiagnosticoYAutorizaciones();
    });

    // 3. Selección de empresa origen autorizada
    $('#selectEmpresaOrigenCopia').on('change', function () {
        manejarCambioEmpresaOrigen(parseInt($(this).val(), 10));
    });

    // 4. Previsualizar Copia
    $('#btnPrevisualizarCopia').on('click', function () {
        previsualizarCopia();
    });

    // 5. Confirmar y Ejecutar Copia
    $('#btnEjecutarCopia').on('click', function () {
        confirmarYEjecutarCopia();
    });

    $('#btnConfirmarCopiaDesdeModal').on('click', function () {
        getModalPrev().hide();
        setTimeout(function () {
            confirmarYEjecutarCopia();
        }, 300);
    });

    // 6. Acciones rápidas
    $('#btnCrearSucursalRapida').on('click', function () {
        crearSucursalRapida();
    });

    $('#btnAsignarAccesosGlobales').on('click', function () {
        asignarAccesosGlobales();
    });

    // 7. Tipo de operación y perfiles predefinidos
    let tipoOperacionGuardado = '';
    let diagPerfiles = { faltantes: [], bienes: [], tipos: {} };

    $('#selectTipoOperacion').on('change', function () {
        $('#btnGuardarTipoOperacion').prop('disabled', $(this).val() === tipoOperacionGuardado);
    });

    $('#btnGuardarTipoOperacion').on('click', function () {
        guardarTipoOperacion();
    });

    $('#btnCrearPerfilesPredefinidos').on('click', function () {
        crearPerfilesPredefinidos();
    });

    $('#btnQuitarFuncionesBienes').on('click', function () {
        quitarFuncionesBienes();
    });

    function cargarDiagnosticoYAutorizaciones() {
        cargarDiagnostico();
        cargarEmpresasAutorizadas();
    }

    // Cargar diagnóstico completo
    function cargarDiagnostico() {
        $.ajax({
            url: AJAX_URL,
            type: 'GET',
            data: {
                action: 'obtener_diagnostico',
                empresa_id: empresaActivaId
            },
            dataType: 'json',
            success: function (data) {
                if (!data.success) {
                    Swal.fire('Error al diagnosticar', data.error || 'No se pudo obtener el estado', 'error');
                    return;
                }
                renderizarDiagnostico(data.data);
            },
            error: function () {
                console.error("Error al obtener diagnóstico");
            }
        });
    }

    // Renderizar diagnóstico en vista
    function renderizarDiagnostico(diag) {
        const emp = diag.empresa || {};
        $('#empresaRazonSocial').text(emp.empresa || `Empresa ID ${empresaActivaId}`);
        $('#empresaCuit').text(emp.documento_numero || 'Sin CUIT');
        $('#empresaCondFiscal').text(emp.condicion_fiscal || 'No definida');
        $('#empresaLocalidad').text(emp.localidad ? `${emp.localidad}` : (emp.domicilio || 'Sin domicilio'));

        // Scorecard
        const score = diag.score || 0;
        const progressBar = $('#progressBarScore');
        const badgeScore = $('#badgeScore');
        const scoreMensaje = $('#scoreMensaje');

        progressBar.css('width', `${score}%`).text(`${score}%`);
        badgeScore.text(`${score}%`);

        progressBar.attr('class', 'progress-bar progress-bar-striped progress-bar-animated');
        if (score >= 100) {   // "lista para operar" solo con todos los controles cumplidos
            progressBar.addClass('bg-success');
            badgeScore.attr('class', 'badge fs-6 bg-success');
            scoreMensaje.html('<i class="fas fa-check-circle text-success me-1"></i><strong>Excelente:</strong> Empresa lista para emitir comprobantes y operar en producción.');
        } else if (score >= 50) {
            progressBar.addClass('bg-warning');
            badgeScore.attr('class', 'badge fs-6 bg-warning text-dark');
            scoreMensaje.html('<i class="fas fa-exclamation-circle text-warning me-1"></i><strong>En configuración:</strong> Faltan puntos recomendados antes de operar plenamente.');
        } else {
            progressBar.addClass('bg-danger');
            badgeScore.attr('class', 'badge fs-6 bg-danger');
            scoreMensaje.html('<i class="fas fa-times-circle text-danger me-1"></i><strong>Configuración inicial requerida:</strong> Se recomienda copiar datos básicos desde una empresa autorizada.');
        }

        const chk = diag.checks || {};

        // 0. Tipo de operación y perfiles
        renderizarTipoYPerfiles(diag, chk.perfiles);

        // 1. Sucursales
        const sucursales = diag.sucursales || [];
        actualizarItemAcordeon('Suc', chk.sucursales, `(${sucursales.length} registradas)`);
        renderizarListaDetalle('listaSucursalesDetalle', sucursales, s => `<strong>${escapeHtml(s.sucursal_nombre)}</strong> <small class="text-muted">(${s.sucursal_tipo || 'Tipo ' + s.sucursal_tipo_id}) - ${escapeHtml(s.direccion || 'Sin dirección')}</small>`);

        // 2. Bocas
        const bocas = diag.bocas || [];
        actualizarItemAcordeon('Boc', chk.bocas, `(${bocas.length} registradas)`);
        renderizarListaDetalle('listaBocasDetalle', bocas, b => `<code>${b.codigo}</code> <strong>${escapeHtml(b.boca_nombre)}</strong> <small class="text-muted">(${b.sucursal_nombre}) ${b.es_principal == 1 ? '<span class="badge bg-primary">Principal</span>' : ''}</small>`);

        // 3. Puntos de Venta
        const pv = diag.puntos_venta || [];
        actualizarItemAcordeon('Pv', chk.puntos_venta, `(${pv.length} registrados)`);
        renderizarListaDetalle('listaPvDetalle', pv, p => `PV <strong>#${String(p.codigo_fiscal || '0').padStart(4, '0')}</strong> - ${escapeHtml(p.nombre)} <span class="badge bg-info">${p.sistema_emision}</span> <small class="text-muted">(${p.sucursal_nombre})</small>`);

        // 4. Tipos de Comprobantes
        const comp = diag.comprobantes_tipos || [];
        actualizarItemAcordeon('Comp', chk.comprobantes_tipos, `(${comp.length} configurados)`);
        renderizarListaDetalle('listaCompDetalle', comp.slice(0, 8), c => `<code>${c.codigo}</code> ${escapeHtml(c.comprobante_tipo)} ${c.letra ? '<span class="badge bg-secondary">' + c.letra + '</span>' : ''}`, comp.length > 8 ? `... y ${comp.length - 8} tipos más.` : '');

        // 5. Accesos por Perfil
        const acc = diag.accesos || [];
        actualizarItemAcordeon('Acc', chk.accesos_perfiles, `(${acc.length} permisos otorgados)`);
        renderizarListaDetalle('listaAccDetalle', acc, a => `Perfil: <strong>${escapeHtml(a.empresa_perfil_nombre)}</strong> &rarr; <span class="badge bg-success">${a.sucursal_nombre || 'Todas las Sucursales'}</span>`);

        // 6. Plan de Cuentas
        const cStats = diag.cuentas_stats || {};
        actualizarItemAcordeon('Plan', chk.plan_cuentas, `(${cStats.total_cuentas || 0} cuentas, ${cStats.total_imputables || 0} imputables)`);
        $('#listaPlanDetalle').html(`<div class="p-2 border rounded bg-white">
            <span class="badge bg-primary me-2">Total Cuentas: ${cStats.total_cuentas || 0}</span>
            <span class="badge bg-success me-2">Imputables: ${cStats.total_imputables || 0}</span>
            <span class="badge bg-info me-2">Caja y Bancos: ${cStats.total_caja_banco || 0}</span>
            <span class="badge bg-secondary">Niveles jerárquicos: ${cStats.max_nivel || 0}</span>
        </div>`);

        // 7. Conceptos Contables
        const conc = diag.conceptos || [];
        const concConfig = diag.conceptos_configurados || 0;
        actualizarItemAcordeon('Conc', chk.conceptos_contables, `(${concConfig} de ${conc.length} configurados)`);
        renderizarListaDetalle('listaConcDetalle', conc.slice(0, 8), cn => `<code>${cn.concepto}</code> &rarr; ${cn.cuenta_codigo ? '<strong class="text-success">' + cn.cuenta_codigo + ' - ' + escapeHtml(cn.cuenta_nombre) + '</strong>' : '<span class="text-danger fw-bold"><i class="fas fa-exclamation-triangle"></i> Sin cuenta asignada</span>'}`, conc.length > 8 ? `... y ${conc.length - 8} conceptos más.` : '');

        // 8. Roles y Cuentas
        const roles = diag.roles_cuentas || [];
        actualizarItemAcordeon('Roles', chk.roles_cuentas, `(${roles.length} roles configurados)`);
        renderizarListaDetalle('listaRolesDetalle', roles, r => `Rol <strong>${r.rol_entidad_codigo} (${escapeHtml(r.rol_entidad_nombre)})</strong> &rarr; <strong class="text-success">${r.cuenta_codigo} - ${escapeHtml(r.cuenta_nombre)}</strong>`);

        // 9. Condiciones de Pago
        const cond = diag.condiciones_pago || [];
        actualizarItemAcordeon('Cond', chk.condiciones_pago, `(${cond.length} condiciones)`);
        renderizarListaDetalle('listaCondDetalle', cond, cp => `<code>${cp.codigo}</code> ${escapeHtml(cp.condicion_pago)} <span class="badge bg-light text-dark border">${cp.tipo}</span>`);

        // 10. Parámetros
        const param = diag.parametros;
        actualizarItemAcordeon('Param', chk.parametros, param ? `(Vigente: ${param.condicion_fiscal || 'OK'})` : '(Sin parámetros vigentes)');
        if (param) {
            $('#listaParamDetalle').html(`<div class="p-2 border rounded bg-white small">
                <div><strong>Condición Fiscal:</strong> ${param.condicion_fiscal || 'N/D'}</div>
                <div><strong>Punto de Venta por Defecto:</strong> ${param.pv_nombre || 'No asignado'}</div>
                <div><strong>Lista de Precios por Defecto:</strong> ${param.lista_precio_nombre || 'No asignada'}</div>
                <div><strong>Condición de Pago por Defecto:</strong> ${param.condicion_pago || 'No asignada'}</div>
                <div><strong>Facturación Electrónica ARCA:</strong> ${param.arca_habilitado == 1 ? '<span class="badge bg-success">Habilitado (CAE)</span>' : '<span class="badge bg-secondary">Deshabilitado</span>'}</div>
            </div>`);
        } else {
            $('#listaParamDetalle').html('<div class="alert alert-warning py-1 small mb-0"><i class="fas fa-exclamation-triangle me-1"></i>No se registran parámetros de facturación vigentes para esta empresa.</div>');
        }

        // 11 a 16. Maestros de operación (los trae la copia de "Datos básicos" y "Tesorería", salvo bancos y tipos por PV)
        const op = diag.operacion || {};
        const falta = (texto) => `<div class="alert alert-warning py-1 small mb-0"><i class="fas fa-exclamation-triangle me-1"></i>${texto}</div>`;
        const ok = (texto) => `<div class="p-2 border rounded bg-white small">${texto}</div>`;

        actualizarItemAcordeon('Mon', chk.monedas, op.moneda_base ? `(${escapeHtml(op.moneda_base)})` : '(Sin moneda base)');
        $('#listaMonDetalle').html(op.moneda_base ? ok(`Moneda base: <strong>${escapeHtml(op.moneda_base)}</strong>`)
            : falta('La empresa no tiene moneda base: cargue la moneda local en Tesorería &gt; Monedas.'));

        actualizarItemAcordeon('Iva', chk.iva_alicuotas, `(${op.iva_alicuotas || 0} alícuotas, ${op.iva_gravadas || 0} gravadas)`);
        $('#listaIvaDetalle').html(chk.iva_alicuotas ? ok(`${op.iva_alicuotas} alícuotas activas, ${op.iva_gravadas} gravadas.`)
            : falta('No hay alícuotas de IVA gravadas activas.'));

        actualizarItemAcordeon('Asi', chk.tipos_asiento, chk.tipos_asiento ? '(AUT configurado)' : '(Sin tipo AUT)');
        $('#listaAsiDetalle').html(chk.tipos_asiento ? ok('Los asientos automáticos usan el tipo <code>AUT</code>.')
            : falta('Falta el tipo de asiento de código <code>AUT</code>: sin él no se generan los asientos de facturas, recibos ni pagos.'));

        actualizarItemAcordeon('Tes', chk.tesoreria_cuentas, `(${op.cajas || 0} cajas, ${op.bancos || 0} bancos, ${op.valores || 0} valores)`);
        let tes = `${op.cajas || 0} cajas, ${op.bancos || 0} cuentas bancarias y ${op.valores || 0} cuentas de valores activas.`;
        if (!op.cajas) tes += ' <strong>Falta al menos una caja.</strong>';
        if (op.cajas_sin_cuenta) tes += ` <strong>${op.cajas_sin_cuenta} cajas sin cuenta contable.</strong>`;
        if (!op.valores) tes += ' Sin cuenta de valores no se pueden recibir cheques.';
        $('#listaTesDetalle').html(chk.tesoreria_cuentas ? ok(tes) : falta(tes));

        actualizarItemAcordeon('Med', chk.medios_pago, `(${op.medios_cobro || 0} de cobro, ${op.medios_pago || 0} de pago)`);
        let med = `${op.medios_cobro || 0} medios de cobro (${op.medios_retencion || 0} retenciones sufridas) y ${op.medios_pago || 0} de pago.`;
        if (op.medios_sin_cuenta) med += ` <strong>${op.medios_sin_cuenta} sin cuenta (de tesorería o contable, según el tipo).</strong>`;
        $('#listaMedDetalle').html(chk.medios_pago ? ok(med) : falta(med));

        actualizarItemAcordeon('PvComp', chk.pv_comprobantes, op.pv_sin_comprobantes ? `(${op.pv_sin_comprobantes} puntos de venta sin tipos)` : '(todos con tipos habilitados)');
        $('#listaPvCompDetalle').html(chk.pv_comprobantes ? ok('Todos los puntos de venta activos tienen tipos de comprobante habilitados.')
            : falta(op.pv_sin_comprobantes ? `${op.pv_sin_comprobantes} puntos de venta activos sin tipos de comprobante habilitados.` : 'La empresa no tiene puntos de venta.'));

        // 17. Configuración de Impuestos
        const impCfg = diag.impuestos_config || [];
        actualizarItemAcordeon('ImpCfg', chk.impuestos_config, `(${impCfg.length} configurados)`);
        renderizarListaDetalle('listaImpCfgDetalle', impCfg.slice(0, 8), ic => `<strong>${escapeHtml(ic.impuesto_tipo || 'Impuesto')}</strong> ${ic.jurisdiccion_nombre ? '<span class="badge bg-secondary">' + escapeHtml(ic.jurisdiccion_nombre) + '</span>' : ''} &rarr; ${ic.cuenta_codigo ? '<code class="text-success">' + ic.cuenta_codigo + '</code>' : '<span class="text-warning">Sin cuenta</span>'} <small class="text-muted">(${ic.tipo_calculo})</small>`, impCfg.length > 8 ? `... y ${impCfg.length - 8} impuestos más.` : '');

        // 18. Catálogo de Productos y Servicios
        const pStats = diag.productos_stats || { total_productos: 0, total_bienes: 0, total_servicios: 0 };
        actualizarItemAcordeon('Prod', chk.productos, `(${pStats.total_productos || 0} ítems: ${pStats.total_bienes || 0} productos, ${pStats.total_servicios || 0} servicios)`);
        $('#listaProdDetalle').html(chk.productos
            ? ok(`${pStats.total_productos} ítems activos en el catálogo (${pStats.total_bienes} productos / bienes y ${pStats.total_servicios} servicios).`)
            : falta('Sin productos ni servicios dados de alta en el catálogo.'));
    }

    function renderizarTipoYPerfiles(diag, isOk) {
        const tipos = diag.tipos_operacion || {};
        tipoOperacionGuardado = diag.tipo_operacion || 'MIXTA';
        $('#selectTipoOperacion').val(tipoOperacionGuardado);
        $('#btnGuardarTipoOperacion').prop('disabled', true);

        const predefinidos = diag.perfiles_predefinidos || [];
        const bienes = diag.perfiles_bienes || [];
        const faltantes = predefinidos.filter(p => !p.empresa_perfil_id);
        diagPerfiles = { faltantes: faltantes, bienes: bienes, tipos: tipos };

        let resumen = `(${tipos[tipoOperacionGuardado] || tipoOperacionGuardado}`;
        resumen += faltantes.length ? ` · faltan ${faltantes.length} perfiles predefinidos` : ' · perfiles predefinidos completos';
        if (bienes.length) resumen += ` · ${bienes.length} perfiles con pedidos/remitos/stock`;
        actualizarItemAcordeon('Perf', isOk, resumen + ')');

        const etiquetaTipo = { BIENES: 'solo bienes', SERVICIOS: 'solo servicios' };
        renderizarListaDetalle('listaPerfilesPredefinidos', predefinidos, p => {
            const estado = p.empresa_perfil_id
                ? '<span class="badge bg-success">Creado</span>'
                : '<span class="badge bg-secondary">Falta</span>';
            const tipo = etiquetaTipo[p.tipo_operacion] ? ` <span class="badge bg-light text-dark border">${etiquetaTipo[p.tipo_operacion]}</span>` : '';
            return `${estado} <strong>${escapeHtml(p.perfil_nombre)}</strong>${tipo} <small class="text-muted">(${p.funciones} funciones)</small>`;
        });
        $('#btnCrearPerfilesPredefinidos').prop('disabled', faltantes.length === 0);

        if (bienes.length) {
            $('#boxPerfilesBienes').show();
            $('#btnQuitarFuncionesBienes').show();
            renderizarListaDetalle('listaPerfilesBienes', bienes, b => `<strong>${escapeHtml(b.empresa_perfil_nombre)}</strong> <small class="text-muted">(${b.funciones} funciones: ${escapeHtml(b.paginas)})</small>`);
        } else {
            $('#boxPerfilesBienes').hide();
            $('#btnQuitarFuncionesBienes').hide();
        }
    }

    function actualizarItemAcordeon(key, isOk, resumenText) {
        const badge = $(`#chkBadge${key}`);
        const resumen = $(`#chkResumen${key}`);
        if (badge.length) {
            if (isOk) {
                badge.attr('class', 'badge bg-success me-2').html('<i class="fas fa-check"></i>');
            } else {
                badge.attr('class', 'badge bg-danger me-2').html('<i class="fas fa-times"></i>');
            }
        }
        if (resumen.length) {
            resumen.text(resumenText);
            resumen.attr('class', isOk ? 'text-success small fw-semibold' : 'text-danger small fw-semibold');
        }
    }

    function renderizarListaDetalle(elementId, items, renderFn, extraText = '') {
        const container = $(`#${elementId}`);
        if (!container.length) return;
        if (!items || items.length === 0) {
            container.html('<small class="text-danger fw-bold"><i class="fas fa-exclamation-circle me-1"></i>Sin registros dados de alta.</small>');
            return;
        }
        let html = '<ul class="list-group list-group-flush border rounded bg-white">';
        items.forEach(it => {
            html += `<li class="list-group-item py-1 px-2 small">${renderFn(it)}</li>`;
        });
        if (extraText) {
            html += `<li class="list-group-item py-1 px-2 text-muted small bg-light">${extraText}</li>`;
        }
        html += '</ul>';
        container.html(html);
    }

    // Cargar empresas autorizadas para clonar
    function cargarEmpresasAutorizadas() {
        $.ajax({
            url: AJAX_URL,
            type: 'GET',
            data: {
                action: 'obtener_empresas_autorizadas',
                empresa_id: empresaActivaId
            },
            dataType: 'json',
            success: function (data) {
                if (!data.success) return;
                empresasAutorizadasData = data.data || [];

                const count = empresasAutorizadasData.length;
                $('#badgeEmpresasAutorizadasCount').text(`${count} empresa(s) autorizada(s)`);

                if (count === 0) {
                    $('#contenedorSinAutorizaciones').show();
                    $('#contenedorConAutorizaciones').hide();
                } else {
                    $('#contenedorSinAutorizaciones').hide();
                    $('#contenedorConAutorizaciones').show();

                    const options = ['<option value="">Seleccione una empresa autorizada...</option>'];
                    empresasAutorizadasData.forEach(aut => {
                        options.push(`<option value="${aut.empresa_origen_id}">${aut.empresa_origen_nombre} (CUIT: ${aut.empresa_origen_cuit || 'S/D'})</option>`);
                    });
                    $('#selectEmpresaOrigenCopia').html(options.join(''));

                    // Si vino por URL un origen_id o si hay solo una empresa autorizada
                    if (typeof ORIGEN_ID_URL !== 'undefined' && ORIGEN_ID_URL > 0) {
                        $('#selectEmpresaOrigenCopia').val(ORIGEN_ID_URL);
                        manejarCambioEmpresaOrigen(ORIGEN_ID_URL);
                    } else if (empresasAutorizadasData.length === 1) {
                        const soloId = parseInt(empresasAutorizadasData[0].empresa_origen_id, 10);
                        $('#selectEmpresaOrigenCopia').val(soloId);
                        manejarCambioEmpresaOrigen(soloId);
                    }
                }
            },
            error: function () {
                console.error("Error al cargar autorizadas");
            }
        });
    }

    // Manejar selección de empresa origen
    function manejarCambioEmpresaOrigen(origenId) {
        const aut = empresasAutorizadasData.find(a => parseInt(a.empresa_origen_id, 10) === origenId);
        const infoBox = $('#infoAutorizacionSeleccionada');
        const boxChk = $('#checkboxesComponentes');
        const btnPrev = $('#btnPrevisualizarCopia');
        const btnEjec = $('#btnEjecutarCopia');

        if (!aut) {
            infoBox.html('<em>Seleccione una empresa para ver los componentes habilitados.</em>');
            boxChk.html('');
            btnPrev.prop('disabled', true);
            btnEjec.prop('disabled', true);
            return;
        }

        infoBox.html(`<div><strong>Vigencia:</strong> ${aut.fecha_desde} al ${aut.fecha_hasta || 'Sin límite'}</div>
            <div><strong>Motivo:</strong> ${escapeHtml(aut.motivo || 'Autorización concedida')}</div>`);

        const componentesConfig = [
            { key: 'perfiles', field: null, label: 'Tipo de Operación y Perfiles', icon: 'fas fa-user-shield', color: 'primary', desc: 'Tipo de operación, perfiles y sus funciones' },
            { key: 'plan_cuentas', field: 'permite_plan_cuentas', label: 'Plan de Cuentas Contables', icon: 'fas fa-sitemap', color: 'primary', desc: 'Árbol de cuentas e imputables' },
            { key: 'conceptos_contables', field: 'permite_conceptos_contables', label: 'Conceptos Contables Operativos', icon: 'fas fa-tags', color: 'info', desc: 'Mapeo para IVA, Valores, etc.' },
            { key: 'roles_cuentas', field: 'permite_roles_cuentas', label: 'Cuentas por Rol de Entidad', icon: 'fas fa-users-cog', color: 'success', desc: 'Clientes, Proveedores, Empleados' },
            { key: 'comprobantes_tipos', field: 'permite_comprobantes_tipos', label: 'Tipos de Comprobantes', icon: 'fas fa-file-invoice', color: 'warning', desc: 'Facturas, Remitos, Recibos' },
            { key: 'condiciones_pago', field: 'permite_condiciones_pago', label: 'Condiciones de Pago', icon: 'fas fa-credit-card', color: 'danger', desc: 'Contado, Cuenta Corriente' },
            { key: 'sucursales_bocas', field: 'permite_sucursales_bocas', label: 'Sucursales, Bocas y Puntos de Venta', icon: 'fas fa-store', color: 'secondary', desc: 'Sucursales, depósitos, puntos de venta y sus comprobantes' },
            { key: 'listas_precios', field: 'permite_listas_precios', label: 'Listas de Precios Base', icon: 'fas fa-list-ol', color: 'dark', desc: 'Listas de venta base' },
            { key: 'parametros', field: 'permite_parametros', label: 'Parámetros Generales', icon: 'fas fa-sliders-h', color: 'dark', desc: 'Defaults y reglas comerciales' },
            { key: 'datos_basicos', field: 'permite_datos_basicos', label: 'Datos Básicos', icon: 'fas fa-database', color: 'success', desc: 'Monedas, tipos de asiento, IVA, tipos y categorías de producto y de gasto' },
            { key: 'tesoreria', field: 'permite_tesoreria', label: 'Tesorería', icon: 'fas fa-cash-register', color: 'info', desc: 'Cajas, valores, cupones y medios de cobro y pago' },
            { key: 'impuestos_config', field: 'permite_impuestos_config', label: 'Configuración de Impuestos', icon: 'fas fa-percent', color: 'warning', desc: 'Reglas de IIBB, percepciones, retenciones, padrones y alícuotas' },
            { key: 'productos', field: 'permite_productos', label: 'Productos y Servicios', icon: 'fas fa-boxes', color: 'primary', desc: 'Catálogo de productos y servicios con tipos, categorías y cuentas' }
        ];

        let chkHtml = '';
        componentesConfig.forEach(comp => {
            const isPermitido = comp.field === null || parseInt(aut[comp.field], 10) === 1;
            chkHtml += `<div class="col-md-3 col-sm-6">
                <div class="card h-100 p-2 border ${isPermitido ? 'border-primary-subtle bg-white' : 'border-light bg-light-subtle opacity-75'}">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input chk-copiar-comp" type="checkbox" id="chk_copiar_${comp.key}" data-key="${comp.key}" ${isPermitido ? 'checked' : 'disabled'}>
                        <label class="form-check-label fw-bold small ${isPermitido ? 'text-dark' : 'text-muted'}" for="chk_copiar_${comp.key}">
                            <i class="${comp.icon} text-${comp.color} me-1"></i>${comp.label}
                        </label>
                        <small class="d-block text-muted" style="font-size: 0.75rem;">${isPermitido ? comp.desc : '<span class="text-danger">No autorizado por la empresa</span>'}</small>
                    </div>
                </div>
            </div>`;
        });

        boxChk.html(chkHtml);
        btnPrev.prop('disabled', false);
        btnEjec.prop('disabled', false);
    }

    // Previsualizar copia
    function previsualizarCopia() {
        const origenId = parseInt($('#selectEmpresaOrigenCopia').val(), 10);
        if (!origenId) return;

        getModalPrev().show();
        const body = $('#modalPrevisualizacionBody');
        body.html('<div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Analizando catálogos de la empresa origen...</div>');

        $.ajax({
            url: AJAX_URL,
            type: 'GET',
            data: {
                action: 'previsualizar_copia',
                empresa_id: empresaActivaId,
                empresa_origen_id: origenId
            },
            dataType: 'json',
            success: function (data) {
                if (!data.success) {
                    body.html(`<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>${data.error || 'Error al previsualizar'}</div>`);
                    return;
                }

                const d = data.data;
                const detalles = d.detalles || {};

                let tableHtml = `<div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Componente</th>
                                <th class="text-center" width="160">Registros en Origen</th>
                                <th class="text-center" width="160">Registros en Destino</th>
                                <th class="text-center" width="120">Estado</th>
                            </tr>
                        </thead>
                        <tbody>`;

                for (let k in detalles) {
                    const it = detalles[k];
                    const estadoHtml = it.destino_actual === 0
                        ? '<span class="badge bg-success">Se creará completo</span>'
                        : `<span class="badge bg-warning text-dark">Se integrará (${it.destino_actual} existentes)</span>`;

                    tableHtml += `<tr>
                        <td><i class="${it.icono} me-2 text-primary"></i><strong>${it.nombre}</strong></td>
                        <td class="text-center fw-bold fs-6 text-primary">${it.origen_total}</td>
                        <td class="text-center fw-bold fs-6 text-secondary">${it.destino_actual}</td>
                        <td class="text-center">${estadoHtml}</td>
                    </tr>`;
                }

                tableHtml += '</tbody></table></div>';
                tableHtml += `<div class="alert alert-info py-2 small mb-0">
                    <i class="fas fa-info-circle me-1"></i>Al ejecutar la copia, los IDs relacionales (como cuentas padre del plan contable y asignación de cuentas a conceptos y roles) serán automáticamente remapeados con integridad referencial.
                </div>`;

                body.html(tableHtml);
            },
            error: function () {
                body.html('<div class="alert alert-danger">Error de comunicación al consultar el servidor.</div>');
            }
        });
    }

    // Confirmar y ejecutar copia
    function confirmarYEjecutarCopia() {
        const origenId = parseInt($('#selectEmpresaOrigenCopia').val(), 10);
        if (!origenId) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'Atención', text: 'Seleccione una empresa origen autorizada' });
            } else {
                alert('Atención: Seleccione una empresa origen autorizada');
            }
            return;
        }

        const componentes = {};
        let totalSeleccionados = 0;
        $('.chk-copiar-comp').each(function () {
            const chk = $(this);
            const key = chk.attr('data-key');
            if (chk.is(':checked') && !chk.is(':disabled')) {
                componentes[key] = 1;
                totalSeleccionados++;
            }
        });

        if (totalSeleccionados === 0) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe marcar al menos un componente para copiar' });
            } else {
                alert('Atención: Debe marcar al menos un componente para copiar');
            }
            return;
        }

        const aut = empresasAutorizadasData.find(a => parseInt(a.empresa_origen_id, 10) === origenId);
        const origenNombre = aut ? aut.empresa_origen_nombre : `Empresa ID ${origenId}`;

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '¿Confirmar copia de datos iniciales?',
                html: `Se importarán los datos seleccionados desde <strong>${escapeHtml(origenNombre)}</strong> hacia la empresa actual.<br><br><small class="text-muted">La operación es segura y conserva la coherencia de cuentas y catálogos.</small>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-download me-1"></i>Sí, ejecutar copia',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#198754'
            }).then(function (result) {
                if (result.isConfirmed) {
                    ejecutarCopiaAjax(origenId, componentes);
                }
            });
        } else {
            if (confirm(`¿Confirmar copia de datos iniciales desde "${origenNombre}" hacia la empresa actual?`)) {
                ejecutarCopiaAjax(origenId, componentes);
            }
        }
    }

    function ejecutarCopiaAjax(origenId, componentes) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Copiando configuración inicial...',
                html: '<i class="fas fa-spinner fa-spin fa-3x text-primary my-3"></i><br>Por favor aguarde, recreando cuentas, conceptos y comprobantes...',
                allowOutsideClick: false,
                showConfirmButton: false
            });
        }

        $.ajax({
            url: AJAX_URL,
            type: 'POST',
            data: {
                action: 'ejecutar_copia',
                empresa_id: empresaActivaId,
                empresa_origen_id: origenId,
                componentes: JSON.stringify(componentes)
            },
            dataType: 'json',
            success: function (data) {
                if (data.success) {
                    let detalleMsg = '';
                    if (data.detalles) {
                        detalleMsg = '<ul class="text-start mt-3 mb-0">';
                        for (let k in data.detalles) {
                            detalleMsg += `<li><strong>${k}:</strong> ${data.detalles[k]}</li>`;
                        }
                        detalleMsg += '</ul>';
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Copia Completada!',
                            html: `${data.message}${detalleMsg}`,
                            confirmButtonText: 'Entendido'
                        });
                    } else {
                        alert(`¡Copia Completada!\n${data.message}`);
                    }

                    cargarDiagnosticoYAutorizaciones();
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Error durante la copia', data.error || 'Ocurrió un error inesperado al procesar la clonación', 'error');
                    } else {
                        alert(`Error durante la copia: ${data.error || 'Ocurrió un error inesperado'}`);
                    }
                }
            },
            error: function (xhr, status, error) {
                console.error("Error AJAX copia:", xhr.responseText || error);
                const msg = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'No se pudo comunicar con el servidor';
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error de comunicación', msg, 'error');
                } else {
                    alert('Error de comunicación: ' + msg);
                }
            }
        });
    }

    // Acción rápida: Crear sucursal y depósito
    function crearSucursalRapida() {
        Swal.fire({
            title: '¿Crear Sucursal Central y Depósito?',
            text: 'Se creará automáticamente la Casa Central, el Depósito Central y el Punto de Venta 0001 (WSFE) para esta empresa.',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Sí, crear configuración básica',
            cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: AJAX_URL,
                    type: 'POST',
                    data: {
                        action: 'crear_sucursal_rapida',
                        empresa_id: empresaActivaId
                    },
                    dataType: 'json',
                    success: function (data) {
                        if (data.success) {
                            Swal.fire({ icon: 'success', title: 'Listo', text: data.message });
                            cargarDiagnostico();
                        } else {
                            Swal.fire('Atención', data.error || 'No se pudo crear', 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
                    }
                });
            }
        });
    }

    // Acción rápida: Asignar accesos globales
    function asignarAccesosGlobales() {
        Swal.fire({
            title: '¿Conceder accesos a todos los perfiles?',
            text: 'Se habilitará a todos los perfiles activos de la empresa el acceso general a las sucursales.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, conceder accesos',
            cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: AJAX_URL,
                    type: 'POST',
                    data: {
                        action: 'asignar_accesos_globales',
                        empresa_id: empresaActivaId
                    },
                    dataType: 'json',
                    success: function (data) {
                        if (data.success) {
                            Swal.fire({ icon: 'success', title: 'Accesos asignados', text: data.message });
                            cargarDiagnostico();
                        } else {
                            Swal.fire('Error', data.error || 'No se pudo conceder acceso', 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
                    }
                });
            }
        });
    }

    function postAccionPerfiles(data, tituloOk) {
        $.ajax({
            url: AJAX_URL,
            type: 'POST',
            data: Object.assign({ empresa_id: empresaActivaId }, data),
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: tituloOk, text: res.message });
                    cargarDiagnostico();
                } else {
                    Swal.fire('Error', res.error || 'No se pudo completar la acción', 'error');
                }
            },
            error: function () {
                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
            }
        });
    }

    function guardarTipoOperacion() {
        const tipo = $('#selectTipoOperacion').val();
        const nombre = diagPerfiles.tipos[tipo] || tipo;
        Swal.fire({
            title: `¿Marcar la empresa como "${nombre}"?`,
            html: 'Cambia los perfiles predefinidos que se ofrecen. Los perfiles que la empresa ya tiene no se modifican.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, guardar',
            cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (result.isConfirmed) {
                postAccionPerfiles({ action: 'guardar_tipo_operacion', tipo_operacion: tipo }, 'Tipo de operación guardado');
            } else {
                $('#selectTipoOperacion').val(tipoOperacionGuardado);
                $('#btnGuardarTipoOperacion').prop('disabled', true);
            }
        });
    }

    function crearPerfilesPredefinidos() {
        const faltantes = diagPerfiles.faltantes;
        if (!faltantes.length) return;
        let html = '<div class="text-start">';
        faltantes.forEach(p => {
            html += `<div class="form-check"><input class="form-check-input chk-perfil-predef" type="checkbox" value="${p.perfil_id}" id="chkPerf${p.perfil_id}" checked>
                     <label class="form-check-label" for="chkPerf${p.perfil_id}">${escapeHtml(p.perfil_nombre)} <small class="text-muted">(${p.funciones} funciones)</small></label></div>`;
        });
        html += '<small class="text-muted d-block mt-2">Cada perfil se crea con acceso a todas las sucursales. Después asigne los usuarios.</small></div>';
        Swal.fire({
            title: 'Crear perfiles predefinidos',
            html: html,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Crear seleccionados',
            cancelButtonText: 'Cancelar',
            preConfirm: function () {
                const ids = $('.chk-perfil-predef:checked').map(function () { return $(this).val(); }).get();
                if (!ids.length) {
                    Swal.showValidationMessage('Seleccione al menos un perfil');
                }
                return ids;
            }
        }).then(function (result) {
            if (result.isConfirmed) {
                postAccionPerfiles({ action: 'crear_perfiles_predefinidos', perfil_ids: result.value }, 'Perfiles creados');
            }
        });
    }

    function quitarFuncionesBienes() {
        const bienes = diagPerfiles.bienes;
        if (!bienes.length) return;
        let html = '<div class="text-start">';
        bienes.forEach(b => {
            // "Acceso total" queda destildado por defecto: es el perfil para ver todo el sistema.
            const marcado = b.empresa_perfil_nombre === 'Acceso total' ? '' : 'checked';
            html += `<div class="form-check"><input class="form-check-input chk-perfil-bienes" type="checkbox" value="${b.empresa_perfil_id}" id="chkPB${b.empresa_perfil_id}" ${marcado}>
                     <label class="form-check-label" for="chkPB${b.empresa_perfil_id}">${escapeHtml(b.empresa_perfil_nombre)} <small class="text-muted">(${b.funciones} funciones)</small></label></div>`;
        });
        html += '<small class="text-muted d-block mt-2">Las funciones quedan destildadas (no se borran): se pueden volver a asignar en Perfiles - Funciones.</small></div>';
        Swal.fire({
            title: 'Ocultar pedidos, remitos y stock',
            html: html,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Quitar funciones',
            cancelButtonText: 'Cancelar',
            preConfirm: function () {
                const ids = $('.chk-perfil-bienes:checked').map(function () { return $(this).val(); }).get();
                if (!ids.length) {
                    Swal.showValidationMessage('Seleccione al menos un perfil');
                }
                return ids;
            }
        }).then(function (result) {
            if (result.isConfirmed) {
                postAccionPerfiles({ action: 'quitar_funciones_bienes', empresa_perfil_ids: result.value }, 'Perfiles actualizados');
            }
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
    }

    // 8. Alta de empresa nueva desde la Constancia de Inscripción de ARCA, leída con IA
    //    (assets/js/constancia-cuit-ia.js + acción leer_constancia; la IA es la de la empresa activa).
    //    El usuario revisa los datos en el modal y "Crear empresa" llama a alta_empresa_constancia.
    const modalAltaEl = document.getElementById('modalAltaEmpresa');
    const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    let combosAltaPromesa = null;

    function getModalAlta() {
        if (window.bootstrap && bootstrap.Modal) {
            return bootstrap.Modal.getOrCreateInstance(modalAltaEl);
        }
        return {
            show: function () { $('#modalAltaEmpresa').modal('show'); },
            hide: function () { $('#modalAltaEmpresa').modal('hide'); }
        };
    }

    $('#btnAltaEmpresaConstancia').on('click', leerConstanciaAlta);
    $('#btnReleerConstancia').on('click', function () {
        getModalAlta().hide();
        setTimeout(leerConstanciaAlta, 300);
    });
    $('#altaProvincia').on('change', function () {
        cargarLocalidadesAlta($(this).val(), null);
    });
    $('#btnGuardarAltaEmpresa').on('click', guardarAltaEmpresa);

    function leerConstanciaAlta() {
        if (!window.ConstanciaCuitIa) {
            Swal.fire('No disponible', 'No se cargó el lector de constancias. Recargá la página.', 'error');
            return;
        }
        ConstanciaCuitIa.leer({
            url: AJAX_URL,
            extraHtml: function (d) {
                return d.empresa_existente
                    ? `<div class="alert alert-warning py-2 small text-start">Ya existe la empresa <strong>${escapeHtml(d.empresa_existente.empresa)}</strong>`
                      + ` (#${escapeHtml(d.empresa_existente.empresa_id)}) con este CUIT: no se va a poder crear otra.</div>`
                    : '';
            },
            completar: abrirModalAlta
        });
    }

    /** Combos del modal (una sola vez por página). */
    function cargarCombosAlta() {
        if (!combosAltaPromesa) {
            combosAltaPromesa = $.ajax({ url: AJAX_URL, type: 'GET', dataType: 'json', data: { action: 'obtener_combos_alta', empresa_id: empresaActivaId } })
                .then(function (r) {
                    if (!r || !r.success) return $.Deferred().reject().promise();
                    const c = r.data;
                    const opciones = (lista, id, texto, vacio) => (vacio ? `<option value="">${vacio}</option>` : '')
                        + lista.map((x) => `<option value="${escapeHtml(x[id])}">${escapeHtml(texto(x))}</option>`).join('');
                    $('#altaCondicion').html(opciones(c.condiciones, 'condicion_fiscal_id', (x) => x.condicion_fiscal, 'Elegí...'));
                    $('#altaProvincia').html(opciones(c.provincias, 'provincia_id', (x) => x.provincia, 'Elegí...'));
                    $('#altaIibbRegimen').html(opciones(c.iibb_regimenes, 'iibb_regimen_id', (x) => x.iibb_regimen_nombre, ''));
                    $('#altaJurisdiccionSede').html(opciones(c.jurisdicciones_sedes, 'jurisdiccion_sede_id',
                        (x) => (x.codigo_comarb ? x.codigo_comarb + ' - ' : '') + x.jurisdiccion_sede, 'Sin definir'));
                    $('#altaMesCierre').html(MESES.map((m, i) => `<option value="${i + 1}">${m}</option>`).join(''));
                });
            combosAltaPromesa.fail(function () { combosAltaPromesa = null; });
        }
        return combosAltaPromesa;
    }

    function cargarLocalidadesAlta(provinciaId, localidadId) {
        const $loc = $('#altaLocalidad');
        if (!provinciaId) {
            $loc.html('<option value="">Elegí la provincia</option>');
            return $.Deferred().resolve().promise();
        }
        $loc.html('<option value="">Cargando...</option>');
        return $.ajax({ url: AJAX_URL, type: 'GET', dataType: 'json', data: { action: 'obtener_localidades', provincia_id: provinciaId, empresa_id: empresaActivaId } })
            .done(function (r) {
                const lista = (r && r.success) ? r.data : [];
                $loc.html('<option value="">Elegí...</option>' + lista.map((l) =>
                    `<option value="${escapeHtml(l.localidad_id)}">${escapeHtml(l.localidad)}${l.cp ? ' (' + escapeHtml(l.cp) + ')' : ''}</option>`).join(''));
                if (localidadId) $loc.val(String(localidadId));
            });
    }

    /** Completa el modal con lo leído de la constancia (r.data de leer_constancia) y lo abre. */
    function abrirModalAlta(d) {
        cargarCombosAlta().done(function () {
            const $f = $('#formAltaEmpresa');
            $f[0].reset();
            $('#altaRazonSocial').val(d.razon_social || '');
            $('#altaCuit').val(d.cuit || '');
            $('#altaCondicion').val(d.condicion_fiscal_id ? String(d.condicion_fiscal_id) : '');
            $('#altaTipoOperacion').val('MIXTA');
            $('#altaDomicilio').val(d.domicilio || '');
            $('#altaIibbRegimen').val('1');
            $('#altaJurisdiccionSede').val(d.jurisdiccion_sede_id ? String(d.jurisdiccion_sede_id) : '');
            $('#altaInicioActividades').val(d.inicio_actividades || '');
            $('#altaMesCierre').val(String(d.mes_cierre_ejercicio || 12));
            $('#altaProvincia').val(d.provincia_id ? String(d.provincia_id) : '');
            cargarLocalidadesAlta(d.provincia_id, d.localidad_id);

            const avisos = (d.avisos || []).map((a) => `<li>${escapeHtml(a)}</li>`).join('');
            $('#altaAvisos').html(
                (d.empresa_existente
                    ? `<div class="alert alert-warning py-2 small">Ya existe la empresa <strong>${escapeHtml(d.empresa_existente.empresa)}</strong> (#${escapeHtml(d.empresa_existente.empresa_id)}) con este CUIT.</div>`
                    : '')
                + (avisos ? `<div class="alert alert-light border py-2 small mb-3"><ul class="mb-0 ps-3">${avisos}</ul></div>` : '')
                + '<div class="small text-muted mb-2"><i class="fas fa-robot me-1"></i>Datos leídos con IA de la constancia: revisalos antes de crear la empresa.</div>'
            );
            getModalAlta().show();
        }).fail(function () {
            Swal.fire('No se pudo completar', 'No se pudieron cargar las listas del formulario.', 'error');
        });
    }

    function guardarAltaEmpresa() {
        const faltan = [];
        if (!$('#altaRazonSocial').val().trim()) faltan.push('la razón social');
        if ($('#altaCuit').val().replace(/\D/g, '').length !== 11) faltan.push('el CUIT (11 dígitos)');
        if (!$('#altaCondicion').val()) faltan.push('la condición frente al IVA');
        if (!$('#altaLocalidad').val()) faltan.push('la provincia y la localidad');
        if (faltan.length) {
            Swal.fire('Revisá los datos', 'Falta ' + faltan.join(', ') + '.', 'warning');
            return;
        }

        const datos = $('#formAltaEmpresa').serializeArray();
        datos.push({ name: 'action', value: 'alta_empresa_constancia' }, { name: 'empresa_id', value: empresaActivaId });
        if (!$('#altaCopiarDesdeActual').is(':checked')) datos.push({ name: 'copiar_desde_actual', value: '0' });

        const $btn = $('#btnGuardarAltaEmpresa').prop('disabled', true);
        $.ajax({ url: AJAX_URL, type: 'POST', dataType: 'json', data: $.param(datos) })
            .done(function (r) {
                if (r && r.success) {
                    getModalAlta().hide();
                    mostrarResultadoAlta(r);
                    return;
                }
                const ex = r && r.data && r.data.empresa_existente;
                if (ex && ex.accesible) {
                    Swal.fire({
                        title: 'La empresa ya existe', text: r.message, icon: 'info',
                        showCancelButton: true, confirmButtonText: 'Abrir esa empresa', cancelButtonText: 'Cerrar'
                    }).then(function (res) {
                        if (res.isConfirmed) window.location.href = `empresa_inicio.php?empresa_id=${ex.empresa_id}`;
                    });
                    return;
                }
                Swal.fire('No se pudo crear la empresa', (r && (r.message || r.error)) || 'Error desconocido.', 'warning');
            })
            .fail(function (xhr) {
                const msg = xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error);
                Swal.fire('No se pudo crear la empresa', msg || 'No se pudo comunicar con el servidor.', 'error');
            })
            .always(function () { $btn.prop('disabled', false); });
    }

    /** Pasos del alta y paso al asistente de la empresa nueva (con la empresa activa como origen de la copia). */
    function mostrarResultadoAlta(r) {
        const d = r.data;
        const pasos = (d.pasos || []).map((p) => `<li class="${p.ok ? '' : 'text-warning-emphasis'}">`
            + `<i class="fas ${p.ok ? 'fa-check text-success' : 'fa-exclamation-triangle text-warning'} me-1"></i>`
            + `<strong>${escapeHtml(p.paso)}:</strong> ${escapeHtml(p.detalle)}</li>`).join('');
        Swal.fire({
            title: r.message,
            html: `<ul class="list-unstyled text-start small mb-0">${pasos}</ul>`,
            icon: (d.pasos || []).every((p) => p.ok) ? 'success' : 'warning',
            confirmButtonText: 'Configurar la empresa nueva',
            allowOutsideClick: false
        }).then(function () {
            const origen = d.origen_copia_id ? `&origen_id=${d.origen_copia_id}` : '';
            window.location.href = `empresa_inicio.php?empresa_id=${d.empresa_id}${origen}`;
        });
    }

    // Carga inicial
    cargarDiagnosticoYAutorizaciones();
});
