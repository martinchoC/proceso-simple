/**
 * JavaScript: Asistente "Alta de cliente" (estudio_clientes_alta.js)
 * Módulo 4: Estudio Contable. Usa estudio_clientes_ajax.php (acciones alta_combos / alta_cliente).
 */
$(function () {
    const modalEl = document.getElementById('modalAlta');
    if (!modalEl) { return; }
    const modal = new bootstrap.Modal(modalEl, { focus: false }); // focus:false permite escribir en los SweetAlert abiertos sobre el modal
    let ejecutando = false;

    function opciones($sel, items, valor, texto, vacio) {
        $sel.empty();
        if (vacio !== undefined) { $sel.append($('<option>').val('').text(vacio)); }
        (items || []).forEach(function (it) {
            $sel.append($('<option>').val(it[valor]).text(it[texto]));
        });
    }

    function cargarCombos() {
        $.getJSON('estudio_clientes_ajax.php', { accion: 'obtener_combos' }, function (r) {
            if (!r.success) { return; }
            opciones($('#al_tipo'), r.data.tipos, 'cliente_tipo_id', 'nombre');
            opciones($('#al_cond'), r.data.condiciones_iva, 'condicion_fiscal_id', 'condicion_fiscal');
        });
        $.getJSON('estudio_clientes_ajax.php', { accion: 'alta_combos' }, function (r) {
            if (!r.success) { return; }
            // Todas las empresas con módulo de gestión activo; se copia lo mismo que "Copiar Datos Iniciales"
            opciones($('#al_plantilla'), (r.data.plantillas_empresas || []).map(function (p) {
                return { empresa_id: p.empresa_id, nombre: p.plantilla_nombre ? p.plantilla_nombre + ' (' + p.empresa + ')' : p.empresa };
            }), 'empresa_id', 'nombre', '— Sin plantilla (empresa vacía) —');
            opciones($('#al_iibb'), r.data.iibb_regimenes, 'iibb_regimen_id', 'iibb_regimen_nombre');
            opciones($('#al_sede'), (r.data.jurisdicciones_sedes || []).map(function (j) {
                return { jurisdiccion_sede_id: j.jurisdiccion_sede_id, t: j.codigo_comarb + ' - ' + j.jurisdiccion_sede };
            }), 'jurisdiccion_sede_id', 't');
            $('#al_sede').val('2');
        });
        const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $('#al_cierre').empty();
        meses.forEach(function (m, i) { $('#al_cierre').append($('<option>').val(i + 1).text(m)); });
        $('#al_cierre').val('12');
    }

    const AYUDA_NIVEL = {
        PADRON: 'No se crea empresa en el ERP.',
        FISCAL: 'Se crea una empresa simplificada vinculada al estudio, sin perfiles ni sucursal.',
        CONTABLE: 'Empresa simplificada con perfiles y sucursal, para llevar contabilidad.',
        GESTION: 'Empresa de gestión completa.'
    };

    function alternarOrigen() {
        const nivel = $('#al_nivel').val();
        const padron = nivel === 'PADRON';
        const origen = $('input[name="origen_datos"]:checked').val();
        $('#al_bloque_origen').toggleClass('d-none', padron);
        $('.al-local').toggleClass('d-none', padron || origen !== 'LOCAL');
        $('.al-remoto').toggleClass('d-none', padron || origen !== 'REMOTO');
        $('#al_nivel_ayuda').text(AYUDA_NIVEL[nivel] || '');
    }

    function pintarResultado(r) {
        const $box = $('#alResultado').removeClass('d-none').empty();
        const $h = $('<div>').addClass('alert py-2 ' + (r.success ? 'alert-success' : 'alert-danger')).text(r.message || '');
        $box.append($h);
        const pasos = (r.data && r.data.pasos) || [];
        if (pasos.length) {
            const $ul = $('<ul>').addClass('list-unstyled small mb-2');
            pasos.forEach(function (p) {
                $('<li>')
                    .append($('<i>').addClass('fas me-1 ' + (p.ok ? 'fa-check-circle text-success' : 'fa-times-circle text-danger')))
                    .append($('<strong>').text(p.paso + ': '))
                    .append(document.createTextNode(p.detalle || ''))
                    .appendTo($ul);
            });
            $box.append($ul);
        }
        const pend = (r.data && r.data.pendientes) || [];
        if (r.success && pend.length) {
            const $p = $('<div>').addClass('small');
            $p.append($('<strong>').text('Pendientes manuales:'));
            const $ul = $('<ul>').addClass('mb-0');
            pend.forEach(function (t) { $ul.append($('<li>').text(t)); });
            $box.append($p.append($ul));
        }
    }

    $('#btnAltaAsistente').on('click', function () {
        $('#formAlta')[0].reset();
        // reset() no limpia los hidden (su valor es el atributo): los de la constancia van a mano
        $('#al_localidad_id, #al_domicilio, #al_inicio_actividades').val('');
        $('#alResultado').addClass('d-none').empty();
        $('#al_sede').val('2');
        $('#al_cierre').val('12');
        alternarOrigen();
        modal.show();
    });
    $('input[name="origen_datos"], #al_nivel').on('change', alternarOrigen);

    // Datos desde la constancia de inscripción de ARCA, leída con IA (assets/js/constancia-cuit-ia.js). La empresa del
    // ERP se crea con el domicilio fiscal, la localidad y el inicio de actividades de la constancia.
    $('#btnLeerConstanciaAlta').on('click', function () {
        ConstanciaCuitIa.leer({
            url: 'estudio_clientes_ajax.php',
            extraHtml: ConstanciaCuitIa.avisoClienteExistente,
            completar: function (d) {
                const $f = $('#formAlta');
                if (d.razon_social) $f.find('[name="razon_social"]').val(d.razon_social);
                if (d.cuit) $f.find('[name="cuit"]').val(d.cuit);
                if (d.condicion_fiscal_id) $('#al_cond').val(String(d.condicion_fiscal_id));
                if (d.mes_cierre_ejercicio) $('#al_cierre').val(String(d.mes_cierre_ejercicio));
                if (d.jurisdiccion_sede_id) $('#al_sede').val(String(d.jurisdiccion_sede_id));
                ConstanciaCuitIa.elegirTipoCliente($('#al_tipo'), d);
                $('#al_localidad_id').val(d.localidad_id || '');
                $('#al_domicilio').val(d.domicilio || '');
                $('#al_inicio_actividades').val(d.inicio_actividades || '');
                if (window.UiSimple) UiSimple.ok('Datos de la constancia cargados: revisalos y creá el cliente.');
            }
        });
    });

    $('#btnAltaEjecutar').on('click', function () {
        if (ejecutando) { return; }
        const f = $('#formAlta')[0];
        if (!f.checkValidity()) { f.reportValidity(); return; }
        ejecutando = true;
        const $btn = $(this).prop('disabled', true);
        $.ajax({
            url: 'estudio_clientes_ajax.php?accion=alta_cliente',
            method: 'POST',
            data: $('#formAlta').serialize(),
            dataType: 'json'
        }).done(function (r) {
            pintarResultado(r);
            if (r.success) {
                if ($.fn.DataTable.isDataTable('#tablaClientes')) { $('#tablaClientes').DataTable().ajax.reload(null, false); }
            }
        }).fail(function () {
            pintarResultado({ success: false, message: 'Fallo en la comunicación con el servidor.' });
        }).always(function () {
            ejecutando = false;
            $btn.prop('disabled', false);
        });
    });

    // Registrar una empresa vinculada (que otorgó vinculación al estudio) como plantilla de alta
    $('#btnRegistrarPlantilla').on('click', function () {
        $.getJSON('estudio_clientes_ajax.php', { accion: 'obtener_combos' }, function (r) {
            const emps = (r.data && r.data.empresas_erp) || [];
            if (!emps.length) {
                Swal.fire('Sin empresas', 'No hay empresas que hayan otorgado una vinculación vigente al estudio.', 'info');
                return;
            }
            const inputOptions = {};
            emps.forEach(function (e) { inputOptions[e.empresa_id] = e.empresa; });
            Swal.fire({
                title: 'Registrar plantilla',
                input: 'select',
                inputOptions: inputOptions,
                inputPlaceholder: 'Empresa modelo',
                showCancelButton: true,
                confirmButtonText: 'Siguiente'
            }).then(function (sel) {
                if (!sel.isConfirmed || !sel.value) { return; }
                Swal.fire({ title: 'Nombre de la plantilla', input: 'text', inputValue: 'Cliente simplificado', showCancelButton: true })
                    .then(function (nom) {
                        if (!nom.isConfirmed || !nom.value) { return; }
                        $.post('estudio_clientes_ajax.php?accion=guardar_plantilla', { empresa_origen_id: sel.value, nombre: nom.value }, function (res) {
                            if (res.success) { cargarCombos(); Swal.fire('Listo', res.message, 'success'); }
                            else { Swal.fire('Atención', res.message || 'No se pudo guardar.', 'warning'); }
                        }, 'json');
                    });
            });
        });
    });

    cargarCombos();
});
