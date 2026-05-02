<?php
$pageTitle   = "Punto de Venta - Carrito";
$currentPage = 'carrito';
$empresa_idx = intval($_GET['empresa_id'] ?? 0);

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));

require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<style>
    body { background-color: #ededed; }

    /* ── Layout principal ── */
    .catalog-layout   { display: flex; gap: 1rem; align-items: flex-start; }

    /* ── Panel de filtros ── */
    .filters-panel {
        width: 220px;
        flex-shrink: 0;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(0,0,0,.12);
        padding: 1rem;
        /* posición y tamaño los setea JS con position:fixed */
        overflow-y: auto;
        box-sizing: border-box;
    }
    .filters-panel::-webkit-scrollbar { width: 4px; }
    .filters-panel::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
    .filter-section { margin-bottom: 1.2rem; }
    .filter-section-title {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #999;
        margin-bottom: 0.5rem;
    }
    .filter-item { display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.35rem; }
    .filter-item label { font-size: 0.84rem; color: #444; cursor: pointer; line-height: 1.2; }
    .filter-item input[type=checkbox] { cursor: pointer; flex-shrink: 0; }
    .price-range-inputs { display: flex; gap: 0.4rem; align-items: center; }
    .price-range-inputs input {
        width: 80px; padding: 0.28rem 0.4rem;
        border: 1px solid #ddd; border-radius: 4px; font-size: 0.8rem;
    }
    .btn-apply-filter {
        width: 100%; padding: 0.35rem; margin-top: 0.5rem;
        background: #3483fa; color: #fff; border: none;
        border-radius: 4px; font-size: 0.8rem; font-weight: 600;
        cursor: pointer; transition: background .2s;
    }
    .btn-apply-filter:hover { background: #2968c8; }
    .btn-clear-filters {
        background: transparent; color: #3483fa;
        border: 1px solid #3483fa; border-radius: 4px;
        font-size: 0.75rem; padding: 0.2rem 0.5rem;
        cursor: pointer; transition: all .2s;
    }
    .btn-clear-filters:hover { background: #eef4ff; }

    /* ── Tags de filtros activos ── */
    .active-filters { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.8rem; min-height: 0; }
    .filter-tag {
        background: #eef4ff; color: #3483fa;
        border: 1px solid #c0d8fc; border-radius: 20px;
        padding: 0.2rem 0.55rem; font-size: 0.78rem;
        display: flex; align-items: center; gap: 0.3rem;
    }
    .filter-tag button {
        background: none; border: none; color: #3483fa;
        cursor: pointer; padding: 0; font-size: 0.9rem; line-height: 1;
    }

    /* ── Tarjetas de productos ── */
    .product-card {
        background: #fff; border: none; border-radius: 8px;
        box-shadow: 0 1px 2px rgba(0,0,0,.12);
        transition: box-shadow .2s, transform .2s;
        height: 100%; display: flex; flex-direction: column; overflow: hidden;
    }
    .product-card:hover { box-shadow: 0 4px 8px rgba(0,0,0,.15); transform: translateY(-2px); }
    .product-img-wrapper {
        height: 180px; background: #f8f9fa;
        display: flex; align-items: center; justify-content: center;
        border-bottom: 1px solid #eee; position: relative;
    }
    .product-img-wrapper i { font-size: 4rem; color: #dee2e6; }
    .product-img-wrapper img { max-height: 100%; max-width: 100%; object-fit: contain; }
    .carousel-btn {
        position: absolute; top: 50%; transform: translateY(-50%);
        background: rgba(255,255,255,.85); border: none;
        width: 28px; height: 28px; border-radius: 50%;
        cursor: pointer; font-size: 1.1rem; font-weight: bold;
        display: flex; align-items: center; justify-content: center;
        z-index: 2; opacity: 0; transition: opacity .2s;
        box-shadow: 0 1px 4px rgba(0,0,0,.2); color: #333; padding: 0;
    }
    .product-img-wrapper:hover .carousel-btn { opacity: 1; }
    .carousel-btn:hover { background: #fff; }
    .carousel-prev { left: 6px; }
    .carousel-next { right: 6px; }
    .carousel-dots {
        position: absolute; bottom: 6px; left: 50%; transform: translateX(-50%);
        display: flex; gap: 4px; z-index: 2;
    }
    .carousel-dot { width: 6px; height: 6px; border-radius: 50%; background: rgba(0,0,0,.25); cursor: pointer; transition: background .2s; }
    .carousel-dot.active { background: #3483fa; }

    .product-info { padding: 1rem; flex-grow: 1; display: flex; flex-direction: column; }
    .product-price { font-size: 1.5rem; font-weight: 400; color: #333; margin-bottom: 0.2rem; }
    .product-price.sin-precio { font-size: 0.9rem; color: #e74c3c; font-style: italic; font-weight: 400; }
    .product-badge {
        font-size: 0.7rem; background: #f0f0f0; color: #888;
        border-radius: 3px; padding: 1px 5px; margin-bottom: 0.4rem; display: inline-block;
    }
    .product-title {
        font-size: 0.9rem; color: #666; margin-bottom: 1rem; flex-grow: 1;
        display: -webkit-box; -webkit-line-clamp: 2;
        -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis;
    }
    .btn-add-cart {
        background-color: rgba(65,137,230,.15); color: #3483fa;
        font-weight: 600; border: none; padding: 0.5rem; border-radius: 6px;
        transition: background-color .2s; cursor: pointer;
    }
    .btn-add-cart:hover { background-color: rgba(65,137,230,.25); }
    .btn-add-cart:disabled { opacity: .5; cursor: not-allowed; }

    /* ── Barra de búsqueda sticky ── */
    .sticky-search-bar {
        position: sticky;
        top: 0;                          /* top:0 relativo al scroll container (.app-main) */
        z-index: 200;
        background: #ededed;
        padding: 0.75rem 0 0.4rem;
        box-shadow: 0 2px 8px rgba(0,0,0,.07);
        margin-bottom: 0.75rem;
    }

    /* ── Carrito ── */
    .cart-sidebar {
        /* posición y tamaño los setea JS con position:fixed */
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-sizing: border-box;
    }
    .cart-card {
        flex: 1 1 0;              /* crece para llenar .cart-sidebar */
        min-height: 0;            /* permite comprimirse; reemplaza height:100% */
        background: #fff; border: none; border-radius: 8px;
        box-shadow: 0 1px 2px rgba(0,0,0,.12);
        display: flex; flex-direction: column;
        overflow: hidden;
    }
    .cart-header { flex-shrink: 0; }   /* encabezado nunca se comprime */
    .cart-items-container { flex: 1 1 0; min-height: 0; overflow-y: auto; padding: 1rem; }
    .cart-items-container::-webkit-scrollbar { width: 6px; }
    .cart-items-container::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
    .cart-item { display: flex; align-items: center; padding-bottom: 1rem; margin-bottom: 1rem; border-bottom: 1px solid #eee; }
    .cart-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .cart-item-details { flex-grow: 1; padding-left: 0.8rem; }
    .cart-item-title { font-size: 0.85rem; color: #333; margin-bottom: 0.2rem; line-height: 1.2; }
    .cart-item-price { font-weight: 600; color: #333; }
    .qty-controls { display: flex; align-items: center; background: #f5f5f5; border-radius: 4px; padding: 2px; }
    .qty-btn { border: none; background: transparent; color: #3483fa; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; cursor: pointer; border-radius: 4px; }
    .qty-btn:hover { background: #e0e0e0; }
    .qty-input { width: 30px; text-align: center; border: none; background: transparent; font-size: 0.9rem; font-weight: 600; }
    .cart-summary { flex-shrink: 0; padding: 1.5rem; border-top: 1px solid #eee; background: #fcfcfc; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; }
    .summary-row { display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: #666; }
    .summary-total { display: flex; justify-content: space-between; margin-top: 1rem; font-size: 1.4rem; font-weight: 600; color: #333; }
    .btn-checkout {
        background-color: #3483fa; color: #fff; border: none; width: 100%;
        padding: 0.8rem; font-size: 1rem; font-weight: 600; border-radius: 6px;
        margin-top: 1rem; cursor: pointer; transition: background-color .2s;
    }
    .btn-checkout:hover { background-color: #2968c8; }
    .btn-checkout:disabled { background-color: #a2c6fa; cursor: not-allowed; }

    @media (max-width: 991px) {
        .filters-panel { width: 100% !important; position: static !important;
                         max-height: none !important; height: auto !important; }
        .catalog-layout { flex-direction: column; }
        .catalog-main   { margin-left: 0 !important; }
        .cart-sidebar   { position: static !important; height: auto !important;
                          min-height: 300px; }
    }
</style>

<main class="app-main">
    <div class="app-content pt-3">
        <div class="container-fluid">

            <!-- Barra de búsqueda + tags: sticky debajo del navbar -->
            <div class="sticky-search-bar" id="stickySearchBar">
                <div class="row g-2 align-items-center">
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
                <div class="active-filters mt-1" id="activeFilterTags"></div>
            </div>

            <div class="row">
                <!-- Catálogo + filtros -->
                <div class="col-lg-8 col-xl-9 mb-4" id="colCatalog">
                    <div class="catalog-layout">

                        <!-- Panel de filtros -->
                        <div class="filters-panel" id="filtersPanel">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong style="font-size:.88rem">Filtrar por</strong>
                                <button class="btn-clear-filters" id="btnLimpiarFiltros">Limpiar</button>
                            </div>

                            <div class="filter-section">
                                <div class="filter-section-title">Categoría</div>
                                <div id="filtro-categorias">
                                    <span class="text-muted" style="font-size:.8rem">Cargando...</span>
                                </div>
                            </div>

                            <div class="filter-section" id="seccion-tipos" style="display:none">
                                <div class="filter-section-title">Tipo de producto</div>
                                <div id="filtro-tipos"></div>
                            </div>

                            <div class="filter-section">
                                <div class="filter-section-title">Precio</div>
                                <div class="price-range-inputs">
                                    <input type="number" id="precioMin" placeholder="Mín" min="0">
                                    <span style="color:#aaa;font-size:.8rem">—</span>
                                    <input type="number" id="precioMax" placeholder="Máx" min="0">
                                </div>
                                <button class="btn-apply-filter" id="btnAplicarPrecio">Aplicar precio</button>
                            </div>

                            <div class="filter-section" id="seccion-colores" style="display:none">
                                <div class="filter-section-title">Color</div>
                                <div id="filtro-colores"></div>
                            </div>

                            <div class="filter-section" id="seccion-materiales" style="display:none">
                                <div class="filter-section-title">Material</div>
                                <div id="filtro-materiales"></div>
                            </div>

                            <div class="filter-section" id="seccion-lados" style="display:none">
                                <div class="filter-section-title">Lado</div>
                                <div id="filtro-lados"></div>
                            </div>

                            <div class="filter-section" id="seccion-garantias" style="display:none">
                                <div class="filter-section-title">Garantía</div>
                                <div id="filtro-garantias"></div>
                            </div>

                            <div class="filter-section">
                                <div class="filter-section-title">Tipo</div>
                                <div class="filter-item">
                                    <input type="checkbox" id="filtro-es-servicio" class="filtro-tipo">
                                    <label for="filtro-es-servicio">Solo servicios</label>
                                </div>
                                <div class="filter-item">
                                    <input type="checkbox" id="filtro-con-stock" class="filtro-tipo">
                                    <label for="filtro-con-stock">Con control de stock</label>
                                </div>
                            </div>
                        </div>

                        <!-- Grilla de productos -->
                        <div class="catalog-main" style="flex:1;min-width:0">
                            <div class="row g-3" id="contenedorProductos">
                                <div class="col-12 text-center py-5" id="cargandoProductos">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Cargando catálogo...</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Carrito -->
                <div class="col-lg-4 col-xl-3" id="colCart">
                    <div class="cart-sidebar">
                        <div class="cart-card">
                            <div class="cart-header p-3 border-bottom border-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold">
                                    <i class="bi bi-cart3 me-2 text-primary"></i>Tu Pedido
                                </h5>
                                <span class="badge bg-primary rounded-pill" id="badgeCantidadGlobal">0</span>
                            </div>
                            <!-- Mensaje vacío: FUERA del contenedor de items para no ser destruido por innerHTML='' -->
                            <div class="text-center text-muted py-5" id="carritoVacio">
                                <i class="bi bi-bag-x fs-1 mb-2 d-block"></i>
                                <p class="mb-0">El carrito está vacío</p>
                                <small>Agrega productos para comenzar</small>
                            </div>
                            <div class="cart-items-container" id="contenedorCarrito" style="display:none"></div>
                            <div class="cart-destinatarios px-3 pt-2">
                                <div class="mb-2">
                                    <label for="selSucursal" class="form-label small mb-1 fw-semibold text-muted">Sucursal</label>
                                    <select id="selSucursal" class="form-select form-select-sm">
                                        <option value="">Cargando…</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label for="selCliente" class="form-label small mb-1 fw-semibold text-muted">Cliente</label>
                                    <select id="selCliente" class="form-select form-select-sm">
                                        <option value="">Cargando…</option>
                                    </select>
                                </div>
                            </div>
                            <div class="cart-summary">
                                <div class="summary-row">
                                    <span>Subtotal</span>
                                    <span id="txtSubtotal">$ 0,00</span>
                                </div>
                                <div class="summary-row">
                                    <span>Descuentos</span>
                                    <span class="text-success" id="txtDescuento">-$ 0,00</span>
                                </div>
                                <div class="summary-total">
                                    <span>Total</span>
                                    <span id="txtTotal">$ 0,00</span>
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
    const EMPRESA_ID       = <?= $empresa_idx ?>;
</script>
<script>
const CarritoApp = {
    todosProductos:    [],
    productosFiltrados:[],
    carrito:           [],
    filtrosActivos: {
        q:          '',
        categorias: [],
        tipos:      [],
        precioMin:  null,
        precioMax:  null,
        colores:    [],
        materiales: [],
        lados:      [],
        garantias:  [],
        esServicio: null,
        conStock:   null
    },

    fmt: num => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(num),

    init() {
        this.cargarProductos();
        this.cargarDestinatarios();
        this.bindEvents();
    },

    cargarDestinatarios() {
        $.getJSON(CARRITO_AJAX_URL, { accion: 'obtener_destinatarios', empresa_id: EMPRESA_ID }, res => {
            const selSuc = document.getElementById('selSucursal');
            const selCli = document.getElementById('selCliente');
            if (!res || !res.resultado) {
                selSuc.innerHTML = '<option value="">Sin sucursales</option>';
                selCli.innerHTML = '<option value="">Sin clientes</option>';
                return;
            }
            selSuc.innerHTML = '<option value="">Seleccionar sucursal…</option>' +
                res.sucursales.map(s => `<option value="${s.id}">${s.nombre}</option>`).join('');
            selCli.innerHTML = '<option value="">Seleccionar cliente…</option>' +
                res.clientes.map(c => `<option value="${c.id}">${c.nombre}</option>`).join('');
        });
    },

    bindEvents() {
        document.getElementById('buscarProducto').addEventListener('keyup', e => {
            if (e.key === 'Enter') this.aplicarBusqueda();
        });
        document.getElementById('btnBuscar').addEventListener('click', () => this.aplicarBusqueda());
        document.getElementById('btnAplicarPrecio').addEventListener('click', () => {
            const min = document.getElementById('precioMin').value;
            const max = document.getElementById('precioMax').value;
            this.filtrosActivos.precioMin = min !== '' ? parseFloat(min) : null;
            this.filtrosActivos.precioMax = max !== '' ? parseFloat(max) : null;
            this.aplicarFiltrosLocales();
            this.renderActiveTags();
        });
        document.getElementById('btnLimpiarFiltros').addEventListener('click', () => this.limpiarFiltros());
        document.getElementById('btnProcesarCompra').addEventListener('click', () => this.procesarCompra());
        document.getElementById('filtro-es-servicio').addEventListener('change', e => {
            this.filtrosActivos.esServicio = e.target.checked ? true : null;
            this.aplicarFiltrosLocales(); this.renderActiveTags();
        });
        document.getElementById('filtro-con-stock').addEventListener('change', e => {
            this.filtrosActivos.conStock = e.target.checked ? true : null;
            this.aplicarFiltrosLocales(); this.renderActiveTags();
        });
    },

    aplicarBusqueda() {
        this.filtrosActivos.q = document.getElementById('buscarProducto').value.trim();
        this.aplicarFiltrosLocales();
        this.renderActiveTags();
    },

    /* ── Carga catálogo desde servidor ── */
    cargarProductos() {
        $.ajax({
            url: CARRITO_AJAX_URL,
            type: 'GET',
            data: { accion: 'obtener_catalogo', empresa_id: EMPRESA_ID },
            dataType: 'json',
            success: response => {
                document.getElementById('cargandoProductos').style.display = 'none';

                if (response && response.error_bd) {
                    document.getElementById('contenedorProductos').innerHTML =
                        `<div class="col-12"><div class="alert alert-danger">Error BD: ${response.error_bd}</div></div>`;
                    return;
                }

                const lista = response?.data ?? [];
                if (lista.length > 0) {
                    this.todosProductos     = lista;
                    this.productosFiltrados = lista;
                    this.renderProductos(lista);
                    this.computarFiltrosDesdeProductos(lista);
                } else {
                    const info = response
                        ? ` (empresa_id=${response.empresa_id}, total=${response.total})`
                        : '';
                    document.getElementById('contenedorProductos').innerHTML =
                        `<div class="col-12"><div class="alert alert-warning">No se encontraron productos activos${info}.</div></div>`;
                }
            },
            error: xhr => {
                document.getElementById('cargandoProductos').style.display = 'none';
                document.getElementById('contenedorProductos').innerHTML =
                    '<div class="col-12"><div class="alert alert-danger">Error al conectar con el servidor. Revisá la consola (F12).</div></div>';
                console.error('Error AJAX catálogo:', xhr.status, xhr.responseText);
            }
        });
    },

    /* ── Carga categorías (necesita JOIN, se pide al servidor) ── */
    cargarFiltros() {
        $.getJSON(CARRITO_AJAX_URL, { accion: 'obtener_filtros', empresa_id: EMPRESA_ID }, data => {
            this.renderFiltrosCategorias(data.categorias || []);
        }).fail(() => {
            document.getElementById('filtro-categorias').innerHTML =
                '<span class="text-muted" style="font-size:.8rem">No disponible</span>';
        });
    },

    /* ── Construye filtros de atributos desde los productos ya cargados ── */
    computarFiltrosDesdeProductos(productos) {

        /* ── Filtros de ID+Nombre (categoria, tipo) ── */
        const buildIdNombre = (idField, nameField) => {
            const map = {};
            productos.forEach(p => {
                const id = p[idField]; const name = (p[nameField] ?? '').trim();
                if (id && name) map[id] = name;
            });
            return Object.entries(map)
                .map(([id, nombre]) => ({ id: parseInt(id), nombre }))
                .sort((a, b) => a.nombre.localeCompare(b.nombre));
        };

        const renderIdNombre = (contId, secId, items, idKey, fieldKey) => {
            const sec = document.getElementById(secId);
            if (sec) sec.style.display = '';
            const cont = document.getElementById(contId);
            if (!cont) return;
            cont.innerHTML = '';
            if (!items.length) {
                cont.innerHTML = '<span class="text-muted" style="font-size:.78rem">Sin datos</span>';
                return;
            }
            items.forEach(({ id, nombre }) => {
                const div = document.createElement('div');
                div.className = 'filter-item';
                div.innerHTML = `<input type="checkbox" id="${idKey}_${id}" value="${id}" class="filtro-${idKey}">
                                 <label for="${idKey}_${id}">${nombre}</label>`;
                div.querySelector('input').addEventListener('change', e => {
                    const val = parseInt(e.target.value);
                    if (e.target.checked) { if (!this.filtrosActivos[fieldKey].includes(val)) this.filtrosActivos[fieldKey].push(val); }
                    else this.filtrosActivos[fieldKey] = this.filtrosActivos[fieldKey].filter(v => v !== val);
                    this.aplicarFiltrosLocales(); this.renderActiveTags();
                });
                cont.appendChild(div);
            });
        };

        // Categorías: la sección ya está visible en el HTML, solo poblamos el contenedor
        const catItems = buildIdNombre('categoria_id', 'categoria');
        const catCont  = document.getElementById('filtro-categorias');
        catCont.innerHTML = '';
        if (!catItems.length) {
            catCont.innerHTML = '<span class="text-muted" style="font-size:.78rem">Sin datos</span>';
        } else {
            catItems.forEach(({ id, nombre }) => {
                const div = document.createElement('div');
                div.className = 'filter-item';
                div.innerHTML = `<input type="checkbox" id="cat_${id}" value="${id}" class="filtro-cat">
                                 <label for="cat_${id}">${nombre}</label>`;
                div.querySelector('input').addEventListener('change', e => {
                    const val = parseInt(e.target.value);
                    if (e.target.checked) { if (!this.filtrosActivos.categorias.includes(val)) this.filtrosActivos.categorias.push(val); }
                    else this.filtrosActivos.categorias = this.filtrosActivos.categorias.filter(v => v !== val);
                    this.aplicarFiltrosLocales(); this.renderActiveTags();
                });
                catCont.appendChild(div);
            });
        }

        // Tipos: sección inicialmente hidden, se muestra si hay datos
        renderIdNombre('filtro-tipos', 'seccion-tipos',
            buildIdNombre('tipo_id', 'tipo'), 'tipo', 'tipos');

        /* ── Filtros de texto libre (color, material, lado, garantia) ── */
        const uniqueStr = field => [...new Set(
            productos.map(p => (p[field] ?? '').toString().trim()).filter(v => v !== '' && v !== '0')
        )].sort();

        const strConfigs = [
            { contId: 'filtro-colores',    secId: 'seccion-colores',    field: 'color',    tipo: 'color',    key: 'colores'    },
            { contId: 'filtro-materiales', secId: 'seccion-materiales', field: 'material', tipo: 'material', key: 'materiales' },
            { contId: 'filtro-lados',      secId: 'seccion-lados',      field: 'lado',     tipo: 'lado',     key: 'lados'      },
            { contId: 'filtro-garantias',  secId: 'seccion-garantias',  field: 'garantia', tipo: 'garantia', key: 'garantias'  },
        ];

        strConfigs.forEach(({ contId, secId, field, tipo, key }) => {
            const vals = uniqueStr(field);
            const sec  = document.getElementById(secId);
            sec.style.display = '';
            const cont = document.getElementById(contId);
            cont.innerHTML = '';
            if (!vals.length) {
                cont.innerHTML = '<span class="text-muted" style="font-size:.78rem">Sin datos</span>';
                return;
            }
            vals.forEach(val => {
                const div = document.createElement('div');
                div.className = 'filter-item';
                const safeId = `${tipo}_${String(val).replace(/\s+/g, '_')}`;
                div.innerHTML = `<input type="checkbox" id="${safeId}" value="${val}" class="filtro-${tipo}">
                                 <label for="${safeId}">${val}</label>`;
                div.querySelector('input').addEventListener('change', e => {
                    if (e.target.checked) { if (!this.filtrosActivos[key].includes(val)) this.filtrosActivos[key].push(val); }
                    else this.filtrosActivos[key] = this.filtrosActivos[key].filter(v => v !== val);
                    this.aplicarFiltrosLocales(); this.renderActiveTags();
                });
                cont.appendChild(div);
            });
        });
    },

    renderFiltrosCategorias(cats) {
        const cont = document.getElementById('filtro-categorias');
        if (!cats.length) { cont.innerHTML = '<span class="text-muted" style="font-size:.8rem">Sin categorías</span>'; return; }
        cont.innerHTML = '';
        cats.forEach(cat => {
            const div = document.createElement('div');
            div.className = 'filter-item';
            div.innerHTML = `<input type="checkbox" id="cat_${cat.id}" value="${cat.id}" class="filtro-categoria"><label for="cat_${cat.id}">${cat.nombre}</label>`;
            div.querySelector('input').addEventListener('change', e => {
                const id = parseInt(e.target.value);
                if (e.target.checked) { if (!this.filtrosActivos.categorias.includes(id)) this.filtrosActivos.categorias.push(id); }
                else this.filtrosActivos.categorias = this.filtrosActivos.categorias.filter(c => c !== id);
                this.aplicarFiltrosLocales(); this.renderActiveTags();
            });
            cont.appendChild(div);
        });
    },

    // renderFiltrosCheckbox ya no se usa; la lógica está en computarFiltrosDesdeProductos

    /* ── Filtrado local (sobre los datos ya cargados) ── */
    aplicarFiltrosLocales() {
        const f = this.filtrosActivos;
        let lista = this.todosProductos;

        if (f.q) {
            const q = f.q.toLowerCase();
            lista = lista.filter(p => p.nombre.toLowerCase().includes(q) || p.codigo.toLowerCase().includes(q));
        }
        if (f.categorias.length)  lista = lista.filter(p => f.categorias.includes(p.categoria_id));
        if (f.tipos.length)       lista = lista.filter(p => f.tipos.includes(p.tipo_id));
        if (f.precioMin !== null) lista = lista.filter(p => p.precio >= f.precioMin);
        if (f.precioMax !== null) lista = lista.filter(p => p.precio <= f.precioMax);
        if (f.colores.length)     lista = lista.filter(p => f.colores.includes(p.color));
        if (f.materiales.length)  lista = lista.filter(p => f.materiales.includes(p.material));
        if (f.lados.length)       lista = lista.filter(p => f.lados.includes(p.lado));
        if (f.garantias.length)   lista = lista.filter(p => f.garantias.includes(p.garantia));
        if (f.esServicio !== null) lista = lista.filter(p => p.es_servicio === (f.esServicio ? 1 : 0));
        if (f.conStock !== null)   lista = lista.filter(p => p.controla_stock === (f.conStock ? 1 : 0));

        this.productosFiltrados = lista;
        this.renderProductos(lista);
    },

    renderActiveTags() {
        const f    = this.filtrosActivos;
        const cont = document.getElementById('activeFilterTags');
        cont.innerHTML = '';

        const addTag = (label, onRemove) => {
            const span = document.createElement('span');
            span.className = 'filter-tag';
            span.innerHTML = `${label} <button title="Quitar">×</button>`;
            span.querySelector('button').addEventListener('click', onRemove);
            cont.appendChild(span);
        };

        if (f.q) addTag(`"${f.q}"`, () => {
            f.q = ''; document.getElementById('buscarProducto').value = '';
            this.aplicarFiltrosLocales(); this.renderActiveTags();
        });

        f.categorias.forEach(id => {
            const el = document.getElementById(`cat_${id}`);
            const lbl = el ? el.nextElementSibling.textContent : `Cat. ${id}`;
            addTag(lbl, () => {
                el && (el.checked = false);
                f.categorias = f.categorias.filter(c => c !== id);
                this.aplicarFiltrosLocales(); this.renderActiveTags();
            });
        });

        f.tipos.forEach(id => {
            const el = document.getElementById(`tipo_${id}`);
            const lbl = el ? el.nextElementSibling.textContent : `Tipo ${id}`;
            addTag(`Tipo: ${lbl}`, () => {
                el && (el.checked = false);
                f.tipos = f.tipos.filter(t => t !== id);
                this.aplicarFiltrosLocales(); this.renderActiveTags();
            });
        });

        if (f.precioMin !== null || f.precioMax !== null) {
            const min = f.precioMin !== null ? this.fmt(f.precioMin) : '*';
            const max = f.precioMax !== null ? this.fmt(f.precioMax) : '*';
            addTag(`Precio: ${min} – ${max}`, () => {
                f.precioMin = null; f.precioMax = null;
                document.getElementById('precioMin').value = '';
                document.getElementById('precioMax').value = '';
                this.aplicarFiltrosLocales(); this.renderActiveTags();
            });
        }

        // Tags genéricos para filtros de checkbox
        [
            { key: 'colores',   tipo: 'color',    label: 'Color' },
            { key: 'materiales', tipo: 'material', label: 'Material' },
            { key: 'lados',     tipo: 'lado',     label: 'Lado' },
            { key: 'garantias', tipo: 'garantia', label: 'Garantía' },
        ].forEach(({ key, tipo, label }) => {
            f[key].forEach(val => addTag(`${label}: ${val}`, () => {
                f[key] = f[key].filter(v => v !== val);
                const el = document.getElementById(`${tipo}_${String(val).replace(/\s+/g,'_')}`);
                el && (el.checked = false);
                this.aplicarFiltrosLocales(); this.renderActiveTags();
            }));
        });

        if (f.esServicio !== null) addTag('Solo servicios', () => {
            f.esServicio = null;
            document.getElementById('filtro-es-servicio').checked = false;
            this.aplicarFiltrosLocales(); this.renderActiveTags();
        });
        if (f.conStock !== null) addTag('Con control de stock', () => {
            f.conStock = null;
            document.getElementById('filtro-con-stock').checked = false;
            this.aplicarFiltrosLocales(); this.renderActiveTags();
        });
    },

    limpiarFiltros() {
        this.filtrosActivos = {
            q: '', categorias: [], tipos: [], precioMin: null, precioMax: null,
            colores: [], materiales: [], lados: [], garantias: [],
            esServicio: null, conStock: null
        };
        document.getElementById('buscarProducto').value = '';
        document.getElementById('precioMin').value      = '';
        document.getElementById('precioMax').value      = '';
        document.getElementById('filtro-es-servicio').checked = false;
        document.getElementById('filtro-con-stock').checked   = false;
        document.querySelectorAll('.filtro-cat, .filtro-tipo, .filtro-color, .filtro-material, .filtro-lado, .filtro-garantia')
            .forEach(el => el.checked = false);
        this.renderProductos(this.todosProductos);
        this.renderActiveTags();
    },

    /* ── Render de tarjetas ── */
    renderProductos(lista) {
        const contenedor = document.getElementById('contenedorProductos');
        contenedor.innerHTML = '';

        document.getElementById('txtResultados').textContent =
            lista.length > 0 ? `${lista.length} resultado${lista.length !== 1 ? 's' : ''}` : '';

        if (!lista.length) {
            contenedor.innerHTML = '<div class="col-12 text-center text-muted py-5"><i class="bi bi-search fs-1 d-block mb-2"></i>No se encontraron productos con los filtros aplicados.</div>';
            return;
        }

        lista.forEach(prod => {
            const tienePrecio   = prod.precio > 0;
            const precioFormat  = tienePrecio
                ? this.fmt(prod.precio)
                : '<span class="sin-precio">Sin precio asignado</span>';
            const imgs          = prod.imagenes || [];
            const imgsJson      = JSON.stringify(imgs).replace(/'/g, '&#39;');

            let wrapperContent = '';
            if (!imgs.length) {
                wrapperContent = `<i class="bi bi-box-seam"></i>`;
            } else {
                const arrows = imgs.length > 1
                    ? `<button class="carousel-btn carousel-prev" onclick="event.stopPropagation();CarritoApp.moverImagen(this,-1)">&#8249;</button>
                       <button class="carousel-btn carousel-next" onclick="event.stopPropagation();CarritoApp.moverImagen(this,1)">&#8250;</button>`
                    : '';
                const dots = imgs.length > 1
                    ? `<div class="carousel-dots">${imgs.map((_,i) => `<span class="carousel-dot${i===0?' active':''}"></span>`).join('')}</div>`
                    : '';
                wrapperContent = `${arrows}<img src="${imgs[0]}" alt="${prod.nombre}">${dots}`;
            }

            const col = document.createElement('div');
            col.className = 'col-12 col-sm-6 col-md-4 col-xl-3';
            col.innerHTML = `
            <div class="product-card">
                <div class="product-img-wrapper" data-images='${imgsJson}' data-current="0">
                    ${wrapperContent}
                </div>
                <div class="product-info">
                    ${prod.categoria ? `<span class="product-badge">${prod.categoria}</span>` : ''}
                    <div class="product-price">${precioFormat}</div>
                    <div class="product-title" title="${prod.nombre}">${prod.nombre}</div>
                    <button class="btn-add-cart w-100"
                            onclick="CarritoApp.agregarAlCarrito(${prod.id})"
                            ${!tienePrecio ? 'disabled title="Sin precio disponible"' : ''}>
                        <i class="bi bi-cart-plus me-1"></i> Agregar al carrito
                    </button>
                </div>
            </div>`;
            contenedor.appendChild(col);
        });
    },

    moverImagen(btn, delta) {
        const wrapper = btn.closest('.product-img-wrapper');
        const images  = JSON.parse(wrapper.dataset.images);
        let current   = (parseInt(wrapper.dataset.current) + delta + images.length) % images.length;
        wrapper.dataset.current = current;
        wrapper.querySelector('img').src = images[current];
        wrapper.querySelectorAll('.carousel-dot').forEach((dot, i) => dot.classList.toggle('active', i === current));
    },

    /* ── Carrito ── */
    agregarAlCarrito(id) {
        const producto = this.todosProductos.find(p => p.id === id);
        if (!producto || producto.precio <= 0) return;

        const item = this.carrito.find(i => i.id === id);
        if (item) { item.cantidad++; }
        else {
            this.carrito.push({ id: producto.id, nombre: producto.nombre, precio: parseFloat(producto.precio), cantidad: 1 });
        }
        this.actualizarUI();
        Swal.mixin({ toast:true, position:'bottom-end', showConfirmButton:false, timer:1500, timerProgressBar:true })
            .fire({ icon:'success', title:'Agregado al carrito' });
    },

    modificarCantidad(id, delta) {
        const item = this.carrito.find(i => i.id === id);
        if (!item) return;
        item.cantidad += delta;
        if (item.cantidad <= 0) this.carrito = this.carrito.filter(i => i.id !== id);
        this.actualizarUI();
    },

    actualizarUI() {
        const contenedor = document.getElementById('contenedorCarrito');
        const vacio      = document.getElementById('carritoVacio');

        if (!this.carrito.length) {
            contenedor.style.display = 'none';
            contenedor.innerHTML     = '';
            vacio.style.display      = '';
            document.getElementById('btnProcesarCompra').disabled      = true;
            document.getElementById('badgeCantidadGlobal').innerText   = '0';
            document.getElementById('txtSubtotal').innerText           = this.fmt(0);
            document.getElementById('txtTotal').innerText              = this.fmt(0);
            return;
        }

        vacio.style.display      = 'none';
        contenedor.style.display = '';
        contenedor.innerHTML     = '';
        document.getElementById('btnProcesarCompra').disabled = false;

        let totalItems = 0, subtotal = 0;

        this.carrito.forEach(item => {
            totalItems += item.cantidad;
            subtotal   += item.precio * item.cantidad;
            const div   = document.createElement('div');
            div.className = 'cart-item';
            div.innerHTML = `
            <div class="bg-light rounded p-2 text-primary flex-shrink-0"><i class="bi bi-box"></i></div>
            <div class="cart-item-details">
                <div class="cart-item-title">${item.nombre}</div>
                <div class="d-flex justify-content-between align-items-center mt-1">
                    <div class="cart-item-price">${this.fmt(item.precio * item.cantidad)}</div>
                    <div class="qty-controls shadow-sm">
                        <button class="qty-btn" onclick="CarritoApp.modificarCantidad(${item.id},-1)"><i class="bi bi-dash"></i></button>
                        <input type="text" class="qty-input" value="${item.cantidad}" readonly>
                        <button class="qty-btn" onclick="CarritoApp.modificarCantidad(${item.id},1)"><i class="bi bi-plus"></i></button>
                    </div>
                </div>
            </div>`;
            contenedor.appendChild(div);
        });

        document.getElementById('badgeCantidadGlobal').innerText = totalItems;
        document.getElementById('txtSubtotal').innerText         = this.fmt(subtotal);
        document.getElementById('txtTotal').innerText            = this.fmt(subtotal);
    },

    procesarCompra() {
        if (!this.carrito.length) return;

        const sucursalId = parseInt(document.getElementById('selSucursal').value, 10) || 0;
        const entidadId  = parseInt(document.getElementById('selCliente').value,  10) || 0;
        if (!sucursalId) { Swal.fire('Falta sucursal', 'Seleccioná una sucursal.', 'warning'); return; }
        if (!entidadId)  { Swal.fire('Falta cliente',  'Seleccioná un cliente.',  'warning'); return; }

        Swal.fire({
            title: '¿Confirmar Pedido?',
            text: 'Se generará un comprobante con los productos del carrito.',
            icon: 'question', showCancelButton: true,
            confirmButtonColor: '#3483fa', cancelButtonColor: '#dc3545',
            confirmButtonText: 'Sí, confirmar', cancelButtonText: 'Cancelar'
        }).then(result => {
            if (!result.isConfirmed) return;
            Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            $.ajax({
                url: CARRITO_AJAX_URL, type: 'POST',
                data: {
                    accion:       'guardar_pedido_carrito',
                    empresa_id:   EMPRESA_ID,
                    sucursal_id:  sucursalId,
                    entidad_id:   entidadId,
                    detalles:     JSON.stringify(this.carrito)
                },
                dataType: 'json',
                success: res => {
                    if (res.resultado) {
                        Swal.fire('¡Completado!', 'Tu pedido ha sido registrado con éxito.', 'success')
                            .then(() => { this.carrito = []; this.actualizarUI(); });
                    } else {
                        Swal.fire('Error', res.error || 'Ocurrió un problema al guardar', 'error');
                    }
                },
                error: () => Swal.fire('Error', 'No se pudo conectar con el servidor', 'error')
            });
        });
    }
};

// ── Posicionamiento fijo de los paneles ──────────────────────────────────────
// position:sticky no funciona porque AdminLTE pone overflow:auto en .app-content,
// lo que rompe el contexto de sticky. Usamos position:fixed con coordenadas reales.

function fijarPaneles() {
    const FILTROS_W = 220;   // ancho fijo del panel de filtros (px)
    const GAP       = 16;    // gap entre panel de filtros y catálogo (1rem)
    const MARGEN_B  = 16;    // margen inferior (1rem)
    const MOBILE_BP = 992;   // debajo de lg → no fijar, flujo normal

    const sb        = document.getElementById('stickySearchBar');
    const fp        = document.getElementById('filtersPanel');
    const catMain   = document.querySelector('.catalog-main');
    const cs        = document.querySelector('.cart-sidebar');
    const colCart   = document.getElementById('colCart');
    const colCat    = document.getElementById('colCatalog');

    if (!sb || !fp || !cs) return;

    // ── Modo móvil: restablecer flujo normal ──
    if (window.innerWidth < MOBILE_BP) {
        fp.removeAttribute('style');
        if (catMain) catMain.style.marginLeft = '';
        cs.removeAttribute('style');
        return;
    }

    // ── Paso 1: resetear a flujo normal para medir posiciones reales ──
    fp.style.cssText = '';
    if (catMain) catMain.style.marginLeft = '';
    cs.style.cssText = '';

    // ── Paso 2: medir (sin layout contaminado por fixed anterior) ──
    const sbBottom   = sb.getBoundingClientRect().bottom;
    const panelTop   = sbBottom;
    const panelH     = Math.max(200, window.innerHeight - sbBottom - MARGEN_B);

    // Izquierda del panel de filtros = izquierda de la columna de catálogo
    const catLeft    = colCat
        ? colCat.getBoundingClientRect().left
        : fp.getBoundingClientRect().left;

    // Posición del carrito = izquierda y ancho de su columna
    const cartRect   = colCart ? colCart.getBoundingClientRect() : null;

    // ── Paso 3: aplicar position:fixed ──
    fp.style.cssText = [
        'position:fixed',
        `top:${panelTop}px`,
        `left:${catLeft}px`,
        `width:${FILTROS_W}px`,
        `height:${panelH}px`,
        'overflow-y:auto',
        'z-index:50',
        'background:#fff',
        'border-radius:8px',
        'box-shadow:0 1px 2px rgba(0,0,0,.12)',
        'padding:1rem',
        'box-sizing:border-box',
    ].join(';');

    // Empujar el catálogo para que no quede debajo del panel fijo
    if (catMain) {
        catMain.style.marginLeft = (FILTROS_W + GAP) + 'px';
    }

    if (cartRect) {
        cs.style.cssText = [
            'position:fixed',
            `top:${panelTop}px`,
            `left:${cartRect.left}px`,
            `width:${cartRect.width}px`,
            `height:${panelH}px`,
            'display:flex',
            'flex-direction:column',
            'overflow:hidden',
            'z-index:50',
        ].join(';');
    }
}

// ── Inicialización ────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    CarritoApp.init();

    // doble rAF: espera que AdminLTE termine su layout inicial antes de medir
    requestAnimationFrame(() => requestAnimationFrame(fijarPaneles));

    // Recalcular cuando cambia el alto de la search bar (tags de filtros)
    new ResizeObserver(fijarPaneles).observe(document.getElementById('stickySearchBar'));

    // Recalcular en resize de ventana
    window.addEventListener('resize', fijarPaneles);
});

// Recalcular después de que carguen todos los recursos (AdminLTE puede terminar tarde)
window.addEventListener('load', fijarPaneles);
</script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>