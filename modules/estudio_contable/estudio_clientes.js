/**
 * JavaScript: Padrón de Clientes y Matriz Territorial
 * Módulo 4: Estudio Contable (estudio_clientes.js)
 */

$(document).ready(function () {
    let tablaClientes = null;
    let modalCliente = new bootstrap.Modal(document.getElementById('modalCliente'));
    let modalJurisdicciones = new bootstrap.Modal(document.getElementById('modalJurisdicciones'));
    let combosCache = null;

    // 1. Inicialización
    cargarCombos();
    iniciarDataTable();

    // 2. Eventos de filtros
    $('#filtroTipoCliente, #filtroCondicionIva, #filtroTerminacion, #filtroEstado').on('change', function () {
        recargarTabla();
    });

    // 3. Auto-cálculo de terminación de CUIT en tiempo real
    $('#modal_cuit').on('input', function () {
        let val = $(this).val().replace(/[^0-9]/g, '');
        if (val.length > 0) {
            let ultimo = val.slice(-1);
            $('#cuit_helper').text('Terminación calculada: ' + ultimo + (val.length === 11 ? ' (CUIT Completo)' : ''));
        } else {
            $('#cuit_helper').text('Terminación calculada: -');
        }
    });

    // 3b. Autocompletar datos al seleccionar Empresa ERP Vinculada
    $('#modal_empresa_vinculada_id').on('change', function () {
        let empId = $(this).val();
        if (!empId) {
            return;
        }

        $.getJSON('estudio_clientes_ajax.php', { accion: 'obtener_empresa_erp', empresa_vinculada_id: empId }, function (res) {
            if (!res.success || !res.data) {
                return;
            }

            let e = res.data;

            // 1. Razón Social
            if (e.empresa) {
                $('#modal_razon_social').val(e.empresa);
            }

            // 2. CUIT (dispara evento input para actualizar helper de terminación)
            if (e.cuit) {
                $('#modal_cuit').val(e.cuit).trigger('input');
            }

            // 3. Condición IVA
            if (e.condicion_fiscal_id) {
                $('#modal_condicion_iva_id').val(e.condicion_fiscal_id);
            }

            // 4. Régimen IIBB (LOCAL, CONVENIO_MULTILATERAL, EXENTO)
            if (e.ingresos_brutos_tipo) {
                $('#modal_ingresos_brutos_tipo').val(e.ingresos_brutos_tipo);
            }

            // 5. Jurisdicción Sede
            if (e.jurisdiccion_id_estudio) {
                $('#modal_jurisdiccion_sede_id').val(e.jurisdiccion_id_estudio);
            }

            // 6. Es Empleador y Cantidad Empleados
            $('#modal_es_empleador').prop('checked', e.es_empleador == 1);
            if (e.cantidad_empleados !== undefined) {
                $('#modal_cantidad_empleados').val(e.cantidad_empleados);
            }

            // 7. Tipo de cliente sugerido si no está seleccionado
            if (!$('#modal_cliente_tipo_id').val() && combosCache && combosCache.tipos && combosCache.tipos.length > 0) {
                $('#modal_cliente_tipo_id').val(combosCache.tipos[0].cliente_tipo_id);
            }

            // 8. Observaciones / Datos complementarios si está vacío o es de precarga previa
            let obsActual = $('#modal_observaciones').val().trim();
            let detalles = [];
            if (e.domicilio) detalles.push('Domicilio: ' + e.domicilio);
            if (e.telefono) detalles.push('Tel: ' + e.telefono);
            if (e.email) detalles.push('Email: ' + e.email);
            if (e.ingresos_brutos_nro) detalles.push('IIBB N°: ' + e.ingresos_brutos_nro);
            if (detalles.length > 0 && (!obsActual || obsActual.startsWith('Domicilio:') || obsActual.startsWith('Tel:'))) {
                $('#modal_observaciones').val(detalles.join(' | '));
            }

            // Notificación flotante informativa
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2500
            });
            Toast.fire({
                icon: 'info',
                title: 'Información cargada desde ' + (e.empresa || 'empresa vinculada')
            });
        }).fail(function () {
            console.error('Error al obtener datos de la empresa vinculada.');
        });
    });

    // 4. Botón Nuevo Cliente
    $('#btnNuevoCliente').on('click', function () {
        $('#formCliente')[0].reset();
        $('#modal_cliente_id').val(0);
        $('#modal_empresa_vinculada_id').val('');
        $('#bloqueConstanciaCliente').removeClass('d-none').addClass('d-flex');
        $('#modalClienteLabel').html('<i class="fas fa-user-plus me-2"></i>Registrar Cliente de Estudio');
        $('#cuit_helper').text('Terminación calculada: -');
        $('#modal_nivel_servicio').val('PADRON');
        $('#modal_origen_datos').val('LOCAL');
        alternarServicio();
        modalCliente.show();

        // Poner foco en el campo de Empresa ERP Vinculada para facilitar precarga inmediata
        setTimeout(function () {
            $('#modal_empresa_vinculada_id').focus();
        }, 400);
    });

    // 5. Submit Formulario Cliente
    $('#formCliente').on('submit', function (e) {
        e.preventDefault();
        // Un select deshabilitado (origen en nivel Padrón) no se serializa
        $('#modal_origen_datos').prop('disabled', false);
        let datos = $(this).serialize();
        alternarServicio();

        Swal.fire({
            title: 'Guardando cliente...',
            text: 'Procesando padrón y matriz tributaria',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        $.ajax({
            url: 'estudio_clientes_ajax.php',
            type: 'POST',
            data: datos + '&accion=guardar',
            dataType: 'json'
        }).done(function (res) {
            if (res.success) {
                Swal.fire('¡Éxito!', res.message, 'success');
                modalCliente.hide();
                recargarTabla();
            } else if (res.error === 'REQUIERE_EMPRESA') {
                ofrecerCrearEmpresa(res.message);
            } else {
                Swal.fire('Atención', res.message || 'Error al guardar.', 'warning');
            }
        }).fail(function () {
            Swal.fire('Error', 'Fallo en la comunicación con el servidor.', 'error');
        });
    });

    // Nivel de servicio / origen: Padrón no tiene origen propio; la URL solo aplica a servidor propio
    function alternarServicio() {
        const padron = $('#modal_nivel_servicio').val() === 'PADRON';
        if (padron) { $('#modal_origen_datos').val('LOCAL'); }
        $('#modal_origen_datos').prop('disabled', padron);
        $('#grupo_remoto_url').toggleClass('d-none', $('#modal_origen_datos').val() !== 'REMOTO');
    }
    $('#modal_nivel_servicio, #modal_origen_datos').on('change', alternarServicio);

    /**
     * El nivel elegido necesita la empresa del cliente en el ERP (FISCAL / CONTABLE / GESTION sin empresa).
     * Cliente existente: ofrece crearla (plantilla y modo de acceso) con la acción crear_empresa y vuelve a guardar
     * la ficha ya vinculada. Cliente nuevo: se da de alta con el asistente.
     */
    function ofrecerCrearEmpresa(mensaje) {
        const clienteId = parseInt($('#modal_cliente_id').val(), 10) || 0;
        if (!clienteId) {
            Swal.fire('Falta la empresa', mensaje + ' Para un cliente nuevo use "Alta con asistente", que crea la empresa y la vincula.', 'info');
            return;
        }
        $.getJSON('estudio_clientes_ajax.php', { accion: 'alta_combos' }, function (r) {
            const $cont = $('<div class="text-start small">').append($('<p>').text(mensaje + ' ¿Crearla ahora y vincularla al estudio?'));
            const $pl = $('<select class="form-select form-select-sm mb-2" id="swCePlantilla">')
                .append($('<option>').val('').text('— Sin plantilla (empresa vacía) —'));
            ((r.success && r.data.plantillas_empresas) || []).forEach(function (p) {
                $pl.append($('<option>').val(p.empresa_id).text(p.plantilla_nombre ? p.plantilla_nombre + ' (' + p.empresa + ')' : p.empresa));
            });
            const $modo = $('<select class="form-select form-select-sm" id="swCeModo">')
                .append($('<option>').val('LECTURA_ESCRITURA').text('Lectura y escritura (el estudio la gestiona)'))
                .append($('<option>').val('LECTURA').text('Solo lectura'));
            $cont.append($('<label class="form-label fw-semibold">').text('Plantilla de configuración')).append($pl)
                 .append($('<label class="form-label fw-semibold">').text('Acceso del estudio')).append($modo);
            Swal.fire({
                title: 'Crear empresa del cliente', html: $cont[0], showCancelButton: true,
                confirmButtonText: 'Crear empresa', cancelButtonText: 'Cancelar',
                preConfirm: function () { return { plantilla_empresa_id: $('#swCePlantilla').val(), modo_acceso: $('#swCeModo').val() }; }
            }).then(function (res) {
                if (!res.isConfirmed) { return; }
                Swal.fire({ title: 'Creando la empresa...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                $.post('estudio_clientes_ajax.php?accion=crear_empresa', {
                    cliente_id: clienteId, nivel_servicio: $('#modal_nivel_servicio').val(), origen_datos: $('#modal_origen_datos').val(),
                    plantilla_empresa_id: res.value.plantilla_empresa_id, modo_acceso: res.value.modo_acceso
                }, null, 'json').done(function (r2) {
                    if (!r2.success) {
                        Swal.fire('No se pudo crear la empresa', r2.message || '', 'warning');
                        return;
                    }
                    // La ficha queda vinculada a la empresa nueva: se vuelve a guardar con el resto de lo editado
                    const empId = r2.data.empresa_id;
                    if (!$('#modal_empresa_vinculada_id option[value="' + empId + '"]').length) {
                        $('#modal_empresa_vinculada_id').append($('<option>').val(empId).text($('#modal_razon_social').val()));
                    }
                    $('#modal_empresa_vinculada_id').val(String(empId));
                    $('#formCliente').trigger('submit');
                }).fail(function () {
                    Swal.fire('Error', 'Fallo en la comunicación con el servidor.', 'error');
                });
            });
        });
    }

    // 6. Cargar combos iniciales
    function cargarCombos() {
        $.getJSON('estudio_clientes_ajax.php', { accion: 'obtener_combos' }, function (res) {
            if (!res.success) return;
            combosCache = res.data;

            // Tipos de cliente
            let optTipo = '<option value="">-- Todos los tipos --</option>';
            let optTipoModal = '<option value="">Seleccione tipo...</option>';
            combosCache.tipos.forEach(t => {
                optTipo += `<option value="${t.cliente_tipo_id}">${t.nombre}</option>`;
                optTipoModal += `<option value="${t.cliente_tipo_id}">${t.nombre}</option>`;
            });
            $('#filtroTipoCliente').html(optTipo);
            $('#modal_cliente_tipo_id').html(optTipoModal);

            // Condiciones IVA
            let optIva = '<option value="">-- Todas las condiciones --</option>';
            let optIvaModal = '<option value="">Seleccione condición...</option>';
            combosCache.condiciones_iva.forEach(i => {
                optIva += `<option value="${i.condicion_fiscal_id}">${i.condicion_fiscal}</option>`;
                optIvaModal += `<option value="${i.condicion_fiscal_id}">${i.condicion_fiscal}</option>`;
            });
            $('#filtroCondicionIva').html(optIva);
            $('#modal_condicion_iva_id').html(optIvaModal);

            // Jurisdicciones
            let optJur = '<option value="">-- Seleccione sede --</option>';
            let optJurModalMatriz = '<option value="">Seleccione provincia...</option>';
            combosCache.jurisdicciones.forEach(j => {
                optJur += `<option value="${j.jurisdiccion_id}">${j.jurisdiccion_nombre} (${j.jurisdiccion_codigo})</option>`;
                optJurModalMatriz += `<option value="${j.jurisdiccion_id}">${j.jurisdiccion_nombre} (${j.jurisdiccion_codigo})</option>`;
            });
            $('#modal_jurisdiccion_sede_id').html(optJur);
            $('#mj_jurisdiccion_id').html(optJurModalMatriz);

            // Empresas ERP
            let optEmp = '<option value="">-- No vinculada / Cliente externo (O seleccionar para precargar datos) --</option>';
            combosCache.empresas_erp.forEach(e => {
                optEmp += `<option value="${e.empresa_id}">${e.empresa}</option>`;
            });
            $('#modal_empresa_vinculada_id').html(optEmp);

            // Usuarios R y A
            let optUsr = '<option value="">-- Sin asignar --</option>';
            combosCache.usuarios.forEach(u => {
                optUsr += `<option value="${u.usuario_id}">${u.usuario_nombre} (${u.usuario})</option>`;
            });
            $('#modal_usuario_r_defecto_id').html(optUsr);
            $('#modal_usuario_a_defecto_id').html(optUsr);

            // Estados
            let optEst = '<option value="">Todos los estados</option>';
            combosCache.estados.forEach(es => {
                optEst += `<option value="${es.estado_registro_id}">${es.tabla_estado_registro}</option>`;
            });
            $('#filtroEstado').html(optEst);
        });
    }

    // 7. Inicializar DataTable
    function iniciarDataTable() {
        tablaClientes = $('#tablaClientes').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: 'estudio_clientes_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.cliente_tipo_id = $('#filtroTipoCliente').val();
                    d.condicion_iva_id = $('#filtroCondicionIva').val();
                    d.terminacion_cuit = $('#filtroTerminacion').val();
                    d.estado_id = $('#filtroEstado').val();
                },
                dataSrc: function (json) {
                    return json.data || [];
                }
            },
            columns: [
                { data: 'cliente_id' },
                {
                    data: 'razon_social',
                    render: function (data, type, row) {
                        let badgeTipo = `<span class="badge bg-secondary fs-8 me-1">${row.tipo_cliente_nombre}</span>`;
                        let badgeEmp = row.es_empleador == 1 ? '<span class="badge bg-warning text-dark fs-8">F.931</span>' : '';
                        return `<div><strong>${data}</strong></div><div>${badgeTipo}${badgeEmp}</div>`;
                    }
                },
                {
                    data: 'cuit',
                    render: function (data, type, row) {
                        return `<span class="fw-semibold">${data}</span> <span class="badge bg-light text-dark border">Term: ${row.terminacion_cuit}</span>`;
                    }
                },
                { data: 'condicion_fiscal' },
                {
                    data: null,
                    render: function (data, type, row) {
                        let iibb = row.ingresos_brutos_tipo;
                        let count = row.total_jurisdicciones;
                        let badgeColor = iibb === 'CONVENIO_MULTILATERAL' ? 'bg-primary' : 'bg-secondary';
                        return `<div><span class="badge ${badgeColor}">${iibb}</span></div>
                                <button type="button" class="btn btn-xs btn-outline-info mt-1 btn-matriz" data-id="${row.cliente_id}" data-nombre="${row.razon_social}">
                                    <i class="fas fa-map-marked-alt me-1"></i>${count} jurisdicción(es)
                                </button>`;
                    }
                },
                {
                    data: 'usuario_r_nombre',
                    render: function (data) {
                        return data ? `<span class="badge bg-light text-primary border"><i class="fas fa-user me-1"></i>${data}</span>` : '<span class="text-muted fs-8">-</span>';
                    }
                },
                {
                    data: 'usuario_a_nombre',
                    render: function (data) {
                        return data ? `<span class="badge bg-light text-dark border"><i class="fas fa-user-check me-1"></i>${data}</span>` : '<span class="text-muted fs-8">-</span>';
                    }
                },
                {
                    data: 'tabla_estado_registro',
                    render: function (data, type, row) {
                        let badgeClass = 'bg-success';
                        if (row.tabla_estado_registro_id == 2) badgeClass = 'bg-danger';
                        if (row.tabla_estado_registro_id == 16) badgeClass = 'bg-warning text-dark';
                        return `<span class="badge ${badgeClass}">${data || 'Activo'}</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        return `
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary btn-editar" data-funcion="editar" data-id="${row.cliente_id}" title="Editar Cliente">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-outline-info btn-matriz" data-id="${row.cliente_id}" data-nombre="${row.razon_social}" title="Matriz IIBB">
                                    <i class="fas fa-map-marker-alt"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="visually-hidden">Toggle</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end fs-7">
                                    <li><a class="dropdown-item btn-estado" href="#" data-funcion="editar" data-id="${row.cliente_id}" data-estado="1"><i class="fas fa-check text-success me-2"></i>Marcar Activo</a></li>
                                    <li><a class="dropdown-item btn-estado" href="#" data-funcion="editar" data-id="${row.cliente_id}" data-estado="3"><i class="fas fa-pause text-warning me-2"></i>Suspender</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item btn-estado text-danger" href="#" data-funcion="inhabilitar" data-id="${row.cliente_id}" data-estado="2"><i class="fas fa-ban me-2"></i>Inactivar</a></li>
                                </ul>
                            </div>
                        `;
                    }
                }
            ],
            language: {
                url: window.APP_ASSETS_URL + 'js/datatables-i18n-1.13.4/es-ES.json'
            },
            dom: '<"row align-items-center p-2 mb-0"<"col-12 col-md-6"l><"col-12 col-md-6 text-end"f>>' +
                 '<"row"<"col-12"tr>>' +
                 '<"row align-items-center p-2 mt-2"<"col-12 col-md-5"i><"col-12 col-md-7 d-flex justify-content-end"p>>',
            initComplete: function () {
                new $.fn.dataTable.Buttons(tablaClientes, {
                    buttons: [
                        { extend: 'copyHtml5', text: 'Copiar', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'excelHtml5', title: 'Padron_Clientes', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'csvHtml5', title: 'Padron_Clientes', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'pdfHtml5', title: 'Padron_Clientes', orientation: 'landscape', exportOptions: { columns: ':not(:last-child)' } },
                        { extend: 'print', exportOptions: { columns: ':not(:last-child)' } }
                    ]
                });
            },
            order: [[1, 'asc']]
        });
    }

    $(document).on('click', '#btnRecargar', function () {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        tablaClientes.ajax.reload(function () {
            btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
        }, false);
    });
    $(document).on('click', '#btnExportarCopy', function () { tablaClientes.button('.buttons-copy').trigger(); });
    $(document).on('click', '#btnExportarExcel', function () { tablaClientes.button('.buttons-excel').trigger(); });
    $(document).on('click', '#btnExportarPDF', function () { tablaClientes.button('.buttons-pdf').trigger(); });
    $(document).on('click', '#btnExportarCSV', function () { tablaClientes.button('.buttons-csv').trigger(); });
    $(document).on('click', '#btnExportarPrint', function () { tablaClientes.button('.buttons-print').trigger(); });

    function recargarTabla() {
        if (tablaClientes) tablaClientes.ajax.reload();
    }

    // 8. Editar Cliente
    $(document).on('click', '.btn-editar', function () {
        let id = $(this).data('id');
        Swal.fire({
            title: 'Cargando datos...',
            didOpen: () => Swal.showLoading()
        });

        $.getJSON('estudio_clientes_ajax.php', { accion: 'obtener', cliente_id: id }, function (res) {
            Swal.close();
            if (!res.success) {
                Swal.fire('Error', res.message, 'error');
                return;
            }
            let c = res.data;
            $('#modal_cliente_id').val(c.cliente_id);
            $('#modal_razon_social').val(c.razon_social);
            $('#modal_cliente_tipo_id').val(c.cliente_tipo_id);
            $('#modal_cuit').val(c.cuit).trigger('input');
            $('#modal_condicion_iva_id').val(c.condicion_iva_id);
            $('#modal_mes_cierre_ejercicio').val(c.mes_cierre_ejercicio);
            $('#modal_ingresos_brutos_tipo').val(c.ingresos_brutos_tipo);
            $('#modal_jurisdiccion_sede_id').val(c.jurisdiccion_sede_id);
            $('#modal_empresa_vinculada_id').val(c.empresa_vinculada_id);
            $('#modal_es_empleador').prop('checked', c.es_empleador == 1);
            $('#modal_cantidad_empleados').val(c.cantidad_empleados);
            $('#modal_tabla_estado_registro_id').val(c.tabla_estado_registro_id);
            $('#modal_usuario_r_defecto_id').val(c.usuario_r_defecto_id);
            $('#modal_usuario_a_defecto_id').val(c.usuario_a_defecto_id);
            $('#modal_observaciones').val(c.observaciones);
            $('#modal_nivel_servicio').val(c.nivel_servicio || 'PADRON');
            $('#modal_origen_datos').val(c.origen_datos || 'LOCAL');
            $('#modal_remoto_url').val(c.remoto_url || '');
            alternarServicio();

            $('#bloqueConstanciaCliente').removeClass('d-flex').addClass('d-none');
            $('#modalClienteLabel').html('<i class="fas fa-edit me-2"></i>Modificar Cliente #' + c.cliente_id);
            modalCliente.show();
        });
    });

    // 8b. Nuevo cliente desde la constancia de inscripción de ARCA, leída con IA (assets/js/constancia-cuit-ia.js)
    $('#btnLeerConstanciaCliente').on('click', function () {
        ConstanciaCuitIa.leer({
            url: 'estudio_clientes_ajax.php',
            extraHtml: ConstanciaCuitIa.avisoClienteExistente,
            completar: function (d) {
                if (d.razon_social) $('#modal_razon_social').val(d.razon_social);
                if (d.cuit) $('#modal_cuit').val(d.cuit).trigger('input');
                if (d.condicion_fiscal_id) $('#modal_condicion_iva_id').val(String(d.condicion_fiscal_id));
                if (d.mes_cierre_ejercicio) $('#modal_mes_cierre_ejercicio').val(String(d.mes_cierre_ejercicio));
                if (d.jurisdiccion_id) $('#modal_jurisdiccion_sede_id').val(String(d.jurisdiccion_id));
                ConstanciaCuitIa.elegirTipoCliente($('#modal_cliente_tipo_id'), d);
                if (window.UiSimple) UiSimple.ok('Datos de la constancia cargados: revisalos y guardá.');
            }
        });
    });

    // 9. Abrir Matriz Territorial (Jurisdicciones)
    $(document).on('click', '.btn-matriz', function () {
        let cliente_id = $(this).data('id');
        let nombre = $(this).data('nombre');
        $('#mj_cliente_id').val(cliente_id);
        $('#mj_cliente_nombre').text(nombre);
        cargarJurisdiccionesCliente(cliente_id);
        modalJurisdicciones.show();
    });

    function cargarJurisdiccionesCliente(cliente_id) {
        $('#mj_lista_body').html('<tr><td colspan="6" class="text-center py-2"><div class="spinner-border spinner-border-sm text-primary"></div> Cargando...</td></tr>');
        $.getJSON('estudio_clientes_ajax.php', { accion: 'obtener', cliente_id: cliente_id }, function (res) {
            if (!res.success) return;
            let jur = res.data.jurisdicciones || [];
            let html = '';
            if (jur.length === 0) {
                html = '<tr><td colspan="6" class="text-center text-muted py-3">No posee jurisdicciones asignadas.</td></tr>';
            } else {
                jur.forEach(j => {
                    html += `
                        <tr>
                            <td><strong>${j.jurisdiccion_codigo}</strong></td>
                            <td>${j.jurisdiccion_nombre}</td>
                            <td><span class="badge bg-secondary fs-8">${j.tipo_inscripcion}</span></td>
                            <td class="text-end fw-semibold">${parseFloat(j.coeficiente_cm05).toFixed(4)}</td>
                            <td>${j.f_alta || '-'}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-xs btn-outline-danger btn-baja-jur" data-funcion="editar" data-id="${j.cliente_jurisdiccion_id}" title="Dar de baja">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
            }
            $('#mj_lista_body').html(html);
        });
    }

    // Submit Agregar Jurisdicción
    $('#formJurisdiccion').on('submit', function (e) {
        e.preventDefault();
        let datos = $(this).serialize();
        let cliente_id = $('#mj_cliente_id').val();

        $.post('estudio_clientes_ajax.php', datos + '&accion=guardar_jurisdiccion', function (res) {
            if (res.success) {
                cargarJurisdiccionesCliente(cliente_id);
                recargarTabla();
                $('#mj_jurisdiccion_id').val('');
                $('#mj_coeficiente_cm05').val('0.0000');
            } else {
                Swal.fire('Error', res.message, 'warning');
            }
        }, 'json');
    });

    // Dar de baja jurisdicción
    $(document).on('click', '.btn-baja-jur', function () {
        let id = $(this).data('id');
        let cliente_id = $('#mj_cliente_id').val();

        Swal.fire({
            title: '¿Dar de baja jurisdicción?',
            text: 'La jurisdicción pasará a estado inactivo con fecha de baja hoy.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, dar de baja',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (result.isConfirmed) {
                $.post('estudio_clientes_ajax.php', { accion: 'eliminar_jurisdiccion', cliente_jurisdiccion_id: id }, function (res) {
                    if (res.success) {
                        cargarJurisdiccionesCliente(cliente_id);
                        recargarTabla();
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }, 'json');
            }
        });
    });

    // 10. Cambiar Estado Cliente
    $(document).on('click', '.btn-estado', function (e) {
        e.preventDefault();
        let cliente_id = $(this).data('id');
        let estado_id = $(this).data('estado');

        $.post('estudio_clientes_ajax.php', { accion: 'cambiar_estado', cliente_id: cliente_id, nuevo_estado_id: estado_id }, function (res) {
            if (res.success) {
                recargarTabla();
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000
                });
                Toast.fire({ icon: 'success', title: res.message });
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    });
});
