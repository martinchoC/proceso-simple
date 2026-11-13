<?php
// Configuración de la página
$pageTitle = "Empresas - Configuración";
$currentPage = 'empresas';
$modudo_idx = 1;
$pagina_idx = 27; // conf__paginas
$ui_simple = true;

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
}
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

    <!-- Header de la Aplicación -->
    <?php ui_encabezado('Empresas', 'bi bi-building', 'Empresas del sistema (cada una es un cliente): datos fiscales, formato de fechas e importes y parámetros generales.', ['Empresas y accesos']); ?>

    <div class="app-content">
        <div class="container-fluid">

            <!-- Tarjetas de Resumen / Métricas Rápidas -->
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="card shadow-sm border-0 border-start border-4 border-primary h-100">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold d-block">Total Empresas</span>
                                <span class="fs-4 fw-bold text-dark" id="statTotalEmpresas">-</span>
                            </div>
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                                <i class="fas fa-building fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card shadow-sm border-0 border-start border-4 border-success h-100">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold d-block">Empresas Activas</span>
                                <span class="fs-4 fw-bold text-success" id="statEmpresasActivas">-</span>
                            </div>
                            <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                                <i class="fas fa-check-circle fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card shadow-sm border-0 border-start border-4 border-info h-100">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold d-block">Con Condición Fiscal</span>
                                <span class="fs-4 fw-bold text-info" id="statConCondicion">-</span>
                            </div>
                            <div class="rounded-circle bg-info bg-opacity-10 p-3 text-info">
                                <i class="fas fa-file-invoice-dollar fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card shadow-sm border-0 border-start border-4 border-warning h-100">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold d-block">Parámetros y Vigencias</span>
                                <a href="../gestion/empresas_parametros.php" class="btn btn-sm btn-outline-warning mt-1 fw-bold">
                                    <i class="fas fa-sliders-h me-1"></i>Ir a Parámetros
                                </a>
                            </div>
                            <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning">
                                <i class="fas fa-history fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Principal con DataTable -->
            <div class="card card-outline card-primary shadow-sm mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title fw-bold mb-0 text-dark">
                        <i class="fas fa-list me-2 text-primary"></i>Catálogo de Empresas Emisoras
                    </h5>
                    <div class="card-tools">
                        <a href="../gestion/empresas_parametros.php" class="btn btn-sm btn-outline-secondary me-2">
                            <i class="fas fa-sliders-h me-1"></i>Historial de Parámetros
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-primary shadow-sm me-1" id="btnNuevoConstancia" title="Nueva empresa con los datos de la constancia de inscripción de ARCA, leída con IA">
                            <i class="fas fa-robot me-1"></i>Desde constancia de CUIT
                        </button>
                        <button type="button" class="btn btn-sm btn-primary shadow-sm" id="btnNuevo">
                            <i class="fas fa-plus-circle me-1"></i>Nueva Empresa
                        </button>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="tablaempresas" class="table table-hover table-striped align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th>Razón Social</th>
                                    <th>Condición Fiscal</th>
                                    <th>Documento / CUIT</th>
                                    <th>Nro. IIBB</th>
                                    <th>Domicilio y Localidad</th>
                                    <th>Datos Fiscales Adicionales</th>
                                    <th style="width: 90px;">Estado</th>
                                    <th style="width: 120px;" class="text-center">Acciones</th>
                                </tr>
                                <tr class="filters">
                                    <th></th>
                                    <th><input type="text" class="form-control form-control-sm" placeholder="Filtrar Razón Social" /></th>
                                    <th><input type="text" class="form-control form-control-sm" placeholder="Filtrar Condición" /></th>
                                    <th><input type="text" class="form-control form-control-sm" placeholder="Filtrar CUIT" /></th>
                                    <th><input type="text" class="form-control form-control-sm" placeholder="Filtrar IIBB" /></th>
                                    <th><input type="text" class="form-control form-control-sm" placeholder="Filtrar Domicilio/Loc." /></th>
                                    <th><input type="text" class="form-control form-control-sm" placeholder="Filtrar Leyenda" /></th>
                                    <th><input type="text" class="form-control form-control-sm" placeholder="Filtrar Estado" /></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

<!-- Modal Empresa (Formulario Completo y Organizado) -->
<div class="modal fade" id="modalempresa" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold" id="modalLabel">
                    <i class="fas fa-building me-2"></i>Empresa
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formempresa" novalidate>
                    <input type="hidden" id="empresa_id" name="empresa_id" />

                    <!-- Alta desde la constancia de inscripción de ARCA (solo empresa nueva) -->
                    <div id="bloqueConstancia" class="d-flex align-items-center gap-2 mb-3 p-2 border rounded bg-light">
                        <i class="fas fa-file-pdf text-danger fs-5"></i>
                        <span class="small flex-grow-1">¿Tenés la constancia de CUIT de ARCA? Subila y se completan los datos.
                            <?= ui_ayuda('Se lee con la IA de la empresa (Configuración > Empresas: su clave o la de la principal). Acepta PDF, también escaneado, o una foto de hasta 5 MB. No graba nada: revisá los datos y guardá.') ?></span>
                        <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" id="btnLeerConstancia">
                            <i class="fas fa-robot me-1"></i>Leer constancia
                        </button>
                    </div>

                    <!-- Pestañas de Navegación del Modal -->
                    <ul class="nav nav-pills nav-fill mb-3 p-1 bg-light rounded" id="empresaTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold py-2" id="tab-generales-tab" data-bs-toggle="pill" data-bs-target="#tab-generales" type="button" role="tab">
                                <i class="fas fa-id-card me-2"></i>1. Datos Generales
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold py-2" id="tab-fiscales-tab" data-bs-toggle="pill" data-bs-target="#tab-fiscales" type="button" role="tab">
                                <i class="fas fa-file-invoice-dollar me-2 text-primary"></i>2. Datos Fiscales Principales
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="empresaTabsContent">
                        
                        <!-- TAB 1: DATOS GENERALES -->
                        <div class="tab-pane fade show active" id="tab-generales" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label fw-bold" for="empresa">
                                        Razón Social / Nombre Comercial <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-building text-muted"></i></span>
                                        <input type="text" class="form-control" id="empresa" name="empresa" required placeholder="Ej: Casa Lucho S.A." />
                                    </div>
                                    <div class="invalid-feedback">Por favor ingrese la Razón Social.</div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-bold" for="documento_tipo_id">Tipo Documento</label>
                                    <select class="form-select" id="documento_tipo_id" name="documento_tipo_id">
                                        <!-- Opciones cargadas por JS -->
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fw-bold" for="documento_numero">
                                        Número de CUIT / Documento <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-hashtag text-muted"></i></span>
                                        <input type="text" class="form-control font-monospace" id="documento_numero" name="documento_numero" required placeholder="Ej: 30541512590" />
                                    </div>
                                    <div class="invalid-feedback">Por favor ingrese el número de CUIT.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold" for="telefono">Teléfono / Contacto</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone text-muted"></i></span>
                                        <input type="text" class="form-control" id="telefono" name="telefono" placeholder="Ej: 0291-4556677" />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold" for="email">Correo Electrónico (Email)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" placeholder="contacto@empresa.com" />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold" for="tipo_operacion">Tipo de Operación</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-briefcase text-muted"></i></span>
                                        <select class="form-select" id="tipo_operacion" name="tipo_operacion">
                                            <option value="MIXTA">Bienes y servicios</option>
                                            <option value="BIENES">Venta de bienes</option>
                                            <option value="SERVICIOS">Servicios</option>
                                        </select>
                                    </div>
                                    <small class="text-muted">Define los perfiles predefinidos: en Servicios no se ofrecen pedidos, remitos ni stock.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold" for="nivel_gestion">Nivel de Gestión</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-sliders-h text-muted"></i></span>
                                        <select class="form-select" id="nivel_gestion" name="nivel_gestion">
                                            <option value="COMPLETA">Gestión completa</option>
                                            <option value="SIMPLIFICADA">Gestión simplificada (cliente de estudio contable)</option>
                                        </select>
                                    </div>
                                    <small class="text-muted">En la simplificada los perfiles nuevos no incluyen las páginas marcadas como "solo gestión completa". No modifica perfiles ya creados.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold" for="formato_fecha">Formato de fechas y números <?= ui_ayuda('Cómo se muestran las fechas y los importes en todas las pantallas y tablas de la empresa. Los datos se guardan siempre igual: cambiarlo no modifica nada cargado.') ?></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-calendar-alt text-muted"></i></span>
                                        <select class="form-select" id="formato_fecha" name="formato_fecha" title="Formato de fecha">
                                            <?php foreach (FORMATOS_FECHA as $fmt_cod => $fmt_txt): ?>
                                                <option value="<?= htmlspecialchars($fmt_cod) ?>"><?= htmlspecialchars($fmt_txt) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select class="form-select" id="formato_numero" name="formato_numero" title="Separador de miles y decimales">
                                            <option value="PUNTO|COMA">1.234,56</option>
                                            <option value="COMA|PUNTO">1,234.56</option>
                                            <option value="ESPACIO|COMA">1 234,56</option>
                                            <option value="ESPACIO|PUNTO">1 234.56</option>
                                            <option value="NINGUNO|COMA">1234,56</option>
                                            <option value="NINGUNO|PUNTO">1234.56</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold" for="decimales_stock">Decimales en cantidades de stock <?= ui_ayuda('Cuántos decimales se muestran y se cargan en las cantidades de stock (ajustes de stock y las pantallas que lo usen). Para productos que se venden por unidad conviene 0 o 2; para kilos o metros, 2 o 3.') ?></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-boxes text-muted"></i></span>
                                        <select class="form-select" id="decimales_stock" name="decimales_stock">
                                            <?php for ($dec_i = 0; $dec_i <= DECIMALES_STOCK_MAX; $dec_i++): ?>
                                                <option value="<?= $dec_i ?>"<?= $dec_i === FORMATO_DEFAULT['stock'] ? ' selected' : '' ?>><?= $dec_i ?> (<?= htmlspecialchars(number_format(12, $dec_i, ',', '.')) ?>)</option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold d-block">Productos <?= ui_ayuda('Con presentaciones, cada producto puede tener además de la unidad sus formas de empaque (caja, pack, pallet) con cuántas unidades contiene y su propio código de barras. Útil en distribuidoras y mayoristas.') ?></label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" id="usa_presentaciones" name="usa_presentaciones" value="1" />
                                        <label class="form-check-label" for="usa_presentaciones">Usa presentaciones (caja, pack, pallet)</label>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold" for="domicilio">Domicilio Fiscal / Comercial</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-map-marker-alt text-muted"></i></span>
                                        <input type="text" class="form-control" id="domicilio" name="domicilio" placeholder="Calle, número, piso, depto." />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold" for="provincia_id">
                                        Provincia <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="provincia_id" name="provincia_id">
                                        <option value="">Cargando provincias...</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold" for="localidad_id">
                                        Localidad <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="localidad_id" name="localidad_id" required>
                                        <option value="">Elegí primero la provincia</option>
                                    </select>
                                    <div class="invalid-feedback">Por favor seleccione la localidad.</div>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label fw-bold" for="logo">Logo de la empresa
                                        <?= ui_ayuda('PNG, JPG, GIF o WEBP, hasta 1 MB. Sale en los comprobantes impresos y de sus colores se arma el encabezado de las ventanas de la empresa. Es el mismo logo de Parámetros de Empresa: cambiarlo en una de las dos páginas lo cambia en la otra.') ?></label>
                                    <div class="d-flex align-items-center gap-3 flex-wrap">
                                        <div class="border rounded d-flex align-items-center justify-content-center bg-white" style="width:180px;height:70px;">
                                            <img id="logoPreview" src="" alt="Logo" style="max-width:170px;max-height:62px;object-fit:contain;" class="d-none">
                                            <small id="logoSinImagen" class="text-muted">Sin logo</small>
                                        </div>
                                        <div class="flex-grow-1" style="min-width:220px;">
                                            <input type="file" class="form-control" id="logo" name="logo" accept="image/png,image/jpeg,image/gif,image/webp">
                                            <input type="hidden" id="quitar_logo" name="quitar_logo" value="0">
                                            <div class="d-flex align-items-center gap-2 mt-1">
                                                <button type="button" class="btn btn-sm btn-outline-danger" id="btnQuitarLogo"><i class="fas fa-times me-1"></i>Quitar logo</button>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Colores del encabezado (core/empresa_logo.php): IA / calculados / a mano -->
                                    <div id="logoColores" class="d-none mt-2 p-2 border rounded bg-light">
                                        <div class="d-flex align-items-center gap-2 flex-wrap small">
                                            <span class="fw-bold">Colores del encabezado
                                                <?= ui_ayuda('Fondo de la barra superior, el menú lateral y las ventanas de la empresa, con texto blanco. Al cambiar el logo los elige la IA de la empresa (o se calculan del logo si no hay IA). Podés ajustarlos acá: si un color no deja leer el texto blanco, se oscurece al guardar.') ?></span>
                                            <span id="logoMuestraColores" class="d-inline-flex align-items-center px-3 rounded text-white fw-semibold" style="height:26px;min-width:130px;">Encabezado</span>
                                            <label class="d-inline-flex align-items-center gap-1 mb-0" for="color_logo_oscuro">Oscuro
                                                <input type="color" class="form-control form-control-color form-control-sm" id="color_logo_oscuro" title="Color oscuro (inicio del degradé)"></label>
                                            <label class="d-inline-flex align-items-center gap-1 mb-0" for="color_logo_claro">Claro
                                                <input type="color" class="form-control form-control-color form-control-sm" id="color_logo_claro" title="Color claro (fin del degradé)"></label>
                                            <span id="logoColoresOrigen" class="badge text-bg-secondary"></span>
                                            <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="btnRecalcularColores" title="Volver a elegirlos a partir del logo (con la IA, si la empresa la tiene)">
                                                <i class="fas fa-magic me-1"></i>Recalcular</button>
                                            <input type="hidden" id="colores_manual" value="0">
                                        </div>
                                    </div>
                                    <div id="logoColoresNuevo" class="d-none small text-muted mt-1"><i class="fas fa-info-circle me-1"></i>Los colores del encabezado se eligen al guardar, a partir del logo nuevo.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold" for="estado_registro_id">Estado del Registro</label>
                                    <select class="form-select" id="estado_registro_id" name="estado_registro_id">
                                        <option value="1">1 - Activo</option>
                                        <option value="2">2 - Inactivo / Inhabilitado</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold" for="base_conf">Identificador de Configuración</label>
                                    <input type="number" class="form-control" id="base_conf" name="base_conf" placeholder="Opcional" />
                                </div>
                                <div class="col-md-12">
                                    <div class="form-check form-switch p-3 bg-light rounded border">
                                        <input class="form-check-input ms-0 me-2" type="checkbox" id="es_principal" name="es_principal" value="1">
                                        <label class="form-check-label fw-bold text-dark" for="es_principal">
                                            <i class="fas fa-star text-warning me-1"></i>Empresa Principal del Sistema
                                        </label>
                                        <div class="text-muted small mt-1">
                                            El logo de esta empresa se utilizará como logo inicial en la pantalla de inicio de sesión y en la barra superior cuando no haya otra empresa seleccionada. Solo una empresa puede ser la principal.
                                        </div>
                                    </div>
                                </div>

                                <!-- Uso de la IA en todos los módulos (core/ia_empresa.php) -->
                                <div class="col-md-12">
                                    <div class="p-3 bg-light rounded border">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="ia_habilitada" name="ia_habilitada" value="1" checked>
                                            <label class="form-check-label fw-bold text-dark" for="ia_habilitada">
                                                <i class="fas fa-robot text-primary me-1"></i>Usar inteligencia artificial
                                                <?= ui_ayuda('Vale para todos los módulos de la empresa: lectura de facturas y de extractos bancarios, Asistente IA, alta desde la constancia de CUIT y colores del logo. Los archivos y textos se envían al servicio de IA (Google Gemini o Anthropic Claude). Deshabilitada, nada sale del servidor. Los topes diarios siguen en Parámetros de Empresa.') ?>
                                            </label>
                                        </div>
                                        <div id="iaClaveBox" class="row g-2 mt-1">
                                            <div class="col-md-8">
                                                <label for="ia_api_key" class="form-label small mb-1">Clave de la API (Gemini / Claude)
                                                    <?= ui_ayuda('Clave de Google Gemini (aistudio.google.com > Get API key, empieza con AIza...) o de Anthropic Claude (empieza con sk-ant-). Se guarda cifrada y no se vuelve a mostrar. Si la empresa no tiene clave propia, usa la de la empresa principal.') ?></label>
                                                <input type="password" class="form-control form-control-sm" id="ia_api_key" autocomplete="new-password" spellcheck="false" placeholder="AIzaSy... (Gemini) o sk-ant-... (Claude)">
                                                <div class="form-text" id="ia_api_key_estado"></div>
                                            </div>
                                            <div class="col-md-4 d-none align-self-center" id="ia_api_key_quitar_box">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="ia_api_key_quitar" value="1">
                                                    <label class="form-check-label small" for="ia_api_key_quitar">Quitar la clave propia</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: DATOS FISCALES PRINCIPALES -->
                        <div class="tab-pane fade" id="tab-fiscales" role="tabpanel">
                            
                            <div class="alert alert-light border border-primary border-opacity-25 rounded-3 p-3 mb-3">
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-info-circle text-primary fs-4 me-3 mt-1"></i>
                                    <div>
                                        <h6 class="fw-bold mb-1 text-primary">Configuración Fiscal de Emisión</h6>
                                        <p class="small text-muted mb-0">
                                            Estos datos son utilizados por el motor de facturación fiscal para emitir comprobantes electrónicos y definir relaciones fiscales. Los cambios registrados aquí se sincronizan automáticamente con el historial de <a href="../gestion/empresas_parametros.php" target="_blank" class="fw-bold text-decoration-none">Parámetros de Empresa</a>.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="card border border-light-subtle shadow-sm mb-3">
                                <div class="card-body p-3">
                                    <div class="row g-3">
                                        
                                        <!-- Condición Fiscal -->
                                        <div class="col-md-12">
                                            <label class="form-label fw-bold" for="condicion_fiscal_id">
                                                <i class="fas fa-certificate text-warning me-1"></i>Condición Fiscal Emisora (Nivel Nacional - IVA)
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-white"><i class="fas fa-landmark text-primary"></i></span>
                                                <select class="form-select form-select-lg fw-bold text-primary" id="condicion_fiscal_id" name="condicion_fiscal_id">
                                                    <option value="">Seleccionar condición fiscal...</option>
                                                </select>
                                            </div>
                                            <small class="text-muted d-block mt-1">
                                                Determina el tipo de comprobantes que la empresa puede emitir (Facturas A, B, C, etc.)
                                            </small>
                                        </div>

                                        <!-- Régimen de IIBB -->
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold" for="iibb_regimen_id">
                                                <i class="fas fa-map-pin text-success me-1"></i>Régimen de IIBB (Nivel Provincial)
                                            </label>
                                            <select class="form-select" id="iibb_regimen_id" name="iibb_regimen_id">
                                                <option value="">Seleccionar régimen de IIBB...</option>
                                            </select>
                                            <div id="help_conf_iibb_regimen" class="text-muted small mt-1"></div>
                                        </div>

                                        <!-- Sede Principal / Jurisdicción Sede -->
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold" for="jurisdiccion_sede_id">
                                                <i class="fas fa-map-marked-alt text-primary me-1"></i>Sede Principal / Jurisdicción (COMARB)
                                            </label>
                                            <select class="form-select" id="jurisdiccion_sede_id" name="jurisdiccion_sede_id">
                                                <option value="">Seleccionar jurisdicción sede...</option>
                                            </select>
                                            <small class="text-muted">Código ARCA / COMARB (ej. 901 CABA, 902 Bs. As.)</small>
                                        </div>

                                        <!-- Nro. IIBB -->
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold" for="ingresos_brutos_nro">
                                                <i class="fas fa-file-invoice text-primary me-1"></i>Nro. Ingresos Brutos (IIBB)
                                            </label>
                                            <div class="input-group">
                                                <input type="text" class="form-control font-monospace" id="ingresos_brutos_nro" name="ingresos_brutos_nro" placeholder="Ej: 30541512590" />
                                                <button class="btn btn-outline-secondary btn-sm" type="button" id="btnCopiarCuitAIibb" title="Copiar mismo valor del CUIT">
                                                    <i class="fas fa-copy me-1"></i>Copiar CUIT
                                                </button>
                                            </div>
                                            <small class="text-muted">Número local provincial o número de CUIT / Convenio.</small>
                                        </div>

                                        <!-- Inicio Actividades -->
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold" for="inicio_actividades">
                                                <i class="far fa-calendar-alt text-success me-1"></i>Inicio de Actividades
                                            </label>
                                            <input type="date" class="form-control" id="inicio_actividades" name="inicio_actividades" />
                                            <small class="text-muted">Fecha de inicio registrada ante AFIP/ARCA.</small>
                                        </div>

                                        <!-- Leyenda Fiscal -->
                                        <div class="col-md-12">
                                            <label class="form-label fw-bold" for="leyenda_fiscal">
                                                <i class="fas fa-heading text-secondary me-1"></i>Leyenda Fiscal
                                            </label>
                                            <input type="text" class="form-control" id="leyenda_fiscal" name="leyenda_fiscal" placeholder="Ej: IVA Responsable Inscripto" />
                                            <small class="text-muted">Texto impreso en el encabezado de comprobantes oficiales.</small>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <div class="text-muted small">
                                <i class="fas fa-history text-secondary me-1"></i>
                                Para gestionar puntos de venta por defecto, listas de precio o períodos históricos, utilice la sección <a href="../gestion/empresas_parametros.php" target="_blank" class="fw-bold">Parámetros Generales de Empresa</a>.
                            </div>

                        </div>

                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancelar
                </button>
                <button type="button" id="btnGuardar" class="btn btn-primary px-4 fw-bold">
                    <i class="fas fa-save me-1"></i>Guardar Empresa
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Funciones de carga de catálogos
function cargarProvincias(selectedId = null, callback = null) {
    $.ajax({
        url: 'empresas_ajax.php',
        type: 'GET',
        data: {accion: 'obtener_provincias'},
        dataType: 'json',
        success: function(res) {
            var options = '<option value="">Seleccionar provincia...</option>';
            $.each(res || [], function(index, provincia) {
                var selected = (selectedId == provincia.provincia_id) ? 'selected' : '';
                options += `<option value="${provincia.provincia_id}" ${selected}>${provincia.provincia}</option>`;
            });
            $('#provincia_id').html(options);
            if (typeof callback === 'function') callback();
        },
        error: function() {
            $('#provincia_id').html('<option value="">Error al cargar provincias</option>');
        }
    });
}

// Las localidades se piden por provincia (el catálogo completo tiene ~23.000).
function cargarLocalidades(provinciaId = null, selectedId = null, callback = null) {
    if (!provinciaId) {
        $('#localidad_id').html('<option value="">Elegí primero la provincia</option>');
        return;
    }
    $('#localidad_id').html('<option value="">Cargando localidades...</option>');
    $.ajax({
        url: 'empresas_ajax.php',
        type: 'GET',
        data: {accion: 'obtener_localidades', provincia_id: provinciaId},
        dataType: 'json',
        success: function(res) {
            var options = '<option value="">Seleccionar localidad...</option>';
            $.each(res || [], function(index, localidad) {
                var selected = (selectedId == localidad.localidad_id) ? 'selected' : '';
                var cp = localidad.cp ? ` (${localidad.cp})` : '';
                options += `<option value="${localidad.localidad_id}" ${selected}>${localidad.localidad}${cp}</option>`;
            });
            $('#localidad_id').html(options);
            if (typeof callback === 'function') callback();
        },
        error: function() {
            $('#localidad_id').html('<option value="">Error al cargar localidades</option>');
        }
    });
}

function cargarDocumentosTipos(selectedId = null, callback = null) {
    $.ajax({
        url: 'empresas_ajax.php',
        type: 'GET',
        data: {accion: 'obtener_documentos_tipos'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '';
                $.each(res, function(index, dt) {
                    var selected = (selectedId == dt.documento_tipo_id) ? 'selected' : '';
                    options += `<option value="${dt.documento_tipo_id}" ${selected}>${dt.documento_tipo}</option>`;
                });
                $('#documento_tipo_id').html(options);
                if (typeof callback === 'function') callback();
            }
        }
    });
}

function cargarCondicionesFiscales(selectedId = null, callback = null) {
    $.ajax({
        url: 'empresas_ajax.php',
        type: 'GET',
        data: {accion: 'obtener_condiciones_fiscales'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar condición fiscal...</option>';
                $.each(res, function(index, cf) {
                    var selected = (selectedId == cf.condicion_fiscal_id) ? 'selected' : '';
                    var codigo = cf.condicion_fiscal_codigo ? ' (' + cf.condicion_fiscal_codigo + ')' : '';
                    options += `<option value="${cf.condicion_fiscal_id}" ${selected}>${cf.condicion_fiscal}${codigo}</option>`;
                });
                $('#condicion_fiscal_id').html(options);
                if (typeof callback === 'function') callback();
            }
        },
        error: function() {
            $('#condicion_fiscal_id').html('<option value="">Error al cargar condiciones fiscales</option>');
        }
    });
}

function cargarIibbRegimenes(selectedId = null, callback = null) {
    $.ajax({
        url: 'empresas_ajax.php',
        type: 'GET',
        data: {accion: 'obtener_iibb_regimenes'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar régimen de IIBB...</option>';
                $.each(res, function(index, reg) {
                    var selected = (selectedId == reg.iibb_regimen_id) ? 'selected' : '';
                    options += `<option value="${reg.iibb_regimen_id}" data-desc="${reg.descripcion || ''}" ${selected}>${reg.iibb_regimen_nombre}</option>`;
                });
                $('#iibb_regimen_id').html(options);
                actualizarConfDescripcionRegimen();
                if (typeof callback === 'function') callback();
            }
        }
    });
}

function cargarJurisdiccionesSedes(selectedId = null, callback = null) {
    $.ajax({
        url: 'empresas_ajax.php',
        type: 'GET',
        data: {accion: 'obtener_jurisdicciones_sedes'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar jurisdicción sede...</option>';
                $.each(res, function(index, s) {
                    var selected = (selectedId == s.jurisdiccion_sede_id) ? 'selected' : '';
                    var org = s.organismo ? ` (${s.organismo})` : '';
                    options += `<option value="${s.jurisdiccion_sede_id}" ${selected}>${s.codigo_comarb} - ${s.jurisdiccion_sede}${org}</option>`;
                });
                $('#jurisdiccion_sede_id').html(options);
                if (typeof callback === 'function') callback();
            }
        }
    });
}

function actualizarConfDescripcionRegimen() {
    var desc = $('#iibb_regimen_id option:selected').data('desc') || '';
    if (desc) {
        $('#help_conf_iibb_regimen').html('<i class="fas fa-info-circle me-1 text-success"></i>' + desc).show();
    } else {
        $('#help_conf_iibb_regimen').empty().hide();
    }
}

$('#iibb_regimen_id').on('change', actualizarConfDescripcionRegimen);

function formatoFechaDMA(str) {
    if (!str) return '-';
    var p = str.split('-');
    return (p.length === 3) ? (p[2] + '/' + p[1] + '/' + p[0]) : str;
}

$('#provincia_id').on('change', function() {
    cargarLocalidades($(this).val());
});

$(document).ready(function(){
    cargarProvincias();
    cargarDocumentosTipos();
    cargarCondicionesFiscales();
    cargarIibbRegimenes();
    cargarJurisdiccionesSedes();

    // Configuración de DataTable con filtros por columna
    var tabla = $('#tablaempresas').DataTable({
        dom: '<"row mb-3"<"col-12 col-md-6"B><"col-12 col-md-6 text-end"f>>rt<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>',
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel me-1"></i> Excel',
                titleAttr: 'Exportar a Excel',
                className: 'btn btn-success btn-sm me-2 rounded-2',
                exportOptions: { columns: ':visible' }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fas fa-file-pdf me-1"></i> PDF',
                titleAttr: 'Exportar a PDF',
                className: 'btn btn-danger btn-sm rounded-2',
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions: { columns: ':visible' }
            }
        ],
        ajax: {
            url: 'empresas_ajax.php?accion=listar',
            dataSrc: function(json) {
                var data = json || [];
                // Actualizar tarjetas de resumen
                $('#statTotalEmpresas').text(data.length);
                var activas = data.filter(function(e){ return e.tabla_estado_registro_id == 1; }).length;
                $('#statEmpresasActivas').text(activas);
                var conCf = data.filter(function(e){ return e.condicion_fiscal_id > 0; }).length;
                $('#statConCondicion').text(conCf);
                return data;
            }
        },
        initComplete: function() {
            this.api().columns().every(function() {
                var column = this;
                var colIdx = column.index();
                if (colIdx === 8 || colIdx === 0) return;
                $('input', $('.filters th').eq(colIdx)).on('keyup change clear', function() {
                    if (column.search() !== this.value) {
                        column.search(this.value).draw();
                    }
                });
            });
        },
        language: {
            "lengthMenu": "Mostrar _MENU_ registros por página",
            "zeroRecords": "No se encontraron empresas registradas",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ empresas",
            "infoEmpty": "No hay registros disponibles",
            "infoFiltered": "(filtrado de _MAX_ totales)",
            "search": "Buscar:",
            "paginate": {
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            }
        },
        columns: [
            { 
                data: 'empresa_id',
                className: 'text-center fw-bold text-muted',
                render: function(data) {
                    return `<span class="badge bg-light text-dark border">#${data}</span>`;
                }
            },
            { 
                data: 'empresa',
                className: 'fw-bold',
                render: function(data, type, row) {
                    var tipos = { BIENES: 'Bienes', SERVICIOS: 'Servicios', MIXTA: 'Bienes y servicios' };
                    var tipo = tipos[row.tipo_operacion] || tipos.MIXTA;
                    var principalBadge = (parseInt(row.es_principal, 10) === 1)
                        ? ' <span class="badge bg-warning text-dark"><i class="fas fa-star me-1"></i>Principal</span>'
                        : '';
                    return `<i class="fas fa-building text-primary me-2"></i>${data || '-'}${principalBadge}<br><span class="badge bg-light text-secondary border fw-normal">${tipo}</span>`;
                }
            },
            { 
                data: 'condicion_fiscal',
                render: function(data, type, row) {
                    if (!data) return '<span class="badge bg-secondary">Sin definir</span>';
                    var cod = row.condicion_fiscal_codigo ? ` <span class="badge bg-light text-dark border ms-1">${row.condicion_fiscal_codigo}</span>` : '';
                    return `<span class="badge bg-primary text-white">${data}</span>${cod}`;
                }
            },
            { 
                data: 'documento_numero',
                render: function(data, type, row) {
                    var tipo = row.documento_tipo || 'CUIT';
                    return `<div><span class="badge bg-light text-muted border me-1">${tipo}</span><span class="font-monospace fw-semibold">${data || '-'}</span></div>`;
                }
            },
            { 
                data: 'ingresos_brutos_nro',
                render: function(data, type, row) {
                    var num = data ? `<span class="font-monospace small fw-bold">${data}</span>` : '<span class="text-muted small">No reg.</span>';
                    var regBadge = '';
                    if (row.iibb_regimen_id == 1) {
                        regBadge = '<span class="badge bg-primary mb-1" style="font-size:0.7rem;">Local</span>';
                    } else if (row.iibb_regimen_id == 2) {
                        regBadge = '<span class="badge mb-1 text-white" style="background-color:#6f42c1;font-size:0.7rem;">Convenio</span>';
                    } else if (row.iibb_regimen_id == 3) {
                        regBadge = '<span class="badge bg-warning text-dark mb-1" style="font-size:0.7rem;">Exento</span>';
                    }
                    var sede = row.codigo_comarb ? `<br><small class="text-muted"><span class="badge bg-light text-dark border">${row.codigo_comarb}</span> ${row.jurisdiccion_sede || ''}</small>` : '';
                    return `<div>${regBadge} ${num}${sede}</div>`;
                }
            },
            { 
                data: 'domicilio',
                render: function(data, type, row) {
                    var prov = row.provincia ? ` (${row.provincia})` : '';
                    var loc = row.localidad ? `<br><small class="text-muted"><i class="fas fa-map-pin me-1 text-danger"></i>${row.localidad}${prov}</small>` : '';
                    return `<div>${data || '-'}${loc}</div>`;
                }
            },
            { 
                data: null,
                render: function(data, type, row) {
                    var inicio = row.inicio_actividades ? `<i class="far fa-calendar-alt text-success me-1"></i>${formatoFechaDMA(row.inicio_actividades)}` : '<span class="text-muted">Inicio: -</span>';
                    var leyenda = row.leyenda_fiscal ? `<div class="text-truncate text-muted small" style="max-width: 180px;" title="${row.leyenda_fiscal}"><i class="fas fa-quote-left text-muted me-1"></i>${row.leyenda_fiscal}</div>` : '';
                    return `<div class="small">${inicio}${leyenda}</div>`;
                }
            },
            { 
                data: 'tabla_estado_registro_id',
                className: 'text-center',
                render: function(data, type, row) {
                    if (data == 1) {
                        return '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Activo</span>';
                    } else {
                        return '<span class="badge bg-danger"><i class="fas fa-ban me-1"></i>Inactivo</span>';
                    }
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: "text-center",
                render: function(data, type, row) {
                    var esPrincipal = parseInt(row.es_principal, 10) === 1;
                    var btnPrincipal = esPrincipal
                        ? `<button class="btn btn-sm btn-warning text-dark" disabled title="Empresa Principal del Sistema (define logo inicial y branding)"><i class="fas fa-star"></i></button>`
                        : `<button class="btn btn-sm btn-outline-warning btnMarcarPrincipal" data-id="${row.empresa_id}" data-nombre="${row.empresa}" title="Marcar como Empresa Principal del Sistema"><i class="far fa-star"></i></button>`;
                    return `
                        <div class="btn-group" role="group">
                            ${btnPrincipal}
                            <button class="btn btn-sm btn-outline-primary btnEditar" data-id="${row.empresa_id}" title="Editar Empresa y Datos Fiscales">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                            <a href="../gestion/empresas_parametros.php" class="btn btn-sm btn-outline-warning" title="Gestionar Parámetros y Vigencias">
                                <i class="fas fa-sliders-h"></i>
                            </a>
                            ${row.tabla_estado_registro_id == 1
                                ? `<button class="btn btn-sm btn-outline-danger btnCambiarEstado" data-id="${row.empresa_id}" data-nombre="${row.empresa}" data-accion="inhabilitar" title="Inhabilitar">
                                       <i class="fas fa-ban"></i>
                                   </button>`
                                : `<button class="btn btn-sm btn-outline-success btnCambiarEstado" data-id="${row.empresa_id}" data-nombre="${row.empresa}" data-accion="activar" title="Activar">
                                       <i class="fas fa-check"></i>
                                   </button>`}
                        </div>
                    `;
                }
            }
        ]
    });

    // Copiar CUIT a IIBB
    $('#btnCopiarCuitAIibb').click(function(){
        var cuit = $('#documento_numero').val();
        if (cuit) {
            $('#ingresos_brutos_nro').val(cuit);
        }
    });

    // Sugerir Leyenda Fiscal al cambiar condición fiscal
    $('#condicion_fiscal_id').change(function(){
        var cfTexto = $(this).find('option:selected').text();
        if (cfTexto && !$('#leyenda_fiscal').val()) {
            var limpio = cfTexto.split('(')[0].trim();
            $('#leyenda_fiscal').val('IVA ' + limpio);
        }
    });

    // Logo: vista previa del actual (logo_data viene como data URI) o del archivo elegido
    var logoActual = '';
    function pintarLogo(src) {
        if (src) {
            $('#logoPreview').attr('src', src).removeClass('d-none');
            $('#logoSinImagen').addClass('d-none');
        } else {
            $('#logoPreview').attr('src', '').addClass('d-none');
            $('#logoSinImagen').removeClass('d-none');
        }
    }
    // Colores del encabezado: los mismos valores por defecto que core/empresa_logo.php
    var COLORES_DEFECTO = { oscuro: '#03112f', claro: '#054a96' };
    var ORIGEN_COLORES = { IA: 'Elegidos por la IA', AUTO: 'Calculados del logo', MANUAL: 'Ajustados a mano' };
    var colorValido = function(c) { return /^#[0-9a-f]{6}$/i.test(c || ''); };

    function pintarColores(oscuro, claro) {
        $('#logoMuestraColores').css('background', 'linear-gradient(90deg, ' + oscuro + ', ' + claro + ')');
    }
    function mostrarColores(oscuro, claro, origen) {
        var hay = colorValido(oscuro) && colorValido(claro);
        oscuro = hay ? oscuro : COLORES_DEFECTO.oscuro;
        claro = hay ? claro : COLORES_DEFECTO.claro;
        $('#color_logo_oscuro').val(oscuro);
        $('#color_logo_claro').val(claro);
        $('#colores_manual').val('0');
        $('#logoColoresOrigen').text(hay ? (ORIGEN_COLORES[origen] || 'Calculados del logo') : 'Por defecto');
        pintarColores(oscuro, claro);
    }
    // Mezcla de los dos colores (el --ui-logo-medio que arma emp_logo_css)
    function mezclarColores(a, b, t) {
        var ca = [1, 3, 5].map(function(i) { return parseInt(a.substr(i, 2), 16); });
        var cb = [1, 3, 5].map(function(i) { return parseInt(b.substr(i, 2), 16); });
        return '#' + ca.map(function(v, i) { return ('0' + Math.round(v + (cb[i] - v) * t).toString(16)).slice(-2); }).join('');
    }
    // Si se editó la empresa activa, la pantalla toma los colores nuevos sin recargar
    function aplicarTemaSiActiva(empresaId, oscuro, claro) {
        if (parseInt(empresaId, 10) !== parseInt(window.EMPRESA_ID, 10) || !colorValido(oscuro) || !colorValido(claro)) return;
        var raiz = document.documentElement.style;
        raiz.setProperty('--ui-logo-marino', oscuro);
        raiz.setProperty('--ui-logo-medio', mezclarColores(oscuro, claro, 0.45));
        raiz.setProperty('--ui-logo-azul', claro);
    }

    function mostrarLogo(src, oscuro, claro, origen) {
        logoActual = src || '';
        $('#logo').val('');
        $('#quitar_logo').val('0');
        $('#logoColoresNuevo').addClass('d-none');
        pintarLogo(logoActual);
        mostrarColores(oscuro, claro, origen);
        // Ajustar / recalcular solo con un logo ya guardado
        $('#logoColores').toggleClass('d-none', !(logoActual && $('#empresa_id').val()));
    }
    $('#logo').on('change', function() {
        var archivo = this.files[0];
        if (!archivo) {
            pintarLogo($('#quitar_logo').val() === '1' ? '' : logoActual);
            $('#logoColoresNuevo').addClass('d-none');
            $('#logoColores').toggleClass('d-none', !(logoActual && $('#empresa_id').val() && $('#quitar_logo').val() !== '1'));
            return;
        }
        if (archivo.size > 1048576) {
            Swal.fire('Logo demasiado grande', 'El logo no puede superar 1 MB.', 'warning');
            $(this).val('');
            return;
        }
        $('#quitar_logo').val('0');
        $('#logoColores').addClass('d-none'); // un logo nuevo: los colores se eligen al guardar
        $('#logoColoresNuevo').removeClass('d-none');
        var lector = new FileReader();
        lector.onload = function(e) { pintarLogo(e.target.result); };
        lector.readAsDataURL(archivo);
    });
    $('#btnQuitarLogo').on('click', function() {
        $('#logo').val('');
        $('#quitar_logo').val('1');
        $('#logoColores, #logoColoresNuevo').addClass('d-none');
        pintarLogo('');
    });
    $('#color_logo_oscuro, #color_logo_claro').on('input', function() {
        $('#colores_manual').val('1');
        $('#logoColoresOrigen').text('Ajustados a mano (sin guardar)');
        pintarColores($('#color_logo_oscuro').val(), $('#color_logo_claro').val());
    });
    $('#btnRecalcularColores').on('click', function() {
        var empresaId = $('#empresa_id').val();
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Eligiendo...');
        $.post('empresas_ajax.php', { accion: 'recalcular_colores', empresa_id: empresaId }, function(res) {
            if (res && res.success) {
                mostrarColores(res.data.oscuro, res.data.claro, res.data.origen);
                aplicarTemaSiActiva(empresaId, res.data.oscuro, res.data.claro);
                Swal.fire({ icon: 'success', title: res.message, timer: 1600, showConfirmButton: false });
            } else {
                Swal.fire('No se pudo recalcular', (res && res.message) || 'Probá de nuevo en unos minutos.', 'warning');
            }
        }, 'json').fail(function() {
            Swal.fire('Error de red', 'No se pudo comunicar con el servidor.', 'error');
        }).always(function() {
            $btn.prop('disabled', false).html('<i class="fas fa-magic me-1"></i>Recalcular');
        });
    });

    // Botón Nueva Empresa
    // Uso de la IA (core/ia_empresa.php): la clave nunca viene del servidor, solo si está y sus últimos 4 caracteres
    var iaEstado = null;
    function mostrarIa(estado) {
        iaEstado = estado || { ia_habilitada: 1, clave_propia: 0, mascara: '', origen: 'servidor', es_principal: 0, principal: '' };
        $('#ia_habilitada').prop('checked', parseInt(iaEstado.ia_habilitada, 10) === 1);
        $('#ia_api_key').val('');
        $('#ia_api_key_quitar').prop('checked', false);
        $('#ia_api_key_quitar_box').toggleClass('d-none', parseInt(iaEstado.clave_propia, 10) !== 1);
        pintarEstadoIa();
    }
    function pintarEstadoIa() {
        var e = iaEstado || {};
        var propia = parseInt(e.clave_propia, 10) === 1 && !$('#ia_api_key_quitar').is(':checked');
        var esPrincipal = $('#es_principal').is(':checked');
        var txt;
        if ($('#ia_api_key').val().trim() !== '') {
            txt = 'Se guarda la clave nueva.';
        } else if (propia) {
            txt = 'Clave propia cargada: ' + (e.mascara || '') + '. Dejá el campo vacío para conservarla.';
        } else if (esPrincipal) {
            txt = 'Sin clave propia: las empresas sin clave usan la de la principal, así que conviene cargarla acá.';
        } else if (e.principal) {
            txt = 'Sin clave propia: usa la de la empresa principal (' + e.principal + ').';
        } else {
            txt = 'Sin clave propia: usa la del servidor, si hay.';
        }
        $('#ia_api_key_estado').text(txt);
        $('#iaClaveBox').toggleClass('opacity-50', !$('#ia_habilitada').is(':checked'));
        $('#ia_api_key, #ia_api_key_quitar').prop('disabled', !$('#ia_habilitada').is(':checked'));
    }
    $('#ia_habilitada, #ia_api_key_quitar, #es_principal').on('change', pintarEstadoIa);
    $('#ia_api_key').on('input', pintarEstadoIa);

    $('#btnNuevo').click(function(){
        $('#formempresa')[0].reset();
        $('#empresa_id').val('');
        $('#es_principal').prop('checked', false);
        mostrarLogo('');
        // Nueva: IA habilitada; sin clave propia usa la de la principal
        var principal = '';
        if (tabla) {
            tabla.rows().data().each(function(r) { if (parseInt(r.es_principal, 10) === 1) principal = r.empresa || ''; });
        }
        mostrarIa({ ia_habilitada: 1, clave_propia: 0, mascara: '', origen: principal ? 'principal' : 'servidor', es_principal: 0, principal: principal });
        $('#formempresa').removeClass('was-validated');
        $('#tab-generales-tab').tab('show');

        cargarDocumentosTipos(1);
        cargarProvincias(null);
        cargarLocalidades(null);
        cargarCondicionesFiscales(null);
        cargarIibbRegimenes(1);
        cargarJurisdiccionesSedes(2);

        $('#bloqueConstancia').removeClass('d-none').addClass('d-flex');
        $('#modalLabel').html('<i class="fas fa-plus-circle me-2"></i>Nueva Empresa');
        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalempresa'));
        modal.show();
    });

    // Alta desde la constancia de inscripción de ARCA, leída con IA (assets/js/constancia-cuit-ia.js)
    function leerConstanciaEmpresa() {
        ConstanciaCuitIa.leer({
            url: 'empresas_ajax.php',
            extraHtml: function(d) {
                if (!d.empresa_existente) return '';
                return '<div class="alert alert-warning py-2 small text-start">Ya existe la empresa <strong>'
                    + $('<div>').text(d.empresa_existente.empresa).html() + '</strong> (#' + d.empresa_existente.empresa_id + ') con este CUIT.</div>';
            },
            completar: function(d) {
                $('#tab-generales-tab').tab('show');
                if (d.razon_social) $('#empresa').val(d.razon_social);
                if (d.cuit) $('#documento_numero').val(d.cuit);
                if (d.domicilio) $('#domicilio').val(d.domicilio);
                // Tipo de documento CUIT por nombre (no por id)
                var $cuit = $('#documento_tipo_id option').filter(function() { return $(this).text().trim().toUpperCase() === 'CUIT'; }).first();
                if ($cuit.length) $('#documento_tipo_id').val($cuit.val());
                if (d.provincia_id) {
                    cargarProvincias(d.provincia_id);
                    cargarLocalidades(d.provincia_id, d.localidad_id);
                }
                if (d.condicion_fiscal_id) cargarCondicionesFiscales(d.condicion_fiscal_id);
                if (d.jurisdiccion_sede_id) $('#jurisdiccion_sede_id').val(String(d.jurisdiccion_sede_id));
                if (d.inicio_actividades) $('#inicio_actividades').val(d.inicio_actividades);
                if (d.leyenda_fiscal) $('#leyenda_fiscal').val(d.leyenda_fiscal);
                if (window.UiSimple) UiSimple.ok('Datos de la constancia cargados: revisalos y guardá.');
            }
        });
    }
    $('#btnLeerConstancia').on('click', leerConstanciaEmpresa);
    $('#btnNuevoConstancia').on('click', function() {
        $('#btnNuevo').trigger('click');
        leerConstanciaEmpresa();
    });

    // Botón Editar Empresa
    $('#tablaempresas tbody').on('click', '.btnEditar', function(){
        var id = $(this).data('id');
        $.get('empresas_ajax.php', {accion: 'obtener', empresa_id: id}, function(res){
            if(res){
                $('#formempresa')[0].reset();
                $('#formempresa').removeClass('was-validated');
                $('#tab-generales-tab').tab('show');

                $('#empresa_id').val(res.empresa_id);
                $('#empresa').val(res.empresa || '');
                $('#documento_numero').val(res.documento_numero || '');
                $('#telefono').val(res.telefono || '');
                $('#domicilio').val(res.domicilio || '');
                $('#email').val(res.email || '');
                $('#tipo_operacion').val(res.tipo_operacion || 'MIXTA');
                $('#nivel_gestion').val(res.nivel_gestion || 'COMPLETA');
                $('#formato_fecha').val(res.formato_fecha || 'd/m/Y');
                $('#formato_numero').val((res.separador_miles || 'PUNTO') + '|' + (res.separador_decimal || 'COMA'));
                if (!$('#formato_numero').val()) $('#formato_numero').val('PUNTO|COMA');
                $('#decimales_stock').val(res.decimales_stock !== undefined && res.decimales_stock !== null ? String(res.decimales_stock) : '2');
                $('#usa_presentaciones').prop('checked', parseInt(res.usa_presentaciones, 10) === 1);
                $('#base_conf').val(res.base_conf || '');
                $('#estado_registro_id').val(res.tabla_estado_registro_id || 1);
                $('#es_principal').prop('checked', parseInt(res.es_principal, 10) === 1);
                mostrarLogo(res.logo_data || '', res.color_logo_oscuro, res.color_logo_claro, res.color_logo_origen);
                mostrarIa(res.ia);

                // Campos fiscales principales
                $('#ingresos_brutos_nro').val(res.ingresos_brutos_nro || '');
                $('#inicio_actividades').val(res.inicio_actividades || '');
                $('#leyenda_fiscal').val(res.leyenda_fiscal || '');

                cargarDocumentosTipos((res.documento_tipo_id && parseInt(res.documento_tipo_id) > 0) ? res.documento_tipo_id : 1);
                cargarProvincias(res.provincia_id);
                cargarLocalidades(res.provincia_id, res.localidad_id);
                cargarCondicionesFiscales(res.condicion_fiscal_id);
                cargarIibbRegimenes(res.iibb_regimen_id || 1);
                cargarJurisdiccionesSedes(res.jurisdiccion_sede_id || 2);

                $('#bloqueConstancia').removeClass('d-flex').addClass('d-none');
                $('#modalLabel').html('<i class="fas fa-edit me-2"></i>Editar Empresa: ' + (res.empresa || ''));
                var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalempresa'));
                modal.show();
            } else {
                Swal.fire('Error', 'No se pudieron obtener los datos de la empresa', 'error');
            }
        }, 'json');
    });

    // Botón Marcar Empresa Principal del Sistema
    $('#tablaempresas tbody').on('click', '.btnMarcarPrincipal', function(){
        var id = $(this).data('id');
        var nombre = $(this).data('nombre');

        Swal.fire({
            title: "¿Establecer como Empresa Principal?",
            text: `El logo de "${nombre}" se utilizará como logo inicial en el inicio de sesión y la cabecera general del sistema.`,
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#ffc107",
            confirmButtonText: '<span class="text-dark fw-bold"><i class="fas fa-star me-1"></i>Sí, marcar como principal</span>',
            cancelButtonColor: "#6c757d",
            cancelButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('empresas_ajax.php', {accion: 'marcar_principal', empresa_id: id}, function(res){
                    if (res && res.success) {
                        tabla.ajax.reload(null, false);
                        Swal.fire({
                            icon: "success",
                            title: res.message,
                            timer: 1600,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire('Error', (res && res.message) || 'No se pudo establecer la empresa como principal.', 'error');
                    }
                }, 'json').fail(function() {
                    Swal.fire('Error de red', 'No se pudo comunicar con el servidor.', 'error');
                });
            }
        });
    });

    // Botón Inhabilitar / Activar Empresa (nunca se borra: tiene datos vinculados)
    $('#tablaempresas tbody').on('click', '.btnCambiarEstado', function(){
        var id = $(this).data('id');
        var nombre = $(this).data('nombre');
        var accion = $(this).data('accion');
        var inhabilitar = accion === 'inhabilitar';

        Swal.fire({
            title: inhabilitar ? "¿Inhabilitar Empresa?" : "¿Activar Empresa?",
            text: inhabilitar
                ? `"${nombre}" dejará de aparecer en el selector de empresas. Se puede volver a activar.`
                : `"${nombre}" volverá a estar disponible para sus usuarios.`,
            icon: inhabilitar ? "warning" : "question",
            showCancelButton: true,
            confirmButtonColor: inhabilitar ? "#d33" : "#198754",
            cancelButtonColor: "#6c757d",
            confirmButtonText: inhabilitar ? "Sí, inhabilitar" : "Sí, activar",
            cancelButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('empresas_ajax.php', {accion: accion, empresa_id: id}, function(res){
                    if (res.success) {
                        tabla.ajax.reload(null, false);
                        Swal.fire({
                            icon: "success",
                            title: res.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire('Error', res.message || 'No se pudo actualizar la empresa.', 'error');
                    }
                }, 'json');
            }
        });
    });

    // Botón Guardar Empresa
    $('#btnGuardar').click(function(){
        var form = $('#formempresa')[0];
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            // Si el error está en la primera solapa, activarla
            if ($('#empresa:invalid, #documento_numero:invalid, #localidad_id:invalid').length > 0) {
                $('#tab-generales-tab').tab('show');
            }
            return false;
        }
        
        var id = $('#empresa_id').val();
        var accion = id ? 'editar' : 'agregar';
        
        var formData = {
            accion: accion,
            empresa_id: id,
            empresa: $('#empresa').val(),
            documento_tipo_id: $('#documento_tipo_id').val(),
            documento_numero: $('#documento_numero').val(),
            telefono: $('#telefono').val(),
            domicilio: $('#domicilio').val(),
            localidad_id: $('#localidad_id').val(),
            condicion_fiscal_id: $('#condicion_fiscal_id').val(),
            iibb_regimen_id: $('#iibb_regimen_id').val(),
            jurisdiccion_sede_id: $('#jurisdiccion_sede_id').val(),
            ingresos_brutos_nro: $('#ingresos_brutos_nro').val(),
            inicio_actividades: $('#inicio_actividades').val(),
            leyenda_fiscal: $('#leyenda_fiscal').val(),
            email: $('#email').val(),
            tipo_operacion: $('#tipo_operacion').val(),
            nivel_gestion: $('#nivel_gestion').val(),
            formato_fecha: $('#formato_fecha').val(),
            separador_miles: String($('#formato_numero').val() || '').split('|')[0],
            separador_decimal: String($('#formato_numero').val() || '').split('|')[1],
            decimales_stock: $('#decimales_stock').val(),
            usa_presentaciones: $('#usa_presentaciones').is(':checked') ? 1 : 0,
            base_conf: $('#base_conf').val(),
            es_principal: $('#es_principal').is(':checked') ? 1 : 0,
            ia_habilitada: $('#ia_habilitada').is(':checked') ? 1 : 0,
            ia_api_key: $('#ia_habilitada').is(':checked') ? $('#ia_api_key').val().trim() : '',
            ia_api_key_quitar: $('#ia_habilitada').is(':checked') && $('#ia_api_key_quitar').is(':checked') ? 1 : 0,
            tabla_estado_registro_id: $('#estado_registro_id').val(),
            quitar_logo: $('#quitar_logo').val(),
            colores_manual: $('#colores_manual').val(),
            color_logo_oscuro: $('#color_logo_oscuro').val(),
            color_logo_claro: $('#color_logo_claro').val()
        };
        // multipart: viaja el archivo del logo junto con los datos
        var datos = new FormData();
        $.each(formData, function(clave, valor) { datos.append(clave, valor == null ? '' : valor); });
        var archivoLogo = $('#logo')[0].files[0];
        if (archivoLogo) datos.append('logo', archivoLogo);

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({ url: 'empresas_ajax.php', type: 'POST', data: datos, processData: false, contentType: false, dataType: 'json' }).done(function(res){
            $btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Empresa');
            if(res.resultado){
                if (res.tema) aplicarTemaSiActiva(id, res.tema.oscuro, res.tema.claro);
                tabla.ajax.reload();
                var modal = bootstrap.Modal.getInstance(document.getElementById('modalempresa'));
                if (modal) modal.hide();
                Swal.fire({
                    icon: "success",
                    title: id ? "Empresa y Parámetros Fiscales Actualizados" : "Empresa Creada con Éxito",
                    timer: 1600,
                    showConfirmButton: false
                });
            } else {
                Swal.fire('Error', res.error || 'Ocurrió un error al guardar los datos.', 'error');
            }
        }).fail(function(){
            $btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Empresa');
            Swal.fire('Error de red', 'No se pudo comunicar con el servidor.', 'error');
        });
    });
});
</script>

<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="<?= asset_local('js/constancia-cuit-ia.js') ?>?v=<?= filemtime(ROOT_PATH . '/assets/js/constancia-cuit-ia.js') ?>"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>