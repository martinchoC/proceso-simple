<?php
// Configuración de la página
$pageTitle = "Gestión de Listas de Precios - Productos";
$currentPage = 'paginas';
$ui_simple = true;
$modudo_idx = 5;
$empresa_idx = intval($_GET['empresa_id'] ?? $_SESSION['empresa_id'] ?? 0);

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<!-- ========== ESTILOS ========== -->
<style>
    /* Estilos para el buscador de etiquetas (mismo patrón que productos.php) */
    .tag-item {
        display: inline-flex;
        align-items: center;
        background-color: #e9ecef;
        border-radius: 16px;
        padding: 2px 8px 2px 12px;
        font-size: 13px;
        font-weight: 500;
        color: #212529;
        transition: all 0.2s;
        margin: 2px 2px;
        white-space: nowrap;
        max-width: 200px;
        border: 1px solid #dee2e6;
    }

    .tag-item:hover {
        background-color: #dee2e6;
    }

    .tag-item .tag-remove {
        cursor: pointer;
        margin-left: 6px;
        font-size: 14px;
        color: #6c757d;
        transition: color 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        line-height: 1;
    }

    .tag-item .tag-remove:hover {
        color: #dc3545;
        background-color: rgba(220, 53, 69, 0.1);
    }

    .tag-item .tag-text {
        max-width: 150px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    #buscadorTagsContainer {
        background-color: #fff;
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        min-height: 38px;
        padding: 4px 8px;
        cursor: text;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    #buscadorTagsContainer:focus-within {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    /* Dashboard */
    .dash-kpi-click {
        cursor: pointer;
        transition: box-shadow 0.15s, transform 0.15s;
    }

    .dash-kpi-click:hover {
        box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.12);
        transform: translateY(-1px);
    }

    .dash-kpi-click.dash-kpi-disabled {
        cursor: default;
        opacity: 0.6;
        box-shadow: none;
        transform: none;
    }

    #tablaDashListas tbody tr {
        cursor: pointer;
    }

    #buscadorTagsInput {
        background: transparent;
        border: none;
        outline: none;
        padding: 4px 0;
        font-size: 14px;
        min-width: 80px;
        flex: 1;
    }
</style>

<main class="app-main">
    <?php ui_encabezado('Precios por lista','', '', ['Ventas']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <ul class="nav nav-tabs mb-3" id="tabsListasPrecios" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tabPreciosBtn" data-bs-toggle="tab" data-bs-target="#tabPrecios" type="button" role="tab">
                        <i class="fas fa-list me-1"></i> Precios
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tabDashboardBtn" data-bs-toggle="tab" data-bs-target="#tabDashboard" type="button" role="tab">
                        <i class="fas fa-chart-bar me-1"></i> Dashboard
                    </button>
                </li>
            </ul>
            <div class="tab-content">
            <div class="tab-pane fade show active" id="tabPrecios" role="tabpanel">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <!-- Fila 1: Filtro por tipo (productos / servicios) -->
                            <div class="row mb-2">
                                <div class="col-md-3">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text" title="Tipo"><i class="fas fa-tags"></i></span>
                                        <select class="form-select" id="filtroTipo">
                                            <option value="">Productos y servicios</option>
                                            <option value="P">Solo productos</option>
                                            <option value="S">Solo servicios</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-9 d-flex align-items-end justify-content-end">
                                    <button class="btn btn-primary me-2" id="btnNuevo" data-funcion="agregar">Nuevo Precio</button>
                                    <button class="btn btn-success" id="btnImportarExcel" data-funcion="agregar">Importar Excel</button>
                                </div>
                            </div>
                            <!-- Fila 2: Filtros de lista + buscador combinado -->
                            <div class="row mb-2">
                                <div class="col-md-3">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text" title="Lista de Precios"><i class="fas fa-list"></i></span>
                                        <select class="form-select" id="filtroLista">
                                            <option value="">Todas las listas</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text" title="Buscar por código, nombre o descripción"><i class="fas fa-search"></i></span>
                                        <div class="form-control p-1" id="buscadorTagsContainer" style="min-height: 38px; display: flex; flex-wrap: wrap; align-items: center; gap: 4px; cursor: text;">
                                            <input type="text" id="buscadorTagsInput" class="border-0 flex-grow-1" style="min-width: 100px; outline: none; padding: 4px 8px; font-size: 14px;" placeholder="Buscar código, producto o servicio...">
                                        </div>
                                        <button type="button" class="btn btn-outline-secondary" id="btnLimpiarTags" title="Limpiar todos los filtros">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">
                                        Presioná <kbd>Espacio</kbd> para agregar una palabra (se combinan con AND). La <i class="fas fa-times"></i> limpia todos los filtros.
                                        <span id="infoFiltros" class="badge bg-light text-dark ms-1" style="display:none;"></span>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <table id="tablaListasPreciosProductos" class="table table-striped table-bordered" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Lista</th>
                                        <th>Código</th>
                                        <th>Producto / Servicio</th>
                                        <th>Tipo</th>
                                        <th>Ubicación</th>
                                        <th width="70" class="text-center">Imagen</th>
                                        <th class="text-end">Precio Unitario</th>
                                        <th class="text-end">Precio c/IVA</th>
                                        <th>Última Actualización</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            </div><!-- /#tabPrecios -->

            <!-- ========== PESTAÑA DASHBOARD ========== -->
            <div class="tab-pane fade" id="tabDashboard" role="tabpanel">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <div class="input-group input-group-sm" style="max-width: 320px;">
                        <span class="input-group-text" title="Lista de Precios"><i class="fas fa-list"></i></span>
                        <select class="form-select" id="dashLista">
                            <option value="">Todas las listas</option>
                        </select>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary" id="btnDashActualizar" title="Actualizar indicadores">
                        <i class="fas fa-sync-alt"></i> Actualizar
                    </button>
                    <small class="text-muted ms-auto" id="dashInfo"></small>
                </div>

                <!-- KPIs -->
                <div class="row g-3 mb-3" id="dashKpis">
                    <div class="col-xl-3 col-md-6">
                        <div class="card dash-kpi dash-kpi-click border-start border-4 border-danger h-100" data-tipo="sin_precio" title="Ver productos sin precio">
                            <div class="card-body py-3">
                                <div class="text-muted small"><i class="fas fa-tag me-1"></i> Productos sin precio</div>
                                <div class="fs-3 fw-semibold" id="kpiSinPrecio">-</div>
                                <div class="small text-muted" id="kpiSinPrecioSub">&nbsp;</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card dash-kpi dash-kpi-click border-start border-4 border-warning h-100" data-tipo="parcial" id="kpiParcialCard" title="Ver productos con precio en algunas listas pero no en todas">
                            <div class="card-body py-3">
                                <div class="text-muted small"><i class="fas fa-adjust me-1"></i> Cobertura parcial</div>
                                <div class="fs-3 fw-semibold" id="kpiParcial">-</div>
                                <div class="small text-muted" id="kpiParcialSub">&nbsp;</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card dash-kpi border-start border-4 border-success h-100">
                            <div class="card-body py-3">
                                <div class="text-muted small"><i class="fas fa-check-circle me-1"></i> Cobertura completa</div>
                                <div class="fs-3 fw-semibold" id="kpiCobertura">-</div>
                                <div class="progress mt-1" style="height: 6px;">
                                    <div class="progress-bar bg-success" id="kpiCoberturaBar" style="width: 0%;"></div>
                                </div>
                                <div class="small text-muted mt-1" id="kpiCoberturaSub">&nbsp;</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card dash-kpi border-start border-4 border-primary h-100">
                            <div class="card-body py-3">
                                <div class="text-muted small"><i class="fas fa-dollar-sign me-1"></i> Precios activos</div>
                                <div class="fs-3 fw-semibold" id="kpiPrecios">-</div>
                                <div class="small text-muted" id="kpiPreciosSub">&nbsp;</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card dash-kpi dash-kpi-click border-start border-4 border-info h-100" data-tipo="actualizados_30" title="Ver precios actualizados en los últimos 30 días">
                            <div class="card-body py-3">
                                <div class="text-muted small"><i class="fas fa-history me-1"></i> Actualizados últimos 30 días</div>
                                <div class="fs-3 fw-semibold" id="kpiAct30">-</div>
                                <div class="small text-muted" id="kpiAct30Sub">&nbsp;</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card dash-kpi dash-kpi-click border-start border-4 border-warning h-100" data-tipo="desactualizados" title="Ver precios sin actualizar hace más de 90 días">
                            <div class="card-body py-3">
                                <div class="text-muted small"><i class="fas fa-hourglass-half me-1"></i> Sin actualizar +90 días</div>
                                <div class="fs-3 fw-semibold" id="kpiDesact">-</div>
                                <div class="small text-muted" id="kpiDesactSub">&nbsp;</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card dash-kpi dash-kpi-click border-start border-4 border-danger h-100" data-tipo="bajo_costo" title="Ver precios por debajo del costo actual">
                            <div class="card-body py-3">
                                <div class="text-muted small"><i class="fas fa-arrow-down me-1"></i> Precio menor al costo</div>
                                <div class="fs-3 fw-semibold" id="kpiBajoCosto">-</div>
                                <div class="small text-muted" id="kpiBajoCostoSub">&nbsp;</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card dash-kpi border-start border-4 border-secondary h-100">
                            <div class="card-body py-3">
                                <div class="text-muted small"><i class="fas fa-calendar-alt me-1"></i> Antigüedad promedio</div>
                                <div class="fs-3 fw-semibold" id="kpiAntiguedad">-</div>
                                <div class="small text-muted" id="kpiAntiguedadSub">&nbsp;</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos -->
                <div class="row g-3 mb-3">
                    <div class="col-lg-8">
                        <div class="card h-100">
                            <div class="card-header">
                                <h3 class="card-title mb-0">Precios por mes de última actualización</h3>
                            </div>
                            <div class="card-body">
                                <div style="position: relative; height: 300px;">
                                    <canvas id="chartMeses"></canvas>
                                </div>
                                <?= ui_ayuda('Hacé click en una barra para ver el listado de ese mes.') ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h3 class="card-title mb-0">Antigüedad del precio</h3>
                            </div>
                            <div class="card-body">
                                <div style="position: relative; height: 300px;">
                                    <canvas id="chartAntiguedad"></canvas>
                                </div>
                                <?= ui_ayuda('Click en una barra para ver el detalle.') ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cobertura por lista -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title mb-0">Cobertura por lista de precios</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0" id="tablaDashListas">
                                <thead>
                                    <tr>
                                        <th>Lista</th>
                                        <th class="text-end">Con precio</th>
                                        <th class="text-end">Sin precio</th>
                                        <th style="min-width: 180px;">Cobertura</th>
                                        <th>Última actualización</th>
                                        <th class="text-center">Faltantes</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div><!-- /#tabDashboard -->
            </div><!-- /.tab-content -->
        </div>
    </div>

    <!-- Modal detalle del dashboard -->
    <div class="modal fade" id="modalDashDetalle" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dashDetalleTitulo">Detalle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning py-2 small" id="dashDetalleAviso" style="display:none;"></div>
                    <table id="tablaDashDetalle" class="table table-striped table-bordered table-sm" style="width:100%"></table>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal para Importar Excel -->
    <div class="modal fade" id="modalImportarExcel" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Importar Precios desde Excel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formImportarExcel">
                        <div class="mb-3">
                            <label for="importListaPrecioId" class="form-label">Lista de Precios *</label>
                            <select class="form-control" id="importListaPrecioId" required>
                                <option value="">Seleccionar lista...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="archivoExcel" class="form-label">Archivo Excel (.xlsx) <?= ui_ayuda('El archivo debe tener la columna A con el código del producto y la columna C con el precio.') ?></label>
                            <input type="file" class="form-control" id="archivoExcel" accept=".xlsx, .xls" required>
                        </div>
                        <div id="importProgress" style="display: none;">
                            <div class="progress">
                                <div id="importProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;"></div>
                            </div>
                            <p id="importStatus" class="mt-2">Procesando...</p>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button id="btnProcesarImportacion" class="btn btn-success">Procesar Importación</button>
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="modalListaPrecioProducto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Precio de Producto / Servicio en Lista</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formListaPrecioProducto">
                        <input type="hidden" id="lista_precio_producto_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label>Lista de Precios *</label>
                                <select class="form-control" id="lista_precio_id" required>
                                    <option value="">Seleccionar lista...</option>
                                </select>
                                <div class="invalid-feedback">Seleccione una lista</div>
                            </div>
                            <div class="col-md-6">
                                <label>Producto / Servicio *</label>
                                <select class="form-control" id="producto_id" required>
                                    <option value="">Seleccionar producto o servicio...</option>
                                </select>
                                <div class="invalid-feedback">Seleccione un producto o servicio</div>
                            </div>
                            <div class="col-md-6">
                                <label>Precio Unitario *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="precio_unitario" step="0.0001" min="0" required>
                                </div>
                                <div class="invalid-feedback">El precio es obligatorio</div>
                            </div>
                            <div class="col-md-6">
                                <label>Ajuste ID</label>
                                <input type="number" class="form-control" id="ajuste_id" min="0">
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

    <script>
    $(document).ready(function(){
        const empresa_idx = window.EMPRESA_ID; // empresa activa (header1.php)

        function cargarListasPrecios() {
            $.get('listas_precios_productos_ajax.php', {accion: 'obtener_listas'}, function(res){
                let optionsFiltro = '<option value="">Todas las listas</option>';
                let optionsModal = '<option value="">Seleccionar lista...</option>';
                res.forEach(lista => {
                    optionsFiltro += `<option value="${lista.lista_precio_id}">${lista.nombre}</option>`;
                    optionsModal += `<option value="${lista.lista_precio_id}">${lista.nombre}</option>`;
                });
                $('#filtroLista').html(optionsFiltro);
                $('#lista_precio_id').html(optionsModal);
            });
        }

        function cargarProductos() {
            // Servicios y productos en grupos separados del selector
            $.get('listas_precios_productos_ajax.php', {accion: 'obtener_productos'}, function(res){
                const esc = t => $('<div>').text(t == null ? '' : t).html();
                const grupo = (titulo, filas) => filas.length
                    ? `<optgroup label="${titulo}">` + filas.map(p => `<option value="${p.producto_id}">${esc(p.producto_codigo)} - ${esc(p.producto_nombre)}</option>`).join('') + '</optgroup>'
                    : '';
                $('#producto_id').html('<option value="">Seleccionar producto o servicio...</option>'
                    + grupo('Servicios', res.filter(p => p.es_servicio == 1))
                    + grupo('Productos', res.filter(p => p.es_servicio != 1)));
            });
        }

        // Agranda la miniatura de producto en un Swal en vez de armar un modal
        // aparte: SweetAlert2 ya está cargado en esta página para todo lo demás.
        function verImagenGrande(src, titulo) {
            Swal.fire({
                title: titulo,
                imageUrl: src,
                imageAlt: titulo,
                width: 650,
                showConfirmButton: false,
                showCloseButton: true
            });
        }

        // ========== BUSCADOR POR TAGS (código, nombre y descripción) ==========
        var tags = [];
        var tagsInput, tagsContainer;
        var terminoBusquedaActual = ''; // se manda al servidor en cada ajax (ver ejecutarBusquedaTags)

        function agregarTag(texto) {
            texto = texto.trim();
            if (!texto) return;
            var duplicado = tags.some(function(tag) { return tag.toLowerCase() === texto.toLowerCase(); });
            if (duplicado) { tagsInput.val(''); return; }
            tags.push(texto);
            renderizarTags();
            tagsInput.val('');
            tagsInput.focus();
        }

        function eliminarTag(index) {
            if (index >= 0 && index < tags.length) {
                tags.splice(index, 1);
                renderizarTags();
            }
        }

        function limpiarTags() {
            tags = [];
            renderizarTags();
        }

        function renderizarTags() {
            tagsContainer.find('.tag-item').remove();
            tags.forEach(function(tag, index) {
                var tagHtml = `
                    <span class="tag-item" data-index="${index}">
                        <span class="tag-text">${escapeHtml(tag)}</span>
                        <span class="tag-remove" data-index="${index}" title="Eliminar"><i class="fas fa-times"></i></span>
                    </span>
                `;
                tagsContainer.find('#buscadorTagsInput').before(tagHtml);
            });
            tagsContainer.find('.tag-remove').on('click', function(e) {
                e.stopPropagation();
                var index = parseInt($(this).data('index'));
                eliminarTag(index);
                ejecutarBusquedaTags();
            });
        }

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Precios del Excel de importación vienen como texto con coma
        // decimal (ej: "36398,97"), no como número JS. parseFloat() a secas
        // trunca en la coma ("2,20" -> 2), así que primero se saca cualquier
        // punto de miles y se reemplaza la coma decimal por punto. Si la
        // celda ya viene como número (otro formato de Excel), se usa tal cual.
        function parsearPrecioExcel(valor) {
            if (valor === null || valor === undefined || valor === '') return 0;
            if (typeof valor === 'number') return valor;
            var texto = valor.toString().trim();
            if (texto.indexOf(',') !== -1) {
                texto = texto.replace(/\./g, '').replace(',', '.');
            }
            var numero = parseFloat(texto);
            return isNaN(numero) ? 0 : numero;
        }

        function ejecutarBusquedaTags() {
            // VERSION 2026-09-05: antes usaba tabla.search() (búsqueda "smart"
            // de DataTables sobre lo ya cargado en el navegador). Ahora la
            // tabla es server-side: cada palabra se manda al backend y se
            // exige (AND) contra código, nombre, descripción y lista, sin
            // traer de antemano todo el catálogo al navegador.
            terminoBusquedaActual = tags.join(' ');
            if (tags.length > 0) {
                $('#buscadorTagsContainer').attr('title', 'Buscando ' + tags.length + ' palabra(s): ' + tags.join(', '));
            } else {
                $('#buscadorTagsContainer').removeAttr('title');
            }
            if (tabla) {
                tabla.ajax.reload(); // reload() vuelve a la página 1 por default
            }
        }

        function inicializarBuscadorTags() {
            tagsInput = $('#buscadorTagsInput');
            tagsContainer = $('#buscadorTagsContainer');

            tagsContainer.on('click', function(e) {
                if (e.target === this || $(e.target).is('#buscadorTagsContainer')) tagsInput.focus();
            });

            tagsInput.on('input', function(e) {
                var value = $(this).val().trim();
                if (value.includes(' ')) {
                    var palabras = value.split(/\s+/);
                    palabras.forEach(function(palabra) {
                        if (palabra.length > 0) agregarTag(palabra);
                    });
                    $(this).val('');
                    ejecutarBusquedaTags();
                }
            });

            tagsInput.on('keydown', function(e) {
                var value = $(this).val().trim();
                if (e.key === ' ' || e.key === 'Space') {
                    e.preventDefault();
                    if (value.length > 0) {
                        agregarTag(value);
                        $(this).val('');
                        ejecutarBusquedaTags();
                    }
                } else if (e.key === 'Backspace' && value === '' && tags.length > 0) {
                    eliminarTag(tags.length - 1);
                    ejecutarBusquedaTags();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (value.length > 0) {
                        agregarTag(value);
                        $(this).val('');
                        ejecutarBusquedaTags();
                    }
                } else if (e.key === 'Escape') {
                    $(this).blur();
                }
            });

            tagsInput.on('paste', function() {
                setTimeout(function() {
                    var value = tagsInput.val().trim();
                    if (value) {
                        var palabras = value.split(/\s+/);
                        palabras.forEach(function(palabra) {
                            if (palabra.length > 0) agregarTag(palabra);
                        });
                        tagsInput.val('');
                        ejecutarBusquedaTags();
                    }
                }, 10);
            });

        }

        function actualizarIndicadorFiltros() {
            var lista = $('#filtroLista').val();
            var tipo = $('#filtroTipo').val();
            var total = 0;
            if (lista) total++;
            if (tipo) total++;
            if (tags.length > 0) total++;

            // La X del buscador ahora limpia TODOS los filtros, no solo el
            // texto libre: la marcamos en rojo cuando hay algo activo.
            if (total > 0) {
                $('#btnLimpiarTags').removeClass('btn-outline-secondary').addClass('btn-danger')
                    .attr('title', 'Limpiar todos los filtros (' + total + ' activo' + (total > 1 ? 's' : '') + ')');
            } else {
                $('#btnLimpiarTags').removeClass('btn-danger').addClass('btn-outline-secondary')
                    .attr('title', 'Limpiar todos los filtros');
            }
        }

        // VERSION 2026-10-01: exportación del listado filtrado completo con
        // server-side. Recarga una vez pidiendo length=-1 (mismos filtros,
        // búsqueda y orden), cancela el dibujado de esas filas (preDraw
        // false), ejecuta la exportación original del botón (excelHtml5 /
        // pdfHtml5) sobre todos los datos ya cargados y vuelve a recargar la
        // página en la que estaba el usuario.
        //
        // Recibe el tipo de botón explícito ('excelHtml5' / 'pdfHtml5'):
        // Buttons 2.4 pisa config.extend al resolver el botón (queda
        // undefined), así que no se puede leer de ahí.
        function exportarFiltradoCompleto(tipoBoton) {
            return function(e, dt, button, config, cb) {
                var self = this;
                var inicioAnterior = dt.settings()[0]._iDisplayStart;

                dt.one('preXhr', function(ev, s, data) {
                    data.start = 0;
                    data.length = -1;

                    dt.one('preDraw', function(ev2, settings) {
                        try {
                            $.fn.dataTable.ext.buttons[tipoBoton].action.call(self, e, dt, button, config, cb);
                        } catch (err) {
                            console.error('Error exportando listado completo:', err);
                            Swal.fire('Error', 'No se pudo generar la exportación.', 'error');
                        } finally {
                            // Siempre volver a la página en la que estaba,
                            // aunque la exportación falle.
                            dt.one('preXhr', function(ev3, s3, data3) {
                                settings._iDisplayStart = inicioAnterior;
                                data3.start = inicioAnterior;
                            });
                            setTimeout(function() { dt.ajax.reload(null, false); }, 0);
                        }
                        return false; // no dibujar las ~miles de filas en pantalla
                    });
                });

                dt.ajax.reload(null, false);
            };
        }

        var tabla = $('#tablaListasPreciosProductos').DataTable({
            dom: '<"row mb-2"<"col-12 text-end"B>>rt<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>',
            responsive: true,
            processing: true,
            serverSide: true,
            pageLength: 10, // explícito: antes dependía del default implícito de DataTables
            // VERSION 2026-09-05: server-side real (antes carga completa +
            // búsqueda/orden/paginación en el navegador). Con ~5.000 filas la
            // carga completa se sentía cada vez más lenta apenas se agregaban
            // listas/productos; ahora cada draw pide sólo la página actual, y
            // el texto libre se busca en el servidor, no en el navegador.
            //
            // OJO exportación: los botones Excel/PDF de DataTables sólo
            // pueden exportar lo que hay cargado en el navegador, y con
            // server-side eso es SOLO la página visible. VERSION 2026-10-01:
            // por eso los botones usan exportarFiltradoCompleto(), que pide
            // al backend el listado filtrado completo (length=-1) antes de
            // exportar y después vuelve a la página en la que estaba.
            buttons: [
                {
                    extend: 'excelHtml5', text: '<i class="fas fa-file-excel"></i> Excel', className: 'btn btn-success btn-sm me-2',
                    // La columna Imagen (índice 5) no exporta bien a Excel
                    // (es un <img>, no texto), así que se excluye.
                    exportOptions: { columns: ':not(:eq(5))' },
                    title: 'Listas de precios',
                    action: exportarFiltradoCompleto('excelHtml5')
                },
                {
                    extend: 'pdfHtml5', text: '<i class="fas fa-file-pdf"></i> PDF', className: 'btn btn-danger btn-sm',
                    exportOptions: { columns: [0, 1, 2, 6, 7] },
                    title: 'Listas de precios',
                    action: exportarFiltradoCompleto('pdfHtml5')
                }
            ],
            ajax: {
                url: 'listas_precios_productos_ajax.php',
                type: 'GET',
                data: function(d) {
                    d.accion = 'listar';
                    d.filtro_lista = $('#filtroLista').val();
                    d.filtro_tipo = $('#filtroTipo').val();
                    d.busqueda = terminoBusquedaActual;
                }
            },
            language: { url: '<?= asset_local('js/datatables-i18n-1.13.4/es-ES.json') ?>' },
            drawCallback: function(settings) {
                // Server-side: el total ya viene filtrado desde el backend
                // (recordsFiltered), no se recuenta en el navegador.
                var total = this.api().page.info().recordsDisplay;
                var lista = $('#filtroLista').val();
                var tipo = $('#filtroTipo').val();
                var filtroActivo = lista || tipo || tags.length > 0;

                if (filtroActivo && total > 0) {
                    $('#infoFiltros').show().html('<i class="fas fa-filter text-primary me-1"></i> ' + total + ' resultados');
                } else if (filtroActivo && total === 0) {
                    $('#infoFiltros').show().html('<i class="fas fa-exclamation-triangle text-warning me-1"></i> No se encontraron resultados');
                } else {
                    $('#infoFiltros').hide();
                }
                actualizarIndicadorFiltros();
            },
            columns: [
                { data: 'lista_nombre', responsivePriority: 6 },
                { data: 'producto_codigo', responsivePriority: 1 },
                { data: 'producto_nombre', responsivePriority: 2 },
                {
                    data: 'es_servicio',
                    responsivePriority: 9,
                    render: function(data, type) {
                        var texto = data == 1 ? 'Servicio' : 'Producto';
                        if (type !== 'display') return texto;
                        return data == 1
                            ? '<span class="badge bg-info text-dark">Servicio</span>'
                            : '<span class="badge bg-light text-dark border">Producto</span>';
                    }
                },
                {
                    data: 'ubicaciones_info',
                    responsivePriority: 8,
                    orderable: false, // se resuelve aparte (2do paso), no está en el whitelist de orden server-side
                    render: {
                        // '_': texto plano para buscar (sucursal + sección/estantería/etc,
                        // sin el HTML de los badges).
                        _: function(data) {
                            if (!data) return '';
                            return data.split(';;').map(function(loc) { return loc.split('|').join(' '); }).join(' ');
                        },
                        display: function(data) {
                            if (!data) return '<span class="text-muted">-</span>';
                            var ubicaciones = data.split(';;');
                            var mostrar = ubicaciones.slice(0, 2);
                            var html = mostrar.map(function(loc) {
                                var partes = loc.split('|'); // [sucursal, seccion, estanteria, estante, posicion]
                                var sucursal = partes[0], seccion = partes[1], estanteria = partes[2], estante = partes[3], posicion = partes[4];
                                var badges = '';
                                if (seccion) badges += '<span class="badge bg-secondary me-1">' + seccion + '</span>';
                                if (estanteria) badges += '<span class="badge bg-info text-dark me-1">' + estanteria + '</span>';
                                if (estante) badges += '<span class="badge bg-warning text-dark me-1">' + estante + '</span>';
                                if (posicion) badges += '<span class="badge bg-light text-dark border">' + posicion + '</span>';
                                var prefijo = sucursal ? '<small class="text-muted d-block">' + sucursal + '</small>' : '';
                                return '<div class="mb-1">' + prefijo + badges + '</div>';
                            }).join('');
                            if (ubicaciones.length > 2) {
                                html += '<small class="text-muted">+' + (ubicaciones.length - 2) + ' más</small>';
                            }
                            return html;
                        }
                    }
                },
                {
                    data: 'imagen_id_principal', orderable: false, searchable: false, className: 'text-center',
                    responsivePriority: 10,
                    render: function(data, type, row) {
                        if (type !== 'display') return data || '';
                        if (!data) return '<span class="text-muted"><i class="fas fa-image"></i></span>';
                        var src = 'get_imagen.php?id=' + data;
                        var alt = (row.producto_nombre || 'Producto').replace(/"/g, '');
                        return '<img src="' + src + '" alt="' + alt + '" class="img-thumbnail rounded" ' +
                               'style="width:40px;height:40px;object-fit:cover;cursor:pointer;" ' +
                               'onclick="verImagenGrande(\'' + src + '\', \'' + alt.replace(/'/g, "\\'") + '\')" ' +
                               'onerror="this.onerror=null;this.src=\'\';this.replaceWith(document.createTextNode(\'-\'));">';
                    }
                },
                {
                    data: 'precio_unitario',
                    className: 'text-end',
                    responsivePriority: 3,
                    render: data => '$ ' + Fmt.numero(parseFloat(data), { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                },
                {
                    data: 'precio_con_iva',
                    className: 'text-end',
                    responsivePriority: 5,
                    orderable: false, // depende del join de IVA, no está en el whitelist de orden server-side (ver ajax)
                    render: function(data, type, row) {
                        if (type !== 'display') return data;
                        var iva = row.iva_porcentaje ? parseFloat(row.iva_porcentaje) : 0;
                        var formateado = Fmt.numero(parseFloat(data), { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        return '<span title="IVA ' + iva + '%">$ ' + formateado + '</span>';
                    }
                },
                { data: 'f_actualizacion', responsivePriority: 7, render: data => data ? Fmt.fechaHora(data) : '-' },
                {
                    data: null, orderable: false, className: "text-center",
                    responsivePriority: 1,
                    render: data => `<div class="btn-group btn-group-sm">
                                        <button class="btn btn-primary btnEditar" data-funcion="editar"><i class="fa fa-edit"></i></button>
                                        <button class="btn btn-danger btnEliminar" data-funcion="inactivar"><i class="fa fa-trash"></i></button>
                                    </div>`
                }
            ]
        });

        cargarListasPrecios();
        cargarProductos();
        inicializarBuscadorTags();

        $('#filtroTipo, #filtroLista').change(() => tabla.ajax.reload());

        $('#btnLimpiarTags').click(function(){
            $('#filtroLista, #filtroTipo').val('');
            limpiarTags();
            ejecutarBusquedaTags();
            tabla.ajax.reload();
        });

        $('#btnNuevo').click(function(){
            $('#formListaPrecioProducto')[0].reset();
            $('#lista_precio_producto_id').val('');
            new bootstrap.Modal(document.getElementById('modalListaPrecioProducto')).show();
        });

        $('#tablaListasPreciosProductos tbody').on('click', '.btnEditar', function(){
            let data = tabla.row($(this).parents('tr')).data();
            $.get('listas_precios_productos_ajax.php', {accion: 'obtener', lista_precio_producto_id: data.lista_precio_producto_id}, function(res){
                if(res){
                    $('#lista_precio_producto_id').val(res.lista_precio_producto_id);
                    $('#lista_precio_id').val(res.lista_precio_id);
                    $('#producto_id').val(res.producto_id);
                    // toFixed(4): la base guarda 6 decimales; 4 alcanzan para cargar netos
                    // que con el IVA cierren al centavo (evita ver "1500.000000").
                    $('#precio_unitario').val(parseFloat(res.precio_unitario).toFixed(4));
                    $('#ajuste_id').val(res.ajuste_id);
                    new bootstrap.Modal(document.getElementById('modalListaPrecioProducto')).show();
                } else Swal.fire('Error', 'Error al obtener datos', 'error');
            });
        });

        $('#tablaListasPreciosProductos tbody').on('click', '.btnEliminar', function(){
            let data = tabla.row($(this).parents('tr')).data();
            Swal.fire({
                title: '¿Eliminar precio?', text: 'No se puede deshacer', icon: 'warning',
                showCancelButton: true, confirmButtonColor: '#3085d6', cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar'
            }).then((result) => {
                if(result.isConfirmed){
                    $.get('listas_precios_productos_ajax.php', {accion: 'eliminar', lista_precio_producto_id: data.lista_precio_producto_id}, function(res){
                        if(res.resultado){
                            let page = tabla.page();
                            tabla.ajax.reload(() => tabla.page(page).draw('page'));
                            Swal.fire('Eliminado', '', 'success');
                        } else Swal.fire('Error', res.error || 'No se pudo eliminar', 'error');
                    });
                }
            });
        });

        $('#btnGuardar').click(function(){
            let form = document.getElementById('formListaPrecioProducto');
            if(!form.checkValidity()) return form.classList.add('was-validated');
            let id = $('#lista_precio_producto_id').val();
            $.post('listas_precios_productos_ajax.php', {
                accion: id ? 'editar' : 'agregar',
                lista_precio_producto_id: id,
                lista_precio_id: $('#lista_precio_id').val(),
                producto_id: $('#producto_id').val(),
                precio_unitario: $('#precio_unitario').val(),
                ajuste_id: $('#ajuste_id').val() || null
            }, function(res){
                if(res.resultado){
                    let page = tabla.page();
                    tabla.ajax.reload(() => tabla.page(page).draw('page'));
                    bootstrap.Modal.getInstance(document.getElementById('modalListaPrecioProducto')).hide();
                    Swal.fire('Guardado', '', 'success');
                    $(document).trigger('listaPrecio:guardado'); // refresca el dashboard si ya se abrió
                } else Swal.fire('Error', res.error || 'Error al guardar', 'error');
            });
        });
        function cargarListasPreciosImport() {
            $.get('listas_precios_productos_ajax.php', {accion: 'obtener_listas'}, function(res){
                let options = '<option value="">Seleccionar lista...</option>';
                res.forEach(lista => {
                    options += `<option value="${lista.lista_precio_id}">${lista.nombre}</option>`;
                });
                $('#importListaPrecioId').html(options);
            });
        }

        // Inicializar funciones de carga
        cargarListasPrecios();
        cargarProductos();
        cargarListasPreciosImport(); // Cargar para el modal de importación

        // --- Botón para abrir el modal de importación ---
        $('#btnImportarExcel').click(function(){
            $('#formImportarExcel')[0].reset();
            $('#importProgress').hide();
            $('#importProgressBar').css('width', '0%');
            new bootstrap.Modal(document.getElementById('modalImportarExcel')).show();
        });

        // --- Procesar la importación ---
        $('#btnProcesarImportacion').click(function(){
            var listaId = $('#importListaPrecioId').val();
            var fileInput = document.getElementById('archivoExcel');

            if (!listaId) {
                Swal.fire('Error', 'Debe seleccionar una lista de precios.', 'error');
                return;
            }
            if (!fileInput.files || fileInput.files.length === 0) {
                Swal.fire('Error', 'Debe seleccionar un archivo Excel.', 'error');
                return;
            }

            // Tamaño de lote: se manda de a partes al servidor en vez de todo
            // junto. Esto es lo que permite ver el progreso real (no una barra
            // que salta de 10% a 40% y se queda esperando una única respuesta),
            // y además evita mandar un POST gigante y un loop de 5.000 filas
            // en un solo request PHP (riesgo de timeout). 300 es un punto de
            // partida razonable; si el servidor responde rápido se puede subir.
            var TAM_LOTE = 300;

            // Mostrar barra de progreso
            $('#importProgress').show();
            $('#importProgressBar').css('width', '5%');
            $('#importStatus').text('Leyendo archivo...');

            var reader = new FileReader();
            reader.onload = function(e) {
                try {
                    var data = new Uint8Array(e.target.result);
                    var workbook = XLSX.read(data, {type: 'array'});
                    var firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                    var jsonData = XLSX.utils.sheet_to_json(firstSheet, {header: 1});

                    // Mapear datos: columna A (índice 0) = código, columna C (índice 2) = precio.
                    // No hace falta detectar fila de encabezado a mano: filas de
                    // título/encabezado/vacías se descartan solas porque su
                    // columna C no parsea a un precio > 0.
                    var productos = [];
                    for (var i = 0; i < jsonData.length; i++) {
                        var row = jsonData[i];
                        var codigo = row[0] ? row[0].toString().trim() : '';
                        var precio = parsearPrecioExcel(row[2]); // Columna C (índice 2)

                        if (codigo !== '' && precio > 0) {
                            productos.push({codigo: codigo, precio: precio});
                        }
                    }

                    if (productos.length === 0) {
                        $('#importProgress').hide();
                        Swal.fire('Error', 'No se encontraron datos válidos en el archivo. Asegúrese de que la columna A tenga códigos y la columna C precios.', 'error');
                        return;
                    }

                    // --- Envío por lotes con progreso real ---
                    var totalLotes = Math.ceil(productos.length / TAM_LOTE);
                    var loteActual = 0;
                    var acumulado = {
                        procesados: 0,
                        sin_cambios: 0,
                        no_encontrados_count: 0,
                        errores_count: 0,
                        detalle: []
                    };

                    $('#importProgressBar').css('width', '5%');
                    $('#importStatus').text('Enviando 0 de ' + productos.length + ' productos...');

                    function marcarLoteComoError(lote, motivo) {
                        // Si un lote entero falla (timeout, error 500, corte de
                        // conexión), no se aborta toda la importación: se
                        // registran esas filas como error y se sigue con el
                        // resto de los lotes.
                        acumulado.errores_count += lote.length;
                        lote.forEach(function(p){
                            acumulado.detalle.push({
                                codigo: p.codigo,
                                producto_nombre: '',
                                estado: 'Error',
                                precio_anterior: null,
                                precio_nuevo: p.precio,
                                detalle: motivo
                            });
                        });
                    }

                    function procesarLote() {
                        var inicio = loteActual * TAM_LOTE;
                        var lote = productos.slice(inicio, inicio + TAM_LOTE);

                        $.ajax({
                            url: 'listas_precios_productos_ajax.php',
                            type: 'POST',
                            data: {
                                accion: 'importar',
                                lista_precio_id: listaId,
                                productos: JSON.stringify(lote)
                            },
                            dataType: 'json'
                        }).done(function(response){
                            if (response && response.success) {
                                acumulado.procesados += response.procesados || 0;
                                acumulado.sin_cambios += response.sin_cambios || 0;
                                acumulado.no_encontrados_count += response.no_encontrados_count || 0;
                                acumulado.errores_count += response.errores_count || 0;
                                if (response.detalle && response.detalle.length > 0) {
                                    acumulado.detalle = acumulado.detalle.concat(response.detalle);
                                }
                            } else {
                                marcarLoteComoError(lote, (response && response.message) ? response.message : 'Error al procesar el lote');
                            }
                        }).fail(function(xhr, status, error){
                            marcarLoteComoError(lote, 'Error de comunicación con el servidor: ' + error);
                        }).always(function(){
                            loteActual++;
                            var procesadosHasta = Math.min(loteActual * TAM_LOTE, productos.length);
                            var pct = Math.round((procesadosHasta / productos.length) * 100);
                            $('#importProgressBar').css('width', pct + '%');
                            $('#importStatus').text(
                                'Procesando ' + procesadosHasta + ' de ' + productos.length + ' productos ' +
                                '(lote ' + loteActual + ' de ' + totalLotes + ')...'
                            );

                            if (loteActual < totalLotes) {
                                procesarLote();
                            } else {
                                finalizarImportacion();
                            }
                        });
                    }

                    function finalizarImportacion() {
                        $('#importProgressBar').css('width', '100%');
                        $('#importStatus').text('Finalizado');

                        // En vez de un mensaje de texto gigante, armamos un
                        // Excel de respuesta con el detalle fila por fila,
                        // combinando el detalle de TODOS los lotes.
                        if (acumulado.detalle.length > 0) {
                            try {
                                var filas = acumulado.detalle.map(function(d) {
                                    return {
                                        'Código': d.codigo,
                                        'Producto': d.producto_nombre || '',
                                        'Estado': d.estado,
                                        'Precio anterior': d.precio_anterior,
                                        'Precio nuevo': d.precio_nuevo,
                                        'Detalle': d.detalle
                                    };
                                });
                                var wsReporte = XLSX.utils.json_to_sheet(filas);
                                wsReporte['!cols'] = [
                                    {wch: 14}, {wch: 35}, {wch: 16}, {wch: 14}, {wch: 14}, {wch: 45}
                                ];
                                var wbReporte = XLSX.utils.book_new();
                                XLSX.utils.book_append_sheet(wbReporte, wsReporte, 'Resultado importación');
                                var nombreArchivo = 'resultado_importacion_' +
                                    Fmt.hoyISO() + '.xlsx';
                                XLSX.writeFile(wbReporte, nombreArchivo);
                            } catch (errExcel) {
                                console.error('Error generando el Excel de resultado:', errExcel);
                                Swal.fire('Atención', 'La importación se completó pero no se pudo generar el Excel de resultado: ' + errExcel.message, 'warning');
                            }
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'Importación completada',
                            html: 'Actualizados: <b>' + acumulado.procesados + '</b><br>' +
                                  'Sin cambios: <b>' + acumulado.sin_cambios + '</b><br>' +
                                  'No encontrados: <b>' + acumulado.no_encontrados_count + '</b><br>' +
                                  'Con error: <b>' + acumulado.errores_count + '</b><br>' +
                                  '<small>Se descargó un Excel con el detalle producto por producto.</small>'
                        });

                        // Recargar la tabla
                        tabla.ajax.reload();
                        $(document).trigger('listaPrecio:guardado');

                        // Ocultar barra después de un momento
                        setTimeout(() => {
                            $('#importProgress').hide();
                            $('#importProgressBar').css('width', '0%');
                        }, 2000);
                    }

                    procesarLote();

                } catch (error) {
                    $('#importProgress').hide();
                    Swal.fire('Error', 'Error al leer el archivo: ' + error.message, 'error');
                }
            };

            reader.readAsArrayBuffer(fileInput.files[0]);
        });

    });
    </script>

    <script src="<?= asset_local('js/chart.js-4.4.1/chart.umd.min.js') ?>"></script>
    <script>
    // ========== DASHBOARD (pestaña) ==========
    // VERSION 2026-09-30. Se carga recién la primera vez que se abre la
    // pestaña (no suma consultas a quien sólo usa la grilla) y se refresca
    // al cambiar la lista, con el botón Actualizar o al guardar/importar
    // precios (evento 'listaPrecio:guardado').
    $(document).ready(function(){
        const empresa_idx = window.EMPRESA_ID; // empresa activa (header1.php)
        const AJAX = 'listas_precios_productos_ajax.php';
        let dashCargado = false;
        let chartMeses = null, chartAntiguedad = null;
        let tablaDetalle = null;
        let totalListasActual = 0;

        const fmtNum = n => Fmt.numero((n || 0));
        const fmtMoneda = n => (n === null || n === undefined || n === '') ? '-' :
            '$ ' + Fmt.numero(parseFloat(n), { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const fmtPct = (parte, total) => total > 0 ? Fmt.numero((parte * 100 / total), { maximumFractionDigits: 1 }) + '%' : '-';
        const fmtFecha = f => f ? Fmt.fechaHora(f) : '-';
        const esc = t => $('<div>').text(t === null || t === undefined ? '' : String(t)).html();

        function etiquetaMes(mes) {
            if (mes === 'anteriores') return 'Anteriores';
            const [anio, m] = mes.split('-').map(Number);
            const txt = new Date(anio, m - 1, 1).toLocaleDateString('es-AR', { month: 'short', year: '2-digit' });
            return txt.charAt(0).toUpperCase() + txt.slice(1);
        }

        function etiquetaMesLarga(mes) {
            if (mes === 'anteriores') return 'anteriores a la ventana de 12 meses';
            const [anio, m] = mes.split('-').map(Number);
            return new Date(anio, m - 1, 1).toLocaleDateString('es-AR', { month: 'long', year: 'numeric' });
        }

        function colorTexto() {
            return getComputedStyle(document.body).color || '#6c757d';
        }

        function cargarListasDashboard() {
            $.get(AJAX, { accion: 'obtener_listas' }, function(res){
                let opts = '<option value="">Todas las listas</option>';
                (res || []).forEach(l => opts += `<option value="${l.lista_precio_id}">${esc(l.nombre)}</option>`);
                $('#dashLista').html(opts);
            });
        }

        function cargarDashboard() {
            $('#dashInfo').html('<i class="fas fa-spinner fa-spin me-1"></i> Calculando...');
            $.get(AJAX, { accion: 'dashboard', empresa_idx, lista_id: $('#dashLista').val() || 0 }, function(res){
                if (!res || !res.success) {
                    $('#dashInfo').text('');
                    Swal.fire('Error', (res && res.message) || 'No se pudo cargar el dashboard', 'error');
                    return;
                }
                dashCargado = true;
                renderKpis(res.data.kpis);
                renderChartMeses(res.data.meses);
                renderChartAntiguedad(res.data.antiguedad);
                renderPorLista(res.data.por_lista, res.data.kpis.productos_activos);
                $('#dashInfo').text('Actualizado ' + new Date().toLocaleTimeString('es-AR'));
            }).fail(function(xhr){
                $('#dashInfo').text('');
                const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error de comunicación con el servidor';
                Swal.fire('Error', msg, 'error');
            });
        }

        function renderKpis(k) {
            const conLista = !!$('#dashLista').val();
            totalListasActual = k.total_listas;

            $('#kpiSinPrecio').text(fmtNum(k.sin_precio));
            $('#kpiSinPrecioSub').text(fmtPct(k.sin_precio, k.productos_activos) + ' de ' + fmtNum(k.productos_activos) +
                ' productos' + (conLista ? ' (en esta lista)' : ' (en ninguna lista)'));

            // Con una sola lista, "parcial" no aplica (o tiene precio o no).
            if (conLista || k.total_listas <= 1) {
                $('#kpiParcial').text('-');
                $('#kpiParcialSub').text(conLista ? 'No aplica con una lista seleccionada' : 'Hay una sola lista activa');
                $('#kpiParcialCard').addClass('dash-kpi-disabled');
            } else {
                $('#kpiParcial').text(fmtNum(k.cobertura_parcial));
                $('#kpiParcialSub').text('Con precio en algunas de las ' + k.total_listas + ' listas');
                $('#kpiParcialCard').removeClass('dash-kpi-disabled');
            }

            const pctCob = k.productos_activos > 0 ? k.cobertura_completa * 100 / k.productos_activos : 0;
            $('#kpiCobertura').text(fmtPct(k.cobertura_completa, k.productos_activos));
            $('#kpiCoberturaBar').css('width', pctCob.toFixed(1) + '%');
            $('#kpiCoberturaSub').text(fmtNum(k.cobertura_completa) + ' de ' + fmtNum(k.productos_activos) +
                (conLista ? ' con precio en esta lista' : ' con precio en todas las listas'));

            $('#kpiPrecios').text(fmtNum(k.precios_activos));
            $('#kpiPreciosSub').text(k.margen_promedio !== null
                ? 'Margen promedio s/costo: ' + Fmt.numero(k.margen_promedio, { maximumFractionDigits: 1 }) + '%'
                : 'Sin costos cargados para calcular margen');

            $('#kpiAct30').text(fmtNum(k.actualizados_30));
            $('#kpiAct30Sub').text(fmtPct(k.actualizados_30, k.precios_activos) + ' de los precios activos');

            $('#kpiDesact').text(fmtNum(k.desactualizados));
            $('#kpiDesactSub').text(fmtPct(k.desactualizados, k.precios_activos) + ' de los precios activos');

            $('#kpiBajoCosto').text(fmtNum(k.bajo_costo));
            $('#kpiBajoCostoSub').html(k.precio_cero > 0
                ? `<a href="#" class="link-danger" id="lnkPrecioCero">${fmtNum(k.precio_cero)} con precio en $0</a>`
                : 'Ninguno con precio en $0');

            $('#kpiAntiguedad').text(k.antiguedad_promedio !== null ? fmtNum(k.antiguedad_promedio) + ' días' : '-');
            $('#kpiAntiguedadSub').text('Última actualización: ' + fmtFecha(k.ultima_actualizacion));
        }

        function opcionesBarras(onClickFn, horizontal) {
            const txt = colorTexto();
            const grid = 'rgba(128,128,128,0.15)';
            return {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: horizontal ? 'y' : 'x',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: { label: ctx => ' ' + fmtNum(ctx.parsed[horizontal ? 'x' : 'y']) + ' precios' }
                    }
                },
                scales: {
                    x: { grid: { display: horizontal, color: grid }, ticks: { color: txt, precision: 0 }, beginAtZero: true },
                    y: { grid: { display: !horizontal, color: grid }, ticks: { color: txt, precision: 0 }, beginAtZero: true }
                },
                onClick: (evt, elementos) => { if (elementos.length) onClickFn(elementos[0].index); },
                onHover: (evt, elementos) => { evt.native.target.style.cursor = elementos.length ? 'pointer' : 'default'; }
            };
        }

        function renderChartMeses(meses) {
            const ctx = document.getElementById('chartMeses');
            if (chartMeses) chartMeses.destroy();
            chartMeses = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: meses.map(m => etiquetaMes(m.mes)),
                    datasets: [{
                        data: meses.map(m => m.cantidad),
                        // "Anteriores" en gris: es un acumulado, no un mes comparable
                        backgroundColor: meses.map(m => m.mes === 'anteriores' ? '#9ca3af' : '#3b82f6'),
                        borderRadius: 4,
                        borderSkipped: 'start',
                        maxBarThickness: 48
                    }]
                },
                options: opcionesBarras(function(i){
                    const m = meses[i];
                    if (!m || !m.cantidad) return;
                    abrirDetalle('mes', m.mes, 'Precios actualizados en ' + etiquetaMesLarga(m.mes));
                }, false)
            });
        }

        function renderChartAntiguedad(buckets) {
            const ctx = document.getElementById('chartAntiguedad');
            if (chartAntiguedad) chartAntiguedad.destroy();
            // Secuencial de un solo tono: más oscuro = precio más viejo
            const rampa = ['#93c5fd', '#60a5fa', '#3b82f6', '#1d4ed8', '#1e3a8a'];
            chartAntiguedad = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: buckets.map(b => b.label),
                    datasets: [{
                        data: buckets.map(b => b.cantidad),
                        backgroundColor: buckets.map((b, i) => rampa[i] || rampa[rampa.length - 1]),
                        borderRadius: 4,
                        borderSkipped: 'start',
                        maxBarThickness: 36
                    }]
                },
                options: opcionesBarras(function(i){
                    const b = buckets[i];
                    if (!b || !b.cantidad) return;
                    abrirDetalle('antiguedad', b.bucket, 'Precios con antigüedad: ' + b.label.toLowerCase());
                }, true)
            });
        }

        function renderPorLista(listas, productosActivos) {
            const filtro = $('#dashLista').val();
            let html = '';
            (listas || []).forEach(l => {
                const con = parseInt(l.con_precio) || 0;
                const sin = Math.max(0, productosActivos - con);
                const pct = productosActivos > 0 ? con * 100 / productosActivos : 0;
                const color = pct >= 90 ? 'bg-success' : (pct >= 60 ? 'bg-warning' : 'bg-danger');
                const resaltada = filtro && String(filtro) === String(l.lista_precio_id) ? 'table-active' : '';
                html += `<tr class="${resaltada}" data-lista-id="${l.lista_precio_id}" data-lista-nombre="${esc(l.lista_nombre)}">
                    <td>${esc(l.lista_nombre)}</td>
                    <td class="text-end">${fmtNum(con)}</td>
                    <td class="text-end">${sin > 0 ? '<span class="text-danger fw-semibold">' + fmtNum(sin) + '</span>' : '0'}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height: 8px;">
                                <div class="progress-bar ${color}" style="width: ${pct.toFixed(1)}%;"></div>
                            </div>
                            <small class="text-muted" style="min-width: 48px;">${fmtPct(con, productosActivos)}</small>
                        </div>
                    </td>
                    <td>${fmtFecha(l.ultima_actualizacion)}</td>
                    <td class="text-center">
                        ${sin > 0 ? '<button class="btn btn-outline-danger btn-sm btnDashFaltantes" title="Ver productos sin precio en esta lista"><i class="fas fa-search"></i></button>' : '<i class="fas fa-check text-success" title="Todos los productos tienen precio"></i>'}
                    </td>
                </tr>`;
            });
            if (!html) html = '<tr><td colspan="6" class="text-center text-muted py-3">No hay listas de precios activas</td></tr>';
            $('#tablaDashListas tbody').html(html);
        }

        // ---------- Modal de detalle ----------
        function abrirDetalle(tipo, valor, titulo, listaIdForzada) {
            const listaId = listaIdForzada || $('#dashLista').val() || 0;
            // Si la lista viene forzada (fila de cobertura) el título ya la nombra
            const nombreLista = (listaId && !listaIdForzada) ? ($('#dashLista option[value="' + listaId + '"]').text() || '') : '';
            $('#dashDetalleTitulo').text(titulo + (nombreLista ? ' — ' + nombreLista : ''));
            $('#dashDetalleAviso').hide();

            Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            $.get(AJAX, { accion: 'dashboard_detalle', empresa_idx, tipo, valor: valor || '', lista_id: listaId }, function(res){
                Swal.close();
                if (!res || !res.success) {
                    Swal.fire('Error', (res && res.message) || 'No se pudo obtener el detalle', 'error');
                    return;
                }
                if (res.data.truncado) {
                    $('#dashDetalleAviso').show().text('Se muestran sólo los primeros ' + fmtNum(res.data.limite) +
                        ' registros. Usá el filtro por lista para acotar el resultado.');
                }
                renderTablaDetalle(res.data, listaId, titulo);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDashDetalle')).show();
            }).fail(function(xhr){
                Swal.close();
                const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error de comunicación con el servidor';
                Swal.fire('Error', msg, 'error');
            });
        }

        function renderTablaDetalle(data, listaId, titulo) {
            if (tablaDetalle) {
                tablaDetalle.destroy();
                tablaDetalle = null;
            }
            $('#tablaDashDetalle').empty();

            const texto = $.fn.dataTable.render.text();
            let columnas, orden;
            if (data.tipo_fila === 'producto') {
                const totalListas = listaId ? 1 : totalListasActual;
                columnas = [
                    { title: 'Código', data: 'producto_codigo', render: texto },
                    { title: 'Producto', data: 'producto_nombre', render: texto },
                    { title: 'Costo actual', data: 'costo_actual', className: 'text-end', render: (d, t) => t === 'display' ? fmtMoneda(d) : d },
                    { title: 'Listas con precio', data: 'listas_con_precio', className: 'text-center',
                      render: (d, t) => t === 'display' ? d + ' de ' + totalListas : d },
                    { title: '', data: null, orderable: false, className: 'text-center',
                      render: () => '<button class="btn btn-success btn-sm btnDashCargarPrecio" data-funcion="agregar" title="Cargar precio"><i class="fas fa-plus"></i></button>' }
                ];
                orden = [[0, 'asc']];
            } else {
                columnas = [
                    { title: 'Lista', data: 'lista_nombre', render: texto },
                    { title: 'Código', data: 'producto_codigo', render: texto },
                    { title: 'Producto', data: 'producto_nombre', render: texto },
                    { title: 'Precio', data: 'precio_unitario', className: 'text-end', render: (d, t) => t === 'display' ? fmtMoneda(d) : parseFloat(d) },
                    { title: 'Costo', data: 'costo_actual', className: 'text-end', render: (d, t) => t === 'display' ? fmtMoneda(d) : (d === null ? -1 : parseFloat(d)) },
                    { title: 'Margen', data: 'margen_pct', className: 'text-end',
                      render: (d, t) => {
                          if (t !== 'display') return d === null ? -99999 : parseFloat(d);
                          if (d === null) return '-';
                          const v = parseFloat(d);
                          return `<span class="${v < 0 ? 'text-danger fw-semibold' : ''}">${Fmt.numero(v, { maximumFractionDigits: 1 })}%</span>`;
                      } },
                    { title: 'Últ. actualización', data: 'f_actualizacion', render: (d, t) => t === 'display' ? fmtFecha(d) : d },
                    { title: 'Días', data: 'dias', className: 'text-end' },
                    { title: '', data: null, orderable: false, className: 'text-center',
                      render: () => '<button class="btn btn-primary btn-sm btnDashEditarPrecio" data-funcion="editar" title="Editar precio"><i class="fa fa-edit"></i></button>' }
                ];
                orden = [[7, 'desc']];
            }

            const columnasExport = ':not(:last-child)';
            tablaDetalle = $('#tablaDashDetalle').DataTable({
                data: data.filas,
                columns: columnas,
                order: orden,
                pageLength: 10,
                dom: '<"row mb-2"<"col-12 col-md-6"B><"col-12 col-md-6 text-end"f>>rt<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>',
                buttons: [
                    { extend: 'excelHtml5', text: '<i class="fas fa-file-excel"></i> Excel', className: 'btn btn-success btn-sm',
                      title: titulo, exportOptions: { columns: columnasExport } }
                ],
                language: { url: '<?= asset_local('js/datatables-i18n-1.13.4/es-ES.json') ?>' }
            });
            $('#tablaDashDetalle').data('lista-contexto', listaId);
        }

        // Abre el modal de alta/edición de la pestaña Precios, después de
        // cerrar el de detalle (Bootstrap no maneja bien dos modales abiertos).
        function cerrarDetalleY(fn) {
            const el = document.getElementById('modalDashDetalle');
            $(el).one('hidden.bs.modal', fn);
            bootstrap.Modal.getOrCreateInstance(el).hide();
        }

        $('#tablaDashDetalle').on('click', '.btnDashCargarPrecio', function(){
            const fila = tablaDetalle.row($(this).closest('tr')).data();
            const listaId = $('#tablaDashDetalle').data('lista-contexto') || '';
            cerrarDetalleY(function(){
                $('#formListaPrecioProducto')[0].reset();
                $('#formListaPrecioProducto').removeClass('was-validated');
                $('#lista_precio_producto_id').val('');
                $('#producto_id').val(fila.producto_id);
                if (listaId) $('#lista_precio_id').val(listaId);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalListaPrecioProducto')).show();
            });
        });

        $('#tablaDashDetalle').on('click', '.btnDashEditarPrecio', function(){
            const fila = tablaDetalle.row($(this).closest('tr')).data();
            $.get(AJAX, { accion: 'obtener', lista_precio_producto_id: fila.lista_precio_producto_id }, function(res){
                if (!res) return Swal.fire('Error', 'Error al obtener datos', 'error');
                cerrarDetalleY(function(){
                    $('#formListaPrecioProducto').removeClass('was-validated');
                    $('#lista_precio_producto_id').val(res.lista_precio_producto_id);
                    $('#lista_precio_id').val(res.lista_precio_id);
                    $('#producto_id').val(res.producto_id);
                    $('#precio_unitario').val(parseFloat(res.precio_unitario).toFixed(4));
                    $('#ajuste_id').val(res.ajuste_id);
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalListaPrecioProducto')).show();
                });
            });
        });

        // ---------- Eventos ----------
        $('#tabDashboardBtn').on('shown.bs.tab', function(){
            if (!dashCargado) cargarDashboard();
        });
        $('#dashLista').on('change', cargarDashboard);
        $('#btnDashActualizar').on('click', cargarDashboard);
        $(document).on('listaPrecio:guardado', function(){ if (dashCargado) cargarDashboard(); });

        $('#dashKpis').on('click', '.dash-kpi-click', function(e){
            if ($(e.target).closest('#lnkPrecioCero').length) return; // lo maneja su propio handler
            if ($(this).hasClass('dash-kpi-disabled') || !dashCargado) return;
            const tipo = $(this).data('tipo');
            const titulos = {
                sin_precio: 'Productos sin precio',
                parcial: 'Productos con cobertura parcial',
                actualizados_30: 'Precios actualizados en los últimos 30 días',
                desactualizados: 'Precios sin actualizar hace más de 90 días',
                bajo_costo: 'Precios por debajo del costo actual'
            };
            abrirDetalle(tipo, '', titulos[tipo] || 'Detalle');
        });

        $('#dashKpis').on('click', '#lnkPrecioCero', function(e){
            e.preventDefault();
            abrirDetalle('precio_cero', '', 'Precios en $0');
        });

        $('#tablaDashListas').on('click', 'tbody tr[data-lista-id]', function(){
            const id = $(this).data('lista-id');
            const nombre = $(this).data('lista-nombre');
            if ($(this).find('.btnDashFaltantes').length === 0) return; // lista completa, nada que mostrar
            // Se fuerza la lista de la fila, no la del filtro de arriba
            abrirDetalle('sin_precio', '', 'Productos sin precio en ' + nombre, id);
        });

        cargarListasDashboard();
    });
    </script>

    <script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
     <script src="<?= asset_local('js/xlsx-0.18.5/xlsx.full.min.js') ?>"></script>
     <!-- Plugin Responsive de DataTables: no asumo que ya esté cargado en el
          template compartido (no tengo header1.php/footer para confirmarlo),
          así que lo incluyo acá para que "responsive: true" funcione seguro. -->
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
