/**
 * JavaScript: Gestión del Cliente (cliente_gestion.js) - Módulo 4 Estudio Contable, página 422.
 * Usa cliente_gestion_ajax.php (solo lectura). Cada solapa se carga la primera vez que se abre.
 */
$(function () {
    const URL = 'cliente_gestion_ajax.php';
    const NIVELES = { PADRON: 'Padrón', FISCAL: 'Fiscal', CONTABLE: 'Contable', GESTION: 'Gestión completa' };
    const ORIGENES = { LOCAL: 'Este ERP', REMOTO: 'Servidor del cliente', EXTERNO: 'Otro software' };
    const MODOS = { ERP: 'Empresa en el ERP', MANUAL: 'Carga en Mini-Gestión', REMOTO: 'Servidor del cliente', EXTERNO: 'Otro software' };
    const AREAS = { ventas: 'Ventas', compras: 'Compras', impuestos: 'Impuestos', contabilidad: 'Contabilidad', tesoreria: 'Tesorería' };
    const INSP = {
        ABIERTA: 'Abierta', EN_DESCARGO: 'En descargo', EN_APELACION: 'En apelación', PLAN_PAGOS: 'Plan de pagos',
        CERRADA_SIN_AJUSTE: 'Cerrada sin ajuste', CERRADA_CON_AJUSTE: 'Cerrada con ajuste', PRESCRIPTA: 'Prescripta'
    };
    const ACCESOS = {
        padron: ['bi-pencil-square', 'Editar en el Padrón'], vencimientos: ['bi-calendar-check', 'Vencimientos'],
        tareas: ['bi-list-check', 'Tareas'], honorarios: ['bi-receipt', 'Facturar abonos'], cobranzas: ['bi-cash-coin', 'Cobrar en Gestión'], empresa: ['bi-building', 'Abrir su empresa en Gestión'], inspecciones: ['bi-search', 'Inspecciones'],
        minigestion: ['bi-calculator', 'Mini-Gestión e importar de ARCA'], cierres: ['bi-lock', 'Cierres contables'], contabilidad: ['bi-journal-text', 'Contabilidad']
    };

    let clienteId = 0;
    let cargadas = {};

    function esc(t) { return $('<div>').text(t == null ? '' : String(t)).html(); }
    function fecha(f) { return f ? Fmt.fecha(f) : '—'; }
    function plata(v) { return Fmt.moneda(Number(v || 0)); }
    function vacio($tb, cols, txt) { $tb.html('<tr><td colspan="' + cols + '" class="text-center text-muted py-3">' + esc(txt) + '</td></tr>'); }
    function error($tb, cols, txt) { $tb.html('<tr><td colspan="' + cols + '" class="text-center text-danger py-3">' + esc(txt) + '</td></tr>'); }
    function get(accion) {
        return $.getJSON(URL, { accion: accion, cliente_id: clienteId }).fail(function () {
            UiSimple.error('Revisá la conexión e intentá de nuevo.', 'No se pudo cargar');
        });
    }
    function hoyIso() { const d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
    function aviso($el, r) {
        $el.text(r.message || 'No se pudo cargar.').removeClass('d-none');
    }

    // ---------- Clientes ----------
    $.getJSON(URL, { accion: 'clientes' }).done(function (r) {
        if (!r.success) { UiSimple.error(r.message); return; }
        const $s = $('#cgCliente');
        r.data.forEach(function (c) {
            $s.append($('<option>').val(c.cliente_id).text(c.razon_social + ' · ' + (NIVELES[c.nivel_servicio] || c.nivel_servicio)));
        });
        const id = EstudioCliente.inicial();
        if (id > 0 && $s.find('option[value="' + id + '"]').length) { $s.val(String(id)).trigger('change'); }
    });

    $('#cgCliente').on('change', function () {
        clienteId = parseInt($(this).val() || '0', 10);
        EstudioCliente.guardar(clienteId);
        cargadas = {};
        if (!clienteId) { $('#cgFicha').addClass('d-none'); $('#cgVacio').removeClass('d-none'); return; }
        try { history.replaceState(null, '', location.pathname + '?cliente_id=' + clienteId); } catch (e) { /* sin historial */ }
        $('#cgSolapas button[data-bs-target="#sResumen"]').tab('show');
        cargarResumen();
    });

    $('#cgSolapas').on('shown.bs.tab', 'button', function () {
        const solapa = $(this).closest('li').data('solapa');
        if (!cargadas[solapa] && CARGAS[solapa]) { cargadas[solapa] = true; CARGAS[solapa](); }
    });

    // ---------- Resumen ----------
    function cargarResumen() {
        get('resumen').done(function (r) {
            if (!r.success) { UiSimple.error(r.message); return; }
            cargadas.resumen = true;
            const d = r.data, c = d.cliente, k = d.kpis;
            $('#cgVacio').addClass('d-none');
            $('#cgFicha').removeClass('d-none');

            $('#cgRazon').text(c.razon_social);
            $('#cgDatos').text('CUIT ' + c.cuit + ' · ' + (c.condicion_fiscal || '') + ' · ' + c.tipo_cliente);
            $('#cgBadges').html(
                '<span class="badge text-bg-primary me-1">' + esc(NIVELES[c.nivel_servicio] || c.nivel_servicio) + '</span>' +
                '<span class="badge text-bg-secondary me-1">' + esc(MODOS[c.modo] || c.modo) + '</span>' +
                (c.empresa_vinculada ? '<span class="badge text-bg-light border">' + esc(c.empresa_vinculada) + '</span>' : ''));

            // Solapas según el servicio
            $('#cgSolapas li').each(function () {
                $(this).toggleClass('d-none', d.solapas.indexOf($(this).data('solapa')) < 0);
            });

            // Accesos a las páginas del estudio (solo las que el usuario puede abrir)
            const $a = $('#cgAccesos').empty();
            Object.keys(d.accesos).forEach(function (key) {
                const def = ACCESOS[key] || ['bi-box-arrow-up-right', key];
                $a.append('<a class="btn btn-sm btn-outline-secondary" href="' + esc(d.accesos[key]) + '"><i class="bi ' + def[0] + ' me-1"></i>' + esc(def[1]) + '</a>');
            });

            // Permisos que dio la empresa del cliente
            if (c.empresa_vinculada_id > 0) {
                const partes = Object.keys(AREAS).map(function (a) {
                    const v = d.vinculacion[a];
                    const cls = v === 'ESCRITURA' ? 'text-bg-success' : (v === 'LECTURA' ? 'text-bg-info' : 'text-bg-light border text-muted');
                    const txt = v === 'ESCRITURA' ? 'lee y escribe' : (v === 'LECTURA' ? 'solo lee' : 'sin permiso');
                    return '<span class="badge ' + cls + ' me-1" title="' + txt + '">' + AREAS[a] + '</span>';
                });
                $('#cgVinculacion').html('<span class="text-muted me-1">Permisos de su empresa al estudio ' + ayudaVinculacion() + ':</span>' + partes.join('')).removeClass('d-none');
            } else {
                $('#cgVinculacion').addClass('d-none');
            }

            $('#kVencidos').text(k.venc_vencidos).toggleClass('text-danger', k.venc_vencidos > 0);
            $('#kProximo').text(k.proximo_venc ? 'Próximo: ' + fecha(k.proximo_venc) + ' · ' + k.venc_proximos + ' en 15 días' : 'Sin vencimientos próximos');
            $('#kTareas').text(k.tareas_abiertas);
            $('#kTareasAtr').text(k.tareas_atrasadas > 0 ? k.tareas_atrasadas + ' atrasada(s)' : 'Al día').toggleClass('text-danger', k.tareas_atrasadas > 0);
            $('#kInsp').text(k.inspecciones_abiertas);
            $('#kAbono').text(plata(k.abono_mensual));

            const filas = [
                ['Servicio', NIVELES[c.nivel_servicio] || c.nivel_servicio],
                ['Origen de los datos', ORIGENES[c.origen_datos] || c.origen_datos],
                ['Responsable / aprueba', (c.responsable_r || '—') + ' / ' + (c.responsable_a || '—')],
                ['Ingresos Brutos', (c.ingresos_brutos_tipo || '').replace('_', ' ') +
                    (d.jurisdicciones.length ? ' · ' + d.jurisdicciones.map(function (j) { return j.jurisdiccion_nombre; }).join(', ') : '')],
                ['Cierre de ejercicio', 'Mes ' + c.mes_cierre_ejercicio],
                ['Empleador', Number(c.es_empleador) ? 'Sí · ' + c.cantidad_empleados + ' empleado(s)' : 'No'],
            ];
            if (c.origen_datos === 'REMOTO' && c.remoto_url) { filas.push(['ERP del cliente', c.remoto_url]); }
            if (c.observaciones) { filas.push(['Observaciones', c.observaciones]); }
            $('#cgFichaDatos').html(filas.map(function (f) {
                return '<dt class="col-sm-4 col-lg-3 text-muted fw-normal">' + esc(f[0]) + '</dt><dd class="col-sm-8 col-lg-9">' + esc(f[1]) + '</dd>';
            }).join(''));
        });
    }
    function ayudaVinculacion() {
        return '<i class="bi bi-question-circle" title="Se otorgan desde la empresa del cliente, en Vinculaciones entre empresas."></i>';
    }

    // ---------- Solapas ----------
    const CARGAS = {
        vencimientos: function () {
            get('vencimientos').done(function (r) {
                const $a = $('#tbVencAbiertos'), $c = $('#tbVencCerrados');
                if (!r.success) { error($a, 5, r.message); $c.empty(); return; }
                const hoy = hoyIso();
                if (!r.data.abiertos.length) { vacio($a, 5, 'Sin vencimientos pendientes.'); }
                else {
                    $a.html(r.data.abiertos.map(function (v) {
                        const vencido = v.f_limite < hoy;
                        return '<tr' + (vencido ? ' class="table-danger"' : '') + '><td>' + esc(fecha(v.f_limite)) + '</td>' +
                            '<td>' + esc(v.codigo_oficial + ' - ' + v.obligacion) + (v.jurisdiccion_nombre ? '<br><small class="text-muted">' + esc(v.jurisdiccion_nombre) + '</small>' : '') + '</td>' +
                            '<td>' + esc(v.periodo_fiscal) + '</td><td>' + esc(v.estado) + '</td>' +
                            '<td class="text-end">' + (Number(v.importe_a_pagar) ? esc(plata(v.importe_a_pagar)) : '—') + '</td></tr>';
                    }).join(''));
                }
                if (!r.data.cerrados.length) { vacio($c, 6, 'Todavía no hay presentaciones registradas.'); }
                else {
                    $c.html(r.data.cerrados.map(function (v) {
                        return '<tr><td>' + esc(fecha(v.f_limite)) + '</td><td>' + esc(v.codigo_oficial + ' - ' + v.obligacion) + '</td>' +
                            '<td>' + esc(v.periodo_fiscal) + '</td><td>' + esc(v.estado) + '</td>' +
                            '<td>' + esc(fecha(v.f_presentacion)) + '</td><td>' + esc(fecha(v.f_pago)) + '</td></tr>';
                    }).join(''));
                }
            });
        },
        tareas: function () {
            get('tareas').done(function (r) {
                const $t = $('#tbTareas');
                if (!r.success) { error($t, 6, r.message); return; }
                if (!r.data.length) { vacio($t, 6, 'Sin tareas abiertas.'); return; }
                const hoy = hoyIso();
                $t.html(r.data.map(function (t) {
                    const atr = t.f_limite && t.f_limite < hoy;
                    return '<tr' + (atr ? ' class="table-danger"' : '') + '><td>' + esc(t.titulo) + '</td><td>' + esc(fecha(t.f_limite)) + '</td>' +
                        '<td>' + esc(t.estado) + '</td><td>' + esc(t.responsable || '—') + '</td><td>' + esc(t.aprobador || '—') + '</td>' +
                        '<td class="text-end">' + esc(Fmt.numero(Number(t.horas_reales || 0), 1)) + ' / ' + esc(Fmt.numero(Number(t.horas_estimadas || 0), 1)) + '</td></tr>';
                }).join(''));
            });
        },
        honorarios: function () {
            get('honorarios').done(function (r) {
                if (!r.success) { error($('#tbAbonos'), 3, r.message); return; }
                const d = r.data;
                $('#kHonSaldo').text(plata(d.saldo)).toggleClass('text-danger', d.saldo > 0);
                if (!d.abonos.length) { vacio($('#tbAbonos'), 3, 'Sin abonos.'); }
                else {
                    $('#tbAbonos').html(d.abonos.map(function (a) {
                        return '<tr><td>' + esc(a.concepto || '—') + '</td><td class="text-end">' + esc(plata(a.importe_base)) + '</td><td>' + esc(a.ultimo_periodo_facturado || '—') + '</td></tr>';
                    }).join(''));
                }
                if (!d.novedades.length) { vacio($('#tbNovedades'), 3, 'Sin novedades pendientes.'); }
                else {
                    $('#tbNovedades').html(d.novedades.map(function (n) {
                        return '<tr><td>' + esc(n.periodo) + '</td><td>' + esc(n.concepto || n.descripcion || '—') + '</td><td class="text-end">' + esc(plata(n.importe_total)) + '</td></tr>';
                    }).join(''));
                }
                if (!d.facturas.length) { vacio($('#tbFactHon'), 5, 'El estudio todavía no le facturó.'); }
                else {
                    $('#tbFactHon').html(d.facturas.map(function (f) {
                        return '<tr><td>' + esc(fecha(f.f_emision)) + '</td><td>' + esc(f.tipo + ' ' + f.numero) + '</td><td>' + esc(f.estado) + '</td>' +
                            '<td class="text-end">' + esc(plata(f.importe_total)) + '</td><td class="text-end">' + esc(plata(f.importe_pendiente)) + '</td></tr>';
                    }).join(''));
                }
            });
        },
        inspecciones: function () {
            get('inspecciones').done(function (r) {
                const $t = $('#tbInsp');
                if (!r.success) { error($t, 6, r.message); return; }
                if (!r.data.length) { vacio($t, 6, 'Sin inspecciones.'); return; }
                $t.html(r.data.map(function (i) {
                    const plazo = i.plazos_vencidos > 0 ? '<span class="badge text-bg-danger">' + i.plazos_vencidos + ' vencido(s)</span>' : esc(fecha(i.proximo_plazo));
                    return '<tr><td>' + esc(i.organismo) + '<br><small class="text-muted">' + esc(i.numero_acta || '') + '</small></td>' +
                        '<td>' + esc(fecha(i.f_inicio)) + '</td><td>' + esc(INSP[i.estado_proceso] || i.estado_proceso) + '</td>' +
                        '<td class="text-end">' + esc(plata(i.monto_reclamado)) + '</td><td class="text-end">' + esc(plata(i.monto_pagado)) + '</td><td>' + plazo + '</td></tr>';
                }).join(''));
            });
        },
        fiscal: function () {
            get('fiscal').done(function (r) {
                const $t = $('#tbFiscal');
                $('#fiscalAviso').addClass('d-none');
                if (!r.success) { aviso($('#fiscalAviso'), r); $t.empty(); return; }
                const d = r.data;
                if (d.aviso) { $('#fiscalAviso').text(d.aviso).removeClass('d-none'); $t.empty(); return; }
                if (d.compras_borrador > 0) {
                    $('#fiscalAviso').text('Hay ' + d.compras_borrador + ' factura(s) de compra en Borrador: no suman crédito fiscal hasta confirmarlas (Mini-Gestión > Importar de ARCA > Confirmar seleccionadas).').removeClass('d-none');
                }
                $t.html(d.periodos.slice().reverse().map(function (p) {
                    if (p.error) { return '<tr><td>' + esc(p.periodo) + '</td><td colspan="8" class="text-danger">' + esc(p.error) + '</td></tr>'; }
                    const estado = p.liquidado ? '<span class="badge text-bg-success">Liquidado</span>'
                        : ((p.cant_ventas + p.cant_compras) ? '<span class="badge text-bg-warning">Sin liquidar</span>' : '<span class="text-muted">Sin movimientos</span>');
                    return '<tr><td>' + esc(p.periodo) + '</td><td class="text-end">' + esc(plata(p.debito)) + '</td><td class="text-end">' + esc(plata(p.credito)) + '</td>' +
                        '<td class="text-end">' + esc(plata(p.percepciones)) + '</td><td class="text-end">' + esc(plata(p.saldo_favor_anterior)) + '</td>' +
                        '<td class="text-end fw-bold">' + esc(plata(p.a_pagar)) + '</td><td class="text-end">' + esc(plata(p.a_favor)) + '</td>' +
                        '<td class="text-center">' + p.cant_ventas + ' / ' + p.cant_compras + '</td><td>' + estado + '</td></tr>';
                }).join(''));
            });
        },
        contable: function () {
            get('contable').done(function (r) {
                $('#contableAviso').addClass('d-none');
                $('#contableDatos').addClass('d-none');
                if (!r.success) { aviso($('#contableAviso'), r); return; }
                const d = r.data;
                if (d.aviso) { $('#contableAviso').text(d.aviso).removeClass('d-none'); return; }
                $('#contableDatos').removeClass('d-none');
                $('#kResultado').text(plata(d.resultado)).toggleClass('text-danger', d.resultado < 0);
                $('#kEjercicio').text(d.ejercicio);
                $('#kAsientos').text(d.asientos);
                $('#kAsientosSub').text((d.asientos_borrador ? d.asientos_borrador + ' en borrador · ' : '') + (d.ultimo_asiento ? 'último ' + fecha(d.ultimo_asiento) : 'sin asientos'));
                $('#kMesesCerrados').text(d.meses_cerrados.length + ' de ' + d.meses_ejercicio);
                $('#kMesesSub').text(d.meses_cerrados.length ? 'hasta ' + d.meses_cerrados[d.meses_cerrados.length - 1] : 'ninguno cerrado');
                if (!d.rubros.length) { vacio($('#tbRubros'), 2, 'Sin asientos vigentes en el ejercicio.'); return; }
                $('#tbRubros').html(d.rubros.map(function (x) {
                    return '<tr><td>' + esc(x.rubro + ' - ' + x.nombre) + '</td><td class="text-end">' + esc(plata(x.saldo)) + '</td></tr>';
                }).join(''));
            });
        },
        gestion: function () {
            get('gestion').done(function (r) {
                $('#gestionAviso').addClass('d-none');
                $('#gestionDatos').addClass('d-none');
                if (!r.success) { aviso($('#gestionAviso'), r); return; }
                const d = r.data;
                if (d.aviso) { $('#gestionAviso').text(d.aviso).removeClass('d-none'); return; }
                $('#gestionDatos').removeClass('d-none');
                const kpi = function (titulo, b, pendiente) {
                    if (!b) {
                        return '<div class="col-6 col-lg-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">' + esc(titulo) + '</div>' +
                            '<div class="ui-kpi-valor text-muted">—</div><div class="ui-kpi-sub">sin permiso de su empresa</div></div></div>';
                    }
                    return '<div class="col-6 col-lg-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">' + esc(titulo) + ' ' + esc(d.mes) + '</div>' +
                        '<div class="ui-kpi-valor">' + esc(plata(b.mes_neto)) + '</div>' +
                        '<div class="ui-kpi-sub">' + b.mes_cantidad + ' comprob. · mes anterior ' + esc(plata(b.anterior_neto)) + (b.borradores ? ' · ' + b.borradores + ' en borrador' : '') + '</div></div></div>' +
                        '<div class="col-6 col-lg-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">' + esc(pendiente) + '</div>' +
                        '<div class="ui-kpi-valor">' + esc(plata(b.pendiente)) + '</div>' +
                        '<div class="ui-kpi-sub' + (b.vencidas ? ' text-danger' : '') + '">' + (b.vencidas ? b.vencidas + ' vencida(s)' : 'nada vencido') + '</div></div></div>';
                };
                $('#gestionKpis').html(kpi('Ventas netas', d.ventas, 'A cobrar') + kpi('Compras netas', d.compras, 'A pagar'));
                if (d.tesoreria === null) { $('#gestionTesoreria').addClass('d-none'); return; }
                $('#gestionTesoreria').removeClass('d-none');
                if (!d.tesoreria.length) { vacio($('#tbTesoreria'), 4, 'Sin cuentas de tesorería.'); return; }
                $('#tbTesoreria').html(d.tesoreria.map(function (t) {
                    return '<tr><td>' + esc(t.cuenta_tipo) + '</td><td>' + esc(t.tesoreria_cuenta) + '</td><td>' + esc(t.moneda || '') + '</td>' +
                        '<td class="text-end">' + esc(Fmt.numero(Number(t.saldo_actual || 0), 2)) + '</td></tr>';
                }).join(''));
            });
        }
    };
});
