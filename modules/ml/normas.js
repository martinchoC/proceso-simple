/**
 * Lógica JavaScript para Matriz de Normas y Versiones (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/normas.js
 */

$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresa_idx = parseInt(urlParams.get('empresa_id') || '2', 10);
    const pagina_idx = 313;

    let tablaNormas;
    let catalogoAmbitos = [];
    let todasLasNormas = [];

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
    // 1. CARGA DE COMBOS Y CATÁLOGOS
    // =========================================================================
    function cargarCombos() {
        $.ajax({
            url: 'normas_ajax.php',
            type: 'GET',
            data: { accion: 'combos', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const d = res.data;
                    catalogoAmbitos = d.ambitos_tipos || [];

                    // Tipos de Norma
                    let optTiposFiltro = '<option value="">-- Todos los tipos --</option>';
                    let optTiposModal = '<option value="">-- Seleccionar Tipo --</option>';
                    d.tipos.forEach(function (t) {
                        optTiposFiltro += `<option value="${t.norma_tipo_id}">${t.codigo} - ${t.nombre}</option>`;
                        optTiposModal += `<option value="${t.norma_tipo_id}">${t.codigo} - ${t.nombre}</option>`;
                    });
                    $('#filtroTipo').html(optTiposFiltro);
                    $('#norma_tipo_id').html(optTiposModal);

                    // Organismos
                    let optOrgFiltro = '<option value="">-- Todos los organismos --</option>';
                    let optOrgModal = '<option value="">-- Sin organismo asignado --</option>';
                    d.organismos.forEach(function (o) {
                        optOrgFiltro += `<option value="${o.organismo_id}">${o.nombre}</option>`;
                        optOrgModal += `<option value="${o.organismo_id}">${o.nombre}</option>`;
                    });
                    $('#filtroOrganismo').html(optOrgFiltro);
                    $('#norma_organismo_id').html(optOrgModal);

                    // Jerarquías
                    let optJerModal = '<option value="">-- Sin jerarquía fijada --</option>';
                    d.jerarquias.forEach(function (j) {
                        optJerModal += `<option value="${j.norma_jerarquia_id}">Nivel ${j.nivel}: ${j.nombre}</option>`;
                    });
                    $('#norma_jerarquia_id').html(optJerModal);

                    // Ámbitos Tipos
                    let optAmbFiltro = '<option value="">-- Todos los niveles --</option>';
                    let optAmbModal = '<option value="">-- Nivel de Alcance --</option>';
                    catalogoAmbitos.forEach(function (at) {
                        optAmbFiltro += `<option value="${at.ambito_tipo_id}">${at.nombre}</option>`;
                        optAmbModal += `<option value="${at.ambito_tipo_id}" data-codigo="${at.codigo}">${at.nombre}</option>`;
                    });
                    $('#filtroAmbito').html(optAmbFiltro);
                    $('#norma_ambito_tipo_id').html(optAmbModal);

                    // Destinos: Grupos
                    let optGrupos = '<option value="">-- Seleccionar Grupo --</option>';
                    d.grupos.forEach(function (g) { optGrupos += `<option value="${g.grupo_id}">${g.nombre}</option>`; });
                    $('#norma_grupo_id').html(optGrupos);

                    // Destinos: Regiones
                    let optReg = '<option value="">-- Seleccionar Región --</option>';
                    d.regiones.forEach(function (r) { optReg += `<option value="${r.region_grupo_id}">[${r.grupo_nombre}] ${r.nombre}</option>`; });
                    $('#norma_region_grupo_id').html(optReg);

                    // Destinos: Empresas
                    let optEmp = '<option value="">-- Seleccionar Empresa --</option>';
                    d.empresas.forEach(function (e) { optEmp += `<option value="${e.empresa_id}">${e.empresa}</option>`; });
                    $('#norma_empresa_id').html(optEmp);

                    // Destinos: Territorios
                    let optTerr = '<option value="">-- Seleccionar Territorio / País --</option>';
                    d.territorios.forEach(function (t) { optTerr += `<option value="${t.territorio_id}">[${t.tipo_nombre}] ${t.nombre} (${t.codigo})</option>`; });
                    $('#norma_territorio_id').html(optTerr);

                    // Relaciones Tipos
                    let optRelTipos = '<option value="">-- Seleccionar Tipo de Vínculo --</option>';
                    d.relaciones_tipos.forEach(function (rt) {
                        optRelTipos += `<option value="${rt.norma_relacion_tipo_id}">${rt.codigo} - ${rt.nombre}</option>`;
                    });
                    $('#rel_tipo_id').html(optRelTipos);

                    if (res.permisos && res.permisos.permite_crear) {
                        $('#btn-container-norma').html(`
                            <button type="button" class="btn btn-sm btn-primary" id="btnNuevaNorma">
                                <i class="fas fa-plus me-1"></i>Nueva Norma
                            </button>
                        `);
                    }
                }
            }
        });
    }

    // Toggle dinámico de destino según el tipo de ámbito seleccionado
    $('#norma_ambito_tipo_id').on('change', function () {
        const codigo = $(this).find('option:selected').data('codigo');
        $('.bloque-destino').hide();
        $('#norma_grupo_id, #norma_region_grupo_id, #norma_empresa_id, #norma_territorio_id').val('');

        if (codigo === 'GRUPO') $('#destGrupo').show();
        else if (codigo === 'REGION_GRUPO') $('#destRegion').show();
        else if (codigo === 'EMPRESA') $('#destEmpresa').show();
        else if (codigo === 'TERRITORIO') $('#destTerritorio').show();
    });

    // =========================================================================
    // 2. INICIALIZAR DATATABLE DE NORMAS
    // =========================================================================
    function inicializarDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaNormas')) {
            $('#tablaNormas').DataTable().destroy();
            $('#tablaNormas tbody').empty();
        }

        tablaNormas = $('#tablaNormas').DataTable({
            dom: dtDom,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: dtLang,
            responsive: true,
            ajax: {
                url: 'normas_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                    d.filtro_tipo = $('#filtroTipo').val();
                    d.filtro_organismo = $('#filtroOrganismo').val();
                    d.filtro_ambito = $('#filtroAmbito').val();
                    d.filtro_estado = $('#filtroEstado').val();
                    d.filtro_confidencial = $('#filtroConfidencial').val();
                },
                dataSrc: function (json) {
                    todasLasNormas = json.data || [];
                    return todasLasNormas;
                }
            },
            columns: [
                { data: 'norma_id', className: 'text-center fw-bold text-muted' },
                {
                    data: 'codigo',
                    render: function (val, type, row) {
                        let confIcon = parseInt(row.confidencial, 10) === 1 ? '<i class="fas fa-lock text-danger ms-1" title="Norma Confidencial"></i>' : '';
                        let reqIcon = parseInt(row.requiere_norma_local, 10) === 1 ? '<i class="fas fa-flag text-warning ms-1" title="Requiere ley local"></i>' : '';
                        return `<span class="fw-bold text-primary">${val}</span>${confIcon}${reqIcon}`;
                    }
                },
                {
                    data: 'titulo',
                    render: function (val, type, row) {
                        let orig = `<span class="badge bg-secondary me-1">${row.idioma_original || 'ES'}</span>`;
                        let desc = row.descripcion ? `<small class="text-muted d-block text-truncate" style="max-width: 280px;" title="${row.descripcion}">${row.descripcion}</small>` : '';
                        let trad = row.titulo_traducido ? `<small class="text-info d-block fst-italic">[${row.idioma_traducido}] ${row.titulo_traducido}</small>` : '';
                        return `<div>${orig}<span class="fw-semibold">${val}</span>${trad}${desc}</div>`;
                    }
                },
                {
                    data: 'norma_tipo_nombre',
                    render: function (val, type, row) {
                        let jer = row.jerarquia_nombre ? `<small class="text-muted d-block fs-8">Nivel ${row.jerarquia_nivel}: ${row.jerarquia_nombre}</small>` : '';
                        return `<span class="badge bg-light text-dark border">${val}</span>${jer}`;
                    }
                },
                {
                    data: 'organismo_nombre',
                    render: function (val) {
                        return val ? `<small><i class="fas fa-university me-1 text-muted"></i>${val}</small>` : '<span class="text-muted fs-8">No asignado</span>';
                    }
                },
                {
                    data: 'alcance_nombre',
                    render: function (val, type, row) {
                        return `<span class="badge bg-info text-dark">${row.ambito_tipo_nombre}</span><small class="d-block text-truncate" style="max-width: 150px;">${val}</small>`;
                    }
                },
                {
                    data: 'version_vigente',
                    className: 'text-center',
                    render: function (val, type, row) {
                        let total = row.total_versiones > 0 ? ` (${row.total_versiones})` : '';
                        return val ? `<span class="badge bg-success">v${val}</span><small class="text-muted d-block fs-8">${total}</small>` : '<span class="badge bg-light text-muted border">-</span>';
                    }
                },
                { data: 'vigente_desde', className: 'text-center' },
                {
                    data: 'tabla_estado_registro_id',
                    className: 'text-center',
                    render: function (val, type, row) {
                        const est = parseInt(val, 10);
                        if (est === 2045) return '<span class="badge bg-secondary"><i class="fas fa-edit me-1"></i>Borrador</span>';
                        if (est === 2046) return '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>En Revisión</span>';
                        if (est === 2047) return '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Vigente</span>';
                        if (est === 2048) return '<span class="badge bg-dark"><i class="fas fa-ban me-1"></i>Derogada</span>';
                        return `<span class="badge bg-light text-dark">${row.estado_registro}</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        let btns = '<div class="btn-group btn-group-sm" role="group">';

                        // Botón Editar (solo Borrador)
                        if (row.permite_editar) {
                            btns += `<button type="button" class="btn btn-outline-primary btn-sm btnEditarNorma" data-id="${row.norma_id}" title="Editar Norma"><i class="fas fa-edit"></i></button>`;
                        }

                        // Botón Enviar a Revisión (2045 -> 2046)
                        if (row.permite_enviar_revision) {
                            btns += `<button type="button" class="btn btn-outline-warning btn-sm btnCambiarEstado" data-id="${row.norma_id}" data-estado="2046" title="Enviar a Revisión Legal"><i class="fas fa-paper-plane"></i></button>`;
                        }

                        // Botón Aprobar y Publicar (2046 -> 2047)
                        if (row.permite_aprobar) {
                            btns += `<button type="button" class="btn btn-outline-success btn-sm btnCambiarEstado" data-id="${row.norma_id}" data-estado="2047" title="Aprobar y Publicar (Vigente)"><i class="fas fa-check"></i></button>`;
                        }

                        // Botón Derogar (2047 -> 2048)
                        if (row.permite_derogar) {
                            btns += `<button type="button" class="btn btn-outline-danger btn-sm btnCambiarEstado" data-id="${row.norma_id}" data-estado="2048" title="Derogar Norma (Caso C12)"><i class="fas fa-ban"></i></button>`;
                        }

                        // Botón Versiones
                        btns += `<button type="button" class="btn btn-outline-info btn-sm btnVerVersiones" data-id="${row.norma_id}" data-codigo="${row.codigo}" title="Gestionar Versiones Documentales"><i class="fas fa-code-branch"></i></button>`;

                        // Botón Relaciones
                        btns += `<button type="button" class="btn btn-outline-secondary btn-sm btnVerRelaciones" data-id="${row.norma_id}" data-codigo="${row.codigo}" title="Vínculos y Relaciones (Caso C18)"><i class="fas fa-project-diagram"></i></button>`;

                        btns += '</div>';
                        return btns;
                    }
                }
            ],
            order: [[7, 'desc']]
        });
    }

    // Filtros
    $('#filtroTipo, #filtroOrganismo, #filtroAmbito, #filtroEstado, #filtroConfidencial').on('change', function () {
        if (tablaNormas) tablaNormas.ajax.reload();
    });

    $('#btnRecargarNormas').on('click', function () {
        if (tablaNormas) tablaNormas.ajax.reload();
    });

    // =========================================================================
    // 3. ALTA Y EDICIÓN DE NORMA
    // =========================================================================
    $(document).on('click', '#btnNuevaNorma', function () {
        $('#formNorma')[0].reset();
        $('#norma_id').val('0');
        $('#norma_codigo').prop('readonly', false);
        $('#norma_ambito_tipo_id').prop('disabled', false);
        $('.bloque-destino').hide();
        $('#norma_vigente_desde').val(Fmt.hoyISO());
        $('#modalNormaLabel').html('<i class="fas fa-book me-1"></i>Registrar Norma Jurídica o Corporativa');
        new bootstrap.Modal(document.getElementById('modalNorma')).show();
    });

    $(document).on('click', '.btnEditarNorma', function () {
        const id = $(this).data('id');
        $.getJSON('normas_ajax.php', { accion: 'obtener_norma', norma_id: id, empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            if (res.success && res.data) {
                const n = res.data;
                $('#norma_id').val(n.norma_id);
                $('#norma_codigo').val(n.codigo).prop('readonly', true);
                $('#norma_titulo').val(n.titulo);
                $('#norma_descripcion').val(n.descripcion || '');
                $('#norma_idioma_original').val(n.idioma_original || 'ES');
                $('#norma_titulo_traducido').val(n.titulo_traducido || '');
                $('#norma_idioma_traducido').val(n.idioma_traducido || '');
                $('#norma_tipo_id').val(n.norma_tipo_id);
                $('#norma_jerarquia_id').val(n.norma_jerarquia_id || '');
                $('#norma_organismo_id').val(n.organismo_id || '');
                $('#norma_ambito_tipo_id').val(n.ambito_tipo_id).trigger('change').prop('disabled', true);

                if (n.grupo_id) $('#norma_grupo_id').val(n.grupo_id);
                if (n.region_grupo_id) $('#norma_region_grupo_id').val(n.region_grupo_id);
                if (n.empresa_id) $('#norma_empresa_id').val(n.empresa_id);
                if (n.territorio_id) $('#norma_territorio_id').val(n.territorio_id);

                $('#norma_vigente_desde').val(n.vigente_desde);
                $('#norma_vigente_hasta').val(n.vigente_hasta || '');
                $('#norma_requiere_local').prop('checked', parseInt(n.requiere_norma_local, 10) === 1);
                $('#norma_confidencial').prop('checked', parseInt(n.confidencial, 10) === 1);

                $('#modalNormaLabel').html('<i class="fas fa-edit me-1"></i>Editar Norma');
                new bootstrap.Modal(document.getElementById('modalNorma')).show();
            }
        });
    });

    $('#formNorma').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) return;

        const data = $(this).serialize() + '&accion=guardar_norma&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;
        $.post('normas_ajax.php', data, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Guardada', text: res.message, timer: 1500, showConfirmButton: false });
                bootstrap.Modal.getInstance(document.getElementById('modalNorma')).hide();
                tablaNormas.ajax.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    // =========================================================================
    // 4. CAMBIO DE ESTADO (PROCESO CORTO)
    // =========================================================================
    $(document).on('click', '.btnCambiarEstado', function () {
        const id = $(this).data('id');
        const estado = parseInt($(this).data('estado'), 10);

        let tituloSwal = '¿Avanzar estado?';
        let textoSwal = '';
        let btnColor = '#3085d6';

        if (estado === 2046) {
            tituloSwal = '¿Enviar a Revisión Legal?';
            textoSwal = 'La norma pasará a control técnico y dictamen legal.';
            btnColor = '#f39c12';
        } else if (estado === 2047) {
            tituloSwal = '¿Aprobar y Publicar Norma?';
            textoSwal = 'La norma quedará Confirmada y Vigente para herencia en el árbol institucional.';
            btnColor = '#00a65a';
        } else if (estado === 2048) {
            tituloSwal = '¿Derogar Norma? (Caso C12)';
            textoSwal = 'La norma cesará su vigencia activa y sus ciclos futuros serán eximidos o cancelados.';
            btnColor = '#dd4b39';
        }

        Swal.fire({
            title: tituloSwal,
            text: textoSwal,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: btnColor
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('normas_ajax.php', {
                    accion: 'cambiar_estado',
                    norma_id: id,
                    nuevo_estado: estado,
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                }, function (res) {
                    if (res.success) {
                        Swal.fire({ icon: 'success', title: 'Actualizado', text: res.message, timer: 1500, showConfirmButton: false });
                        tablaNormas.ajax.reload();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                    }
                }, 'json');
            }
        });
    });

    // =========================================================================
    // 5. GESTIÓN DE VERSIONES (CASO C02)
    // =========================================================================
    $(document).on('click', '.btnVerVersiones', function () {
        const id = $(this).data('id');
        const codigo = $(this).data('codigo');

        $('#lblNormaCodigoVersiones').text(codigo);
        $('#ver_norma_id').val(id);
        $('#formNuevaVersion')[0].reset();
        $('#ver_vigente_desde').val(Fmt.hoyISO());

        cargarVersiones(id);
        new bootstrap.Modal(document.getElementById('modalVersiones')).show();
    });

    function cargarVersiones(normaId) {
        $.getJSON('normas_ajax.php', { accion: 'listar_versiones', norma_id: normaId, empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            if (res.success && res.data) {
                let html = '';
                res.data.forEach(function (v) {
                    const activaBadge = parseInt(v.es_vigente, 10) === 1 
                        ? '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Vigente</span>' 
                        : '<span class="badge bg-light text-muted border">Histórica</span>';

                    html += `<tr>
                        <td class="text-center text-muted fw-semibold">${v.norma_version_id}</td>
                        <td class="fw-bold text-primary">v${v.version}</td>
                        <td class="text-center">${v.fecha_publicacion || '-'}</td>
                        <td class="text-center">${v.vigente_desde}</td>
                        <td class="text-center">${activaBadge}</td>
                        <td><small class="font-monospace text-truncate d-inline-block" style="max-width: 220px;" title="${v.hash || ''}">${v.hash || 'Sin hash'}</small></td>
                        <td class="text-center"><span class="badge bg-secondary">${v.estado_registro}</span></td>
                    </tr>`;
                });
                $('#bodyVersiones').html(html || '<tr><td colspan="7" class="text-center text-muted py-2">Sin versiones registradas</td></tr>');
            }
        });
    }

    $('#formNuevaVersion').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) return;

        const data = $(this).serialize() + '&accion=guardar_version&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;
        $.post('normas_ajax.php', data, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Versión Creada', text: res.message, timer: 1200, showConfirmButton: false });
                $('#formNuevaVersion')[0].reset();
                cargarVersiones($('#ver_norma_id').val());
                tablaNormas.ajax.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    // =========================================================================
    // 6. GESTIÓN DE RELACIONES INTER-NORMAS (CASO C18)
    // =========================================================================
    $(document).on('click', '.btnVerRelaciones', function () {
        const id = $(this).data('id');
        const codigo = $(this).data('codigo');

        $('#lblNormaCodigoRelaciones').text(codigo);
        $('#rel_norma_origen_id').val(id);
        $('#formNuevaRelacion')[0].reset();
        $('#rel_vigente_desde').val(Fmt.hoyISO());

        // Cargar combo de normas destino excluyendo la norma actual
        let optDestinos = '<option value="">-- Seleccionar Norma Destino --</option>';
        todasLasNormas.forEach(function (n) {
            if (parseInt(n.norma_id, 10) !== parseInt(id, 10)) {
                optDestinos += `<option value="${n.norma_id}">[${n.codigo}] ${n.titulo}</option>`;
            }
        });
        $('#rel_norma_destino_id').html(optDestinos);

        cargarRelaciones(id);
        new bootstrap.Modal(document.getElementById('modalRelaciones')).show();
    });

    function cargarRelaciones(normaId) {
        $.getJSON('normas_ajax.php', { accion: 'listar_relaciones', norma_id: normaId, empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            if (res.success && res.data) {
                let html = '';
                res.data.forEach(function (r) {
                    const sentidoBadge = r.sentido === 'SALIENTE'
                        ? '<span class="badge bg-primary"><i class="fas fa-arrow-right me-1"></i>Saliente</span>'
                        : '<span class="badge bg-info text-dark"><i class="fas fa-arrow-left me-1"></i>Entrante</span>';

                    const normaVinculada = r.sentido === 'SALIENTE'
                        ? `<span class="fw-semibold text-dark">${r.destino_codigo}</span> - <small>${r.destino_titulo}</small>`
                        : `<span class="fw-semibold text-dark">${r.origen_codigo}</span> - <small>${r.origen_titulo}</small>`;

                    html += `<tr>
                        <td class="text-center">${sentidoBadge}</td>
                        <td><span class="badge bg-dark">${r.relacion_codigo}</span> <small class="text-muted d-block">${r.relacion_nombre}</small></td>
                        <td>${normaVinculada}</td>
                        <td><small>${r.descripcion || 'Sin detalle de artículo'}</small></td>
                        <td class="text-center"><small>${r.vigente_desde}</small></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-outline-danger btn-sm btnEliminarRelacion" data-id="${r.norma_relacion_id}" title="Eliminar Vínculo">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>`;
                });
                $('#bodyRelaciones').html(html || '<tr><td colspan="6" class="text-center text-muted py-2">Sin relaciones vinculadas</td></tr>');
            }
        });
    }

    $('#formNuevaRelacion').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) return;

        const data = $(this).serialize() + '&accion=guardar_relacion&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;
        $.post('normas_ajax.php', data, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Relación Vinculada', text: res.message, timer: 1200, showConfirmButton: false });
                $('#rel_tipo_id').val('');
                $('#rel_norma_destino_id').val('');
                $('#rel_descripcion').val('');
                cargarRelaciones($('#rel_norma_origen_id').val());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    $(document).on('click', '.btnEliminarRelacion', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: '¿Eliminar vínculo?',
            text: 'Se removerá la relación normativa entre ambas normas.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, remover',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('normas_ajax.php', {
                    accion: 'eliminar_relacion',
                    norma_relacion_id: id,
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                }, function (res) {
                    if (res.success) {
                        Swal.fire({ icon: 'success', title: 'Eliminada', text: res.message, timer: 1200, showConfirmButton: false });
                        cargarRelaciones($('#rel_norma_origen_id').val());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                    }
                }, 'json');
            }
        });
    });

    // =========================================================================
    // 7. INICIALIZACIÓN
    // =========================================================================
    cargarCombos();
    inicializarDataTable();
});
