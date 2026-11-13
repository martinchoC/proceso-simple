/**
 * Lógica JavaScript para Grupos Corporativos y Regiones (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/grupos_regiones.js
 */

$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresa_idx = parseInt(urlParams.get('empresa_id') || '2', 10);
    const pagina_idx = 302;

    let tablaGrupos, tablaRegiones, tablaEmpresasRegiones;
    let catalogoEmpresas = [], catalogoGrupos = [], catalogoRegiones = [];

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
            url: 'grupos_regiones_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_combos', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    catalogoEmpresas = res.data.empresas || [];
                    catalogoGrupos = res.data.grupos || [];
                    catalogoRegiones = res.data.regiones || [];

                    // Combo Empresas para Grupo y Asignar
                    let optEmpresas = '<option value="">-- Seleccionar Empresa --</option>';
                    catalogoEmpresas.forEach(function (e) {
                        optEmpresas += `<option value="${e.empresa_id}">${e.empresa} (CUIT: ${e.cuit})</option>`;
                    });
                    $('#modal_grupo_empresa_titular, #modal_asignar_empresa_id').html(optEmpresas);

                    // Combo Grupos para Región y Filtros
                    let optGruposFiltro = '<option value="">-- Todos los grupos --</option>';
                    let optGruposModal = '<option value="">-- Seleccionar Grupo --</option>';
                    catalogoGrupos.forEach(function (g) {
                        optGruposFiltro += `<option value="${g.grupo_id}">${g.nombre}</option>`;
                        optGruposModal += `<option value="${g.grupo_id}">${g.nombre}</option>`;
                    });
                    $('#filtroGrupoRegiones').html(optGruposFiltro);
                    $('#modal_region_grupo_id').html(optGruposModal);

                    // Combo Regiones para Filtro y Asignar
                    let optRegionesFiltro = '<option value="">-- Todas las regiones --</option>';
                    let optRegionesModal = '<option value="">-- Seleccionar Región --</option>';
                    catalogoRegiones.forEach(function (r) {
                        optRegionesFiltro += `<option value="${r.region_grupo_id}">[${r.grupo_nombre}] ${r.nombre}</option>`;
                        optRegionesModal += `<option value="${r.region_grupo_id}">[${r.grupo_nombre}] ${r.nombre}</option>`;
                    });
                    $('#filtroRegionEmpresas').html(optRegionesFiltro);
                    $('#modal_asignar_region_id').html(optRegionesModal);
                }
            }
        });
    }

    // =========================================================================
    // 2. INICIALIZAR TABLAS
    // =========================================================================
    function inicializarTablaGrupos() {
        if ($.fn.DataTable.isDataTable('#tablaGrupos')) {
            $('#tablaGrupos').DataTable().destroy();
            $('#tablaGrupos tbody').empty();
        }

        tablaGrupos = $('#tablaGrupos').DataTable({
            ajax: {
                url: 'grupos_regiones_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar_grupos';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                    d.filtro_estado = $('#filtroEstadoGrupos').val();
                },
                dataSrc: 'data'
            },
            columns: [
                { data: 'grupo_id', className: 'text-center fw-bold' },
                {
                    data: 'nombre',
                    render: function (data) {
                        return `<span class="fw-semibold text-primary"><i class="fas fa-layer-group me-1 text-muted"></i>${data}</span>`;
                    }
                },
                {
                    data: 'empresa_titular_nombre',
                    render: function (data) {
                        return `<span class="badge bg-light text-dark border"><i class="fas fa-building me-1"></i>${data}</span>`;
                    }
                },
                {
                    data: 'total_regiones',
                    className: 'text-center',
                    render: function (data) {
                        return `<span class="badge bg-secondary">${data} regiones</span>`;
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

    function inicializarTablaRegiones() {
        if ($.fn.DataTable.isDataTable('#tablaRegiones')) {
            $('#tablaRegiones').DataTable().destroy();
            $('#tablaRegiones tbody').empty();
        }

        tablaRegiones = $('#tablaRegiones').DataTable({
            ajax: {
                url: 'grupos_regiones_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar_regiones';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                    d.filtro_grupo = $('#filtroGrupoRegiones').val();
                },
                dataSrc: 'data'
            },
            columns: [
                { data: 'region_grupo_id', className: 'text-center fw-bold' },
                {
                    data: 'nombre',
                    render: function (data) {
                        return `<span class="fw-semibold text-dark"><i class="fas fa-map-pin me-1 text-primary"></i>${data}</span>`;
                    }
                },
                {
                    data: 'grupo_nombre',
                    render: function (data) {
                        return `<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">${data}</span>`;
                    }
                },
                {
                    data: 'vigente_desde',
                    className: 'text-center font-monospace',
                    render: function (data) {
                        return `<small>${data}</small>`;
                    }
                },
                {
                    data: 'vigente_hasta',
                    className: 'text-center font-monospace',
                    render: function (data) {
                        return data ? `<small class="text-danger">${data}</small>` : '<span class="badge bg-light text-muted border">Vigente</span>';
                    }
                },
                {
                    data: 'total_empresas_activas',
                    className: 'text-center',
                    render: function (data, type, row) {
                        return `<a href="javascript:void(0)" class="badge bg-primary text-decoration-none btn-ver-empresas-region" data-region-id="${row.region_grupo_id}" title="Ver y editar empresas de esta región">${data} empresas <i class="fas fa-arrow-right ms-1"></i></a>`;
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

    function inicializarTablaEmpresasRegiones() {
        if ($.fn.DataTable.isDataTable('#tablaEmpresasRegiones')) {
            $('#tablaEmpresasRegiones').DataTable().destroy();
            $('#tablaEmpresasRegiones tbody').empty();
        }

        tablaEmpresasRegiones = $('#tablaEmpresasRegiones').DataTable({
            ajax: {
                url: 'grupos_regiones_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar_empresas_regiones';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                    d.filtro_region = $('#filtroRegionEmpresas').val();
                },
                dataSrc: 'data'
            },
            columns: [
                { data: 'empresa_region_id', className: 'text-center fw-bold' },
                {
                    data: 'empresa_nombre',
                    render: function (data, type, row) {
                        return `<span class="fw-semibold text-primary">${data}</span> <small class="text-muted">(CUIT: ${row.cuit})</small>`;
                    }
                },
                {
                    data: 'region_nombre',
                    render: function (data) {
                        return `<span class="badge bg-secondary bg-opacity-10 text-dark border">${data}</span>`;
                    }
                },
                { data: 'grupo_nombre' },
                {
                    data: 'vigente_desde',
                    className: 'text-center font-monospace',
                    render: function (data) {
                        return `<small>${data}</small>`;
                    }
                },
                {
                    data: 'vigente_hasta',
                    className: 'text-center font-monospace',
                    render: function (data) {
                        return data ? `<small class="text-danger">${data}</small>` : '<span class="badge bg-light text-muted border">Abierta</span>';
                    }
                },
                {
                    data: 'es_activa',
                    className: 'text-center',
                    render: function (data) {
                        return parseInt(data, 10) === 1
                            ? '<span class="badge bg-success">Activa</span>'
                            : '<span class="badge bg-secondary">Cerrada</span>';
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
    // 3. EVENTOS FILTROS & TABS
    // =========================================================================
    $('#filtroEstadoGrupos').on('change', function () { tablaGrupos.ajax.reload(); });
    $('#filtroGrupoRegiones').on('change', function () { tablaRegiones.ajax.reload(); });
    $('#filtroRegionEmpresas').on('change', function () { tablaEmpresasRegiones.ajax.reload(); });

    $('#btnRecargarGrupos').on('click', function () { tablaGrupos.ajax.reload(null, false); });
    $('#btnRecargarRegiones').on('click', function () { tablaRegiones.ajax.reload(null, false); });
    $('#btnRecargarEmpresasRegiones').on('click', function () { tablaEmpresasRegiones.ajax.reload(null, false); });

    // Ajustar columnas de DataTables al cambiar de pestaña
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust().responsive.recalc();
    });

    // =========================================================================
    // 4. MODALES GRUPO
    // =========================================================================
    $('#btnNuevoGrupo').on('click', function () {
        $('#formGrupo')[0].reset();
        $('#grupo_id').val(0);
        $('#tituloModalGrupo').text('Nuevo Grupo Corporativo');
        $('#formGrupo').removeClass('was-validated');
        $('#modalGrupo').modal('show');
    });

    $(document).on('click', '.btn-editar-grupo', function () {
        const id = $(this).data('id');
        $.ajax({
            url: 'grupos_regiones_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_grupo', grupo_id: id, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const g = res.data;
                    $('#grupo_id').val(g.grupo_id);
                    $('#modal_grupo_nombre').val(g.nombre);
                    $('#modal_grupo_empresa_titular').val(g.empresa_titular_id);

                    $('#tituloModalGrupo').text('Editar Grupo #' + g.grupo_id);
                    $('#formGrupo').removeClass('was-validated');
                    $('#modalGrupo').modal('show');
                } else {
                    Swal.fire('Error', res.message || 'No se pudo cargar el grupo.', 'error');
                }
            }
        });
    });

    $('#formGrupo').on('submit', function (e) {
        e.preventDefault();
        const form = this;
        if (!form.checkValidity()) {
            e.stopPropagation();
            $(form).addClass('was-validated');
            return;
        }

        const formData = $(form).serialize() + `&accion=guardar_grupo&empresa_idx=${empresa_idx}&pagina_idx=${pagina_idx}`;
        $('#btnGuardarGrupo').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: 'grupos_regiones_ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                $('#btnGuardarGrupo').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Grupo');
                if (res.success) {
                    $('#modalGrupo').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Éxito', text: res.message, timer: 1500, showConfirmButton: false });
                    tablaGrupos.ajax.reload(null, false);
                    cargarCombos();
                } else {
                    Swal.fire('Error al guardar', res.message, 'error');
                }
            },
            error: function () {
                $('#btnGuardarGrupo').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Grupo');
                Swal.fire('Error de red', 'No se pudo comunicar con el servidor.', 'error');
            }
        });
    });

    // Inactivar / Activar Grupo
    $(document).on('click', '.btn-estado-grupo', function () {
        const id = $(this).data('id');
        const nuevoEstado = $(this).data('estado');
        const accionTexto = nuevoEstado === 1 ? 'activar' : 'inactivar';

        Swal.fire({
            title: `¿Confirma ${accionTexto} el grupo?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'grupos_regiones_ajax.php',
                    type: 'POST',
                    data: { accion: 'cambiar_estado_grupo', grupo_id: id, nuevo_estado: nuevoEstado, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            Swal.fire('Actualizado', res.message, 'success');
                            tablaGrupos.ajax.reload(null, false);
                            cargarCombos();
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // =========================================================================
    // 5. MODALES REGIÓN
    // =========================================================================
    $('#btnNuevaRegion').on('click', function () {
        $('#formRegion')[0].reset();
        $('#region_grupo_id').val(0);
        $('#modal_region_vigente_desde').val(new Date().toISOString().slice(0, 10));
        $('#tituloModalRegion').text('Nueva Región de Gestión');
        $('#formRegion').removeClass('was-validated');

        const gSel = $('#filtroGrupoRegiones').val();
        if (gSel) $('#modal_region_grupo_id').val(gSel);

        $('#modalRegion').modal('show');
    });

    $(document).on('click', '.btn-editar-region', function () {
        const id = $(this).data('id');
        $.ajax({
            url: 'grupos_regiones_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_region', region_grupo_id: id, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const r = res.data;
                    $('#region_grupo_id').val(r.region_grupo_id);
                    $('#modal_region_grupo_id').val(r.grupo_id);
                    $('#modal_region_nombre').val(r.nombre);
                    $('#modal_region_vigente_desde').val(r.vigente_desde);
                    $('#modal_region_vigente_hasta').val(r.vigente_hasta || '');

                    $('#tituloModalRegion').text('Editar Región #' + r.region_grupo_id);
                    $('#formRegion').removeClass('was-validated');
                    $('#modalRegion').modal('show');
                } else {
                    Swal.fire('Error', res.message || 'No se pudo cargar la región.', 'error');
                }
            }
        });
    });

    $('#formRegion').on('submit', function (e) {
        e.preventDefault();
        const form = this;
        if (!form.checkValidity()) {
            e.stopPropagation();
            $(form).addClass('was-validated');
            return;
        }

        const formData = $(form).serialize() + `&accion=guardar_region&empresa_idx=${empresa_idx}&pagina_idx=${pagina_idx}`;
        $('#btnGuardarRegion').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: 'grupos_regiones_ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                $('#btnGuardarRegion').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Región');
                if (res.success) {
                    $('#modalRegion').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Éxito', text: res.message, timer: 1500, showConfirmButton: false });
                    tablaRegiones.ajax.reload(null, false);
                    cargarCombos();
                } else {
                    Swal.fire('Error al guardar', res.message, 'error');
                }
            },
            error: function () {
                $('#btnGuardarRegion').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Región');
                Swal.fire('Error de red', 'No se pudo comunicar con el servidor.', 'error');
            }
        });
    });

    // =========================================================================
    // 6. ASIGNAR, EDITAR Y CERRAR VIGENCIA DE EMPRESA EN REGIÓN (C03 / C04)
    // =========================================================================
    // Enlace directo desde la columna "Empresas" de la pestaña Regiones
    $(document).on('click', '.btn-ver-empresas-region', function () {
        const regId = $(this).data('region-id');
        $('#filtroRegionEmpresas').val(regId);
        const tabEl = document.querySelector('#tab-empresas-link');
        const tab = bootstrap.Tab.getOrCreateInstance(tabEl);
        tab.show();
        tablaEmpresasRegiones.ajax.reload();
    });

    $('#btnAsignarEmpresa').on('click', function () {
        $('#formAsignarEmpresa')[0].reset();
        $('#empresa_region_id').val(0);
        $('#modal_asignar_vigente_desde').val(new Date().toISOString().slice(0, 10));
        $('#modal_asignar_vigente_hasta').val('');
        $('#tituloModalAsignarEmpresa').text('Asignar Empresa a Región');
        $('#textoBotonGuardarAsignacion').text('Asignar Empresa');
        $('#formAsignarEmpresa').removeClass('was-validated');

        const regSel = $('#filtroRegionEmpresas').val();
        if (regSel) $('#modal_asignar_region_id').val(regSel);

        $('#modalAsignarEmpresa').modal('show');
    });

    $(document).on('click', '.btn-asignar-empresa', function () {
        const regId = $(this).data('id');
        $('#formAsignarEmpresa')[0].reset();
        $('#empresa_region_id').val(0);
        $('#modal_asignar_vigente_desde').val(new Date().toISOString().slice(0, 10));
        $('#modal_asignar_vigente_hasta').val('');
        $('#modal_asignar_region_id').val(regId);
        $('#tituloModalAsignarEmpresa').text('Asignar Empresa a Región');
        $('#textoBotonGuardarAsignacion').text('Asignar Empresa');
        $('#formAsignarEmpresa').removeClass('was-validated');
        $('#modalAsignarEmpresa').modal('show');
    });

    $(document).on('click', '.btn-editar-empresa-region', function () {
        const id = $(this).data('id');
        $.ajax({
            url: 'grupos_regiones_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_empresa_region', empresa_region_id: id, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const d = res.data;
                    $('#formAsignarEmpresa')[0].reset();
                    $('#empresa_region_id').val(d.empresa_region_id);
                    $('#modal_asignar_region_id').val(d.region_grupo_id);
                    $('#modal_asignar_empresa_id').val(d.empresa_id);
                    $('#modal_asignar_vigente_desde').val(d.vigente_desde);
                    $('#modal_asignar_vigente_hasta').val(d.vigente_hasta || '');
                    $('#tituloModalAsignarEmpresa').text('Editar Empresa en Región #' + d.empresa_region_id);
                    $('#textoBotonGuardarAsignacion').text('Guardar Cambios');
                    $('#formAsignarEmpresa').removeClass('was-validated');
                    $('#modalAsignarEmpresa').modal('show');
                } else {
                    Swal.fire('Error', res.message || 'No se pudo cargar la asignación.', 'error');
                }
            },
            error: function () {
                Swal.fire('Error de red', 'No se pudo comunicar con el servidor.', 'error');
            }
        });
    });

    $('#formAsignarEmpresa').on('submit', function (e) {
        e.preventDefault();
        const form = this;
        if (!form.checkValidity()) {
            e.stopPropagation();
            $(form).addClass('was-validated');
            return;
        }

        const isEdit = parseInt($('#empresa_region_id').val() || '0', 10) > 0;
        const btnTexto = isEdit ? 'Guardar Cambios' : 'Asignar Empresa';
        const formData = $(form).serialize() + `&accion=asignar_empresa&empresa_idx=${empresa_idx}&pagina_idx=${pagina_idx}`;
        $('#btnGuardarAsignacion').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: 'grupos_regiones_ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                $('#btnGuardarAsignacion').prop('disabled', false).html(`<i class="fas fa-save me-1"></i><span id="textoBotonGuardarAsignacion">${btnTexto}</span>`);
                if (res.success) {
                    $('#modalAsignarEmpresa').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Éxito', text: res.message, timer: 1500, showConfirmButton: false });
                    tablaEmpresasRegiones.ajax.reload(null, false);
                    tablaRegiones.ajax.reload(null, false);
                } else {
                    Swal.fire('Error al guardar', res.message, 'error');
                }
            },
            error: function () {
                $('#btnGuardarAsignacion').prop('disabled', false).html(`<i class="fas fa-save me-1"></i><span id="textoBotonGuardarAsignacion">${btnTexto}</span>`);
                Swal.fire('Error de red', 'No se pudo comunicar con el servidor.', 'error');
            }
        });
    });

    $(document).on('click', '.btn-cerrar-vigencia', function () {
        const id = $(this).data('id');
        const empresaNombre = $(this).data('empresa');

        Swal.fire({
            title: `Cerrar vigencia de ${empresaNombre}`,
            text: 'Indique la fecha de cierre de la empresa en esta región:',
            input: 'date',
            inputValue: new Date().toISOString().slice(0, 10),
            showCancelButton: true,
            confirmButtonText: 'Cerrar Vigencia',
            cancelButtonText: 'Cancelar',
            inputValidator: (value) => {
                if (!value) return 'Debe ingresar una fecha válida.';
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'grupos_regiones_ajax.php',
                    type: 'POST',
                    data: { accion: 'cerrar_vigencia_empresa', empresa_region_id: id, fecha_cierre: result.value, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            Swal.fire('Vigencia Cerrada', res.message, 'success');
                            tablaEmpresasRegiones.ajax.reload(null, false);
                            tablaRegiones.ajax.reload(null, false);
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                });
            }
        });
    });

    $(document).on('click', '.btn-eliminar-empresa-region', function () {
        const id = $(this).data('id');
        const empresaNombre = $(this).data('empresa');

        Swal.fire({
            title: '¿Eliminar asignación?',
            html: `Se desvinculará a <strong>${empresaNombre}</strong> de esta región.<br><small class="text-muted">Esta acción no se puede deshacer.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'grupos_regiones_ajax.php',
                    type: 'POST',
                    data: { accion: 'eliminar_empresa_region', empresa_region_id: id, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Eliminada', text: res.message, timer: 1500, showConfirmButton: false });
                            tablaEmpresasRegiones.ajax.reload(null, false);
                            tablaRegiones.ajax.reload(null, false);
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error de red', 'No se pudo comunicar con el servidor.', 'error');
                    }
                });
            }
        });
    });

    // INICIALIZACIÓN
    cargarCombos();
    inicializarTablaGrupos();
    inicializarTablaRegiones();
    inicializarTablaEmpresasRegiones();
});
