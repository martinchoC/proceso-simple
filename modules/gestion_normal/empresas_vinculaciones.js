/**
 * modules/gestion_normal/empresas_vinculaciones.js
 * Lógica de frontend para el ABM de Vinculaciones y Permisos Inter-Empresas
 */

$(document).ready(function () {
    let tablaOtorgadas = null;
    let tablaRecibidas = null;
    let modalVinculacion = null;
    let modalEl = document.getElementById('modalVinculacion');
    if (modalEl) {
        modalVinculacion = new bootstrap.Modal(modalEl);
    }

    const AJAX_URL = 'empresas_vinculaciones_ajax.php';
    let empresaActivaId = parseInt($('#selectEmpresaFiltro').val()) || 0;

    // Inicializar DataTables
    function inicializarTablas() {
        if (!$.fn.DataTable.isDataTable('#tablaOtorgadas')) {
            tablaOtorgadas = $('#tablaOtorgadas').DataTable({
                language: {
                    url: window.APP_ASSETS_URL + 'js/datatables-es-ES.json'
                },
                responsive: true,
                autoWidth: false,
                columns: [
                    { data: 'empresa_destino_nombre', render: function (data, type, row) {
                        return `<div>
                                    <strong class="text-primary"><i class="fas fa-building me-1"></i>${data}</strong>
                                    <div class="small text-muted">CUIT: ${row.empresa_destino_cuit || '-'}</div>
                                </div>`;
                    }},
                    { data: 'tipo_vinculo', render: function (data) {
                        const labels = {
                            'ESTUDIO_CONTABLE': '<span class="badge bg-primary"><i class="fas fa-calculator me-1"></i>Estudio Contable</span>',
                            'AUDITORIA': '<span class="badge bg-info text-dark"><i class="fas fa-search me-1"></i>Auditoría</span>',
                            'EMPRESA_VINCULADA': '<span class="badge bg-dark"><i class="fas fa-sitemap me-1"></i>Holding / Grupo</span>',
                            'SINDICATO': '<span class="badge bg-secondary"><i class="fas fa-users me-1"></i>Sindicato</span>',
                            'OTRO': '<span class="badge bg-light text-dark">Otro</span>'
                        };
                        return labels[data] || `<span class="badge bg-secondary">${data}</span>`;
                    }},
                    { data: 'modo_acceso', render: function (data) {
                        if (data === 'LECTURA_ESCRITURA') {
                            return '<span class="badge bg-success-subtle text-success border border-success px-2 py-1"><i class="fas fa-edit me-1"></i>Lectura y Escritura</span>';
                        }
                        return '<span class="badge bg-secondary-subtle text-secondary border border-secondary px-2 py-1"><i class="fas fa-eye me-1"></i>Solo Lectura</span>';
                    }},
                    { data: null, render: function (data, type, row) {
                        let badges = [];
                        if (parseInt(row.permiso_ventas) === 1) badges.push('<span class="badge bg-primary-subtle text-primary border">Ventas</span>');
                        if (parseInt(row.permiso_compras) === 1) badges.push('<span class="badge bg-info-subtle text-info-emphasis border">Compras</span>');
                        if (parseInt(row.permiso_tesoreria) === 1) badges.push('<span class="badge bg-success-subtle text-success border">Tesorería</span>');
                        if (parseInt(row.permiso_contabilidad) === 1) badges.push('<span class="badge bg-warning-subtle text-warning-emphasis border">Contabilidad</span>');
                        if (parseInt(row.permiso_impuestos) === 1) badges.push('<span class="badge bg-danger-subtle text-danger border">Impuestos</span>');
                        if (parseInt(row.permiso_stock) === 1) badges.push('<span class="badge bg-secondary-subtle text-secondary border">Stock</span>');
                        if (parseInt(row.permiso_sindicatos) === 1) badges.push('<span class="badge bg-dark-subtle text-dark border">Sindicatos</span>');

                        return badges.length > 0 ? `<div class="d-flex flex-wrap gap-1">${badges.join('')}</div>` : '<span class="text-muted small">Sin permisos asignados</span>';
                    }},
                    { data: 'fecha_desde', render: function (data, type, row) {
                        const hasta = row.fecha_hasta ? row.fecha_hasta : 'Indefinida';
                        return `<div class="small">
                                    <div><i class="fas fa-calendar-alt me-1 text-muted"></i>Desde: ${data}</div>
                                    <div><i class="fas fa-calendar-check me-1 text-muted"></i>Hasta: ${hasta}</div>
                                </div>`;
                    }},
                    { data: 'tabla_estado_registro_id', render: function (data) {
                        if (parseInt(data) === 1) {
                            return '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Activa</span>';
                        }
                        return '<span class="badge bg-danger"><i class="fas fa-ban me-1"></i>Revocada</span>';
                    }},
                    { data: null, orderable: false, className: 'text-end', render: function (data, type, row) {
                        const esActiva = parseInt(row.tabla_estado_registro_id) === 1;
                        const btnToggle = esActiva
                            ? `<button class="btn btn-outline-danger btn-sm btn-cambiar-estado" data-funcion="inactivar" data-id="${row.vinculacion_id}" data-estado="2" title="Revocar autorización"><i class="fas fa-ban"></i></button>`
                            : `<button class="btn btn-outline-success btn-sm btn-cambiar-estado" data-funcion="inactivar" data-id="${row.vinculacion_id}" data-estado="1" title="Reactivar autorización"><i class="fas fa-check"></i></button>`;

                        return `<div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary btn-sm btn-editar" data-funcion="editar" data-id="${row.vinculacion_id}" title="Editar permisos"><i class="fas fa-edit"></i></button>
                                    ${btnToggle}
                                </div>`;
                    }}
                ]
            });
        }

        if (!$.fn.DataTable.isDataTable('#tablaRecibidas')) {
            tablaRecibidas = $('#tablaRecibidas').DataTable({
                language: {
                    url: window.APP_ASSETS_URL + 'js/datatables-es-ES.json'
                },
                responsive: true,
                autoWidth: false,
                columns: [
                    { data: 'empresa_origen_nombre', render: function (data, type, row) {
                        return `<div>
                                    <strong class="text-primary"><i class="fas fa-building me-1"></i>${data}</strong>
                                    <div class="small text-muted">CUIT: ${row.empresa_origen_cuit || '-'}</div>
                                </div>`;
                    }},
                    { data: 'tipo_vinculo', render: function (data) {
                        const labels = {
                            'ESTUDIO_CONTABLE': '<span class="badge bg-primary">Cliente de Estudio</span>',
                            'AUDITORIA': '<span class="badge bg-info text-dark">Cliente de Auditoría</span>',
                            'EMPRESA_VINCULADA': '<span class="badge bg-dark">Empresa de Grupo</span>',
                            'SINDICATO': '<span class="badge bg-secondary">Empleador Gremial</span>',
                            'OTRO': '<span class="badge bg-light text-dark">Otro</span>'
                        };
                        return labels[data] || `<span class="badge bg-secondary">${data}</span>`;
                    }},
                    { data: 'modo_acceso', render: function (data) {
                        if (data === 'LECTURA_ESCRITURA') {
                            return '<span class="badge bg-success-subtle text-success border border-success"><i class="fas fa-edit me-1"></i>Lectura y Escritura</span>';
                        }
                        return '<span class="badge bg-secondary-subtle text-secondary border border-secondary"><i class="fas fa-eye me-1"></i>Solo Lectura</span>';
                    }},
                    { data: null, render: function (data, type, row) {
                        let badges = [];
                        if (parseInt(row.permiso_ventas) === 1) badges.push('<span class="badge bg-primary-subtle text-primary border">Ventas</span>');
                        if (parseInt(row.permiso_compras) === 1) badges.push('<span class="badge bg-info-subtle text-info-emphasis border">Compras</span>');
                        if (parseInt(row.permiso_tesoreria) === 1) badges.push('<span class="badge bg-success-subtle text-success border">Tesorería</span>');
                        if (parseInt(row.permiso_contabilidad) === 1) badges.push('<span class="badge bg-warning-subtle text-warning-emphasis border">Contabilidad</span>');
                        if (parseInt(row.permiso_impuestos) === 1) badges.push('<span class="badge bg-danger-subtle text-danger border">Impuestos</span>');
                        if (parseInt(row.permiso_stock) === 1) badges.push('<span class="badge bg-secondary-subtle text-secondary border">Stock</span>');
                        if (parseInt(row.permiso_sindicatos) === 1) badges.push('<span class="badge bg-dark-subtle text-dark border">Sindicatos</span>');

                        return badges.length > 0 ? `<div class="d-flex flex-wrap gap-1">${badges.join('')}</div>` : '<span class="text-muted small">Sin permisos</span>';
                    }},
                    { data: 'fecha_desde', render: function (data, type, row) {
                        const hasta = row.fecha_hasta ? row.fecha_hasta : 'Indefinida';
                        return `<span class="small">${data} al ${hasta}</span>`;
                    }},
                    { data: 'tabla_estado_registro_id', render: function (data) {
                        if (parseInt(data) === 1) {
                            return '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Activa</span>';
                        }
                        return '<span class="badge bg-danger"><i class="fas fa-ban me-1"></i>Revocada</span>';
                    }},
                    { data: null, render: function (data, type, row) {
                        let contactos = [];
                        if (row.empresa_origen_email) contactos.push(`<a href="mailto:${row.empresa_origen_email}" class="text-decoration-none small"><i class="fas fa-envelope me-1"></i>${row.empresa_origen_email}</a>`);
                        if (row.empresa_origen_telefono) contactos.push(`<span class="small text-muted"><i class="fas fa-phone me-1"></i>${row.empresa_origen_telefono}</span>`);
                        return contactos.length > 0 ? contactos.join('<br>') : '<span class="text-muted small">-</span>';
                    }}
                ]
            });
        }
    }

    // Cargar empresas disponibles en el modal (destino)
    function cargarEmpresasDestinoModal() {
        $.ajax({
            url: AJAX_URL,
            type: 'GET',
            data: { action: 'obtener_empresas_disponibles', empresa_id: empresaActivaId },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    let select = $('#empresa_destino_id');
                    select.empty().append('<option value="">-- Seleccionar Empresa Externa --</option>');
                    res.data.forEach(function (emp) {
                        select.append(`<option value="${emp.empresa_id}">${emp.empresa} (CUIT: ${emp.documento_numero || '-'})</option>`);
                    });
                }
            }
        });
    }

    // Cargar KPIs
    function cargarKPIs() {
        $.ajax({
            url: AJAX_URL,
            type: 'GET',
            data: { action: 'obtener_kpis', empresa_id: empresaActivaId },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    $('#kpiOtorgadasActivas').text(res.data.otorgadas_activas || 0);
                    $('#kpiEstudiosContables').text(res.data.estudios_contables || 0);
                    $('#kpiModoEscritura').text(res.data.modo_escritura || 0);
                    $('#kpiRecibidasActivas').text(res.data.recibidas_activas || 0);
                }
            }
        });
    }

    // Cargar tablas
    function recargarDatos() {
        cargarKPIs();

        // Cargar otorgadas
        $.ajax({
            url: AJAX_URL,
            type: 'GET',
            data: { action: 'obtener_otorgadas', empresa_id: empresaActivaId },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    tablaOtorgadas.clear().rows.add(res.data).draw();
                    $('#badgeTotalOtorgadas').text(res.data.length);
                }
            }
        });

        // Cargar recibidas
        $.ajax({
            url: AJAX_URL,
            type: 'GET',
            data: { action: 'obtener_recibidas', empresa_id: empresaActivaId },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    tablaRecibidas.clear().rows.add(res.data).draw();
                    $('#badgeTotalRecibidas').text(res.data.length);
                }
            }
        });
    }

    // Botones Marcar / Desmarcar todos los switches
    $('#btnMarcarTodos').on('click', function () {
        $('.chk-permiso').prop('checked', true);
    });

    $('#btnDesmarcarTodos').on('click', function () {
        $('.chk-permiso').prop('checked', false);
    });

    // Abrir Modal para Nueva Vinculación
    $('#btnNuevaVinculacion').on('click', function () {
        $('#formVinculacion')[0].reset();
        $('#vinculacion_id').val('0');
        $('#form_empresa_origen_id').val(empresaActivaId);
        $('#modalVinculacionLabel').html('<i class="fas fa-handshake me-2"></i>Nueva Vinculación de Empresa');
        $('#empresa_destino_id').prop('disabled', false);

        // Valores por defecto recomendados
        $('#modoLectura').prop('checked', true);
        $('#permiso_ventas, #permiso_compras, #permiso_tesoreria, #permiso_contabilidad, #permiso_impuestos').prop('checked', true);
        $('#permiso_stock, #permiso_sindicatos').prop('checked', false);
        $('#fecha_desde').val(Fmt.hoyISO());
        $('#tabla_estado_registro_id').val('1');

        cargarEmpresasDestinoModal();
        modalVinculacion.show();
    });

    // Guardar Vinculación (Alta / Edición)
    $('#formVinculacion').on('submit', function (e) {
        e.preventDefault();

        let form = $(this);
        let btn = $('#btnGuardarVinculacion');
        let formData = form.serialize() + '&action=guardar&empresa_id=' + empresaActivaId;

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: AJAX_URL,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Vinculación');
                if (res.success) {
                    modalVinculacion.hide();
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: res.mensaje || 'Vinculación registrada correctamente',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    recargarDatos();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Atención',
                        text: res.error || 'No se pudo guardar la vinculación'
                    });
                }
            },
            error: function () {
                btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Vinculación');
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Red',
                    text: 'Ocurrió un error al comunicarse con el servidor'
                });
            }
        });
    });

    // Editar Vinculación
    $(document).on('click', '.btn-editar', function () {
        let id = $(this).data('id');
        $.ajax({
            url: AJAX_URL,
            type: 'GET',
            data: { action: 'obtener_por_id', vinculacion_id: id, empresa_id: empresaActivaId },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    let d = res.data;
                    $('#vinculacion_id').val(d.vinculacion_id);
                    $('#modalVinculacionLabel').html('<i class="fas fa-edit me-2"></i>Modificar Permisos de Vinculación');
                    
                    cargarEmpresasDestinoModal();
                    setTimeout(function () {
                        $('#empresa_destino_id').val(d.empresa_destino_id).prop('disabled', true);
                    }, 200);

                    $('#tipo_vinculo').val(d.tipo_vinculo);
                    if (d.modo_acceso === 'LECTURA_ESCRITURA') {
                        $('#modoEscritura').prop('checked', true);
                    } else {
                        $('#modoLectura').prop('checked', true);
                    }

                    $('#permiso_ventas').prop('checked', parseInt(d.permiso_ventas) === 1);
                    $('#permiso_compras').prop('checked', parseInt(d.permiso_compras) === 1);
                    $('#permiso_tesoreria').prop('checked', parseInt(d.permiso_tesoreria) === 1);
                    $('#permiso_contabilidad').prop('checked', parseInt(d.permiso_contabilidad) === 1);
                    $('#permiso_impuestos').prop('checked', parseInt(d.permiso_impuestos) === 1);
                    $('#permiso_stock').prop('checked', parseInt(d.permiso_stock) === 1);
                    $('#permiso_sindicatos').prop('checked', parseInt(d.permiso_sindicatos) === 1);

                    $('#fecha_desde').val(d.fecha_desde);
                    $('#fecha_hasta').val(d.fecha_hasta || '');
                    $('#observaciones').val(d.observaciones || '');
                    $('#tabla_estado_registro_id').val(d.tabla_estado_registro_id);

                    modalVinculacion.show();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.error || 'No se pudieron recuperar los datos de la vinculación'
                    });
                }
            }
        });
    });

    // Cambiar Estado (Revocar / Activar)
    $(document).on('click', '.btn-cambiar-estado', function () {
        let id = $(this).data('id');
        let nuevoEstado = $(this).data('estado');
        let esBaja = parseInt(nuevoEstado) === 2;

        Swal.fire({
            title: esBaja ? '¿Revocar vinculación?' : '¿Reactivar vinculación?',
            text: esBaja 
                ? 'La empresa o estudio externo ya no tendrá acceso para consultar o registrar información.' 
                : 'Se restaurarán los permisos de acceso previamente configurados.',
            icon: esBaja ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonColor: esBaja ? '#d33' : '#3085d6',
            cancelButtonColor: '#6c757d',
            confirmButtonText: esBaja ? 'Sí, revocar' : 'Sí, reactivar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: AJAX_URL,
                    type: 'POST',
                    data: {
                        action: 'cambiar_estado',
                        vinculacion_id: id,
                        nuevo_estado: nuevoEstado,
                        empresa_id: empresaActivaId
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Completado',
                                text: res.mensaje,
                                timer: 1500,
                                showConfirmButton: false
                            });
                            recargarDatos();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Atención',
                                text: res.error || 'Error al cambiar estado'
                            });
                        }
                    }
                });
            }
        });
    });

    // Cambio de empresa activa desde el selector del header
    $('#selectEmpresaFiltro').on('change', function () {
        // Recarga la página para que header1.php valide la empresa y la fije en la sesión
        window.location.href = `empresas_vinculaciones.php?empresa_id=${parseInt($(this).val(), 10) || 0}`;
    });

    // Botón Actualizar
    $('#btnRecargar').on('click', function () {
        recargarDatos();
    });

    // Inicio
    inicializarTablas();
    recargarDatos();
});
