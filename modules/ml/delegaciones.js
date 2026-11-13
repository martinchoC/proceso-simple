/**
 * Lógica JavaScript para Delegaciones y Suplencias (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/delegaciones.js
 */

$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresa_idx = parseInt(urlParams.get('empresa_id') || '2', 10);
    const pagina_idx = 310;

    let tablaDelegaciones;

    // Configuración estricta de DataTables: 10 registros por defecto, selector 'l' e 'i' abajo a la izquierda
    const dtDom = '<"row mb-2"<"col-12 col-md-6"><"col-12 col-md-6 text-end"f>>' +
                  'rt' +
                  '<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>';

    const dtLang = {
        url: window.APP_ASSETS_URL + 'js/datatables-i18n-1.13.6/es-ES.json',
        lengthMenu: 'Mostrar _MENU_ registros',
        info: 'Página _PAGE_ de _PAGES_',
        infoEmpty: 'Página 0 de 0',
        infoFiltered: '(filtrado de _MAX_ registros)',
        paginate: {
            first: 'Primero',
            previous: 'Anterior',
            next: 'Siguiente',
            last: 'Último'
        }
    };

    // =========================================================================
    // 1. CARGA DE CATÁLOGOS COMBOS
    // =========================================================================
    function cargarCombos() {
        $.ajax({
            url: 'delegaciones_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_combos', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const d = res.data;

                    // Combos Usuarios
                    let optTitularesFiltro = '<option value="">-- Todos los titulares --</option>';
                    let optSuplentesFiltro = '<option value="">-- Todos los suplentes --</option>';
                    let optTitularesModal = '<option value="">-- Seleccionar Titular --</option>';
                    let optSuplentesModal = '<option value="">-- Seleccionar Suplente --</option>';

                    d.usuarios.forEach(function (u) {
                        optTitularesFiltro += `<option value="${u.usuario_id}">${u.usuario}</option>`;
                        optSuplentesFiltro += `<option value="${u.usuario_id}">${u.usuario}</option>`;
                        optTitularesModal += `<option value="${u.usuario_id}">${u.usuario}</option>`;
                        optSuplentesModal += `<option value="${u.usuario_id}">${u.usuario}</option>`;
                    });

                    $('#filtroTitular').html(optTitularesFiltro);
                    $('#filtroSuplente').html(optSuplentesFiltro);
                    $('#modal_usuario_id').html(optTitularesModal);
                    $('#modal_usuario_suplente_id').html(optSuplentesModal);

                    // Combo Perfiles
                    let optPerfiles = '<option value="">-- Todos los perfiles del titular en Matriz Legal --</option>';
                    d.perfiles.forEach(function (p) {
                        optPerfiles += `<option value="${p.empresa_perfil_id}">${p.empresa_perfil_nombre}</option>`;
                    });
                    $('#modal_empresa_perfil_id').html(optPerfiles);

                    if (d.permite_agregar) {
                        $('#contenedor-boton-agregar').html(`
                            <button type="button" class="btn btn-sm btn-primary" id="btnNuevaDelegacion">
                                <i class="fas fa-plus me-1"></i>Registrar Suplencia
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
        if ($.fn.DataTable.isDataTable('#tablaDelegaciones')) {
            $('#tablaDelegaciones').DataTable().destroy();
            $('#tablaDelegaciones tbody').empty();
        }

        tablaDelegaciones = $('#tablaDelegaciones').DataTable({
            dom: dtDom,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: dtLang,
            responsive: true,
            ajax: {
                url: 'delegaciones_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                    d.filtro_titular = $('#filtroTitular').val();
                    d.filtro_suplente = $('#filtroSuplente').val();
                    d.filtro_estado = $('#filtroEstado').val();
                },
                dataSrc: function (json) {
                    if (!json.success) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error al listar',
                            text: json.message || 'No se pudieron recuperar las delegaciones.'
                        });
                        return [];
                    }
                    return json.data || [];
                }
            },
            columns: [
                {
                    data: 'delegacion_id',
                    className: 'text-center fw-bold text-muted'
                },
                {
                    data: 'titular_login',
                    render: function (val) {
                        return `<span class="fw-semibold text-primary"><i class="fas fa-user-tie me-1"></i>${val}</span>`;
                    }
                },
                {
                    data: 'suplente_login',
                    render: function (val) {
                        return `<span class="fw-semibold text-success"><i class="fas fa-user-check me-1"></i>${val}</span>`;
                    }
                },
                {
                    data: 'perfil_nombre',
                    render: function (val) {
                        return `<span class="badge bg-secondary">${val}</span>`;
                    }
                },
                {
                    data: 'motivo',
                    render: function (val) {
                        return val ? `<span class="text-truncate d-inline-block" style="max-width: 250px;" title="${val}">${val}</span>` : '<span class="text-muted fst-italic">Sin motivo especificado</span>';
                    }
                },
                {
                    data: 'vigente_desde',
                    className: 'text-center'
                },
                {
                    data: 'vigente_hasta',
                    className: 'text-center'
                },
                {
                    data: 'estado_vigencia',
                    className: 'text-center',
                    render: function (val) {
                        if (val === 'ACTIVA') {
                            return '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Activa</span>';
                        } else if (val === 'FUTURA') {
                            return '<span class="badge bg-info text-dark"><i class="fas fa-clock me-1"></i>Programada</span>';
                        } else {
                            return '<span class="badge bg-light text-muted border"><i class="fas fa-history me-1"></i>Finalizada</span>';
                        }
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        let btns = '<div class="btn-group btn-group-sm" role="group">';
                        
                        if (row.permite_cancelar) {
                            btns += `<button type="button" class="btn btn-outline-danger btn-sm btnCancelar" data-id="${row.delegacion_id}" title="Finalizar o dar de baja anticipada"><i class="fas fa-power-off"></i></button>`;
                        }

                        if (row.permite_eliminar) {
                            btns += `<button type="button" class="btn btn-outline-secondary btn-sm btnEliminar" data-id="${row.delegacion_id}" title="Eliminar registro"><i class="fas fa-trash-alt"></i></button>`;
                        }

                        btns += '</div>';
                        return btns;
                    }
                }
            ],
            order: [[5, 'desc']]
        });
    }

    // =========================================================================
    // 3. EVENTOS DE FILTROS Y RECARGA
    // =========================================================================
    $('#filtroTitular, #filtroSuplente, #filtroEstado').on('change', function () {
        if (tablaDelegaciones) {
            tablaDelegaciones.ajax.reload();
        }
    });

    $('#btnRecargar').on('click', function () {
        if (tablaDelegaciones) {
            tablaDelegaciones.ajax.reload();
        }
    });

    // =========================================================================
    // 4. MODAL ALTA DE DELEGACIÓN
    // =========================================================================
    $(document).on('click', '#btnNuevaDelegacion', function () {
        $('#formDelegacion')[0].reset();
        $('#formDelegacion').removeClass('was-validated');
        
        // Fecha desde por defecto: hoy
        const hoy = new Date().toISOString().split('T')[0];
        $('#modal_vigente_desde').val(hoy);
        $('#modal_vigente_hasta').val('');

        const modal = new bootstrap.Modal(document.getElementById('modalDelegacion'));
        modal.show();
    });

    $('#formDelegacion').on('submit', function (e) {
        e.preventDefault();

        const titularId = $('#modal_usuario_id').val();
        const suplenteId = $('#modal_usuario_suplente_id').val();
        const desde = $('#modal_vigente_desde').val();
        const hasta = $('#modal_vigente_hasta').val();

        if (!this.checkValidity()) {
            e.stopPropagation();
            $(this).addClass('was-validated');
            return;
        }

        if (titularId === suplenteId) {
            Swal.fire({
                icon: 'warning',
                title: 'Usuarios idénticos',
                text: 'El titular y el suplente no pueden ser el mismo usuario.'
            });
            return;
        }

        if (hasta < desde) {
            Swal.fire({
                icon: 'warning',
                title: 'Rango de fechas erróneo',
                text: 'La fecha de fin de vigencia debe ser posterior o igual a la de inicio.'
            });
            return;
        }

        const formData = $(this).serialize() + '&accion=guardar&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;

        $.ajax({
            url: 'delegaciones_ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Registrado',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    bootstrap.Modal.getInstance(document.getElementById('modalDelegacion')).hide();
                    tablaDelegaciones.ajax.reload();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.message || 'No se pudo registrar la suplencia.'
                    });
                }
            },
            error: function () {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de servidor',
                    text: 'Ocurrió un error en la comunicación con el servidor.'
                });
            }
        });
    });

    // =========================================================================
    // 5. CANCELAR / FINALIZAR ANTICIPADAMENTE
    // =========================================================================
    $(document).on('click', '.btnCancelar', function () {
        const id = $(this).data('id');

        Swal.fire({
            title: '¿Finalizar suplencia anticipadamente?',
            text: 'La fecha de vigencia se ajustará para dar por concluida la delegación de inmediato.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, finalizar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'delegaciones_ajax.php',
                    type: 'POST',
                    data: {
                        accion: 'cancelar',
                        delegacion_id: id,
                        empresa_idx: empresa_idx,
                        pagina_idx: pagina_idx
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Finalizada',
                                text: res.message,
                                timer: 1500,
                                showConfirmButton: false
                            });
                            tablaDelegaciones.ajax.reload();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: res.message
                            });
                        }
                    },
                    error: function () {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudo procesar la cancelación.'
                        });
                    }
                });
            }
        });
    });

    // =========================================================================
    // 6. ELIMINAR REGISTRO DE SUPLENCIA
    // =========================================================================
    $(document).on('click', '.btnEliminar', function () {
        const id = $(this).data('id');

        Swal.fire({
            title: '¿Eliminar registro?',
            text: 'Esta acción removerá el registro de la suplencia de la base de datos.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#6c757d'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'delegaciones_ajax.php',
                    type: 'POST',
                    data: {
                        accion: 'eliminar',
                        delegacion_id: id,
                        empresa_idx: empresa_idx,
                        pagina_idx: pagina_idx
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Eliminado',
                                text: res.message,
                                timer: 1500,
                                showConfirmButton: false
                            });
                            tablaDelegaciones.ajax.reload();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: res.message
                            });
                        }
                    },
                    error: function () {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudo procesar la eliminación.'
                        });
                    }
                });
            }
        });
    });

    // =========================================================================
    // 7. ARRANQUE
    // =========================================================================
    cargarCombos();
    inicializarDataTable();
});
