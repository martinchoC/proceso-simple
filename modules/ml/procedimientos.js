/**
 * Lógica JavaScript Integral para el Ciclo de Vida de Procedimientos y Soporte
 * Módulo 3: Matriz Legal y Normativa
 * Archivo: modules/ml/procedimientos.js
 */

$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    const empresa_idx = parseInt(urlParams.get('empresa_id') || '2', 10);
    const pagina_idx = 315;

    let tablaProc;
    let procActualId = 0;
    let versionActualId = 0;

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

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatearTamano(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const dm = 1;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    // =========================================================================
    // 1. CARGA DE COMBOS (TIPOS, EMPRESAS, NORMAS, RESPONSABLES, ÁREAS)
    // =========================================================================
    function cargarCombos() {
        $.ajax({
            url: 'procedimientos_ajax.php',
            type: 'GET',
            data: { accion: 'combos', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const d = res.data;

                    // Tipos de Documento
                    let optFiltroTipo = '<option value="">Todos los tipos</option>';
                    let optProcTipo = '<option value="">-- Seleccione tipo --</option>';
                    if (d.tipos_documento) {
                        d.tipos_documento.forEach(function (t) {
                            const desc = `[${t.codigo}] ${t.nombre}`;
                            optFiltroTipo += `<option value="${t.procedimiento_tipo_id}">${desc}</option>`;
                            optProcTipo += `<option value="${t.procedimiento_tipo_id}">${desc}</option>`;
                        });
                    }
                    $('#filtroTipo').html(optFiltroTipo);
                    $('#proc_tipo_id').html(optProcTipo);

                    // Empresas
                    let optEmp = '<option value="">-- Aplica a todo el Grupo --</option>';
                    if (d.empresas) {
                        d.empresas.forEach(function (e) {
                            optEmp += `<option value="${e.empresa_id}">${e.empresa}</option>`;
                        });
                    }
                    $('#proc_empresa_id').html(optEmp);

                    // Normas
                    let optNormas = '<option value="">-- Sin norma asociada (Soporte interno) --</option>';
                    if (d.normas) {
                        d.normas.forEach(function (n) {
                            optNormas += `<option value="${n.norma_id}">[${n.codigo}] ${n.titulo}</option>`;
                        });
                    }
                    $('#proc_norma_id').html(optNormas);

                    // Responsables (Usuarios)
                    let optResp = '<option value="">-- Sin responsable asignado --</option>';
                    if (d.usuarios) {
                        d.usuarios.forEach(function (u) {
                            optResp += `<option value="${u.usuario_id}">${u.nombre}</option>`;
                        });
                    }
                    $('#proc_responsable_id').html(optResp);

                    // Datalist de Áreas sugeridas
                    let optAreas = '';
                    if (d.areas_sugeridas) {
                        d.areas_sugeridas.forEach(function (a) {
                            optAreas += `<option value="${a}">`;
                        });
                    }
                    $('#dlAreas').html(optAreas);

                    if (d.permite_guardar) {
                        $('#btn-container-procedimiento').html(`
                            <button type="button" class="btn btn-sm btn-primary" id="btnNuevoProcedimiento">
                                <i class="fas fa-plus me-1"></i>Nuevo Procedimiento
                            </button>
                        `);
                    }
                }
            }
        });
    }

    // =========================================================================
    // 2. INICIALIZAR DATATABLE DE PROCEDIMIENTOS
    // =========================================================================
    function inicializarDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaProcedimientos')) {
            $('#tablaProcedimientos').DataTable().destroy();
            $('#tablaProcedimientos tbody').empty();
        }

        tablaProc = $('#tablaProcedimientos').DataTable({
            dom: dtDom,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: dtLang,
            responsive: true,
            ajax: {
                url: 'procedimientos_ajax.php',
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                    d.filtro_estado = $('#filtroEstado').val();
                    d.filtro_tipo = $('#filtroTipo').val();
                },
                dataSrc: 'data'
            },
            columns: [
                // Col 0: ID
                { data: 'procedimiento_id', className: 'text-center fw-bold text-muted' },

                // Col 1: Tipo de Documento
                {
                    data: 'tipo_nombre',
                    render: function (val, type, row) {
                        if (!val) return '<span class="badge bg-light text-muted border">General</span>';
                        const color = row.tipo_color || '#0d6efd';
                        const icono = row.tipo_icono || 'fas fa-file-alt';
                        return `<span class="badge border" style="background-color: ${color}15; color: ${color}; border-color: ${color}40 !important;">
                            <i class="${icono} me-1"></i>${row.tipo_codigo || val}
                        </span>`;
                    }
                },

                // Col 2: Código
                {
                    data: 'codigo',
                    render: function (val) {
                        return `<strong class="text-primary font-monospace">${val}</strong>`;
                    }
                },

                // Col 3: Nombre del Procedimiento e Indicadores Relacionales
                {
                    data: 'nombre',
                    render: function (val, type, row) {
                        let desc = row.descripcion ? `<small class="text-muted d-block text-truncate" style="max-width: 260px;" title="${escapeHtml(row.descripcion)}">${escapeHtml(row.descripcion)}</small>` : '';
                        let emp = row.empresa_nombre ? `<span class="badge bg-light text-dark border me-1">${escapeHtml(row.empresa_nombre)}</span>` : '<span class="badge bg-secondary me-1">Grupo</span>';
                        
                        // Badges de Documentos Asociados y Lecturas (Puntos 1, 2, 4)
                        let badgeDocs = '';
                        if (row.total_documentos_asociados && parseInt(row.total_documentos_asociados, 10) > 0) {
                            badgeDocs = `<span class="badge bg-light text-success border border-success-subtle me-1" title="Documentos asociados vinculados"><i class="fas fa-sitemap me-1"></i>${row.total_documentos_asociados} docs</span>`;
                        }

                        let badgeLect = '';
                        if (row.total_lecturas_conformidad && parseInt(row.total_lecturas_conformidad, 10) > 0) {
                            badgeLect = `<span class="badge bg-light text-primary border border-primary-subtle me-1" title="Colaboradores que confirmaron lectura"><i class="fas fa-user-check me-1"></i>${row.total_lecturas_conformidad}</span>`;
                        }

                        let badgeConf = parseInt(row.confidencial, 10) === 1 ? '<span class="badge bg-danger ms-1" title="Documento Confidencial"><i class="fas fa-lock"></i></span>' : '';

                        return `<div>
                            <span class="fw-semibold text-dark">${escapeHtml(val)}</span>${badgeConf}
                            <div class="mt-1">${emp}${badgeDocs}${badgeLect}</div>
                            ${desc}
                        </div>`;
                    }
                },

                // Col 4: Área / Responsable
                {
                    data: 'area_responsable',
                    render: function (val, type, row) {
                        let areaHtml = val ? `<div class="fw-semibold text-dark"><i class="fas fa-sitemap me-1 text-secondary"></i>${escapeHtml(val)}</div>` : '';
                        let respHtml = row.responsable_nombre ? `<small class="text-muted d-block"><i class="fas fa-user-tie me-1 text-primary"></i>${escapeHtml(row.responsable_nombre)}</small>` : '';
                        if (!areaHtml && !respHtml) return '<span class="text-muted" style="font-size:0.8rem;">No asignado</span>';
                        return `<div>${areaHtml}${respHtml}</div>`;
                    }
                },

                // Col 5: Norma Jurídica Vinculada
                {
                    data: 'norma_codigo',
                    render: function (val, type, row) {
                        return val ? `<small><i class="fas fa-balance-scale me-1 text-primary"></i><strong>${escapeHtml(val)}</strong></small>` : '<span class="text-muted" style="font-size:0.8rem;">Soporte interno</span>';
                    }
                },

                // Col 6: Versión & Adjunto
                {
                    data: 'version_vigente',
                    className: 'text-center',
                    render: function (val, type, row) {
                        let verBadge = val ? `<span class="badge bg-success">v${val}</span>` : `<span class="badge bg-secondary">${row.total_versiones} ver.</span>`;
                        let fileRef = row.archivo_ref_vigente || row.archivo_ref_reciente;
                        let fileBtn = '';
                        if (fileRef) {
                            fileBtn = `<div class="mt-1"><a href="${fileRef}" target="_blank" class="btn btn-xs btn-outline-danger py-0 px-1" title="Descargar documento controlado vigente"><i class="fas fa-file-pdf me-1"></i>Adjunto</a></div>`;
                        }
                        return `<div>${verBadge}${fileBtn}</div>`;
                    }
                },

                // Col 7: Semáforo de Revisión Periódica (Punto 7 - ISO)
                {
                    data: 'semaforo_revision',
                    className: 'text-center',
                    render: function (val, type, row) {
                        const txt = row.semaforo_texto || 'Al día';
                        if (val === 'vencido') {
                            return `<span class="badge bg-danger-subtle text-danger border border-danger-subtle" title="Fecha límite superada"><i class="fas fa-exclamation-triangle me-1"></i>${txt}</span>`;
                        } else if (val === 'por_vencer') {
                            return `<span class="badge bg-warning-subtle text-dark border border-warning-subtle" title="Revisión próxima a vencer"><i class="fas fa-clock me-1"></i>${txt}</span>`;
                        } else {
                            return `<span class="badge bg-success-subtle text-success border border-success-subtle" title="Revisión periódica al día"><i class="fas fa-check-circle me-1"></i>${txt}</span>`;
                        }
                    }
                },

                // Col 8: Estado
                {
                    data: 'tabla_estado_registro_id',
                    className: 'text-center',
                    render: function (val, type, row) {
                        const est = parseInt(val, 10);
                        if (est === 2053) return '<span class="badge bg-secondary">Borrador</span>';
                        if (est === 2054) return '<span class="badge bg-warning text-dark">En Aprobación</span>';
                        if (est === 2055) return '<span class="badge bg-success">Confirmado</span>';
                        if (est === 2056) return '<span class="badge bg-dark">Obsoleto</span>';
                        return `<span class="badge bg-light text-dark">${escapeHtml(row.estado_registro)}</span>`;
                    }
                },

                // Col 9: Acciones Integrales
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        let btns = '<div class="btn-group btn-group-sm" role="group">';
                        const est = parseInt(row.tabla_estado_registro_id, 10);

                        // Editar datos cabecera (en borrador)
                        if (row.permite_editar) {
                            btns += `<button type="button" class="btn btn-outline-secondary btn-sm btnEditarProc" data-id="${row.procedimiento_id}" title="Editar Datos del Procedimiento"><i class="fas fa-edit"></i></button>`;
                        }

                        // Enviar a aprobación (si es borrador)
                        if (row.permite_enviar) {
                            btns += `<button type="button" class="btn btn-outline-warning btn-sm btnSolicitarAprobacion" data-id="${row.procedimiento_id}" data-codigo="${escapeHtml(row.codigo)}" title="Iniciar Circuito de Aprobación"><i class="fas fa-paper-plane"></i></button>`;
                        }

                        // Emitir dictamen / firma formal (si tiene permiso)
                        if (row.permite_aprobar && (est === 2054 || est === 2053)) {
                            btns += `<button type="button" class="btn btn-outline-success btn-sm btnAbrirModalFirma" data-id="${row.procedimiento_id}" data-codigo="${escapeHtml(row.codigo)}" title="Dictamen y Firma Electrónica"><i class="fas fa-file-signature"></i></button>`;
                        }

                        // Dar de baja / obsoleto (si está confirmado/vigente)
                        if (row.permite_aprobar && est === 2055) {
                            btns += `<button type="button" class="btn btn-outline-danger btn-sm btnAbrirModalBaja" data-id="${row.procedimiento_id}" data-codigo="${escapeHtml(row.codigo)}" title="Pase a Obsoleto / Baja"><i class="fas fa-archive"></i></button>`;
                        }

                        // Centro de Control del Ciclo de Vida (Hub)
                        btns += `<button type="button" class="btn btn-outline-primary btn-sm btnVerVersionesProc" data-id="${row.procedimiento_id}" data-codigo="${escapeHtml(row.codigo)}" data-nombre="${escapeHtml(row.nombre)}" title="Centro de Gestión Integral del Ciclo de Vida"><i class="fas fa-layer-group"></i></button>`;

                        btns += '</div>';
                        return btns;
                    }
                }
            ],
            order: [[0, 'desc']]
        });
    }

    // Filtros
    $('#filtroEstado, #filtroTipo').on('change', function () {
        if (tablaProc) tablaProc.ajax.reload();
    });

    $('#btnRecargar').on('click', function () {
        if (tablaProc) tablaProc.ajax.reload();
    });

    // =========================================================================
    // 3. ALTA Y EDICIÓN DE PROCEDIMIENTO (PUNTO 1)
    // =========================================================================
    $(document).on('click', '#btnNuevoProcedimiento', function () {
        $('#formProcedimiento')[0].reset();
        $('#proc_id').val('0');
        $('#modalProcTitle').html('<i class="fas fa-file-alt me-1"></i>Registrar Procedimiento de Soporte');
        $('#proc_frecuencia_meses').val('12');
        $('#proc_archivo').val('');
        new bootstrap.Modal(document.getElementById('modalProcedimiento')).show();
    });

    $(document).on('click', '.btnEditarProc', function () {
        const procId = $(this).data('id');
        $.getJSON('procedimientos_ajax.php', { accion: 'obtener', procedimiento_id: procId, empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            if (res.success && res.data) {
                const p = res.data;
                $('#formProcedimiento')[0].reset();
                $('#proc_id').val(p.procedimiento_id);
                $('#modalProcTitle').html(`<i class="fas fa-edit me-1"></i>Editar: [${escapeHtml(p.codigo)}] ${escapeHtml(p.nombre)}`);
                $('#proc_tipo_id').val(p.procedimiento_tipo_id || '');
                $('#proc_codigo').val(p.codigo);
                $('#proc_nombre').val(p.nombre);
                $('#proc_area_responsable').val(p.area_responsable || '');
                $('#proc_responsable_id').val(p.responsable_usuario_id || '');
                $('#proc_frecuencia_meses').val(p.frecuencia_revision_meses || '12');
                $('#proc_empresa_id').val(p.empresa_id || '');
                $('#proc_norma_id').val(p.norma_id || '');
                $('#proc_descripcion').val(p.descripcion || '');
                $('#proc_confidencial').prop('checked', parseInt(p.confidencial, 10) === 1);
                $('#proc_archivo').val('');

                new bootstrap.Modal(document.getElementById('modalProcedimiento')).show();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo obtener el procedimiento' });
            }
        });
    });

    $('#formProcedimiento').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) {
            this.reportValidity();
            return;
        }

        const btnSubmit = $('#btnGuardarProcedimiento');
        const originalBtnHtml = btnSubmit.html();
        btnSubmit.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        const formData = new FormData(this);
        formData.append('accion', 'guardar');
        formData.append('empresa_idx', empresa_idx);
        formData.append('pagina_idx', pagina_idx);

        $.ajax({
            url: 'procedimientos_ajax.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function (res) {
                btnSubmit.prop('disabled', false).html(originalBtnHtml);
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Guardado', text: res.message, timer: 1500, showConfirmButton: false });
                    bootstrap.Modal.getInstance(document.getElementById('modalProcedimiento')).hide();
                    tablaProc.ajax.reload();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                }
            },
            error: function () {
                btnSubmit.prop('disabled', false).html(originalBtnHtml);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error de comunicación con el servidor al guardar el procedimiento.' });
            }
        });
    });

    // =========================================================================
    // 4. CENTRO DE CONTROL DEL CICLO DE VIDA (TABS 1 A 5)
    // =========================================================================
    $(document).on('click', '.btnVerVersionesProc', function () {
        procActualId = $(this).data('id');
        const codigo = $(this).data('codigo');
        const nombre = $(this).data('nombre') || '';

        $('#lblProcCodigo').text(codigo);
        $('#lblProcNombreHeader').text('- ' + nombre);
        $('#verproc_id').val(procActualId);
        $('#vinc_padre_id').val(procActualId);
        $('#aprob_proc_id').val(procActualId);

        // Reset tab a Tab 1
        const tabEl = document.querySelector('#tab-ver-btn');
        const tab = new bootstrap.Tab(tabEl);
        tab.show();

        // Cargar datos de todos los tabs
        cargarVersionesProc(procActualId);
        cargarDocumentosAsociados(procActualId);
        cargarCandidatosDocumentos(procActualId);
        cargarAprobacionesProc(procActualId);
        cargarLogsProc(procActualId);

        new bootstrap.Modal(document.getElementById('modalVersionesProc')).show();
    });

    // --- TAB 1: Versiones e Instrucciones ---
    function cargarVersionesProc(procId) {
        $.getJSON('procedimientos_ajax.php', { accion: 'listar_versiones', procedimiento_id: procId, empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            if (res.success && res.data) {
                let html = '';
                let primeraVerId = 0;

                res.data.forEach(function (v, idx) {
                    if (idx === 0) primeraVerId = v.procedimiento_version_id;

                    let archivoHtml = '<span class="text-muted" style="font-size:0.75rem;">Sin adjunto</span>';
                    if (v.archivo_ref) {
                        const nombreArchivo = v.archivo_nombre_original || 'Descargar archivo';
                        const tamanoStr = v.archivo_tamano ? ` (${formatearTamano(v.archivo_tamano)})` : '';
                        const hashBadge = v.hash ? `<span class="badge bg-light text-muted border font-monospace mt-1 d-block" style="font-size:0.65rem;" title="SHA-256: ${v.hash}"><i class="fas fa-fingerprint me-1"></i>${v.hash.substring(0, 10)}...</span>` : '';
                        
                        archivoHtml = `<div>
                            <a href="${v.archivo_ref}" target="_blank" class="btn btn-xs btn-outline-danger py-0 px-1 text-truncate" style="max-width: 170px;" title="${escapeHtml(nombreArchivo)}">
                                <i class="fas fa-file-download me-1"></i>${escapeHtml(nombreArchivo)}
                            </a>
                            <small class="text-muted d-block" style="font-size:0.7rem;">${tamanoStr}</small>
                            ${hashBadge}
                        </div>`;
                    }

                    const estId = parseInt(v.tabla_estado_registro_id, 10);
                    let estBadge = '<span class="badge bg-secondary">Borrador</span>';
                    if (estId === 2059) {
                        estBadge = '<span class="badge bg-success">Vigente</span>';
                    } else if (estId === 2058) {
                        estBadge = '<span class="badge bg-warning text-dark">En Revisión</span>';
                    } else if (estId === 2060) {
                        estBadge = '<span class="badge bg-dark">Obsoleta</span>';
                    }

                    html += `<tr>
                        <td>
                            <strong>v${v.version}</strong>
                            ${v.resumen_cambios ? `<br><small class="text-muted fst-italic d-block text-truncate" style="max-width: 130px;" title="${escapeHtml(v.resumen_cambios)}">${escapeHtml(v.resumen_cambios)}</small>` : ''}
                        </td>
                        <td><small>${v.vigente_desde || '-'}</small></td>
                        <td>${archivoHtml}</td>
                        <td>${estBadge}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-xs btn-outline-primary btnSelVersion" data-id="${v.procedimiento_version_id}" data-version="${v.version}" title="Ver/Editar pasos de esta versión">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </td>
                    </tr>`;
                });

                $('#bodyVersionesProc').html(html || '<tr><td colspan="5" class="text-center text-muted py-2">Sin versiones registradas</td></tr>');

                // Si hay al menos una versión, cargar difusión y lectura para ella
                if (primeraVerId > 0 && versionActualId === 0) {
                    versionActualId = primeraVerId;
                    cargarLecturasProc(versionActualId);
                }
            }
        });
    }

    $(document).on('click', '.btnSelVersion', function () {
        versionActualId = $(this).data('id');
        const verStr = $(this).data('version');

        $('#lblVersionSeleccionada').text('v' + verStr).removeClass('bg-secondary').addClass('bg-success');
        $('#ins_version_id').val(versionActualId);
        $('#aprob_ver_id').val(versionActualId);
        $('#formNuevaInstruccion').show();

        cargarInstrucciones(versionActualId);
        cargarLecturasProc(versionActualId);
    });

    function cargarInstrucciones(versionId) {
        $.getJSON('procedimientos_ajax.php', { accion: 'listar_instrucciones', procedimiento_version_id: versionId, empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            if (res.success && res.data) {
                let html = '';
                res.data.forEach(function (ins) {
                    html += `<tr>
                        <td class="text-center fw-bold text-muted">${ins.orden}</td>
                        <td>
                            <strong>${escapeHtml(ins.titulo)}</strong>
                            ${ins.descripcion ? `<small class="text-muted d-block">${escapeHtml(ins.descripcion)}</small>` : ''}
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-xs btn-outline-danger btnEliminarInstruccion" data-id="${ins.instruccion_id}">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>`;
                });
                $('#bodyInstrucciones').html(html || '<tr><td colspan="3" class="text-center text-muted py-2">Sin pasos registrados aún</td></tr>');
                $('#ins_orden').val((res.data.length || 0) + 1);
            }
        });
    }

    $('#formNuevaVersionProc').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) {
            this.reportValidity();
            return;
        }

        const btnSubmit = $('#btnCrearVersion');
        const originalBtnHtml = btnSubmit.html();
        btnSubmit.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Creando...');

        const formData = new FormData(this);
        formData.append('accion', 'guardar_version');
        formData.append('empresa_idx', empresa_idx);
        formData.append('pagina_idx', pagina_idx);

        $.ajax({
            url: 'procedimientos_ajax.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function (res) {
                btnSubmit.prop('disabled', false).html(originalBtnHtml);
                if (res.success) {
                    $('#verproc_version').val('');
                    $('#verproc_resumen').val('');
                    $('#verproc_archivo').val('');
                    $('#verproc_aprobar_directo').prop('checked', false);
                    cargarVersionesProc(procActualId);
                    cargarLogsProc(procActualId);
                    tablaProc.ajax.reload();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                }
            },
            error: function () {
                btnSubmit.prop('disabled', false).html(originalBtnHtml);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error al comunicarse con el servidor al registrar la versión.' });
            }
        });
    });

    $('#formNuevaInstruccion').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) return;

        const data = $(this).serialize() + '&accion=guardar_instruccion&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;
        $.post('procedimientos_ajax.php', data, function (res) {
            if (res.success) {
                $('#ins_titulo').val('');
                $('#ins_descripcion').val('');
                cargarInstrucciones(versionActualId);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    $(document).on('click', '.btnEliminarInstruccion', function () {
        const id = $(this).data('id');
        $.post('procedimientos_ajax.php', {
            accion: 'eliminar_instruccion',
            instruccion_id: id,
            empresa_idx: empresa_idx,
            pagina_idx: pagina_idx
        }, function (res) {
            if (res.success) {
                cargarInstrucciones(versionActualId);
            }
        }, 'json');
    });

    // --- TAB 2: Documentos Asociados / Jerarquía Documental (Puntos 1 y 2) ---
    function cargarDocumentosAsociados(procId) {
        $.getJSON('procedimientos_ajax.php', { accion: 'listar_documentos_asociados', procedimiento_id: procId, empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            if (res.success && res.data) {
                $('#badgeContadorDocs').text(res.data.length);
                let html = '';
                res.data.forEach(function (doc) {
                    const relLabels = {
                        'instructivo': '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fas fa-list-ol me-1"></i>Instructivo</span>',
                        'formulario': '<span class="badge bg-warning-subtle text-dark border border-warning-subtle"><i class="fas fa-file-invoice me-1"></i>Formulario</span>',
                        'registro': '<span class="badge bg-info-subtle text-info border border-info-subtle"><i class="fas fa-clipboard-check me-1"></i>Registro</span>',
                        'anexo': '<span class="badge bg-secondary-subtle text-dark border"><i class="fas fa-paperclip me-1"></i>Anexo</span>',
                        'politica_marco': '<span class="badge bg-primary-subtle text-primary border"><i class="fas fa-landmark me-1"></i>Política Marco</span>'
                    };
                    const relHtml = relLabels[doc.tipo_relacion] || `<span class="badge bg-light text-dark">${doc.tipo_relacion}</span>`;
                    const obligHtml = parseInt(doc.obligatorio, 10) === 1 ? '<span class="badge bg-danger">Sí</span>' : '<span class="badge bg-light text-muted border">Opcional</span>';

                    let verDocHtml = '<span class="text-muted small">Sin versión</span>';
                    if (doc.version_vigente) {
                        let linkAdjunto = doc.archivo_ref_vigente ? `<a href="${doc.archivo_ref_vigente}" target="_blank" class="btn btn-xs btn-outline-danger py-0 px-1 ms-1" title="Descargar documento"><i class="fas fa-file-pdf"></i></a>` : '';
                        verDocHtml = `<span class="badge bg-success">v${doc.version_vigente}</span>${linkAdjunto}`;
                    }

                    html += `<tr>
                        <td>${relHtml}</td>
                        <td><strong class="text-primary font-monospace">${escapeHtml(doc.codigo)}</strong></td>
                        <td>
                            <span class="fw-semibold">${escapeHtml(doc.nombre)}</span>
                            ${doc.notas ? `<small class="text-muted d-block fst-italic">${escapeHtml(doc.notas)}</small>` : ''}
                        </td>
                        <td class="text-center">${obligHtml}</td>
                        <td class="text-center">${verDocHtml}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-xs btn-outline-danger btnDesvincularDoc" data-id="${doc.procedimiento_documento_id}" title="Desvincular">
                                <i class="fas fa-unlink"></i>
                            </button>
                        </td>
                    </tr>`;
                });
                $('#bodyDocumentosAsociados').html(html || '<tr><td colspan="6" class="text-center text-muted py-3">Sin documentos vinculados</td></tr>');
            }
        });
    }

    function cargarCandidatosDocumentos(procId) {
        $.getJSON('procedimientos_ajax.php', { accion: 'candidatos_documentos_asociados', procedimiento_id: procId, empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            if (res.success && res.data) {
                let opts = '<option value="">-- Seleccionar documento del catálogo --</option>';
                res.data.forEach(function (c) {
                    opts += `<option value="${c.procedimiento_id}">[${c.tipo_codigo}] ${c.codigo} - ${c.nombre}</option>`;
                });
                $('#vinc_documento_id').html(opts);
            }
        });
    }

    $('#formVincularDocumento').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) {
            this.reportValidity();
            return;
        }

        const data = $(this).serialize() + '&accion=vincular_documento&empresa_idx=' + empresa_idx + '&pagina_idx=' + pagina_idx;
        $.post('procedimientos_ajax.php', data, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Vinculado', text: res.message, timer: 1200, showConfirmButton: false });
                cargarDocumentosAsociados(procActualId);
                cargarCandidatosDocumentos(procActualId);
                cargarLogsProc(procActualId);
                tablaProc.ajax.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    $(document).on('click', '.btnDesvincularDoc', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: '¿Desvincular documento?',
            text: 'El documento dejará de figurar como anexo/asociado a este procedimiento.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, desvincular',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('procedimientos_ajax.php', {
                    accion: 'desvincular_documento',
                    procedimiento_documento_id: id,
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                }, function (res) {
                    if (res.success) {
                        cargarDocumentosAsociados(procActualId);
                        cargarCandidatosDocumentos(procActualId);
                        cargarLogsProc(procActualId);
                        tablaProc.ajax.reload();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                    }
                }, 'json');
            }
        });
    });

    // --- TAB 3: Workflow de Aprobaciones y Firmas Digitales (Punto 3) ---
    function cargarAprobacionesProc(procId) {
        $.getJSON('procedimientos_ajax.php', { accion: 'listar_aprobaciones', procedimiento_id: procId, empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            if (res.success && res.data) {
                $('#badgeContadorAprobs').text(res.data.length);
                let html = '';
                res.data.forEach(function (a) {
                    let dictBadge = '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Aprobado</span>';
                    if (a.accion === 'rechazado') {
                        dictBadge = '<span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Rechazado</span>';
                    } else if (a.accion === 'solicitado') {
                        dictBadge = '<span class="badge bg-warning text-dark"><i class="fas fa-paper-plane me-1"></i>Solicitud</span>';
                    }

                    const hashShort = a.firma_hash ? `<span class="badge bg-light text-muted border font-monospace" style="font-size:0.65rem;" title="SHA-256 Firma Digital: ${a.firma_hash}"><i class="fas fa-fingerprint me-1"></i>${a.firma_hash.substring(0, 10)}...</span>` : '-';
                    const verBadge = a.version ? `<span class="badge bg-light text-dark border ms-1">v${a.version}</span>` : '';

                    html += `<tr>
                        <td><small>${a.creado_en}</small></td>
                        <td><strong>${escapeHtml(a.usuario_nombre || a.usuario)}</strong>${verBadge}</td>
                        <td><span class="badge bg-light text-secondary border">${escapeHtml(a.rol_firmante)}</span></td>
                        <td class="text-center">${dictBadge}</td>
                        <td><small>${escapeHtml(a.observaciones || '-')}</small></td>
                        <td>${hashShort}</td>
                    </tr>`;
                });
                $('#bodyAprobacionesProc').html(html || '<tr><td colspan="6" class="text-center text-muted py-3">Sin firmas registradas aún</td></tr>');
            }
        });
    }

    $(document).on('click', '#btnAbrirModalAprobacion, .btnAbrirModalFirma', function () {
        const id = $(this).data('id') || procActualId;
        const codigo = $(this).data('codigo') || $('#lblProcCodigo').text();

        $('#aprob_proc_id').val(id);
        $('#aprob_ver_id').val(versionActualId || 0);
        $('#aprob_observaciones').val('');
        new bootstrap.Modal(document.getElementById('modalAprobacionFormal')).show();
    });

    // Iniciar solicitud de aprobación desde botón en fila
    $(document).on('click', '.btnSolicitarAprobacion', function () {
        const id = $(this).data('id');
        const codigo = $(this).data('codigo');

        Swal.fire({
            title: `Enviar a Aprobación: ${codigo}`,
            input: 'textarea',
            inputLabel: 'Nota de solicitud para los revisores/aprobadores:',
            inputPlaceholder: 'Indique los cambios realizados o alcance de la revisión requerida...',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-paper-plane me-1"></i>Enviar a Aprobación',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('procedimientos_ajax.php', {
                    accion: 'solicitar_aprobacion',
                    procedimiento_id: id,
                    nota: result.value || '',
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                }, function (res) {
                    if (res.success) {
                        Swal.fire({ icon: 'success', title: 'Enviado', text: res.message, timer: 1500, showConfirmButton: false });
                        tablaProc.ajax.reload();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                    }
                }, 'json');
            }
        });
    });

    // Dictamen Formal: APROBAR
    $('#formAprobacionFormal').on('submit', function (e) {
        e.preventDefault();
        const procId = $('#aprob_proc_id').val();
        const verId = $('#aprob_ver_id').val();
        const rol = $('#aprob_rol_firmante').val();
        const obs = $('#aprob_observaciones').val();

        $.post('procedimientos_ajax.php', {
            accion: 'aprobar_formal',
            procedimiento_id: procId,
            procedimiento_version_id: verId,
            rol_firmante: rol,
            observaciones: obs,
            empresa_idx: empresa_idx,
            pagina_idx: pagina_idx
        }, function (res) {
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Procedimiento Aprobado',
                    html: `<b>${res.message}</b><br><small class="text-muted font-monospace mt-2 d-block">Firma Digital: ${res.firma_hash ? res.firma_hash.substring(0, 24) + '...' : ''}</small>`
                });
                bootstrap.Modal.getInstance(document.getElementById('modalAprobacionFormal')).hide();
                tablaProc.ajax.reload();
                if ($('#modalVersionesProc').hasClass('show')) {
                    cargarVersionesProc(procId);
                    cargarAprobacionesProc(procId);
                    cargarLogsProc(procId);
                }
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    // Dictamen Formal: RECHAZAR
    $('#btnRechazarFormal').on('click', function () {
        const procId = $('#aprob_proc_id').val();
        const verId = $('#aprob_ver_id').val();
        const obs = $('#aprob_observaciones').val();

        if (!obs || obs.trim() === '') {
            Swal.fire({ icon: 'warning', title: 'Observaciones requeridas', text: 'Debe ingresar el motivo del rechazo en el campo de observaciones para que el autor pueda corregirlo.' });
            return;
        }

        $.post('procedimientos_ajax.php', {
            accion: 'rechazar_formal',
            procedimiento_id: procId,
            procedimiento_version_id: verId,
            motivo_rechazo: obs,
            empresa_idx: empresa_idx,
            pagina_idx: pagina_idx
        }, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'info', title: 'Procedimiento Rechazado', text: res.message });
                bootstrap.Modal.getInstance(document.getElementById('modalAprobacionFormal')).hide();
                tablaProc.ajax.reload();
                if ($('#modalVersionesProc').hasClass('show')) {
                    cargarVersionesProc(procId);
                    cargarAprobacionesProc(procId);
                    cargarLogsProc(procId);
                }
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    // --- TAB 4: Difusión y Toma de Conocimiento (Punto 4) ---
    function cargarLecturasProc(versionId) {
        if (!versionId || versionId <= 0) return;
        $.getJSON('procedimientos_ajax.php', { accion: 'listar_lecturas', procedimiento_version_id: versionId, empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            if (res.success && res.data) {
                const d = res.data;
                const porc = d.porcentaje_cobertura || 0;
                $('#lblPorcentajeLectura').text(porc + '%');
                $('#lblContadorLecturas').text(`(${d.total_lecturas} de ${d.total_personal} colaboradores)`);
                $('#barLecturas').css('width', porc + '%');

                let html = '';
                if (d.lecturas && d.lecturas.length > 0) {
                    d.lecturas.forEach(function (l) {
                        html += `<tr>
                            <td><small>${l.fecha_lectura}</small></td>
                            <td><strong>${escapeHtml(l.usuario_nombre || l.usuario)}</strong></td>
                            <td><small class="text-muted">${escapeHtml(l.perfil_nombre || 'General')}</small></td>
                            <td><span class="badge bg-light text-muted font-monospace">${escapeHtml(l.ip_origen || '-')}</span></td>
                            <td class="text-center"><span class="badge bg-success"><i class="fas fa-check me-1"></i>Conforme</span></td>
                        </tr>`;
                    });
                }
                $('#bodyLecturasProc').html(html || '<tr><td colspan="5" class="text-center text-muted py-3">Sin acuses de recibo registrados para esta versión</td></tr>');
            }
        });
    }

    $('#btnFirmarTomaConocimiento').on('click', function () {
        if (!versionActualId || versionActualId <= 0) {
            Swal.fire({ icon: 'warning', title: 'Seleccione una versión', text: 'Debe seleccionar una versión a la izquierda en la pestaña de Versiones.' });
            return;
        }

        Swal.fire({
            title: 'Toma de Conocimiento y Conformidad',
            html: '<p class="text-start small">Declaro formalmente haber <b>leído, comprendido y recibido capacitación/instrucción</b> sobre este procedimiento, comprometiéndome a su cumplimiento irrestricto en mis funciones operativas.</p>',
            input: 'text',
            inputPlaceholder: 'Comentario opcional...',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check-circle me-1"></i>Confirmar Conformidad',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('procedimientos_ajax.php', {
                    accion: 'registrar_lectura',
                    procedimiento_version_id: versionActualId,
                    comentario: result.value || '',
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                }, function (res) {
                    if (res.success) {
                        Swal.fire({ icon: 'success', title: 'Registrado', text: res.message, timer: 1500, showConfirmButton: false });
                        cargarLecturasProc(versionActualId);
                        cargarLogsProc(procActualId);
                        tablaProc.ajax.reload();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                    }
                }, 'json');
            }
        });
    });

    // --- TAB 5: Pista de Auditoría (Punto 7) ---
    function cargarLogsProc(procId) {
        $.getJSON('procedimientos_ajax.php', { accion: 'listar_logs', procedimiento_id: procId, empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            if (res.success && res.data) {
                let html = '';
                res.data.forEach(function (log) {
                    const evtColors = {
                        'CREACION': 'bg-primary',
                        'MODIFICACION': 'bg-info text-dark',
                        'NUEVA_VERSION': 'bg-primary',
                        'ENVIO_APROBACION': 'bg-warning text-dark',
                        'APROBADO': 'bg-success',
                        'RECHAZADO': 'bg-danger',
                        'OBSOLETO': 'bg-dark',
                        'VINCULO_DOC': 'bg-success text-white',
                        'DESVINCULO_DOC': 'bg-secondary',
                        'LECTURA_CONFORMIDAD': 'bg-info text-dark'
                    };
                    const colorBadge = evtColors[log.evento] || 'bg-light text-dark';
                    const verStr = log.version ? `<span class="badge bg-light text-dark border ms-1">v${log.version}</span>` : '';

                    html += `<tr>
                        <td><small>${log.creado_en}</small></td>
                        <td><span class="badge ${colorBadge}">${escapeHtml(log.evento)}</span>${verStr}</td>
                        <td>${escapeHtml(log.descripcion)}</td>
                        <td><strong>${escapeHtml(log.usuario_nombre || log.usuario)}</strong></td>
                        <td><small class="text-muted font-monospace">${escapeHtml(log.ip_origen || '-')}</small></td>
                    </tr>`;
                });
                $('#bodyLogsProc').html(html || '<tr><td colspan="5" class="text-center text-muted py-3">Sin eventos registrados</td></tr>');
            }
        });
    }

    // =========================================================================
    // 5. OBSOLESCENCIA Y BAJA CONTROLADA (PUNTO 6)
    // =========================================================================
    $(document).on('click', '.btnAbrirModalBaja', function () {
        const id = $(this).data('id');
        const codigo = $(this).data('codigo');

        $('#obs_proc_id').val(id);
        $('#obs_motivo').val('');
        new bootstrap.Modal(document.getElementById('modalObsoletoProc')).show();
    });

    $('#formMarcarObsoleto').on('submit', function (e) {
        e.preventDefault();
        const procId = $('#obs_proc_id').val();
        const motivo = $('#obs_motivo').val();

        if (!motivo || motivo.trim() === '') {
            Swal.fire({ icon: 'warning', title: 'Motivo obligatorio', text: 'Debe ingresar la justificación técnica de la baja del procedimiento.' });
            return;
        }

        $.post('procedimientos_ajax.php', {
            accion: 'marcar_obsoleto',
            procedimiento_id: procId,
            motivo: motivo,
            empresa_idx: empresa_idx,
            pagina_idx: pagina_idx
        }, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Dado de Baja', text: res.message });
                bootstrap.Modal.getInstance(document.getElementById('modalObsoletoProc')).hide();
                tablaProc.ajax.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            }
        }, 'json');
    });

    // =========================================================================
    // 6. INICIALIZACIÓN
    // =========================================================================
    cargarCombos();
    inicializarDataTable();
});
