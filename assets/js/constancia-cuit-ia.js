/**
 * "Leer constancia (IA)": el usuario elige la Constancia de Inscripción de ARCA (PDF o foto), el servidor la lee con la
 * IA (acción leer_constancia, core/constancia_cuit_ia.php), se muestra lo leído y, si acepta, la página completa su
 * formulario. Lo usan conf/empresas.php, estudio_contable/estudio_clientes.php y empresa_inicio.php de gestion /
 * gestion_normal (alta de empresa / de cliente).
 *
 * ConstanciaCuitIa.leer({
 *     url: 'empresas_ajax.php',            // endpoint con la acción leer_constancia
 *     completar: function (datos) {...},   // llena el formulario de la página
 *     extraHtml: function (datos) {...}    // opcional: aviso propio de la página (p. ej. "ya existe")
 * });
 */
window.ConstanciaCuitIa = (function () {
    'use strict';

    const esc = (t) => $('<div>').text(t == null ? '' : String(t)).html();
    const fila = (etiqueta, valor) => valor
        ? `<tr><th class="text-muted fw-normal pe-3 text-start text-nowrap">${etiqueta}</th><td class="text-start">${esc(valor)}</td></tr>` : '';

    function elegirArchivo(callback) {
        const $input = $('<input type="file" accept="application/pdf,image/jpeg,image/png,image/webp" class="d-none">').appendTo('body');
        $input.on('change', function () {
            const archivo = this.files && this.files[0];
            $input.remove();
            if (archivo) callback(archivo);
        });
        $input.trigger('click');
    }

    function mostrar(d, opciones) {
        const avisos = (d.avisos || []).map((a) =>
            `<div class="small text-warning-emphasis text-start"><i class="fas fa-exclamation-triangle me-1"></i>${esc(a)}</div>`).join('');
        const localidad = d.localidad
            ? d.localidad + ', ' + (d.provincia || '')
            : [d.localidad_texto, d.cp ? 'CP ' + d.cp : '', d.provincia_texto].filter(Boolean).join(', ');
        const impuestos = (d.impuestos || []).map((i) => i.descripcion).join(' · ');
        const extra = typeof opciones.extraHtml === 'function' ? (opciones.extraHtml(d) || '') : '';
        const meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        return Swal.fire({
            title: 'Datos de la constancia',
            width: 640,
            html: `${extra}<table class="table table-sm mb-2"><tbody>
                ${fila('Razón social', d.razon_social)}${fila('CUIT', d.cuit)}
                ${fila('Condición IVA', (d.condicion_fiscal || '') + (d.categoria_monotributo ? ' · ' + d.categoria_monotributo : ''))}
                ${fila('Domicilio fiscal', d.domicilio)}${fila('Localidad', localidad)}
                ${fila('Impuestos', impuestos)}
                ${fila('Actividad', (d.actividades && d.actividades[0]) ? d.actividades[0].descripcion : '')}
                ${fila('Cierre de ejercicio', d.mes_cierre_ejercicio ? meses[d.mes_cierre_ejercicio] : '')}
                </tbody></table>${avisos}
                <div class="small text-muted text-start mt-2"><i class="fas fa-robot me-1"></i>Leído con IA: revisá los datos antes de guardar.</div>`,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check me-1"></i>Completar el formulario',
            cancelButtonText: 'Cancelar'
        });
    }

    function leer(opciones) {
        elegirArchivo(function (archivo) {
            if (archivo.size > 5 * 1024 * 1024) {
                Swal.fire('Archivo muy grande', 'La constancia no puede superar los 5 MB.', 'warning');
                return;
            }
            const datos = new FormData();
            datos.append('accion', 'leer_constancia');
            datos.append('constancia', archivo);
            Swal.fire({
                title: 'Leyendo la constancia…',
                html: 'La IA está leyendo el archivo. Puede tardar unos segundos.',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            $.ajax({ url: opciones.url, type: 'POST', data: datos, processData: false, contentType: false, dataType: 'json' })
                .done(function (r) {
                    if (!r || !r.success) {
                        Swal.fire('No se pudo leer la constancia', (r && r.message) || 'Error desconocido.', 'warning');
                        return;
                    }
                    mostrar(r.data, opciones).then(function (res) {
                        if (res.isConfirmed) opciones.completar(r.data);
                    });
                })
                .fail(function () {
                    Swal.fire('No se pudo leer la constancia', 'No se pudo comunicar con el servidor.', 'error');
                });
        });
    }

    /** extraHtml del padrón de clientes del estudio: el CUIT ya es cliente o ya es una empresa del ERP. */
    function avisoClienteExistente(d) {
        let html = '';
        if (d.cliente_existente) {
            html += `<div class="alert alert-warning py-2 small text-start">Ya es cliente del estudio: <strong>${esc(d.cliente_existente.razon_social)}</strong>`
                + ` (#${esc(d.cliente_existente.cliente_id)}).</div>`;
        }
        if (d.empresa_existente) {
            html += `<div class="alert alert-info py-2 small text-start">Ya existe en el ERP la empresa <strong>${esc(d.empresa_existente.empresa)}</strong>`
                + ` (#${esc(d.empresa_existente.empresa_id)}) con este CUIT.</div>`;
        }
        return html;
    }

    /**
     * Tipo de cliente del estudio (catálogo propio de cada estudio): propone, si existe, uno de sociedad para una persona
     * jurídica o uno de profesional / autónomo para una persona humana. Si no hay coincidencia no toca el combo.
     */
    function elegirTipoCliente($select, d) {
        const patron = d.tipo_persona === 'JURIDICA' ? /SOCIEDAD|EMPRESA/i : /AUT[OÓ]NOMO|PROFESIONAL|PERSONA/i;
        const $opt = $select.find('option').filter(function () { return this.value && patron.test($(this).text()); }).first();
        if ($opt.length) $select.val($opt.val());
    }

    return { leer: leer, avisoClienteExistente: avisoClienteExistente, elegirTipoCliente: elegirTipoCliente };
})();
