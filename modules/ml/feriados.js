/**
 * Lógica JavaScript para Calendario de Feriados (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/feriados.js
 */

$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresa_idx = parseInt(urlParams.get('empresa_id') || '2', 10);
    const pagina_idx = 306;

    let tablaFeriados;

    // =========================================================================
    // 1. CARGA DE CATÁLOGOS Y BOTONES
    // =========================================================================
    function cargarTerritorios() {
        $.ajax({
            url: 'feriados_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_territorios', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    let optFiltro = '<option value="">-- Todos los territorios --</option>';
                    let optModal = '<option value="">-- Seleccionar Territorio --</option>';

                    res.data.forEach(function (t) {
                        optFiltro += `<option value="${t.territorio_id}">[${t.tipo_nombre}] ${t.nombre} (${t.codigo})</option>`;
                        optModal += `<option value="${t.territorio_id}">[${t.tipo_nombre}] ${t.nombre} (${t.codigo})</option>`;
                    });

                    $('#filtroTerritorio').html(optFiltro);
                    $('#modal_territorio_id').html(optModal);
                }
            }
        });
    }

    function cargarBotonAgregar() {
        $.ajax({
            url: 'feriados_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_boton_agregar', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    $('#contenedor-boton-agregar').html(res.data);
                }
            }
        });
    }

    // =========================================================================
    // 2. INICIALIZACIÓN DE DATATABLE
    // =========================================================================
    function inicializarDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaFeriados')) {
            $('#tablaFeriados').DataTable().destroy();
            $('#tablaFeriados tbody').empty();
        }

        tablaFeriados = $('#tablaFeriados').DataTable({
            ajax: {
                url: 'feriados_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                    d.filtro_territorio = $('#filtroTerritorio').val();
                    d.filtro_anio = $('#filtroAnio').val();
                    d.filtro_estado = $('#filtroEstado').val();
                },
                dataSrc: 'data'
            },
            columns: [
                { data: 'feriado_id', className: 'text-center fw-bold' },
                {
                    data: 'fecha',
                    className: 'text-center',
                    render: function (data) {
                        return `<span class="badge bg-light text-dark border font-monospace fs-7"><i class="far fa-calendar-day me-1 text-primary"></i>${data}</span>`;
                    }
                },
                {
                    data: 'nombre',
                    render: function (data) {
                        return `<span class="fw-semibold text-dark">${data}</span>`;
                    }
                },
                {
                    data: 'territorio_nombre',
                    render: function (data, type, row) {
                        return `<span class="fw-medium text-primary">${data}</span> <small class="text-muted">(${row.territorio_codigo})</small>`;
                    }
                },
                {
                    data: 'territorio_tipo_nombre',
                    render: function (data) {
                        return `<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">${data}</span>`;
                    }
                },
                {
                    data: 'tabla_estado_registro_id',
                    className: 'text-center',
                    render: function (data) {
                        return parseInt(data, 10) === 1
                            ? '<span class="badge bg-success">Activo</span>'
                            : '<span class="badge bg-secondary">Inactivo</span>';
                    }
                },
                {
                    data: 'acciones',
                    className: 'text-center',
                    orderable: false,
                    searchable: false
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
            },
            dom: '<"row mb-2"<"col-12 col-md-6"><"col-12 col-md-6 text-end"f>>' +
                 'rt' +
                 '<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>',
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            responsive: true,
            order: [[1, 'asc']]
        });
    }

    // =========================================================================
    // 3. EVENTOS Y FILTROS
    // =========================================================================
    $('#filtroTerritorio, #filtroAnio, #filtroEstado').on('change', function () {
        tablaFeriados.ajax.reload();
    });

    $('#btnRecargar').on('click', function () {
        tablaFeriados.ajax.reload(null, false);
    });

    // Abrir modal Nuevo
    $(document).on('click', '#btnNuevoFeriado', function () {
        $('#formFeriado')[0].reset();
        $('#feriado_id').val(0);
        $('#tituloModal').text('Nuevo Feriado');
        $('#formFeriado').removeClass('was-validated');

        // Si hay un territorio seleccionado en el filtro, preseleccionarlo
        const terrSel = $('#filtroTerritorio').val();
        if (terrSel) {
            $('#modal_territorio_id').val(terrSel);
        }

        $('#modalFeriado').modal('show');
    });

    // Abrir modal Editar
    $(document).on('click', '.btn-editar', function () {
        const id = $(this).data('id');
        $.ajax({
            url: 'feriados_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_feriado', feriado_id: id, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const f = res.data;
                    $('#feriado_id').val(f.feriado_id);
                    $('#modal_territorio_id').val(f.territorio_id);
                    $('#modal_fecha').val(f.fecha);
                    $('#modal_nombre').val(f.nombre);

                    $('#tituloModal').text('Editar Feriado #' + f.feriado_id);
                    $('#formFeriado').removeClass('was-validated');
                    $('#modalFeriado').modal('show');
                } else {
                    Swal.fire('Error', res.message || 'No se pudo obtener el feriado.', 'error');
                }
            }
        });
    });

    // Guardar (Crear o Editar)
    $('#formFeriado').on('submit', function (e) {
        e.preventDefault();
        const form = this;
        if (!form.checkValidity()) {
            e.stopPropagation();
            $(form).addClass('was-validated');
            return;
        }

        const formData = $(form).serialize() + `&accion=guardar&empresa_idx=${empresa_idx}&pagina_idx=${pagina_idx}`;

        $('#btnGuardarFeriado').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: 'feriados_ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                $('#btnGuardarFeriado').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Feriado');
                if (res.success) {
                    $('#modalFeriado').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    tablaFeriados.ajax.reload(null, false);
                } else {
                    Swal.fire('Error al guardar', res.message, 'error');
                }
            },
            error: function () {
                $('#btnGuardarFeriado').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Feriado');
                Swal.fire('Error de red', 'No se pudo comunicar con el servidor.', 'error');
            }
        });
    });

    // Eliminar / Inactivar Feriado
    $(document).on('click', '.btn-eliminar', function () {
        const id = $(this).data('id');
        const nombre = $(this).data('nombre');

        Swal.fire({
            title: '¿Eliminar feriado?',
            text: `¿Confirma que desea dar de baja el feriado "${nombre}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'feriados_ajax.php',
                    type: 'POST',
                    data: { accion: 'cambiar_estado', feriado_id: id, nuevo_estado: 2, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            Swal.fire('Eliminado', res.message, 'success');
                            tablaFeriados.ajax.reload(null, false);
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // Reactivar Feriado
    $(document).on('click', '.btn-reactivar', function () {
        const id = $(this).data('id');
        $.ajax({
            url: 'feriados_ajax.php',
            type: 'POST',
            data: { accion: 'cambiar_estado', feriado_id: id, nuevo_estado: 1, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    Swal.fire('Reactivado', res.message, 'success');
                    tablaFeriados.ajax.reload(null, false);
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }
        });
    });

    // INICIALIZACIÓN
    cargarTerritorios();
    cargarBotonAgregar();
    inicializarDataTable();
});
