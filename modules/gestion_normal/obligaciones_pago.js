$(document).ready(function () {
    const AJAX = 'obligaciones_pago_ajax.php';
    const { esc } = Bco;
    let opciones = { tipos: [], entidades: [], cuentas: [], sucursales: [], url_pagos: 'pagos.php' };
    let importacionId = 0;
    const filas = {};
    const moneda = (v) => '$ ' + Bco.num(v);
    const hoy = () => new Date().toISOString().slice(0, 10);

    const tabla = $('#tablaObligaciones').DataTable({
        ajax: {
            url: AJAX,
            data: (d) => Object.assign(d, {
                accion: 'listar', empresa_idx: window.EMPRESA_ID, vista: $('#filtroVista').val(), obligacion_tipo_id: $('#filtroTipo').val() || 0,
                entidad_id: $('#filtroEntidad').val() || 0, vto_desde: $('#filtroVtoDesde').val(), vto_hasta: $('#filtroVtoHasta').val()
            }),
            dataSrc: Bco.datos
        },
        order: [], pageLength: 25,
        columns: [
            { data: 'f_vto', render: (d, t, r) => t === 'display' ? `<span class="${+r.vencida ? 'text-danger fw-bold' : ''}">${Bco.fecha(d)}</span>` : d },
            { data: 'tipo_nombre', render: (d, t, r) => esc(d) + (r.clase === 'ANTICIPO' ? ' <span class="badge bg-info-subtle text-info border">anticipo</span>' : '') },
            { data: 'entidad_nombre', render: (d) => `<strong>${esc(d)}</strong>` },
            { data: 'descripcion', render: (d, t, r) => esc(d) + (r.periodo_fiscal ? ` <span class="text-muted small">(${esc(r.periodo_fiscal)})</span>` : '') },
            { data: 'numero', className: 'ui-detalle', render: (d) => d ? esc(d) : '<span class="text-muted small">sin número</span>' },
            { data: 'origen_texto', className: 'ui-detalle', render: (d) => esc(d) },
            { data: 'importe_total', className: 'text-end', render: (d) => Bco.importe(d) },
            { data: 'importe_pendiente', className: 'text-end', render: (d, t, r) => [5, 39].includes(+r.tabla_estado_registro_id) ? Bco.importe(d) : '' },
            { data: 'estado', className: 'text-center', render: (d, t, r) => `<span class="badge ${r.bg_clase || 'bg-secondary'} ${r.text_clase || ''}">${esc(d || '')}</span>` },
            { data: 'botones', orderable: false, className: 'text-center text-nowrap', render: (d, t, r) => Bco.botonesFila(d, r.obligacion_id, r.descripcion) }
        ],
        createdRow: (row, r) => {
            $(row).find('.btn-accion').attr('data-entidad', r.entidad_id).attr('data-comprobante', r.comprobante_id);
        },
        language: Bco.idioma()
    });

    function recargar() {
        tabla.ajax.reload(null, false);
        Bco.pedir(AJAX, { accion: 'resumen' }, { metodo: 'GET', silencioso: true }).then((res) => {
            const k = res.data || {};
            $('#kpiAPagar').text(moneda(k.a_pagar_importe || 0));
            $('#kpiSemana').text(moneda(k.semana_importe || 0));
            $('#kpiSemanaN').text((+k.semana_n || 0) + ' obligaciones');
            $('#kpiVencidas').text(moneda(k.vencidas_importe || 0));
            $('#kpiVencidasN').text((+k.vencidas_n || 0) + ' obligaciones');
            $('#kpiAprobacion').text(+k.aprobacion_n || 0);
        }).catch(() => {});
    }
    recargar();

    // El listado necesita el entidad_id y comprobante_id de cada fila para "Pagar"
    tabla.on('xhr.dt', (e, s, json) => { (json && json.data || []).forEach(r => { filas[r.obligacion_id] = r; }); });

    // Botones de página: Agregar, Importar, Aplicar anticipos
    $.getJSON(AJAX, { accion: 'obtener_boton_agregar', empresa_idx: window.EMPRESA_ID }, (res) => {
        const ids = { agregar: 'btnNuevo', importar: 'btnImportar', aplicar_anticipo: 'btnAnticipos' };
        $('#contenedor-boton-agregar').html((res.data || []).map(b =>
            Bco.botonPagina(b, ids[b.accion_js] || ('btn_' + b.accion_js), b.accion_js === 'agregar' ? 'btn-sm' : 'btn-sm btn-outline-secondary')).join(''));
    });

    Bco.pedir(AJAX, { accion: 'opciones' }, { metodo: 'GET' }).then((res) => {
        opciones = res.data;
        const manuales = opciones.tipos.filter(t => +t.permite_manual === 1);
        Bco.opcionesSelect($('#filtroTipo'), opciones.tipos, 'obligacion_tipo_id', 'nombre', 'Todos los tipos');
        Bco.opcionesSelect($('#obligacion_tipo_id'), manuales, 'obligacion_tipo_id', 'nombre', 'Elegí el tipo');
        Bco.opcionesSelect($('#tipoImportar'), manuales, 'obligacion_tipo_id', 'nombre', 'Según la columna "tipo"');
        Bco.opcionesSelect($('#filtroEntidad'), opciones.entidades, 'entidad_id', (e) => e.entidad_nombre + (e.cuit ? ` (${e.cuit})` : ''), 'Todos los beneficiarios');
        Bco.opcionesSelect($('#antEntidad'), opciones.entidades, 'entidad_id', (e) => e.entidad_nombre + (e.cuit ? ` (${e.cuit})` : ''), 'Elegí el beneficiario');
        Bco.opcionesSelect($('#sucursal_id'), opciones.sucursales, 'sucursal_id', 'sucursal_nombre');
        const ctas = (sel) => Bco.opcionesSelect(sel, opciones.cuentas, 'cont_cuenta_id', (c) => `${c.codigo} ${c.nombre}`, 'La del tipo de obligación');
        ctas($('#cont_cuenta_debe_id'));
        ctas($('#cont_cuenta_pasivo_id'));
    }).catch(() => {});

    $('#filtroVista, #filtroTipo, #filtroEntidad, #filtroVtoDesde, #filtroVtoHasta').on('change', () => tabla.ajax.reload());
    $('#btnRecargar').on('click', recargar);

    // Beneficiarios según el rol que pide el tipo
    function filtrarEntidades(valor) {
        const tipo = opciones.tipos.find(t => +t.obligacion_tipo_id === +$('#obligacion_tipo_id').val());
        const rol = tipo ? tipo.rol_entidad_codigo : null;
        const campo = { EMP: 'es_emp', PRO: 'es_pro', ORG: 'es_org' }[rol];
        Bco.opcionesSelect($('#entidad_id'), opciones.entidades.filter(e => !campo || +e[campo] === 1), 'entidad_id',
            (e) => e.entidad_nombre + (e.cuit ? ` (${e.cuit})` : ''), 'Elegí el beneficiario');
        if (valor) $('#entidad_id').val(valor);
    }
    $('#obligacion_tipo_id').on('change', () => filtrarEntidades($('#entidad_id').val()));

    function abrir(d, soloVer) {
        const f = $('#formObligacion');
        f[0].reset();
        f.removeClass('was-validated');
        $('#obligacion_id').val(d ? d.obligacion_id : '');
        $('#modalObligacionLabel').text(d ? 'Editar obligación' : 'Nueva obligación');
        if (d) {
            ['obligacion_tipo_id', 'importe_total', 'f_emision', 'f_vto', 'descripcion', 'referencia', 'nro_vep', 'sucursal_id', 'observaciones'].forEach(c => $('#' + c).val(d[c]));
            $('#periodo_fiscal').val(d.periodo_fiscal || '');
            $('#cont_cuenta_debe_id').val(d.cont_cuenta_debe_id || '');
            $('#cont_cuenta_pasivo_id').val(d.cont_cuenta_id || '');
            filtrarEntidades(d.entidad_id);
        } else {
            $('#f_emision').val(hoy());
            $('#f_vto').val(hoy());
            filtrarEntidades();
        }
        $('#grupoConfirmarAhora').toggle(!d);
        $('#formObligacion :input').not('[data-bs-dismiss]').prop('disabled', !!soloVer);
        $('#btnGuardar').toggle(!soloVer);
        $('#modalObligacion').modal('show');
    }

    $(document).on('click', '#btnNuevo', () => abrir(null));

    $('#formObligacion').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) { $(this).addClass('was-validated'); return; }
        const obj = { accion: $('#obligacion_id').val() ? 'editar' : 'agregar' };
        $(this).serializeArray().forEach(x => { obj[x.name] = x.value; });
        if (!$('#confirmar_ahora').is(':checked') || obj.accion === 'editar') delete obj.confirmar_ahora;
        const btn = $('#btnGuardar').prop('disabled', true);
        Bco.pedir(AJAX, obj).then((res) => {
            $('#modalObligacion').modal('hide');
            Swal.fire({ icon: 'success', title: 'Listo', text: res.message });
            recargar();
        }).catch(() => {}).finally(() => btn.prop('disabled', false));
    });

    // Acciones de fila
    $(document).on('click', '#tablaObligaciones .btn-accion', function () {
        const id = $(this).data('id');
        const accion = $(this).data('accion');
        const r = filas[id] || {};
        switch (accion) {
            case 'visualizar':
                return verDetalle(id);
            case 'editar':
                return Bco.pedir(AJAX, { accion: 'obtener', obligacion_id: id }, { metodo: 'GET' }).then((res) => abrir(res.data)).catch(() => {});
            case 'pagar':
                window.location.href = `${opciones.url_pagos}&empresa_id=${window.EMPRESA_ID}&entidad_id=${r.entidad_id || $(this).data('entidad')}&comprobante_id=${r.comprobante_id || $(this).data('comprobante')}`;
                return;
            case 'rechazar':
            case 'anular':
                return Bco.confirmar(accion === 'anular' ? '¿Anular la obligación?' : '¿Rechazar? Vuelve a Borrador.', $(this).data('nombre'), true)
                    .then((motivo) => Bco.pedir(AJAX, { accion, obligacion_id: id, motivo }))
                    .then((res) => { Bco.ok(res.message); recargar(); }).catch(() => {});
            default:
                return Bco.confirmar(`¿${$(this).attr('title')}?`, $(this).data('nombre'))
                    .then(() => Bco.pedir(AJAX, { accion, obligacion_id: id }))
                    .then((res) => { Swal.fire({ icon: 'success', title: 'Listo', text: res.message }); recargar(); }).catch(() => {});
        }
    });

    function verDetalle(id) {
        Bco.pedir(AJAX, { accion: 'obtener', obligacion_id: id }, { metodo: 'GET' }).then((res) => {
            const o = res.data;
            const fila = (k, v) => v ? `<tr><th class="text-muted fw-normal" style="width:35%">${k}</th><td>${v}</td></tr>` : '';
            const nro = +o.comprobante_nro > 0 ? `${esc(o.comprobante_codigo)} ${String(o.pv_mostrar).padStart(4, '0')}-${String(o.comprobante_nro).padStart(8, '0')}` : 'sin número (borrador)';
            let h = `<table class="table table-sm mb-3">${fila('Tipo', esc(o.tipo_nombre))}${fila('Beneficiario', `<strong>${esc(o.entidad_nombre)}</strong> ${esc(o.cuit || '')}`)}
                ${fila('Descripción', esc(o.descripcion))}${fila('Comprobante', nro)}${fila('Estado', esc(o.estado))}${fila('Origen', esc(o.origen_texto))}
                ${fila('Fecha / vence', Bco.fecha(o.f_emision) + ' / ' + Bco.fecha(o.f_vto))}${fila('Período', esc(o.periodo_fiscal || ''))}
                ${fila('Importe', moneda(o.importe_total))}${fila('Pendiente', [5, 39].includes(+o.tabla_estado_registro_id) ? moneda(o.importe_pendiente) : '')}
                ${fila('Referencia', esc(o.referencia || ''))}${fila('VEP / DDJJ', esc([o.nro_vep, o.nro_ddjj].filter(Boolean).join(' / ')))}
                ${fila('Cuenta del gasto', o.cuenta_debe ? esc(o.cuenta_debe_codigo + ' ' + o.cuenta_debe) : '')}
                ${fila('Cuenta del pasivo', o.cuenta_pasivo ? esc(o.cuenta_pasivo_codigo + ' ' + o.cuenta_pasivo) : '')}${fila('Motivo de anulación', esc(o.motivo_anulacion || ''))}</table>`;
            if ((o.asiento || []).length) {
                h += '<h6 class="fw-bold">Asiento</h6><table class="table table-sm small"><thead><tr><th>Cuenta</th><th class="text-end">Debe</th><th class="text-end">Haber</th></tr></thead><tbody>'
                    + o.asiento.map(a => `<tr><td>${esc(a.codigo + ' ' + a.nombre)}</td><td class="text-end">${+a.importe_local > 0 ? Bco.num(a.importe_local) : ''}</td><td class="text-end">${+a.importe_local < 0 ? Bco.num(-a.importe_local) : ''}</td></tr>`).join('')
                    + '</tbody></table>';
            }
            if ((o.pagos || []).length) {
                h += '<h6 class="fw-bold">Pagos</h6><table class="table table-sm small"><tbody>'
                    + o.pagos.map(p => `<tr class="${+p.tabla_estado_registro_id === 38 ? 'text-decoration-line-through text-muted' : ''}"><td>${esc(p.codigo)} ${String(p.pv_mostrar).padStart(4, '0')}-${String(p.comprobante_nro).padStart(8, '0')}</td><td>${Bco.fecha(p.f_imputacion)}</td><td class="text-end">${moneda(p.importe_imputado)}</td></tr>`).join('')
                    + '</tbody></table>';
            }
            if ((o.aprobaciones || []).length) {
                h += '<h6 class="fw-bold">Aprobaciones</h6><ul class="small mb-0">'
                    + o.aprobaciones.map(a => `<li class="${+a.vigente ? '' : 'text-muted'}">${a.accion === 'APROBAR' ? 'Aprobó' : 'Rechazó'} ${esc(a.usuario || '')} el ${esc(String(a.fecha).slice(0, 16))}${a.motivo ? ': ' + esc(a.motivo) : ''}${+a.vigente || a.accion !== 'APROBAR' ? '' : ' (sin efecto)'}</li>`).join('')
                    + '</ul>';
            }
            $('#cuerpoDetalle').html(h);
            $('#modalDetalle').modal('show');
        }).catch(() => {});
    }

    // --- Importación ----------------------------------------------------------------------------------
    function listarImportaciones() {
        Bco.pedir(AJAX, { accion: 'importaciones' }, { metodo: 'GET', silencioso: true }).then((res) => {
            const l = res.data || [];
            $('#listaImportaciones').html(l.length ? '<table class="table table-sm"><tbody>' + l.map(i => `<tr>
                <td>${esc(String(i.fecha_creacion).slice(0, 16))}</td><td>${esc(i.archivo_nombre)}</td><td>${esc(i.tipo_nombre || '')}</td>
                <td>${i.filas_importadas} de ${i.filas_leidas} filas</td><td class="text-end">${moneda(i.importe_total)}</td><td>${esc(i.estado || '')}</td>
                <td>${+i.tabla_estado_registro_id === 5 ? `<button class="btn btn-sm btn-outline-danger btn-deshacer-imp" data-id="${i.importacion_id}">Deshacer</button>` : ''}</td></tr>`).join('') + '</tbody></table>'
                : '<span class="text-muted">Todavía no hay importaciones.</span>');
        }).catch(() => {});
    }

    $(document).on('click', '#btnImportar', () => {
        $('#formImportar')[0].reset();
        $('#resultadoImportar').addClass('d-none');
        importacionId = 0;
        listarImportaciones();
        $('#modalImportar').modal('show');
    });
    $('#lnkPlantilla').on('click', function (e) {
        e.preventDefault();
        window.location.href = `${AJAX}?accion=plantilla&empresa_idx=${window.EMPRESA_ID}`;
    });

    $('#formImportar').on('submit', function (e) {
        e.preventDefault();
        if (!$('#archivoImportar')[0].files.length) return Swal.fire('Revisá los datos', 'Elegí el archivo.', 'warning');
        const fd = new FormData(this);
        fd.append('accion', 'importar_previsualizar');
        const btn = $('#btnPrevisualizar').prop('disabled', true);
        Bco.pedir(AJAX, fd).then((res) => {
            const d = res.data;
            importacionId = d.importacion_id;
            const r = d.resumen;
            $('#resumenImportar').html(`<strong>${r.validas}</strong> filas válidas de ${r.leidas} por <strong>${moneda(r.importe)}</strong>`
                + (r.con_error ? ` · <span class="text-danger">${r.con_error} con error (no se importan)</span>` : '')
                + (r.faltan_roles ? ` · <span class="text-warning">${r.faltan_roles} entidades sin el rol</span>` : ''));
            $('#tablaPrevia tbody').html(d.filas.map(f => `<tr class="${f.errores.length ? 'table-danger' : (f.avisos.length ? 'table-warning' : '')}">
                <td>${f.fila}</td><td>${esc(f.tipo_nombre)}</td><td>${esc(f.entidad_nombre)} <span class="text-muted small">${esc(f.cuit)}</span></td>
                <td>${esc(f.periodo || '')}</td><td>${Bco.fecha(f.f_vto)}</td><td class="text-end">${Bco.num(f.importe)}</td>
                <td class="small">${[...f.errores, ...f.avisos].map(esc).join('<br>')}</td></tr>`).join(''));
            $('#impAsignarRoles').prop('checked', false).closest('.form-check').toggle(r.faltan_roles > 0);
            $('#btnConfirmarImportacion').prop('disabled', r.validas === 0);
            $('#resultadoImportar').removeClass('d-none');
        }).catch(() => {}).finally(() => btn.prop('disabled', false));
    });

    $('#btnConfirmarImportacion').on('click', function () {
        if (!importacionId) return;
        const btn = $(this).prop('disabled', true);
        Bco.pedir(AJAX, { accion: 'importar_confirmar', importacion_id: importacionId,
                          confirmar_ahora: $('#impConfirmar').is(':checked') ? 1 : '', asignar_roles: $('#impAsignarRoles').is(':checked') ? 1 : '' })
            .then((res) => {
                Swal.fire({ icon: 'success', title: 'Importación terminada', text: res.message });
                importacionId = 0;
                $('#resultadoImportar').addClass('d-none');
                listarImportaciones();
                recargar();
            }).catch(() => {}).finally(() => btn.prop('disabled', false));
    });

    $(document).on('click', '.btn-deshacer-imp', function () {
        const id = $(this).data('id');
        Bco.confirmar('¿Deshacer la importación?', 'Sus obligaciones quedan eliminadas. Solo se puede si ninguna está confirmada.')
            .then(() => Bco.pedir(AJAX, { accion: 'importar_deshacer', importacion_id: id }))
            .then((res) => { Bco.ok(res.message); listarImportaciones(); recargar(); }).catch(() => {});
    });

    // --- Anticipos --------------------------------------------------------------------------------------
    $(document).on('click', '#btnAnticipos', () => {
        $('#antEntidad').val('');
        $('#antOrigen, #antDestino').html('');
        $('#antImporte').val('');
        $('#modalAnticipos').modal('show');
    });
    $('#antEntidad').on('change', function () {
        $('#antOrigen, #antDestino').html('');
        if (!this.value) return;
        Bco.pedir(AJAX, { accion: 'anticipos', entidad_id: this.value }, { metodo: 'GET' }).then((res) => {
            const d = res.data;
            $('#antOrigen').html((d.anticipos || []).map(a => `<option value="${a.comprobante_id}">OP ${String(a.pv_mostrar).padStart(4, '0')}-${String(a.comprobante_nro).padStart(8, '0')} · ${Bco.fecha(a.f_emision)} · saldo ${moneda(a.importe_pendiente)}</option>`).join('')
                || '<option disabled>Sin órdenes de pago con saldo a favor</option>');
            $('#antDestino').html((d.destinos || []).map(c => `<option value="${c.comprobante_id}">${esc(c.comprobante_codigo)} ${String(c.comprobante_pv).padStart(4, '0')}-${String(c.comprobante_nro).padStart(8, '0')}${c.obligacion_tipo ? ' · ' + esc(c.obligacion_tipo) : ''} · pendiente ${moneda(c.importe_pendiente)}</option>`).join('')
                || '<option disabled>Sin deudas pendientes</option>');
        }).catch(() => {});
    });
    $('#btnAplicarAnticipo').on('click', function () {
        const origen = $('#antOrigen').val();
        const destino = $('#antDestino').val();
        if (!origen || !destino) return Swal.fire('Revisá los datos', 'Elegí el anticipo y la deuda.', 'warning');
        Bco.pedir(AJAX, { accion: 'aplicar_anticipo', op_comprobante_id: origen, destino_comprobante_id: destino, importe: $('#antImporte').val() })
            .then((res) => { Bco.ok(res.message); $('#antEntidad').trigger('change'); recargar(); }).catch(() => {});
    });
});
