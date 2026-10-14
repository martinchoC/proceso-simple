/**
 * modules/sindicatos/rendiciones.js
 * Interacción y controladores reactivos para Rendiciones y Cobranzas (Página 1031).
 */

document.addEventListener('DOMContentLoaded', function () {
    const URL_AJAX = 'rendiciones_ajax.php';
    let tablaRendiciones = null;
    let rendicionActualId = 0;

    const formatoMoneda = (val) => {
        return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(val || 0);
    };

    const badgeEstadoRendicion = (estadoId, nombre) => {
        let cls = 'bg-secondary';
        switch (parseInt(estadoId)) {
            case 8:  cls = 'bg-info text-dark'; break; // Recibido
            case 5:  cls = 'bg-primary'; break;        // Conciliado
            case 35: cls = 'bg-success'; break;        // Procesado
            case 27: cls = 'bg-danger'; break;         // Con Error
        }
        return `<span class="badge ${cls} px-2 py-1">${nombre || 'Sin estado'}</span>`;
    };

    function initDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaRendiciones')) {
            $('#tablaRendiciones').DataTable().destroy();
        }

        tablaRendiciones = $('#tablaRendiciones').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: URL_AJAX + '?accion=listar',
                dataSrc: 'data'
            },
            columns: [
                { data: 'rendicion_id', className: 'fw-bold' },
                {
                    data: null,
                    render: (row) => `
                        <div class="fw-semibold">${row.medio_nombre || '-'}</div>
                        <small class="text-muted font-monospace">${row.driver || ''}</small>
                    `
                },
                { data: 'f_rendicion' },
                {
                    data: 'archivo_nombre',
                    render: (d) => `<span class="small font-monospace">${d || '-'}</span>`
                },
                { data: 'total_registros', className: 'text-center fw-bold' },
                {
                    data: 'total_importe',
                    className: 'text-end fw-semibold text-primary',
                    render: (d) => formatoMoneda(d)
                },
                {
                    data: null,
                    render: (row) => badgeEstadoRendicion(row.tabla_estado_registro_id, row.estado_nombre)
                },
                {
                    data: null,
                    className: 'text-end',
                    orderable: false,
                    // Botones del motor de estados (página 1031), filtrados por estado y permisos en el servidor
                    render: (row) => row.acciones || ''
                }
            ],
            order: [[0, 'desc']]
        });
    }

    function cargarMediosPago() {
        fetch(URL_AJAX + '?accion=combo_medios_pago')
            .then(res => res.json())
            .then(res => {
                if (!res.success) return;
                const opts = res.data.map(m => `<option value="${m.medio_pago_id}">${m.nombre} (${m.driver})</option>`).join('');
                $('#subirMedioPagoId').append(opts);
            })
            .catch(err => console.error("Error al cargar medios de pago:", err));
    }

    $('#btnRecargarRendiciones').on('click', function () {
        if (tablaRendiciones) tablaRendiciones.ajax.reload();
    });

    // Subir rendiciones solo con la función "agregar" de la página
    fetch(URL_AJAX + '?accion=obtener_boton_agregar')
        .then(res => res.json())
        .then(res => { if (!res || !res.nombre_funcion) $('#btnSubirRendicion').addClass('d-none'); })
        .catch(() => $('#btnSubirRendicion').addClass('d-none'));

    // Abrir modal de subida
    $('#btnSubirRendicion').on('click', function () {
        $('#formSubirRendicion')[0].reset();
        const modal = new bootstrap.Modal(document.getElementById('modalSubirRendicion'));
        modal.show();
    });

    // Enviar archivo de rendición
    $('#formSubirRendicion').on('submit', function (e) {
        e.preventDefault();

        const medioId = $('#subirMedioPagoId').val();
        const fecha = $('#subirFechaRendicion').val();
        const archivo = $('#subirArchivo')[0].files[0];

        if (!medioId || !archivo) {
            alert("Debe seleccionar un canal y un archivo de rendición.");
            return;
        }

        const formData = new FormData();
        formData.append('accion', 'subir_archivo');
        formData.append('medio_pago_id', medioId);
        formData.append('f_rendicion', fecha);
        formData.append('archivo', archivo);

        const btn = $('#btnGuardarSubida');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Subiendo...');

        fetch(URL_AJAX, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                btn.prop('disabled', false).html('<i class="fas fa-cloud-upload-alt me-1"></i> Procesar Archivo');
                if (res.success) {
                    bootstrap.Modal.getInstance(document.getElementById('modalSubirRendicion')).hide();
                    if (tablaRendiciones) tablaRendiciones.ajax.reload();
                    // Abrir detalle para ver líneas
                    abrirDetalleRendicion(res.rendicion_id);
                } else {
                    alert(res.message || "Error al procesar el archivo.");
                }
            })
            .catch(err => {
                btn.prop('disabled', false).html('<i class="fas fa-cloud-upload-alt me-1"></i> Procesar Archivo');
                console.error("Error al subir archivo:", err);
                alert("Ocurrió un error al subir el archivo.");
            });
    });

    // Ver detalle de líneas
    $(document).on('click', '.btn-accion-ver', function () {
        const id = $(this).data('id');
        abrirDetalleRendicion(id);
    });

    function abrirDetalleRendicion(id) {
        rendicionActualId = id;
        fetch(URL_AJAX + `?accion=obtener&rendicion_id=${id}`)
            .then(res => res.json())
            .then(res => {
                if (!res.success || !res.rendicion) {
                    alert(res.message || "No se pudo cargar la rendición.");
                    return;
                }
                const r = res.rendicion;
                $('#detCanalNombre').text(r.medio_nombre + ' (' + r.driver + ')');
                $('#detArchivo').text(r.archivo_nombre || '-');
                $('#detFecha').text(r.f_rendicion);
                $('#detImporteTotal').text(formatoMoneda(r.total_importe));

                let html = '';
                (r.lineas || []).forEach(l => {
                    let badgeConc = '<span class="badge bg-secondary">Pendiente</span>';
                    if (l.resultado_conciliacion === 'OK') badgeConc = '<span class="badge bg-success">OK / Conciliado</span>';
                    else if (l.resultado_conciliacion === 'DIFERENCIA') badgeConc = '<span class="badge bg-warning text-dark">Diferencia</span>';
                    else if (l.resultado_conciliacion === 'SIN_VOLANTE') badgeConc = '<span class="badge bg-danger">Sin Volante</span>';

                    html += `
                        <tr>
                            <td class="text-muted small">${l.numero_linea}</td>
                            <td><span class="font-monospace small">${l.identificador_externo}</span></td>
                            <td><small>${l.f_pago}</small></td>
                            <td><span class="fw-bold font-monospace text-primary">${l.volante_numero || (l.volante_id ? 'VOL-' + l.volante_id : '-')}</span></td>
                            <td><small>${l.empleador_nombre || '-'}</small></td>
                            <td class="text-end fw-bold">${formatoMoneda(l.importe_rendido)}</td>
                            <td class="text-center">${badgeConc}</td>
                        </tr>
                    `;
                });

                $('#tbodyLineasRendicion').html(html || '<tr><td colspan="7" class="text-center py-3 text-muted">No hay líneas registradas</td></tr>');

                if (parseInt(r.tabla_estado_registro_id) === 35) {
                    $('#btnProcesarCobranzaModal').hide();
                } else {
                    $('#btnProcesarCobranzaModal').show();
                }

                const modal = new bootstrap.Modal(document.getElementById('modalDetalleRendicion'));
                modal.show();
            })
            .catch(err => {
                console.error("Error al obtener rendición:", err);
                alert("Error al cargar detalle de rendición.");
            });
    }

    // Procesar cobranza desde tabla
    $(document).on('click', '.btn-accion-procesar', function () {
        const id = $(this).data('id');
        procesarCobranza(id);
    });

    $('#btnProcesarCobranzaModal').on('click', function () {
        if (rendicionActualId > 0) {
            procesarCobranza(rendicionActualId);
        }
    });

    function procesarCobranza(id) {
        if (!confirm("¿Desea conciliar y procesar las cobranzas de esta rendición? Se emitirán los recibos de cobranza, las ND de intereses resarcitorios y se cancelarán las deudas correspondientes.")) {
            return;
        }

        const formData = new FormData();
        formData.append('accion', 'procesar');
        formData.append('rendicion_id', id);

        fetch(URL_AJAX, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    alert(res.message);
                    const modalEl = document.getElementById('modalDetalleRendicion');
                    const modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) modalInstance.hide();
                    if (tablaRendiciones) tablaRendiciones.ajax.reload();
                } else {
                    alert(res.message || "Error al procesar cobranzas.");
                }
            })
            .catch(err => {
                console.error("Error al procesar:", err);
                alert("Ocurrió un error al procesar las cobranzas.");
            });
    }

    initDataTable();
    cargarMediosPago();
});
