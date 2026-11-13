/**
 * JavaScript: Normas Legales y Motor de Reglas
 * Módulo 4: Estudio Contable (normas_gestion.js)
 */

$(document).ready(function () {
    let tablaNormas = null;
    let modalNorma = new bootstrap.Modal(document.getElementById('modalNorma'));
    let modalImpacto = new bootstrap.Modal(document.getElementById('modalImpacto'));
    let contadorReglas = 0;

    cargarCombos();
    iniciarDataTable();

    $('#filtroOrganismo, #filtroEstado').on('change', function () {
        if (tablaNormas) tablaNormas.ajax.reload();
    });

    function cargarCombos() {
        $.getJSON('normas_gestion_ajax.php', { accion: 'obtener_combos' }, function (res) {
            if (!res.success) return;
            let d = res.data;

            let optOrg = '<option value="">-- Todos los organismos --</option>';
            d.organismos.forEach(o => {
                optOrg += `<option value="${o}">${o}</option>`;
            });
            $('#filtroOrganismo').html(optOrg);

            let optOb = '<option value="">-- Seleccione obligación a vincular --</option>';
            d.obligaciones.forEach(ob => {
                optOb += `<option value="${ob.impuesto_obligacion_id}">[${ob.organismo}] ${ob.nombre} (${ob.codigo_oficial})</option>`;
            });
            $('#mn_impuesto_obligacion_id').html(optOb);
        });
    }

    function iniciarDataTable() {
        tablaNormas = $('#tablaNormas').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: 'normas_gestion_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.organismo = $('#filtroOrganismo').val();
                    d.estado_id = $('#filtroEstado').val();
                },
                dataSrc: function (json) { return json.data || []; }
            },
            columns: [
                {
                    data: 'organismo_emisor',
                    render: function (data) {
                        return `<span class="badge bg-dark fs-7">${data}</span>`;
                    }
                },
                {
                    data: 'nro_norma',
                    render: function (data, type, row) {
                        return `<div><strong>${data}</strong></div><div class="text-muted fs-8">${row.tipo_norma}</div>`;
                    }
                },
                {
                    data: 'titulo',
                    render: function (data, type, row) {
                        let badgeOb = row.genera_nueva_obligacion == 1 ? '<span class="badge bg-warning text-dark fs-8 me-1">Genera Obligación</span>' : '';
                        return `<div><strong>${data}</strong></div><div>${badgeOb}</div>`;
                    }
                },
                { data: 'vigencia_desde' },
                {
                    data: 'total_condiciones',
                    className: 'text-center',
                    render: function (data) {
                        return `<span class="badge bg-info">${data} regla(s)</span>`;
                    }
                },
                {
                    data: 'total_alcanzados',
                    className: 'text-center',
                    render: function (data, type, row) {
                        return `
                            <button type="button" class="btn btn-xs btn-outline-success btn-ver-impacto" data-id="${row.norma_id}" data-norma="${row.nro_norma}">
                                <i class="fas fa-users me-1"></i>${data} alcanzados
                            </button>
                        `;
                    }
                },
                {
                    data: 'tabla_estado_registro',
                    render: function (data, type, row) {
                        let st = parseInt(row.tabla_estado_registro_id);
                        let bClass = 'bg-secondary';
                        if (st === 5) bClass = 'bg-success';
                        if (st === 20) bClass = 'bg-danger';
                        return `<span class="badge ${bClass}">${data || 'Borrador'}</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        return `
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary btn-evaluar" data-funcion="editar" data-id="${row.norma_id}" title="Evaluar Reglas sobre Clientes">
                                    <i class="fas fa-play"></i> Evaluar
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-editar-norma" data-funcion="editar" data-id="${row.norma_id}" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        `;
                    }
                }
            ],
            language: { url: window.APP_ASSETS_URL + 'js/datatables-i18n-1.13.4/es-ES.json' },
            dom: '<"row align-items-center p-2 mb-0"<"col-12 col-md-6"l><"col-12 col-md-6 text-end"f>>' +
                 '<"row"<"col-12"tr>>' +
                 '<"row align-items-center p-2 mt-2"<"col-12 col-md-5"i><"col-12 col-md-7 d-flex justify-content-end"p>>',
            initComplete: function () {
                new $.fn.dataTable.Buttons(tablaNormas, {
                    buttons: [
                        { extend: 'copyHtml5', text: 'Copiar', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'excelHtml5', title: 'Normas_Resoluciones', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'csvHtml5', title: 'Normas_Resoluciones', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'pdfHtml5', title: 'Normas_Resoluciones', orientation: 'landscape', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'print', exportOptions: { columns: ':not(:last-child)' } }
                    ]
                });
            }
        });
    }

    $(document).on('click', '#btnRecargar', function () {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        tablaNormas.ajax.reload(function () {
            btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
        }, false);
    });
    $(document).on('click', '#btnExportarCopy', function () { tablaNormas.button('.buttons-copy').trigger(); });
    $(document).on('click', '#btnExportarExcel', function () { tablaNormas.button('.buttons-excel').trigger(); });
    $(document).on('click', '#btnExportarPDF', function () { tablaNormas.button('.buttons-pdf').trigger(); });
    $(document).on('click', '#btnExportarCSV', function () { tablaNormas.button('.buttons-csv').trigger(); });
    $(document).on('click', '#btnExportarPrint', function () { tablaNormas.button('.buttons-print').trigger(); });

    // Modal Nueva Norma
    $('#btnNuevaNorma').on('click', function () {
        $('#formNorma')[0].reset();
        $('#mn_norma_id').val(0);
        $('#reglas_body').empty();
        contadorReglas = 0;
        agregarFilaRegla('CONDICION_IVA', '=', 'RI');
        modalNorma.show();
    });

    // Agregar Fila de Regla
    $('#btnAgregarFilaRegla').on('click', function () {
        agregarFilaRegla();
    });

    function agregarFilaRegla(param = 'CONDICION_IVA', op = '=', val = '') {
        contadorReglas++;
        let idx = contadorReglas;
        let html = `
            <tr id="fila_regla_${idx}">
                <td>
                    <select class="form-select form-select-sm" name="condiciones[${idx}][parametro]">
                        <option value="CONDICION_IVA" ${param === 'CONDICION_IVA' ? 'selected' : ''}>Condición IVA (RI, MT, EX)</option>
                        <option value="EMPLEADOR" ${param === 'EMPLEADOR' ? 'selected' : ''}>Es Empleador (1 o 0)</option>
                        <option value="JURISDICCION" ${param === 'JURISDICCION' ? 'selected' : ''}>ID Jurisdicción Sede</option>
                    </select>
                </td>
                <td>
                    <select class="form-select form-select-sm" name="condiciones[${idx}][operador]">
                        <option value="=" ${op === '=' ? 'selected' : ''}>= (Igual)</option>
                        <option value="!=" ${op === '!=' ? 'selected' : ''}>!= (Distinto)</option>
                        <option value="IN" ${op === 'IN' ? 'selected' : ''}>IN (En lista separada por coma)</option>
                    </select>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm" name="condiciones[${idx}][valor_esperado]" value="${val}" required placeholder="Ej: RI o 1">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-xs btn-outline-danger btn-eliminar-regla" data-id="${idx}">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
        $('#reglas_body').append(html);
    }

    $(document).on('click', '.btn-eliminar-regla', function () {
        let id = $(this).data('id');
        $(`#fila_regla_${id}`).remove();
    });

    // Guardar Norma
    $('#formNorma').on('submit', function (e) {
        e.preventDefault();
        let datos = $(this).serialize();

        $.post('normas_gestion_ajax.php', datos + '&accion=guardar', function (res) {
            if (res.success) {
                modalNorma.hide();
                if (tablaNormas) tablaNormas.ajax.reload();
                Swal.fire('¡Éxito!', res.message, 'success');
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    });

    // Editar Norma
    $(document).on('click', '.btn-editar-norma', function () {
        let id = $(this).data('id');
        $.getJSON('normas_gestion_ajax.php', { accion: 'obtener', norma_id: id }, function (res) {
            if (!res.success) return;
            let n = res.data;
            $('#mn_norma_id').val(n.norma_id);
            $('#mn_organismo_emisor').val(n.organismo_emisor);
            $('#mn_tipo_norma').val(n.tipo_norma);
            $('#mn_nro_norma').val(n.nro_norma);
            $('#mn_f_boletin_oficial').val(n.f_boletin_oficial);
            $('#mn_vigencia_desde').val(n.vigencia_desde);
            $('#mn_vigencia_hasta').val(n.vigencia_hasta || '');
            $('#mn_titulo').val(n.titulo);
            $('#mn_resumen_tecnico').val(n.resumen_tecnico);
            $('#mn_genera_nueva_obligacion').prop('checked', n.genera_nueva_obligacion == 1);
            $('#mn_impuesto_obligacion_id').val(n.impuesto_obligacion_id || '');

            $('#reglas_body').empty();
            contadorReglas = 0;
            if (n.condiciones && n.condiciones.length > 0) {
                n.condiciones.forEach(c => {
                    agregarFilaRegla(c.parametro, c.operador, c.valor_esperado);
                });
            } else {
                agregarFilaRegla();
            }

            modalNorma.show();
        });
    });

    // Evaluar Impacto
    $(document).on('click', '.btn-evaluar', function () {
        let id = $(this).data('id');

        Swal.fire({
            title: '¿Ejecutar motor de evaluación?',
            text: 'Se analizarán todos los clientes activos del estudio contra las reglas de esta norma.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, evaluar impacto',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Evaluando reglas...',
                    didOpen: () => Swal.showLoading()
                });

                $.post('normas_gestion_ajax.php', { accion: 'evaluar_impacto', norma_id: id }, function (res) {
                    if (res.success) {
                        if (tablaNormas) tablaNormas.ajax.reload();
                        Swal.fire('¡Evaluación Completa!', res.message, 'success');
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }, 'json');
            }
        });
    });

    // Ver Impacto
    $(document).on('click', '.btn-ver-impacto', function () {
        let id = $(this).data('id');
        $('#impacto_tbody').html('<tr><td colspan="5" class="text-center py-3"><div class="spinner-border text-success"></div></td></tr>');

        $.getJSON('normas_gestion_ajax.php', { accion: 'listar_impacto', norma_id: id }, function (res) {
            if (!res.success) return;
            let items = res.data || [];
            let html = '';
            if (items.length === 0) {
                html = '<tr><td colspan="5" class="text-center text-muted py-3">La norma aún no ha sido evaluada sobre el padrón.</td></tr>';
            } else {
                items.forEach(it => {
                    let b = it.estado_impacto === 'ALCANZADO' ? 'bg-danger' : 'bg-secondary';
                    html += `
                        <tr>
                            <td><strong>${it.razon_social}</strong></td>
                            <td>${it.cuit}</td>
                            <td><span class="badge bg-light text-dark border">${it.condicion_fiscal_codigo}</span></td>
                            <td><span class="badge ${b}">${it.estado_impacto}</span></td>
                            <td>${it.f_evaluacion}</td>
                        </tr>
                    `;
                });
            }
            $('#impacto_tbody').html(html);
            modalImpacto.show();
        });
    });
});
