/**
 * auditoria.js
 * Lógica del frontend para el panel de Auditoría y Trazabilidad Forense.
 * Módulo: Conf (Configuración y Seguridad)
 */

$(document).ready(function () {
    'use strict';

    let tablaAuditoria = null;
    const modalDetalleEl = document.getElementById('modalDetalleAuditoria');
    const modalDetalle = modalDetalleEl ? bootstrap.Modal.getOrCreateInstance(modalDetalleEl) : null;
    const modalHistoriaEl = document.getElementById('modalHistoriaRegistro');
    const modalHistoria = modalHistoriaEl ? bootstrap.Modal.getOrCreateInstance(modalHistoriaEl) : null;

    /**
     * Mapeo visual de tipos de acción a badges HTML
     */
    function renderBadgeAccion(accion) {
        if (!accion) return '<span class="badge bg-secondary">DESCONOCIDO</span>';
        
        const map = {
            'LOGIN_EXITOSO': '<span class="badge bg-success badge-accion"><i class="bi bi-box-arrow-in-right me-1"></i>LOGIN OK</span>',
            'LOGIN_FALLIDO': '<span class="badge bg-danger badge-accion"><i class="bi bi-shield-x me-1"></i>LOGIN FALLIDO</span>',
            'LOGOUT': '<span class="badge bg-secondary badge-accion"><i class="bi bi-box-arrow-left me-1"></i>LOGOUT</span>',
            'CAMBIO_CONTEXTO': '<span class="badge bg-info text-dark badge-accion"><i class="bi bi-arrow-left-right me-1"></i>CAMBIO EMPRESA</span>',
            'SEGURIDAD_ACCESO_DENEGADO': '<span class="badge bg-warning text-dark badge-accion"><i class="bi bi-shield-lock me-1"></i>ACCESO DENEGADO</span>',
            'SEGURIDAD_CSRF_INVALIDO': '<span class="badge bg-danger badge-accion"><i class="bi bi-exclamation-octagon me-1"></i>CSRF INVÁLIDO</span>',
            'ALTA': '<span class="badge bg-primary badge-accion"><i class="bi bi-plus-lg me-1"></i>ALTA</span>',
            'MODIFICACION': '<span class="badge bg-primary bg-opacity-75 badge-accion"><i class="bi bi-pencil me-1"></i>MODIFICACIÓN</span>',
            'BAJA': '<span class="badge bg-dark badge-accion"><i class="bi bi-trash me-1"></i>BAJA</span>',
            'APROBAR': '<span class="badge bg-success badge-accion"><i class="bi bi-check-circle me-1"></i>APROBADO</span>',
            'ANULAR': '<span class="badge bg-danger badge-accion"><i class="bi bi-x-circle me-1"></i>ANULADO</span>',
            'RECHAZAR': '<span class="badge bg-danger badge-accion"><i class="bi bi-slash-circle me-1"></i>RECHAZADO</span>',
            'CERRAR': '<span class="badge bg-secondary badge-accion"><i class="bi bi-lock me-1"></i>CIERRE</span>',
            'LIQUIDAR': '<span class="badge bg-info text-dark badge-accion"><i class="bi bi-cash-stack me-1"></i>LIQUIDACIÓN</span>',
            'FACTURAR': '<span class="badge bg-success badge-accion"><i class="bi bi-receipt me-1"></i>FACTURACIÓN</span>',
            'COBRAR': '<span class="badge bg-success badge-accion"><i class="bi bi-wallet2 me-1"></i>COBRO</span>',
            'PAGAR': '<span class="badge bg-warning text-dark badge-accion"><i class="bi bi-credit-card me-1"></i>PAGO</span>',
            'IMPORTAR': '<span class="badge bg-secondary badge-accion"><i class="bi bi-file-earmark-arrow-up me-1"></i>IMPORTACIÓN</span>'
        };

        if (map[accion]) return map[accion];
        if (accion.startsWith('SEGURIDAD_')) {
            return `<span class="badge bg-warning text-dark badge-accion"><i class="bi bi-shield-exclamation me-1"></i>${accion}</span>`;
        }
        return `<span class="badge bg-light text-dark border badge-accion">${accion}</span>`;
    }

    /**
     * Formateador seguro de JSON para visualizador
     */
    function formatearJson(jsonStr) {
        if (!jsonStr || jsonStr.trim() === '' || jsonStr === 'null' || jsonStr === '{}') {
            return '<span class="text-muted fst-italic">Sin datos registrados</span>';
        }
        try {
            const obj = (typeof jsonStr === 'object') ? jsonStr : JSON.parse(jsonStr);
            return JSON.stringify(obj, null, 2);
        } catch (e) {
            return jsonStr;
        }
    }

    /**
     * Obtener el año actualmente seleccionado
     */
    function getAnioSeleccionado() {
        return parseInt($('#selectAnioAuditoria').val(), 10) || new Date().getFullYear();
    }

    /**
     * Cargar KPIs estadísticos del año
     */
    function cargarKPIs(anio) {
        $.ajax({
            url: 'auditoria_ajax.php',
            type: 'GET',
            data: { accion: 'kpis', anio: anio },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.kpis) {
                    const total = res.kpis.total || res.kpis.total_eventos || 0;
                    const loginsOk = res.kpis.logins_ok || 0;
                    const loginsFail = res.kpis.logins_fail || 0;
                    const seg = res.kpis.seguridad || res.kpis.alertas_seguridad || 0;

                    $('#kpiTotal').text(Number(total).toLocaleString());
                    $('#kpiLoginsOk').text(Number(loginsOk).toLocaleString());
                    $('#kpiLoginsFail').text(Number(loginsFail).toLocaleString());
                    $('#kpiSeguridad').text(Number(seg).toLocaleString());
                }
            },
            error: function () {
                console.warn('Error al cargar KPIs de auditoría');
            }
        });
    }

    /**
     * Cargar catálogos para los selectores de filtros (solo actores del año seleccionado)
     */
    function cargarCatalogos(anio) {
        const anioConsulta = anio || getAnioSeleccionado();
        $.ajax({
            url: 'auditoria_ajax.php',
            type: 'GET',
            data: { accion: 'catalogos', anio: anioConsulta },
            dataType: 'json',
            success: function (res) {
                if (!res.success) return;

                // Actualizar usuarios (solo los que interactuaron en el año)
                const $selUsuario = $('#filtroUsuario');
                const prevUsuario = $selUsuario.val();
                $selUsuario.find('option:not(:first)').remove();

                if (Array.isArray(res.usuarios) && res.usuarios.length > 0) {
                    res.usuarios.forEach(function (u) {
                        const id = u.id || u.usuario_id;
                        let texto = u.nombre;
                        if (!texto) {
                            const n = (u.nombre_completo && u.nombre_completo.trim()) ? u.nombre_completo.trim() : '';
                            const l = (u.usuario && u.usuario.trim()) ? u.usuario.trim() : '';
                            texto = n ? (l ? `${n} (${l})` : n) : l;
                        }
                        $selUsuario.append($('<option>', {
                            value: id,
                            text: texto || ('Usuario #' + id)
                        }));
                    });
                    if (prevUsuario) {
                        $selUsuario.val(prevUsuario);
                    }
                }

                // Actualizar empresas (solo las que tuvieron eventos en el año)
                const $selEmpresa = $('#filtroEmpresa');
                const prevEmpresa = $selEmpresa.val();
                $selEmpresa.find('option:not(:first)').remove();

                if (Array.isArray(res.empresas) && res.empresas.length > 0) {
                    res.empresas.forEach(function (e) {
                        const id = e.id || e.empresa_id;
                        const nombre = e.nombre || e.empresa || ('Empresa #' + id);
                        $selEmpresa.append($('<option>', {
                            value: id,
                            text: nombre
                        }));
                    });
                    if (prevEmpresa) {
                        $selEmpresa.val(prevEmpresa);
                    }
                }

                // Actualizar lista de años si hay años nuevos en la base de datos
                if (Array.isArray(res.anios)) {
                    const $selAnio = $('#selectAnioAuditoria');
                    const aniosEnSelect = [];
                    $selAnio.find('option').each(function () {
                        aniosEnSelect.push(parseInt($(this).val(), 10));
                    });

                    res.anios.forEach(function (a) {
                        if (!aniosEnSelect.includes(a)) {
                            $selAnio.append(`<option value="${a}">${a}</option>`);
                        }
                    });
                }
            }
        });
    }

    /**
     * Inicializar DataTable de auditoría
     */
    function initDataTable() {
        tablaAuditoria = $('#tablaAuditoria').DataTable({
            responsive: true,
            processing: true,
            order: [[1, 'desc'], [0, 'desc']],
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            dom: '<"row mb-3"<"col-12 col-md-6"B><"col-12 col-md-6 text-end"l>>rt<"row align-items-center mt-3"<"col-12 col-md-4"i><"col-12 col-md-8 d-flex justify-content-md-end justify-content-center"p>>',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="bi bi-file-earmark-excel me-1"></i> Exportar Excel',
                    className: 'btn btn-sm btn-outline-success rounded-2',
                    title: function () {
                        return 'Logs_Auditoria_' + getAnioSeleccionado() + '_' + new Date().toISOString().slice(0, 10);
                    },
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7]
                    }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="bi bi-file-earmark-pdf me-1"></i> Exportar PDF',
                    className: 'btn btn-sm btn-outline-danger rounded-2 ms-2',
                    orientation: 'landscape',
                    pageSize: 'A4',
                    title: function () {
                        return 'Auditoría Casalucho - Año ' + getAnioSeleccionado();
                    },
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7]
                    }
                }
            ],
            language: {
                lengthMenu: "Mostrar _MENU_ registros",
                zeroRecords: "No se encontraron eventos para los criterios especificados",
                info: "Mostrando _START_ a _END_ de _TOTAL_ eventos",
                infoEmpty: "Sin eventos registrados",
                infoFiltered: "(filtrado de un total de _MAX_)",
                search: "Buscar:",
                processing: '<div class="spinner-border text-primary spinner-border-sm" role="status"></div> Cargando registros...',
                paginate: {
                    first: "Primero",
                    last: "Último",
                    next: "Siguiente",
                    previous: "Anterior"
                }
            },
            ajax: {
                url: 'auditoria_ajax.php',
                type: 'POST',
                data: function (d) {
                    d.accion = 'listar';
                    d.anio = getAnioSeleccionado();
                    d.fecha_desde = $('#filtroFechaDesde').val();
                    d.fecha_hasta = $('#filtroFechaHasta').val();
                    d.filtro_accion = $('#filtroAccion').val();
                    d.usuario_id = $('#filtroUsuario').val();
                    d.empresa_id = $('#filtroEmpresa').val();
                    d.busqueda = $('#filtroBusqueda').val();
                },
                dataSrc: function (res) {
                    if (res && res.data) {
                        return res.data;
                    }
                    if (res && res.error) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Atención',
                            text: res.error,
                            confirmButtonColor: '#3085d6'
                        });
                    }
                    return [];
                }
            },
            columns: [
                {
                    data: function (row) {
                        return row.id || row.auditoria_id || '';
                    },
                    defaultContent: '',
                    className: 'text-center font-monospace small',
                    render: function (data, type) {
                        if (type === 'sort' || type === 'type') {
                            return parseInt(data, 10) || 0;
                        }
                        return `<span class="badge bg-light text-dark border">#${data}</span>`;
                    }
                },
                {
                    data: 'fecha_hora',
                    defaultContent: '',
                    className: 'text-nowrap small',
                    render: function (data, type) {
                        if (type === 'sort' || type === 'type') {
                            return data || '';
                        }
                        if (!data) return '-';
                        return `<span class="fw-semibold text-secondary">${data}</span>`;
                    }
                },
                {
                    data: 'usuario_nombre',
                    defaultContent: '',
                    render: function (data, type, row) {
                        if (!data && !row.usuario_login) {
                            return '<span class="text-muted fst-italic">Sistema / Anónimo</span>';
                        }
                        const nombre = data ? data : row.usuario_login;
                        return `<div class="d-flex align-items-center">
                            <i class="bi bi-person-circle text-secondary me-1"></i>
                            <span class="fw-semibold">${nombre}</span>
                        </div>`;
                    }
                },
                {
                    data: 'empresa_nombre',
                    defaultContent: '',
                    render: function (data) {
                        if (!data) return '<span class="text-muted">-</span>';
                        return `<span class="badge bg-light text-secondary border">${data}</span>`;
                    }
                },
                {
                    data: 'modulo_nombre',
                    defaultContent: '',
                    render: function (data, type, row) {
                        const mod = data ? data : '-';
                        const pag = row.pagina_nombre ? `<small class="text-muted d-block">${row.pagina_nombre}</small>` : '';
                        return `<div><span class="fw-semibold text-dark">${mod}</span>${pag}</div>`;
                    }
                },
                {
                    data: 'accion',
                    defaultContent: '',
                    render: function (data) {
                        return renderBadgeAccion(data);
                    }
                },
                {
                    data: 'descripcion',
                    defaultContent: '',
                    render: function (data, type, row) {
                        let badgeTabla = '';
                        if (row.tabla_afectada && row.tabla_afectada !== '0') {
                            const tablaIdText = row.tabla_id ? ` (Tbl #${row.tabla_id})` : '';
                            const reg = row.registro_id ? ` #${row.registro_id}` : '';
                            let badgeMulti = '';
                            if (row.tablas_involucradas) {
                                try {
                                    const arrTbl = typeof row.tablas_involucradas === 'object' ? row.tablas_involucradas : JSON.parse(row.tablas_involucradas);
                                    if (Array.isArray(arrTbl) && arrTbl.length > 1) {
                                        badgeMulti = ` <span class="badge bg-info text-dark font-monospace" style="font-size:0.65rem;" title="${arrTbl.length} tablas afectadas en esta operación"><i class="bi bi-diagram-3 me-1"></i>+${arrTbl.length} tablas</span>`;
                                    }
                                } catch (e) {}
                            }
                            badgeTabla = `<span class="badge bg-light text-secondary border me-1 font-monospace" style="font-size:0.7rem;"><i class="bi bi-table me-1"></i>${row.tabla_afectada}${tablaIdText}${reg}</span>${badgeMulti}`;
                        }
                        const texto = data || '';
                        const desc = (texto.length > 60) ? `<span title="${texto}">${texto.substring(0, 57)}...</span>` : texto;
                        return `<div>${badgeTabla}${desc}</div>`;
                    }
                },
                {
                    data: 'ip_origen',
                    defaultContent: '',
                    className: 'font-monospace small',
                    render: function (data) {
                        return data ? `<span class="text-secondary"><i class="bi bi-globe me-1"></i>${data}</span>` : '-';
                    }
                },
                {
                    data: null,
                    defaultContent: '',
                    orderable: false,
                    className: 'text-center text-nowrap',
                    render: function (data, type, row) {
                        const logId = row.id || row.auditoria_id || 0;
                        const btnPayload = row.tiene_payload
                            ? '<span class="position-absolute top-0 start-100 translate-middle p-1 bg-primary border border-light rounded-circle" title="Contiene datos de cambio"></span>'
                            : '';
                        return `
                            <button type="button" class="btn btn-sm btn-outline-primary btn-ver-detalle position-relative" data-id="${logId}" title="Ver inspección forense">
                                <i class="bi bi-eye"></i> Detalle
                                ${btnPayload}
                            </button>
                        `;
                    }
                }
            ]
        });
    }

    /**
     * Cargar inspección forense en el modal
     */
    function abrirDetalleLog(id, anio) {
        Swal.fire({
            title: 'Cargando detalle...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: 'auditoria_ajax.php',
            type: 'GET',
            data: { accion: 'detalle', id: id, anio: anio },
            dataType: 'json',
            success: function (res) {
                Swal.close();
                if (!res.success || !res.data) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.error || 'No se pudo obtener el detalle del registro',
                        confirmButtonColor: '#3085d6'
                    });
                    return;
                }

                const d = res.data;
                const logId = d.id || d.auditoria_id || id;
                $('#detLogId').text('#' + logId + ' (Año ' + anio + ')');
                $('#detFecha').text(d.fecha_hora || '-');
                $('#detAccion').html(renderBadgeAccion(d.accion));

                const usuario = (d.usuario_nombre ? d.usuario_nombre : '') + (d.usuario_login ? ` (${d.usuario_login})` : '');
                $('#detUsuario').text(usuario.trim() || 'Sistema / Sin autenticar');
                $('#detEmpresa').text(d.empresa_nombre ? `${d.empresa_nombre} (ID: ${d.empresa_id})` : 'Global / No aplica');

                const modPag = (d.modulo_nombre ? d.modulo_nombre : 'Módulo ' + (d.modulo_id || '-')) +
                               (d.pagina_nombre ? ' / ' + d.pagina_nombre : (d.pagina_id ? ' / Página ' + d.pagina_id : ''));
                $('#detModuloPagina').text(modPag);

                $('#detIp').text(d.ip_origen || '-');
                $('#detMetodo').text(d.metodo_http || 'GET');
                $('#detUrl').text(d.url_solicitada || '-');
                $('#detUserAgent').text(d.user_agent || '-');
                $('#detDescripcion').text(d.descripcion || 'Sin descripción');

                // Tabla y Registro ID
                const tablaAfectada = (d.tabla_afectada && d.tabla_afectada !== '0') ? d.tabla_afectada : '';
                const registroId = d.registro_id || '';
                const tablaId = d.tabla_id || '';
                $('#detTabla').text(tablaAfectada || 'No especificada');
                if (tablaId) {
                    $('#detTablaId').text(`ID Tabla: #${tablaId}`).show();
                } else {
                    $('#detTablaId').hide();
                }

                // Etiqueta e ID de registro (creado / modificado)
                if (d.accion === 'ALTA') {
                    $('#detLblRegistroId').text('ID de Registro Creado:');
                } else {
                    $('#detLblRegistroId').text('ID de Registro Modificado:');
                }
                $('#detRegistroId').text(registroId ? '#' + registroId : (d.accion === 'ALTA' ? 'No disponible' : 'No aplica'));

                if (tablaAfectada && registroId) {
                    $('#btnVerHistoriaRegistro, #btnVerHistoriaRegistroFooter')
                        .show()
                        .data('tabla', tablaAfectada)
                        .data('registro-id', registroId);
                } else {
                    $('#btnVerHistoriaRegistro, #btnVerHistoriaRegistroFooter').hide();
                }

                // Desglose de todas las tablas y entidades involucradas en el proceso
                let tablasInv = [];
                if (d.tablas_involucradas) {
                    try {
                        tablasInv = (typeof d.tablas_involucradas === 'object') ? d.tablas_involucradas : JSON.parse(d.tablas_involucradas);
                    } catch (e) {
                        tablasInv = [];
                    }
                }

                if (Array.isArray(tablasInv) && tablasInv.length > 0) {
                    $('#bloqueTablasInvolucradas').show();
                    $('#badgeTotalTablasInvolucradas').text(`${tablasInv.length} tablas / entidades`);
                    let tbodyHtml = '';
                    tablasInv.forEach(function (t) {
                        const tblNombre = t.tabla || '-';
                        const tblIdBadge = t.tabla_id ? `<span class="badge bg-secondary ms-1 font-monospace" title="ID en conf__tablas">#${t.tabla_id}</span>` : '';
                        const rol = t.rol || 'Entidad secundaria';
                        const subRegId = (t.registro_id && t.registro_id !== '0') 
                            ? `<span class="badge bg-primary font-monospace fs-6">#${t.registro_id}</span>` 
                            : '<span class="badge bg-light text-secondary border">Común al proceso</span>';
                        const accionBadge = renderBadgeAccion(t.accion || d.accion);
                        const btnHist = (tblNombre && t.registro_id && t.registro_id !== '0') 
                            ? `<button type="button" class="btn btn-xs btn-outline-info py-0 px-2 btn-historia-subtabla" data-tabla="${tblNombre}" data-registro-id="${t.registro_id}" title="Ver historia de esta tabla"><i class="bi bi-clock-history me-1"></i>Historia</button>`
                            : '';
                        tbodyHtml += `<tr>
                            <td class="font-monospace fw-bold text-dark"><i class="bi bi-table text-secondary me-1"></i>${tblNombre} ${tblIdBadge}</td>
                            <td>${rol}</td>
                            <td>${subRegId}</td>
                            <td>${accionBadge}</td>
                            <td class="text-end">${btnHist}</td>
                        </tr>`;
                    });
                    $('#tbodyTablasInvolucradas').html(tbodyHtml);
                } else {
                    $('#bloqueTablasInvolucradas').hide();
                }

                // Inspectores de datos antes y después (Diff)
                const tieneAntes = d.datos_antes && d.datos_antes !== '{}' && d.datos_antes !== 'null';
                const tieneDespues = d.datos_despues && d.datos_despues !== '{}' && d.datos_despues !== 'null';

                if (tieneAntes || tieneDespues) {
                    $('#bloquePayloads').slideDown(150);
                    $('#detDatosAntes').html(formatearJson(d.datos_antes));
                    $('#detDatosDespues').html(formatearJson(d.datos_despues));
                } else {
                    $('#bloquePayloads').hide();
                }

                if (modalDetalle) {
                    modalDetalle.show();
                } else {
                    $('#modalDetalleAuditoria').modal('show');
                }
            },
            error: function () {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'Error de red',
                    text: 'Ocurrió un error al consultar el registro de auditoría.',
                    confirmButtonColor: '#3085d6'
                });
            }
        });
    }

    // --- EVENTOS ---

    // Cambio de año en el selector
    $('#selectAnioAuditoria').on('change', function () {
        const anio = getAnioSeleccionado();
        $('#badgeAnioActual').text('Año: ' + anio);
        cargarCatalogos(anio);
        cargarKPIs(anio);
        if (tablaAuditoria) {
            tablaAuditoria.ajax.reload();
        }
    });

    // Botón recargar
    $('#btnRecargarLogs').on('click', function () {
        const anio = getAnioSeleccionado();
        cargarCatalogos(anio);
        cargarKPIs(anio);
        if (tablaAuditoria) {
            tablaAuditoria.ajax.reload(null, false);
        }
    });

    // Envío del formulario de filtros
    $('#formFiltrosAuditoria').on('submit', function (e) {
        e.preventDefault();
        if (tablaAuditoria) {
            tablaAuditoria.ajax.reload();
        }
    });

    // Limpiar filtros
    $('#btnLimpiarFiltros').on('click', function () {
        $('#filtroFechaDesde').val('');
        $('#filtroFechaHasta').val('');
        $('#filtroAccion').val('');
        $('#filtroUsuario').val('');
        $('#filtroEmpresa').val('');
        $('#filtroBusqueda').val('');
        if (tablaAuditoria) {
            tablaAuditoria.ajax.reload();
        }
    });

    // Clic en ver detalle de un log
    $('#tablaAuditoria').on('click', '.btn-ver-detalle', function () {
        const id = $(this).data('id');
        const anio = getAnioSeleccionado();
        abrirDetalleLog(id, anio);
    });

    /**
     * Consulta y renderiza la historia completa de cambios de un registro específico
     */
    function abrirHistoriaRegistro(tabla, registroId, todosAnios) {
        if (!tabla || !registroId) return;

        $('#historiaTablaNombre').text(tabla);
        $('#historiaTablaId').hide();
        $('#historiaRegistroId').text('#' + registroId);
        $('#historiaTotalEventos').text('...');
        $('#contenedorTimelineHistoria').html('<div class="text-center py-4"><div class="spinner-border text-primary spinner-border-sm" role="status"></div> Cargando historial de cambios...</div>');

        if (modalHistoria) {
            modalHistoria.show();
        } else {
            $('#modalHistoriaRegistro').modal('show');
        }

        $.ajax({
            url: 'auditoria_ajax.php',
            type: 'GET',
            data: {
                accion: 'historia_registro',
                tabla: tabla,
                registro_id: registroId,
                anio: getAnioSeleccionado(),
                todos_anios: todosAnios ? '1' : '0'
            },
            dataType: 'json',
            success: function (res) {
                if (!res.success || !Array.isArray(res.data)) {
                    $('#contenedorTimelineHistoria').html('<div class="alert alert-warning small">No se encontró historial para este registro.</div>');
                    $('#historiaTotalEventos').text('0');
                    return;
                }

                $('#historiaTotalEventos').text(res.data.length);
                if (res.tabla_id) {
                    $('#historiaTablaId').text(`ID Tabla: #${res.tabla_id}`).show();
                } else {
                    $('#historiaTablaId').hide();
                }

                if (res.data.length === 0) {
                    $('#contenedorTimelineHistoria').html('<div class="alert alert-info small text-center py-4">No hay registros de cambios asociados a este ID de registro.</div>');
                    return;
                }

                let html = '<div class="timeline-wrapper ps-2">';
                res.data.forEach(function (ev, index) {
                    const badge = renderBadgeAccion(ev.accion);
                    const usuario = (ev.usuario_nombre || 'Sistema') + (ev.usuario_login ? ` (${ev.usuario_login})` : '');
                    const fecha = ev.fecha_hora || '-';
                    const anioBadge = ev.anio ? `<span class="badge bg-light text-secondary border me-2">Año ${ev.anio}</span>` : '';
                    const desc = ev.descripcion || 'Sin descripción';
                    const ip = ev.ip_origen ? `<small class="text-muted"><i class="bi bi-globe me-1"></i>${ev.ip_origen}</small>` : '';

                    const tieneAntes = ev.datos_antes && ev.datos_antes !== '{}' && ev.datos_antes !== 'null';
                    const tieneDespues = ev.datos_despues && ev.datos_despues !== '{}' && ev.datos_despues !== 'null';
                    const colId = `collapseHistDiff_${index}`;

                    let botonDiff = '';
                    let contenidoDiff = '';

                    if (tieneAntes || tieneDespues) {
                        botonDiff = `
                            <button class="btn btn-xs btn-outline-secondary mt-2 py-0 px-2" type="button" data-bs-toggle="collapse" data-bs-target="#${colId}" aria-expanded="false">
                                <i class="bi bi-code-slash me-1"></i>Ver Cambios de Datos (Diff JSON)
                            </button>
                        `;
                        contenidoDiff = `
                            <div class="collapse mt-2" id="${colId}">
                                <div class="row g-2 p-2 bg-dark rounded">
                                    <div class="col-12 col-md-6">
                                        <small class="text-danger fw-bold d-block mb-1">Antes del cambio:</small>
                                        <pre class="json-box mb-0" style="max-height: 180px;">${formatearJson(ev.datos_antes)}</pre>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <small class="text-success fw-bold d-block mb-1">Después del cambio:</small>
                                        <pre class="json-box mb-0" style="max-height: 180px;">${formatearJson(ev.datos_despues)}</pre>
                                    </div>
                                </div>
                            </div>
                        `;
                    }

                    html += `
                        <div class="timeline-item">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <div>
                                    ${anioBadge}
                                    ${badge}
                                </div>
                                <span class="small fw-semibold text-secondary">${fecha}</span>
                            </div>
                            <div class="bg-white p-3 rounded border shadow-sm mb-2">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="small fw-bold text-dark">
                                        <i class="bi bi-person-circle text-primary me-1"></i>${usuario}
                                    </div>
                                    ${ip}
                                </div>
                                <div class="small text-secondary mb-1">${desc}</div>
                                ${botonDiff}
                                ${contenidoDiff}
                            </div>
                        </div>
                    `;
                });
                html += '</div>';

                $('#contenedorTimelineHistoria').html(html);
            },
            error: function () {
                $('#contenedorTimelineHistoria').html('<div class="alert alert-danger small">Error de red al consultar el historial de este registro.</div>');
            }
        });
    }

    // Botones para abrir la historia del registro desde el modal de detalle
    $('#btnVerHistoriaRegistro, #btnVerHistoriaRegistroFooter').on('click', function () {
        const tabla = $(this).data('tabla');
        const registroId = $(this).data('registro-id');
        const todosAnios = $('#checkTodosAniosHistoria').is(':checked');
        abrirHistoriaRegistro(tabla, registroId, todosAnios);
    });

    // Botones de historia para subtablas en el desglose multitabla
    $(document).on('click', '.btn-historia-subtabla', function () {
        const tabla = $(this).data('tabla');
        const registroId = $(this).data('registro-id');
        const todosAnios = $('#checkTodosAniosHistoria').is(':checked');
        abrirHistoriaRegistro(tabla, registroId, todosAnios);
    });

    // Switch de todos los años en el modal de historia
    $('#checkTodosAniosHistoria').on('change', function () {
        const tabla = $('#historiaTablaNombre').text();
        const registroId = $('#historiaRegistroId').text().replace('#', '').trim();
        abrirHistoriaRegistro(tabla, registroId, $(this).is(':checked'));
    });

    // INICIALIZACIÓN
    const anioInicial = getAnioSeleccionado();
    cargarCatalogos(anioInicial);
    cargarKPIs(anioInicial);
    initDataTable();
});

