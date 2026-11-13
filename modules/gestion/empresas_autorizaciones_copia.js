/**
 * JavaScript: ABM de Autorizaciones de Acceso a Información Básica Interempresa
 */

$(document).ready(function () {
    const AJAX_URL = 'empresas_autorizaciones_copia_ajax.php';
    let empresaActivaId = typeof EMPRESA_ACTIVA_ID !== 'undefined' ? parseInt(EMPRESA_ACTIVA_ID, 10) : parseInt($('#selectEmpresaFiltro').val() || '0', 10);

    const modalEl = document.getElementById('modalAutorizacion');

    function getModal() {
        if (window.bootstrap && bootstrap.Modal) {
            return bootstrap.Modal.getOrCreateInstance(modalEl);
        }
        return {
            show: function () { $('#modalAutorizacion').modal('show'); },
            hide: function () { $('#modalAutorizacion').modal('hide'); }
        };
    }

    // 1. Cambio de Empresa Activa en el selector superior
    $('#selectEmpresaFiltro').on('change', function () {
        const nuevaEmpresaId = $(this).val();
        window.location.href = `empresas_autorizaciones_copia.php?empresa_id=${nuevaEmpresaId}`;
    });

    // 2. Botón Recargar
    $('#btnRecargar').on('click', function () {
        cargarTodo();
    });

    // 3. Botón Nueva Autorización
    $('#btnNuevaAutorizacion').on('click', function () {
        abrirModalNuevo();
    });

    // Selección rápida de checkboxes
    $('#btnSelTodosComp').on('click', function () {
        $('.comp-chk').prop('checked', true);
    });

    $('#btnDeselTodosComp').on('click', function () {
        $('.comp-chk').prop('checked', false);
    });

    // 4. Submit del formulario
    $('#formAutorizacion').on('submit', function (e) {
        e.preventDefault();
        guardarAutorizacion();
    });

    function cargarTodo() {
        cargarOtorgadas();
        cargarRecibidas();
        cargarLog();
    }

    // 5. Cargar autorizaciones otorgadas
    function cargarOtorgadas() {
        const tbody = $('#tbodyOtorgadas');
        tbody.html('<tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-2"></i>Cargando autorizaciones otorgadas...</td></tr>');

        $.ajax({
            url: AJAX_URL,
            type: 'GET',
            data: {
                action: 'obtener_otorgadas',
                empresa_id: empresaActivaId
            },
            dataType: 'json',
            success: function (data) {
                if (!data.success) {
                    tbody.html(`<tr><td colspan="7" class="text-center text-danger py-3"><i class="fas fa-exclamation-triangle me-2"></i>${data.error || 'Error al cargar'}</td></tr>`);
                    return;
                }

                const items = data.data || [];
                $('#kpiOtorgadas').text(items.length);
                $('#badgeCountOtorgadas').text(items.length);
                const activas = items.filter(i => i.vigencia_estado === 'Activa').length;
                $('#kpiOtorgadasActivas').text(`${activas} activas`);

                if (items.length === 0) {
                    tbody.html(`<tr><td colspan="7" class="text-center text-muted py-4">
                        <i class="fas fa-folder-open fa-2x mb-2 d-block text-secondary"></i>
                        No se han otorgado autorizaciones a otras empresas desde esta empresa.
                    </td></tr>`);
                    return;
                }

                let html = '';
                items.forEach(function (item) {
                    const badges = generarBadgesComponentes(item);
                    const badgeVigencia = generarBadgeVigencia(item);
                    const estadoTxt = parseInt(item.tabla_estado_registro_id, 10) === 1
                        ? '<span class="badge bg-success">Activa</span>'
                        : '<span class="badge bg-secondary">Revocada</span>';

                    const btnToggle = parseInt(item.tabla_estado_registro_id, 10) === 1
                        ? `<button type="button" class="btn btn-xs btn-outline-warning btn-cambiar-estado" data-funcion="inactivar" data-id="${item.autorizacion_id}" data-estado="2" title="Revocar autorización"><i class="fas fa-ban"></i></button>`
                        : `<button type="button" class="btn btn-xs btn-outline-success btn-cambiar-estado" data-funcion="inactivar" data-id="${item.autorizacion_id}" data-estado="1" title="Reactivar autorización"><i class="fas fa-check"></i></button>`;

                    html += `<tr>
                        <td>
                            <strong class="text-primary">${escapeHtml(item.empresa_destino_nombre)}</strong>
                            <small class="d-block text-muted">ID: ${item.empresa_destino_id}</small>
                        </td>
                        <td><code>${escapeHtml(item.empresa_destino_cuit || 'S/D')}</code></td>
                        <td>${badges}</td>
                        <td class="text-center">
                            <small class="d-block">${item.fecha_desde || 'S/F'} al ${item.fecha_hasta || 'Sin límite'}</small>
                            ${badgeVigencia}
                        </td>
                        <td><small>${escapeHtml(item.motivo || 'Sin observaciones')}</small></td>
                        <td class="text-center">${estadoTxt}</td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-xs btn-outline-primary btn-editar" data-funcion="editar" data-id="${item.autorizacion_id}" title="Editar permisos"><i class="fas fa-pencil-alt"></i></button>
                                ${btnToggle}
                                <button type="button" class="btn btn-xs btn-outline-danger btn-eliminar" data-funcion="inactivar" data-id="${item.autorizacion_id}" title="Eliminar"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>`;
                });

                tbody.html(html);
                vincularEventosTablaOtorgadas();
            },
            error: function () {
                tbody.html('<tr><td colspan="7" class="text-center text-danger py-3">Error de comunicación con el servidor</td></tr>');
            }
        });
    }

    // 6. Cargar autorizaciones recibidas
    function cargarRecibidas() {
        const tbody = $('#tbodyRecibidas');
        tbody.html('<tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-2"></i>Cargando autorizaciones recibidas...</td></tr>');

        $.ajax({
            url: AJAX_URL,
            type: 'GET',
            data: {
                action: 'obtener_recibidas',
                empresa_id: empresaActivaId
            },
            dataType: 'json',
            success: function (data) {
                if (!data.success) {
                    tbody.html(`<tr><td colspan="7" class="text-center text-danger py-3"><i class="fas fa-exclamation-triangle me-2"></i>${data.error || 'Error'}</td></tr>`);
                    return;
                }

                const items = data.data || [];
                $('#kpiRecibidas').text(items.length);
                $('#badgeCountRecibidas').text(items.length);
                const activas = items.filter(i => i.vigencia_estado === 'Activa').length;
                $('#kpiRecibidasActivas').text(`${activas} activas`);

                if (items.length === 0) {
                    tbody.html(`<tr><td colspan="7" class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-2x mb-2 d-block text-secondary"></i>
                        Esta empresa no ha recibido autorizaciones de copia de otras empresas todavía.
                    </td></tr>`);
                    return;
                }

                let html = '';
                items.forEach(function (item) {
                    const badges = generarBadgesComponentes(item);
                    const badgeVigencia = generarBadgeVigencia(item);
                    const estadoTxt = parseInt(item.tabla_estado_registro_id, 10) === 1
                        ? '<span class="badge bg-success">Habilitada</span>'
                        : '<span class="badge bg-secondary">Revocada</span>';

                    html += `<tr>
                        <td>
                            <strong class="text-success">${escapeHtml(item.empresa_origen_nombre)}</strong>
                            <small class="d-block text-muted">ID: ${item.empresa_origen_id}</small>
                        </td>
                        <td><code>${escapeHtml(item.empresa_origen_cuit || 'S/D')}</code></td>
                        <td>${badges}</td>
                        <td class="text-center">
                            <small class="d-block">${item.fecha_desde || 'S/F'} al ${item.fecha_hasta || 'Sin límite'}</small>
                            ${badgeVigencia}
                        </td>
                        <td><small>${escapeHtml(item.motivo || 'Sin observaciones')}</small></td>
                        <td class="text-center">${estadoTxt}</td>
                        <td class="text-center">
                            ${item.vigencia_estado === 'Activa'
                                ? `<a href="empresa_inicio.php?empresa_id=${empresaActivaId}&origen_id=${item.empresa_origen_id}" class="btn btn-xs btn-primary" title="Copiar datos desde esta empresa"><i class="fas fa-clone me-1"></i>Usar Copia</a>`
                                : '<span class="badge bg-light text-muted">No utilizable</span>'}
                        </td>
                    </tr>`;
                });

                tbody.html(html);
            },
            error: function () {
                tbody.html('<tr><td colspan="7" class="text-center text-danger py-3">Error de comunicación con el servidor</td></tr>');
            }
        });
    }

    // 7. Cargar log de auditoría
    function cargarLog() {
        const tbody = $('#tbodyLog');
        tbody.html('<tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-2"></i>Cargando historial de copias...</td></tr>');

        $.ajax({
            url: AJAX_URL,
            type: 'GET',
            data: {
                action: 'obtener_log',
                empresa_id: empresaActivaId
            },
            dataType: 'json',
            success: function (data) {
                if (!data.success) {
                    tbody.html(`<tr><td colspan="7" class="text-center text-danger py-3">${data.error || 'Error'}</td></tr>`);
                    return;
                }

                const items = data.data || [];
                $('#kpiCopias').text(items.length);

                if (items.length === 0) {
                    tbody.html('<tr><td colspan="7" class="text-center text-muted py-4">No se registran eventos de copia para esta empresa.</td></tr>');
                    return;
                }

                let html = '';
                items.forEach(function (log) {
                    let componentes = 'N/D';
                    try {
                        const parsed = JSON.parse(log.componentes_solicitados);
                        componentes = Object.keys(parsed).filter(k => parsed[k]).map(k => k.replace('permite_', '')).join(', ');
                    } catch (e) {
                        componentes = log.componentes_solicitados || 'N/D';
                    }

                    let detalleHtml = '';
                    try {
                        const det = JSON.parse(log.detalle_ejecucion);
                        const parts = [];
                        for (let k in det) {
                            parts.push(`<strong>${k}:</strong> ${det[k]}`);
                        }
                        detalleHtml = parts.join(' | ');
                    } catch (e) {
                        detalleHtml = escapeHtml(log.detalle_ejecucion || '');
                    }

                    html += `<tr>
                        <td class="text-center"><code>#${log.copia_log_id}</code></td>
                        <td><small>${log.fecha_copia}</small></td>
                        <td>${escapeHtml(log.empresa_origen_nombre)}</td>
                        <td>${escapeHtml(log.empresa_destino_nombre)}</td>
                        <td><small class="badge bg-light text-dark border">${componentes}</small></td>
                        <td class="text-center"><span class="badge bg-info">${log.total_registros_creados}</span></td>
                        <td><small class="text-muted">${detalleHtml}</small></td>
                    </tr>`;
                });

                tbody.html(html);
            },
            error: function () {
                tbody.html('<tr><td colspan="7" class="text-center text-danger py-3">Error de comunicación con el servidor</td></tr>');
            }
        });
    }

    // Helper: Badges de componentes
    function generarBadgesComponentes(item) {
        const badges = [];
        if (parseInt(item.permite_plan_cuentas, 10) === 1) {
            badges.push('<span class="badge bg-primary me-1 mb-1" title="Plan de Cuentas Contables"><i class="fas fa-sitemap me-1"></i>Plan Cuentas</span>');
        }
        if (parseInt(item.permite_conceptos_contables, 10) === 1) {
            badges.push('<span class="badge bg-info text-dark me-1 mb-1" title="Conceptos Contables"><i class="fas fa-tags me-1"></i>Conceptos</span>');
        }
        if (parseInt(item.permite_roles_cuentas, 10) === 1) {
            badges.push('<span class="badge bg-success me-1 mb-1" title="Roles de Entidades"><i class="fas fa-users-cog me-1"></i>Roles Cuentas</span>');
        }
        if (parseInt(item.permite_comprobantes_tipos, 10) === 1) {
            badges.push('<span class="badge bg-warning text-dark me-1 mb-1" title="Tipos de Comprobantes"><i class="fas fa-file-invoice me-1"></i>Comprobantes</span>');
        }
        if (parseInt(item.permite_condiciones_pago, 10) === 1) {
            badges.push('<span class="badge bg-danger me-1 mb-1" title="Condiciones de Pago"><i class="fas fa-credit-card me-1"></i>Cond. Pago</span>');
        }
        if (parseInt(item.permite_sucursales_bocas, 10) === 1) {
            badges.push('<span class="badge bg-secondary me-1 mb-1" title="Sucursales y Bocas"><i class="fas fa-store me-1"></i>Sucursales/Bocas</span>');
        }
        if (parseInt(item.permite_listas_precios, 10) === 1) {
            badges.push('<span class="badge bg-dark me-1 mb-1" title="Listas de Precios"><i class="fas fa-list-ol me-1"></i>Listas Precios</span>');
        }
        if (parseInt(item.permite_parametros, 10) === 1) {
            badges.push('<span class="badge bg-light text-dark border me-1 mb-1" title="Parámetros Generales"><i class="fas fa-sliders-h me-1"></i>Parámetros</span>');
        }
        if (parseInt(item.permite_datos_basicos, 10) === 1) {
            badges.push('<span class="badge bg-success-subtle text-success border me-1 mb-1" title="Monedas, tipos de asiento, IVA, tipos y categorías de producto, categorías de gasto"><i class="fas fa-database me-1"></i>Datos Básicos</span>');
        }
        if (parseInt(item.permite_tesoreria, 10) === 1) {
            badges.push('<span class="badge bg-info-subtle text-info-emphasis border me-1 mb-1" title="Cajas, valores, cupones y medios de cobro y pago"><i class="fas fa-cash-register me-1"></i>Tesorería</span>');
        }
        if (parseInt(item.permite_impuestos_config, 10) === 1) {
            badges.push('<span class="badge bg-warning-subtle text-warning-emphasis border me-1 mb-1" title="Configuración de impuestos, IIBB, percepciones y retenciones"><i class="fas fa-percent me-1"></i>Impuestos</span>');
        }
        if (parseInt(item.permite_productos, 10) === 1) {
            badges.push('<span class="badge bg-primary-subtle text-primary-emphasis border me-1 mb-1" title="Catálogo de productos y servicios"><i class="fas fa-boxes me-1"></i>Productos</span>');
        }
        return badges.length > 0 ? badges.join('') : '<span class="badge bg-danger">Sin componentes</span>';
    }

    // Helper: Badge de vigencia
    function generarBadgeVigencia(item) {
        switch (item.vigencia_estado) {
            case 'Activa':
                return '<span class="badge bg-success-subtle text-success border border-success-subtle d-block mt-1">Vigente</span>';
            case 'Vencida':
                return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle d-block mt-1">Expirada</span>';
            case 'Pendiente':
                return '<span class="badge bg-warning-subtle text-warning border border-warning-subtle d-block mt-1">Futura</span>';
            default:
                return '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle d-block mt-1">Inactiva</span>';
        }
    }

    // Abrir modal nuevo
    function abrirModalNuevo() {
        $('#formAutorizacion')[0].reset();
        $('#aut_id').val('0');
        $('#modalAutorizacionLabel').html('<i class="fas fa-key text-primary me-2"></i>Otorgar Autorización a Otra Empresa');
        $('#aut_fecha_desde').val(new Date().toISOString().split('T')[0]);
        $('#aut_fecha_hasta').val('');
        $('#aut_tabla_estado_registro_id').val('1');
        $('.comp-chk').prop('checked', true);

        const selectDestino = $('#aut_empresa_destino_id');
        selectDestino.prop('disabled', false);

        // Si TODAS_EMPRESAS está disponible, actualizar select
        if (typeof TODAS_EMPRESAS !== 'undefined' && Array.isArray(TODAS_EMPRESAS)) {
            let opts = '<option value="">Seleccione la empresa destino...</option>';
            TODAS_EMPRESAS.forEach(function (emp) {
                if (parseInt(emp.empresa_id, 10) !== empresaActivaId) {
                    opts += `<option value="${emp.empresa_id}">${emp.empresa} (ID: ${emp.empresa_id}${emp.documento_numero ? ' - CUIT: ' + emp.documento_numero : ''})</option>`;
                }
            });
            selectDestino.html(opts);
        }

        getModal().show();
    }

    // Vincular botones en tabla otorgadas
    function vincularEventosTablaOtorgadas() {
        $('.btn-editar').off('click').on('click', function () {
            const id = $(this).attr('data-id');
            editarAutorizacion(id);
        });

        $('.btn-cambiar-estado').off('click').on('click', function () {
            const id = $(this).attr('data-id');
            const nuevoEstado = $(this).attr('data-estado');
            cambiarEstado(id, nuevoEstado);
        });

        $('.btn-eliminar').off('click').on('click', function () {
            const id = $(this).attr('data-id');
            eliminarAutorizacion(id);
        });
    }

    // Editar autorización
    function editarAutorizacion(id) {
        $.ajax({
            url: AJAX_URL,
            type: 'GET',
            data: {
                action: 'obtener_por_id',
                autorizacion_id: id,
                empresa_id: empresaActivaId
            },
            dataType: 'json',
            success: function (data) {
                if (!data.success) {
                    Swal.fire('Error', data.error || 'No se pudo cargar la autorización', 'error');
                    return;
                }

                const d = data.data;
                $('#aut_id').val(d.autorizacion_id);
                $('#modalAutorizacionLabel').html('<i class="fas fa-pencil-alt text-primary me-2"></i>Modificar Permisos de Autorización');
                $('#modalEmpresaOrigenNombre').text(`${d.empresa_origen_nombre} (ID: ${d.empresa_origen_id})`);

                const selectDestino = $('#aut_empresa_destino_id');
                // Asegurar que la opción exista
                if (selectDestino.find(`option[value="${d.empresa_destino_id}"]`).length === 0) {
                    selectDestino.append(`<option value="${d.empresa_destino_id}">${d.empresa_destino_nombre} (ID: ${d.empresa_destino_id})</option>`);
                }
                selectDestino.val(d.empresa_destino_id);
                selectDestino.prop('disabled', true);

                $('#chk_plan_cuentas').prop('checked', parseInt(d.permite_plan_cuentas, 10) === 1);
                $('#chk_conceptos_contables').prop('checked', parseInt(d.permite_conceptos_contables, 10) === 1);
                $('#chk_roles_cuentas').prop('checked', parseInt(d.permite_roles_cuentas, 10) === 1);
                $('#chk_comprobantes_tipos').prop('checked', parseInt(d.permite_comprobantes_tipos, 10) === 1);
                $('#chk_condiciones_pago').prop('checked', parseInt(d.permite_condiciones_pago, 10) === 1);
                $('#chk_sucursales_bocas').prop('checked', parseInt(d.permite_sucursales_bocas, 10) === 1);
                $('#chk_listas_precios').prop('checked', parseInt(d.permite_listas_precios, 10) === 1);
                $('#chk_parametros').prop('checked', parseInt(d.permite_parametros, 10) === 1);
                $('#chk_datos_basicos').prop('checked', parseInt(d.permite_datos_basicos, 10) === 1);
                $('#chk_tesoreria').prop('checked', parseInt(d.permite_tesoreria, 10) === 1);
                $('#chk_impuestos_config').prop('checked', parseInt(d.permite_impuestos_config, 10) === 1);
                $('#chk_productos').prop('checked', parseInt(d.permite_productos, 10) === 1);

                $('#aut_fecha_desde').val(d.fecha_desde || '');
                $('#aut_fecha_hasta').val(d.fecha_hasta || '');
                $('#aut_motivo').val(d.motivo || '');
                $('#aut_tabla_estado_registro_id').val(d.tabla_estado_registro_id);

                getModal().show();
            },
            error: function () {
                Swal.fire('Error', 'Error de comunicación al obtener la autorización', 'error');
            }
        });
    }

    // Guardar autorización
    function guardarAutorizacion() {
        const selectDestino = $('#aut_empresa_destino_id');
        const destinoId = selectDestino.val();

        if (!destinoId || parseInt(destinoId, 10) <= 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Atención',
                text: 'Debe seleccionar la empresa destino a autorizar'
            });
            return;
        }

        let totalComp = 0;
        $('.comp-chk:checked').each(function () {
            totalComp++;
        });

        if (totalComp === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Atención',
                text: 'Debe autorizar al menos un componente de información'
            });
            return;
        }

        const formData = new FormData($('#formAutorizacion')[0]);
        formData.append('action', 'guardar');
        formData.append('empresa_id', empresaActivaId);

        // Si estaba disabled, incluir en formData
        if (selectDestino.prop('disabled')) {
            formData.append('empresa_destino_id', destinoId);
        }

        const btnGuardar = $('#btnGuardarAutorizacion');
        btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: AJAX_URL,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (data) {
                btnGuardar.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Autorización');

                if (data.success) {
                    getModal().hide();
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: data.message || 'Autorización guardada correctamente',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    cargarOtorgadas();
                } else {
                    Swal.fire('Error', data.error || 'No se pudo guardar la autorización', 'error');
                }
            },
            error: function () {
                btnGuardar.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Autorización');
                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
            }
        });
    }

    // Cambiar estado (Activar / Revocar)
    function cambiarEstado(id, nuevoEstado) {
        const accionTxt = parseInt(nuevoEstado, 10) === 1 ? 'reactivar' : 'revocar';
        Swal.fire({
            title: `¿Desea ${accionTxt} esta autorización?`,
            text: parseInt(nuevoEstado, 10) === 2
                ? 'La empresa destino ya no podrá consultar ni copiar los datos de esta empresa.'
                : 'La empresa destino podrá volver a acceder a los componentes habilitados.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: `Sí, ${accionTxt}`,
            cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: AJAX_URL,
                    type: 'POST',
                    data: {
                        action: 'cambiar_estado',
                        autorizacion_id: id,
                        nuevo_estado_id: nuevoEstado,
                        empresa_id: empresaActivaId
                    },
                    dataType: 'json',
                    success: function (data) {
                        if (data.success) {
                            Swal.fire({ icon: 'success', title: 'Listo', text: data.message, timer: 1500, showConfirmButton: false });
                            cargarOtorgadas();
                        } else {
                            Swal.fire('Error', data.error || 'No se pudo modificar', 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
                    }
                });
            }
        });
    }

    // Eliminar autorización
    function eliminarAutorizacion(id) {
        Swal.fire({
            title: '¿Eliminar autorización?',
            text: 'Esta acción borrará definitivamente la autorización otorgada.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: AJAX_URL,
                    type: 'POST',
                    data: {
                        action: 'eliminar',
                        autorizacion_id: id,
                        empresa_id: empresaActivaId
                    },
                    dataType: 'json',
                    success: function (data) {
                        if (data.success) {
                            Swal.fire({ icon: 'success', title: 'Eliminada', text: data.message, timer: 1500, showConfirmButton: false });
                            cargarOtorgadas();
                        } else {
                            Swal.fire('Error', data.error || 'No se pudo eliminar', 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
                    }
                });
            }
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
    }

    // Carga inicial
    cargarTodo();
});
