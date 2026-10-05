/**
 * Lógica JavaScript para Tipos de Entidad y Tipos de Datos (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/entidades_tipos.js
 */

$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresa_idx = parseInt(urlParams.get('empresa_id') || '2', 10);
    const pagina_idx = 307;

    let tablaTiposEntidad, tablaTiposDatos;

    const dtDom = '<"row mb-2"<"col-12 col-md-6"><"col-12 col-md-6 text-end"f>>' +
                  'rt' +
                  '<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>';

    const dtLang = {
        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json',
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
    // 1. CARGA DE COMBOS
    // =========================================================================
    function cargarCombos() {
        $.ajax({
            url: 'entidades_tipos_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_combos', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const grupos = res.data.grupos || [];
                    let optGruposFiltro = '<option value="">-- Todos los grupos --</option>';
                    let optGruposModal = '<option value="">-- Seleccionar Grupo --</option>';
                    grupos.forEach(function (g) {
                        optGruposFiltro += `<option value="${g.grupo_id}">${g.nombre}</option>`;
                        optGruposModal += `<option value="${g.grupo_id}">${g.nombre}</option>`;
                    });
                    $('#filtroGrupoTipos').html(optGruposFiltro);
                    $('#modal_tipo_grupo_id').html(optGruposModal);
                }
            }
        });
    }

    // =========================================================================
    // 2. INICIALIZAR TABLAS
    // =========================================================================
    function inicializarTablaTiposEntidad() {
        if ($.fn.DataTable.isDataTable('#tablaTiposEntidad')) {
            $('#tablaTiposEntidad').DataTable().destroy();
            $('#tablaTiposEntidad tbody').empty();
        }

        tablaTiposEntidad = $('#tablaTiposEntidad').DataTable({
            ajax: {
                url: 'entidades_tipos_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar_tipos_entidad';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                    d.filtro_grupo = $('#filtroGrupoTipos').val();
                    d.filtro_estado = $('#filtroEstadoTipos').val();
                },
                dataSrc: 'data'
            },
            columns: [
                { data: 'entidad_tipo_id', className: 'text-center fw-bold' },
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
                { data: 'grupo_nombre' },
                {
                    data: null,
                    className: 'text-center',
                    render: function (data, type, row) {
                        let badges = [];
                        if (parseInt(row.es_fisica, 10) === 1) badges.push('<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 me-1">Física</span>');
                        if (parseInt(row.es_logica, 10) === 1) badges.push('<span class="badge bg-secondary bg-opacity-10 text-dark border me-1">Lógica</span>');
                        if (parseInt(row.es_movil, 10) === 1) badges.push('<span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 me-1">Móvil</span>');
                        return badges.join('') || '-';
                    }
                },
                {
                    data: 'total_entidades',
                    className: 'text-center',
                    render: function (data) {
                        return `<span class="badge bg-secondary">${data}</span>`;
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
            language: dtLang,
            dom: dtDom,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            responsive: true,
            order: [[0, 'asc']]
        });
    }

    function inicializarTablaTiposDatos() {
        if ($.fn.DataTable.isDataTable('#tablaTiposDatos')) {
            $('#tablaTiposDatos').DataTable().destroy();
            $('#tablaTiposDatos tbody').empty();
        }

        tablaTiposDatos = $('#tablaTiposDatos').DataTable({
            ajax: {
                url: 'entidades_tipos_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar_tipos_datos';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                    d.filtro_estado = $('#filtroEstadoDatos').val();
                },
                dataSrc: 'data'
            },
            columns: [
                { data: 'dato_tipo_id', className: 'text-center fw-bold' },
                {
                    data: 'codigo',
                    render: function (data) {
                        return `<span class="badge bg-light text-dark border font-monospace">${data}</span>`;
                    }
                },
                {
                    data: 'nombre',
                    render: function (data) {
                        return `<span class="fw-semibold text-dark">${data}</span>`;
                    }
                },
                {
                    data: 'tipo_valor',
                    className: 'text-center',
                    render: function (data) {
                        return `<span class="badge bg-primary bg-opacity-10 text-primary border">${data}</span>`;
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
            language: dtLang,
            dom: dtDom,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            responsive: true,
            order: [[0, 'asc']]
        });
    }

    // =========================================================================
    // 3. EVENTOS FILTROS & TABS
    // =========================================================================
    $('#filtroGrupoTipos, #filtroEstadoTipos').on('change', function () { tablaTiposEntidad.ajax.reload(); });
    $('#filtroEstadoDatos').on('change', function () { tablaTiposDatos.ajax.reload(); });

    $('#btnRecargarTipos').on('click', function () { tablaTiposEntidad.ajax.reload(null, false); });
    $('#btnRecargarDatos').on('click', function () { tablaTiposDatos.ajax.reload(null, false); });

    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust().responsive.recalc();
    });

    // =========================================================================
    // 4. MODALES TIPO ENTIDAD
    // =========================================================================
    $('#btnNuevoTipoEntidad').on('click', function () {
        $('#formTipoEntidad')[0].reset();
        $('#modal_tipo_entidad_id').val(0);
        $('#modal_es_fisica').prop('checked', true);
        $('#modal_es_logica').prop('checked', false);
        $('#modal_es_movil').prop('checked', false);

        $('#tituloModalTipo').text('Nuevo Tipo de Entidad');
        $('#formTipoEntidad').removeClass('was-validated');
        $('#modalTipoEntidad').modal('show');
    });

    $(document).on('click', '.btn-editar-tipo', function () {
        const id = $(this).data('id');
        $.ajax({
            url: 'entidades_tipos_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_tipo_entidad', entidad_tipo_id: id, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const t = res.data;
                    $('#modal_tipo_entidad_id').val(t.entidad_tipo_id);
                    $('#modal_tipo_grupo_id').val(t.grupo_id);
                    $('#modal_tipo_codigo').val(t.codigo);
                    $('#modal_tipo_nombre').val(t.nombre);
                    $('#modal_es_fisica').prop('checked', parseInt(t.es_fisica, 10) === 1);
                    $('#modal_es_logica').prop('checked', parseInt(t.es_logica, 10) === 1);
                    $('#modal_es_movil').prop('checked', parseInt(t.es_movil, 10) === 1);

                    $('#tituloModalTipo').text('Editar Tipo #' + t.entidad_tipo_id);
                    $('#formTipoEntidad').removeClass('was-validated');
                    $('#modalTipoEntidad').modal('show');
                } else {
                    Swal.fire('Error', res.message || 'No se pudo cargar el tipo.', 'error');
                }
            }
        });
    });

    $('#formTipoEntidad').on('submit', function (e) {
        e.preventDefault();
        const form = this;
        if (!form.checkValidity()) {
            e.stopPropagation();
            $(form).addClass('was-validated');
            return;
        }

        const formData = $(form).serialize() + `&accion=guardar_tipo_entidad&empresa_idx=${empresa_idx}&pagina_idx=${pagina_idx}`;
        $('#btnGuardarTipoEntidad').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: 'entidades_tipos_ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                $('#btnGuardarTipoEntidad').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Tipo');
                if (res.success) {
                    $('#modalTipoEntidad').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Éxito', text: res.message, timer: 1500, showConfirmButton: false });
                    tablaTiposEntidad.ajax.reload(null, false);
                } else {
                    Swal.fire('Error al guardar', res.message, 'error');
                }
            },
            error: function () {
                $('#btnGuardarTipoEntidad').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Tipo');
                Swal.fire('Error de red', 'No se pudo comunicar con el servidor.', 'error');
            }
        });
    });

    $(document).on('click', '.btn-estado-tipo', function () {
        const id = $(this).data('id');
        const nuevoEstado = $(this).data('estado');
        $.ajax({
            url: 'entidades_tipos_ajax.php',
            type: 'POST',
            data: { accion: 'cambiar_estado_tipo_entidad', entidad_tipo_id: id, nuevo_estado: nuevoEstado, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    Swal.fire('Actualizado', res.message, 'success');
                    tablaTiposEntidad.ajax.reload(null, false);
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }
        });
    });

    // =========================================================================
    // 5. MODALES TIPO DATO
    // =========================================================================
    $('#btnNuevoTipoDato').on('click', function () {
        $('#formTipoDato')[0].reset();
        $('#modal_tipo_dato_id').val(0);
        $('#tituloModalDato').text('Nuevo Tipo de Dato');
        $('#formTipoDato').removeClass('was-validated');
        $('#modalTipoDato').modal('show');
    });

    $(document).on('click', '.btn-editar-dato', function () {
        const id = $(this).data('id');
        $.ajax({
            url: 'entidades_tipos_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_tipo_dato', dato_tipo_id: id, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const d = res.data;
                    $('#modal_tipo_dato_id').val(d.dato_tipo_id);
                    $('#modal_dato_codigo').val(d.codigo);
                    $('#modal_dato_nombre').val(d.nombre);
                    $('#modal_dato_tipo_valor').val(d.tipo_valor);

                    $('#tituloModalDato').text('Editar Tipo de Dato #' + d.dato_tipo_id);
                    $('#formTipoDato').removeClass('was-validated');
                    $('#modalTipoDato').modal('show');
                } else {
                    Swal.fire('Error', res.message || 'No se pudo cargar el dato.', 'error');
                }
            }
        });
    });

    $('#formTipoDato').on('submit', function (e) {
        e.preventDefault();
        const form = this;
        if (!form.checkValidity()) {
            e.stopPropagation();
            $(form).addClass('was-validated');
            return;
        }

        const formData = $(form).serialize() + `&accion=guardar_tipo_dato&empresa_idx=${empresa_idx}&pagina_idx=${pagina_idx}`;
        $('#btnGuardarTipoDato').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: 'entidades_tipos_ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                $('#btnGuardarTipoDato').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Tipo de Dato');
                if (res.success) {
                    $('#modalTipoDato').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Éxito', text: res.message, timer: 1500, showConfirmButton: false });
                    tablaTiposDatos.ajax.reload(null, false);
                } else {
                    Swal.fire('Error al guardar', res.message, 'error');
                }
            },
            error: function () {
                $('#btnGuardarTipoDato').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Tipo de Dato');
                Swal.fire('Error de red', 'No se pudo comunicar con el servidor.', 'error');
            }
        });
    });

    $(document).on('click', '.btn-estado-dato', function () {
        const id = $(this).data('id');
        const nuevoEstado = $(this).data('estado');
        $.ajax({
            url: 'entidades_tipos_ajax.php',
            type: 'POST',
            data: { accion: 'cambiar_estado_tipo_dato', dato_tipo_id: id, nuevo_estado: nuevoEstado, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    Swal.fire('Actualizado', res.message, 'success');
                    tablaTiposDatos.ajax.reload(null, false);
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }
        });
    });

    // INICIALIZACIÓN
    cargarCombos();
    inicializarTablaTiposEntidad();
    inicializarTablaTiposDatos();
});
