<?php
$pageTitle = "Punto de Venta - Carrito";
$currentPage = 'carrito';
$empresa_idx = intval($_GET['empresa_id'] ?? 0);

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));

require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<style>
    body {
        background-color: #ededed;
    }

    .product-card {
        background: #fff;
        border: none;
        border-radius: 8px;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, .12);
        transition: box-shadow 0.2s ease-in-out, transform 0.2s;
        height: 100%;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .product-card:hover {
        box-shadow: 0 4px 8px 0 rgba(0, 0, 0, .15);
        transform: translateY(-2px);
    }

    .product-img-wrapper {
        height: 180px;
        background-color: #f8f9fa;
        display: flex;
        align-items: center;
        justify-content: center;
        border-bottom: 1px solid #eee;
    }

    .product-img-wrapper i {
        font-size: 4rem;
        color: #dee2e6;
    }

    .product-img-wrapper img {
        max-height: 100%;
        max-width: 100%;
        object-fit: contain;
    }

    .product-info {
        padding: 1rem;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }

    .product-price {
        font-size: 1.5rem;
        font-weight: 400;
        color: #333;
        margin-bottom: 0.2rem;
    }

    .product-title {
        font-size: 0.9rem;
        color: #666;
        margin-bottom: 1rem;
        flex-grow: 1;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .btn-add-cart {
        background-color: rgba(65, 137, 230, .15);
        color: #3483fa;
        font-weight: 600;
        border: none;
        padding: 0.5rem;
        border-radius: 6px;
        transition: background-color 0.2s;
    }

    .btn-add-cart:hover {
        background-color: rgba(65, 137, 230, .25);
        color: #3483fa;
    }

    .cart-sidebar {
        position: sticky;
        top: 70px;
        height: calc(100vh - 90px);
        display: flex;
        flex-direction: column;
    }

    .cart-card {
        background: #fff;
        border: none;
        border-radius: 8px;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, .12);
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .cart-items-container {
        flex-grow: 1;
        overflow-y: auto;
        padding: 1rem;
    }

    .cart-items-container::-webkit-scrollbar {
        width: 6px;
    }

    .cart-items-container::-webkit-scrollbar-thumb {
        background-color: #ccc;
        border-radius: 4px;
    }

    .cart-item {
        display: flex;
        align-items: center;
        padding-bottom: 1rem;
        margin-bottom: 1rem;
        border-bottom: 1px solid #eee;
    }

    .cart-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .cart-item-details {
        flex-grow: 1;
        padding-left: 0.8rem;
    }

    .cart-item-title {
        font-size: 0.85rem;
        color: #333;
        margin-bottom: 0.2rem;
        line-height: 1.2;
    }

    .cart-item-price {
        font-weight: 600;
        color: #333;
    }

    .qty-controls {
        display: flex;
        align-items: center;
        background: #f5f5f5;
        border-radius: 4px;
        padding: 2px;
    }

    .qty-btn {
        border: none;
        background: transparent;
        color: #3483fa;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        border-radius: 4px;
    }

    .qty-btn:hover {
        background: #e0e0e0;
    }

    .qty-input {
        width: 30px;
        text-align: center;
        border: none;
        background: transparent;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .cart-summary {
        padding: 1.5rem;
        border-top: 1px solid #eee;
        background: #fcfcfc;
        border-bottom-left-radius: 8px;
        border-bottom-right-radius: 8px;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
        color: #666;
    }

    .summary-total {
        display: flex;
        justify-content: space-between;
        margin-top: 1rem;
        font-size: 1.4rem;
        font-weight: 600;
        color: #333;
    }

    .btn-checkout {
        background-color: #3483fa;
        color: #fff;
        border: none;
        width: 100%;
        padding: 0.8rem;
        font-size: 1rem;
        font-weight: 600;
        border-radius: 6px;
        margin-top: 1rem;
        transition: background-color 0.2s;
    }

    .btn-checkout:hover {
        background-color: #2968c8;
        color: #fff;
    }

    .btn-checkout:disabled {
        background-color: #a2c6fa;
        cursor: not-allowed;
    }
</style>

<main class="app-main">
    <div class="app-content pt-4">
        <div class="container-fluid">

            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white border-end-0"><i
                                class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0 py-2" id="buscarProducto"
                            placeholder="Buscar productos, marcas y más...">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8 col-xl-9 mb-4">
                    <div class="row g-3" id="contenedorProductos">
                        <div class="col-12 text-center py-5" id="cargandoProductos">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p class="mt-2 text-muted">Cargando catálogo...</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-xl-3">
                    <div class="cart-sidebar">
                        <div class="cart-card">
                            <div
                                class="p-3 border-bottom border-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold"><i class="bi bi-cart3 me-2 text-primary"></i>Tu Pedido</h5>
                                <span class="badge bg-primary rounded-pill" id="badgeCantidadGlobal">0</span>
                            </div>

                            <div class="cart-items-container" id="contenedorCarrito">
                                <div class="text-center text-muted py-5" id="carritoVacio">
                                    <i class="bi bi-bag-x fs-1 mb-2"></i>
                                    <p class="mb-0">El carrito está vacío</p>
                                    <small>Agrega productos para comenzar</small>
                                </div>
                            </div>

                            <div class="cart-summary">
                                <div class="summary-row">
                                    <span>Subtotal</span>
                                    <span id="txtSubtotal">$ 0.00</span>
                                </div>
                                <div class="summary-row">
                                    <span>Descuentos</span>
                                    <span class="text-success" id="txtDescuento">-$ 0.00</span>
                                </div>
                                <div class="summary-total">
                                    <span>Total</span>
                                    <span id="txtTotal">$ 0.00</span>
                                </div>
                                <button class="btn-checkout shadow-sm" id="btnProcesarCompra" disabled>
                                    Confirmar Compra
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const CARRITO_AJAX_URL = '<?= BASE_URL ?>/modules/ecommerce/carrito_ajax.php';
    const EMPRESA_ID = <?= $empresa_idx ?>;
</script>
<script>
    const CarritoApp = {
        productos: [],
        carrito: [],

        init: function () {
            this.cargarProductos();
            this.bindEvents();
        },

        bindEvents: function () {
            const self = this;
            document.getElementById('buscarProducto').addEventListener('keyup', function (e) {
                self.filtrarProductos(e.target.value);
            });
            document.getElementById('btnProcesarCompra').addEventListener('click', function () {
                self.procesarCompra();
            });
        },

        cargarProductos: function () {
            const self = this;
            $.ajax({
                url: CARRITO_AJAX_URL,
                type: 'GET',
                data: { accion: 'obtener_catalogo', empresa_id: EMPRESA_ID },
                dataType: 'json',
                success: function (response) {
                    document.getElementById('cargandoProductos').style.display = 'none';
                    if (response && response.length > 0) {
                        self.productos = response;
                        self.renderProductos(self.productos);
                    } else {
                        document.getElementById('contenedorProductos').innerHTML =
                            '<div class="col-12"><div class="alert alert-info">No hay productos disponibles en el catálogo.</div></div>';
                    }
                },
                error: function (xhr) {
                    document.getElementById('cargandoProductos').style.display = 'none';
                    document.getElementById('contenedorProductos').innerHTML =
                        '<div class="col-12"><div class="alert alert-danger">Error al cargar el catálogo. Por favor recargue la página.</div></div>';
                    console.error('Error AJAX carrito:', xhr.status, xhr.responseText);
                }
            });
        },

        renderProductos: function (lista) {
            const contenedor = document.getElementById('contenedorProductos');
            contenedor.innerHTML = '';

            if (lista.length === 0) {
                contenedor.innerHTML = '<div class="col-12 text-center text-muted py-5">No se encontraron productos que coincidan con la búsqueda.</div>';
                return;
            }

            lista.forEach(prod => {
                const precioFormat = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(prod.precio);
                const imgHtml = prod.imagen_url
                    ? `<img src="${prod.imagen_url}" alt="${prod.nombre}">`
                    : `<i class="bi bi-box-seam"></i>`;

                const col = document.createElement('div');
                col.className = 'col-12 col-sm-6 col-md-4 col-xl-3';
                col.innerHTML = `
                <div class="product-card">
                    <div class="product-img-wrapper">
                        ${imgHtml}</div>
                    <div class="product-info">
                        <div class="product-price">${precioFormat}</div>
                        <div class="product-title" title="${prod.nombre}">${prod.nombre}</div>
                        <button class="btn-add-cart w-100" onclick="CarritoApp.agregarAlCarrito(${prod.id})">
                            Agregar al carrito
                        </button>
                    </div>
                </div>
            `;
                contenedor.appendChild(col);
            });
        },

        filtrarProductos: function (termino) {
            termino = termino.toLowerCase();
            const filtrados = this.productos.filter(p =>
                p.nombre.toLowerCase().includes(termino) ||
                (p.codigo && p.codigo.toLowerCase().includes(termino))
            );
            this.renderProductos(filtrados);
        },

        agregarAlCarrito: function (id) {
            const producto = this.productos.find(p => p.id === id);
            if (!producto) return;

            const itemExistente = this.carrito.find(item => item.id === id);

            if (itemExistente) {
                itemExistente.cantidad++;
            } else {
                this.carrito.push({
                    id: producto.id,
                    nombre: producto.nombre,
                    precio: parseFloat(producto.precio),
                    cantidad: 1
                });
            }

            this.actualizarUI();

            const Toast = Swal.mixin({
                toast: true,
                position: 'bottom-end',
                showConfirmButton: false,
                timer: 1500,
                timerProgressBar: true,
            });
            Toast.fire({
                icon: 'success',
                title: 'Agregado al carrito'
            });
        },

        modificarCantidad: function (id, delta) {
            const item = this.carrito.find(item => item.id === id);
            if (item) {
                item.cantidad += delta;
                if (item.cantidad <= 0) {
                    this.carrito = this.carrito.filter(i => i.id !== id);
                }
                this.actualizarUI();
            }
        },

        actualizarUI: function () {
            const contenedor = document.getElementById('contenedorCarrito');
            const vacio = document.getElementById('carritoVacio');

            if (this.carrito.length === 0) {
                contenedor.innerHTML = '';
                contenedor.appendChild(vacio);
                vacio.style.display = 'block';
                document.getElementById('btnProcesarCompra').disabled = true;
                document.getElementById('badgeCantidadGlobal').innerText = '0';
            } else {
                vacio.style.display = 'none';
                contenedor.innerHTML = '';
                document.getElementById('btnProcesarCompra').disabled = false;

                let totalItems = 0;
                let subtotal = 0;

                this.carrito.forEach(item => {
                    totalItems += item.cantidad;
                    subtotal += (item.precio * item.cantidad);

                    const precioFormat = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(item.precio * item.cantidad);

                    const div = document.createElement('div');
                    div.className = 'cart-item';
                    div.innerHTML = `
                    <div class="bg-light rounded p-2 text-primary">
                        <i class="bi bi-box"></i>
                    </div>
                    <div class="cart-item-details">
                        <div class="cart-item-title">${item.nombre}</div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <div class="cart-item-price">${precioFormat}</div>
                            <div class="qty-controls shadow-sm">
                                <button class="qty-btn" onclick="CarritoApp.modificarCantidad(${item.id}, -1)">
                                    <i class="bi bi-dash"></i>
                                </button>
                                <input type="text" class="qty-input" value="${item.cantidad}" readonly>
                                <button class="qty-btn" onclick="CarritoApp.modificarCantidad(${item.id}, 1)">
                                    <i class="bi bi-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                    contenedor.appendChild(div);
                });

                document.getElementById('badgeCantidadGlobal').innerText = totalItems;

                // Actualizar totales
                const formtTotal = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(subtotal);
                document.getElementById('txtSubtotal').innerText = formtTotal;
                document.getElementById('txtTotal').innerText = formtTotal;
            }
        },

        procesarCompra: function () {
            if (this.carrito.length === 0) return;

            Swal.fire({
                title: '¿Confirmar Pedido?',
                text: "Se generará un comprobante con los productos del carrito.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3483fa',
                cancelButtonColor: '#dc3545',
                confirmButtonText: 'Sí, confirmar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const data = {
                        accion: 'guardar_pedido_carrito',
                        empresa_id: EMPRESA_ID,
                        detalles: JSON.stringify(this.carrito)
                    };

                    Swal.fire({
                        title: 'Procesando...',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    $.ajax({
                        url: CARRITO_AJAX_URL,
                        type: 'POST',
                        data: data,
                        dataType: 'json',
                        success: function (res) {
                            if (res.resultado) {
                                Swal.fire(
                                    '¡Completado!',
                                    'Tu pedido ha sido registrado con éxito.',
                                    'success'
                                ).then(() => {
                                    CarritoApp.carrito = [];
                                    CarritoApp.actualizarUI();
                                });
                            } else {
                                Swal.fire('Error', res.error || 'Ocurrió un problema al guardar', 'error');
                            }
                        },
                        error: function () {
                            Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
                        }
                    });
                }
            });
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        CarritoApp.init();
    });
</script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>