<?php
// Configuración de la página
$pageTitle = "Gestión de Pedidos de Compra";
$currentPage = 'paginas';
$modudo_idx = 2;
$empresa_idx = 2;
$usuario_id = 1;

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
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
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
                                        <button class="btn btn-primary mb-3" id="btnNuevoPedido">
                                            <i class="fas fa-plus"></i> Nuevo Pedido
                                        </button>
                                        <table id="tablaComprasPedidos" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>N° Pedido</th>
                                                    <th>Fecha Emisión</th>
                                                    <th>Proveedor</th>
                                                    <th>Sucursal</th>
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
            <div class="modal fade" id="modalCompraPedido" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Nuevo Pedido de Compra</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formCompraPedido">
                                <input type="hidden" id="comprobante_id" name="comprobante_id" />
                                <input type="hidden" id="importe_neto" name="importe_neto" value="0" />
                                <input type="hidden" id="importe_no_gravado" name="importe_no_gravado" value="0" />
                                <input type="hidden" id="punto_venta_id" name="punto_venta_id" value="1" />
                                
                                <!-- Cabecera del Pedido -->
                                <div class="row g-3 mb-4">
                                    <div class="col-md-12">
                                        <div class="alert alert-info">
                                            <small>
                                                <strong>Información:</strong> Los pedidos se crean como <span class="badge bg-warning">Borrador</span>. 
                                                Una vez confirmados, no podrán ser modificados.
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label>Sucursal *</label>
                                        <select class="form-control" id="sucursal_id" name="sucursal_id" required>
                                            <option value="">Seleccionar...</option>
                                        </select>                                        
                                    </div>
                                    <div class="col-md-3">
                                        <label>Tipo de Pedido *</label>
                                        <select class="form-control" id="comprobante_tipo_id" name="comprobante_tipo_id" required>
                                            <option value="">Seleccionar...</option>
                                        </select>                                        
                                    </div>
                                    <div class="col-md-3">
                                        <label>Número de Pedido *</label>
                                        <input type="number" class="form-control" id="numero_comprobante" 
                                               name="numero_comprobante" required min="1" readonly />
                                        
                                    </div>
                                    <div class="col-md-3">
                                        <label>Fecha Emisión *</label>
                                        <input type="date" class="form-control" id="f_emision" 
                                               name="f_emision" required />
                                        
                                    </div>
                                    <div class="col-md-3">
                                        <label>Fecha Vencimiento *</label>
                                        <input type="date" class="form-control" id="f_vto" 
                                               name="f_vto" required />
                                        
                                    </div>
                                    <div class="col-md-6">
                                        <label>Proveedor *</label>
                                        <select class="form-control" id="entidad_id" name="entidad_id" required>
                                            <option value="">Seleccionar...</option>
                                        </select>                                        
                                    </div>
                                    <div class="col-md-3">
                                        <label>Fecha Contabilización</label>
                                        <input type="date" class="form-control" id="f_contabilizacion" 
                                               name="f_contabilizacion" />
                                    </div>
                                    <div class="col-12">
                                        <label>Observaciones</label>
                                        <textarea class="form-control" id="observaciones" name="observaciones" 
                                                  rows="2" maxlength="255"></textarea>
                                    </div>
                                </div>

                                <!-- Detalles del Pedido -->
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <h5>Productos del Pedido</h5>
                                        <button type="button" class="btn btn-success btn-sm mb-2" id="btnAgregarProducto">
                                            <i class="fas fa-plus"></i> Agregar Producto
                                        </button>
                                        
                                        <div class="table-responsive">
                                            <table class="table table-bordered" id="tablaDetalles">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th width="40%">Producto</th>
                                                        <th width="15%">Cantidad</th>
                                                        <th width="15%">Precio Unit.</th>
                                                        <th width="15%">Descuento</th>
                                                        <th width="10%">Subtotal</th>
                                                        <th width="5%"></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="tbodyDetalles">
                                                    <!-- Las filas de productos se agregarán aquí dinámicamente -->
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <td colspan="4" class="text-end"><strong>Total:</strong></td>
                                                        <td colspan="2">
                                                            <strong id="totalPedido">$ 0.00</strong>
                                                            <input type="hidden" id="total" name="total" value="0" />
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button id="btnGuardarPedido" class="btn btn-success">
                                <i class="fas fa-save"></i> Guardar Pedido
                            </button>
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function(){
        let datosMaestros = {};
        let detalles = [];
        
        // Configuración de DataTable
        var tabla = $('#tablaComprasPedidos').DataTable({
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
                url: 'compras_pedidos_ajax.php',
                type: 'GET',
                data: {accion: 'listar'},
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
                { 
                    data: 'numero_comprobante',
                    render: function(data) {
                        return 'PED-' + data.toString().padStart(6, '0');
                    }
                },
                { 
                    data: 'f_emision',
                    render: function(data) {
                        return new Date(data).toLocaleDateString('es-ES');
                    }
                },
                { data: 'proveedor' },
                { data: 'sucursal_nombre' },
                { data: 'comprobante_tipo' },
                { 
                    data: 'total',
                    render: function(data) {
                        return '$ ' + parseFloat(data).toLocaleString('es-ES', {minimumFractionDigits: 2});
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    render: function(data){
                        var badgeClass = '';
                        var estadoTexto = data.estado_display || data.estado_nombre;
                        
                        switch(data.estado_registro_id) {
                            case 3: // Borrador
                                badgeClass = 'bg-warning';
                                break;
                            case 4: // Pendiente
                                badgeClass = 'bg-info';
                                break;
                            case 5: // Confirmado
                                badgeClass = 'bg-success';
                                break;
                            case 6: // Eliminado
                                badgeClass = 'bg-secondary';
                                break;
                            default:
                                badgeClass = 'bg-light text-dark';
                        }
                        
                        return `<span class="badge ${badgeClass}">${estadoTexto}</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    render: function(data){
                        var botones = '';
                        
                        // Botón Editar - Solo para borradores (estado 3)
                        if (data.estado_registro_id == 3) {
                            botones += `<button class="btn btn-sm btn-primary btnEditar" title="Editar" data-id="${data.comprobante_id}">
                                            <i class="fa fa-edit"></i>
                                        </button>`;
                        } else {
                            botones += `<button class="btn btn-sm btn-secondary" title="Editar no disponible" disabled>
                                            <i class="fa fa-edit"></i>
                                        </button>`;
                        }
                        
                        // Botón Confirmar - Solo para borradores (estado 3)
                        if (data.estado_registro_id == 3) {
                            botones += `<button class="btn btn-sm btn-success btnConfirmar" title="Confirmar" data-id="${data.comprobante_id}">
                                            <i class="fa fa-check"></i>
                                        </button>`;
                        } else {
                            botones += `<button class="btn btn-sm btn-secondary" title="Confirmar no disponible" disabled>
                                            <i class="fa fa-check"></i>
                                        </button>`;
                        }
                        
                        // Botón Pendiente - Solo para borradores (estado 3)
                        if (data.estado_registro_id == 3) {
                            botones += `<button class="btn btn-sm btn-info btnPendiente" title="Marcar como Pendiente" data-id="${data.comprobante_id}">
                                            <i class="fa fa-clock"></i>
                                        </button>`;
                        } else {
                            botones += `<button class="btn btn-sm btn-secondary" title="Pendiente no disponible" disabled>
                                            <i class="fa fa-clock"></i>
                                        </button>`;
                        }
                        
                        // Botón Eliminar - Para todos los estados excepto eliminado (estado 6)
                        if (data.estado_registro_id != 6) {
                            botones += `<button class="btn btn-sm btn-danger btnEliminar" title="Eliminar" data-id="${data.comprobante_id}">
                                            <i class="fa fa-trash"></i>
                                        </button>`;
                        } else {
                            botones += `<button class="btn btn-sm btn-secondary" title="Eliminar no disponible" disabled>
                                            <i class="fa fa-trash"></i>
                                        </button>`;
                        }
                        
                        return `<div class="d-flex align-items-center justify-content-center gap-2">${botones}</div>`;
                    }
                }
            ],
            createdRow: function(row, data, dataIndex) {
                // Cambiar color de fondo según el estado
                if (data.estado_registro_id == 6) { // Eliminado
                    $(row).addClass('table-secondary');
                    $(row).find('td').css('color', '#6c757d');
                } else if (data.estado_registro_id == 5) { // Confirmado
                    $(row).addClass('table-success');
                    $(row).find('td').css('color', '#155724');
                } else if (data.estado_registro_id == 4) { // Pendiente
                    $(row).addClass('table-info');
                    $(row).find('td').css('color', '#0c5460');
                }
            }
        });

        // Cargar datos maestros
        function cargarDatosMaestros() {
            $.get('compras_pedidos_ajax.php', {accion: 'obtener_datos_maestros'}, function(res){
                datosMaestros = res;
                
                // Llenar sucursales
                $('#sucursal_id').empty().append('<option value="">Seleccionar...</option>');
                $.each(res.sucursales, function(i, sucursal){
                    $('#sucursal_id').append(`<option value="${sucursal.sucursal_id}">${sucursal.sucursal_nombre}</option>`);
                });
                
                // Llenar tipos de pedido
                $('#comprobante_tipo_id').empty().append('<option value="">Seleccionar...</option>');
                $.each(res.tipos, function(i, tipo){
                    $('#comprobante_tipo_id').append(`<option value="${tipo.comprobante_tipo_id}">${tipo.comprobante_tipo}</option>`);
                });
                
                // Llenar proveedores
                $('#entidad_id').empty().append('<option value="">Seleccionar...</option>');
                $.each(res.proveedores, function(i, proveedor){
                    $('#entidad_id').append(`<option value="${proveedor.entidad_id}">${proveedor.entidad_nombre}</option>`);
                });
            }, 'json');
        }

        // Obtener siguiente número de comprobante
        function obtenerSiguienteNumero(sucursalId, tipoId) {
            if (!sucursalId || !tipoId) return;
            
            $.get('compras_pedidos_ajax.php', {
                accion: 'obtener_siguiente_numero',
                sucursal_id: sucursalId,
                comprobante_tipo_id: tipoId
            }, function(res){
                $('#numero_comprobante').val(res.siguiente_numero);
            }, 'json');
        }

        // Agregar fila de producto
        function agregarFilaProducto(detalle = null) {
            const index = detalles.length;
            const productoId = detalle ? detalle.producto_id : '';
            const cantidad = detalle ? detalle.cantidad : 1;
            const precio = detalle ? detalle.precio_unitario : 0;
            const descuento = detalle ? detalle.descuento : 0;
            
            const fila = `
                <tr data-index="${index}">
                    <td>
                        <select class="form-control producto-select" name="detalles[${index}][producto_id]" required>
                            <option value="">Seleccionar...</option>
                            ${datosMaestros.productos ? datosMaestros.productos.map(p => 
                                `<option value="${p.producto_id}" ${p.producto_id == productoId ? 'selected' : ''}>
                                    ${p.producto_nombre} (${p.codigo_barras || 'Sin código'})
                                </option>`
                            ).join('') : ''}
                        </select>
                    </td>
                    <td>
                        <input type="number" class="form-control cantidad" 
                               name="detalles[${index}][cantidad]" 
                               value="${cantidad}" min="0.01" step="0.01" required>
                    </td>
                    <td>
                        <input type="number" class="form-control precio" 
                               name="detalles[${index}][precio_unitario]" 
                               value="${precio}" min="0" step="0.01" required>
                    </td>
                    <td>
                        <input type="number" class="form-control descuento" 
                               name="detalles[${index}][descuento]" 
                               value="${descuento}" min="0" step="0.01">
                    </td>
                    <td>
                        <span class="subtotal">$ 0.00</span>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-danger btnEliminarFila">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            
            $('#tbodyDetalles').append(fila);
            
            if (detalle) {
                detalles.push(detalle);
            } else {
                detalles.push({
                    producto_id: '',
                    cantidad: 1,
                    precio_unitario: 0,
                    descuento: 0
                });
            }
            
            actualizarTotal();
        }

        // Actualizar total
        function actualizarTotal() {
            let total = 0;
            
            $('tr[data-index]').each(function(){
                const index = $(this).data('index');
                const cantidad = parseFloat($(this).find('.cantidad').val()) || 0;
                const precio = parseFloat($(this).find('.precio').val()) || 0;
                const descuento = parseFloat($(this).find('.descuento').val()) || 0;
                
                const subtotal = (cantidad * precio) - descuento;
                $(this).find('.subtotal').text('$ ' + subtotal.toFixed(2));
                
                total += subtotal;
            });
            
            $('#totalPedido').text('$ ' + total.toFixed(2));
            $('#total').val(total);
        }

        // Eventos
        $('#btnNuevoPedido').click(function(){
            $('#modalLabel').text('Nuevo Pedido de Compra');
            $('#formCompraPedido')[0].reset();
            $('#comprobante_id').val('');
            detalles = [];
            $('#tbodyDetalles').empty();
            $('#totalPedido').text('$ 0.00');
            $('#total').val('0');
            
            // Habilitar todos los campos
            $('#sucursal_id, #comprobante_tipo_id, #entidad_id, #f_emision, #f_vto, #observaciones').prop('disabled', false);
            $('#btnAgregarProducto').prop('disabled', false);
            $('.cantidad, .precio, .descuento').prop('readonly', false);
            $('#btnGuardarPedido').prop('disabled', false);
            
            // Establecer fecha actual
            const hoy = new Date().toISOString().split('T')[0];
            $('#f_emision').val(hoy);
            $('#f_contabilizacion').val(hoy);
            
            // Establecer fecha de vencimiento (30 días después)
            const vto = new Date();
            vto.setDate(vto.getDate() + 30);
            $('#f_vto').val(vto.toISOString().split('T')[0]);
            
            $('#modalCompraPedido').modal('show');
        });

        // Actualizar número cuando cambien sucursal o tipo
        $('#sucursal_id, #comprobante_tipo_id').change(function(){
            const sucursalId = $('#sucursal_id').val();
            const tipoId = $('#comprobante_tipo_id').val();
            obtenerSiguienteNumero(sucursalId, tipoId);
        });

        $('#btnAgregarProducto').click(function(){
            agregarFilaProducto();
        });

        $(document).on('click', '.btnEliminarFila', function(){
            const index = $(this).closest('tr').data('index');
            detalles.splice(index, 1);
            $(this).closest('tr').remove();
            
            // Reindexar
            $('tr[data-index]').each(function(i){
                $(this).data('index', i);
                $(this).find('select, input').each(function(){
                    const name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name', name.replace(/\[\d+\]/, '[' + i + ']'));
                    }
                });
            });
            
            actualizarTotal();
        });

        $(document).on('input', '.cantidad, .precio, .descuento', function(){
            actualizarTotal();
        });

        $('#btnGuardarPedido').click(function(){
            const form = $('#formCompraPedido')[0];
            
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return;
            }
            
            if (detalles.length === 0) {
                Swal.fire({
                    icon: "warning",
                    title: "Productos requeridos",
                    text: "Debe agregar al menos un producto al pedido",
                    confirmButtonText: "Entendido"
                });
                return;
            }
            
            // Validar que todos los productos estén seleccionados
            let productosValidos = true;
            $('.producto-select').each(function() {
                if (!$(this).val()) {
                    productosValidos = false;
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });
            
            if (!productosValidos) {
                Swal.fire({
                    icon: "warning",
                    title: "Productos incompletos",
                    text: "Todos los productos deben estar seleccionados",
                    confirmButtonText: "Entendido"
                });
                return;
            }
            
            const formData = new FormData(form);
            const accion = $('#comprobante_id').val() ? 'editar' : 'agregar';
            formData.append('accion', accion);
            
            // Agregar detalles al FormData
            detalles.forEach((detalle, index) => {
                formData.append(`detalles[${index}][producto_id]`, detalle.producto_id || $('.producto-select').eq(index).val());
                formData.append(`detalles[${index}][cantidad]`, detalle.cantidad || $('.cantidad').eq(index).val());
                formData.append(`detalles[${index}][precio_unitario]`, detalle.precio_unitario || $('.precio').eq(index).val());
                formData.append(`detalles[${index}][descuento]`, detalle.descuento || $('.descuento').eq(index).val() || 0);
            });
            
            // Mostrar loading
            Swal.fire({
                title: 'Guardando...',
                text: 'Por favor espere',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            $.ajax({
                url: 'compras_pedidos_ajax.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res){
                    Swal.close();
                    
                    if (res.resultado) {
                        tabla.ajax.reload();
                        $('#modalCompraPedido').modal('hide');
                        
                        Swal.fire({
                            icon: "success",
                            title: "¡Éxito!",
                            text: res.mensaje || "Pedido guardado correctamente",
                            showConfirmButton: false,
                            timer: 1500
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || "Error al guardar el pedido",
                            confirmButtonText: "Entendido"
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.close();
                    console.error("Error AJAX:", error);
                    
                    Swal.fire({
                        icon: "error",
                        title: "Error de conexión",
                        text: "Error al comunicarse con el servidor",
                        confirmButtonText: "Entendido"
                    });
                }
            });
        });

        // Editar pedido
        $(document).on('click', '.btnEditar', function(){
            var comprobanteId = $(this).data('id');
            
            if (!comprobanteId) {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "No se pudo identificar el pedido a editar",
                    confirmButtonText: "Entendido"
                });
                return;
            }
            
            // Verificar estado antes de cargar
            Swal.fire({
                title: 'Verificando...',
                text: 'Comprobando estado del pedido',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Primero verificar el estado
            $.get('compras_pedidos_ajax.php', {
                accion: 'obtener_info_estado',
                comprobante_id: comprobanteId
            }, function(resEstado){
                Swal.close();
                
                if (resEstado.editable) {
                    // Cargar datos del pedido para editar
                    cargarPedidoParaEditar(comprobanteId);
                } else {
                    Swal.fire({
                        icon: "warning",
                        title: "No editable",
                        text: "Este pedido no se puede editar porque está en estado: " + resEstado.estado_nombre,
                        confirmButtonText: "Entendido"
                    });
                }
            }, 'json').fail(function(xhr, status, error) {
                Swal.close();
                // Si falla la verificación, intentar cargar igual
                cargarPedidoParaEditar(comprobanteId);
            });
        });

        function cargarPedidoParaEditar(comprobanteId) {
            console.log("Cargando pedido para editar:", comprobanteId);
            
            Swal.fire({
                title: 'Cargando...',
                text: 'Obteniendo datos del pedido',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            $.get('compras_pedidos_ajax.php', {
                accion: 'obtener',
                comprobante_id: comprobanteId
            }, function(res){
                Swal.close();
                
                console.log("Respuesta del servidor:", res);
                
                if (res.pedido) {
                    const pedido = res.pedido;
                    
                    $('#modalLabel').text('Editar Pedido de Compra - Estado: ' + (pedido.estado_display || 'Borrador'));
                    $('#comprobante_id').val(pedido.comprobante_id);
                    $('#sucursal_id').val(pedido.sucursal_id);
                    $('#comprobante_tipo_id').val(pedido.comprobante_tipo_id);
                    $('#numero_comprobante').val(pedido.numero_comprobante);
                    $('#entidad_id').val(pedido.entidad_id);
                    $('#f_emision').val(pedido.f_emision);
                    $('#f_contabilizacion').val(pedido.f_contabilizacion || pedido.f_emision);
                    $('#f_vto').val(pedido.f_vto);
                    $('#observaciones').val(pedido.observaciones || '');
                    
                    // Cargar detalles
                    detalles = [];
                    $('#tbodyDetalles').empty();
                    
                    if (res.detalles && res.detalles.length > 0) {
                        console.log("Cargando detalles:", res.detalles);
                        res.detalles.forEach(detalle => {
                            agregarFilaProducto(detalle);
                        });
                    } else {
                        console.log("No hay detalles para este pedido");
                    }
                    
                    actualizarTotal();
                    $('#modalCompraPedido').modal('show');
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || "No se pudieron obtener los datos del pedido",
                        confirmButtonText: "Entendido"
                    });
                }
            }, 'json').fail(function(xhr, status, error) {
                Swal.close();
                console.error("Error al obtener pedido:", error, xhr.responseText);
                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    text: "Error al obtener los datos del pedido: " + error,
                    confirmButtonText: "Entendido"
                });
            });
        }

        // Confirmar pedido
        $(document).on('click', '.btnConfirmar', function(){
            var comprobanteId = $(this).data('id');
            
            Swal.fire({
                title: '¿Confirmar pedido?',
                text: "El pedido pasará a estado CONFIRMADO y no podrá ser modificado",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, confirmar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('compras_pedidos_ajax.php', {
                        accion: 'confirmar',
                        comprobante_id: comprobanteId
                    }, function(res){
                        if (res.resultado) {
                            tabla.ajax.reload();
                            Swal.fire({
                                icon: "success",
                                title: "¡Confirmado!",
                                text: res.mensaje || "Pedido confirmado correctamente",
                                showConfirmButton: false,
                                timer: 1500
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: res.error || "Error al confirmar el pedido",
                                confirmButtonText: "Entendido"
                            });
                        }
                    }, 'json');
                }
            });
        });

        // Marcar como pendiente
        $(document).on('click', '.btnPendiente', function(){
            var comprobanteId = $(this).data('id');
            
            Swal.fire({
                title: '¿Marcar como pendiente?',
                text: "El pedido pasará a estado PENDIENTE",
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, marcar como pendiente',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('compras_pedidos_ajax.php', {
                        accion: 'pendiente',
                        comprobante_id: comprobanteId
                    }, function(res){
                        if (res.resultado) {
                            tabla.ajax.reload();
                            Swal.fire({
                                icon: "success",
                                title: "¡Pendiente!",
                                text: res.mensaje || "Pedido marcado como pendiente",
                                showConfirmButton: false,
                                timer: 1500
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: res.error || "Error al cambiar el estado del pedido",
                                confirmButtonText: "Entendido"
                            });
                        }
                    }, 'json');
                }
            });
        });

        // Eliminar pedido
        $(document).on('click', '.btnEliminar', function(){
            var comprobanteId = $(this).data('id');
            
            Swal.fire({
                title: '¿Eliminar pedido?',
                text: "El pedido pasará a estado ELIMINADO",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('compras_pedidos_ajax.php', {
                        accion: 'eliminar',
                        comprobante_id: comprobanteId
                    }, function(res){
                        if (res.resultado) {
                            tabla.ajax.reload();
                            Swal.fire({
                                icon: "success",
                                title: "¡Eliminado!",
                                text: res.mensaje || "Pedido eliminado correctamente",
                                showConfirmButton: false,
                                timer: 1500
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: res.error || "Error al eliminar el pedido",
                                confirmButtonText: "Entendido"
                            });
                        }
                    }, 'json');
                }
            });
        });

        // Inicializar
        cargarDatosMaestros();
    });
    </script>

    <style>
    .table-secondary td {
        color: #6c757d !important;
    }
    
    .table-success td {
        color: #155724 !important;
    }
    
    .table-info td {
        color: #0c5460 !important;
    }
    
    .badge {
        font-size: 0.75rem;
    }
    
    .form-check.form-switch .form-check-input {
        margin-right: 0.5rem;
    }
    </style>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>