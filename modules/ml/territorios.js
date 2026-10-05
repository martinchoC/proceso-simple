/**
 * Lógica JavaScript para Territorios y Husos Horarios (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/territorios.js
 */

$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresa_idx = parseInt(urlParams.get('empresa_id') || '2', 10);
    const pagina_idx = 305;

    let tablaTerritorios;

    // =========================================================================
    // 1. CARGA DE CATÁLOGOS (Filtros y Selects)
    // =========================================================================
    function cargarTiposTerritorio() {
        $.ajax({
            url: 'territorios_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_tipos', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    let opcionesFiltro = '<option value="">-- Todos los tipos --</option>';
                    let opcionesModal = '<option value="">-- Seleccionar --</option>';

                    res.data.forEach(function (tipo) {
                        opcionesFiltro += `<option value="${tipo.territorio_tipo_id}">${tipo.nombre}</option>`;
                        opcionesModal += `<option value="${tipo.territorio_tipo_id}">${tipo.nombre} (Nivel ${tipo.nivel_sugerido})</option>`;
                    });

                    $('#filtroTipo').html(opcionesFiltro);
                    $('#territorio_tipo_id').html(opcionesModal);
                }
            }
        });
    }

    function cargarSuperiores(selectId, excluirId = 0) {
        $.ajax({
            url: 'territorios_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_superiores', excluir_id: excluirId, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    let opciones = '<option value="">-- Ninguno (Nodo Raíz / Mundo) --</option>';
                    res.data.forEach(function (t) {
                        opciones += `<option value="${t.territorio_id}">[${t.tipo_nombre}] ${t.nombre} (${t.codigo})</option>`;
                    });
                    $(selectId).html(opciones);
                }
            }
        });
    }

    function cargarBotonAgregar() {
        $.ajax({
            url: 'territorios_ajax.php',
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
        if ($.fn.DataTable.isDataTable('#tablaTerritorios')) {
            $('#tablaTerritorios').DataTable().destroy();
            $('#tablaTerritorios tbody').empty();
        }

        tablaTerritorios = $('#tablaTerritorios').DataTable({
            ajax: {
                url: 'territorios_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                    d.filtro_tipo = $('#filtroTipo').val();
                    d.filtro_estado = $('#filtroEstado').val();
                },
                dataSrc: 'data'
            },
            columns: [
                { data: 'territorio_id', className: 'text-center fw-bold' },
                {
                    data: 'codigo',
                    render: function (data) {
                        return `<span class="badge bg-light text-dark border font-monospace">${data}</span>`;
                    }
                },
                {
                    data: 'nombre',
                    render: function (data) {
                        return `<span class="fw-semibold text-primary">${data}</span>`;
                    }
                },
                {
                    data: 'tipo_nombre',
                    render: function (data, type, row) {
                        return `<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">${data}</span>`;
                    }
                },
                {
                    data: 'superior_nombre',
                    render: function (data, type, row) {
                        if (!data) return '<span class="text-muted fst-italic">Nodo Raíz</span>';
                        return `<i class="fas fa-level-up-alt fa-rotate-90 text-muted me-1"></i> ${data}`;
                    }
                },
                {
                    data: 'huso_horario',
                    render: function (data) {
                        return `<small class="text-muted"><i class="far fa-clock me-1"></i>${data}</small>`;
                    }
                },
                {
                    data: 'tabla_estado_registro_id',
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (parseInt(data, 10) === 1) {
                            return '<span class="badge bg-success">Activo</span>';
                        }
                        return '<span class="badge bg-secondary">Inactivo</span>';
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
            order: [[0, 'asc']]
        });
    }

    // =========================================================================
    // 3. EVENTOS Y MODALES
    // =========================================================================
    $('#filtroTipo, #filtroEstado').on('change', function () {
        tablaTerritorios.ajax.reload();
    });

    $('#btnRecargar').on('click', function () {
        tablaTerritorios.ajax.reload(null, false);
    });

    // Abrir modal de nuevo territorio
    $(document).on('click', '#btnNuevoTerritorio', function () {
        $('#formTerritorio')[0].reset();
        $('#territorio_id').val(0);
        $('#tituloModal').text('Nuevo Territorio');
        $('#bloquePertenenciaInicial').show();
        cargarSuperiores('#territorio_superior_id', 0);
        $('#modalTerritorio').modal('show');
    });

    // Guardar (Agregar / Editar)
    $('#formTerritorio').on('submit', function (e) {
        e.preventDefault();
        const id = parseInt($('#territorio_id').val(), 10);
        const accion = id > 0 ? 'editar' : 'agregar';

        const formData = $(this).serialize() + `&accion=${accion}&empresa_idx=${empresa_idx}&pagina_idx=${pagina_idx}`;

        $('#btnGuardarTerritorio').prop('disabled', true);

        $.ajax({
            url: 'territorios_ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                $('#btnGuardarTerritorio').prop('disabled', false);
                if (res.success) {
                    $('#modalTerritorio').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    tablaTerritorios.ajax.reload(null, false);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.message || 'No se pudo guardar el territorio'
                    });
                }
            },
            error: function (xhr) {
                $('#btnGuardarTerritorio').prop('disabled', false);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Servidor',
                    text: xhr.responseJSON ? xhr.responseJSON.message : 'Error interno'
                });
            }
        });
    });

    // Botón Editar
    $(document).on('click', '.btn-accion[data-accion="editar"]', function () {
        const id = $(this).data('id');
        $.ajax({
            url: 'territorios_ajax.php',
            type: 'GET',
            data: { accion: 'obtener', id: id, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const t = res.data;
                    $('#territorio_id').val(t.territorio_id);
                    $('#codigo').val(t.codigo);
                    $('#nombre').val(t.nombre);
                    $('#territorio_tipo_id').val(t.territorio_tipo_id);
                    $('#huso_horario').val(t.huso_horario);
                    $('#tituloModal').text('Editar Territorio: ' + t.nombre);
                    $('#bloquePertenenciaInicial').hide(); // La pertenencia se cambia con el modal específico (C13)
                    $('#modalTerritorio').modal('show');
                }
            }
        });
    });

    // Botón Cambiar Pertenencia (Caso C13)
    $(document).on('click', '.btn-accion[data-accion="cambiar_pertenencia"]', function () {
        const id = $(this).data('id');
        $.ajax({
            url: 'territorios_ajax.php',
            type: 'GET',
            data: { accion: 'obtener', id: id, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const t = res.data;
                    $('#cp_territorio_id').val(t.territorio_id);
                    $('#cp_nombre_territorio').text(`[${t.codigo}] ${t.nombre} (${t.tipo_nombre})`);
                    $('#cp_superior_actual').text(t.superior_nombre || 'Ninguno (Nodo Raíz)');

                    // Cargar lista de potenciales superiores excluyendo este territorio
                    cargarSuperiores('#cp_nuevo_superior_id', t.territorio_id);

                    // Renderizar historial de pertenencias
                    let filasHistorial = '';
                    if (t.pertenencias && t.pertenencias.length > 0) {
                        t.pertenencias.forEach(function (p) {
                            const esP = parseInt(p.es_principal, 10) === 1 ? '<span class="badge bg-primary">Sí</span>' : '<span class="badge bg-light text-dark">No</span>';
                            const estado = p.vigente_hasta === null ? '<span class="badge bg-success">Abierta</span>' : '<span class="badge bg-secondary">Cerrada</span>';
                            filasHistorial += `<tr>
                                <td>${p.superior_nombre}</td>
                                <td class="text-center">${esP}</td>
                                <td>${p.vigente_desde}</td>
                                <td>${p.vigente_hasta || '<span class="text-muted">Actualidad</span>'}</td>
                                <td class="text-center">${estado}</td>
                            </tr>`;
                        });
                    } else {
                        filasHistorial = '<tr><td colspan="5" class="text-center text-muted">Sin registros de pertenencia histórica</td></tr>';
                    }
                    $('#tablaHistorialPertenencias tbody').html(filasHistorial);

                    $('#modalCambiarPertenencia').modal('show');
                }
            }
        });
    });

    // Formulario Cambiar Pertenencia submit
    $('#formCambiarPertenencia').on('submit', function (e) {
        e.preventDefault();
        const formData = $(this).serialize() + `&accion=cambiar_pertenencia&empresa_idx=${empresa_idx}&pagina_idx=${pagina_idx}`;

        $('#btnGuardarCambioPertenencia').prop('disabled', true);

        $.ajax({
            url: 'territorios_ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                $('#btnGuardarCambioPertenencia').prop('disabled', false);
                if (res.success) {
                    $('#modalCambiarPertenencia').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Pertenencia Actualizada',
                        text: res.message,
                        timer: 1800,
                        showConfirmButton: false
                    });
                    tablaTerritorios.ajax.reload(null, false);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.message
                    });
                }
            },
            error: function (xhr) {
                $('#btnGuardarCambioPertenencia').prop('disabled', false);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Servidor',
                    text: xhr.responseJSON ? xhr.responseJSON.message : 'Error al registrar cambio'
                });
            }
        });
    });

    // Acciones de Estado (Inactivar / Activar)
    $(document).on('click', '.btn-accion[data-accion="inactivar"], .btn-accion[data-accion="activar"]', function () {
        const id = $(this).data('id');
        const accion = $(this).data('accion');
        const destino = $(this).data('destino');
        const esInactivar = accion === 'inactivar';

        Swal.fire({
            title: esInactivar ? '¿Inactivar territorio?' : '¿Activar territorio?',
            text: esInactivar ? 'El territorio no podrá ser asignado como superior a nuevas entidades o normas.' : 'El territorio volverá a estar disponible.',
            icon: esInactivar ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonColor: esInactivar ? '#d33' : '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: esInactivar ? 'Sí, inactivar' : 'Sí, activar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'territorios_ajax.php',
                    type: 'POST',
                    data: {
                        accion: 'ejecutar_accion',
                        id: id,
                        accion_ejecutar: accion,
                        estado_destino: destino,
                        empresa_idx: empresa_idx,
                        pagina_idx: pagina_idx
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Actualizado',
                                text: res.message,
                                timer: 1200,
                                showConfirmButton: false
                            });
                            tablaTerritorios.ajax.reload(null, false);
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // =========================================================================
    // 4. INICIALIZACIÓN
    // =========================================================================
    cargarTiposTerritorio();
    cargarBotonAgregar();
    inicializarDataTable();
});
