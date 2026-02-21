$(document).ready(function () {
    const empresa_idx = 2;
    const pagina_idx = 65; // Este valor debería venir de PHP
    
    var tabla;
    var currentPage = 0;
    var currentOrder = [[1, 'asc']];
    var currentSearch = '';
    
    // Variables para manejo de detalles
    var detalles = [];
    var proveedorActualId = null;
    var timeoutBusqueda = null;
    var selectedIndex = -1; // Para navegación con flechas

    // ========== FUNCIONES DE DATATABLE ==========
    function inicializarDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaOrdenesCompra')) {
            $('#tablaOrdenesCompra').DataTable().destroy();
            $('#tablaOrdenesCompra tbody').empty();
        }

        tabla = $('#tablaOrdenesCompra').DataTable({
            ajax: {
                url: 'ordenes_compra_ajax.php',
                type: 'GET',
                data: {
                    accion: 'listar',
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                },
                dataSrc: ''
            },
            stateSave: true,
            stateSaveParams: function (settings, data) {
                data.page = currentPage;
                data.order = currentOrder;

                if (currentSearch !== '-1' && currentSearch !== '') {
                    data.search = { search: currentSearch };
                } else {
                    data.search = { search: '' };
                }

                delete data.columns;
                return data;
            },
            stateLoadParams: function (settings, data) {
                if (data.page !== undefined) currentPage = data.page;
                if (data.order !== undefined && data.order.length > 0) currentOrder = data.order;

                if (data.search && data.search.search !== undefined) {
                    var searchValue = data.search.search;
                    if (searchValue === '-1' || searchValue === '-1' || searchValue === '') {
                        currentSearch = '';
                    } else {
                        currentSearch = searchValue;
                    }
                } else {
                    currentSearch = '';
                }

                data.search = { search: currentSearch };
            },
            stateLoadCallback: function (settings) {
                var savedData = localStorage.getItem('DataTables_' + settings.sInstance);
                if (savedData) {
                    var data = JSON.parse(savedData);

                    if (data.search && (data.search.search === '-1' || data.search.search === '')) {
                        data.search.search = '';
                    }

                    if (data.columns) {
                        $.each(data.columns, function (i, col) {
                            if (col.search && col.search.search === '-1') {
                                col.search.search = '';
                            }
                        });
                    }

                    return data;
                }
                return null;
            },
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>' +
                '<"clear">',
            pageLength: 50,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Excel',
                    className: 'btn btn-success btn-sm',
                    title: 'Órdenes de Compra',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6],
                        orthogonal: 'export'
                    }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> PDF',
                    className: 'btn btn-danger btn-sm',
                    title: 'Órdenes de Compra',
                    orientation: 'portrait',
                    pageSize: 'A4',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6],
                        orthogonal: 'export'
                    }
                },
                {
                    extend: 'csvHtml5',
                    text: '<i class="fas fa-file-csv"></i> CSV',
                    className: 'btn btn-primary btn-sm',
                    title: 'Ordenes_Compra',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6]
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print"></i> Imprimir',
                    className: 'btn btn-secondary btn-sm',
                    title: 'Órdenes de Compra',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6],
                        stripHtml: false
                    }
                }
            ],
            columns: [
                {
                    data: 'orden_compra_id',
                    className: 'text-center fw-bold'
                },
                {
                    data: null,
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return data.comprobante_nro;
                        }
                        return `<div class="fw-medium">${data.comprobante_nro}</div>
                                <small class="text-muted">${data.comprobante_tipo || ''}</small>`;
                    }
                },
                {
                    data: null,
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return data.entidad_nombre || '';
                        }
                        return `<div class="fw-medium">${data.entidad_nombre || ''}</div>
                                <small class="text-muted">${data.entidad_fantasia || ''}</small>`;
                    }
                },
                {
                    data: 'f_emision',
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return data;
                        }
                        return `<span class="fw-medium">${data}</span>`;
                    }
                },
                {
                    data: 'f_entrega_estimada',
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (type === 'export' || !data) {
                            return data || '';
                        }
                        return `<span class="fw-medium">${data}</span>`;
                    }
                },
                {
                    data: 'total',
                    className: 'text-end',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return parseFloat(data).toFixed(2);
                        }
                        return `<span class="fw-bold text-primary">$${parseFloat(data).toFixed(2)}</span>`;
                    }
                },
                {
                    data: 'estado_info',
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (!data || !data.estado_registro) {
                            if (type === 'export') {
                                return 'Sin estado';
                            }
                            return '<span class="badge bg-secondary">Sin estado</span>';
                        }

                        var estado = data.estado_registro;
                        var colorClass = data.bg_clase || 'bg-secondary';
                        var textClass = data.text_clase || 'text-white';

                        if (type === 'export') {
                            return estado;
                        }

                        return `<span class="badge ${colorClass} ${textClass}">${estado}</span>`;
                    }
                },
                {
                    data: 'botones',
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    width: '250px',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return '';
                        }

                        var botones = '';

                        if (data && data.length > 0) {
                            var editarBoton = '';
                            var otrosBotones = '';

                            data.forEach(boton => {
                                var claseBoton = 'btn-sm me-1 ';
                                if (boton.bg_clase && boton.text_clase) {
                                    claseBoton += boton.bg_clase + ' ' + boton.text_clase;
                                } else if (boton.color_clase) {
                                    claseBoton += boton.color_clase;
                                } else {
                                    claseBoton += 'btn-outline-primary';
                                }

                                var titulo = boton.descripcion || boton.nombre_funcion;
                                var accionJs = boton.accion_js;
                                var icono = boton.icono_clase ? `<i class="${boton.icono_clase}"></i>` : '';
                                var esConfirmable = boton.es_confirmable || 0;

                                var botonHtml = `<button type="button" class="btn ${claseBoton} btn-accion" 
                                               title="${titulo}" 
                                               data-id="${row.orden_compra_id}" 
                                               data-accion="${accionJs}"
                                               data-confirmable="${esConfirmable}"
                                               data-comprobante="${row.comprobante_nro}">
                                            ${icono}
                                        </button>`;

                                if (accionJs === 'editar') {
                                    editarBoton = botonHtml;
                                } else {
                                    otrosBotones += botonHtml;
                                }
                            });

                            botones = editarBoton + otrosBotones;
                        } else {
                            botones = '<span class="text-muted small">Sin acciones</span>';
                        }

                        return `<div class="btn-group" role="group">${botones}</div>`;
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json',
                buttons: {
                    excel: 'Excel',
                    pdf: 'PDF',
                    csv: 'CSV',
                    print: 'Imprimir'
                }
            },
            order: currentOrder,
            responsive: true,
            createdRow: function (row, data, dataIndex) {
                if (data.estado_info && data.estado_info.codigo_estandar === 'CERRADO') {
                    $(row).addClass('table-success');
                } else if (data.estado_info && data.estado_info.codigo_estandar === 'CANCELADO') {
                    $(row).addClass('table-danger');
                } else if (data.estado_info && data.estado_info.codigo_estandar === 'PENDIENTE') {
                    $(row).addClass('table-warning');
                }
            },
            initComplete: function () {
                var buttons = new $.fn.dataTable.Buttons(tabla, {
                    buttons: ['excelHtml5', 'pdfHtml5', 'csvHtml5', 'print']
                }).container().appendTo($('#tablaOrdenesCompra_wrapper .col-md-6:eq(1)'));

                $(tabla.table().container()).on('page.dt', function (e) {
                    currentPage = tabla.page();
                });

                $(tabla.table().container()).on('order.dt', function (e, settings, details) {
                    currentOrder = tabla.order();
                });

                $(tabla.table().container()).on('search.dt', function (e, settings) {
                    currentSearch = tabla.search();
                });

                setTimeout(function () {
                    var searchInput = $('.dataTables_filter input');
                    if (searchInput.val() === '-1' || searchInput.val() === '') {
                        searchInput.val('');
                        currentSearch = '';

                        var savedData = localStorage.getItem('DataTables_' + tabla.settings()[0].sInstance);
                        if (savedData) {
                            var data = JSON.parse(savedData);
                            if (data.search && (data.search.search === '-1' || data.search.search === '')) {
                                data.search.search = '';
                                localStorage.setItem('DataTables_' + tabla.settings()[0].sInstance, JSON.stringify(data));
                            }
                        }
                    }
                }, 100);
            }
        });

        inicializarEventos();
    }

    function inicializarEventos() {
        $('#btnRecargar').off('click').on('click', function () {
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            var savedState = {
                page: tabla.page(),
                order: tabla.order(),
                search: tabla.search()
            };

            tabla.ajax.reload(function (json) {
                if (savedState.page !== undefined) {
                    tabla.page(savedState.page).draw('page');
                }
                if (savedState.search && savedState.search !== '') {
                    tabla.search(savedState.search).draw();
                }
                btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
            }, false);
        });
    }

    function cargarBotonAgregar() {
        $.get('ordenes_compra_ajax.php', {
            accion: 'obtener_boton_agregar',
            pagina_idx: pagina_idx
        }, function (botonAgregar) {
            if (botonAgregar && botonAgregar.nombre_funcion) {
                var icono = botonAgregar.icono_clase ? `<i class="${botonAgregar.icono_clase} me-1"></i>` : '';

                var colorClase = 'btn-primary';
                if (botonAgregar.bg_clase && botonAgregar.text_clase) {
                    colorClase = botonAgregar.bg_clase + ' ' + botonAgregar.text_clase;
                } else if (botonAgregar.color_clase) {
                    colorClase = botonAgregar.color_clase;
                }

                $('#contenedor-boton-agregar').html(
                    `<button type="button" class="btn ${colorClase}" id="btnNuevo">
                ${icono}${botonAgregar.nombre_funcion}
             </button>`
                );
            } else {
                $('#contenedor-boton-agregar').html(
                    '<button type="button" class="btn btn-primary" id="btnNuevo">' +
                    '<i class="fas fa-plus me-1"></i>Nueva Orden</button>'
                );
            }
        }, 'json');
    }

    // ========== FUNCIONES DE BÚSQUEDA DE PRODUCTOS ==========
    $('#busqueda_producto').on('input', function() {
        var q = $(this).val().trim();
        var resultadosDiv = $('#resultados_busqueda');
        
        if (!proveedorActualId) {
            resultadosDiv.hide();
            return;
        }
        
        if (q.length < 2) {
            resultadosDiv.hide();
            return;
        }
        
        clearTimeout(timeoutBusqueda);
        timeoutBusqueda = setTimeout(function() {
            $.ajax({
                url: 'ordenes_compra_ajax.php',
                type: 'GET',
                data: {
                    accion: 'buscar_productos_proveedor',
                    entidad_id: proveedorActualId,
                    q: q,
                    empresa_idx: empresa_idx
                },
                dataType: 'json',
                success: function(data) {
                    resultadosDiv.empty().hide();
                    selectedIndex = -1;
                    
                    if (data && data.length > 0) {
                        data.forEach(function(item, index) {
                            resultadosDiv.append(
                                `<a href="#" class="list-group-item list-group-item-action" 
                                   data-index="${index}"
                                   data-id="${item.producto_id}"
                                   data-iva="${item.iva_porcentaje || 21}">
                                    <strong>${item.producto_codigo}</strong> - ${item.producto_nombre}
                                </a>`
                            );
                        });
                        resultadosDiv.show();
                    }
                }
            });
        }, 300);
    });

    // Navegación con teclas (flechas arriba/abajo y Enter)
    $('#busqueda_producto').on('keydown', function(e) {
        var resultados = $('#resultados_busqueda .list-group-item');
        
        if (resultados.length === 0) return;
        
        // Flecha abajo
        if (e.keyCode === 40) {
            e.preventDefault();
            if (selectedIndex < resultados.length - 1) {
                selectedIndex++;
            } else {
                selectedIndex = 0;
            }
            actualizarSeleccion(resultados);
        }
        // Flecha arriba
        else if (e.keyCode === 38) {
            e.preventDefault();
            if (selectedIndex > 0) {
                selectedIndex--;
            } else {
                selectedIndex = resultados.length - 1;
            }
            actualizarSeleccion(resultados);
        }
        // Enter
        else if (e.keyCode === 13 && selectedIndex >= 0) {
            e.preventDefault();
            $(resultados[selectedIndex]).click();
        }
    });

    function actualizarSeleccion(resultados) {
        resultados.removeClass('active');
        $(resultados[selectedIndex]).addClass('active');
        
        // Scroll al elemento seleccionado si es necesario
        var container = $('#resultados_busqueda');
        var selectedElement = $(resultados[selectedIndex]);
        var containerScrollTop = container.scrollTop();
        var containerHeight = container.height();
        var elementTop = selectedElement.position().top;
        var elementHeight = selectedElement.outerHeight();
        
        if (elementTop < 0) {
            container.scrollTop(containerScrollTop + elementTop);
        } else if (elementTop + elementHeight > containerHeight) {
            container.scrollTop(containerScrollTop + (elementTop + elementHeight - containerHeight));
        }
    }

    // Seleccionar producto de la búsqueda
    $(document).on('click', '#resultados_busqueda .list-group-item', function(e) {
        e.preventDefault();
        
        var item = $(this);
        var productoId = item.data('id');
        var iva = item.data('iva');
        var textoCompleto = item.text().trim();
        
        $('#busqueda_producto').val(textoCompleto);
        $('#producto_seleccionado_id').val(productoId);
        $('#producto_iva').val(iva);
        
        // Cargar último precio
        $.get('ordenes_compra_ajax.php', {
            accion: 'obtener_ultimo_precio',
            producto_id: productoId,
            entidad_id: proveedorActualId,
            empresa_idx: empresa_idx
        }, function(res) {
            if (res.success && res.precio) {
                $('#producto_precio').val(res.precio);
            }
            calcularIvaImporte();
        }, 'json');
        
        $('#resultados_busqueda').hide();
        selectedIndex = -1;
    });

    // Ocultar resultados al hacer clic fuera
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#busqueda_producto, #resultados_busqueda').length) {
            $('#resultados_busqueda').hide();
            selectedIndex = -1;
        }
    });

    // Calcular IVA Importe automáticamente
    function calcularIvaImporte() {
        var cantidad = parseFloat($('#producto_cantidad').val()) || 0;
        var precio = parseFloat($('#producto_precio').val()) || 0;
        var iva = parseFloat($('#producto_iva').val()) || 0;
        // NoGravado y Exento NO afectan el cálculo del IVA
        // var noGravado = parseFloat($('#producto_no_gravado').val()) || 0;
        // var exento = parseFloat($('#producto_exento').val()) || 0;

        var netoGravado = cantidad * precio;
        var ivaImporte = netoGravado * (iva / 100);

        $('#producto_iva_importe').val(ivaImporte.toFixed(2));
    }

    $('#producto_cantidad, #producto_precio, #producto_iva, #producto_no_gravado, #producto_exento').on('input', function() {
        calcularIvaImporte();
    });

    // ========== FUNCIONES DE AGREGADO DE PRODUCTOS ==========
    function obtenerIdIva(porcentaje) {
        switch(parseFloat(porcentaje)) {
            case 21: return 1;
            case 10.5: return 2;
            case 27: return 3;
            case 0: return 4;
            default: return 1;
        }
    }

    $('#btnAgregarProducto').click(function() {
        if (!proveedorActualId) {
            Swal.fire({
                icon: "warning",
                title: "Seleccione proveedor",
                text: "Debe seleccionar un proveedor primero",
                confirmButtonText: "Entendido"
            });
            return;
        }
        
        var productoId = $('#producto_seleccionado_id').val();
        if (!productoId) {
            Swal.fire({
                icon: "warning",
                title: "Producto requerido",
                text: "Debe seleccionar un producto de la lista",
                confirmButtonText: "Entendido"
            });
            return;
        }
        
         var cantidad = parseFloat($('#producto_cantidad').val());
        var precio = parseFloat($('#producto_precio').val());
        var iva = parseFloat($('#producto_iva').val());
        var ivaImporte = parseFloat($('#producto_iva_importe').val()) || 0;
        var noGravado = parseFloat($('#producto_no_gravado').val()) || 0;
        var exento = parseFloat($('#producto_exento').val()) || 0;
        
        if (cantidad <= 0) {
            Swal.fire({
                icon: "warning",
                title: "Cantidad inválida",
                text: "La cantidad debe ser mayor a 0",
                confirmButtonText: "Entendido"
            });
            return;
        }
        
        if (precio <= 0) {
            Swal.fire({
                icon: "warning",
                title: "Precio inválido",
                text: "El precio debe ser mayor a 0",
                confirmButtonText: "Entendido"
            });
            return;
        }
        
        var productoText = $('#busqueda_producto').val();
        
        var netoGravado = cantidad * precio; 
        var ivaImporte = netoGravado * (iva / 100);
        var totalLinea = netoGravado + ivaImporte + noGravado + exento;
        
        var nuevoDetalle = {
            detalle_idx: 'temp_' + new Date().getTime(),
            ordenes_compra_detalle_id: 0,
            producto_id: parseInt(productoId),
            producto_nombre: productoText,
            cantidad: cantidad,
            precio_unitario: precio,
            no_gravado: noGravado,
            exento: exento,
            iva_alicuota_id: obtenerIdIva(iva),
            iva_porcentaje: iva,
            neto_gravado: netoGravado,   // <-- AHORA SOLO ES cantidad * precio
            iva_importe: ivaImporte,
            total_linea: totalLinea       // <-- AHORA ES LA SUMA CORRECTA
        };
        
        detalles.push(nuevoDetalle);
        renderizarDetalles();
        actualizarTotales();
        
        // Limpiar campos
        $('#busqueda_producto').val('');
        $('#producto_seleccionado_id').val('');
        $('#producto_cantidad').val('1.00');
        $('#producto_precio').val('');
        $('#producto_iva').val('21');
        $('#producto_iva_importe').val('0.00');
        $('#producto_no_gravado').val('0.00');
        $('#producto_exento').val('0.00');
        
        $('#busqueda_producto').focus();
    });

    // ========== FUNCIONES DE RENDERIZADO DE DETALLES ==========
    function renderizarDetalles() {
        $('#contenedor-detalles').empty();
        
        if (detalles.length === 0) {
            var htmlVacio = `
            <div class="detalles-vacio">
                <i class="fas fa-box-open"></i>
                <p class="mb-0">No hay productos agregados</p>
                <small class="text-muted">Seleccione un producto para comenzar</small>
            </div>`;
            $('#contenedor-detalles').html(htmlVacio);
            return;
        }
        
        var html = `
        <table class="table table-sm table-bordered table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Producto</th>
                    <th class="text-center">Cant.</th>
                    <th class="text-end">Precio</th>
                    <th class="text-center">IVA %</th>
                    <th class="text-end">IVA $</th>
                    <th class="text-end">No Grav.</th>
                    <th class="text-end">Exento</th>
                    <th class="text-end">Neto</th>
                    <th class="text-end">Total</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>`;
        
        detalles.forEach(function(detalle) {
            var esNuevo = detalle.ordenes_compra_detalle_id === 0;
            var claseFila = esNuevo ? 'table-info' : '';
            
            // Extraer solo el nombre del producto
            var nombreProducto = detalle.producto_nombre || '';
            
            html += `
            <tr class="${claseFila}" data-idx="${detalle.detalle_idx}">
                <td>
                    <div class="fw-bold">${nombreProducto.substring(0, 35)}${nombreProducto.length > 35 ? '...' : ''}</div>
                    ${esNuevo ? '<span class="badge bg-info ms-2">Nuevo</span>' : ''}
                </td>
                <td class="text-center">${detalle.cantidad.toFixed(2)}</td>
                <td class="text-end">$${detalle.precio_unitario.toFixed(4)}</td>
                <td class="text-center">${detalle.iva_porcentaje.toFixed(2)}%</td>
                <td class="text-end">$${(detalle.iva_importe || 0).toFixed(2)}</td>
                <td class="text-end">$${(detalle.no_gravado || 0).toFixed(2)}</td>
                <td class="text-end">$${(detalle.exento || 0).toFixed(2)}</td>
                <td class="text-end">$${(detalle.neto_gravado || 0).toFixed(2)}</td>
                <td class="text-end fw-bold text-success">$${detalle.total_linea.toFixed(2)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-warning btn-editar-detalle" 
                            data-idx="${detalle.detalle_idx}" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger btn-eliminar-detalle" 
                            data-idx="${detalle.detalle_idx}" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
        });
        
        html += `
            </tbody>
        </table>`;
        
        $('#contenedor-detalles').html(html);
    }

    function actualizarTotales() {
        var subtotal = 0;
        var impuestos = 0;
        var totalNoGravado = 0;
        var totalExento = 0;
        
         detalles.forEach(function(detalle) {
            subtotal += detalle.neto_gravado || 0;        // Suma de netos gravados
            impuestos += detalle.iva_importe || 0;        // Suma de IVA
            totalNoGravado += detalle.no_gravado || 0;    // Suma de no gravados
            totalExento += detalle.exento || 0;           // Suma de exentos
        });
        
         var total = subtotal + impuestos + totalNoGravado + totalExento;
        
        $('#subtotal').val(subtotal.toFixed(2));
        $('#impuestos').val(impuestos.toFixed(2));
        $('#total').val(total.toFixed(2));
        
        $('#subtotal_display').text(subtotal.toFixed(2));
        $('#impuestos_display').text(impuestos.toFixed(2));
        $('#total_display').text(total.toFixed(2));
    }

    // ========== FUNCIONES DE PANTALLA COMPLETA ==========
    $('#btnToggleFullscreen').click(function() {
        var modalDialog = $('#modalOrdenCompra .modal-dialog');
        var btnIcon = $(this).find('i');
        
        if (modalDialog.hasClass('modal-fullscreen')) {
            modalDialog.removeClass('modal-fullscreen');
            btnIcon.removeClass('fa-compress').addClass('fa-expand');
        } else {
            modalDialog.addClass('modal-fullscreen');
            btnIcon.removeClass('fa-expand').addClass('fa-compress');
        }
    });

    // Eliminar detalle
    $(document).on('click', '.btn-eliminar-detalle', function() {
        var idx = $(this).data('idx');
        
        Swal.fire({
            title: '¿Eliminar producto?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                detalles = detalles.filter(function(item) {
                    return item.detalle_idx != idx;
                });
                renderizarDetalles();
                actualizarTotales();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Eliminado',
                    text: 'Producto eliminado del detalle',
                    showConfirmButton: false,
                    timer: 1000,
                    toast: true,
                    position: 'top-end'
                });
            }
        });
    });

    // Editar detalle
    $(document).on('click', '.btn-editar-detalle', function() {
        var idx = $(this).data('idx');
        var detalle = detalles.find(function(item) {
            return item.detalle_idx == idx;
        });

        if (detalle) {
            // Cargar datos en el formulario
            $('#busqueda_producto').val(detalle.producto_nombre);
            $('#producto_seleccionado_id').val(detalle.producto_id);
            $('#producto_cantidad').val(detalle.cantidad);
            $('#producto_precio').val(detalle.precio_unitario);
            $('#producto_iva').val(detalle.iva_porcentaje);
            $('#producto_iva_importe').val(detalle.iva_importe);
            $('#producto_no_gravado').val(detalle.no_gravado || 0);
            $('#producto_exento').val(detalle.exento || 0);
            
            // Eliminar el detalle original
            detalles = detalles.filter(function(item) {
                return item.detalle_idx != idx;
            });
            renderizarDetalles();
            actualizarTotales();
            
            // Enfocar el campo de búsqueda
            $('#busqueda_producto').focus();
        }
    });

    // ========== FUNCIONES DE CARGA DE COMBOS ==========
    function cargarCombosFormulario() {
        // Cargar proveedores
        $.get('ordenes_compra_ajax.php', { accion: 'obtener_proveedores' }, function(data) {
            var options = '<option value="">Seleccionar proveedor</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.entidad_id}">${item.entidad_nombre}</option>`;
                });
            }
            $('#entidad_id').html(options);
        }, 'json');

        // Cargar tipos de comprobante
        $.get('ordenes_compra_ajax.php', { accion: 'obtener_comprobantes_tipos' }, function(data) {
            var options = '<option value="">Seleccionar</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.comprobante_tipo_id}" data-letra="${item.letra || ''}">${item.comprobante_tipo}</option>`;
                });
            }
            $('#comprobante_tipo_id').html(options);
            
            $('#comprobante_tipo_id').off('change').on('change', function() {
                var selectedOption = $(this).find('option:selected');
                var letra = selectedOption.data('letra');
                if (letra) {
                    $('#comprobante_letra').val(letra);
                }
            });
        }, 'json');

        // Cargar monedas
        $.get('ordenes_compra_ajax.php', { accion: 'obtener_monedas' }, function(data) {
            var options = '<option value="">Seleccionar moneda</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    var selected = item.es_moneda_base == 1 ? 'selected' : '';
                    options += `<option value="${item.moneda_id}" data-cotizacion="${item.cotizacion_actual}" ${selected}>${item.moneda} (${item.simbolo})</option>`;
                });
            }
            $('#moneda_id').html(options);
            
            var selectedMoneda = $('#moneda_id').find('option:selected');
            var cotizacion = selectedMoneda.data('cotizacion');
            if (cotizacion) {
                $('#tipo_cambio').val(cotizacion);
            }
            
            $('#moneda_id').off('change').on('change', function() {
                var selected = $(this).find('option:selected');
                var cotizacion = selected.data('cotizacion');
                if (cotizacion) {
                    $('#tipo_cambio').val(cotizacion);
                }
            });
        }, 'json');

        // Cargar condiciones de pago
        $.get('ordenes_compra_ajax.php', { 
            accion: 'obtener_condiciones_pago',
            empresa_idx: empresa_idx 
        }, function(data) {
            var options = '<option value="">Seleccionar</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.condicion_pago_id}">${item.codigo} - ${item.condicion_pago}</option>`;
                });
            }
            $('#condicion_pago_id').html(options);
        }, 'json');
    }

    // ========== EVENTOS DEL PROVEEDOR ==========
    $(document).on('change', '#entidad_id', function() {
        proveedorActualId = $(this).val();
        var proveedorNombre = $(this).find('option:selected').text();
        $('#proveedor_actual_nombre').text(proveedorNombre || 'No seleccionado');
        
        if (proveedorActualId) {
            // Cargar sucursales
            $.ajax({
                url: 'ordenes_compra_ajax.php',
                type: 'GET',
                data: { 
                    accion: 'obtener_sucursales',
                    entidad_id: proveedorActualId 
                },
                dataType: 'json',
                success: function(data) {
                    var options = '<option value="">Seleccionar sucursal</option>';
                    if (data && data.length > 0) {
                        data.forEach(function(item) {
                            options += `<option value="${item.sucursal_id}">${item.sucursal_nombre}</option>`;
                        });
                    }
                    $('#entidad_sucursal_id').html(options);
                }
            });
        } else {
            $('#entidad_sucursal_id').html('<option value="">Seleccionar sucursal</option>');
        }
    });

    // ========== FUNCIONES DEL MODAL ==========
    function resetModal() {
        $('#formOrdenCompra')[0].reset();
        $('#orden_compra_id').val('');
        $('#tipo_cambio').val('1.000000');
        $('#formOrdenCompra').removeClass('was-validated');
        
        detalles = [];
        proveedorActualId = null;
        renderizarDetalles();
        actualizarTotales();
        
        $('#entidad_sucursal_id').html('<option value="">Seleccionar sucursal</option>');
        $('#proveedor_actual_nombre').text('No seleccionado');
    }

    $(document).on('click', '#btnNuevo', function () {
        resetModal();
        $('#modalLabel').text('Nueva Orden de Compra');
        cargarCombosFormulario();
        
        var today = new Date().toISOString().split('T')[0];
        $('#f_emision').val(today);

        var modal = new bootstrap.Modal(document.getElementById('modalOrdenCompra'));
        modal.show();
    });

    // ========== FUNCIONES DE ACCIONES DE BOTONES ==========
    $(document).on('click', '.btn-accion', function () {
        var ordenId = $(this).data('id');
        var accionJs = $(this).data('accion');
        var confirmable = $(this).data('confirmable');
        var comprobante = $(this).data('comprobante');

        if (accionJs === 'editar') {
            cargarOrdenParaEditar(ordenId);
        } else if (confirmable == 1) {
            Swal.fire({
                title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                html: `¿Está seguro de <strong>${accionJs}</strong> la orden <strong>"${comprobante}"</strong>?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: `Sí, ${accionJs}`,
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    ejecutarAccion(ordenId, accionJs, comprobante);
                }
            });
        } else {
            ejecutarAccion(ordenId, accionJs, comprobante);
        }
    });

    function ejecutarAccion(ordenId, accionJs, comprobante) {
        var savedState = {
            page: tabla.page(),
            order: tabla.order(),
            search: tabla.search()
        };

        $.post('ordenes_compra_ajax.php', {
            accion: 'ejecutar_accion',
            orden_compra_id: ordenId,
            accion_js: accionJs,
            empresa_idx: empresa_idx,
            pagina_idx: pagina_idx
        }, function (res) {
            if (res.success) {
                tabla.ajax.reload(function (json) {
                    if (savedState.page !== undefined) {
                        tabla.page(savedState.page).draw('page');
                    }
                    if (savedState.search && savedState.search !== '') {
                        tabla.search(savedState.search).draw();
                    }

                    Swal.fire({
                        icon: "success",
                        title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                        text: res.message || `Orden "${comprobante}" actualizada correctamente`,
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
                }, false);
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: res.error || `Error al ${accionJs} la orden`,
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    }

    // ========== CARGA DE ORDEN PARA EDITAR ==========
    function cargarOrdenParaEditar(ordenId) {
        $.get('ordenes_compra_ajax.php', {
            accion: 'obtener',
            orden_compra_id: ordenId,
            empresa_idx: empresa_idx
        }, function (res) {
            console.log("Orden recibida:", res);
            
            if (res && res.orden_compra_id) {
                resetModal();
                cargarCombosFormulario();
                
                $('#orden_compra_id').val(res.orden_compra_id);
                $('#comprobante_nro').val(res.comprobante_nro);
                $('#comprobante_letra').val(res.comprobante_letra || '');
                $('#comprobante_suc').val(res.comprobante_suc || '');
                $('#f_emision').val(res.f_emision);
                $('#f_entrega_estimada').val(res.f_entrega_estimada);
                $('#direccion_entrega').val(res.direccion_entrega);
                $('#observaciones').val(res.observaciones);
                $('#tipo_cambio').val(res.tipo_cambio || '1.000000');
                $('#subtotal').val(res.subtotal);
                $('#descuentos').val(res.descuentos);
                $('#impuestos').val(res.impuestos);
                $('#total').val(res.total);
                
                $('#subtotal_display').text(parseFloat(res.subtotal || 0).toFixed(2));
                $('#descuentos_display').text(parseFloat(res.descuentos || 0).toFixed(2));
                $('#impuestos_display').text(parseFloat(res.impuestos || 0).toFixed(2));
                $('#total_display').text(parseFloat(res.total || 0).toFixed(2));
                
                $('#modalLabel').text('Editar Orden de Compra');

                setTimeout(function() {
                    $('#comprobante_tipo_id').val(res.comprobante_tipo_id);
                    $('#moneda_id').val(res.moneda_id);
                    $('#condicion_pago_id').val(res.condicion_pago_id);
                    
                    if (res.entidad_id) {
                        proveedorActualId = res.entidad_id;
                        $('#entidad_id').val(res.entidad_id);
                        
                        var proveedorNombre = $('#entidad_id option:selected').text();
                        $('#proveedor_actual_nombre').text(proveedorNombre || 'No seleccionado');
                        
                        // Cargar sucursales
                        $.ajax({
                            url: 'ordenes_compra_ajax.php',
                            type: 'GET',
                            data: { 
                                accion: 'obtener_sucursales',
                                entidad_id: res.entidad_id,
                                empresa_idx: empresa_idx  
                            },
                            dataType: 'json',
                            success: function(data) {
                                var options = '<option value="">Seleccionar sucursal</option>';
                                if (data && data.length > 0) {
                                    data.forEach(function(item) {
                                        var selected = item.sucursal_id == res.entidad_sucursal_id ? 'selected' : '';
                                        options += `<option value="${item.sucursal_id}" ${selected}>${item.sucursal_nombre}</option>`;
                                    });
                                }
                                $('#entidad_sucursal_id').html(options);
                            }
                        });
                    }
                    
                    // Cargar detalles si existen
                    if (res.detalles && res.detalles.length > 0) {
                        detalles = res.detalles.map(function(detalle, index) {
                            return {
                                detalle_idx: index,
                                ordenes_compra_detalle_id: detalle.ordenes_compra_detalle_id,
                                producto_id: detalle.producto_id,
                                producto_nombre: detalle.producto_nombre,
                                cantidad: detalle.cantidad,
                                precio_unitario: detalle.precio_unitario,
                                no_gravado: detalle.no_gravado || 0,
                                exento: detalle.exento || 0,
                                iva_alicuota_id: detalle.iva_alicuota_id,
                                iva_porcentaje: detalle.iva_porcentaje,
                                neto_gravado: detalle.neto_gravado,
                                iva_importe: detalle.iva_importe,
                                total_linea: detalle.total_linea
                            };
                        });
                        renderizarDetalles();
                        actualizarTotales();
                    }
                }, 500);

                var modal = new bootstrap.Modal(document.getElementById('modalOrdenCompra'));
                modal.show();
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos de la orden",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    }

    // ========== GUARDAR ORDEN ==========
    $('#btnGuardar').click(function() {
        var form = document.getElementById('formOrdenCompra');
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        
        if (detalles.length === 0) {
            Swal.fire({
                icon: "warning",
                title: "Detalles requeridos",
                text: "Debe agregar al menos un producto al detalle",
                confirmButtonText: "Entendido"
            });
            return false;
        }
        
        var id = $('#orden_compra_id').val();
        var accionBackend = id ? 'editar' : 'agregar';
        
        var btnGuardar = $(this);
        var originalText = btnGuardar.html();
        btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
        
        var formData = new FormData(document.getElementById('formOrdenCompra'));
        formData.append('accion', accionBackend);
        formData.append('empresa_idx', empresa_idx);
        formData.append('pagina_idx', pagina_idx);
        formData.append('detalles', JSON.stringify(detalles));
        
        var savedState = {
            page: tabla ? tabla.page() : 0,
            order: tabla ? tabla.order() : [[1, 'asc']],
            search: tabla ? tabla.search() : ''
        };
        
        $.ajax({
            url: 'ordenes_compra_ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                if (res.resultado) {
                    if (tabla) {
                        tabla.ajax.reload(function(json) {
                            if (savedState.page !== undefined) {
                                tabla.page(savedState.page).draw('page');
                            }
                            if (savedState.search && savedState.search !== '') {
                                tabla.search(savedState.search).draw();
                            }
                        }, false);
                    }
                    
                    btnGuardar.prop('disabled', false).html(originalText);
                    
                    Swal.fire({
                        icon: "success",
                        title: "¡Guardado!",
                        text: "Orden de compra guardada correctamente",
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
                    
                    var modalEl = document.getElementById('modalOrdenCompra');
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    modal.hide();
                } else {
                    btnGuardar.prop('disabled', false).html(originalText);
                    
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
                console.error("Respuesta:", xhr.responseText);
                
                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    text: "Error al comunicarse con el servidor",
                    confirmButtonText: "Entendido"
                });
            }
        });
    });

    // ========== PRODUCTO RÁPIDO ==========
    function cargarDatosProductoRapido() {
        $.get('ordenes_compra_ajax.php', { 
            accion: 'obtener_categorias_productos',
            empresa_idx: empresa_idx 
        }, function(data) {
            var options = '<option value="">Seleccionar categoría</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.producto_categoria_id}">${item.categoria_nombre}</option>`;
                });
            }
            $('#producto_categoria_id_rapido').html(options);
        }, 'json');

        $.get('ordenes_compra_ajax.php', { accion: 'obtener_unidades_medida' }, function(data) {
            var options = '<option value="">Seleccionar unidad</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.unidad_medida_id}">${item.unidad_nombre}</option>`;
                });
            }
            $('#unidad_medida_id_rapido').html(options);
        }, 'json');
    }

    $(document).on('click', '#btnNuevoProductoRapido', function() {
        if (!proveedorActualId) {
            Swal.fire({
                icon: "warning",
                title: "Seleccione proveedor",
                text: "Debe seleccionar un proveedor primero",
                confirmButtonText: "Entendido"
            });
            return;
        }

        $('#formNuevoProductoRapido')[0].reset();
        $('#formNuevoProductoRapido').removeClass('was-validated');
        $('#iva_alicuota_id_rapido').val('1');
        
        cargarDatosProductoRapido();
        
        var modal = new bootstrap.Modal(document.getElementById('modalNuevoProductoRapido'));
        modal.show();
    });

    $(document).on('click', '#btnGuardarProductoRapido', function() {
        var form = document.getElementById('formNuevoProductoRapido');
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }

        var btn = $(this);
        var originalText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        var data = {
            accion: 'agregar_producto_rapido',
            empresa_idx: empresa_idx,
            producto_codigo: $('#producto_codigo_rapido').val(),
            producto_nombre: $('#producto_nombre_rapido').val(),
            codigo_barras: $('#codigo_barras_rapido').val(),
            producto_descripcion: $('#producto_descripcion_rapido').val(),
            producto_categoria_id: $('#producto_categoria_id_rapido').val(),
            iva_alicuota_id: $('#iva_alicuota_id_rapido').val(),
            unidad_medida_id: $('#unidad_medida_id_rapido').val() || null,
            codigo_proveedor: $('#codigo_proveedor_rapido').val(),
            entidad_id: proveedorActualId
        };

        $.ajax({
            url: 'ordenes_compra_ajax.php',
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    var modalProducto = bootstrap.Modal.getInstance(document.getElementById('modalNuevoProductoRapido'));
                    modalProducto.hide();
                    
                    Swal.fire({
                        icon: "success",
                        title: "Producto creado",
                        text: "El producto fue creado exitosamente",
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || "Error al crear el producto",
                        confirmButtonText: "Entendido"
                    });
                }
                btn.prop('disabled', false).html(originalText);
            },
            error: function() {
                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    text: "Error al comunicarse con el servidor",
                    confirmButtonText: "Entendido"
                });
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // ========== EXPORTAR ==========
    $('#btnExportarExcel').click(function (e) {
        e.preventDefault();
        $('.buttons-excel').click();
    });

    $('#btnExportarPDF').click(function (e) {
        e.preventDefault();
        $('.buttons-pdf').click();
    });

    $('#btnExportarCSV').click(function (e) {
        e.preventDefault();
        $('.buttons-csv').click();
    });

    $('#btnExportarPrint').click(function (e) {
        e.preventDefault();
        $('.buttons-print').click();
    });

    // ========== INICIALIZACIÓN ==========
    inicializarDataTable();
    cargarBotonAgregar();

    $('[title]').tooltip({
        trigger: 'hover',
        placement: 'top'
    });
});