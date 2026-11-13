/**
 * Bandeja de Documentos (Gestión 141, Gestión Normal 641). Vista: templates/bandeja/bandeja_documentos_vista.php.
 * Endpoint: window.BDJ_AJAX (bandeja_documentos_ajax.php del módulo); window.BDJ_CSRF para las escrituras.
 * Subir solo guarda el archivo; la lectura va de a uno ("Leer los que faltan" o "Leer" en la fila).
 * - "Revisar" en una factura abre el modal #modalBdjRevisarFactura con facturas_proveedores.php?lectura_adjunto=N&embed=1
 *   sin salir de la Bandeja de Documentos.
 * - "Revisar" en un extracto bancario abre el asistente #modalBdjExtracto para importar los movimientos en la cuenta,
 *   clasificar conceptos y generar el Resumen Bancario (C_RB) directamente desde la Bandeja.
 */
window.Bandeja = (function () {
    const AJAX = window.BDJ_AJAX || 'bandeja_documentos_ajax.php';
    const MAX_BYTES = 15 * 1024 * 1024;
    const EXT_FACTURA = ['pdf', 'jpg', 'jpeg', 'png'];
    const EXT_EXTRACTO = ['pdf', 'xls', 'xlsx', 'csv', 'txt', 'jpg', 'jpeg', 'png'];
    const ESTADOS = {
        RECIBIDO: ['Sin leer', 'bg-secondary-subtle text-secondary-emphasis'],
        LEYENDO: ['Leyendo', 'bg-info-subtle text-info-emphasis'],
        LISTO: ['Listo', 'bg-success-subtle text-success-emphasis'],
        REVISAR: ['Para revisar', 'bg-warning-subtle text-warning-emphasis'],
        ERROR: ['No se pudo leer', 'bg-danger-subtle text-danger-emphasis'],
        PROCESADO: ['Guardado', 'bg-primary-subtle text-primary-emphasis'],
        DESCARTADO: ['Descartado', 'bg-light text-muted border'],
        DUPLICADO: ['Repetido', 'bg-light text-muted border']
    };
    const ORIGENES = { PANTALLA: 'Pantalla', CARPETA: 'Carpeta de entrada', PORTAL: 'Portal / Cliente', ANTERIOR: 'Anterior a la bandeja' };
    let tabla = null;
    let vista = 'pendientes';
    let filas = [];
    let ocupado = false;
    let opciones = { puede_guardar: false, sucursales: [], ia: {} };

    // Estado del asistente de Extracto Bancario en Bandeja
    let extState = {
        id: 0,
        cuentaId: 0,
        mes: '',
        filtroMovs: 'TODOS',
        excluidos: {},
        conceptosPorFila: {},
        data: null
    };

    // ------------------------------------------------------------------ utilidades
    function esc(t) { return $('<div>').text(t == null ? '' : String(t)).html(); }
    function msgXhr(xhr) {
        try { const j = JSON.parse(xhr.responseText); return j.message || j.error; } catch (e) { return null; }
    }
    function get(accion, params) {
        return $.ajax({ url: AJAX, method: 'GET', dataType: 'json', data: Object.assign({ accion: accion }, params || {}) })
            .then(r => r.success ? r.data : $.Deferred().reject(r.message).promise(),
                  xhr => $.Deferred().reject(msgXhr(xhr)).promise());
    }
    function post(accion, data) {
        const opts = { url: AJAX + '?accion=' + encodeURIComponent(accion), method: 'POST', dataType: 'json',
                       headers: { 'X-CSRF-Token': window.BDJ_CSRF || '' } };
        if (data instanceof FormData) {
            data.append('accion', accion);
            Object.assign(opts, { data: data, processData: false, contentType: false });
        } else {
            opts.data = Object.assign({ accion: accion }, data || {});
        }
        return $.ajax(opts).then(r => r.success ? r : $.Deferred().reject(r.message).promise(),
                                 xhr => $.Deferred().reject(msgXhr(xhr)).promise());
    }
    function urlArchivo(id, descargar) {
        return AJAX + '?accion=archivo&id=' + encodeURIComponent(id) + (descargar ? '&descargar=1' : '');
    }
    function progreso(t) { $('#bdjProgreso').text(t || ''); }
    function elegidos() {
        return $('#tablaBandeja .bdj-elegir:checked').map(function () { return parseInt($(this).val(), 10); }).get();
    }
    function fila(id) { return filas.find(f => parseInt(f.bandeja_documento_id, 10) === id); }
    function extArchivo(nombre) {
        const m = /\.([a-z0-9]+)$/i.exec(nombre || '');
        return m ? m[1].toLowerCase() : '';
    }
    function inferirTipoDocumento(file, tipoForzado) {
        if (tipoForzado === 'EXTRACTO' || tipoForzado === 'FACTURA_COMPRA') return tipoForzado;
        const ext = extArchivo(file.name);
        if (['xls', 'xlsx', 'csv', 'txt'].includes(ext)) return 'EXTRACTO';
        if (/extracto|movimientos|resumen[_\s-]*banc/i.test(file.name || '')) return 'EXTRACTO';
        return 'FACTURA_COMPRA';
    }

    // ------------------------------------------------------------------ carga
    function resumen() {
        get('resumen').then(d => {
            $('#kpiBdjPendientes').text(d.pendientes);
            $('#kpiBdjListos').text(d.listos);
            $('#kpiBdjRevisar').text(d.revisar);
            $('#kpiBdjProcesados').text(d.procesados_mes);
        });
    }

    function listar() {
        return get('listar', {
            vista: vista, desde: $('#fBdjDesde').val(), hasta: $('#fBdjHasta').val(),
            origen: $('#fBdjOrigen').val(), texto: $('#fBdjTexto').val()
        }).then(d => {
            filas = d || [];
            tabla.clear().rows.add(filas).draw();
            $('#bdjTodos').prop('checked', false);
            botonesLote();
            resumen();
        }, m => UiSimple.error(m || 'No se pudo cargar la bandeja.'));
    }

    function botonesLote() {
        const ids = elegidos();
        const listos = ids.filter(id => {
            const f = fila(id) || {};
            return f.estado === 'LISTO' && f.tipo_documento !== 'EXTRACTO';
        });
        const descartables = ids.filter(id => ['RECIBIDO', 'LISTO', 'REVISAR', 'ERROR', 'DUPLICADO'].includes((fila(id) || {}).estado));
        $('#btnBdjGuardar').prop('disabled', ocupado || !listos.length || !opciones.puede_guardar)
            .html('<i class="fas fa-save me-1"></i>Guardar en Borrador' + (listos.length ? ' (' + listos.length + ')' : ''));
        $('#btnBdjDescartar').prop('disabled', ocupado || !descartables.length);
        $('#btnBdjLeer').prop('disabled', ocupado || !filas.some(f => f.estado === 'RECIBIDO'));
    }

    // ------------------------------------------------------------------ acciones
    function subir(lista, tipoForzado) {
        const archivos = Array.prototype.slice.call(lista || []);
        const invalidos = archivos.filter(f => {
            const ext = extArchivo(f.name);
            const tipo = inferirTipoDocumento(f, tipoForzado);
            const permitidos = tipo === 'EXTRACTO' ? EXT_EXTRACTO : EXT_FACTURA;
            return !permitidos.includes(ext) || f.size > MAX_BYTES;
        });
        const validos = archivos.filter(f => !invalidos.includes(f));
        if (invalidos.length) {
            UiSimple.aviso(invalidos.length + ' archivo(s) no se suben: formato no admitido o supera los 15 MB.');
        }
        if (!validos.length) return;
        ocupado = true;
        botonesLote();
        let hechos = 0, repetidos = 0, errores = 0;
        const recibidos = [];
        validos.reduce((cadena, f) => cadena.then(() => {
            progreso('Subiendo ' + (hechos + 1) + ' de ' + validos.length + '…');
            const fd = new FormData();
            fd.append('archivo', f);
            fd.append('tipo_documento', inferirTipoDocumento(f, tipoForzado));
            return post('subir', fd).then(r => {
                if (r.data.estado === 'DUPLICADO') repetidos++; else recibidos.push(r.data.id);
            }, () => { errores++; }).always(() => { hechos++; });
        }), $.Deferred().resolve().promise()).always(() => {
            ocupado = false;
            progreso('');
            const partes = [recibidos.length + ' recibido(s)'];
            if (repetidos) partes.push(repetidos + ' ya estaban cargados');
            if (errores) partes.push(errores + ' no se pudieron subir');
            (errores ? UiSimple.aviso : UiSimple.ok)(partes.join(', ') + '.');
            listar().then(() => { if (recibidos.length) leer(recibidos); });
        });
    }

    /** Lee de a uno los documentos indicados (o todos los "Sin leer" de la lista). */
    function leer(ids) {
        ids = ids || filas.filter(f => f.estado === 'RECIBIDO').map(f => parseInt(f.bandeja_documento_id, 10));
        if (!ids.length) return;
        if (opciones.ia && opciones.ia.disponible === false) {
            UiSimple.aviso(opciones.ia.motivo || 'La lectura con IA no está disponible: los documentos quedan para revisar.');
        }
        ocupado = true;
        botonesLote();
        let n = 0;
        ids.reduce((cadena, id) => cadena.then(() => {
            n++;
            progreso('Leyendo ' + n + ' de ' + ids.length + '…');
            return post('leer', { id: id }).then(null, m => { console.warn('Bandeja leer', id, m); return $.Deferred().resolve().promise(); });
        }), $.Deferred().resolve().promise()).always(() => {
            ocupado = false;
            progreso('');
            listar();
        });
    }

    function cambiarTipo(id, nuevoTipo) {
        post('cambiar_tipo', { id: id, tipo_documento: nuevoTipo }).then(res => {
            UiSimple.ok(res.message);
            listar().then(() => leer([id]));
        }, m => UiSimple.error(m || 'No se pudo cambiar el tipo de documento.'));
    }

    function guardar() {
        const ids = elegidos().filter(id => {
            const f = fila(id) || {};
            return f.estado === 'LISTO' && f.tipo_documento !== 'EXTRACTO';
        });
        if (!ids.length) return;
        Swal.fire({
            icon: 'question', title: 'Guardar en Borrador',
            text: 'Se guardan ' + ids.length + ' factura(s) en Borrador con los datos leídos. Después se revisan y confirman como cualquier otra.',
            showCancelButton: true, confirmButtonText: 'Guardar', cancelButtonText: 'Cancelar'
        }).then(r => {
            if (!r.isConfirmed) return;
            ocupado = true;
            botonesLote();
            progreso('Guardando…');
            post('guardar', { ids: ids, sucursal_id: $('#bdjSucursal').val() }).then(res => {
                const fallas = (res.data || []).filter(i => !i.success);
                if (fallas.length) {
                    UiSimple.aviso(res.message + ' ' + fallas.map(i => (fila(i.id) || {}).nombre_original + ': ' + i.message).join(' · '));
                } else {
                    UiSimple.ok(res.message);
                }
            }, m => UiSimple.error(m, 'No se pudo guardar')).always(() => {
                ocupado = false;
                progreso('');
                listar();
            });
        });
    }

    function descartar(ids) {
        if (!ids.length) return;
        Swal.fire({
            icon: 'warning', title: 'Descartar',
            text: 'Los documentos descartados no se cargan. El archivo se conserva.',
            input: 'text', inputPlaceholder: 'Motivo (opcional)',
            showCancelButton: true, confirmButtonText: 'Descartar', cancelButtonText: 'Cancelar'
        }).then(r => {
            if (!r.isConfirmed) return;
            post('descartar', { ids: ids, motivo: r.value || '' }).then(res => {
                UiSimple.ok(res.message);
                listar();
            }, m => UiSimple.error(m, 'No se pudo descartar'));
        });
    }

    // ------------------------------------------------------------------ Revisor embebido de Factura de Proveedor
    function abrirRevisorFactura(adjuntoId) {
        if (!adjuntoId) return;
        const url = 'facturas_proveedores.php?lectura_adjunto=' + encodeURIComponent(adjuntoId) + '&embed=1';
        $('#iframeBdjFactura').attr('src', url);
        $('#modalBdjRevisarFactura').modal('show');
    }

    // ------------------------------------------------------------------ Asistente de Extracto y Gastos Bancarios
    function abrirRevisorExtracto(id) {
        const f = fila(id) || {};
        extState = {
            id: id,
            cuentaId: 0,
            mes: '',
            filtroMovs: 'TODOS',
            excluidos: {},
            conceptosPorFila: {},
            data: null
        };
        $('#bdjExtNombreArchivo').text(f.nombre_original || ('Documento #' + id));
        $('#btnBdjExtVerArchivo').attr('href', urlArchivo(id));
        $('#bdjExtPasoImportar, #bdjExtPasoGastos, #btnBdjExtGenerar, #btnBdjExtFinalizar').addClass('d-none');
        $('#bdjExtCargando').removeClass('d-none');
        $('#modalBdjExtracto').modal('show');
        cargarEstadoExtracto();
    }

    function cargarEstadoExtracto() {
        $('#bdjExtCargando').removeClass('d-none');
        get('ext_estado', {
            id: extState.id,
            tesoreria_cuenta_id: extState.cuentaId || 0,
            mes: extState.mes || ''
        }).then(d => {
            $('#bdjExtCargando').addClass('d-none');
            extState.data = d;
            extState.cuentaId = parseInt(d.tesoreria_cuenta_id || 0, 10);
            if (d.mes_elegido) extState.mes = d.mes_elegido;
            renderCabeceraExtracto(d);
            if (d.importado) {
                renderPasoGastosExtracto(d);
            } else {
                renderPasoImportarExtracto(d);
            }
        }, m => {
            $('#bdjExtCargando').addClass('d-none');
            UiSimple.error(m || 'No se pudo analizar el extracto bancario.');
        });
    }

    function renderCabeceraExtracto(d) {
        const cuentas = d.cuentas || [];
        $('#bdjExtCuenta').html(
            '<option value="0">— Seleccione cuenta bancaria —</option>' +
            cuentas.map(c => {
                const sel = parseInt(c.tesoreria_cuenta_id, 10) === extState.cuentaId ? 'selected' : '';
                const mon = c.moneda_simbolo || c.moneda_codigo || '$';
                return `<option value="${c.tesoreria_cuenta_id}" ${sel}>${esc(c.tesoreria_cuenta_nombre)} (${esc(mon)})</option>`;
            }).join('')
        ).prop('disabled', !!d.importado);

        if (d.cuenta && d.cuenta.proveedor_nombre) {
            $('#bdjExtProveedorNombre').text(d.cuenta.proveedor_nombre + (d.cuenta.proveedor_cuit ? ' (' + d.cuenta.proveedor_cuit + ')' : ''))
                .removeClass('text-danger').addClass('text-dark');
        } else {
            $('#bdjExtProveedorNombre').text('Sin asignar (requerido para C_RB)').removeClass('text-dark').addClass('text-danger');
        }
        $('#btnBdjExtProveedor').prop('disabled', !extState.cuentaId);

        const pvs = d.puntos_venta_op || [];
        if (pvs.length > 1) {
            $('#grupoBdjExtOpPv').removeClass('d-none');
            $('#bdjExtOpPv').html(pvs.map(p => `<option value="${p.punto_venta_id}">${esc(p.etiqueta || p.codigo || p.punto_venta_id)}</option>`).join(''));
        } else {
            $('#grupoBdjExtOpPv').addClass('d-none');
            $('#bdjExtOpPv').html(pvs.length === 1 ? `<option value="${pvs[0].punto_venta_id}">${esc(pvs[0].punto_venta_id)}</option>` : '');
        }
    }

    function opcionesSelectConcepto(conceptos, elegidoId) {
        const grupos = { GASTO_BANCARIO: [], MOVIMIENTO_PROPIO: [] };
        (conceptos || []).forEach(c => {
            const g = c.naturaleza === 'GASTO_BANCARIO' ? 'GASTO_BANCARIO' : 'MOVIMIENTO_PROPIO';
            grupos[g].push(c);
        });
        let html = '<option value="0">— Sin concepto —</option>';
        if (grupos.GASTO_BANCARIO.length) {
            html += '<optgroup label="Gasto / Impuesto del banco (va al C_RB)">';
            grupos.GASTO_BANCARIO.forEach(c => {
                const sel = parseInt(c.banco_concepto_id, 10) === parseInt(elegidoId || 0, 10) ? 'selected' : '';
                html += `<option value="${c.banco_concepto_id}" ${sel}>${esc(c.banco_concepto_nombre)}</option>`;
            });
            html += '</optgroup>';
        }
        if (grupos.MOVIMIENTO_PROPIO.length) {
            html += '<optgroup label="Movimiento propio (no es gasto del banco)">';
            grupos.MOVIMIENTO_PROPIO.forEach(c => {
                const sel = parseInt(c.banco_concepto_id, 10) === parseInt(elegidoId || 0, 10) ? 'selected' : '';
                html += `<option value="${c.banco_concepto_id}" ${sel}>${esc(c.banco_concepto_nombre)}</option>`;
            });
            html += '</optgroup>';
        }
        return html;
    }

    function renderPasoImportarExtracto(d) {
        $('#bdjExtPasoGastos, #btnBdjExtGenerar, #btnBdjExtFinalizar').addClass('d-none');
        $('#bdjExtPasoImportar').removeClass('d-none');

        const prev = d.previsualizacion || {};
        const res = prev.resumen || {};
        const bloq = prev.bloqueos || [];
        const avisos = prev.advertencias || [];

        if (bloq.length) {
            $('#bdjExtPrevBloqueos').html(bloq.map(b => `<div><i class="fas fa-times-circle me-1"></i>${esc(b.mensaje || b)}</div>`).join('')).removeClass('d-none');
        } else {
            $('#bdjExtPrevBloqueos').addClass('d-none');
        }

        if (avisos.length) {
            $('#bdjExtPrevAvisos').html(avisos.map(a => `<div><i class="fas fa-exclamation-triangle me-1"></i>${esc(a.mensaje || a)}</div>`).join('')).removeClass('d-none');
        } else {
            $('#bdjExtPrevAvisos').addClass('d-none');
        }

        $('#bdjExtPrevKpis').html([
            `<span class="badge bg-primary-subtle text-primary-emphasis px-2 py-1">A importar: ${res.importar || 0}</span>`,
            `<span class="badge bg-secondary-subtle text-secondary-emphasis px-2 py-1">Repetidos: ${res.duplicadas || 0}</span>`,
            `<span class="badge bg-danger-subtle text-danger-emphasis px-2 py-1">Con error: ${res.errores || 0}</span>`,
            res.fecha_desde ? `<span class="badge bg-light text-dark border px-2 py-1">Período: ${esc(Fmt.fecha(res.fecha_desde))} al ${esc(Fmt.fecha(res.fecha_hasta))}</span>` : ''
        ].join(''));

        const filasPrev = prev.filas || [];
        const conceptos = d.conceptos || [];
        $('#bdjExtPrevTbody').html(filasPrev.map(r => {
            const eleg = extState.conceptosPorFila[r.fila] !== undefined ? extState.conceptosPorFila[r.fila] : (r.banco_concepto_id || 0);
            const imp = parseFloat(r.importe || 0);
            const clsImp = imp < 0 ? 'text-danger fw-semibold' : 'text-success fw-semibold';
            let badge = '<span class="badge bg-success-subtle text-success-emphasis">Importar</span>';
            if (r.accion === 'DUPLICADA') badge = '<span class="badge bg-secondary-subtle text-secondary-emphasis">Repetido</span>';
            else if (r.accion === 'ERROR') badge = `<span class="badge bg-danger-subtle text-danger-emphasis" title="${esc((r.mensajes || []).join(' '))}">Error</span>`;

            return `<tr class="${r.accion !== 'IMPORTAR' ? 'table-light text-muted' : ''}">
                <td class="small text-muted">${r.fila}</td>
                <td class="text-nowrap small">${r.fecha_operacion ? esc(Fmt.fecha(r.fecha_operacion)) : '-'}</td>
                <td class="small">${esc(r.descripcion_original || '')}</td>
                <td style="min-width: 230px;">
                    <select class="form-select form-select-sm bdj-ext-prev-concepto" data-fila="${r.fila}" ${r.accion !== 'IMPORTAR' ? 'disabled' : ''}>
                        ${opcionesSelectConcepto(conceptos, eleg)}
                    </select>
                </td>
                <td class="small text-muted">${esc(r.referencia || '')}</td>
                <td class="text-end text-nowrap ${clsImp}">${Fmt.moneda(imp)}</td>
                <td class="text-end text-nowrap small text-muted">${r.saldo !== null && r.saldo !== undefined ? Fmt.moneda(r.saldo) : ''}</td>
                <td>${badge}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="8" class="text-center text-muted py-3">Seleccione una cuenta bancaria para previsualizar el extracto.</td></tr>');

        $('#btnBdjExtImportar').prop('disabled', !prev.puede_confirmar || !extState.cuentaId);
    }

    function renderPasoGastosExtracto(d) {
        $('#bdjExtPasoImportar').addClass('d-none');
        $('#bdjExtPasoGastos, #btnBdjExtGenerar, #btnBdjExtFinalizar').removeClass('d-none');

        const meses = d.meses_pendientes || [];
        if (meses.length) {
            $('#bdjExtMeses').html(
                '<span class="small fw-semibold text-muted me-1">Período:</span>' +
                meses.map(m => {
                    const act = m.mes === extState.mes ? 'btn-primary' : 'btn-outline-primary';
                    return `<button type="button" class="btn btn-sm ${act} bdj-ext-mes" data-mes="${esc(m.mes)}">
                        ${esc(m.etiqueta)} <span class="badge bg-white text-dark ms-1">${m.movimientos}</span>
                    </button>`;
                }).join('')
            );
        } else {
            $('#bdjExtMeses').html('<span class="small text-muted">No hay débitos bancarios pendientes de incluir en un Resumen Bancario (C_RB).</span>');
        }

        renderResumenCRB(d.resumen_gastos);
        renderTablaMovimientosImportados(d.lineas_importadas || [], d.conceptos || []);
    }

    function renderResumenCRB(rg) {
        if (!rg) {
            $('#bdjExtGastosBloqueos, #bdjExtGastosAvisos').addClass('d-none');
            $('#bdjExtGastosKpis').html('');
            $('#bdjExtComprobante').html('<tr><td colspan="3" class="text-center text-muted py-3">Sin gastos bancarios pendientes en este período. Puede clasificar movimientos en la solapa 2 o marcar el extracto como procesado.</td></tr>');
            $('#bdjExtLineasGasto').html('<tr><td colspan="5" class="text-center text-muted py-3">Sin líneas de gasto bancario.</td></tr>');
            $('#btnBdjExtGenerar').prop('disabled', true);
            return;
        }

        const bloq = rg.bloqueos || [];
        const avisos = rg.advertencias || [];
        if (bloq.length) {
            $('#bdjExtGastosBloqueos').html(bloq.map(b => `<div><i class="fas fa-times-circle me-1"></i>${esc(b.mensaje || b)}</div>`).join('')).removeClass('d-none');
        } else {
            $('#bdjExtGastosBloqueos').addClass('d-none');
        }
        if (avisos.length) {
            $('#bdjExtGastosAvisos').html(avisos.map(a => `<div><i class="fas fa-exclamation-triangle me-1"></i>${esc(a.mensaje || a)}</div>`).join('')).removeClass('d-none');
        } else {
            $('#bdjExtGastosAvisos').addClass('d-none');
        }

        const comp = rg.comprobante || {};
        $('#bdjExtGastosKpis').html(`
            <div class="col-6 col-md-3"><div class="p-2 border rounded bg-light"><div class="small text-muted">Neto Gastos</div><div class="fw-bold">${Fmt.moneda(comp.neto_total || 0)}</div></div></div>
            <div class="col-6 col-md-3"><div class="p-2 border rounded bg-light"><div class="small text-muted">IVA Extracto</div><div class="fw-bold">${Fmt.moneda(comp.iva_extracto || 0)}</div></div></div>
            <div class="col-6 col-md-3"><div class="p-2 border rounded bg-light"><div class="small text-muted">Percepciones / Ret.</div><div class="fw-bold">${Fmt.moneda(comp.impuestos_total || 0)}</div></div></div>
            <div class="col-6 col-md-3"><div class="p-2 border rounded bg-primary-subtle"><div class="small text-primary-emphasis">Total C_RB</div><div class="fw-bold text-primary-emphasis">${Fmt.moneda(comp.total || 0)}</div></div></div>
        `);

        const filasComp = [];
        (comp.detalles || []).forEach(det => {
            filasComp.push(`<tr>
                <td><div class="fw-semibold small">${esc(det.banco_concepto_nombre)}</div><div class="text-muted" style="font-size:11px;">${esc(det.producto_nombre || '')} (${det.iva_porcentaje}% IVA)</div></td>
                <td class="text-center small">${det.cantidad_movimientos}</td>
                <td class="text-end fw-semibold small">${Fmt.moneda(det.neto)}</td>
            </tr>`);
        });
        if (parseFloat(comp.iva_extracto || 0) > 0) {
            filasComp.push(`<tr class="table-light">
                <td class="small">IVA sobre gastos bancarios</td>
                <td class="text-center small">-</td>
                <td class="text-end fw-semibold small">${Fmt.moneda(comp.iva_extracto)}</td>
            </tr>`);
        }
        (comp.impuestos || []).forEach(imp => {
            filasComp.push(`<tr class="table-light">
                <td class="small">${esc(imp.nombre)}${imp.jurisdiccion_codigo ? ' (' + esc(imp.jurisdiccion_codigo) + ')' : ''}</td>
                <td class="text-center small">${imp.cantidad_movimientos}</td>
                <td class="text-end fw-semibold small">${Fmt.moneda(imp.importe)}</td>
            </tr>`);
        });
        filasComp.push(`<tr class="table-primary fw-bold">
            <td>Total Resumen Bancario (C_RB)</td>
            <td class="text-center">${rg.movimientos_incluidos || 0}</td>
            <td class="text-end">${Fmt.moneda(comp.total || 0)}</td>
        </tr>`);
        $('#bdjExtComprobante').html(filasComp.join(''));

        const lineas = rg.lineas || [];
        $('#bdjExtLineasGasto').html(lineas.map(l => {
            const idExtr = parseInt(l.banco_extracto_id, 10);
            const inc = !extState.excluidos[idExtr];
            return `<tr class="${inc ? '' : 'table-light text-muted'}">
                <td><input type="checkbox" class="form-check-input bdj-ext-inc-linea" value="${idExtr}" ${inc ? 'checked' : ''}></td>
                <td class="small text-nowrap">${esc(Fmt.fecha(l.fecha_operacion))}</td>
                <td class="small">${esc(l.descripcion_original || '')}</td>
                <td class="small"><span class="badge bg-light text-dark border">${esc(l.banco_concepto_nombre || 'Sin concepto')}</span></td>
                <td class="text-end small fw-semibold text-nowrap">${Fmt.moneda(l.monto || Math.abs(l.importe || 0))}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="5" class="text-center text-muted py-3">Sin débitos en este período.</td></tr>');

        const incluidosIds = lineas.map(l => parseInt(l.banco_extracto_id, 10)).filter(id => !extState.excluidos[id]);
        $('#btnBdjExtGenerar').prop('disabled', !rg.puede_confirmar || !incluidosIds.length);
    }

    function renderTablaMovimientosImportados(lineas, conceptos) {
        $('#bdjExtCantMovs').text(lineas.length);
        const filtradas = lineas.filter(l => {
            if (extState.filtroMovs === 'SIN_CONCEPTO') return !parseInt(l.banco_concepto_id || 0, 10);
            if (extState.filtroMovs === 'GASTO') return l.concepto_naturaleza === 'GASTO_BANCARIO';
            return true;
        });

        $('#bdjExtTablaMovimientos').html(filtradas.map(l => {
            const imp = parseFloat(l.importe || 0);
            const clsImp = imp < 0 ? 'text-danger fw-semibold' : 'text-success fw-semibold';
            const conc = l.estado_conciliacion === 'CONCILIADO';
            return `<tr>
                <td class="small text-nowrap">${esc(Fmt.fecha(l.fecha_operacion))}</td>
                <td class="small">${esc(l.descripcion_original || '')}</td>
                <td class="small text-muted">${esc(l.referencia || '')}</td>
                <td style="min-width: 240px;">
                    <select class="form-select form-select-sm bdj-ext-mov-concepto" data-extracto-id="${l.banco_extracto_id}" ${conc ? 'disabled' : ''}>
                        ${opcionesSelectConcepto(conceptos, l.banco_concepto_id)}
                    </select>
                </td>
                <td class="text-end text-nowrap ${clsImp}">${Fmt.moneda(imp)}</td>
                <td><span class="badge ${conc ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis'}">${esc(l.estado_conciliacion)}</span></td>
            </tr>`;
        }).join('') || '<tr><td colspan="6" class="text-center text-muted py-3">No hay movimientos para el filtro seleccionado.</td></tr>');
    }

    // ------------------------------------------------------------------ tabla principal
    function acciones(f) {
        const id = parseInt(f.bandeja_documento_id, 10);
        const esExtracto = f.tipo_documento === 'EXTRACTO';
        const ext = extArchivo(f.nombre_original);
        let h = `<a class="btn btn-sm btn-outline-secondary" href="${esc(urlArchivo(id))}" target="_blank" rel="noopener" title="Ver el archivo"><i class="fas fa-eye"></i></a> `;

        if (esExtracto) {
            if (['RECIBIDO', 'LISTO', 'REVISAR', 'ERROR'].includes(f.estado) && opciones.puede_guardar) {
                h += `<button type="button" class="btn btn-sm btn-primary bdj-revisar-extracto" data-id="${id}" title="Abrir asistente de extracto y gastos bancarios"><i class="fas fa-university me-1"></i>Revisar extracto</button> `;
            }
            if (['RECIBIDO', 'LISTO', 'REVISAR', 'ERROR'].includes(f.estado) && EXT_FACTURA.includes(ext)) {
                h += `<button type="button" class="btn btn-sm btn-outline-secondary bdj-cambiar-tipo" data-id="${id}" data-tipo="FACTURA_COMPRA" title="Tratar como Factura de proveedor"><i class="fas fa-exchange-alt"></i></button> `;
            }
        } else {
            if (['LISTO', 'REVISAR'].includes(f.estado) && f.adjunto_id && opciones.puede_guardar) {
                h += `<button type="button" class="btn btn-sm btn-outline-primary bdj-revisar-factura" data-adjunto="${encodeURIComponent(f.adjunto_id)}" title="Revisar factura aquí mismo">Revisar</button> `;
            }
            if (['RECIBIDO', 'LISTO', 'REVISAR', 'ERROR'].includes(f.estado)) {
                h += `<button type="button" class="btn btn-sm btn-outline-secondary bdj-cambiar-tipo" data-id="${id}" data-tipo="EXTRACTO" title="Tratar como Extracto bancario"><i class="fas fa-university"></i></button> `;
            }
        }

        if (['RECIBIDO', 'ERROR', 'REVISAR'].includes(f.estado)) {
            h += `<button type="button" class="btn btn-sm btn-outline-secondary bdj-leer" data-id="${id}" data-funcion="agregar" title="Leer de nuevo">Leer</button> `;
        }
        if (['RECIBIDO', 'LISTO', 'REVISAR', 'ERROR', 'DUPLICADO'].includes(f.estado)) {
            h += `<button type="button" class="btn btn-sm btn-outline-danger bdj-descartar" data-id="${id}" data-funcion="inhabilitar" title="Descartar"><i class="fas fa-ban"></i></button>`;
        }
        return h;
    }

    function crearTabla() {
        tabla = $('#tablaBandeja').DataTable({
            data: [], order: [[1, 'desc']], pageLength: 25,
            columns: [
                { data: null, orderable: false, render: f => `<input type="checkbox" class="form-check-input bdj-elegir" value="${parseInt(f.bandeja_documento_id, 10)}">` },
                { data: 'fecha_alta', render: (v, t) => t === 'display' ? Fmt.fechaHora(v) : v },
                { data: null, render: f => {
                    const badgeTipo = f.tipo_documento === 'EXTRACTO'
                        ? '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle me-1"><i class="fas fa-university me-1"></i>Extracto</span>'
                        : '<span class="badge bg-light text-secondary border me-1">Factura</span>';
                    return badgeTipo + esc(f.nombre_original);
                } },
                { data: 'estado', render: (v, t) => {
                    if (t !== 'display') return v;
                    const e = ESTADOS[v] || [v, 'bg-light'];
                    return `<span class="badge ${e[1]}">${esc(e[0])}</span>`;
                } },
                { data: null, render: f => {
                    if (f.estado === 'PROCESADO' && f.f_nro) {
                        const tipoNom = f.f_tipo_nombre || 'Factura';
                        return esc(tipoNom + ' ' + String(f.f_pv).padStart(5, '0') + '-' + String(f.f_nro).padStart(8, '0'))
                             + (parseInt(f.f_estado, 10) === 6 ? ' <span class="badge bg-danger-subtle text-danger-emphasis">Eliminada</span>' : '');
                    }
                    if (f.estado === 'PROCESADO' && f.bi_id) {
                        return 'Extracto importado (' + parseInt(f.bi_movs || 0, 10) + ' movs.)';
                    }
                    return esc(f.comprobante) + (f.fecha ? ' <span class="text-muted small">' + esc(Fmt.fecha(f.fecha)) + '</span>' : '');
                } },
                { data: null, render: f => esc(f.f_proveedor || f.bi_cuenta || f.proveedor) + (f.cuit ? ' <span class="text-muted small">' + esc(f.cuit) + '</span>' : '') },
                { data: null, className: 'text-end', render: (f, t) => {
                    const v = f.f_total != null ? parseFloat(f.f_total) : f.total;
                    if (t !== 'display') return v == null ? 0 : v;
                    return v == null ? '' : Fmt.moneda(v);
                } },
                { data: 'motivo', className: 'ui-detalle', defaultContent: '', render: v => esc(v) },
                { data: null, className: 'ui-detalle', render: f => {
                    const orig = ORIGENES[f.origen] || f.origen;
                    if (f.origen === 'PORTAL' && f.enlace_etiqueta) {
                        return `<span class="badge bg-info-subtle text-info border border-info-subtle" title="Enlace: ${esc(f.enlace_etiqueta)}">${esc(orig)}</span>`;
                    }
                    return esc(orig);
                } },
                { data: 'usuario_nombre', className: 'ui-detalle', defaultContent: '', render: v => esc(v) },
                { data: null, orderable: false, className: 'text-center text-nowrap', render: acciones }
            ]
        });
    }

    // ------------------------------------------------------------------ inicio
    function iniciar() {
        crearTabla();
        get('opciones').then(d => {
            opciones = d;
            $('#bdjSucursal').html((d.sucursales || []).map(s => `<option value="${s.id}">${esc(s.nombre)}</option>`).join(''));
            if (!d.puede_guardar) $('#btnBdjGuardar, #bdjSucursal').addClass('d-none');
            if (d.ia && d.ia.disponible === false && d.ia.motivo) $('#bdjIaAviso').text(d.ia.motivo).removeClass('d-none');
            listar();
        }, m => UiSimple.error(m || 'No se pudo cargar la bandeja.'));

        $('#bdjVistas').on('click', '.nav-link', function () {
            $('#bdjVistas .nav-link').removeClass('active');
            $(this).addClass('active');
            vista = $(this).data('vista');
            listar();
        });
        $('#fBdjDesde, #fBdjHasta, #fBdjOrigen').on('change', listar);
        let t = null;
        $('#fBdjTexto').on('input', () => { clearTimeout(t); t = setTimeout(listar, 400); });

        $('#btnBdjSubir').on('click', () => $('#bdjArchivos').val('').trigger('click'));
        $('#bdjArchivos').on('change', function () { subir(this.files); });

        $('#btnBdjSubirExtracto').on('click', () => $('#bdjArchivosExtracto').val('').trigger('click'));
        $('#bdjArchivosExtracto').on('change', function () { subir(this.files, 'EXTRACTO'); });

        // Arrastrar y soltar archivos sobre la tarjeta o la ventana
        const $card = $('#bdjCard');
        $(document).on('dragenter dragover', function (e) {
            e.preventDefault(); e.stopPropagation();
            $card.addClass('border border-2 border-primary shadow');
        }).on('dragleave', function (e) {
            e.preventDefault(); e.stopPropagation();
            if (e.target === document || e.target === document.body || !e.relatedTarget) {
                $card.removeClass('border border-2 border-primary shadow');
            }
        }).on('drop', function (e) {
            e.preventDefault(); e.stopPropagation();
            $card.removeClass('border border-2 border-primary shadow');
            const dt = e.originalEvent && e.originalEvent.dataTransfer;
            if (dt && dt.files && dt.files.length) {
                subir(dt.files);
            }
        });

        $('#btnBdjLeer').on('click', () => leer());
        $('#btnBdjGuardar').on('click', guardar);
        $('#btnBdjDescartar').on('click', () => descartar(elegidos()));
        $('#bdjTodos').on('change', function () {
            $('#tablaBandeja .bdj-elegir').prop('checked', this.checked);
            botonesLote();
        });
        $('#tablaBandeja').on('change', '.bdj-elegir', botonesLote)
            .on('click', '.bdj-leer', function () { leer([parseInt($(this).data('id'), 10)]); })
            .on('click', '.bdj-descartar', function () { descartar([parseInt($(this).data('id'), 10)]); })
            .on('click', '.bdj-cambiar-tipo', function () {
                cambiarTipo(parseInt($(this).data('id'), 10), $(this).data('tipo'));
            })
            .on('click', '.bdj-revisar-factura', function () {
                abrirRevisorFactura($(this).data('adjunto'));
            })
            .on('click', '.bdj-revisar-extracto', function () {
                abrirRevisorExtracto(parseInt($(this).data('id'), 10));
            });

        // Mensajes desde el iframe de revisión de factura
        window.addEventListener('message', function (ev) {
            const d = ev && ev.data;
            if (!d || typeof d !== 'object') return;
            if (d.tipo === 'bdj_factura_guardada') {
                $('#modalBdjRevisarFactura').modal('hide');
                $('#iframeBdjFactura').attr('src', 'about:blank');
                UiSimple.ok('Factura guardada correctamente.');
                listar();
            } else if (d.tipo === 'bdj_factura_cerrada') {
                $('#modalBdjRevisarFactura').modal('hide');
                $('#iframeBdjFactura').attr('src', 'about:blank');
                listar();
            }
        });

        $('#modalBdjRevisarFactura').on('hidden.bs.modal', function () {
            $('#iframeBdjFactura').attr('src', 'about:blank');
            listar();
        });

        // Eventos del asistente de Extracto y Gastos Bancarios
        $('#bdjExtCuenta').on('change', function () {
            extState.cuentaId = parseInt($(this).val() || 0, 10);
            cargarEstadoExtracto();
        });

        $('#bdjExtPrevTbody').on('change', '.bdj-ext-prev-concepto', function () {
            const filaN = parseInt($(this).data('fila'), 10);
            extState.conceptosPorFila[filaN] = parseInt($(this).val() || 0, 10);
        });

        $('#btnBdjExtImportar').on('click', function () {
            if (!extState.id || !extState.cuentaId) return;
            const $btn = $(this).prop('disabled', true);
            post('ext_importar', {
                id: extState.id,
                tesoreria_cuenta_id: extState.cuentaId,
                conceptos_por_fila: JSON.stringify(extState.conceptosPorFila)
            }).then(res => {
                UiSimple.ok(res.message);
                cargarEstadoExtracto();
                listar();
            }, m => {
                UiSimple.error(m || 'No se pudo importar el extracto.');
                $btn.prop('disabled', false);
            });
        });

        $('#bdjExtMeses').on('click', '.bdj-ext-mes', function () {
            extState.mes = $(this).data('mes');
            extState.excluidos = {};
            cargarEstadoExtracto();
        });

        $('#bdjExtLineasGasto').on('change', '.bdj-ext-inc-linea', function () {
            const idExtr = parseInt($(this).val(), 10);
            if (this.checked) delete extState.excluidos[idExtr];
            else extState.excluidos[idExtr] = true;
            if (extState.data) renderResumenCRB(extState.data.resumen_gastos);
        });

        $('#bdjExtFiltroMovs').on('click', 'button', function () {
            $('#bdjExtFiltroMovs button').removeClass('active');
            $(this).addClass('active');
            extState.filtroMovs = $(this).data('filtro');
            if (extState.data) renderTablaMovimientosImportados(extState.data.lineas_importadas || [], extState.data.conceptos || []);
        });

        $('#bdjExtTablaMovimientos').on('change', '.bdj-ext-mov-concepto', function () {
            const idExtr = parseInt($(this).data('extracto-id'), 10);
            const idCon = parseInt($(this).val() || 0, 10);
            post('ext_clasificar', {
                ids: [idExtr],
                banco_concepto_id: idCon,
                recordar_regla: 1
            }).then(() => {
                cargarEstadoExtracto();
            }, m => UiSimple.error(m || 'No se pudo clasificar el movimiento.'));
        });

        $('#btnBdjExtGenerar').on('click', function () {
            const rg = extState.data && extState.data.resumen_gastos;
            if (!rg) return;
            const ids = (rg.lineas || []).map(l => parseInt(l.banco_extracto_id, 10)).filter(id => !extState.excluidos[id]);
            if (!ids.length) return;
            Swal.fire({
                icon: 'question',
                title: 'Generar Resumen Bancario (C_RB)',
                text: 'Se generará y confirmará el comprobante Resumen Bancario (C_RB) con los gastos seleccionados y quedarán conciliados en la cuenta.',
                showCancelButton: true,
                confirmButtonText: 'Generar y conciliar',
                cancelButtonText: 'Cancelar'
            }).then(r => {
                if (!r.isConfirmed) return;
                $('#btnBdjExtGenerar').prop('disabled', true);
                post('ext_generar_gastos', {
                    id: extState.id,
                    tesoreria_cuenta_id: extState.cuentaId,
                    mes: extState.mes,
                    sucursal_id: $('#bdjSucursal').val() || 0,
                    op_punto_venta_id: $('#bdjExtOpPv').val() || 0,
                    extracto_ids: ids
                }).then(res => {
                    UiSimple.ok(res.message);
                    $('#modalBdjExtracto').modal('hide');
                    listar();
                }, m => {
                    UiSimple.error(m || 'No se pudo generar el Resumen Bancario.');
                    $('#btnBdjExtGenerar').prop('disabled', false);
                });
            });
        });

        $('#btnBdjExtFinalizar').on('click', function () {
            if (!extState.id) return;
            post('ext_finalizar_importacion', { id: extState.id }).then(res => {
                UiSimple.ok(res.message);
                $('#modalBdjExtracto').modal('hide');
                listar();
            }, m => UiSimple.error(m || 'No se pudo finalizar el extracto.'));
        });

        // Submodal Proveedor del banco
        function buscarProveedoresBanco(q) {
            get('ext_proveedores', { q: q || '' }).then(lista => {
                $('#bdjExtProvLista').html((lista || []).map(p => `
                    <button type="button" class="list-group-item list-group-item-action py-2 bdj-ext-elegir-prov" data-id="${p.entidad_id}">
                        <div class="fw-semibold small">${esc(p.nombre)}</div>
                        <div class="text-muted" style="font-size:11px;">CUIT: ${esc(p.cuit || 'S/D')}</div>
                    </button>
                `).join('') || '<div class="p-3 text-center text-muted small">No se encontraron proveedores.</div>');
            });
        }

        $('#btnBdjExtProveedor').on('click', function () {
            $('#bdjExtProvTexto').val('');
            buscarProveedoresBanco('');
            $('#modalBdjExtProveedor').modal('show');
        });

        let tProv = null;
        $('#bdjExtProvTexto').on('input', function () {
            clearTimeout(tProv);
            const q = $(this).val();
            tProv = setTimeout(() => buscarProveedoresBanco(q), 250);
        });

        $('#bdjExtProvLista').on('click', '.bdj-ext-elegir-prov', function () {
            const provId = parseInt($(this).data('id'), 10);
            post('ext_proveedor', {
                tesoreria_cuenta_id: extState.cuentaId,
                proveedor_entidad_id: provId
            }).then(res => {
                UiSimple.ok(res.message);
                $('#modalBdjExtProveedor').modal('hide');
                cargarEstadoExtracto();
            }, m => UiSimple.error(m || 'No se pudo asociar el proveedor.'));
        });

        // Submodal Nuevo concepto bancario
        function abrirModalNuevoConcepto() {
            const cat = (extState.data && extState.data.catalogos_concepto) || {};
            $('#bdjConNombre').val('');
            $('#bdjConCodigo').val('');
            $('#bdjConNaturaleza').val('GASTO_BANCARIO').trigger('change');
            $('#bdjConRol').val('ITEM').trigger('change');
            $('#bdjConProducto').html('<option value="0">— Seleccione producto —</option>' +
                (cat.productos || []).map(p => `<option value="${p.producto_id}">${esc(p.producto_nombre)} (${p.iva_porcentaje}%)</option>`).join(''));
            $('#bdjConJurisdiccion').html('<option value="0">— Seleccione jurisdicción —</option>' +
                (cat.jurisdicciones || []).map(j => `<option value="${j.jurisdiccion_id}">${esc(j.jurisdiccion_nombre)}</option>`).join(''));
            $('#modalBdjExtConcepto').modal('show');
        }

        $('#btnBdjExtNuevoConcepto, #btnBdjExtNuevoConcepto2').on('click', abrirModalNuevoConcepto);

        $('#bdjConNaturaleza, #bdjConRol').on('change', function () {
            const esGasto = $('#bdjConNaturaleza').val() === 'GASTO_BANCARIO';
            const rol = $('#bdjConRol').val();
            $('#grupoBdjConRol').toggleClass('d-none', !esGasto);
            $('#grupoBdjConProducto').toggleClass('d-none', !esGasto || !['ITEM', 'IMPUESTO_LEY_25413'].includes(rol));
            $('#grupoBdjConJurisdiccion').toggleClass('d-none', !esGasto || !['PERCEPCION_IIBB', 'RETENCION_IIBB'].includes(rol));
        });

        $('#btnBdjConGuardar').on('click', function () {
            post('ext_agregar_concepto', {
                banco_concepto_nombre: $('#bdjConNombre').val(),
                banco_concepto_codigo: $('#bdjConCodigo').val(),
                naturaleza: $('#bdjConNaturaleza').val(),
                rol_comprobante_gasto: $('#bdjConRol').val(),
                producto_id: $('#bdjConProducto').val(),
                jurisdiccion_id: $('#bdjConJurisdiccion').val(),
                sentido_default: $('#bdjConSentido').val(),
                imputable: 1,
                conciliable: 1
            }).then(res => {
                UiSimple.ok(res.message);
                $('#modalBdjExtConcepto').modal('hide');
                cargarEstadoExtracto();
            }, m => UiSimple.error(m || 'No se pudo guardar el concepto.'));
        });

        // Gestión de enlaces externos de subida (Fase 2)
        function cargarEnlaces() {
            $('#tbodyBdjEnlaces').html('<tr><td colspan="6" class="text-center text-muted py-3">Cargando enlaces…</td></tr>');
            get('enlaces_listar').then(lista => {
                if (!lista || !lista.length) {
                    $('#tbodyBdjEnlaces').html('<tr><td colspan="6" class="text-center text-muted py-3">No hay enlaces creados para esta empresa.</td></tr>');
                    return;
                }
                const filasHtml = lista.map(e => {
                    const activo = parseInt(e.activo, 10) === 1;
                    const estadoBadge = activo
                        ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Activo</span>'
                        : '<span class="badge bg-secondary-subtle text-secondary border">Revocado</span>';
                    const venc = e.f_vencimiento ? Fmt.fecha(e.f_vencimiento) : '<span class="text-muted">Sin vencimiento</span>';
                    let acc = '';
                    if (activo) {
                        acc = `<button type="button" class="btn btn-sm btn-outline-danger btn-bdj-revocar py-0 px-2" data-id="${e.enlace_id}" title="Revocar enlace"><i class="fas fa-ban me-1"></i>Revocar</button>`;
                    }
                    return `<tr>
                        <td class="fw-semibold">${esc(e.etiqueta)}</td>
                        <td class="small text-muted">${Fmt.fechaHora(e.fecha_alta)}</td>
                        <td class="small">${venc}</td>
                        <td class="small">${parseInt(e.subidos_hoy, 10)} hoy / ${parseInt(e.total_subidos, 10)} total</td>
                        <td>${estadoBadge}</td>
                        <td class="text-end">${acc}</td>
                    </tr>`;
                }).join('');
                $('#tbodyBdjEnlaces').html(filasHtml);
            }, m => {
                $('#tbodyBdjEnlaces').html(`<tr><td colspan="6" class="text-center text-danger py-3">${esc(m || 'Error al cargar enlaces')}</td></tr>`);
            });
        }

        $('#btnBdjEnlaces').on('click', () => {
            $('#boxEnlaceCreado').addClass('d-none');
            $('#formBdjNuevoEnlace')[0].reset();
            $('#modalBdjEnlaces').modal('show');
            cargarEnlaces();
        });

        $('#formBdjNuevoEnlace').on('submit', function (e) {
            e.preventDefault();
            const data = {
                etiqueta: $('#enlaceEtiqueta').val(),
                f_vencimiento: $('#enlaceVencimiento').val(),
                max_archivos_dia: $('#enlaceMaxDia').val()
            };
            $('#btnCrearEnlace').prop('disabled', true);
            post('enlace_crear', data).then(res => {
                UiSimple.ok(res.message);
                $('#formBdjNuevoEnlace')[0].reset();
                if (res.data && res.data.url) {
                    $('#txtNuevoEnlaceUrl').val(res.data.url);
                    $('#boxEnlaceCreado').removeClass('d-none');
                }
                cargarEnlaces();
            }, m => UiSimple.error(m, 'No se pudo crear el enlace')).always(() => {
                $('#btnCrearEnlace').prop('disabled', false);
            });
        });

        $('#btnCopiarNuevoEnlace').on('click', function () {
            const url = $('#txtNuevoEnlaceUrl').val();
            if (!url) return;
            navigator.clipboard.writeText(url).then(() => {
                const $btn = $(this);
                const prev = $btn.html();
                $btn.html('<i class="fas fa-check me-1"></i>¡Copiado!').addClass('btn-success').removeClass('btn-outline-success');
                setTimeout(() => $btn.html(prev).removeClass('btn-success').addClass('btn-outline-success'), 2000);
            });
        });

        $('#tbodyBdjEnlaces').on('click', '.btn-bdj-revocar', function () {
            const id = parseInt($(this).data('id'), 10);
            if (!id) return;
            Swal.fire({
                icon: 'warning',
                title: '¿Revocar enlace?',
                text: 'El cliente o proveedor ya no podrá subir nuevos comprobantes con este enlace.',
                showCancelButton: true,
                confirmButtonText: 'Revocar',
                cancelButtonText: 'Cancelar'
            }).then(r => {
                if (!r.isConfirmed) return;
                post('enlace_revocar', { enlace_id: id }).then(res => {
                    UiSimple.ok(res.message);
                    cargarEnlaces();
                }, m => UiSimple.error(m, 'No se pudo revocar el enlace'));
            });
        });
    }

    $(iniciar);
    return { recargar: listar };
})();
