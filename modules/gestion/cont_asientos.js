$(document).ready(function () {
    var tabla;
    var tablaDetalles;
    var asientoActualId = null;
    
    // ========== FUNCIONES DE DATATABLE ==========
    function inicializarDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaContAsientos')) {
            $('#tablaContAsientos').DataTable().destroy();
            $('#tablaContAsientos tbody').empty();
        }

        tabla = $('#tablaContAsientos').DataTable({
            ajax: {
                url: 'cont_asientos_ajax.php',
                type: 'GET',
                data: {
                    accion: 'listar_asientos',
                    empresa_idx: empresa_id,
                    pagina_id: pagina_id
                },
                dataSrc: ''
            },
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>' +
                '<"clear">',
            pageLength: 50,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            columns: [
                { data: 'cont_asiento_id', className: 'text-center fw-bold', width: '50px' },
                { data: 'numero_asiento', width: '120px' },
                { 
                    data: 'f_asiento',
                    width: '100px',
                    className: 'text-center',
                    render: function(data) {
                        if (!data) return '-';
                        var parts = data.split('-');
                        return parts.length === 3 ? parts[2] + '/' + parts[1] + '/' + parts[0] : data;
                    }
                },
                { data: 'descripcion', width: '150px', render: function(data) { return data ? data.substring(0, 50) : '-'; } },
                { data: 'moneda_nombre', defaultContent: '-', width: '100px' },
                { 
                    data: 'total_debe',
                    className: 'text-end',
                    width: '120px',
                    render: function(data) { return formatNumber(data, 2); }
                },
                { 
                    data: 'total_haber',
                    className: 'text-end',
                    width: '120px',
                    render: function(data) { return formatNumber(data, 2); }
                },
                { 
                    data: 'estado_html',
                    className: 'text-center',
                    width: '100px'
                },
                { 
                    data: 'botones',
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    width: '150px',
                    render: function(data, type, row) {
                        if (type === 'export') return '';
                        if (!data || data.length === 0) return '<span class="text-muted small">-</span>';
                        
                        var botonesHtml = '';
                        data.forEach(function(boton) {
                            var colorClase = '';
                            if (boton.bg_clase && boton.text_clase) {
                                colorClase = boton.bg_clase + ' ' + boton.text_clase;
                            } else if (boton.color_clase) {
                                colorClase = boton.color_clase;
                            } else {
                                colorClase = 'btn-outline-primary';
                            }
                            
                            var icono = boton.icono_clase ? `<i class="${boton.icono_clase}"></i>` : '';
                            var titulo = boton.descripcion || boton.nombre_funcion;
                            var accion = boton.accion_js;
                            
                            botonesHtml += `<button type="button" class="btn btn-sm ${colorClase} me-1 btn-accion-asiento" 
                                                    title="${titulo}" 
                                                    data-id="${row.cont_asiento_id}" 
                                                    data-accion="${accion}"
                                                    data-numero="${escapeHtml(row.numero_asiento)}"
                                                    data-confirmable="${boton.es_confirmable || 0}">
                                                ${icono}
                                            </button>`;
                        });
                        
                        return `<div class="btn-group" role="group">${botonesHtml}</div>`;
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json'                
            },
            responsive: true
        });
        
        inicializarEventos();
    }
    
    function inicializarDataTableDetalles(asientoId) {
        if ($.fn.DataTable.isDataTable('#tablaContAsientosDetalles')) {
            $('#tablaContAsientosDetalles').DataTable().destroy();
            $('#tablaContAsientosDetalles tbody').empty();
        }

        tablaDetalles = $('#tablaContAsientosDetalles').DataTable({
            ajax: {
                url: 'cont_asientos_ajax.php',
                type: 'GET',
                data: {
                    accion: 'listar_detalles',
                    empresa_idx: empresa_id,
                    cont_asiento_id: asientoId
                },
                dataSrc: ''
            },
            dom: '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            columns: [
                { data: 'id', className: 'text-center' },
                { data: 'cuenta_nombre_completo', defaultContent: '-' },
                { 
                    data: 'importe_local_debe',
                    className: 'text-end',
                    render: function(data) { return formatNumber(data, 2); }
                },
                { 
                    data: 'importe_local_haber',
                    className: 'text-end',
                    render: function(data) { return formatNumber(data, 2); }
                },
                { data: 'descripcion', defaultContent: '-' },
                { 
                    data: 'tipo',
                    className: 'text-center',
                    render: function(data) {
                        if (data === 'A') {
                            return '<span class="badge bg-secondary">Automático</span>';
                        } else {
                            return '<span class="badge bg-success">Manual</span>';
                        }
                    }
                },
                { 
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    render: function(data, type, row) {
                        if (row.tipo === 'M') {
                            return `<div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-warning me-1 btn-editar-detalle" 
                                                title="Editar" data-cuenta-id="${row.cuenta_id}" data-asiento="${asientoId}">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        <button type="button" class="btn btn-sm btn-danger btn-eliminar-detalle" 
                                                title="Eliminar" data-cuenta-id="${row.cuenta_id}" data-asiento="${asientoId}">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                    </div>`;
                        } else {
                            return `<span class="badge bg-secondary" title="Línea generada automáticamente - No editable">
                                        <i class="fas fa-lock"></i> Automático
                                    </span>`;
                        }
                    }
                }
            ],
            drawCallback: function() {
                actualizarTotales(asientoId);
            },
            language: {
                url: '//cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json'                
            },
            responsive: true
        });
    }
    
    function actualizarTotales(asientoId) {
        $.get('cont_asientos_ajax.php', {
            accion: 'obtener_totales',
            cont_asiento_id: asientoId,
            empresa_idx: empresa_id
        }, function(res) {
            if (res) {
                $('#total_debe_mostrar').text(formatNumber(res.total_debe, 2));
                $('#total_haber_mostrar').text(formatNumber(res.total_haber, 2));
                $('#total_debe_foot').text(formatNumber(res.total_debe, 2));
                $('#total_haber_foot').text(formatNumber(res.total_haber, 2));
                
                var diferencia = res.total_debe - res.total_haber;
                var diferenciaSpan = $('#diferencia_mostrar');
                diferenciaSpan.text(formatNumber(diferencia, 2));
                if (Math.abs(diferencia) < 0.01) {
                    diferenciaSpan.removeClass('text-danger').addClass('text-success');
                } else {
                    diferenciaSpan.removeClass('text-success').addClass('text-danger');
                }
            }
        }, 'json');
    }
    
    function inicializarEventos() {
        $('#btnRecargar').off('click').on('click', function () {
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            tabla.ajax.reload(function () {
                btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
            }, false);
        });
        
        $('#btnExportarExcel').click(function() {
            tabla.button('.buttons-excel').trigger();
        });
        $('#btnExportarPDF').click(function() {
            tabla.button('.buttons-pdf').trigger();
        });
        $('#btnExportarCSV').click(function() {
            tabla.button('.buttons-csv').trigger();
        });
        $('#btnExportarPrint').click(function() {
            tabla.button('.buttons-print').trigger();
        });
    }
    
    function cargarBotonAgregar() {
        $.get('cont_asientos_ajax.php', {
            accion: 'obtener_boton_agregar',
            pagina_idx: pagina_id
        }, function(botonAgregar) {
            if (botonAgregar && botonAgregar.nombre_funcion) {
                var icono = botonAgregar.icono_clase ? `<i class="${botonAgregar.icono_clase} me-1"></i>` : '';
                var colorClase = '';
                
                if (botonAgregar.bg_clase && botonAgregar.text_clase) {
                    colorClase = botonAgregar.bg_clase + ' ' + botonAgregar.text_clase;
                } else if (botonAgregar.color_clase) {
                    colorClase = botonAgregar.color_clase;
                } else {
                    colorClase = 'btn-primary';
                }
                
                $('#contenedor-boton-agregar').html(
                    `<button type="button" class="btn ${colorClase}" id="btnNuevoAsiento">
                        ${icono}${botonAgregar.nombre_funcion}
                    </button>`
                );
            } else {
                $('#contenedor-boton-agregar').html(
                    '<button type="button" class="btn btn-primary" id="btnNuevoAsiento">' +
                    '<i class="fas fa-plus me-1"></i>Nuevo Asiento</button>'
                );
            }
        }, 'json');
    }
    
    function cargarCombosFormulario() {
        $.get('cont_asientos_ajax.php', { 
            accion: 'obtener_comprobantes',
            empresa_idx: empresa_id
        }, function(data) {
            var options = '<option value="">Sin comprobante</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.comprobante_id}">${escapeHtml(item.comprobante_nombre_completo)}</option>`;
                });
            }
            $('#comprobante_id').html(options);
        }, 'json');
        
        $.get('cont_asientos_ajax.php', { 
            accion: 'obtener_sucursales',
            empresa_idx: empresa_id
        }, function(data) {
            var options = '<option value="">Seleccionar sucursal</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.sucursal_id}">${escapeHtml(item.sucursal_nombre)}</option>`;
                });
            }
            $('#sucursal_id').html(options);
        }, 'json');
        
        $.get('cont_asientos_ajax.php', { 
            accion: 'obtener_depositos',
            empresa_idx: empresa_id
        }, function(data) {
            var options = '<option value="">Seleccionar depósito</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.deposito_id}">${escapeHtml(item.deposito_nombre)}</option>`;
                });
            }
            $('#deposito_id').html(options);
        }, 'json');
        
        $.get('cont_asientos_ajax.php', { 
            accion: 'obtener_monedas',
            empresa_idx: empresa_id
        }, function(data) {
            var options = '<option value="">Seleccionar moneda</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    var selected = item.es_moneda_base == 1 ? 'selected' : '';
                    options += `<option value="${item.moneda_id}" ${selected}>${escapeHtml(item.moneda)} (${escapeHtml(item.simbolo)})</option>`;
                });
            }
            $('#moneda_id').html(options);
        }, 'json');
        
        $.get('cont_asientos_ajax.php', { 
            accion: 'obtener_entidades',
            empresa_idx: empresa_id
        }, function(data) {
            var options = '<option value="">Seleccionar entidad</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.entidad_id}">${escapeHtml(item.entidad_nombre)} (${escapeHtml(item.entidad_nro_documento)})</option>`;
                });
            }
            $('#entidad_id').html(options);
        }, 'json');
    }
    
    function cargarCombosDetalle() {
        $.get('cont_asientos_ajax.php', { 
            accion: 'obtener_cuentas',
            empresa_idx: empresa_id
        }, function(data) {
            var options = '<option value="">Seleccionar cuenta</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.cont_cuenta_id}" data-naturaleza="${item.naturaleza}">
                                    ${escapeHtml(item.codigo)} - ${escapeHtml(item.nombre)}
                                </option>`;
                });
            }
            $('#cuenta_id').html(options);
            
            $('#cuenta_id').select2({
                dropdownParent: $('#modalContAsientoDetalle'),
                placeholder: "Buscar cuenta por código o nombre...",
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function() {
                        return "No se encontraron cuentas";
                    },
                    searching: function() {
                        return "Buscando...";
                    }
                }
            });
        }, 'json');
    }
    
    function resetModalAsiento() {
        $('#formContAsiento')[0].reset();
        $('#cont_asiento_id').val('');
        $('#formContAsiento').removeClass('was-validated');
        $('#total_debe_mostrar').text('0.00');
        $('#total_haber_mostrar').text('0.00');
        $('#diferencia_mostrar').text('0.00');
        $('#estado').val('borrador');
        
        var hoy = new Date();
        var fecha = hoy.getFullYear() + '-' + 
                    String(hoy.getMonth() + 1).padStart(2, '0') + '-' + 
                    String(hoy.getDate()).padStart(2, '0');
        $('#f_asiento').val(fecha);
        
        $('#tipo_cambio').val('1.000000');
        $('#numero_asiento').val('');
        
        if (tablaDetalles) {
            tablaDetalles.clear().draw();
        }
    }
    
    function resetModalDetalle() {
        $('#formContAsientoDetalle')[0].reset();
        $('#detalle_cuenta_id').val('');
        $('#detalle_cont_asiento_id').val('');
        $('#formContAsientoDetalle').removeClass('was-validated');
        $('#importe_local_debe').val('0.00');
        $('#importe_local_haber').val('0.00');
        
        if ($('#cuenta_id').data('select2')) {
            $('#cuenta_id').val('').trigger('change');
        }
    }
    
    // ========== EVENTOS ==========
    $(document).on('click', '#btnNuevoAsiento', function () {
        resetModalAsiento();
        cargarCombosFormulario();
        $('#modalLabel').text('Nuevo Asiento Contable');
        asientoActualId = null;
        
        var modal = new bootstrap.Modal(document.getElementById('modalContAsiento'));
        modal.show();
    });
    
    $(document).on('click', '#btnNuevoDetalle', function() {
        var asiento_id = $('#cont_asiento_id').val();
        if (!asiento_id || asiento_id == '') {
            Swal.fire({
                icon: "warning",
                title: "Guarde el asiento primero",
                text: "Debe guardar el asiento contable antes de agregar líneas",
                confirmButtonText: "Entendido"
            });
            return;
        }
        
        resetModalDetalle();
        $('#detalle_cont_asiento_id').val(asiento_id);
        cargarCombosDetalle();
        
        var modal = new bootstrap.Modal(document.getElementById('modalContAsientoDetalle'));
        modal.show();
    });
    
    // Manejador unificado para botones de acción de asientos
    $(document).on('click', '.btn-accion-asiento', function() {
        var id = $(this).data('id');
        var accion = $(this).data('accion');
        var numero = $(this).data('numero');
        
        console.log("Acción recibida del botón:", accion);
        
        switch(accion) {
            case 'editar':
                cargarAsientoParaEditar(id);
                break;
            case 'visualizar':
                cargarAsientoParaVisualizar(id);
                break;
            case 'confirmar':      // Para registrar/confirmar
                confirmarAccion(id, numero, 'confirmar', 'Registrar', 'registrado');
                break;
            case 'eliminar':       // Para eliminar
                confirmarAccion(id, numero, 'eliminar', 'Eliminar', 'eliminado', true);
                break;
            case 'imprimir':
                imprimirAsiento(id);
                break;
            default:
                Swal.fire({
                    icon: "warning",
                    title: "Acción no implementada",
                    text: `La acción "${accion}" no está disponible`
                });
        }
    });
    
   function confirmarAccion(id, numero, accionJs, titulo, mensajeExito, esEliminacion = false) {
        let tituloConfirmacion = '';
        let htmlConfirmacion = '';
        let icono = 'question';
        let textoBoton = `Sí, ${titulo.toLowerCase()}`;
        
        if (accionJs === 'confirmar') {
            // Verificar si el asiento está balanceado antes de confirmar
            var totalDebe = parseFloat($('#total_debe_mostrar').text().replace(/,/g, '')) || 0;
            var totalHaber = parseFloat($('#total_haber_mostrar').text().replace(/,/g, '')) || 0;
            var diferencia = totalDebe - totalHaber;
            
            console.log("Verificando balance - Debe:", totalDebe, "Haber:", totalHaber, "Diferencia:", diferencia);
            
            if (Math.abs(diferencia) > 0.01) {
                Swal.fire({
                    icon: "error",
                    title: "No se puede confirmar",
                    html: `El asiento no está balanceado.<br>
                        <strong>Total Debe:</strong> ${formatNumber(totalDebe, 2)}<br>
                        <strong>Total Haber:</strong> ${formatNumber(totalHaber, 2)}<br>
                        <strong>Diferencia:</strong> ${formatNumber(diferencia, 2)}<br><br>
                        Debe corregir la diferencia antes de confirmar.`,
                    confirmButtonText: "Entendido"
                });
                return;
            }
            
            tituloConfirmacion = '¿Registrar asiento?';
            htmlConfirmacion = `¿Está seguro de <strong>registrar</strong> el asiento<br>
                            <strong>${escapeHtml(numero)}</strong>?<br><br>
                            <span class="text-warning">Una vez registrado no podrá modificar las líneas.</span>`;
            icono = 'question';
            textoBoton = 'Sí, registrar';
        } else if (accionJs === 'eliminar') {
            tituloConfirmacion = '¿Eliminar asiento?';
            htmlConfirmacion = `¿Está seguro de <strong>eliminar</strong> el asiento<br>
                            <strong>${escapeHtml(numero)}</strong>?<br><br>
                            <span class="text-danger">Esta acción eliminará también todas las líneas del asiento.</span>`;
            icono = 'warning';
            textoBoton = 'Sí, eliminar';
        }
        
        Swal.fire({
            title: tituloConfirmacion,
            html: htmlConfirmacion,
            icon: icono,
            showCancelButton: true,
            confirmButtonColor: accionJs === 'eliminar' ? '#d33' : '#28a745',
            cancelButtonColor: '#d33',
            confirmButtonText: textoBoton,
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Procesando...',
                    text: `${titulo} asiento`,
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                $.post('cont_asientos_ajax.php', {
                    accion: 'ejecutar_accion',
                    id: id,
                    accion_js: accionJs,
                    pagina_id: pagina_id,
                    empresa_idx: empresa_id
                }, function(res) {
                    if (res.success) {
                        tabla.ajax.reload(null, false);
                        Swal.fire({
                            icon: "success",
                            title: `${titulo}${esEliminacion ? 'ado' : 'do'}`,
                            text: res.message,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 800,
                            timerProgressBar: true
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || `Error al ${titulo.toLowerCase()} el asiento`,
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json').fail(function() {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Error de conexión",
                        confirmButtonText: "Entendido"
                    });
                });
            }
        });
    }

    // Funciones de confirmación (definidas una sola vez)
    function confirmarRegistro(id, numero) {
        Swal.fire({
            title: '¿Registrar asiento?',
            html: `¿Está seguro de <strong>registrar</strong> el asiento<br>
                <strong>${escapeHtml(numero)}</strong>?<br><br>
                <span class="text-warning">Una vez registrado no podrá modificar las líneas.</span>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, registrar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Procesando...',
                    text: 'Registrando asiento',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // USAR 'confirmar' como accion_js (coincide con la BD)
                $.post('cont_asientos_ajax.php', {
                    accion: 'ejecutar_accion',
                    id: id,
                    accion_js: 'confirmar',  // <--- CAMBIADO: 'confirmar' en lugar de 'registrar'
                    pagina_id: pagina_id,
                    empresa_idx: empresa_id
                }, function(res) {
                    if (res.success) {
                        tabla.ajax.reload(null, false);
                        Swal.fire({
                            icon: "success",
                            title: "Registrado",
                            text: res.message,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 800,
                            timerProgressBar: true
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || "Error al registrar el asiento",
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json').fail(function() {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Error de conexión",
                        confirmButtonText: "Entendido"
                    });
                });
            }
        });
    }
    
function confirmarAnulacion(id, numero) {
    Swal.fire({
        title: '¿Anular asiento?',
        html: `¿Está seguro de <strong>anular</strong> el asiento<br>
               <strong>${escapeHtml(numero)}</strong>?<br><br>
               <span class="text-danger">Esta acción no se puede deshacer.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, anular',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Procesando...',
                text: 'Anulando asiento',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            $.post('cont_asientos_ajax.php', {
                accion: 'ejecutar_accion',
                id: id,
                accion_js: 'anular',  // Si existe en la BD
                pagina_id: pagina_id,
                empresa_idx: empresa_id
            }, function(res) {
                if (res.success) {
                    tabla.ajax.reload(null, false);
                    Swal.fire({
                        icon: "success",
                        title: "Anulado",
                        text: res.message,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 800,
                        timerProgressBar: true
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || "Error al anular el asiento",
                        confirmButtonText: "Entendido"
                    });
                }
            }, 'json');
        }
    });
}

function confirmarEliminacion(id, numero) {
    Swal.fire({
        title: '¿Eliminar asiento?',
        html: `¿Está seguro de <strong>eliminar</strong> el asiento<br>
               <strong>${escapeHtml(numero)}</strong>?<br><br>
               <span class="text-danger">Esta acción eliminará también todas las líneas del asiento.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Procesando...',
                text: 'Eliminando asiento',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            $.post('cont_asientos_ajax.php', {
                accion: 'eliminar_asiento',
                id: id,
                empresa_idx: empresa_id
            }, function(res) {
                if (res.success) {
                    tabla.ajax.reload(null, false);
                    Swal.fire({
                        icon: "success",
                        title: "Eliminado",
                        text: res.message,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 800,
                        timerProgressBar: true
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || "Error al eliminar el asiento",
                        confirmButtonText: "Entendido"
                    });
                }
            }, 'json').fail(function() {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error de conexión",
                    confirmButtonText: "Entendido"
                });
            });
        }
    });
}

function imprimirAsiento(id) {
    Swal.fire({
        icon: "info",
        title: "Imprimir",
        text: "Funcionalidad en desarrollo",
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 1500
    });
}
    
    // Editar detalle usando cuenta_id real
   $(document).on('click', '.btn-editar-detalle', function() {
        var cuenta_id = $(this).data('cuenta-id');
        var asiento_id = $(this).data('asiento');
        
        // Obtener el estado del asiento actual
        var estadoAsiento = $('#estado').val();
        console.log("Estado del asiento:", estadoAsiento);

        var estadoAsiento = $('#estado').val();
        console.log("ESTADO DEL ASIENTO AL EDITAR DETALLE:", estadoAsiento);
        
        // Si el asiento está registrado/confirmado, no permitir edición
        if (estadoAsiento === 'registrado') {
            Swal.fire({
                icon: "warning",
                title: "Operación no permitida",
                text: "No se pueden editar líneas de un asiento ya registrado/confirmado",
                confirmButtonText: "Entendido"
            });
            return;
        }
        
        // Obtener el tipo de la fila
        var $fila = $(this).closest('tr');
        var tipo = tablaDetalles.row($fila).data()?.tipo;
        
        console.log("Editar detalle - cuenta_id:", cuenta_id);
        console.log("Editar detalle - asiento_id:", asiento_id);
        console.log("Tipo de línea:", tipo);
        
        if (tipo !== 'M') {
            Swal.fire({
                icon: "warning",
                title: "Operación no permitida",
                text: "Las líneas de tipo Automático no pueden ser editadas porque provienen de un comprobante",
                confirmButtonText: "Entendido"
            });
            return;
        }
        
        if (!cuenta_id || cuenta_id == 0) {
            Swal.fire({
                icon: "error",
                title: "Error",
                text: "No se pudo identificar la cuenta a editar"
            });
            return;
        }
        
        $.ajax({
            url: 'cont_asientos_ajax.php',
            type: 'GET',
            data: {
                accion: 'obtener_detalle',
                cont_asiento_id: asiento_id,
                cuenta_id: cuenta_id,
                empresa_idx: empresa_id
            },
            dataType: 'json',
            success: function(res) {
                console.log("Respuesta obtener detalle:", res);
                if (res && res.cuenta_id) {
                    resetModalDetalle();
                    cargarCombosDetalle();
                    
                    setTimeout(function() {
                        $('#detalle_cuenta_id').val(res.cuenta_id);
                        $('#detalle_cont_asiento_id').val(res.cont_asiento_id);
                        
                        if ($('#cuenta_id').data('select2')) {
                            $('#cuenta_id').val(res.cuenta_id).trigger('change');
                        } else {
                            $('#cuenta_id').val(res.cuenta_id);
                        }
                        
                        var importe = parseFloat(res.importe_local);
                        if (importe > 0) {
                            $('#importe_local_debe').val(importe.toFixed(2));
                            $('#importe_local_haber').val('0.00');
                        } else {
                            $('#importe_local_debe').val('0.00');
                            $('#importe_local_haber').val(Math.abs(importe).toFixed(2));
                        }
                        
                        $('#detalle_descripcion').val(res.descripcion || '');
                        
                        var modal = new bootstrap.Modal(document.getElementById('modalContAsientoDetalle'));
                        modal.show();
                    }, 300);
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "No se encontró el detalle del asiento"
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error("Error AJAX:", error);
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener los datos del detalle"
                });
            }
        });
    });

    // Eliminar detalle - verificar estado del asiento y tipo
    $(document).on('click', '.btn-eliminar-detalle', function() {
        var cuenta_id = $(this).data('cuenta-id');
        var asiento_id = $(this).data('asiento');
        
        // Obtener el estado del asiento actual
        var estadoAsiento = $('#estado').val();
        console.log("Estado del asiento:", estadoAsiento);
        
        // Si el asiento está registrado/confirmado, no permitir eliminación
        if (estadoAsiento === 'registrado') {
            Swal.fire({
                icon: "warning",
                title: "Operación no permitida",
                text: "No se pueden eliminar líneas de un asiento ya registrado/confirmado",
                confirmButtonText: "Entendido"
            });
            return;
        }
        
        var $fila = $(this).closest('tr');
        var tipo = tablaDetalles.row($fila).data()?.tipo;
        
        console.log("Eliminar detalle - cuenta_id:", cuenta_id);
        console.log("Eliminar detalle - asiento_id:", asiento_id);
        console.log("Tipo de línea:", tipo);
        
        if (tipo !== 'M') {
            Swal.fire({
                icon: "warning",
                title: "Operación no permitida",
                text: "Las líneas de tipo Automático no pueden ser eliminadas porque provienen de un comprobante",
                confirmButtonText: "Entendido"
            });
            return;
        }
        
        if (!cuenta_id || cuenta_id == 0) {
            Swal.fire({
                icon: "error",
                title: "Error",
                text: "No se pudo identificar la cuenta a eliminar"
            });
            return;
        }
        
        if (!asiento_id || asiento_id == 0) {
            asiento_id = $('#cont_asiento_id').val();
        }
        
        if (!asiento_id || asiento_id == 0) {
            Swal.fire({
                icon: "error",
                title: "Error",
                text: "No se pudo identificar el asiento a eliminar"
            });
            return;
        }
        
        Swal.fire({
            title: '¿Eliminar línea?',
            text: "Esta acción eliminará la línea del asiento",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Eliminando...',
                    text: 'Por favor espere',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                $.ajax({
                    url: 'cont_asientos_ajax.php',
                    type: 'POST',
                    data: {
                        accion: 'eliminar_detalle',
                        cuenta_id: cuenta_id,
                        cont_asiento_id: asiento_id,
                        empresa_idx: empresa_id
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.success) {
                            if (tablaDetalles) {
                                tablaDetalles.ajax.reload(null, false);
                            }
                            actualizarTotales(asiento_id);
                            
                            Swal.fire({
                                icon: "success",
                                title: "Eliminado",
                                text: res.message,
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 800,
                                timerProgressBar: true
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: res.error || "Error al eliminar la línea",
                                confirmButtonText: "Entendido"
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error AJAX:", error);
                        Swal.fire({
                            icon: "error",
                            title: "Error de conexión",
                            text: "Error al comunicarse con el servidor",
                            confirmButtonText: "Entendido"
                        });
                    }
                });
            }
        });
    });
    
    function cargarAsientoParaEditar(id) {
        $.get('cont_asientos_ajax.php', {
            accion: 'obtener_asiento',
            id: id,
            empresa_idx: empresa_id
        }, function(res) {
            if (res && res.cont_asiento_id) {
                resetModalAsiento();
                cargarCombosFormulario();
                
                setTimeout(function() {
                    $('#cont_asiento_id').val(res.cont_asiento_id);
                    $('#f_asiento').val(res.f_asiento);
                    
                    if (res.cont_tipo_asiento_nombre) {
                        $('#cont_tipo_asiento_text').val(res.cont_tipo_asiento_nombre);
                    } else {
                        $('#cont_tipo_asiento_text').val(res.cont_tipo_asiento_id == 1 ? 'Asiento Manual' : 'Asiento Automático');
                    }
                    $('#comprobante_id').val(res.comprobante_id);
                    $('#sucursal_id').val(res.sucursal_id);
                    $('#deposito_id').val(res.deposito_id);
                    $('#entidad_id').val(res.entidad_id);
                    $('#moneda_id').val(res.moneda_id);
                    $('#tipo_cambio').val(res.tipo_cambio);
                    $('#descripcion').val(res.descripcion || '');
                    
                    if (res.numero_asiento) {
                        $('#numero_asiento').val(res.numero_asiento);
                    }
                    
                    $('#estado').val(res.estado);
                    console.log("Estado del asiento cargado:", res.estado);
                    
                    $('#modalLabel').text('Editar Asiento Contable');
                    asientoActualId = res.cont_asiento_id;
                    
                    // Si está registrado, cargar en modo solo lectura
                    if (res.estado === 'registrado') {
                        cargarDetallesSoloLectura(res.cont_asiento_id);
                        $('#btnNuevoDetalle').hide();
                        $('#formContAsiento :input').prop('disabled', true);
                        $('#btnGuardarAsiento').hide();
                    } else {
                        // Modo edición normal (con botones)
                        inicializarDataTableDetalles(res.cont_asiento_id);
                        $('#btnNuevoDetalle').show();
                        $('#formContAsiento :input').prop('disabled', false);
                        $('#btnGuardarAsiento').show();
                        $('#numero_asiento').prop('readonly', true);
                    }
                }, 500);
                
                var modal = new bootstrap.Modal(document.getElementById('modalContAsiento'));
                modal.show();
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos del asiento"
                });
            }
        }, 'json');
    }
    
    function cargarAsientoParaVisualizar(id) {
            $.get('cont_asientos_ajax.php', {
                accion: 'obtener_asiento',
                id: id,
                empresa_idx: empresa_id
            }, function(res) {
                if (res && res.cont_asiento_id) {
                    resetModalAsiento();
                    cargarCombosFormulario();
                    
                    setTimeout(function() {
                        $('#cont_asiento_id').val(res.cont_asiento_id);
                        $('#numero_asiento').val(res.numero_asiento);
                        $('#f_asiento').val(res.f_asiento);
                        $('#comprobante_id').val(res.comprobante_id);
                        $('#sucursal_id').val(res.sucursal_id);
                        $('#deposito_id').val(res.deposito_id);
                        $('#entidad_id').val(res.entidad_id);
                        $('#moneda_id').val(res.moneda_id);
                        $('#tipo_cambio').val(res.tipo_cambio);
                        $('#descripcion').val(res.descripcion || '');
                        $('#estado').val(res.estado); // Asignar estado también
                        $('#modalLabel').text('Visualizar Asiento Contable');
                        
                        // Deshabilitar todo el formulario
                        $('#formContAsiento :input').prop('disabled', true);
                        $('#btnGuardarAsiento').hide();
                        $('#btnNuevoDetalle').hide();
                        
                        // IMPORTANTE: Recargar la tabla de detalles en modo solo lectura
                        // Para eso, necesitamos una función que cargue la tabla sin botones de acción
                        cargarDetallesSoloLectura(res.cont_asiento_id);
                    }, 500);
                    
                    var modal = new bootstrap.Modal(document.getElementById('modalContAsiento'));
                    modal.show();
                    
                    $('#modalContAsiento').off('hidden.bs.modal').on('hidden.bs.modal', function () {
                        $('#formContAsiento :input').prop('disabled', false);
                        $('#btnGuardarAsiento').show();
                        $('#btnNuevoDetalle').show();
                    });
                }
            }, 'json');
        }

        function cargarDetallesSoloLectura(asientoId) {
        if ($.fn.DataTable.isDataTable('#tablaContAsientosDetalles')) {
            $('#tablaContAsientosDetalles').DataTable().destroy();
            $('#tablaContAsientosDetalles tbody').empty();
        }

        tablaDetalles = $('#tablaContAsientosDetalles').DataTable({
            ajax: {
                url: 'cont_asientos_ajax.php',
                type: 'GET',
                data: {
                    accion: 'listar_detalles',
                    empresa_idx: empresa_id,
                    cont_asiento_id: asientoId
                },
                dataSrc: ''
            },
            dom: '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            columns: [
                { data: 'id', className: 'text-center' },
                { data: 'cuenta_nombre_completo', defaultContent: '-' },
                { 
                    data: 'importe_local_debe',
                    className: 'text-end',
                    render: function(data) { return formatNumber(data, 2); }
                },
                { 
                    data: 'importe_local_haber',
                    className: 'text-end',
                    render: function(data) { return formatNumber(data, 2); }
                },
                { data: 'descripcion', defaultContent: '-' },
                { 
                    data: 'tipo',
                    className: 'text-center',
                    render: function(data) {
                        if (data === 'A') {
                            return '<span class="badge bg-secondary">Automático</span>';
                        } else {
                            return '<span class="badge bg-success">Manual</span>';
                        }
                    }
                },
                { 
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    render: function(data, type, row) {
                        // En modo solo lectura, mostrar solo un ícono de candado
                        return `<span class="badge bg-secondary" title="Modo solo lectura">
                                    <i class="fas fa-lock"></i> Solo lectura
                                </span>`;
                    }
                }
            ],
            drawCallback: function() {
                actualizarTotales(asientoId);
            },
            language: {
                url: '//cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json'                
            },
            responsive: true
        });
    }
   // ========== GUARDAR ASIENTO ==========
    $('#btnGuardarAsiento').click(function() {
        var form = document.getElementById('formContAsiento');
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        
        var id = $('#cont_asiento_id').val();
        var accionBackend = id ? 'editar_asiento' : 'agregar_asiento';
        
        var f_asiento = $('#f_asiento').val();
        if (!f_asiento) {
            Swal.fire({
                icon: "warning",
                title: "Validación",
                text: "La fecha es obligatoria",
                confirmButtonText: "Entendido"
            });
            return false;
        }
        
        var btnGuardar = $(this);
        var originalText = btnGuardar.html();
        btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
        
        var formData = new FormData();
        formData.append('accion', accionBackend);
        formData.append('empresa_idx', empresa_id);
        formData.append('id', id || '');
        formData.append('f_asiento', f_asiento);
        formData.append('sucursal_id', $('#sucursal_id').val() || '');
        formData.append('deposito_id', $('#deposito_id').val() || '');
        formData.append('comprobante_id', $('#comprobante_id').val() || '');
        formData.append('entidad_id', $('#entidad_id').val() || '');
        formData.append('moneda_id', $('#moneda_id').val() || '');
        formData.append('tipo_cambio', $('#tipo_cambio').val() || '1');
        formData.append('descripcion', $('#descripcion').val() || '');
        
        $.ajax({
            url: 'cont_asientos_ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                btnGuardar.prop('disabled', false).html(originalText);
                
                if (res.resultado) {
                    if (res.cont_asiento_id && !id) {
                        $('#cont_asiento_id').val(res.cont_asiento_id);
                        asientoActualId = res.cont_asiento_id;
                        if (res.numero_asiento) {
                            $('#numero_asiento').val(res.numero_asiento);
                        }
                        inicializarDataTableDetalles(res.cont_asiento_id);
                        $('#btnNuevoDetalle').show();
                    }
                    
                    // Recargar tabla sin esperar
                    tabla.ajax.reload(null, false);
                    
                    // SweetAlert rápido como toast
                    Swal.fire({
                        icon: "success",
                        title: "¡Guardado!",
                        text: res.message || "Asiento guardado correctamente",
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 800,
                        timerProgressBar: true
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || "Error al guardar los datos",
                        confirmButtonText: "Entendido"
                    });
                }
            },
            error: function(xhr, status, error) {
                btnGuardar.prop('disabled', false).html(originalText);
                console.error("Error AJAX:", error);
                
                let errorMsg = "Error al comunicarse con el servidor";
                if (xhr.responseText) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.error) errorMsg = response.error;
                    } catch(e) {
                        errorMsg = xhr.responseText.substring(0, 200);
                    }
                }
                
                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    text: errorMsg,
                    confirmButtonText: "Entendido"
                });
            }
        });
    }); 
   $('#btnGuardar').click(function() {
    var form = document.getElementById('formProducto');
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return false;
    }

    var id = $('#producto_id').val();
    var accionBackend = id ? 'editar' : 'agregar';

    // Obtener valor del lado, si es vacío o "0", enviar null o cadena vacía
    var ladoValue = $('#lado').val();
    if (ladoValue === '0' || ladoValue === '') {
        ladoValue = '';  // Enviar cadena vacía
    }

    var btnGuardar = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

    $.ajax({
        url: 'productos_ajax.php',
        type: 'POST',
        data: {
            accion: accionBackend,
            producto_id: id,
            producto_codigo: $('#producto_codigo').val().trim(),
            producto_nombre: $('#producto_nombre').val().trim(),
            codigo_barras: $('#codigo_barras').val(),
            producto_descripcion: $('#producto_descripcion').val(),
            producto_categoria_id: $('#producto_categoria_id').val(),
            producto_tipo_id: $('#producto_tipo_id').val(),
            unidad_medida_id: $('#unidad_medida_id').val() || null,
            cont_cuenta_id: $('#cont_cuenta_id').val() || null,
            iva_alicuota_id: $('#iva_alicuota_id').val() || null,
            lado: ladoValue,  // Usar el valor procesado
            material: $('#material').val(),
            color: $('#color').val(),
            peso: $('#peso').val(),
            dimensiones: $('#dimensiones').val(),
            garantia: $('#garantia').val(),
            controla_stock: $('#controla_stock').is(':checked') ? 1 : 0,
            empresa_idx: empresa_idx,
            pagina_idx: pagina_idx
        },
        // ...
    });
});
   
   // ========== GUARDAR DETALLE ==========
    $('#btnGuardarDetalle').click(function() {
        var form = document.getElementById('formContAsientoDetalle');
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        
        var asiento_id = $('#detalle_cont_asiento_id').val();
        var cuenta_id = $('#cuenta_id').val();
        
        if (!cuenta_id) {
            Swal.fire({
                icon: "error",
                title: "Error",
                text: "Debe seleccionar una cuenta contable"
            });
            return false;
        }
        
        var importeDebe = parseFloat($('#importe_local_debe').val()) || 0;
        var importeHaber = parseFloat($('#importe_local_haber').val()) || 0;
        
        if (importeDebe == 0 && importeHaber == 0) {
            Swal.fire({
                icon: "error",
                title: "Error",
                text: "Debe ingresar un importe (Debe o Haber)"
            });
            return false;
        }
        
        var btnGuardar = $(this);
        var originalText = btnGuardar.html();
        btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
        
        var formData = new FormData();
        formData.append('accion', 'agregar_detalle');
        formData.append('empresa_idx', empresa_id);
        formData.append('cont_asiento_id', asiento_id);
        formData.append('cuenta_id', cuenta_id);
        formData.append('importe_local_debe', importeDebe);
        formData.append('importe_local_haber', importeHaber);
        formData.append('descripcion', $('#detalle_descripcion').val() || '');
        
        $.ajax({
            url: 'cont_asientos_ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                btnGuardar.prop('disabled', false).html(originalText);
                
                if (res.resultado) {
                    if (tablaDetalles) {
                        tablaDetalles.ajax.reload(null, false);
                    }
                    actualizarTotales(asiento_id);
                    
                    // SweetAlert rápido como toast
                    Swal.fire({
                        icon: "success",
                        title: "¡Guardado!",
                        text: res.message || "Línea guardada correctamente",
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 800,
                        timerProgressBar: true
                    });
                    
                    var modalEl = document.getElementById('modalContAsientoDetalle');
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || "Error al guardar la línea",
                        confirmButtonText: "Entendido"
                    });
                }
            },
            error: function(xhr, status, error) {
                btnGuardar.prop('disabled', false).html(originalText);
                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    text: "Error al comunicarse con el servidor",
                    confirmButtonText: "Entendido"
                });
            }
        });
    });
    
    // Pantalla completa
    $('#btnToggleFullscreen').click(function() {
        var modalDialog = $('#modalContAsiento .modal-dialog');
        var btnIcon = $(this).find('i');
        if (modalDialog.hasClass('modal-fullscreen')) {
            modalDialog.removeClass('modal-fullscreen');
            btnIcon.removeClass('fa-compress').addClass('fa-expand');
        } else {
            modalDialog.addClass('modal-fullscreen');
            btnIcon.removeClass('fa-expand').addClass('fa-compress');
        }
    });
    
    // Sugerir Debe/Haber según naturaleza de la cuenta
    $(document).on('change', '#cuenta_id', function() {
        var selectedOption = $(this).find('option:selected');
        var naturaleza = selectedOption.data('naturaleza');
        if (naturaleza === 'D') {
            $('#importe_local_debe').focus();
        } else if (naturaleza === 'H') {
            $('#importe_local_haber').focus();
        }
    });
    
    // ========== FUNCIONES AUXILIARES ==========
    function formatNumber(number, decimals) {
        if (number === null || number === undefined || number === '') return '0.00';
        var num = parseFloat(number);
        if (isNaN(num)) return '0.00';
        return num.toFixed(decimals || 2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        var str = String(text);
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
    
    // ========== INICIALIZACIÓN ==========
    inicializarDataTable();
    cargarBotonAgregar();
    
    $('[title]').tooltip({
        trigger: 'hover',
        placement: 'top'
    });
});