/**
 * Lógica JavaScript para Ámbitos de Usuarios (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/usuarios_ambitos.js
 */

$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresa_idx = parseInt(urlParams.get('empresa_id') || '2', 10);
    const pagina_idx = 309;

    let tablaAmbitos;
    let catalogoAmbitosTipos = [];

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
            url: 'usuarios_ambitos_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_combos', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const d = res.data;
                    catalogoAmbitosTipos = d.ambitos_tipos || [];

                    // Combo Usuarios
                    let optUsuariosFiltro = '<option value="">-- Todos los usuarios --</option>';
                    let optUsuariosModal = '<option value="">-- Seleccionar Usuario --</option>';
                    d.usuarios.forEach(function (u) {
                        optUsuariosFiltro += `<option value="${u.usuario_id}">${u.usuario}</option>`;
                        optUsuariosModal += `<option value="${u.usuario_id}">${u.usuario}</option>`;
                    });
                    $('#filtroUsuario').html(optUsuariosFiltro);
                    $('#modal_usuario_id').html(optUsuariosModal);

                    // Combo Tipos de Ámbitos
                    let optAmbitosFiltro = '<option value="">-- Todos los tipos --</option>';
                    let optAmbitosModal = '<option value="">-- Seleccionar Tipo de Ámbito --</option>';
                    catalogoAmbitosTipos.forEach(function (at) {
                        optAmbitosFiltro += `<option value="${at.ambito_tipo_id}">${at.nombre}</option>`;
                        optAmbitosModal += `<option value="${at.ambito_tipo_id}" data-codigo="${at.codigo}">${at.nombre}</option>`;
                    });
                    $('#filtroTipoAmbito').html(optAmbitosFiltro);
                    $('#modal_ambito_tipo_id').html(optAmbitosModal);

                    // Combo Perfiles
                    let optPerfiles = '<option value="">-- Seleccionar Perfil --</option>';
                    d.perfiles.forEach(function (p) {
                        optPerfiles += `<option value="${p.empresa_perfil_id}">${p.empresa_perfil_nombre}</option>`;
                    });
                    $('#modal_empresa_perfil_id').html(optPerfiles);

                    // Combo Grupos
                    let optGrupos = '<option value="">-- Seleccionar Grupo --</option>';
                    d.grupos.forEach(function (g) { optGrupos += `<option value="${g.grupo_id}">${g.nombre}</option>`; });
                    $('#modal_grupo_id').html(optGrupos);

                    // Combo Regiones
                    let optRegiones = '<option value="">-- Seleccionar Región --</option>';
                    d.regiones.forEach(function (r) { optRegiones += `<option value="${r.region_grupo_id}">[${r.grupo_nombre}] ${r.nombre}</option>`; });
                    $('#modal_region_grupo_id').html(optRegiones);

                    // Combo Empresas
                    let optEmpresas = '<option value="">-- Seleccionar Empresa --</option>';
                    d.empresas.forEach(function (e) { optEmpresas += `<option value="${e.empresa_id}">${e.empresa}</option>`; });
                    $('#modal_empresa_id').html(optEmpresas);

                    // Combo Territorios
                    let optTerritorios = '<option value="">-- Seleccionar Territorio --</option>';
                    d.territorios.forEach(function (t) { optTerritorios += `<option value="${t.territorio_id}">[${t.tipo_nombre}] ${t.nombre} (${t.codigo})</option>`; });
                    $('#modal_territorio_id').html(optTerritorios);

                    if (d.permite_agregar) {
                        $('#contenedor-boton-agregar').html(`
                            <button type="button" class="btn btn-sm btn-primary" id="btnNuevoAmbito">
                                <i class="fas fa-plus me-1"></i>Asignar Ámbito
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
        if ($.fn.DataTable.isDataTable('#tablaAmbitos')) {
            $('#tablaAmbitos').DataTable().destroy();
            $('#tablaAmbitos tbody').empty();
        }

        tablaAmbitos = $('#tablaAmbitos').DataTable({
            ajax: {
                url: 'usuarios_ambitos_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                    d.filtro_usuario = $('#filtroUsuario').val();
                    d.filtro_tipo = $('#filtroTipoAmbito').val();
                    d.filtro_estado = $('#filtroEstado').val();
                },
                dataSrc: 'data'
            },
            columns: [
                { data: 'usuario_ambito_id', className: 'text-center fw-bold' },
                {
                    data: 'usuario_login',
                    render: function (data) {
                        return `<span class="fw-semibold text-primary"><i class="fas fa-user me-1 text-muted"></i>${data}</span>`;
                    }
                },
                {
                    data: 'empresa_perfil_nombre',
                    render: function (data) {
                        return `<span class="badge bg-light text-dark border">${data}</span>`;
                    }
                },
                {
                    data: 'ambito_tipo_nombre',
                    render: function (data) {
                        return `<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">${data}</span>`;
                    }
                },
                {
                    data: 'alcance_nombre',
                    render: function (data) {
                        return `<span class="fw-medium text-dark">${data || '-'}</span>`;
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
                        return data ? `<small class="text-danger">${data}</small>` : '<span class="badge bg-light text-muted border">Abierto</span>';
                    }
                },
                {
                    data: 'es_activo',
                    className: 'text-center',
                    render: function (data) {
                        return parseInt(data, 10) === 1
                            ? '<span class="badge bg-success">Activo</span>'
                            : '<span class="badge bg-secondary">Cerrado</span>';
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
    $('#filtroUsuario, #filtroTipoAmbito, #filtroEstado').on('change', function () {
        tablaAmbitos.ajax.reload();
    });

    $('#btnRecargar').on('click', function () {
        tablaAmbitos.ajax.reload(null, false);
    });

    // Control dinámico de bloques de destino según el tipo de ámbito seleccionado
    $('#modal_ambito_tipo_id').on('change', function () {
        const codigo = $(this).find('option:selected').data('codigo');
        $('.bloque-destino').hide();
        $('.bloque-destino select').prop('required', false).val('');

        if (codigo === 'GRUPO') {
            $('#bloqueGrupo').show();
            $('#modal_grupo_id').prop('required', true);
        } else if (codigo === 'REGION_GRUPO') {
            $('#bloqueRegion').show();
            $('#modal_region_grupo_id').prop('required', true);
        } else if (codigo === 'EMPRESA') {
            $('#bloqueEmpresa').show();
            $('#modal_empresa_id').prop('required', true);
        } else if (codigo === 'TERRITORIO') {
            $('#bloqueTerritorio').show();
            $('#modal_territorio_id').prop('required', true);
        }
    });

    $(document).on('click', '#btnNuevoAmbito', function () {
        $('#formAmbito')[0].reset();
        $('.bloque-destino').hide();
        $('.bloque-destino select').prop('required', false);
        $('#modal_vigente_desde').val(new Date().toISOString().slice(0, 10));
        $('#formAmbito').removeClass('was-validated');
        $('#modalAmbito').modal('show');
    });

    $('#formAmbito').on('submit', function (e) {
        e.preventDefault();
        const form = this;
        if (!form.checkValidity()) {
            e.stopPropagation();
            $(form).addClass('was-validated');
            return;
        }

        const formData = $(form).serialize() + `&accion=guardar&empresa_idx=${empresa_idx}&pagina_idx=${pagina_idx}`;
        $('#btnGuardarAmbito').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Asignando...');

        $.ajax({
            url: 'usuarios_ambitos_ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                $('#btnGuardarAmbito').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Asignar Ámbito');
                if (res.success) {
                    $('#modalAmbito').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Ámbito Asignado', text: res.message, timer: 1500, showConfirmButton: false });
                    tablaAmbitos.ajax.reload(null, false);
                } else {
                    Swal.fire('Error al asignar', res.message, 'error');
                }
            },
            error: function () {
                $('#btnGuardarAmbito').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Asignar Ámbito');
                Swal.fire('Error de red', 'No se pudo comunicar con el servidor.', 'error');
            }
        });
    });

    // Cerrar vigencia de ámbito
    $(document).on('click', '.btn-cerrar-ambito', function () {
        const id = $(this).data('id');
        const usuario = $(this).data('usuario');

        Swal.fire({
            title: `Cerrar ámbito de ${usuario}`,
            text: 'Indique la fecha de finalización del alcance:',
            input: 'date',
            inputValue: new Date().toISOString().slice(0, 10),
            showCancelButton: true,
            confirmButtonText: 'Cerrar Ámbito',
            cancelButtonText: 'Cancelar',
            inputValidator: (value) => {
                if (!value) return 'Debe ingresar una fecha.';
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'usuarios_ambitos_ajax.php',
                    type: 'POST',
                    data: { accion: 'cerrar', usuario_ambito_id: id, fecha_cierre: result.value, empresa_idx: empresa_idx, pagina_idx: pagina_idx },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            Swal.fire('Ámbito Cerrado', res.message, 'success');
                            tablaAmbitos.ajax.reload(null, false);
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // INICIALIZACIÓN
    cargarCombos();
    inicializarDataTable();
});
