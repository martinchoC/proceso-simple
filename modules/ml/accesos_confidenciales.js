/**
 * Lógica JavaScript para Accesos Confidenciales y Auditoría (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/accesos_confidenciales.js
 */

$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresa_idx = parseInt(urlParams.get('empresa_id') || '2', 10);
    const pagina_idx = 316;

    let tablaAcc, tablaAud;
    let catalogoNormasConf = [];
    let catalogoProcConf = [];

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
    // 1. CARGA DE COMBOS
    // =========================================================================
    function cargarCombos() {
        $.ajax({
            url: 'accesos_confidenciales_ajax.php',
            type: 'GET',
            data: { accion: 'combos', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const d = res.data;
                    catalogoNormasConf = d.normas_confidenciales || [];
                    catalogoProcConf = d.procedimientos_confidenciales || [];

                    // Perfiles
                    let optPerfiles = '<option value="">-- Seleccionar Perfil --</option>';
                    if (d.perfiles) {
                        d.perfiles.forEach(function (p) {
                            optPerfiles += `<option value="${p.empresa_perfil_id}">${p.empresa_perfil_nombre}</option>`;
                        });
                    }
                    $('#acc_empresa_perfil_id').html(optPerfiles);

                    // Ámbitos
                    let optAmbitos = '<option value="">-- Nivel de Alcance --</option>';
                    if (d.ambitos_tipos) {
                        d.ambitos_tipos.forEach(function (at) {
                            optAmbitos += `<option value="${at.ambito_tipo_id}" data-codigo="${at.codigo}">${at.nombre}</option>`;
                        });
                    }
                    $('#acc_ambito_tipo_id').html(optAmbitos);

                    // Destinos
                    let optG = '<option value="">-- Seleccionar Grupo --</option>';
                    d.grupos.forEach(function (g) { optG += `<option value="${g.grupo_id}">${g.nombre}</option>`; });
                    $('#acc_grupo_id').html(optG);

                    let optR = '<option value="">-- Seleccionar Región --</option>';
                    d.regiones.forEach(function (r) { optR += `<option value="${r.region_grupo_id}">[${r.grupo_nombre}] ${r.nombre}</option>`; });
                    $('#acc_region_grupo_id').html(optR);

                    let optE = '<option value="">-- Seleccionar Empresa --</option>';
                    d.empresas.forEach(function (e) { optE += `<option value="${e.empresa_id}">${e.empresa}</option>`; });
                    $('#acc_empresa_id').html(optE);

                    let optT = '<option value="">-- Seleccionar Territorio --</option>';
                    d.territorios.forEach(function (t) { optT += `<option value="${t.territorio_id}">[${t.tipo_nombre}] ${t.nombre}</option>`; });
                    $('#acc_territorio_id').html(optT);

                    if (d.permite_autorizar) {
                        $('#btn-container-autorizar').html(`
                            <button type="button" class="btn btn-sm btn-danger" id="btnNuevoAcceso">
                                <i class="fas fa-plus me-1"></i>Habilitar Perfil
                            </button>
                        `);
                    }
                }
            }
        });
    }

    // Toggle de objeto reservado según el tipo seleccionado
    $('#acc_tipo_objeto').on('change', function () {
        const val = $(this).val();
        $('#acc_objeto_tabla').val(val);

        let opts = '<option value="">-- Seleccionar Documento --</option>';
        if (val === 'ml__normas') {
            catalogoNormasConf.forEach(function (n) {
                opts += `<option value="${n.norma_id}">[Norma] ${n.codigo} - ${n.titulo}</option>`;
            });
        } else if (val === 'ml__procedimientos') {
            catalogoProcConf.forEach(function (p) {
                opts += `<option value="${p.procedimiento_id}">[Procedimiento] ${p.codigo} - ${p.nombre}</option>`;
            });
        }
        $('#acc_objeto_id').html(opts);
    });

    // Toggle de destino de ámbito
    $('#acc_ambito_tipo_id').on('change', function () {
        const codigo = $(this).find('option:selected').data('codigo');
        $('.bloque-destino-acc').hide();
        $('#acc_grupo_id, #acc_region_grupo_id, #acc_empresa_id, #acc_territorio_id').val('');

        if (codigo === 'GRUPO') $('#destAccGrupo').show();
        else if (codigo === 'REGION_GRUPO') $('#destAccRegion').show();
        else if (codigo === 'EMPRESA') $('#destAccEmpresa').show();
        else if (codigo === 'TERRITORIO') $('#destAccTerritorio').show();
    });

    // =========================================================================
    // 2. INICIALIZAR TABLAS DATATABLES
    // =========================================================================

    // Tabla Autorizaciones
    function initTablaAutorizaciones() {
        if ($.fn.DataTable.isDataTable('#tablaAutorizaciones')) {
            $('#tablaAutorizaciones').DataTable().destroy();
            $('#tablaAutorizaciones tbody').empty();
        }

        tablaAcc = $('#tablaAutorizaciones').DataTable({
            dom: dtDom,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: dtLang,
            responsive: true,
            ajax: {
                url: 'accesos_confidenciales_ajax.php',
                type: 'GET',
                data: { accion: 'listar_accesos', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
                dataSrc: 'data'
            },
            columns: [
                { data: 'acceso_confidencial_id', className: 'text-center fw-bold text-muted' },
                {
                    data: 'objeto_descripcion',
                    render: function (val) {
                        return `<span class="fw-semibold text-danger"><i class="fas fa-lock me-1"></i>${val}</span>`;
                    }
                },
                {
                    data: 'empresa_perfil_nombre',
                    render: function (val) {
                        return `<span class="badge bg-secondary">${val}</span>`;
                    }
                },
                {
                    data: 'alcance_nombre',
                    render: function (val, type, row) {
                        return `<span class="badge bg-info text-dark">${row.ambito_tipo_nombre}</span> <small class="text-muted">${val}</small>`;
                    }
                },
                { data: 'vigente_desde', className: 'text-center' },
                {
                    data: 'vigente_hasta',
                    className: 'text-center',
                    render: function (val) {
                        return val || '<span class="text-muted fs-8">Permanente</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        return `<button type="button" class="btn btn-outline-danger btn-sm btnRevocarAcceso" data-id="${row.acceso_confidencial_id}" title="Revocar Autorización">
                                    <i class="fas fa-trash-alt"></i>
                                </button>`;
                    }
                }
            ],
            order: [[0, 'desc']]
        });
    }

    // Tabla Auditoría
    function initTablaAuditoria() {
        if ($.fn.DataTable.isDataTable('#tablaAuditoria')) {
            $('#tablaAuditoria').DataTable().destroy();
            $('#tablaAuditoria tbody').empty();
        }

        tablaAud = $('#tablaAuditoria').DataTable({
            dom: dtDom,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: dtLang,
            responsive: true,
            ajax: {
                url: 'accesos_confidenciales_ajax.php',
                type: 'GET',
                data: { accion: 'listar_auditoria', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
                dataSrc: 'data'
            },
            columns: [
                { data: 'auditoria_acceso_id', className: 'text-center fw-bold text-muted' },
                {
                    data: 'accedido_en',
                    render: function (val) {
                        return `<span class="badge bg-light text-dark border font-monospace"><i class="fas fa-clock me-1"></i>${val}</span>`;
                    }
                },
                {
                    data: 'usuario_login',
                    render: function (val) {
                        return `<span class="fw-semibold text-primary"><i class="fas fa-user me-1"></i>${val}</span>`;
                    }
                },
                {
                    data: 'objeto_nombre',
                    render: function (val) {
                        return `<span class="fw-semibold text-danger">${val}</span>`;
                    }
                },
                {
                    data: 'motivo',
                    render: function (val) {
                        return val ? `<small class="text-muted">${val}</small>` : '<span class="text-muted fst-italic fs-8">Sin motivo declarado</span>';
                    }
                }
            ],
            order: [[1, 'desc']]
        });
    }

    // Cambio de pestañas
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        const target = $(e.target).attr('data-bs-target');
        if (target === '#tab-autorizaciones' && tablaAcc) tablaAcc.columns.adjust().responsive.recalc();
        if (target === '#tab-auditoria' && !tablaAud) initTablaAuditoria();
        else if (target === '#tab-auditoria' && tablaAud) tablaAud.columns.adjust().responsive.recalc();
    });

    $('.btnRecargar').on('click', function () {
        const t = $(this).data('tabla');
        if (t === 'autorizaciones' && tablaAcc) tablaAcc.ajax.reload();
        if (t === 'auditoria' && tablaAud) tablaAud.ajax.reload();
    });

    // =========================================================================
    // 3. ALTA DE AUTORIZACIÓN
    // =========================================================================
    $(document).on('click', '#btnNuevoAcceso', function () {
        $('#formAcceso')[0].reset();
        $('.bloque-destino-acc').hide();
        $('#acc_vigente_desde').val(Fmt.hoyISO());
        new bootstrap.Modal(document.getElementById('modalAcceso')).show();
    });

    $('#formAcceso').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) return;

        const data = $(this).serialize() + '&accion=guardar_acceso&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;
        $.post('accesos_confidenciales_ajax.php', data, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Autorizado', text: res.message, timer: 1200, showConfirmButton: false });
                bootstrap.Modal.getInstance(document.getElementById('modalAcceso')).hide();
                tablaAcc.ajax.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    // Revocar autorización
    $(document).on('click', '.btnRevocarAcceso', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: '¿Revocar autorización?',
            text: 'El perfil dejará de tener acceso reservado a este documento.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, revocar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('accesos_confidenciales_ajax.php', {
                    accion: 'revocar_acceso',
                    acceso_confidencial_id: id,
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                }, function (res) {
                    if (res.success) {
                        Swal.fire({ icon: 'success', title: 'Revocado', text: res.message, timer: 1200, showConfirmButton: false });
                        tablaAcc.ajax.reload();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                    }
                }, 'json');
            }
        });
    });

    // =========================================================================
    // 4. INICIALIZACIÓN
    // =========================================================================
    cargarCombos();
    initTablaAutorizaciones();
});
