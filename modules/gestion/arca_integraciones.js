$(document).ready(function () {
    var empresa_idx = EMPRESA_ID;
    var pagina_idx = PAGINA_ID;
    var AJAX_URL = 'arca_integraciones_ajax.php';

    var tabla = null;
    var tablaResultados = null;
    var combos = null;
    var pruebaActual = null; // fila de la integración abierta en el modal de pruebas

    // ========== UTILIDADES ==========

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return String(text)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function formatoFecha(valor) {
        if (!valor) return '';
        var p = String(valor).split(/[- :]/);
        return p.length >= 5 ? p[2] + '/' + p[1] + '/' + p[0] + ' ' + p[3] + ':' + p[4] : valor;
    }

    function badgeAmbiente(codigo, nombre) {
        var clase = codigo === 'produccion' ? 'bg-danger' : 'bg-warning text-dark';
        return '<span class="badge ' + clase + '">' + escapeHtml(nombre || codigo) + '</span>';
    }

    function badgeResultado(resultado) {
        var clases = { OK: 'bg-success', ERROR: 'bg-danger', RECHAZO: 'bg-danger', TIMEOUT: 'bg-warning text-dark' };
        return '<span class="badge ' + (clases[resultado] || 'bg-secondary') + '">' + escapeHtml(resultado) + '</span>';
    }

    function ajaxPost(datos) {
        return $.ajax({ url: AJAX_URL, type: 'POST', dataType: 'json', data: $.extend({ empresa_idx: empresa_idx, pagina_idx: pagina_idx }, datos) });
    }

    function mensajeError(xhr) {
        return (xhr && xhr.responseJSON && xhr.responseJSON.message) || 'Error al comunicarse con el servidor';
    }

    // ========== CARGAS INICIALES ==========

    function cargarBotonAgregar() {
        $.get(AJAX_URL, { accion: 'obtener_boton_agregar', empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            var boton = res && res.data ? res.data : null;
            if (boton && boton.nombre_funcion) {
                var icono = boton.icono_clase ? '<i class="' + boton.icono_clase + ' me-1"></i>' : '';
                var colorClase = boton.bg_clase && boton.text_clase ? boton.bg_clase + ' ' + boton.text_clase : (boton.color_clase || 'btn-primary');
                $('#contenedor-boton-agregar').html('<button type="button" class="btn ' + colorClase + '" id="btnNuevo">' + icono + escapeHtml(boton.nombre_funcion) + '</button>');
            }
        }, 'json');
    }

    function cargarFiltroEstado(callback) {
        $.get(AJAX_URL, { accion: 'obtener_estados', empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            var $select = $('#filtroEstado');
            $select.empty().append('<option value="">Todos los estados</option>');
            ((res && res.data) || []).forEach(function (estado) {
                $select.append('<option value="' + estado.estado_registro_id + '">' + escapeHtml(estado.estado_registro) + '</option>');
            });
            $select.val('1');
            if (typeof callback === 'function') callback();
        }, 'json').fail(function () {
            if (typeof callback === 'function') callback();
        });
    }

    function cargarCombos(callback) {
        if (combos !== null) {
            if (typeof callback === 'function') callback();
            return;
        }
        $.get(AJAX_URL, { accion: 'obtener_combos', empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            combos = (res && res.data) || { ambientes: [], archivos: { certificados: [], claves: [] }, puntos_venta: [], comprobantes: [] };

            var $amb = $('#integracion_ambiente_id').empty().append('<option value="">Seleccionar ambiente</option>');
            combos.ambientes.forEach(function (a) {
                $amb.append('<option value="' + a.integracion_ambiente_id + '">' + escapeHtml(a.integracion_nombre + ' - ' + a.nombre) + '</option>');
            });

            $('#listaCertificados').html(combos.archivos.certificados.map(function (r) { return '<option value="' + escapeHtml(r) + '">'; }).join(''));
            $('#listaClaves').html(combos.archivos.claves.map(function (r) { return '<option value="' + escapeHtml(r) + '">'; }).join(''));
            $('#listaPuntosVenta').html(combos.puntos_venta.map(function (p) {
                var sis = p.sistema_emision ? ' [' + p.sistema_emision + ']' : '';
                return '<option value="' + p.codigo_fiscal + '">' + escapeHtml(p.nombre + sis) + '</option>';
            }).join(''));

            var $tipo = $('#prueba_cbte_tipo').empty();
            combos.comprobantes.forEach(function (c) {
                $tipo.append('<option value="' + c.codigo + '" data-cae="' + c.permite_cae + '">' + c.codigo + ' - ' + escapeHtml(c.comprobante_fiscal) + '</option>');
            });
            $tipo.val('11');

            if (typeof callback === 'function') callback();
        }, 'json').fail(function (xhr) {
            Swal.fire({ icon: 'error', title: 'Error', text: mensajeError(xhr) });
        });
    }

    // ========== DATATABLES ==========

    function inicializarDataTable() {
        tabla = $('#tablaIntegraciones').DataTable({
            ajax: {
                url: AJAX_URL,
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                    d.filtro_estado = $('#filtroEstado').val();
                },
                dataSrc: function (res) {
                    if (!res || !res.success) {
                        Swal.fire({ icon: 'error', title: 'Error', text: (res && res.message) || 'Error al cargar las integraciones' });
                        return [];
                    }
                    return res.data || [];
                },
                error: function (xhr) {
                    Swal.fire({ icon: 'error', title: 'Error', text: mensajeError(xhr) });
                }
            },
            dom: '<"row"<"col-sm-12"tr>>',
            paging: false,
            columns: [
                { data: 'integracion_nombre', render: function (d) { return escapeHtml(d); } },
                {
                    data: 'ambiente_nombre', className: 'text-center', render: function (d, type, row) {
                        return type === 'display' ? badgeAmbiente(row.ambiente_codigo, d) : d;
                    }
                },
                { data: 'servicio', render: function (d) { return escapeHtml(d); } },
                { data: 'cuit', render: function (d) { return escapeHtml(d); } },
                {
                    data: 'certificado_ruta', render: function (d, type, row) {
                        if (type !== 'display') return d;
                        var c = row.certificado || {};
                        var estado = !c.ok ? '<span class="badge bg-danger">' + escapeHtml(c.error || 'No encontrado') + '</span>'
                            : c.vencido ? '<span class="badge bg-danger">Vencido ' + formatoFecha(c.valido_hasta) + '</span>'
                                : '<span class="badge bg-light text-dark border">Vence ' + formatoFecha(c.valido_hasta) + '</span>';
                        return '<div class="small text-truncate" style="max-width: 320px;" title="' + escapeHtml(d) + '">' + escapeHtml(d) + '</div>' + estado;
                    }
                },
                {
                    data: 'ta_vencimiento', className: 'text-center', render: function (d, type, row) {
                        if (type !== 'display') return d || '';
                        return row.ta_vigente == 1
                            ? '<span class="badge bg-success">Vigente hasta ' + formatoFecha(d) + '</span>'
                            : '<span class="badge bg-secondary">Sin TA vigente</span>';
                    }
                },
                {
                    data: 'estado_registro', className: 'text-center', render: function (d, type, row) {
                        if (type !== 'display') return d;
                        var clase = row.tabla_estado_registro_id == 1 ? 'bg-success' : 'bg-secondary';
                        return '<span class="badge ' + clase + '">' + escapeHtml(d) + '</span>';
                    }
                },
                {
                    data: 'botones', orderable: false, searchable: false, className: 'text-center',
                    render: function (data, type, row) {
                        if (type !== 'display' || !data) return '';
                        return data.map(function (boton) {
                            var nombre = boton.accion_js || boton.nombre_funcion.toLowerCase();
                            var clase = 'btn-xs me-1 ';
                            clase += nombre === 'editar' ? 'btn-outline-primary' :
                                nombre === 'pruebas' ? 'btn-outline-info' :
                                (nombre === 'inactivar' || nombre === 'eliminar' || nombre === 'baja') ? 'btn-outline-danger' :
                                (nombre === 'habilitar' || nombre === 'activar' || nombre === 'alta') ? 'btn-outline-success' : 'btn-outline-secondary';
                            return '<button type="button" class="btn ' + clase + ' btn-accion"' +
                                ' title="' + escapeHtml(boton.descripcion || boton.nombre_funcion) + '"' +
                                ' data-id="' + row.integracion_empresa_id + '"' +
                                ' data-accion="' + escapeHtml(boton.accion_js) + '"' +
                                ' data-nombre-funcion="' + escapeHtml(boton.nombre_funcion) + '"' +
                                ' data-confirmable="' + (boton.es_confirmable || 0) + '">' +
                                '<i class="' + (boton.icono_clase || 'fas fa-cog') + '"></i></button>';
                        }).join('') || '<span class="text-muted small">-</span>';
                    }
                }
            ],
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
            order: [[0, 'asc'], [1, 'asc']],
            responsive: true,
            createdRow: function (row, data) {
                if (data.tabla_estado_registro_id != 1) $(row).addClass('table-secondary');
            }
        });
    }

    function inicializarTablaResultados() {
        tablaResultados = $('#tablaResultados').DataTable({
            ajax: {
                url: AJAX_URL,
                type: 'GET',
                data: function (d) {
                    d.accion = 'listar_resultados';
                    d.empresa_idx = empresa_idx;
                    d.pagina_idx = pagina_idx;
                },
                dataSrc: function (res) { return (res && res.success && res.data) || []; }
            },
            pageLength: 10,
            columns: [
                { data: 'integracion_resultado_id' },
                { data: 'fecha_hora_solicitud', render: function (d, type) { return type === 'display' ? formatoFecha(d) : d; } },
                {
                    data: 'ambiente_nombre', render: function (d, type, row) {
                        return type === 'display' && d ? badgeAmbiente(row.ambiente_codigo, d) : (d || '');
                    }
                },
                { data: 'operacion_nombre', render: function (d, type, row) { return escapeHtml(d || row.operacion_codigo || '-'); } },
                { data: 'resultado', className: 'text-center', render: function (d, type) { return type === 'display' ? badgeResultado(d) : d; } },
                { data: 'codigo', render: function (d) { return '<span class="small">' + escapeHtml(d) + '</span>'; } },
                { data: 'descripcion', render: function (d) { return '<span class="small">' + escapeHtml(d) + '</span>'; } },
                {
                    data: null, orderable: false, searchable: false, className: 'text-center',
                    render: function (d, type, row) {
                        return '<button type="button" class="btn btn-xs btn-outline-secondary btn-ver-resultado" data-id="' +
                            row.integracion_resultado_id + '" title="Ver solicitud / respuesta"><i class="fas fa-eye"></i></button>';
                    }
                }
            ],
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
            order: [[0, 'desc']]
        });
    }

    function recargarTodo() {
        if (tabla) tabla.ajax.reload(null, false);
        if (tablaResultados) tablaResultados.ajax.reload(null, false);
    }

    // ========== ACCIONES DE ESTADO ==========

    function ejecutarAccion(id, accionJs, nombreFuncion, esConfirmable) {
        if (esConfirmable != 1) {
            enviarAccionBackend(id, accionJs, nombreFuncion);
            return;
        }
        Swal.fire({
            title: '¿' + nombreFuncion + '?',
            html: '¿Está seguro de <strong>' + escapeHtml(nombreFuncion.toLowerCase()) + '</strong> esta integración?',
            icon: 'question', showCancelButton: true, confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33', confirmButtonText: 'Sí, ' + nombreFuncion.toLowerCase(), cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (result.isConfirmed) enviarAccionBackend(id, accionJs, nombreFuncion);
        });
    }

    function enviarAccionBackend(id, accionJs, nombreFuncion) {
        ajaxPost({ accion: 'ejecutar_accion', integracion_empresa_id: id, accion_js: accionJs }).done(function (res) {
            if (res.success) {
                tabla.ajax.reload(function () {
                    Swal.fire({ icon: 'success', title: '¡' + nombreFuncion + '!', text: res.message, showConfirmButton: false, timer: 1500, toast: true, position: 'top-end' });
                }, false);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Error al ejecutar la acción' });
            }
        }).fail(function (xhr) {
            Swal.fire({ icon: 'error', title: 'Error', text: mensajeError(xhr) });
        });
    }

    // ========== MODAL ALTA / EDICIÓN ==========

    function resetModal() {
        var form = document.getElementById('formIntegracion');
        form.reset();
        form.classList.remove('was-validated');
        $('#integracion_empresa_id').val('');
        $('#servicio').val('wsfe');
    }

    function mostrarModalNuevo() {
        cargarCombos(function () {
            resetModal();
            if (combos.ambientes.length === 1) $('#integracion_ambiente_id').val(combos.ambientes[0].integracion_ambiente_id);
            $('#modalLabel').html('<i class="fas fa-plug me-2"></i>Nueva Integración');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalIntegracion')).show();
        });
    }

    function cargarParaEditar(id) {
        cargarCombos(function () {
            $.get(AJAX_URL, { accion: 'obtener', integracion_empresa_id: id, empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
                if (!res || !res.success || !res.data) {
                    Swal.fire({ icon: 'error', title: 'Error', text: (res && res.message) || 'Error al obtener la integración' });
                    return;
                }
                var r = res.data;
                resetModal();
                $('#integracion_empresa_id').val(r.integracion_empresa_id);
                if (!$('#integracion_ambiente_id option[value="' + r.integracion_ambiente_id + '"]').length) {
                    // Ambiente desactivado después del alta: se muestra igual, el backend exige uno activo al guardar.
                    $('#integracion_ambiente_id').append('<option value="' + r.integracion_ambiente_id + '">' + escapeHtml(r.ambiente_nombre) + ' (inactivo)</option>');
                }
                $('#integracion_ambiente_id').val(r.integracion_ambiente_id);
                $('#servicio').val(r.servicio);
                $('#cuit').val(r.cuit);
                $('#certificado_ruta').val(r.certificado_ruta);
                $('#clave_privada_ruta').val(r.clave_privada_ruta);
                $('#observaciones').val(r.observaciones || '');
                $('#modalLabel').html('<i class="fas fa-plug me-2"></i>Editar Integración');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalIntegracion')).show();
            }, 'json').fail(function (xhr) {
                Swal.fire({ icon: 'error', title: 'Error', text: mensajeError(xhr) });
            });
        });
    }

    function guardar() {
        var form = document.getElementById('formIntegracion');
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }

        var id = $('#integracion_empresa_id').val();
        var $btn = $('#btnGuardar').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        ajaxPost({
            accion: id ? 'editar' : 'agregar',
            integracion_empresa_id: id,
            integracion_ambiente_id: $('#integracion_ambiente_id').val(),
            servicio: $('#servicio').val().trim(),
            cuit: $('#cuit').val().trim(),
            certificado_ruta: $('#certificado_ruta').val().trim(),
            clave_privada_ruta: $('#clave_privada_ruta').val().trim(),
            observaciones: $('#observaciones').val().trim()
        }).done(function (res) {
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalIntegracion')).hide();
                tabla.ajax.reload(function () {
                    Swal.fire({ icon: 'success', title: '¡Guardado!', text: res.message, showConfirmButton: false, timer: 1500, toast: true, position: 'top-end' });
                }, false);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Error al guardar los datos' });
            }
        }).fail(function (xhr) {
            Swal.fire({ icon: 'error', title: 'Error', text: mensajeError(xhr) });
        }).always(function () {
            $btn.prop('disabled', false).html('Guardar');
        });
    }

    // ========== MODAL DE PRUEBAS ==========

    function abrirPruebas(id) {
        var fila = null;
        tabla.rows().every(function () {
            if (this.data().integracion_empresa_id == id) fila = this.data();
        });
        if (!fila) return;

        cargarCombos(function () {
            pruebaActual = fila;
            $('#modalPruebasLabel').html('<i class="fas fa-wrench me-2"></i>Pruebas ARCA — ' +
                escapeHtml(fila.ambiente_nombre) + ' · CUIT ' + escapeHtml(fila.cuit) + ' · ' + escapeHtml(fila.servicio));
            var esHomologacion = fila.ambiente_codigo === 'homologacion';
            $('#seccionCae').toggleClass('opacity-50', !esHomologacion).find('input, select, button').prop('disabled', !esHomologacion);
            if (esHomologacion) actualizarDocReceptor();
            $('#pruebaResultado').html('<span class="text-muted">Ejecute una operación para ver el resultado.</span>');
            actualizarEstadoTa();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPruebas')).show();
        });
    }

    function actualizarEstadoTa() {
        $('#pruebaEstadoTa').text('consultando...');
        ajaxPost({ accion: 'prueba', operacion: 'estado_ta', integracion_empresa_id: pruebaActual.integracion_empresa_id })
            .done(function (res) {
                var ta = res && res.data;
                $('#pruebaEstadoTa').html(ta && ta.vigente
                    ? '<span class="text-success">vigente hasta ' + formatoFecha(ta.vencimiento) + '</span>'
                    : '<span class="text-secondary">sin TA vigente (se pedirá a WSAA en la próxima operación)</span>');
            })
            .fail(function () { $('#pruebaEstadoTa').text('-'); });
    }

    function actualizarDocReceptor() {
        var esCf = $('#cae_doc_tipo').val() === '99';
        $('#cae_doc_nro').prop('disabled', esCf).val(esCf ? '' : $('#cae_doc_nro').val());
    }

    function tablaClaveValor(obj) {
        var html = '<table class="table table-sm table-bordered mb-2">';
        Object.keys(obj).forEach(function (k) {
            var v = obj[k];
            if (Array.isArray(v) || (v !== null && typeof v === 'object')) return;
            html += '<tr><th class="w-50">' + escapeHtml(k) + '</th><td>' + escapeHtml(v === '' || v === null ? '-' : v) + '</td></tr>';
        });
        return html + '</table>';
    }

    function listaMensajes(titulo, clase, items) {
        if (!items || !items.length) return '';
        return '<div class="alert ' + clase + ' py-1 px-2 mb-2"><strong>' + titulo + '</strong><ul class="mb-0 ps-3">' +
            items.map(function (e) { return '<li>' + escapeHtml(e.codigo) + ': ' + escapeHtml(e.mensaje) + '</li>'; }).join('') +
            '</ul></div>';
    }

    function mostrarResultadoPrueba(operacion, res) {
        var data = (res && res.data) || {};
        var html = '<div class="mb-2"><span class="badge bg-dark me-1">' + escapeHtml(operacion) + '</span>' +
            (res.success ? '<span class="badge bg-success">OK</span>' : (data.timeout ? '<span class="badge bg-warning text-dark">TIMEOUT</span>' : '<span class="badge bg-danger">ERROR</span>')) +
            ' <span class="text-muted">' + new Date().toLocaleTimeString() + '</span></div>';

        if (res.message) {
            html += '<div class="alert ' + (res.success ? 'alert-success' : (data.timeout ? 'alert-warning' : 'alert-danger')) + ' py-1 px-2 mb-2">' + escapeHtml(res.message) + '</div>';
        }

        var resultado = data.resultado !== undefined ? data.resultado : data;
        if (resultado && typeof resultado === 'object') {
            if (resultado.puntos_venta) {
                html += '<div class="alert alert-info py-1 px-2 small mb-2"><i class="fas fa-info-circle me-1"></i><strong>Puntos de Venta Web Service:</strong> ARCA solo lista aquí los puntos de venta habilitados para Factura Electrónica (Web Service). Puntos de venta creados para Comprobantes en Línea (RCEL), Controladores Fiscales o Talonarios tienen numeración independiente y no figuran para Web Service.</div>';
                html += resultado.puntos_venta.length
                    ? '<table class="table table-sm table-bordered mb-2"><tr><th>Nro</th><th>Emisión</th><th>Bloqueado</th><th>Baja</th></tr>' +
                    resultado.puntos_venta.map(function (p) {
                        return '<tr><td>' + escapeHtml(p.Nro) + '</td><td>' + escapeHtml(p.EmisionTipo) + '</td><td>' + escapeHtml(p.Bloqueado) + '</td><td>' + escapeHtml(p.FchBaja || '-') + '</td></tr>';
                    }).join('') + '</table>'
                    : '<div class="text-muted mb-2">Sin puntos de venta informados en ARCA para Web Service.</div>';
            } else {
                html += tablaClaveValor(resultado);
            }
            html += listaMensajes('Observaciones', 'alert-warning', resultado.observaciones);
        }
        html += listaMensajes('Errores ARCA', 'alert-danger', data.errores);
        html += listaMensajes('Eventos ARCA', 'alert-info', data.eventos);

        $('#pruebaResultado').html(html);

        // Autocompletar el número de la consulta con el último autorizado / el recién emitido.
        if (resultado && resultado.CbteNro) $('#prueba_cbte_nro').val(resultado.CbteNro);
    }

    function ejecutarPrueba(operacion, $btn) {
        var datos = {
            accion: 'prueba',
            operacion: operacion,
            integracion_empresa_id: pruebaActual.integracion_empresa_id,
            pto_vta: $('#prueba_pto_vta').val(),
            cbte_tipo: $('#prueba_cbte_tipo').val(),
            cbte_nro: $('#prueba_cbte_nro').val()
        };
        if (operacion === 'cae_solicitar') {
            $.extend(datos, {
                doc_tipo: $('#cae_doc_tipo').val(),
                doc_nro: $('#cae_doc_nro').val(),
                condicion_iva_receptor_id: $('#cae_condicion_iva').val(),
                imp_total: $('#cae_imp_total').val()
            });
        }

        var textoOriginal = $btn.html();
        $('.btn-prueba').prop('disabled', true);
        $btn.html('<i class="fas fa-spinner fa-spin me-1"></i>' + textoOriginal);
        $('#pruebaResultado').html('<span class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i>Consultando a ARCA...</span>');

        ajaxPost(datos).done(function (res) {
            mostrarResultadoPrueba(operacion, res || {});
        }).fail(function (xhr) {
            mostrarResultadoPrueba(operacion, { success: false, message: mensajeError(xhr) });
        }).always(function () {
            $btn.html(textoOriginal);
            $('.btn-prueba').prop('disabled', false);
            if (pruebaActual.ambiente_codigo !== 'homologacion') $('#seccionCae button').prop('disabled', true);
            actualizarEstadoTa();
            recargarTodo();
        });
    }

    // ========== DETALLE DE LLAMADA ==========

    function verResultado(id) {
        $.get(AJAX_URL, { accion: 'obtener_resultado', integracion_resultado_id: id, empresa_idx: empresa_idx, pagina_idx: pagina_idx }, function (res) {
            if (!res || !res.success) {
                Swal.fire({ icon: 'error', title: 'Error', text: (res && res.message) || 'No se pudo obtener el detalle' });
                return;
            }
            var r = res.data;
            $('#modalResultadoLabel').text('Llamada #' + r.integracion_resultado_id + ' — ' + (r.operacion_nombre || ''));
            $('#detalleResultado').html(
                '<div class="mb-2">' + badgeResultado(r.resultado) + ' <span class="small">' + escapeHtml(r.ambiente_nombre || '') +
                ' · solicitud ' + formatoFecha(r.fecha_hora_solicitud) + ' · respuesta ' + (formatoFecha(r.fecha_hora_respuesta) || '-') +
                ' · intento ' + r.intento + '</span></div>' +
                '<div class="small mb-2"><strong>' + escapeHtml(r.codigo) + '</strong> ' + escapeHtml(r.descripcion) + '</div>' +
                '<h6>Solicitud</h6><pre class="xml-tecnico border rounded p-2 bg-light">' + escapeHtml(r.solicitud || '(vacío)') + '</pre>' +
                '<h6>Respuesta</h6><pre class="xml-tecnico border rounded p-2 bg-light">' + escapeHtml(r.respuesta || '(vacío)') + '</pre>'
            );
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalResultado')).show();
        }, 'json').fail(function (xhr) {
            Swal.fire({ icon: 'error', title: 'Error', text: mensajeError(xhr) });
        });
    }

    // ========== EVENTOS ==========

    $(document).on('click', '#btnNuevo', mostrarModalNuevo);

    $(document).on('click', '.btn-accion', function () {
        var $b = $(this);
        var id = $b.data('id');
        var accionJs = $b.data('accion');
        if (accionJs === 'editar') {
            cargarParaEditar(id);
            return;
        }
        if (accionJs === 'pruebas') {
            abrirPruebas(id);
            return;
        }
        ejecutarAccion(id, accionJs, $b.data('nombre-funcion') || accionJs, $b.data('confirmable'));
    });

    $('#btnGuardar').on('click', guardar);
    $('#formIntegracion').on('submit', function (e) {
        e.preventDefault();
        guardar();
    });

    $(document).on('click', '.btn-prueba', function () {
        var $btn = $(this);
        var operacion = $btn.data('operacion');
        if (operacion !== 'cae_solicitar') {
            ejecutarPrueba(operacion, $btn);
            return;
        }
        var $tipo = $('#prueba_cbte_tipo option:selected');
        if ($tipo.data('cae') != 1) {
            Swal.fire({ icon: 'warning', title: 'Tipo no admitido', text: 'La prueba de CAE admite solo Factura A (1), B (6) o C (11).' });
            return;
        }
        Swal.fire({
            title: '¿Solicitar CAE de prueba?',
            html: 'Se emitirá en <strong>Homologación</strong> el siguiente número de <strong>' + escapeHtml($tipo.text()) +
                '</strong>, punto de venta <strong>' + escapeHtml($('#prueba_pto_vta').val()) + '</strong>, por $' + escapeHtml($('#cae_imp_total').val()) + '.',
            icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, solicitar', cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (result.isConfirmed) ejecutarPrueba(operacion, $btn);
        });
    });

    $('#cae_doc_tipo').on('change', actualizarDocReceptor);

    $(document).on('click', '.btn-ver-resultado', function () {
        verResultado($(this).data('id'));
    });

    $('#filtroEstado').on('change', function () {
        tabla.ajax.reload();
    });

    $('#btnRecargar').on('click', function () {
        var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        tabla.ajax.reload(function () { $btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>'); }, false);
    });

    $('#btnRecargarResultados').on('click', function () {
        tablaResultados.ajax.reload(null, false);
    });

    // ========== INICIALIZACIÓN ==========

    cargarBotonAgregar();
    cargarFiltroEstado(inicializarDataTable);
    inicializarTablaResultados();
});
