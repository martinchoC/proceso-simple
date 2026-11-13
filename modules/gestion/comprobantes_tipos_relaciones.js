// Función "Relaciones" de Tipos de Comprobante: qué tipos cancela el tipo y de qué comprobante padre depende.
// Backend: comprobantes_tipos_ajax.php (relaciones_obtener / relaciones_guardar) -> comprobantes_tipos_relaciones_model.php
(function () {
    const AJAX = 'comprobantes_tipos_ajax.php';
    let tipoActual = 0;

    function esc(t) {
        return $('<div>').text(t == null ? '' : String(t)).html();
    }

    function etiqueta(t) {
        return `<span class="font-monospace small text-muted">${esc(t.codigo)}</span> ${esc(t.comprobante_tipo)}`
            + (t.letra ? ` <span class="badge bg-secondary">${esc(t.letra)}</span>` : '')
            + ` <span class="badge bg-light text-dark border">${esc(t.signo)}</span>`;
    }

    function error(msg) {
        Swal.fire({ icon: 'error', title: 'Relaciones', text: msg || 'No se pudo completar la operación' });
    }

    function render(d) {
        const cancela = {};
        (d.cancela || []).forEach(c => { cancela[c.comprobante_tipo_id] = c.exige_misma_entidad == 1; });
        const padres = new Set((d.padres || []).map(Number));
        const otros = (d.tipos || []).filter(t => Number(t.comprobante_tipo_id) !== tipoActual);
        const nombre = {};
        (d.tipos || []).forEach(t => { nombre[t.comprobante_tipo_id] = t.comprobante_tipo; });

        $('#relTipoNombre').text(d.tipo.comprobante_tipo + ' (' + d.tipo.codigo + ')');
        const inv = [];
        if ((d.cancelado_por || []).length) inv.push('Lo cancelan: ' + d.cancelado_por.map(i => nombre[i] || i).join(', '));
        if ((d.hijos || []).length) inv.push('Se asocian a este: ' + d.hijos.map(i => nombre[i] || i).join(', '));
        $('#relInversas').html(inv.length ? esc(inv.join(' · ')) : '');

        $('#relCancelaBody').html(otros.map(t => {
            const id = t.comprobante_tipo_id;
            const marcado = id in cancela;
            const busca = `${t.codigo} ${t.comprobante_tipo} ${t.comprobante_subgrupo || ''}`.toLowerCase();
            return `<tr data-busca="${esc(busca)}">
                <td><input class="form-check-input rel-cancela" type="checkbox" value="${id}" ${marcado ? 'checked' : ''}></td>
                <td>${etiqueta(t)}</td><td class="small">${esc(t.comprobante_subgrupo || '')}</td>
                <td class="text-center"><input class="form-check-input rel-misma" type="checkbox" data-id="${id}"
                    ${!marcado || cancela[id] ? 'checked' : ''} ${marcado ? '' : 'disabled'}></td></tr>`;
        }).join(''));
        $('#relPadresBody').html(otros.map(t => {
            const busca = `${t.codigo} ${t.comprobante_tipo} ${t.comprobante_subgrupo || ''}`.toLowerCase();
            return `<tr data-busca="${esc(busca)}">
                <td><input class="form-check-input rel-padre" type="checkbox" value="${t.comprobante_tipo_id}" ${padres.has(Number(t.comprobante_tipo_id)) ? 'checked' : ''}></td>
                <td>${etiqueta(t)}</td><td class="small">${esc(t.comprobante_subgrupo || '')}</td></tr>`;
        }).join(''));
        $('#relFiltro').val('');
    }

    window.abrirRelacionesTipo = function (tipoId) {
        tipoActual = Number(tipoId);
        $.getJSON(AJAX, { accion: 'relaciones_obtener', comprobante_tipo_id: tipoId, empresa_idx: window.EMPRESA_ID }, function (res) {
            if (!res.success) return error(res.message);
            render(res.data);
            $('#modalRelacionesTipo').modal('show');
        }).fail(xhr => error((xhr.responseJSON || {}).message));
    };

    $(document).on('change', '.rel-cancela', function () {
        $(`.rel-misma[data-id="${this.value}"]`).prop('disabled', !this.checked);
    });

    $(document).on('input', '#relFiltro', function () {
        const q = this.value.trim().toLowerCase();
        $('#relCancelaBody tr, #relPadresBody tr').each(function () {
            $(this).toggle(!q || String($(this).data('busca')).includes(q));
        });
    });

    $(document).on('click', '#btnGuardarRelaciones', function () {
        const cancela = $('.rel-cancela:checked').map(function () {
            return { comprobante_tipo_id: Number(this.value), exige_misma_entidad: $(`.rel-misma[data-id="${this.value}"]`).is(':checked') ? 1 : 0 };
        }).get();
        const padres = $('.rel-padre:checked').map(function () { return Number(this.value); }).get();
        const btn = $(this).prop('disabled', true);
        $.post(AJAX, { accion: 'relaciones_guardar', comprobante_tipo_id: tipoActual, empresa_idx: window.EMPRESA_ID,
                       cancela: JSON.stringify(cancela), padres: JSON.stringify(padres) }, function (res) {
            btn.prop('disabled', false);
            if (!res.success) return error(res.message);
            $('#modalRelacionesTipo').modal('hide');
            Swal.fire({ icon: 'success', title: res.message, timer: 1800, showConfirmButton: false });
        }, 'json').fail(xhr => { btn.prop('disabled', false); error((xhr.responseJSON || {}).message); });
    });
})();
