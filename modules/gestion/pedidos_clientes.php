<?php
// Configuración de la página
$pageTitle = "Gestión de Pedidos de Clientes";
$currentPage = 'paginas';
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
                <div class="col-sm-6"><h3 class="mb-0">Pedidos de Clientes</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Pedidos de Clientes</li>
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
                                        <table id="tablaPedidosClientes" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Número</th>
                                                    <th>Cliente</th>
                                                    <th>Fecha Emisión</th>
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
            <div class="modal fade" id="modalPedidoCliente" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Pedido de Cliente</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formPedidoCliente">
                                <input type="hidden" id="comprobante_id" name="comprobante_id" />
                                
                                <!-- Cabecera del Pedido -->
                                <div class="row g-3 mb-4">
                                    <div class="col-md-3">
                                        <label>Número Comprobante *</label>
                                        <input type="number" class="form-control" id="numero_comprobante" name="numero_comprobante" required />
                                    </div>
                                    <div class="col-md-3">
                                        <label>Fecha Emisión *</label>
                                        <input type="date" class="form-control" id="f_emision" name="f_emision" required />
                                    </div>
                                    <div class="col-md-3">
                                        <label>Fecha Vencimiento *</label>
                                        <input type="date" class="form-control" id="f_vto" name="f_vto" required />
                                    </div>
                                    <div class="col-md-3">
                                        <label>Cliente *</label>
                                        <select class="form-control" id="entidad_id" name="entidad_id" required>
                                            <option value="">Seleccionar Cliente</option>
                                            <!-- Opciones se cargarán via AJAX -->
                                        </select>
                                    </div>
                                    <div class="col-md-12">
                                        <label>Observaciones</label>
                                        <textarea class="form-control" id="observaciones" name="observaciones" rows="2"></textarea>
                                    </div>
                                </div>

                                <!-- Detalles del Pedido -->
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <h5>Detalles del Pedido</h5>
                                        <button type="button" class="btn btn-sm btn-success mb-2" id="btnAgregarDetalle">
                                            <i class="fa fa-plus"></i> Agregar Producto
                                        </button>
                                        <div class="table-responsive">
                                            <table class="table table-bordered" id="tablaDetalles">
                                                <thead>
                                                    <tr>
                                                        <th>Producto</th>
                                                        <th width="120">Cantidad</th>
                                                        <th width="150">Precio Unit.</th>
                                                        <th width="120">Descuento</th>
                                                        <th width="150">Subtotal</th>
                                                        <th width="80">Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="cuerpoDetalles">
                                                    <!-- Filas de detalles se agregarán aquí -->
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <td colspan="4" class="text-end"><strong>Total:</strong></td>
                                                        <td><strong id="totalPedido">0.00</strong></td>
                                                        <td></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button id="btnGuardar" class="btn btn-success">Guardar Pedido</button>
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            $(document).ready(function(){
                var tabla = $('#tablaPedidosClientes').DataTable({
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
                        url: 'pedidos_clientes_ajax.php',
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
                        { data: 'comprobante_id' },
                        { data: 'numero_comprobante' },
                        { 
                            data: 'nombre_entidad',
                            render: function(data, type, row) {
                                return data || 'Cliente no especificado';
                            }
                        },
                        { data: 'f_emision' },
                        { 
                            data: 'total',
                            render: function(data) {
                                return '$ ' + parseFloat(data).toFixed(2);
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
                                
                                return `<div class="d-flex flex-column align-items-center">${botonEstado}</div>`;
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

                // Cargar clientes al modal
                function cargarClientes() {
                    $.get('pedidos_clientes_ajax.php', {accion: 'obtener_clientes'}, function(res){
                        if(res.resultado) {
                            $('#entidad_id').empty().append('<option value="">Seleccionar Cliente</option>');
                            $.each(res.data, function(i, cliente){
                                $('#entidad_id').append(`<option value="${cliente.entidad_id}">${cliente.nombre}</option>`);
                            });
                        }
                    }, 'json');
                }

                // Agregar fila de detalle
                $('#btnAgregarDetalle').click(function(){
                    var nuevaFila = `
                        <tr>
                            <td>
                                <select class="form-control producto_id" name="producto_id[]" required>
                                    <option value="">Seleccionar Producto</option>
                                    <!-- Productos se cargarán via AJAX -->
                                </select>
                            </td>
                            <td><input type="number" class="form-control cantidad" name="cantidad[]" step="0.01" min="0" required></td>
                            <td><input type="number" class="form-control precio_unitario" name="precio_unitario[]" step="0.01" min="0" required></td>
                            <td><input type="number" class="form-control descuento" name="descuento[]" step="0.01" min="0" value="0"></td>
                            <td><input type="text" class="form-control-plaintext subtotal" readonly value="0.00"></td>
                            <td><button type="button" class="btn btn-sm btn-danger btnQuitarDetalle"><i class="fa fa-trash"></i></button></td>
                        </tr>`;
                    $('#cuerpoDetalles').append(nuevaFila);
                    cargarProductosEnFila($('#cuerpoDetalles tr:last'));
                });

                // Cargar productos en una fila específica
                function cargarProductosEnFila(fila) {
                    $.get('pedidos_clientes_ajax.php', {accion: 'obtener_productos'}, function(res){
                        if(res.resultado) {
                            var select = fila.find('.producto_id');
                            select.empty().append('<option value="">Seleccionar Producto</option>');
                            $.each(res.data, function(i, producto){
                                select.append(`<option value="${producto.producto_id}">${producto.nombre}</option>`);
                            });
                        }
                    }, 'json');
                }

                // Calcular subtotal y total
                $(document).on('input', '.cantidad, .precio_unitario, .descuento', function(){
                    var fila = $(this).closest('tr');
                    var cantidad = parseFloat(fila.find('.cantidad').val()) || 0;
                    var precio = parseFloat(fila.find('.precio_unitario').val()) || 0;
                    var descuento = parseFloat(fila.find('.descuento').val()) || 0;
                    
                    var subtotal = (cantidad * precio) - descuento;
                    fila.find('.subtotal').val(subtotal.toFixed(2));
                    
                    calcularTotal();
                });

                function calcularTotal() {
                    var total = 0;
                    $('.subtotal').each(function(){
                        total += parseFloat($(this).val()) || 0;
                    });
                    $('#totalPedido').text(total.toFixed(2));
                }

                // Quitar fila de detalle
                $(document).on('click', '.btnQuitarDetalle', function(){
                    $(this).closest('tr').remove();
                    calcularTotal();
                });

                // Resto del código (manejo de estados, nuevo, editar, eliminar) similar al ejemplo de comprobantes fiscales
                // ... [Código similar al del ejemplo comprobantes_fiscales.php para manejar estados, nuevo, editar, eliminar]
                // Nota: Implementar las funciones para cambiar estado, nuevo pedido, editar pedido y eliminar pedido
                // siguiendo el mismo patrón que en comprobantes_fiscales.php

                $('#btnNuevo').click(function(){
                    $('#formPedidoCliente')[0].reset();
                    $('#comprobante_id').val('');
                    $('#cuerpoDetalles').empty();
                    $('#modalLabel').text('Nuevo Pedido de Cliente');
                    cargarClientes();
                    var modal = new bootstrap.Modal(document.getElementById('modalPedidoCliente'));
                    modal.show();
                });

                // Implementar funciones editar y eliminar similar al ejemplo anterior
            });
            </script>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <?php
            require_once ROOT_PATH . '/templates/adminlte/footer1.php';
            ?>
            </body>
            </html>