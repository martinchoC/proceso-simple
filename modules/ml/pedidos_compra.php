<?php
// Configuración de la página
$pageTitle = "Pedidos de Compra";
$currentPage = 'compras';
$modudo_idx = 2;
$empresa_idx = 2;

// Definir constante para rutas
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));

// Incluir header
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Pedidos de Compra</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Compras</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Pedidos de Compra</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="content-wrapper">
                <section class="content">
                    <div class="container-fluid">                      
                        <div class="row">
                            <div class="col-12">
                                <div class="card">                                
                                    <div class="card-body">
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nuevo Pedido</button>
                                        <table id="tablaPedidosCompra" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Número</th>
                                                    <th>Proveedor</th>
                                                    <th>Fecha Emisión</th>
                                                    <th>Fecha Vto.</th>
                                                    <th>Tipo</th>
                                                    <th>Total</th>
                                                    <th>Estado</th>
                                                    <th>Acciones</th>
                                                </tr>                                            
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Modal Principal -->
            <div class="modal fade" id="modalPedidoCompra" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Pedido de Compra</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formPedidoCompra">
                                <input type="hidden" id="comprobante_id" name="comprobante_id" />
                                <input type="hidden" id="empresa_id" name="empresa_id" value="<?php echo $empresa_idx; ?>" />
                                
                                <!-- Cabecera -->
                                <div class="row g-3 mb-4">
                                    <div class="col-md-3">
                                        <label>Tipo de Comprobante *</label>
                                        <select class="form-control" id="comprobante_tipo_id" name="comprobante_tipo_id" required>
                                            <option value="">Seleccionar tipo</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label>Número *</label>
                                        <input type="number" class="form-control" id="numero_comprobante" name="numero_comprobante" required min="1"/>
                                    </div>
                                    <div class="col-md-3">
                                        <label>Punto de Venta</label>
                                        <input type="number" class="form-control" id="punto_venta_id" name="punto_venta_id" value="1"/>
                                    </div>
                                    <div class="col-md-3">
                                        <label>Sucursal *</label>
                                        <select class="form-control" id="sucursal_id" name="sucursal_id" required>
                                            <option value="">Seleccionar sucursal</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Proveedor *</label>
                                        <select class="form-control" id="entidad_id" name="entidad_id" required>
                                            <option value="">Seleccionar proveedor</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label>Fecha Emisión *</label>
                                        <input type="date" class="form-control" id="f_emision" name="f_emision" required value="<?php echo date('Y-m-d'); ?>"/>
                                    </div>
                                    <div class="col-md-2">
                                        <label>Fecha Contab.</label>
                                        <input type="date" class="form-control" id="f_contabilizacion" name="f_contabilizacion" value="<?php echo date('Y-m-d'); ?>"/>
                                    </div>
                                    <div class="col-md-2">
                                        <label>Fecha Vto.</label>
                                        <input type="date" class="form-control" id="f_vto" name="f_vto" value="<?php echo date('Y-m-d'); ?>"/>
                                    </div>
                                    <div class="col-md-12">
                                        <label>Observaciones</label>
                                        <textarea class="form-control" id="observaciones" name="observaciones" rows="2"></textarea>
                                    </div>
                                </div>

                                <!-- Detalles -->
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5>Detalles del Pedido</h5>
                                            <button type="button" class="btn btn-success btn-sm" id="btnAgregarProducto">
                                                <i class="fa fa-plus"></i> Agregar Producto
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-bordered" id="tablaDetalles">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="5%">#</th>
                                                <th width="35%">Producto</th>
                                                <th width="15%">Cantidad</th>
                                                <th width="15%">Precio Unit.</th>
                                                <th width="15%">Descuento</th>
                                                <th width="15%">Subtotal</th>
                                                <th width="5%">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="cuerpoDetalles">
                                            <!-- Las filas de detalles se agregarán aquí dinámicamente -->
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="5" class="text-end"><strong>Total:</strong></td>
                                                <td><strong><span id="totalPedido">0.00</span></strong></td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-md-4">
                                        <label>Importe Neto</label>
                                        <input type="number" class="form-control" id="importe_neto" name="importe_neto" step="0.01" value="0" readonly/>
                                    </div>
                                    <div class="col-md-4">
                                        <label>Importe No Gravado</label>
                                        <input type="number" class="form-control" id="importe_no_gravado" name="importe_no_gravado" step="0.01" value="0"/>
                                    </div>
                                    <div class="col-md-4">
                                        <label>Total</label>
                                        <input type="number" class="form-control" id="total" name="total" step="0.01" value="0" readonly/>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-md-4">
                                        <label>Estado</label>
                                        <select class="form-control" id="estado_registro_id" name="estado_registro_id">
                                            <option value="1">Activo</option>
                                            <option value="0">Inactivo</option>
                                        </select>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button id="btnGuardar" class="btn btn-success">Guardar</button>
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
$(document).ready(function(){
    let contadorDetalles = 0;
    let productosDisponibles = [];

    // Cargar combos
    function cargarCombos() {
        // Cargar tipos de comprobante
        $.get('pedidos_compra_ajax.php', {accion: 'listar_tipos'}, function(res){
            var options = '<option value="">Seleccionar tipo</option>';
            $.each(res, function(index, tipo){
                options += '<option value="' + tipo.comprobante_tipo_id + '">' + tipo.comprobante_tipo + '</option>';
            });
            $('#comprobante_tipo_id').html(options);
        }, 'json');

        // Cargar proveedores
        $.get('pedidos_compra_ajax.php', {
            accion: 'listar_proveedores',
            empresa_id: <?php echo $empresa_idx; ?>
        }, function(res){
            var options = '<option value="">Seleccionar proveedor</option>';
            $.each(res, function(index, proveedor){
                options += '<option value="' + proveedor.entidad_id + '">' + proveedor.razon_social + '</option>';
            });
            $('#entidad_id').html(options);
        }, 'json');

        // Cargar sucursales
        $.get('pedidos_compra_ajax.php', {
            accion: 'listar_sucursales',
            empresa_id: <?php echo $empresa_idx; ?>
        }, function(res){
            var options = '<option value="">Seleccionar sucursal</option>';
            $.each(res, function(index, sucursal){
                options += '<option value="' + sucursal.sucursal_id + '">' + sucursal.sucursal + '</option>';
            });
            $('#sucursal_id').html(options);
        }, 'json');

        // Cargar productos
        $.get('pedidos_compra_ajax.php', {
            accion: 'listar_productos',
            empresa_id: <?php echo $empresa_idx; ?>
        }, function(res){
            productosDisponibles = res;
        }, 'json');
    }

    // Configuración de DataTable
    var tabla = $('#tablaPedidosCompra').DataTable({
        dom: '<"row"<"col-md-6"l><"col-md-6"fB>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel"></i> Excel',
                titleAttr: 'Exportar a Excel',
                className: 'btn btn-success btn-sm me-2',
                exportOptions: { columns: ':visible' }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                titleAttr: 'Exportar a PDF',
                className: 'btn btn-danger btn-sm',
                orientation: 'portrait',
                pageSize: 'A4',
                exportOptions: { columns: ':visible' }
            }
        ],
        ajax: {
            url: 'pedidos_compra_ajax.php',
            type: 'GET',
            data: {accion: 'listar', empresa_id: <?php echo $empresa_idx; ?>},
            dataSrc: ''
        },
        language: {
            "search": "Buscar:",
            "searchPlaceholder": "Buscar pedidos...",
            "lengthMenu": "Mostrar _MENU_ registros por página",
            "zeroRecords": "No se encontraron pedidos",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ pedidos",
            "infoEmpty": "Mostrando 0 a 0 de 0 pedidos",
            "infoFiltered": "(filtrado de _MAX_ pedidos totales)",
            "paginate": {
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            }
        },
        columns: [
            { data: 'comprobante_id' },
            { 
                data: 'numero_comprobante',
                render: function(data, type, row) {
                    return row.punto_venta_id + '-' + data.toString().padStart(8, '0');
                }
            },
            { data: 'proveedor_nombre' },
            { data: 'f_emision' },
            { data: 'f_vto' },
            { data: 'comprobante_tipo' },
            { 
                data: 'total',
                render: function(data) {
                    return '$ ' + parseFloat(data).toLocaleString('es-AR', {minimumFractionDigits: 2});
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: "text-center",
                render: function(data){
                    var estadoTexto = data.estado_registro_id == 1 ? 
                        '<span class="badge bg-success">Activo</span>' : 
                        '<span class="badge bg-secondary">Inactivo</span>';
                    
                    var botonEstado = 
                        `<div class="form-check form-switch d-inline-block">
                            <input class="form-check-input toggle-estado"
                                type="checkbox" 
                                data-pedido-id="${data.comprobante_id}" 
                                ${data.estado_registro_id == 1 ? 'checked' : ''}>
                        </div>`;
                    
                    return `<div class="d-flex flex-column align-items-center">                                            
                                ${botonEstado}
                            </div>`;
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: "text-center",
                render: function(data){
                    var botonEditar = data.estado_registro_id == 1 ? 
                        `<button class="btn btn-sm btn-primary btnEditar" title="Editar">
                            <i class="fa fa-edit"></i>
                         </button>` : 
                        `<button class="btn btn-sm btn-secondary" title="Editar no disponible" disabled>
                            <i class="fa fa-edit"></i>
                         </button>`;
                    
                    var botonEliminar = data.estado_registro_id == 1 ? 
                        `<button class="btn btn-sm btn-danger btnEliminar" title="Eliminar">
                            <i class="fa fa-trash"></i>
                         </button>` : 
                        `<button class="btn btn-sm btn-secondary" title="Eliminar no disponible" disabled>
                            <i class="fa fa-trash"></i>
                         </button>`;
                    
                    return `<div class="d-flex align-items-center justify-content-center gap-2">${botonEditar} ${botonEliminar}</div>`;
                }
            }
        ],
        createdRow: function(row, data, dataIndex) {
            if (data.estado_registro_id != 1) {
                $(row).addClass('table-secondary');
                $(row).find('td').css('color', '#6c757d');
            }
        }
    });

    // Agregar fila de detalle
    function agregarFilaDetalle(detalle = null) {
        contadorDetalles++;
        const index = contadorDetalles;
        
        let productoOptions = '<option value="">Seleccionar producto</option>';
        productosDisponibles.forEach(producto => {
            const selected = detalle && detalle.producto_id == producto.producto_id ? 'selected' : '';
            productoOptions += `<option value="${producto.producto_id}" data-precio="${producto.precio_compra}" ${selected}>${producto.producto} (${producto.codigo})</option>`;
        });

        const fila = `
            <tr id="filaDetalle_${index}">
                <td>${index}</td>
                <td>
                    <select class="form-control producto-select" name="detalles[${index}][producto_id]" required>
                        ${productoOptions}
                    </select>
                </td>
                <td>
                    <input type="number" class="form-control cantidad" name="detalles[${index}][cantidad]" 
                           value="${detalle ? detalle.cantidad : 1}" step="0.01" min="0.01" required>
                </td>
                <td>
                    <input type="number" class="form-control precio" name="detalles[${index}][precio_unitario]" 
                           value="${detalle ? detalle.precio_unitario : ''}" step="0.01" min="0" required>
                </td>
                <td>
                    <input type="number" class="form-control descuento" name="detalles[${index}][descuento]" 
                           value="${detalle ? detalle.descuento : 0}" step="0.01" min="0">
                </td>
                <td>
                    <span class="subtotal">0.00</span>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger btnEliminarFila" data-index="${index}">
                        <i class="fa fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        
        $('#cuerpoDetalles').append(fila);
        
        // Si hay detalle, calcular subtotal
        if (detalle) {
            calcularSubtotal(index);
        }
        
        // Agregar eventos
        $(`#filaDetalle_${index} .producto-select`).change(function() {
            const precio = $(this).find(':selected').data('precio');
            if (precio) {
                $(this).closest('tr').find('.precio').val(precio);
            }
            calcularSubtotal(index);
        });
        
        $(`#filaDetalle_${index} .cantidad, #filaDetalle_${index} .precio, #filaDetalle_${index} .descuento`).on('input', function() {
            calcularSubtotal(index);
        });
    }

    // Calcular subtotal de una fila
    function calcularSubtotal(index) {
        const fila = $(`#filaDetalle_${index}`);
        const cantidad = parseFloat(fila.find('.cantidad').val()) || 0;
        const precio = parseFloat(fila.find('.precio').val()) || 0;
        const descuento = parseFloat(fila.find('.descuento').val()) || 0;
        
        const subtotal = (cantidad * precio) - descuento;
        fila.find('.subtotal').text(subtotal.toFixed(2));
        
        calcularTotales();
    }

    // Calcular totales generales
    function calcularTotales() {
        let totalNeto = 0;
        
        $('.subtotal').each(function() {
            totalNeto += parseFloat($(this).text()) || 0;
        });
        
        const importeNoGravado = parseFloat($('#importe_no_gravado').val()) || 0;
        const totalGeneral = totalNeto + importeNoGravado;
        
        $('#importe_neto').val(totalNeto.toFixed(2));
        $('#total').val(totalGeneral.toFixed(2));
        $('#totalPedido').text(totalGeneral.toFixed(2));
    }

    // Obtener próximo número de comprobante
    function obtenerProximoNumero() {
        const tipoId = $('#comprobante_tipo_id').val();
        const puntoVenta = $('#punto_venta_id').val();
        
        if (tipoId && puntoVenta) {
            $.get('pedidos_compra_ajax.php', {
                accion: 'obtener_proximo_numero',
                comprobante_tipo_id: tipoId,
                punto_venta_id: puntoVenta
            }, function(res) {
                if (res.proximo_numero) {
                    $('#numero_comprobante').val(res.proximo_numero);
                }
            }, 'json');
        }
    }

    // Eventos
    $('#btnNuevo').click(function(){
        $('#formPedidoCompra')[0].reset();
        $('#comprobante_id').val('');
        $('#modalLabel').text('Nuevo Pedido de Compra');
        $('#estado_registro_id').val('1');
        $('#cuerpoDetalles').empty();
        contadorDetalles = 0;
        
        // Agregar una fila vacía
        agregarFilaDetalle();
        
        cargarCombos();
        var modal = new bootstrap.Modal(document.getElementById('modalPedidoCompra'));
        modal.show();
    });

    $('#btnAgregarProducto').click(function(){
        agregarFilaDetalle();
    });

    $(document).on('click', '.btnEliminarFila', function(){
        const index = $(this).data('index');
        $(`#filaDetalle_${index}`).remove();
        recalcularNumeracion();
        calcularTotales();
    });

    $('#comprobante_tipo_id').change(function(){
        obtenerProximoNumero();
    });

    $('#punto_venta_id').change(function(){
        obtenerProximoNumero();
    });

    $('#importe_no_gravado').on('input', calcularTotales);

    // Guardar pedido
    $('#btnGuardar').click(function(){
        const form = $('#formPedidoCompra')[0];
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }
        
        // Validar que haya al menos un detalle
        if ($('.producto-select').length === 0) {
            Swal.fire({
                icon: "error",
                title: "Error",
                text: "Debe agregar al menos un producto al pedido"
            });
            return;
        }
        
        const id = $('#comprobante_id').val();
        const accion = id ? 'editar' : 'agregar';
        
        const formData = new FormData(form);
        formData.append('accion', accion);
        
        // Agregar detalles al formData
        const detalles = [];
        $('tr[id^="filaDetalle_"]').each(function() {
            const productoId = $(this).find('.producto-select').val();
            const cantidad = $(this).find('.cantidad').val();
            const precio = $(this).find('.precio').val();
            const descuento = $(this).find('.descuento').val();
            
            if (productoId && cantidad && precio) {
                detalles.push({
                    producto_id: productoId,
                    cantidad: cantidad,
                    precio_unitario: precio,
                    descuento: descuento || 0
                });
            }
        });
        
        formData.append('detalles', JSON.stringify(detalles));
        
        $.ajax({
            url: 'pedidos_compra_ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res){
                if(res.resultado){
                    tabla.ajax.reload();
                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalPedidoCompra'));
                    modal.hide();
                    
                    Swal.fire({
                        icon: "success",
                        title: "¡Éxito!",
                        text: id ? "Pedido actualizado correctamente" : "Pedido creado correctamente",
                        showConfirmButton: false,
                        timer: 1500
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || "Error al guardar el pedido"
                    });
                }
            }
        });
    });

    // Cargar combos al inicio
    cargarCombos();
});
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php
// Incluir footer
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>