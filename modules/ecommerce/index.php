<?php
$pageTitle = "Punto de Venta - Carrito";
$currentPage = 'carrito';
$modudo_idx = 2;
$empresa_idx = 2;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));

require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<style>
    body { background-color: #ededed; }

    /* ── Layout ── */
    .catalog-layout { display: flex; gap: 1rem; align-items: flex-start; }
    .filters-panel {
        width: 220px;
        flex-shrink: 0;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(0,0,0,.12);
        padding: 1rem;
        position: sticky;
        top: 70px;
        max-height: calc(100vh - 90px);
        overflow-y: auto;
    }
    .filters-panel::-webkit-scrollbar { width: 4px; }
    .filters-panel::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
    .catalog-main { flex: 1; min-width: 0; }

    /* ── Filtros ── */
    .filter-section { margin-bottom: 1.2rem; }
    .filter-section-title {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #aaa;
        margin-bottom: 0.5rem;
    }
    .filter-item { display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.3rem; }
    .filter-item label { font-size: 0.83rem; color: #444; cursor: pointer; line-height: 1.2; flex-grow: 1; }
    .filter-item input[type=checkbox] { cursor: pointer; flex-shrink: 0; }
    .filter-count { font-size: 0.72rem; color: #bbb; margin-left: auto; }
    .filter-select {
        width: 100%;
        padding: 0.38rem 0.5rem;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 0.83rem;
        background: #fff;
        color: #444;
        cursor: pointer;
    }
    .filter-select:focus { outline: none; border-color: #3483fa; box-shadow: 0 0 0 2px rgba(52,131,250,.15); }
    .filter-select:disabled { background: #f5f5f5; color: #bbb; cursor: default; }
    .price-range-inputs { display: flex; gap: 0.4rem; align-items: center; }
    .price-range-inputs input {
        width: 80px;
        padding: 0.3rem 0.4rem;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 0.8rem;
    }
    .btn-apply-filter {
        width: 100%;
        padding: 0.35rem;
        background: #3483fa;
        color: #fff;
        border: none;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        margin-top: 0.5rem;
        transition: background .2s;
    }
    .btn-apply-filter:hover { background: #2968c8; }
    .btn-clear-filters {
        width: 100%;
        padding: 0.3rem;
        background: transparent;
        color: #3483fa;
        border: 1px solid #3483fa;
        border-radius: 4px;
        font-size: 0.78rem;
        cursor: pointer;
        margin-top: 0.3rem;
        transition: all .2s;
    }
    .btn-clear-filters:hover { background: #eef4ff; }
    .filter-loading { font-size: 0.78rem; color: #aaa; font-style: italic; }

    /* ── Tags de filtros activos ── */
    .active-filters { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.8rem; }
    .filter-tag {
        background: #eef4ff;
        color: #3483fa;
        border: 1px solid #c0d8fc;
        border-radius: 20px;
        padding: 0.2rem 0.6rem;
        font-size: 0.78rem;
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }
    .filter-tag button {
        background: none;
        border: none;
        color: #3483fa;
        cursor: pointer;
        padding: 0;
        font-size: 0.9rem;
        line-height: 1;
    }

    /* ── Tarjetas de productos ── */
    .product-card {
        background: #fff;
        border: none;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(0,0,0,.12);
        transition: box-shadow .2s, transform .2s;
        height: 100%;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .product-card:hover { box-shadow: 0 4px 8px rgba(0,0,0,.15); transform: translateY(-2px); }
    .product-img-wrapper {
        height: 160px;
        background: #f8f9fa;
        display: flex;
        align-items: center;
        justify-content: center;
        border-bottom: 1px solid #eee;
    }
    .product-img-wrapper i { font-size: 3.5rem; color: #dee2e6; }
    .product-img-wrapper img { max-height: 100%; max-width: 100%; object-fit: contain; }
    .product-info { padding: 0.8rem; flex-grow: 1; display: flex; flex-direction: column; }
    .product-price { font-size: 1.3rem; font-weight: 400; color: #333; margin-bottom: 0.15rem; }
    .product-price.sin-precio { font-size: 0.9rem; color: #e74c3c; font-style: italic; }
    .product-title {
        font-size: 0.85rem;
        color: #666;
        margin-bottom: 0.8rem;
        flex-grow: 1;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .product-badge {
        font-size: 0.7rem;
        background: #f0f0f0;
        color: #888;
        border-radius: 3px;
        padding: 1px 5px;
        margin-bottom: 0.4rem;
        display: inline-block;
    }
    .btn-add-cart {
        background-color: rgba(65,137,230,.15);
        color: #3483fa;
        font-weight: 600;
        border: none;
        padding: 0.45rem;
        border-radius: 6px;
        transition: background-color .2s;
        cursor: pointer;
        font-size: 0.85rem;
    }
    .btn-add-cart:hover { background-color: rgba(65,137,230,.25); }
    .btn-add-cart:disabled { opacity: .5; cursor: not-allowed; }

    /* ── Sidebar carrito ── */
    .cart-sidebar { position: sticky; top: 70px; height: calc(100vh - 90px); display: flex; flex-direction: column; }
    .cart-card {
        background: #fff;
        border: none;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(0,0,0,.12);
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .cart-items-container { flex-grow: 1; overflow-y: auto; padding: 1rem; }
    .cart-items-container::-webkit-scrollbar { width: 6px; }
    .cart-items-container::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
    .cart-item {
        display: flex;
        align-items: center;
        padding-bottom: 1rem;
        margin-bottom: 1rem;
        border-bottom: 1px solid #eee;
    }
    .cart-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .cart-item-details { flex-grow: 1; padding-left: 0.7rem; }
    .cart-item-title { font-size: 0.82rem; color: #333; margin-bottom: 0.2rem; line-height: 1.2; }
    .cart-item-price { font-weight: 600; color: #333; font-size: 0.9rem; }
    .qty-controls { display: flex; align-items: center; background: #f5f5f5; border-radius: 4px; padding: 2px; }
    .qty-btn { border: none; background: transparent; color: #3483fa; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; cursor: pointer; border-radius: 4px; }
    .qty-btn:hover { background: #e0e0e0; }
    .qty-input { width: 30px; text-align: center; border: none; background: transparent; font-size: 0.9rem; font-weight: 600; }
    .cart-summary { padding: 1.2rem; border-top: 1px solid #eee; background: #fcfcfc; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; }
    .summary-row { display: flex; justify-content: space-between; margin-bottom: 0.4rem; color: #666; font-size: 0.9rem; }
    .summary-row.iva { color: #e67e22; }
    .summary-total { display: flex; justify-content: space-between; margin-top: 0.8rem; font-size: 1.3rem; font-weight: 600; color: #333; }
    .cart-selectors { padding: 0.8rem 1.2rem; border-top: 1px solid #eee; background: #f9f9f9; }
    .cart-selectors select {
        width: 100%;
        padding: 0.4rem 0.5rem;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 0.85rem;
        background: #fff;
        margin-bottom: 0.5rem;
        color: #444;
    }
    .cart-selectors select:focus { outline: none; border-color: #3483fa; box-shadow: 0 0 0 2px rgba(52,131,250,.15); }
    .cart-selectors label { font-size: 0.75rem; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: .04em; display: block; margin-bottom: 0.2rem; }
    .btn-checkout {
        background-color: #3483fa;
        color: #fff;
        border: none;
        width: 100%;
        padding: 0.75rem;
        font-size: 0.95rem;
        font-weight: 600;
        border-radius: 6px;
        margin-top: 0.8rem;
        cursor: pointer;
        transition: background-color .2s;
    }
    .btn-checkout:hover { background-color: #2968c8; }
    .btn-checkout:disabled { background-color: #a2c6fa; cursor: not-allowed; }

    /* ── Responsive ── */
    @media (max-width: 768px) {
        .filters-panel { width: 100%; position: static; max-height: none; }
        .catalog-layout { flex-direction: column; }
    }
</style>

<main class="app-main">
    <div class="app-content pt-4">
        <div class="container-fluid">

            <!-- Barra de búsqueda -->
            <div class="row mb-3">
                <div class="col-md-8">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0 py-2"
                               id="buscarProducto"
                               placeholder="Buscar productos, marcas y más...">
                        <button class="btn btn-primary px-3" id="btnBuscar">Buscar</button>
                    </div>
                </div>
                <div class="col-md-4 d-flex align-items-center">
                    <small class="text-muted" id="txtResultados"></small>
                </div>
            </div>

            <!-- Tags de filtros activos -->
            <div class="active-filters" id="activeFilerTags"></div>

            <div class="row">
                <!-- Columna izquierda: filtros + catálogo -->
                <div class="col-lg-8 col-xl-9 mb-4">
                    <div class="catalog-layout">

                        <!-- Panel de filtros -->
                        <div class="filters-panel" id="filtersPanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <strong style="font-size:.9rem">Filtrar por</strong>
                                <button class="btn-clear-filters" id="btnLimpiarFiltros" style="width:auto;padding:.2rem .5rem;">
                                    Limpiar todo
                                </button>
                            </div>

                            <!-- Marca -->
                            <div class="filter-section">
                                <div class="filter-section-title">Marca</div>
                                <select class="filter-select" id="filtroMarca">
                                    <option value="">Todas las marcas</option>
                                </select>
                            </div>

                            <!-- Modelo (aparece solo si hay marca seleccionada) -->
                            <div class="filter-section" id="seccionModelo" style="display:none">
                                <div class="filter-section-title">Modelo</div>
                                <select class="filter-select" id="filtroModelo">
                                    <option value="">Todos los modelos</option>
                                </select>
                            </div>

                            <!-- Categorías -->
                            <div class="filter-section">
                                <div class="filter-section-title">Categoría</div>
                                <div id="filtroCategorias">
                                    <span class="filter-loading">Cargando…</span>
                                </div>
                            </div>

                            <!-- Precio -->
                            <div class="filter-section">
                                <div class="filter-section-title">Precio</div>
                                <div class="price-range-inputs">
                                    <input type="number" id="precioMin" placeholder="Mín" min="0">
                                    <span style="color:#aaa;font-size:.8rem">—</span>
                                    <input type="number" id="precioMax" placeholder="Máx" min="0">
                                </div>
                                <button class="btn-apply-filter" id="btnAplicarPrecio">Aplicar</button>
                            </div>
                        </div>

                        <!-- Catálogo de productos -->
                        <div class="catalog-main">
                            <div class="row g-3" id="contenedorProductos">
                                <div class="col-12 text-center py-5" id="cargandoProductos">
                                    <div class="spinner-border text-primary" role="status"></div>
                                    <p class="mt-2 text-muted">Cargando catálogo...</p>
                                </div>
                            </div>
                        </div>

                    </div><!-- /catalog-layout -->
                </div>

                <!-- Carrito -->
                <div class="col-lg-4 col-xl-3">
                    <div class="cart-sidebar">
                        <div class="cart-card">
                            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold">
                                    <i class="bi bi-cart3 me-2 text-primary"></i>Tu Pedido
                                </h5>
                                <span class="badge bg-primary rounded-pill" id="badgeCantidadGlobal">0</span>
                            </div>

                            <div class="cart-items-container" id="contenedorCarrito">
                                <div class="text-center text-muted py-5" id="carritoVacio">
                                    <i class="bi bi-bag-x fs-1 mb-2 d-block"></i>
                                    <p class="mb-0">El carrito está vacío</p>
                                    <small>Agrega productos para comenzar</small>
                                </div>
                            </div>

                            <div class="cart-selectors">
                                <label for="selectCliente">Cliente *</label>
                                <select id="selectCliente">
                                    <option value="">— Seleccionar cliente —</option>
                                </select>
                                <label for="selectSucursal">Sucursal</label>
                                <select id="selectSucursal">
                                    <option value="">— Seleccionar sucursal —</option>
                                </select>
                            </div>

                            <div class="cart-summary">
                                <div class="summary-row">
                                    <span>Subtotal neto</span>
                                    <span id="txtSubtotal">$ 0,00</span>
                                </div>
                                <div class="summary-row iva">
                                    <span>IVA</span>
                                    <span id="txtIva">$ 0,00</span>
                                </div>
                                <div class="summary-total">
                                    <span>Total</span>
                                    <span id="txtTotal">$ 0,00</span>
                                </div>
                                <button class="btn-checkout" id="btnProcesarCompra" disabled>
                                    Confirmar Compra
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- /row -->

        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const CarritoApp = {
    productosActuales: [],  // última página de productos del servidor
    carrito: [],
    _debounceTimer: null,

    filtros: {
        q:          '',
        categorias: [],   // array de IDs
        marca_id:   0,
        modelo_id:  0,
        precio_min: '',
        precio_max: '',
    },

    fmt: n => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(n),

    init() {
        this.cargarClientes();
        this.cargarSucursales();
        this.bindEvents();
        this.fetchCatalogo();   // carga inicial
    },

    // ── Eventos ──────────────────────────────────────────────────────────────
    bindEvents() {
        // Búsqueda con debounce
        document.getElementById('buscarProducto').addEventListener('input', () => {
            clearTimeout(this._debounceTimer);
            this._debounceTimer = setTimeout(() => {
                this.filtros.q = document.getElementById('buscarProducto').value.trim();
                this.fetchCatalogo();
            }, 350);
        });
        document.getElementById('btnBuscar').addEventListener('click', () => {
            clearTimeout(this._debounceTimer);
            this.filtros.q = document.getElementById('buscarProducto').value.trim();
            this.fetchCatalogo();
        });
        document.getElementById('buscarProducto').addEventListener('keydown', e => {
            if (e.key === 'Enter') { clearTimeout(this._debounceTimer); document.getElementById('btnBuscar').click(); }
        });

        // Marca
        document.getElementById('filtroMarca').addEventListener('change', () => {
            this.filtros.marca_id  = parseInt(document.getElementById('filtroMarca').value) || 0;
            this.filtros.modelo_id = 0;
            document.getElementById('filtroModelo').value = '';
            this.fetchCatalogo();
        });

        // Modelo
        document.getElementById('filtroModelo').addEventListener('change', () => {
            this.filtros.modelo_id = parseInt(document.getElementById('filtroModelo').value) || 0;
            this.fetchCatalogo();
        });

        // Precio
        document.getElementById('btnAplicarPrecio').addEventListener('click', () => {
            this.filtros.precio_min = document.getElementById('precioMin').value;
            this.filtros.precio_max = document.getElementById('precioMax').value;
            this.fetchCatalogo();
        });

        // Limpiar todo
        document.getElementById('btnLimpiarFiltros').addEventListener('click', () => this.limpiarFiltros());

        // Confirmar compra
        document.getElementById('btnProcesarCompra').addEventListener('click', () => this.procesarCompra());

        // Validar checkout al cambiar cliente
        document.getElementById('selectCliente').addEventListener('change', () => this.validarCheckout());
    },

    // ── Fetch catálogo + facets ───────────────────────────────────────────────
    fetchCatalogo() {
        const params = { accion: 'obtener_catalogo' };
        if (this.filtros.q)          params.q          = this.filtros.q;
        if (this.filtros.marca_id)   params.marca_id   = this.filtros.marca_id;
        if (this.filtros.modelo_id)  params.modelo_id  = this.filtros.modelo_id;
        if (this.filtros.precio_min) params.precio_min = this.filtros.precio_min;
        if (this.filtros.precio_max) params.precio_max = this.filtros.precio_max;
        if (this.filtros.categorias.length) params['categorias[]'] = this.filtros.categorias;

        // Mostrar spinner en catálogo
        document.getElementById('contenedorProductos').innerHTML =
            `<div class="col-12 text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>`;

        $.ajax({
            url: 'carrito_ajax.php',
            type: 'GET',
            data: params,
            dataType: 'json',
            success: resp => {
                if (resp && resp.error_bd) {
                    document.getElementById('contenedorProductos').innerHTML =
                        `<div class="col-12"><div class="alert alert-danger">Error BD: ${resp.error_bd}</div></div>`;
                    return;
                }
                this.productosActuales = resp.productos || [];
                this.renderProductos(this.productosActuales);
                this.renderFacets(resp.facets || {});
                this.renderActiveTags();
            },
            error: xhr => {
                document.getElementById('contenedorProductos').innerHTML =
                    `<div class="col-12"><div class="alert alert-danger">Error al cargar el catálogo.</div></div>`;
                console.error(xhr.responseText);
            }
        });
    },

    // ── Render facets (filtros dinámicos) ─────────────────────────────────────
    renderFacets(facets) {
        this._renderMarcas(facets.marcas || []);
        this._renderModelos(facets.modelos || []);
        this._renderCategorias(facets.categorias || []);
    },

    _renderMarcas(marcas) {
        const sel  = document.getElementById('filtroMarca');
        const prev = sel.value;
        sel.innerHTML = '<option value="">Todas las marcas</option>';
        marcas.forEach(m => {
            const opt = new Option(`${m.nombre} (${m.count})`, m.id, false, parseInt(prev) === m.id);
            sel.appendChild(opt);
        });
        if (!marcas.find(m => m.id === parseInt(prev))) sel.value = '';
    },

    _renderModelos(modelos) {
        const sec  = document.getElementById('seccionModelo');
        const sel  = document.getElementById('filtroModelo');
        const prev = parseInt(sel.value) || 0;

        if (!this.filtros.marca_id || modelos.length === 0) {
            sec.style.display = 'none';
            sel.innerHTML     = '<option value="">Todos los modelos</option>';
            return;
        }
        sec.style.display = '';
        sel.innerHTML = '<option value="">Todos los modelos</option>';
        modelos.forEach(m => {
            const opt = new Option(`${m.nombre} (${m.count})`, m.id, false, prev === m.id);
            sel.appendChild(opt);
        });
    },

    _renderCategorias(categorias) {
        const cont = document.getElementById('filtroCategorias');
        if (categorias.length === 0) {
            cont.innerHTML = '<span class="filter-loading">Sin categorías</span>';
            return;
        }
        cont.innerHTML = '';
        categorias.forEach(cat => {
            const checked = this.filtros.categorias.includes(cat.id);
            const div = document.createElement('div');
            div.className = 'filter-item';
            div.innerHTML = `
                <input type="checkbox" id="cat_${cat.id}" value="${cat.id}" ${checked ? 'checked' : ''}>
                <label for="cat_${cat.id}">${cat.nombre}</label>
                <span class="filter-count">${cat.count}</span>
            `;
            div.querySelector('input').addEventListener('change', e => {
                const id = cat.id;
                if (e.target.checked) {
                    if (!this.filtros.categorias.includes(id)) this.filtros.categorias.push(id);
                } else {
                    this.filtros.categorias = this.filtros.categorias.filter(c => c !== id);
                }
                this.fetchCatalogo();
            });
            cont.appendChild(div);
        });
    },

    // ── Tags de filtros activos ───────────────────────────────────────────────
    renderActiveTags() {
        const cont = document.getElementById('activeFilerTags');
        cont.innerHTML = '';

        const tag = (label, onRemove) => {
            const span = document.createElement('span');
            span.className = 'filter-tag';
            span.innerHTML = `${label} <button>×</button>`;
            span.querySelector('button').addEventListener('click', onRemove);
            cont.appendChild(span);
        };

        if (this.filtros.q) {
            tag(`"${this.filtros.q}"`, () => {
                this.filtros.q = '';
                document.getElementById('buscarProducto').value = '';
                this.fetchCatalogo();
            });
        }

        if (this.filtros.marca_id) {
            const opt = document.querySelector(`#filtroMarca option[value="${this.filtros.marca_id}"]`);
            const nombre = opt ? opt.textContent.replace(/\s*\(\d+\)$/, '') : `Marca #${this.filtros.marca_id}`;
            tag(`Marca: ${nombre}`, () => {
                this.filtros.marca_id  = 0;
                this.filtros.modelo_id = 0;
                document.getElementById('filtroMarca').value  = '';
                document.getElementById('filtroModelo').value = '';
                this.fetchCatalogo();
            });
        }

        if (this.filtros.modelo_id) {
            const opt = document.querySelector(`#filtroModelo option[value="${this.filtros.modelo_id}"]`);
            const nombre = opt ? opt.textContent.replace(/\s*\(\d+\)$/, '') : `Modelo #${this.filtros.modelo_id}`;
            tag(`Modelo: ${nombre}`, () => {
                this.filtros.modelo_id = 0;
                document.getElementById('filtroModelo').value = '';
                this.fetchCatalogo();
            });
        }

        this.filtros.categorias.forEach(id => {
            const el = document.getElementById(`cat_${id}`);
            const label = el ? el.nextElementSibling.textContent : `Cat #${id}`;
            tag(label, () => {
                this.filtros.categorias = this.filtros.categorias.filter(c => c !== id);
                this.fetchCatalogo();
            });
        });

        if (this.filtros.precio_min || this.filtros.precio_max) {
            const min = this.filtros.precio_min ? this.fmt(this.filtros.precio_min) : '*';
            const max = this.filtros.precio_max ? this.fmt(this.filtros.precio_max) : '*';
            tag(`Precio: ${min} – ${max}`, () => {
                this.filtros.precio_min = '';
                this.filtros.precio_max = '';
                document.getElementById('precioMin').value = '';
                document.getElementById('precioMax').value = '';
                this.fetchCatalogo();
            });
        }
    },

    limpiarFiltros() {
        this.filtros = { q: '', categorias: [], marca_id: 0, modelo_id: 0, precio_min: '', precio_max: '' };
        document.getElementById('buscarProducto').value = '';
        document.getElementById('precioMin').value      = '';
        document.getElementById('precioMax').value      = '';
        document.getElementById('filtroMarca').value    = '';
        document.getElementById('filtroModelo').value   = '';
        this.fetchCatalogo();
    },

    // ── Clientes y sucursales ─────────────────────────────────────────────────
    cargarClientes() {
        $.getJSON('carrito_ajax.php', { accion: 'obtener_clientes' }, data => {
            const sel = document.getElementById('selectCliente');
            data.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.nombre;
                sel.appendChild(opt);
            });
        });
    },

    cargarSucursales() {
        $.getJSON('carrito_ajax.php', { accion: 'obtener_sucursales_empresa' }, data => {
            const sel = document.getElementById('selectSucursal');
            data.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.nombre;
                sel.appendChild(opt);
            });
            if (data.length > 0) sel.value = data[0].id;
        });
    },

    validarCheckout() {
        const clienteOk = document.getElementById('selectCliente').value !== '';
        const carritoOk = this.carrito.length > 0;
        document.getElementById('btnProcesarCompra').disabled = !(clienteOk && carritoOk);
    },

    renderProductos(lista) {
        const contenedor = document.getElementById('contenedorProductos');
        contenedor.innerHTML = '';

        document.getElementById('txtResultados').textContent =
            lista.length > 0 ? `${lista.length} resultado${lista.length !== 1 ? 's' : ''}` : 'Sin resultados';

        if (lista.length === 0) {
            contenedor.innerHTML = `<div class="col-12 text-center text-muted py-5">
                <i class="bi bi-search fs-1 d-block mb-2"></i>
                No se encontraron productos con los filtros aplicados.
            </div>`;
            return;
        }

        lista.forEach(prod => {
            const tienePrecio = prod.precio > 0;
            const precioHtml  = tienePrecio
                ? `<div class="product-price">${this.fmt(prod.precio)}</div>`
                : `<div class="product-price sin-precio">Sin precio asignado</div>`;
            const ivaHtml = tienePrecio && prod.iva_porcentaje > 0
                ? `<small class="text-muted" style="font-size:.7rem">+IVA ${prod.iva_porcentaje}%</small>`
                : '';

            const imgHtml = prod.imagen_id > 0
                ? `<img src="../gestion/get_imagen.php?id=${prod.imagen_id}" alt="${prod.nombre}"
                        loading="lazy" onerror="this.parentElement.innerHTML='<i class=\\'bi bi-box-seam\\'></i>'">`
                : `<i class="bi bi-box-seam"></i>`;

            const col = document.createElement('div');
            col.className = 'col-12 col-sm-6 col-xl-4';
            col.innerHTML = `
                <div class="product-card">
                    <div class="product-img-wrapper">
                        ${imgHtml}
                    </div>
                    <div class="product-info">
                        ${prod.categoria ? `<span class="product-badge">${prod.categoria}</span>` : ''}
                        ${precioHtml}
                        ${ivaHtml}
                        <div class="product-title" title="${prod.nombre}">${prod.nombre}</div>
                        <button class="btn-add-cart w-100"
                                onclick="CarritoApp.agregarAlCarrito(${prod.id})"
                                ${!tienePrecio ? 'disabled title="Sin precio disponible"' : ''}>
                            <i class="bi bi-cart-plus me-1"></i> Agregar
                        </button>
                    </div>
                </div>
            `;
            contenedor.appendChild(col);
        });
    },

    agregarAlCarrito(id) {
        const producto = this.productosActuales.find(p => p.id === id);
        if (!producto || producto.precio <= 0) return;

        const itemExistente = this.carrito.find(item => item.id === id);
        if (itemExistente) {
            itemExistente.cantidad++;
        } else {
            this.carrito.push({
                id:             producto.id,
                nombre:         producto.nombre,
                precio:         parseFloat(producto.precio),
                iva_porcentaje: parseFloat(producto.iva_porcentaje || 0),
                cantidad:       1
            });
        }

        this.actualizarUI();

        const Toast = Swal.mixin({
            toast: true,
            position: 'bottom-end',
            showConfirmButton: false,
            timer: 1500,
            timerProgressBar: true
        });
        Toast.fire({ icon: 'success', title: 'Agregado al carrito' });
    },

    modificarCantidad(id, delta) {
        const item = this.carrito.find(item => item.id === id);
        if (!item) return;
        item.cantidad += delta;
        if (item.cantidad <= 0) {
            this.carrito = this.carrito.filter(i => i.id !== id);
        }
        this.actualizarUI();
    },

    actualizarUI: function () {
        const contenedor = document.getElementById('contenedorCarrito');
        const vacio      = document.getElementById('carritoVacio');

        if (this.carrito.length === 0) {
            contenedor.innerHTML = '';
            contenedor.appendChild(vacio);
            vacio.style.display = 'block';
            document.getElementById('badgeCantidadGlobal').innerText = '0';
            document.getElementById('txtSubtotal').innerText = '$ 0,00';
            document.getElementById('txtIva').innerText      = '$ 0,00';
            document.getElementById('txtTotal').innerText    = '$ 0,00';
            this.validarCheckout();
            return;
        }

        vacio.style.display = 'none';
        contenedor.innerHTML = '';

        let totalItems  = 0;
        let subtotalNeto = 0;
        let totalIva    = 0;

        this.carrito.forEach(item => {
            totalItems   += item.cantidad;
            const neto    = item.precio * item.cantidad;
            const iva     = neto * (item.iva_porcentaje / 100);
            subtotalNeto += neto;
            totalIva     += iva;

            const ivaLabel = item.iva_porcentaje > 0
                ? `<small class="text-muted ms-1">+IVA ${item.iva_porcentaje}%</small>`
                : '';

            const div = document.createElement('div');
            div.className = 'cart-item';
            div.innerHTML = `
                <div class="bg-light rounded p-2 text-primary flex-shrink-0">
                    <i class="bi bi-box"></i>
                </div>
                <div class="cart-item-details">
                    <div class="cart-item-title">${item.nombre}</div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <div class="cart-item-price">${this.fmt(neto)}${ivaLabel}</div>
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

        const total = subtotalNeto + totalIva;
        document.getElementById('badgeCantidadGlobal').innerText = totalItems;
        document.getElementById('txtSubtotal').innerText = this.fmt(subtotalNeto);
        document.getElementById('txtIva').innerText      = this.fmt(totalIva);
        document.getElementById('txtTotal').innerText    = this.fmt(total);
        this.validarCheckout();
    },

    procesarCompra: function () {
        if (this.carrito.length === 0) return;

        const entidad_id  = document.getElementById('selectCliente').value;
        const sucursal_id = document.getElementById('selectSucursal').value;

        if (!entidad_id) {
            Swal.fire('Atención', 'Debe seleccionar un cliente antes de confirmar.', 'warning');
            return;
        }

        const clienteNombre   = document.getElementById('selectCliente').selectedOptions[0].text;
        const sucursalNombre  = sucursal_id
            ? document.getElementById('selectSucursal').selectedOptions[0].text
            : '—';
        const totalText = document.getElementById('txtTotal').innerText;

        Swal.fire({
            title: '¿Confirmar Pedido?',
            html: `<b>Cliente:</b> ${clienteNombre}<br><b>Sucursal:</b> ${sucursalNombre}<br><b>Total:</b> ${totalText}`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3483fa',
            cancelButtonColor: '#dc3545',
            confirmButtonText: 'Sí, confirmar',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (!result.isConfirmed) return;

            Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            $.ajax({
                url: 'carrito_ajax.php',
                type: 'POST',
                data: {
                    accion:      'guardar_pedido_carrito',
                    detalles:    JSON.stringify(this.carrito),
                    entidad_id:  entidad_id,
                    sucursal_id: sucursal_id || 1
                },
                dataType: 'json',
                success: res => {
                    if (res.resultado) {
                        Swal.fire(
                            '¡Pedido registrado!',
                            `Orden N° ${res.comprobante_nro} creada con éxito.<br>Total: ${this.fmt(res.total)}`,
                            'success'
                        ).then(() => {
                            this.carrito = [];
                            this.actualizarUI();
                        });
                    } else {
                        Swal.fire('Error', res.error || 'Ocurrió un problema al guardar', 'error');
                    }
                },
                error: () => Swal.fire('Error', 'No se pudo conectar con el servidor', 'error')
            });
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
