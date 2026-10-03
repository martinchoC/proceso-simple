/**
 * Lógica JavaScript para Eventos Societarios (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/empresas_eventos.js
 */

$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresa_idx = parseInt(urlParams.get('empresa_id') || '2', 10);
    const pagina_idx = 303;

    let tablaEventos;

    const dtDom = '<"row mb-2"<"col-12 col-md-6"><"col-12 col-md-6 text-end"f>>' +
                  'rt' +
                  '<"row mt-3 align-items-center"<"col-12 col-md-6 d-flex flex-wrap align-items-center gap-3"li><"col-12 col-md-6 d-flex justify-content-md-end justify-content-center"p>>';

    const dtLang = { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' };

    // =========================================================================
    // 1. CARGA DE CATÁLOGOS COMBOS
    // =========================================================================
    function cargarCombos() {
        $.ajax({
            url: 'empresas_eventos_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_combos', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const tipos = res.data.tipos || [];
                    const empresas = res.data.empresas || [];

                    let optTiposFiltro = '<option value="">-- Todos los tipos --</option>';
                    let optTiposModal = '<option value="">-- Seleccionar Tipo --</option>';
                    tipos.forEach(function (t) {
                        optTiposFiltro += `<option value="${t.empresa_evento_tipo_id}">${t.nombre}</option>`;
                        optTiposModal += `<option value="${t.empresa_evento_tipo_id}" data-codigo="${t.codigo}">${t.nombre}</option>`;
                    });
                    $('#filtroTipo').html(optTiposFiltro);
                    $('#modal_tipo_id').html(optTiposModal);

                    let optEmpresasFiltro = '<option value="">-- Todas las empresas --</option>';
                    let optEmpresasModal = '<option value="">-- Seleccionar Empresa --</option>';
                    let optEmpresasDestino = '<option value="">-- Ninguna / No aplica --</option>';
                    empresas.forEach(function (e) {
                        optEmpresasFiltro += `<option value="${e.empresa_id}">${e.empresa}</option>`;
                        optEmpresasModal += `<option value="${e.empresa_id}">${e.empresa} (CUIT: ${e.cuit})</option>`;
                        optEmpresasDestino += `<option value="${e.empresa_id}">${e.empresa}</option>`;
                    });
                    $('#filtroEmpresa').html(optEmpresasFiltro);
                    $('#modal_empresa_origen_id').html(optEmpresasModal);
                    $('#modal_empresa_destino_id').html(optEmpresasDestino);

                    if (res.data.permite_agregar) {
                        $('#contenedor-boton-agregar').html(`
                            <button type="button" class="btn btn-sm btn-primary" id="btnNuevoEvento">
                                <i class="fas fa-plus me-1"></i>Registrar Evento
                            </button>
                        `);
                    }
                }
            }
        });
    }

    // =========================================================================
    // 2. INICIALIZAR DATATABLE
    // =========================================================================
    function inicializarDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaEventos')) {
            $('#tablaEventos').DataTable().destroy();
            $('#tablaEventos tbody').empty();
        }

        tablaEventos = $('#tablaEventos').DataTable({
            ajax: {
                url: 'empresas_eventos_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                    d.filtro_empresa = $('#filtroEmpresa').val();
                    d.filtro_tipo = $('#filtroTipo').val();
                    d.filtro_estado = $('#filtroEstado').val();
                },
                dataSrc: 'data'
            },
            columns: [
                { data: 'empresa_evento_id', className: 'text-center fw-bold' },
                {
                    data: 'fecha',
                    className: 'text-center font-monospace',
                    render: function (data) {
                        return `<span class="badge bg-light text-dark border"><i class="far fa-calendar-alt me-1 text-primary"></i>${data}</span>`;
                    }
                },
                {
                    data: 'tipo_nombre',
                    render: function (data) {
                        return `<span class="fw-semibold text-primary">${data}</span>`;
                    }
                },
                {
                    data: 'empresa_origen_nombre',
                    render: function (data, type, row) {
                        return `<span class="fw-medium">${data}</span> <small class="text-muted">(CUIT: ${row.empresa_origen_cuit})</small>`;
                    }
                },
                {
                    data: 'empresa_destino_nombre',
                    render: function (data) {
                        return data ? `<span class="badge bg-secondary bg-opacity-10 text-dark border">${data}</span>` : '<span class="text-muted fst-italic">-</span>';
                    }
                },
                {
                    data: 'estado_nombre',
                    className: 'text-center',
                    render: function (data, type, row) {
                        return `<span class="badge bg-${row.estado_badge}">${data}</span>`;
                    }
                },
                {
                    data: 'acciones',
                    className: 'text-center',
                    orderable: false,
                    searchable: false
                }
            ],
            language: dtLang,
            dom: dtDom,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            responsive: true,
            order: [[0, 'desc']]
        });
    }

    // =========================================================================
    // 3. EVENTOS FILTROS & MODALES
    // =========================================================================
    $('#filtroEmpresa, #filtroTipo, #filtroEstado').on('change', function () {
        tablaEventos.ajax.reload();
    });

    $('#btnRecargar').on('click', function () {
        tablaEventos.ajax.reload(null, false);
    });

    $(document).on('click', '#btnNuevoEvento', function () {
        $('#formEvento')[0].reset();
        $('#empresa_evento_id').val(0);
        $('#modal_fecha').val(new Date().toISOString().slice(0, 10));
        $('#modal_empresa_origen_id').val(empresa_idx);
        $('#tituloModalEvento').text('Registrar Evento Societario');
        $('#formEvento').removeClass('was-validated');
        $('#modalEvento').modal('show');
    });

    $(document).on('click', '.btn-editar', function () {
        const id = $(this).data('id');
        $.ajax({
            url: 'empresas_eventos_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_evento', evento_id: id, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const ev = res.data;
                    $('#empresa_evento_id').val(ev.empresa_evento_id);
                    $('#modal_tipo_id').val(ev.empresa_evento_tipo_id);
                    $('#modal_empresa_origen_id').val(ev.empresa_origen_id);
                    $('#modal_empresa_destino_id').val(ev.empresa_destino_id || '');
                    $('#modal_fecha').val(ev.fecha);
                    $('#modal_observaciones').val(ev.observaciones || '');

                    $('#tituloModalEvento').text('Editar Evento #' + ev.empresa_evento_id);
                    $('#formEvento').removeClass('was-validated');
                    $('#modalEvento').modal('show');
                } else {
                    Swal.fire('Error', res.message || 'No se pudo cargar el evento.', 'error');
                }
            }
        });
    });

    $('#formEvento').on('submit', function (e) {
        e.preventDefault();
        const form = this;
        if (!form.checkValidity()) {
            e.stopPropagation();
            $(form).addClass('was-validated');
            return;
        }

        const formData = $(form).serialize() + `&accion=guardar&empresa_idx=${empresa_idx}&pagina_idx=${pagina_idx}`;
        $('#btnGuardarEvento').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: 'empresas_eventos_ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                $('#btnGuardarEvento').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar en Borrador');
                if (res.success) {
                    $('#modalEvento').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Éxito', text: res.message, timer: 1500, showConfirmButton: false });
                    tablaEventos.ajax.reload(null, false);
                } else {
                    Swal.fire('Error al guardar', res.message, 'error');
                }
            },
            error: function () {
                $('#btnGuardarEvento').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar en Borrador');
                Swal.fire('Error de red', 'No se pudo comunicar con el servidor.', 'error');
            }
        });
    });

    // Transición de estado simple (Enviar a aprobación o Eliminar)
    $(document).on('click', '.btn-transicionar', function () {
        const id = $(this).data('id');
        const estadoDestino = parseInt($(this).data('estado'), 10);

        let textoTitulo = estadoDestino === 4 ? '¿Enviar a Aprobación Legal?' : '¿Desestimar evento?';
        let textoBoton = estadoDestino === 4 ? 'Sí, enviar a aprobación' : 'Sí, desestimar';
        let colorBoton = estadoDestino === 4 ? '#ffc107' : '#d33';

        Swal.fire({
            title: textoTitulo,
            text: 'Se actualizará el estado del trámite societario.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: colorBoton,
            cancelButtonColor: '#6c757d',
            confirmButtonText: textoBoton,
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'empresas_eventos_ajax.php',
                    type: 'POST',
                    data: { accion: 'transicionar', evento_id: id, nuevo_estado: estadoDestino, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            Swal.fire('Trámite Actualizado', res.message, 'success');
                            tablaEventos.ajax.reload(null, false);
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // Confirmación y Consolidación (Modal con soporte de nueva razón social si es CAMBIO_NOMBRE)
    $(document).on('click', '.btn-confirmar-modal', function () {
        const id = $(this).data('id');
        const tipoCodigo = $(this).data('tipo');

        $('#confirmar_evento_id').val(id);
        $('#modal_nueva_razon_social').val('');

        if (tipoCodigo === 'CAMBIO_NOMBRE') {
            $('#bloqueNuevaRazonSocial').show();
            $('#modal_nueva_razon_social').prop('required', true);
        } else {
            $('#bloqueNuevaRazonSocial').hide();
            $('#modal_nueva_razon_social').prop('required', false);
        }

        $('#modalConfirmar').modal('show');
    });

    $('#formConfirmar').on('submit', function (e) {
        e.preventDefault();
        const form = this;
        if (!form.checkValidity()) {
            e.stopPropagation();
            $(form).addClass('was-validated');
            return;
        }

        const formData = $(form).serialize() + `&accion=transicionar&empresa_idx=${empresa_idx}&pagina_idx=${pagina_idx}`;
        $('#btnEjecutarConfirmacion').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Consolidando...');

        $.ajax({
            url: 'empresas_eventos_ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                $('#btnEjecutarConfirmacion').prop('disabled', false).html('<i class="fas fa-check me-1"></i>Confirmar Definitivamente');
                if (res.success) {
                    $('#modalConfirmar').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Evento Confirmado', text: res.message });
                    tablaEventos.ajax.reload(null, false);
                } else {
                    Swal.fire('Error al confirmar', res.message, 'error');
                }
            },
            error: function () {
                $('#btnEjecutarConfirmacion').prop('disabled', false).html('<i class="fas fa-check me-1"></i>Confirmar Definitivamente');
                Swal.fire('Error de red', 'No se pudo comunicar con el servidor.', 'error');
            }
        });
    });

    // Ver detalle
    $(document).on('click', '.btn-ver', function () {
        const id = $(this).data('id');
        $.ajax({
            url: 'empresas_eventos_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_evento', evento_id: id, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const ev = res.data;
                    Swal.fire({
                        title: `Evento Societario #${ev.empresa_evento_id}`,
                        html: `
                            <div class="text-start">
                                <p><strong>Tipo:</strong> ${ev.tipo_nombre}</p>
                                <p><strong>Empresa Origen:</strong> ${ev.empresa_origen_nombre}</p>
                                <p><strong>Empresa Destino:</strong> ${ev.empresa_destino_nombre || 'N/A'}</p>
                                <p><strong>Fecha Efectiva:</strong> ${ev.fecha}</p>
                                <p><strong>Observaciones:</strong><br>${ev.observaciones || 'Sin observaciones'}</p>
                            </div>
                        `,
                        icon: 'info',
                        confirmButtonText: 'Cerrar'
                    });
                }
            }
        });
    });

    // INICIALIZACIÓN
    cargarCombos();
    inicializarDataTable();
});
