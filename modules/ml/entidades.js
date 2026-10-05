/**
 * Lógica JavaScript para Entidades Sujetas a Control (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/entidades.js
 */

$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresa_idx = parseInt(urlParams.get('empresa_id') || '2', 10);
    const pagina_idx = 304;

    let tablaEntidades;

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
    // 1. CARGA DE COMBOS Y CATALOGOS
    // =========================================================================
    function cargarCombos() {
        $.ajax({
            url: 'entidades_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_combos', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const tipos = res.data.tipos || [];
                    const empresas = res.data.empresas || [];
                    const territorios = res.data.territorios || [];
                    const padres = res.data.padres || [];

                    let optTiposFiltro = '<option value="">-- Todos los tipos --</option>';
                    let optTiposModal = '<option value="">-- Seleccionar Tipo --</option>';
                    tipos.forEach(function (t) {
                        const sufijo = t.es_movil == 1 ? ' [Móvil]' : (t.es_fisica == 1 ? ' [Física]' : ' [Lógica]');
                        optTiposFiltro += `<option value="${t.entidad_tipo_id}">${t.nombre}</option>`;
                        optTiposModal += `<option value="${t.entidad_tipo_id}">${t.nombre}${sufijo}</option>`;
                    });
                    $('#filtroTipo').html(optTiposFiltro);
                    $('#modal_entidad_tipo_id').html(optTiposModal);

                    let optEmpresasFiltro = '<option value="">-- Todas las empresas --</option>';
                    let optEmpresasModal = '<option value="">-- Seleccionar Empresa --</option>';
                    empresas.forEach(function (e) {
                        optEmpresasFiltro += `<option value="${e.empresa_id}">${e.empresa}</option>`;
                        optEmpresasModal += `<option value="${e.empresa_id}">${e.empresa} (CUIT: ${e.cuit})</option>`;
                    });
                    $('#filtroEmpresa').html(optEmpresasFiltro);
                    $('#modal_empresa_titular_id, #transf_nueva_empresa_id').html(optEmpresasModal);

                    let optTerritorios = '<option value="">-- Seleccionar Territorio --</option>';
                    territorios.forEach(function (t) {
                        optTerritorios += `<option value="${t.territorio_id}">[${t.tipo_nombre}] ${t.nombre} (${t.codigo})</option>`;
                    });
                    $('#modal_territorio_id, #ubi_nuevo_territorio_id').html(optTerritorios);

                    let optPadres = '<option value="">-- Ninguna (Entidad Raíz) --</option>';
                    padres.forEach(function (p) {
                        optPadres += `<option value="${p.entidad_id}">[${p.tipo_nombre}] ${p.nombre} (${p.codigo})</option>`;
                    });
                    $('#modal_entidad_padre_id').html(optPadres);

                    if (res.data.permite_agregar) {
                        $('#contenedor-boton-agregar').html(`
                            <button type="button" class="btn btn-sm btn-primary" id="btnNuevaEntidad">
                                <i class="fas fa-plus me-1"></i>Nueva Entidad
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
        if ($.fn.DataTable.isDataTable('#tablaEntidades')) {
            $('#tablaEntidades').DataTable().destroy();
            $('#tablaEntidades tbody').empty();
        }

        tablaEntidades = $('#tablaEntidades').DataTable({
            ajax: {
                url: 'entidades_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                    d.filtro_empresa = $('#filtroEmpresa').val();
                    d.filtro_tipo = $('#filtroTipo').val();
                    d.filtro_estado = $('#filtroEstado').val();
                    d.filtro_movil = $('#filtroMovil').val();
                },
                dataSrc: 'data'
            },
            columns: [
                { data: 'entidad_id', className: 'text-center fw-bold' },
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
                        let icono = row.es_movil == 1 ? '<i class="fas fa-truck-moving text-warning me-1"></i>' : '<i class="fas fa-warehouse text-info me-1"></i>';
                        return `<small>${icono}${data}</small>`;
                    }
                },
                {
                    data: 'empresa_titular_nombre',
                    render: function (data) {
                        return data ? `<span class="badge bg-secondary bg-opacity-10 text-dark border"><i class="fas fa-building me-1"></i>${data}</span>` : '<span class="text-muted fst-italic">Sin titular</span>';
                    }
                },
                {
                    data: 'ubicacion_territorio_nombre',
                    render: function (data) {
                        return data ? `<small><i class="fas fa-map-marker-alt text-danger me-1"></i>${data}</small>` : '<span class="text-muted fst-italic">No asignada</span>';
                    }
                },
                {
                    data: 'padre_nombre',
                    render: function (data) {
                        return data ? `<small class="text-muted"><i class="fas fa-level-up-alt fa-rotate-90 me-1"></i>${data}</small>` : '<span class="badge bg-light text-muted border">Raíz</span>';
                    }
                },
                {
                    data: 'tabla_estado_registro_id',
                    className: 'text-center',
                    render: function (data) {
                        return parseInt(data, 10) === 1
                            ? '<span class="badge bg-success">Activa</span>'
                            : '<span class="badge bg-secondary">Inactiva</span>';
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
    // 3. EVENTOS FILTROS & MODALES
    // =========================================================================
    $('#filtroEmpresa, #filtroTipo, #filtroMovil, #filtroEstado').on('change', function () {
        tablaEntidades.ajax.reload();
    });

    $('#btnRecargar').on('click', function () {
        tablaEntidades.ajax.reload(null, false);
    });

    $(document).on('click', '#btnNuevaEntidad', function () {
        $('#formEntidad')[0].reset();
        $('#entidad_id').val(0);
        $('#modal_vigente_desde').val(new Date().toISOString().slice(0, 10));
        $('#modal_empresa_titular_id').val(empresa_idx);
        $('#bloqueTitularidadAlta').show();
        $('#modal_empresa_titular_id').prop('required', true);

        $('#tituloModalEntidad').text('Nueva Entidad Sujeta a Control');
        $('#formEntidad').removeClass('was-validated');
        $('#modalEntidad').modal('show');
    });

    $(document).on('click', '.btn-editar', function () {
        const id = $(this).data('id');
        $.ajax({
            url: 'entidades_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_entidad', entidad_id: id, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const e = res.data;
                    $('#entidad_id').val(e.entidad_id);
                    $('#modal_codigo').val(e.codigo);
                    $('#modal_nombre').val(e.nombre);
                    $('#modal_entidad_tipo_id').val(e.entidad_tipo_id);
                    $('#modal_entidad_padre_id').val(e.entidad_padre_id || '');
                    $('#modal_vigente_desde').val(e.vigente_desde);
                    $('#modal_descripcion').val(e.descripcion || '');

                    // Al editar no se cambia de titular directamente acá (se usa el modal de transferir C16)
                    $('#bloqueTitularidadAlta').hide();
                    $('#modal_empresa_titular_id').prop('required', false);

                    $('#tituloModalEntidad').text('Editar Entidad #' + e.entidad_id);
                    $('#formEntidad').removeClass('was-validated');
                    $('#modalEntidad').modal('show');
                } else {
                    Swal.fire('Error', res.message || 'No se pudo cargar la entidad.', 'error');
                }
            }
        });
    });

    $('#formEntidad').on('submit', function (e) {
        e.preventDefault();
        const form = this;
        if (!form.checkValidity()) {
            e.stopPropagation();
            $(form).addClass('was-validated');
            return;
        }

        const formData = $(form).serialize() + `&accion=guardar&empresa_idx=${empresa_idx}&pagina_idx=${pagina_idx}`;
        $('#btnGuardarEntidad').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: 'entidades_ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                $('#btnGuardarEntidad').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Entidad');
                if (res.success) {
                    $('#modalEntidad').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Éxito', text: res.message, timer: 1500, showConfirmButton: false });
                    tablaEntidades.ajax.reload(null, false);
                    cargarCombos();
                } else {
                    Swal.fire('Error al guardar', res.message, 'error');
                }
            },
            error: function () {
                $('#btnGuardarEntidad').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Entidad');
                Swal.fire('Error de red', 'No se pudo comunicar con el servidor.', 'error');
            }
        });
    });

    // Transferir Titularidad (C16)
    $(document).on('click', '.btn-transferir', function () {
        const id = $(this).data('id');
        const nombre = $(this).data('nombre');
        const actualEmpresa = $(this).data('empresa');

        $('#transf_entidad_id').val(id);
        $('#transf_nombre_entidad').text(nombre);
        $('#transf_fecha').val(new Date().toISOString().slice(0, 10));
        $('#transf_nueva_empresa_id').val('');

        $('#modalTransferir').modal('show');
    });

    $('#formTransferir').on('submit', function (e) {
        e.preventDefault();
        const form = this;
        if (!form.checkValidity()) {
            e.stopPropagation();
            $(form).addClass('was-validated');
            return;
        }

        const formData = $(form).serialize() + `&accion=transferir&empresa_idx=${empresa_idx}&pagina_idx=${pagina_idx}`;
        $('#btnEjecutarTransferencia').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Transfiriendo...');

        $.ajax({
            url: 'entidades_ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                $('#btnEjecutarTransferencia').prop('disabled', false).html('<i class="fas fa-check me-1"></i>Transferir Titularidad');
                if (res.success) {
                    $('#modalTransferir').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Transferencia Exitosa', text: res.message });
                    tablaEntidades.ajax.reload(null, false);
                } else {
                    Swal.fire('Error al transferir', res.message, 'error');
                }
            },
            error: function () {
                $('#btnEjecutarTransferencia').prop('disabled', false).html('<i class="fas fa-check me-1"></i>Transferir Titularidad');
                Swal.fire('Error de red', 'No se pudo comunicar con el servidor.', 'error');
            }
        });
    });

    // Actualizar Ubicación Base (C05 / C06)
    $(document).on('click', '.btn-ubicacion', function () {
        const id = $(this).data('id');
        const nombre = $(this).data('nombre');
        const terr = $(this).data('territorio');

        $('#ubi_entidad_id').val(id);
        $('#ubi_nombre_entidad').text(nombre);
        $('#ubi_fecha').val(new Date().toISOString().slice(0, 10));
        $('#ubi_nuevo_territorio_id').val(terr || '');

        $('#modalUbicacion').modal('show');
    });

    $('#formUbicacion').on('submit', function (e) {
        e.preventDefault();
        const form = this;
        if (!form.checkValidity()) {
            e.stopPropagation();
            $(form).addClass('was-validated');
            return;
        }

        const formData = $(form).serialize() + `&accion=cambiar_ubicacion&empresa_idx=${empresa_idx}&pagina_idx=${pagina_idx}`;
        $('#btnEjecutarUbicacion').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Actualizando...');

        $.ajax({
            url: 'entidades_ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                $('#btnEjecutarUbicacion').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Actualizar Ubicación');
                if (res.success) {
                    $('#modalUbicacion').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Ubicación Actualizada', text: res.message });
                    tablaEntidades.ajax.reload(null, false);
                } else {
                    Swal.fire('Error al actualizar ubicación', res.message, 'error');
                }
            },
            error: function () {
                $('#btnEjecutarUbicacion').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Actualizar Ubicación');
                Swal.fire('Error de red', 'No se pudo comunicar con el servidor.', 'error');
            }
        });
    });

    // Inactivar / Activar Entidad
    $(document).on('click', '.btn-estado', function () {
        const id = $(this).data('id');
        const nuevoEstado = $(this).data('estado');
        const accionTexto = nuevoEstado === 1 ? 'activar' : 'inactivar';

        Swal.fire({
            title: `¿Confirma ${accionTexto} la entidad?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'entidades_ajax.php',
                    type: 'POST',
                    data: { accion: 'cambiar_estado', entidad_id: id, nuevo_estado: nuevoEstado, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            Swal.fire('Actualizado', res.message, 'success');
                            tablaEntidades.ajax.reload(null, false);
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // Ver Historial de Trazabilidad (C16 / C17 / C05)
    $(document).on('click', '.btn-historial', function () {
        const id = $(this).data('id');
        const nombre = $(this).data('nombre');
        $('#hist_nombre_entidad').text(nombre);

        $.ajax({
            url: 'entidades_ajax.php',
            type: 'GET',
            data: { accion: 'historial', entidad_id: id, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    let rowsEmpresas = '';
                    res.data.empresas.forEach(function (e) {
                        const rol = e.es_titular == 1 ? '<span class="badge bg-primary">Titular Responsable</span>' : '<span class="badge bg-secondary">Vinculada</span>';
                        const hasta = e.vigente_hasta ? `<span class="text-danger">${e.vigente_hasta}</span>` : '<span class="badge bg-success">Actual</span>';
                        rowsEmpresas += `
                            <tr>
                                <td class="fw-semibold text-start">${e.empresa_nombre}</td>
                                <td>${e.cuit}</td>
                                <td>${rol}</td>
                                <td>${e.vigente_desde}</td>
                                <td>${hasta}</td>
                            </tr>
                        `;
                    });
                    if (!rowsEmpresas) rowsEmpresas = '<tr><td colspan="5" class="text-muted">Sin registros de titularidad</td></tr>';
                    $('#tablaHistorialEmpresas tbody').html(rowsEmpresas);

                    let rowsUbicaciones = '';
                    res.data.ubicaciones.forEach(function (u) {
                        const hasta = u.vigente_hasta ? `<span class="text-danger">${u.vigente_hasta}</span>` : '<span class="badge bg-success">Actual</span>';
                        rowsUbicaciones += `
                            <tr>
                                <td class="fw-semibold text-start">${u.territorio_nombre} (${u.territorio_codigo})</td>
                                <td><span class="badge bg-info bg-opacity-10 text-info border">${u.tipo_nombre}</span></td>
                                <td>${u.vigente_desde}</td>
                                <td>${hasta}</td>
                            </tr>
                        `;
                    });
                    if (!rowsUbicaciones) rowsUbicaciones = '<tr><td colspan="4" class="text-muted">Sin registros de radicación</td></tr>';
                    $('#tablaHistorialUbicaciones tbody').html(rowsUbicaciones);

                    $('#modalHistorial').modal('show');
                }
            }
        });
    });

    // INICIALIZACIÓN
    cargarCombos();
    inicializarDataTable();
});
