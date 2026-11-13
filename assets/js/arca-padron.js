/**
 * Botón "Buscar en ARCA" junto al CUIT de los formularios de Clientes, Proveedores y Entidades (gestion y gestion_normal).
 * Consulta el padrón (arca_padron_ajax.php del módulo, core/arca_padron_service.php), muestra lo que encontró y, si el
 * usuario acepta, completa razón social, tipo, condición frente al IVA, domicilio, provincia y localidad.
 * Usa los ids del formulario: #cuit, #entidad_nombre, #entidad_fantasia, #entidad_tipo_id, #condicion_fiscal_id,
 * #domicilio_legal, #provincia_id, #localidad_id, #localidad_texto, #localidad_help.
 */
(function () {
    'use strict';

    const URL_AJAX = window.ARCA_PADRON_AJAX || 'arca_padron_ajax.php';
    const esc = (t) => $('<div>').text(t == null ? '' : String(t)).html();

    function agregarBoton() {
        const $cuit = $('#cuit');
        if (!$cuit.length || $('#btnArcaPadron').length) return;
        $cuit.wrap('<div class="input-group"></div>').after(
            '<button type="button" class="btn btn-outline-primary" id="btnArcaPadron" title="Completar con los datos de ARCA">'
            + '<i class="fas fa-search me-1"></i>Buscar en ARCA</button>');
    }

    function fila(etiqueta, valor) {
        return valor ? `<tr><th class="text-muted fw-normal pe-3 text-start">${etiqueta}</th><td class="text-start">${esc(valor)}</td></tr>` : '';
    }

    function completar(d) {
        $('#entidad_nombre').val(d.razon_social);
        if (!$('#entidad_fantasia').val()) $('#entidad_fantasia').val(d.razon_social);
        if (d.entidad_tipo_id) $('#entidad_tipo_id').val(String(d.entidad_tipo_id)).trigger('change');
        if (d.condicion_fiscal_id) $('#condicion_fiscal_id').val(String(d.condicion_fiscal_id)).trigger('change');
        if (d.domicilio) $('#domicilio_legal').val(d.domicilio);
        if (d.provincia_id) {
            // El cambio de provincia limpia la localidad (inicializarBuscadorLocalidad): primero la provincia, después la localidad
            $('#provincia_id').val(String(d.provincia_id)).trigger('change');
            if (d.localidad_id) {
                $('#localidad_id').val(d.localidad_id);
                $('#localidad_texto').val(d.localidad + (d.localidad_cp ? ' (CP ' + d.localidad_cp + ')' : '')).prop('disabled', false);
                $('#localidad_help').empty();
            }
        }
        if (window.UiSimple) UiSimple.ok('Datos de ARCA cargados'); else Swal.fire({ icon: 'success', title: 'Datos de ARCA cargados', timer: 1500, showConfirmButton: false });
    }

    function mostrar(d) {
        const ex = d.entidad_existente;
        const yaExiste = ex ? `<div class="alert alert-warning py-2 small text-start">Ya existe <strong>${esc(ex.nombre)}</strong> con este CUIT`
            + ` (${[ex.es_cliente ? 'cliente' : null, ex.es_proveedor ? 'proveedor' : null].filter(Boolean).join(' y ') || 'sin rol'}).</div>` : '';
        const avisos = (d.avisos || []).map((a) => `<div class="small text-warning-emphasis text-start"><i class="fas fa-exclamation-triangle me-1"></i>${esc(a)}</div>`).join('');
        const localidad = d.localidad ? d.localidad + ', ' + (d.provincia || '') : [d.localidad_arca, d.cp ? 'CP ' + d.cp : '', d.provincia_arca].filter(Boolean).join(', ');
        Swal.fire({
            title: 'Datos de ARCA',
            width: 600,
            html: `${yaExiste}<table class="table table-sm mb-2"><tbody>
                ${fila('Razón social', d.razon_social)}${fila('CUIT', d.cuit)}
                ${fila('Tipo', d.tipo_persona === 'JURIDICA' ? 'Persona jurídica' : 'Persona física')}
                ${fila('Condición IVA', (d.condicion_fiscal || '') + (d.categoria_monotributo ? ' · ' + d.categoria_monotributo : ''))}
                ${fila('Domicilio fiscal', d.domicilio)}${fila('Localidad', localidad)}
                ${fila('Actividad', (d.actividades && d.actividades[0]) ? d.actividades[0].descripcion : '')}
                ${fila('Estado', d.estado_clave && d.estado_clave !== 'ACTIVO' ? d.estado_clave : '')}
                </tbody></table>${avisos}
                ${d.ambiente && d.ambiente !== 'Producción' ? `<div class="small text-muted text-start">Consulta en ${esc(d.ambiente)}.</div>` : ''}`,
            showCancelButton: true,
            confirmButtonText: 'Completar el formulario',
            cancelButtonText: 'Cancelar'
        }).then((r) => { if (r.isConfirmed) completar(d); });
    }

    $(document).on('click', '#btnArcaPadron', function () {
        const cuit = String($('#cuit').val() || '').replace(/\D/g, '');
        if (cuit.length !== 11) {
            Swal.fire({ icon: 'warning', title: 'Revisá el CUIT', text: 'Ingresá los 11 dígitos del CUIT para buscarlo en ARCA.' });
            return;
        }
        const $btn = $(this).prop('disabled', true);
        const html = $btn.html();
        $btn.html('<span class="spinner-border spinner-border-sm me-1"></span>Consultando…');
        $.ajax({ url: URL_AJAX, method: 'GET', dataType: 'json', timeout: 90000,
                 data: { accion: 'consultar', cuit: cuit, empresa_idx: window.EMPRESA_ID || window.empresa_idx || '' } })
            .done((res) => {
                if (res && res.success) mostrar(res.data);
                else Swal.fire({ icon: 'error', title: 'No se pudo consultar ARCA', text: (res && res.message) || 'Probá de nuevo.' });
            })
            .fail(() => Swal.fire({ icon: 'error', title: 'No se pudo consultar ARCA', text: 'No hubo respuesta del servidor. Probá de nuevo.' }))
            .always(() => $btn.prop('disabled', false).html(html));
    });

    $(agregarBoton);
})();
