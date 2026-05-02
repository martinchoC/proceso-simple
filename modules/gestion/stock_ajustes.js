$(document).ready(function () {
    const empresa_idx = 2;
    const pagina_idx = 77;
    
    var tabla;
    var currentPage = 0;
    var currentOrder = [[1, 'asc']];
    var currentSearch = '';
    
    var detalles = [];
    var timeoutBusqueda = null;
    var selectedIndex = -1;

    function inicializarDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaStockAjustes')) {
            $('#tablaStockAjustes').DataTable().destroy();
            $('#tablaStockAjustes tbody').empty();
        }

        tabla = $('#tablaStockAjustes').DataTable({
            ajax: {
                url: 'stock_ajustes_ajax.php',
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
                    if (searchValue === '-1' || searchValue === '') {
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
            dom: '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>' +
                '<"clear">',
            pageLength: 50,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            
            columns: [
                {
                    data: 'stock_ajuste_id',
                    className: 'text-center'
                },
                {
                    data: 'comprobante_tipo',
                    className: 'text-center',
                    render: function(data, type, row) {
                        return type === 'export' ? (data || '') : `<span>${data || ''}</span>`;
                    }
                },
                {
                    data: 'sucursal_nombre',
                    className: 'text-center',
                    render: function(data, type, row) {
                        return type === 'export' ? (data || '') : `<span>${data || ''}</span>`;
                    }
                },
                {
                    data: 'deposito_nombre',
                    className: 'text-center',
                    render: function(data, type, row) {
                        return type === 'export' ? (data || '') : `<span>${data || ''}</span>`;
                    }
                },
                {
                    data: 'fecha',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (type === 'export' || !data) return data || '';
                        return data.replace(' ', ' / ').substring(0, 16);
                    }
                },
                {
                    data: 'descripcion',
                    render: function(data, type, row) {
                        return type === 'export' ? (data || '') : `<span>${data || ''}</span>`;
                    }
                },
                {
                    data: 'estado_info',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (type === 'export') {
                            return (data && data.estado_registro) ? data.estado_registro : '';
                        }
                        var estadoTexto = (data && data.estado_registro) ? data.estado_registro : '';
                        return `<span>${estadoTexto}</span>`;
                    }
                },
                {
                    data: 'botones',
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    width: '250px',
                    render: function(data, type, row) {
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
                                var ajusteInfo = `Ajuste #${row.stock_ajuste_id} - ${row.comprobante_tipo || ''}`;

                                var botonHtml = `<button type="button" class="btn ${claseBoton} btn-accion" 
                                                title="${titulo}" 
                                                data-id="${row.stock_ajuste_id}" 
                                                data-accion="${accionJs}"
                                                data-confirmable="${esConfirmable}"
                                                data-comprobante="${ajusteInfo}">
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
                url: '//cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json'
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
                this.api().columns().every(function () {
                    var column = this;
                    var header = $(column.header());
                    var input = $('<input type="text" class="column-filter" placeholder="Filtrar...">')
                        .appendTo(header)
                        .on('keyup change', function () {
                            if (column.search() !== this.value) {
                                column.search(this.value).draw();
                            }
                        });
                });

                setTimeout(function() {
                    var lengthControl = $('#tablaStockAjustes_length').detach();
                    $('#tablaStockAjustes_length').replaceWith(lengthControl);
                    var filterControl = $('#tablaStockAjustes_filter').detach();
                    $('#tablaStockAjustes_filter').replaceWith(filterControl);
                    
                    $('#tablaStockAjustes_length').addClass('dataTables_length_custom');
                    $('#tablaStockAjustes_filter').addClass('dataTables_filter_custom');
                    
                    if ($('#tablaStockAjustes_length').html().trim() === '') {
                        var selectHtml = '<label>Mostrar <select name="tablaStockAjustes_length" aria-controls="tablaStockAjustes" class="form-select form-select-sm"><option value="10">10</option><option value="25">25</option><option value="50" selected="">50</option><option value="100">100</option><option value="-1">Todos</option></select> registros</label>';
                        $('#tablaStockAjustes_length').html(selectHtml);
                        $('#tablaStockAjustes_length select').on('change', function() {
                            tabla.page.len($(this).val()).draw();
                        });
                    }
                    
                    if ($('#tablaStockAjustes_filter').html().trim() === '') {
                        var filterHtml = '<label>Buscar:<input type="search" class="form-control form-control-sm" placeholder="" aria-controls="tablaStockAjustes"></label>';
                        $('#tablaStockAjustes_filter').html(filterHtml);
                        $('#tablaStockAjustes_filter input').on('keyup', function() {
                            tabla.search($(this).val()).draw();
                        });
                    }
                }, 100);

                var buttons = new $.fn.dataTable.Buttons(tabla, {
                    buttons: ['excelHtml5', 'pdfHtml5', 'csvHtml5', 'print']
                }).container().appendTo($('#tablaStockAjustes_wrapper .col-md-6:eq(1)'));

                $(tabla.table().container()).on('page.dt', function (e) {
                    currentPage = tabla.page();
                });
                $(tabla.table().container()).on('order.dt', function (e, settings, details) {
                    currentOrder = tabla.order();
                });
                $(tabla.table().container()).on('search.dt', function (e, settings) {
                    currentSearch = tabla.search();
                });
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

        $('#btnExportarExcel').on('click', function() { tabla.button('.buttons-excel').trigger(); });
        $('#btnExportarPDF').on('click', function() { tabla.button('.buttons-pdf').trigger(); });
        $('#btnExportarCSV').on('click', function() { tabla.button('.buttons-csv').trigger(); });
        $('#btnExportarPrint').on('click', function() { tabla.button('.buttons-print').trigger(); });
    }

    function cargarBotonAgregar() {
        $.get('stock_ajustes_ajax.php', {
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
                    '<i class="fas fa-plus me-1"></i>Nuevo Ajuste</button>'
                );
            }
        }, 'json');
    }

    $(document).on('click', '.btn-accion', function () {
        var id = $(this).data('id');
        var accionJs = $(this).data('accion');
        var confirmable = $(this).data('confirmable');
        var comprobanteInfo = $(this).data('comprobante') || 'Ajuste #' + id;

        if (accionJs === 'editar') {
            cargarAjusteParaEditar(id);
        } else if (accionJs === 'visualizar') {
            cargarAjusteParaVisualizar(id);
        } else if (confirmable == 1) {
            Swal.fire({
                title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                html: `¿Está seguro de <strong>${accionJs}</strong> el ajuste?<br><strong>${comprobanteInfo}</strong>`,
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
                    ejecutarAccion(id, accionJs, comprobanteInfo);
                }
            });
        } else {
            ejecutarAccion(id, accionJs, comprobanteInfo);
        }
    });

    function ejecutarAccion(id, accionJs, comprobanteInfo) {
        var savedState = {
            page: tabla.page(),
            order: tabla.order(),
            search: tabla.search()
        };

        $.post('stock_ajustes_ajax.php', {
            accion: 'ejecutar_accion',
            stock_ajuste_id: id,
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
                        text: res.message || `Ajuste actualizado correctamente`,
                        showConfirmButton: false,
                        timer: 2000,
                        toast: true,
                        position: 'top-end'
                    });
                }, false);
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: res.error || `Error al ${accionJs} el ajuste`,
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json').fail(function(xhr) {
            Swal.fire({
                icon: "error",
                title: "Error de conexión",
                text: "No se pudo conectar con el servidor",
                confirmButtonText: "Entendido"
            });
            console.error('Error en ejecutarAccion:', xhr.responseText);
        });
    }

    function cargarCombosFormulario() {
        $.get('stock_ajustes_ajax.php', { 
            accion: 'obtener_sucursales_empresa',
            empresa_idx: empresa_idx 
        }, function(data) {
            let options = '<option value="">Seleccionar sucursal</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.sucursal_id}">${item.sucursal_nombre}</option>`;
                });
            }
            $('#sucursal_id').html(options);
        }, 'json');

        $.get('stock_ajustes_ajax.php', { accion: 'obtener_comprobantes_tipos' }, function(data) {
            if (data && data.length > 0) {
                if (data.length === 1) {
                    var options = `<option value="${data[0].comprobante_tipo_id}" selected>${data[0].comprobante_tipo}</option>`;
                    $('#comprobante_tipo_id').html(options);
                } else {
                    var options = '<option value="">Seleccionar</option>';
                    data.forEach(function(item) {
                        options += `<option value="${item.comprobante_tipo_id}">${item.comprobante_tipo}</option>`;
                    });
                    $('#comprobante_tipo_id').html(options);
                }
            }
        }, 'json');
    }

    function cargarDepositos(sucursalId, depositoSeleccionado) {
        if (!sucursalId) {
            $('#deposito_id').html('<option value="">Primero seleccione sucursal</option>');
            $('#deposito_id').prop('disabled', true);
            return;
        }
        $.get('stock_ajustes_ajax.php', {
            accion: 'obtener_depositos',
            sucursal_id: sucursalId,
            empresa_idx: empresa_idx
        }, function(data) {
            var options = '<option value="">Seleccionar depósito</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.deposito_id}">${item.deposito_nombre}</option>`;
                });
                $('#deposito_id').prop('disabled', false);
            } else {
                options = '<option value="">No hay depósitos disponibles</option>';
                $('#deposito_id').prop('disabled', true);
            }
            $('#deposito_id').html(options);
            if (depositoSeleccionado) {
                $('#deposito_id').val(depositoSeleccionado);
            }
        }, 'json');
    }

    $('#sucursal_id').on('change', function() {
        var sucursalId = $(this).val();
        cargarDepositos(sucursalId);
    });

    $('#busqueda_producto').on('input', function() {
        var q = $(this).val().trim();
        var resultadosDiv = $('#resultados_busqueda');
        var depositoId = $('#deposito_id').val();
        
        if (!depositoId) {
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
                url: 'stock_ajustes_ajax.php',
                type: 'GET',
                data: {
                    accion: 'buscar_productos',
                    deposito_id: depositoId,
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
                                   data-codigo="${item.producto_codigo}"
                                   data-nombre="${item.producto_nombre}"
                                   data-stock="${item.stock_sistema || 0}">
                                    <strong>${item.producto_codigo}</strong> - ${item.producto_nombre}
                                    <br><small>Stock: ${parseFloat(item.stock_sistema || 0).toFixed(6)}</small>
                                </a>`
                            );
                        });
                        resultadosDiv.show();
                    }
                }
            });
        }, 300);
    });

    $('#busqueda_producto').on('keydown', function(e) {
        var resultados = $('#resultados_busqueda .list-group-item');
        if (resultados.length === 0) return;
        if (e.keyCode === 40) {
            e.preventDefault();
            if (selectedIndex < resultados.length - 1) {
                selectedIndex++;
            } else {
                selectedIndex = 0;
            }
            actualizarSeleccion(resultados);
        }
        else if (e.keyCode === 38) {
            e.preventDefault();
            if (selectedIndex > 0) {
                selectedIndex--;
            } else {
                selectedIndex = resultados.length - 1;
            }
            actualizarSeleccion(resultados);
        }
        else if (e.keyCode === 13 && selectedIndex >= 0) {
            e.preventDefault();
            $(resultados[selectedIndex]).click();
        }
    });

    function actualizarSeleccion(resultados) {
        resultados.removeClass('active');
        $(resultados[selectedIndex]).addClass('active');
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

    $(document).on('click', '#resultados_busqueda .list-group-item', function(e) {
        e.preventDefault();
        var item = $(this);
        var productoId = item.data('id');
        var stockSistema = parseFloat(item.data('stock') || 0);
        var textoCompleto = item.data('codigo') + ' - ' + item.data('nombre');
        
        $('#busqueda_producto').val(textoCompleto);
        $('#producto_seleccionado_id').val(productoId);
        $('#producto_stock_sistema').val(stockSistema.toFixed(6));
        $('#producto_stock_fisico').val(stockSistema.toFixed(6));
        calcularDiferencia();
        
        $('#resultados_busqueda').hide();
        selectedIndex = -1;
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#busqueda_producto, #resultados_busqueda').length) {
            $('#resultados_busqueda').hide();
            selectedIndex = -1;
        }
    });

    function calcularDiferencia() {
        var stockSistema = parseFloat($('#producto_stock_sistema').val()) || 0;
        var stockFisico = parseFloat($('#producto_stock_fisico').val()) || 0;
        var diferencia = stockFisico - stockSistema;
        $('#producto_diferencia').val(diferencia.toFixed(6));
    }

    $('#producto_stock_fisico').on('input', function() {
        calcularDiferencia();
    });

    $('#btnAgregarProducto').click(function() {
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
        
        var stockSistema = parseFloat($('#producto_stock_sistema').val()) || 0;
        var stockFisico = parseFloat($('#producto_stock_fisico').val()) || 0;
        var diferencia = parseFloat($('#producto_diferencia').val()) || 0;
        var costo = parseFloat($('#producto_costo').val()) || 0;
        var productoText = $('#busqueda_producto').val();
        
        var nuevoDetalle = {
            detalle_idx: 'temp_' + new Date().getTime(),
            stock_ajuste_detalle_id: 0,
            producto_id: parseInt(productoId),
            producto_nombre: productoText,
            deposito_id: parseInt($('#deposito_id').val()),
            stock_sistema: stockSistema,
            stock_fisico: stockFisico,
            diferencia: diferencia,
            cantidad_ajuste: diferencia,
            costo_unitario: costo,
            costo_total: Math.abs(diferencia) * costo,
            observacion: ''
        };
        
        detalles.push(nuevoDetalle);
        renderizarDetalles();
        
        $('#busqueda_producto').val('');
        $('#producto_seleccionado_id').val('');
        $('#producto_stock_sistema').val('0.000000');
        $('#producto_stock_fisico').val('0.000000');
        $('#producto_diferencia').val('0.000000');
        $('#producto_costo').val('0.000000');
        
        $('#busqueda_producto').focus();
    });

    function renderizarDetalles() {
        $('#contenedor-detalles').empty();
        
        if (detalles.length === 0) {
            var htmlVacio = `
            <div class="detalles-vacio text-center p-4 border rounded bg-light">
                <i class="fas fa-box-open fa-2x text-muted mb-2"></i>
                <p class="mb-0">No hay productos agregados</p>
                <small class="text-muted">Busque y agregue productos al ajuste</small>
            </div>`;
            $('#contenedor-detalles').html(htmlVacio);
            return;
        }
        
        var html = `
        <table class="table table-sm table-bordered table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Producto</th>
                    <th class="text-end">Stock Sistema</th>
                    <th class="text-end">Stock Físico</th>
                    <th class="text-end">Diferencia</th>
                    <th class="text-end">Cant. Ajuste</th>
                    <th class="text-end">Costo Unit.</th>
                    <th class="text-end">Costo Total</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>`;
        
        detalles.forEach(function(detalle) {
            var esNuevo = detalle.stock_ajuste_detalle_id === 0;
            var claseFila = esNuevo ? 'table-info' : '';
            
            html += `
            <tr class="${claseFila}" data-idx="${detalle.detalle_idx}">
                <td>
                    <div class="fw-bold">${detalle.producto_nombre}</div>
                    ${esNuevo ? '<span class="badge bg-info ms-2">Nuevo</span>' : ''}
                </td>
                <td class="text-end">${detalle.stock_sistema.toFixed(6)}</td>
                <td class="text-end">${detalle.stock_fisico.toFixed(6)}</td>
                <td class="text-end ${detalle.diferencia < 0 ? 'text-danger' : detalle.diferencia > 0 ? 'text-success' : ''}">${detalle.diferencia.toFixed(6)}</td>
                <td class="text-end">${detalle.cantidad_ajuste.toFixed(6)}</td>
                <td class="text-end">$${detalle.costo_unitario.toFixed(6)}</td>
                <td class="text-end fw-bold text-primary">$${detalle.costo_total.toFixed(6)}</td>
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
        
        html += `</tbody></table>`;
        $('#contenedor-detalles').html(html);
    }

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
                Swal.fire({
                    icon: 'success',
                    title: 'Eliminado',
                    text: 'Producto eliminado del detalle',
                    showConfirmButton: false,
                    timer: 1500,
                    toast: true,
                    position: 'top-end'
                });
            }
        });
    });

    $(document).on('click', '.btn-editar-detalle', function() {
        var idx = $(this).data('idx');
        var detalle = detalles.find(function(item) {
            return item.detalle_idx == idx;
        });

        if (detalle) {
            $('#busqueda_producto').val(detalle.producto_nombre);
            $('#producto_seleccionado_id').val(detalle.producto_id);
            $('#producto_stock_sistema').val(detalle.stock_sistema.toFixed(6));
            $('#producto_stock_fisico').val(detalle.stock_fisico.toFixed(6));
            $('#producto_diferencia').val(detalle.diferencia.toFixed(6));
            $('#producto_costo').val(detalle.costo_unitario.toFixed(6));
            
            detalles = detalles.filter(function(item) {
                return item.detalle_idx != idx;
            });
            renderizarDetalles();
            $('#busqueda_producto').focus();
        }
    });

    function resetModal() {
        $('#formStockAjuste')[0].reset();
        $('#stock_ajuste_id').val('');
        $('#deposito_id').html('<option value="">Primero seleccione sucursal</option>');
        $('#deposito_id').prop('disabled', true);
        $('#formStockAjuste').removeClass('was-validated');
        
        detalles = [];
        renderizarDetalles();
        
        $('.btn-secondary[data-bs-dismiss="modal"]').show();
        $('.btn-editar-detalle, .btn-eliminar-detalle, #btnAgregarProducto').show();
        $('#busqueda_producto').prop('disabled', false);
    }

    $(document).on('click', '#btnNuevo', function () {
        resetModal();
        $('#modalLabel').text('Nuevo Ajuste de Stock');
        cargarCombosFormulario();
        
        var today = new Date().toISOString().slice(0, 16);
        $('#fecha').val(today);

        var modal = new bootstrap.Modal(document.getElementById('modalStockAjuste'));
        modal.show();
    });

    function cargarAjusteParaEditar(id) {
        $.get('stock_ajustes_ajax.php', {
            accion: 'obtener',
            stock_ajuste_id: id,
            empresa_idx: empresa_idx
        }, function (res) {
            if (res && res.stock_ajuste_id) {
                resetModal();
                cargarCombosFormulario();
                
                $('#stock_ajuste_id').val(res.stock_ajuste_id);
                $('#comprobante_tipo_id').val(res.comprobante_tipo_id);
                $('#fecha').val(res.fecha.replace(' ', 'T'));
                $('#descripcion').val(res.descripcion);
                $('#modalLabel').text('Editar Ajuste de Stock');

                setTimeout(function() {
                    if (res.sucursal_id) {
                        $('#sucursal_id').val(res.sucursal_id);
                        cargarDepositos(res.sucursal_id, res.deposito_id);
                    }
                    
                    if (res.detalles && res.detalles.length > 0) {
                        detalles = res.detalles.map(function(detalle, index) {
                            return {
                                detalle_idx: index,
                                stock_ajuste_detalle_id: detalle.stock_ajuste_detalle_id,
                                producto_id: detalle.producto_id,
                                producto_nombre: detalle.producto_nombre,
                                deposito_id: detalle.deposito_id,
                                stock_sistema: parseFloat(detalle.stock_sistema || 0),
                                stock_fisico: parseFloat(detalle.stock_fisico || 0),
                                diferencia: parseFloat(detalle.diferencia || 0),
                                cantidad_ajuste: parseFloat(detalle.cantidad_ajuste || 0),
                                costo_unitario: parseFloat(detalle.costo_unitario || 0),
                                costo_total: parseFloat(detalle.costo_total || 0),
                                observacion: detalle.observacion || ''
                            };
                        });
                        renderizarDetalles();
                    }
                }, 300);

                var modal = new bootstrap.Modal(document.getElementById('modalStockAjuste'));
                modal.show();
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos del ajuste",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    }

    function cargarAjusteParaVisualizar(id) {
        $.get('stock_ajustes_ajax.php', {
            accion: 'obtener',
            stock_ajuste_id: id,
            empresa_idx: empresa_idx
        }, function (res) {
            if (res && res.stock_ajuste_id) {
                resetModal();
                cargarCombosFormulario();
                
                $('#stock_ajuste_id').val(res.stock_ajuste_id);
                $('#comprobante_tipo_id').val(res.comprobante_tipo_id);
                $('#fecha').val(res.fecha.replace(' ', 'T'));
                $('#descripcion').val(res.descripcion);
                $('#modalLabel').text('Visualizar Ajuste de Stock');

                setTimeout(function() {
                    if (res.sucursal_id) {
                        $('#sucursal_id').val(res.sucursal_id);
                        cargarDepositos(res.sucursal_id, res.deposito_id);
                    }
                    
                    if (res.detalles && res.detalles.length > 0) {
                        detalles = res.detalles.map(function(detalle, index) {
                            return {
                                detalle_idx: index,
                                stock_ajuste_detalle_id: detalle.stock_ajuste_detalle_id,
                                producto_id: detalle.producto_id,
                                producto_nombre: detalle.producto_nombre,
                                deposito_id: detalle.deposito_id,
                                stock_sistema: parseFloat(detalle.stock_sistema || 0),
                                stock_fisico: parseFloat(detalle.stock_fisico || 0),
                                diferencia: parseFloat(detalle.diferencia || 0),
                                cantidad_ajuste: parseFloat(detalle.cantidad_ajuste || 0),
                                costo_unitario: parseFloat(detalle.costo_unitario || 0),
                                costo_total: parseFloat(detalle.costo_total || 0),
                                observacion: detalle.observacion || ''
                            };
                        });
                        renderizarDetalles();
                    }

                    $('#formStockAjuste :input').prop('disabled', true);
                    $('.btn-eliminar-detalle, .btn-editar-detalle, #btnAgregarProducto').hide();
                    $('#busqueda_producto').prop('disabled', true);
                    $('#btnGuardar').hide();
                    $('.modal-footer .btn-secondary').hide();
                    $('.btn-secondary[data-bs-dismiss="modal"]').hide();
                    $('#btnToggleFullscreen').prop('disabled', false);
                }, 300);

                var modal = new bootstrap.Modal(document.getElementById('modalStockAjuste'));
                modal.show();

                $('#modalStockAjuste').off('hidden.bs.modal').on('hidden.bs.modal', function () {
                    $('#formStockAjuste :input').prop('disabled', false);
                    $('#btnGuardar').show();
                    $('.modal-footer .btn-secondary').show();
                    $('.btn-eliminar-detalle, .btn-editar-detalle, #btnAgregarProducto').show();
                    $('#busqueda_producto').prop('disabled', false);
                    $('.btn-secondary[data-bs-dismiss="modal"]').show();
                });
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos del ajuste",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    }

    $('#btnGuardar').click(function() {
        var form = document.getElementById('formStockAjuste');
        
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
        
        var id = $('#stock_ajuste_id').val();
        var accionBackend = id ? 'editar' : 'agregar';
        
        var btnGuardar = $(this);
        var originalText = btnGuardar.html();
        btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
        
        var formData = new FormData();
        formData.append('accion', accionBackend);
        formData.append('empresa_idx', empresa_idx);
        formData.append('pagina_idx', pagina_idx);
        formData.append('stock_ajuste_id', $('#stock_ajuste_id').val() || '');
        formData.append('sucursal_id', $('#sucursal_id').val() || '');
        formData.append('deposito_id', $('#deposito_id').val() || '');
        formData.append('comprobante_tipo_id', $('#comprobante_tipo_id').val() || '');
        formData.append('fecha', $('#fecha').val() || '');
        formData.append('descripcion', $('#descripcion').val() || '');
        formData.append('detalles', JSON.stringify(detalles));

        var savedState = {
            page: tabla ? tabla.page() : 0,
            order: tabla ? tabla.order() : [[1, 'asc']],
            search: tabla ? tabla.search() : ''
        };
        
        $.ajax({
            url: 'stock_ajustes_ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                btnGuardar.prop('disabled', false).html(originalText);
                
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
                    
                    Swal.fire({
                        icon: "success",
                        title: "¡Guardado!",
                        text: "Ajuste de stock guardado correctamente",
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
                    
                    var modalEl = document.getElementById('modalStockAjuste');
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) {
                        modal.hide();
                    } else {
                        modal = new bootstrap.Modal(modalEl);
                        modal.hide();
                    }
                    
                    $('body').removeClass('modal-open');
                    $('.modal-backdrop').remove();
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

    $('#btnToggleFullscreen').click(function() {
        var modalDialog = $('#modalStockAjuste .modal-dialog');
        var btnIcon = $(this).find('i');
        
        if (modalDialog.hasClass('modal-fullscreen')) {
            modalDialog.removeClass('modal-fullscreen');
            btnIcon.removeClass('fa-compress').addClass('fa-expand');
        } else {
            modalDialog.addClass('modal-fullscreen');
            btnIcon.removeClass('fa-expand').addClass('fa-compress');
        }
    });

    inicializarDataTable();
    cargarBotonAgregar();

    $('[title]').tooltip({
        trigger: 'hover',
        placement: 'top'
    });
});