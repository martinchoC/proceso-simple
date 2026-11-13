<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Plan de Cuentas";
$currentPage = 'plan_cuentas';
$modudo_idx = 5;
$pagina_idx = 568; // ID de página para plan de cuentas
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <?php ui_encabezado('Plan de cuentas','fas fa-book', '', ['Contabilidad']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="content-wrapper">
                <section class="content">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <div id="contenedor-boton-agregar" class="d-inline"></div>
                                    </div>

                                    <div class="card-body">
                                        <!-- Barra de herramientas superior -->
                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <div class="btn-group" role="group" aria-label="Controles de jerarquía">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnExpandAll" title="Expandir todo">
                                                        <i class="fas fa-expand-alt"></i> Expandir
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnCollapseAll" title="Contraer todo">
                                                        <i class="fas fa-compress-alt"></i> Contraer
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Recargar tabla">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text bg-white" id="filtroJerarquicoLabel">
                                                        <i class="fas fa-filter text-primary"></i>
                                                    </span>
                                                    <select class="form-select" id="filtroCuentaRaiz" aria-label="Filtro por cuenta raíz">
                                                        <option value="">-- Mostrar todas las cuentas --</option>
                                                    </select>
                                                    <button class="btn btn-outline-secondary" type="button" id="btnLimpiarFiltro" title="Limpiar filtro">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                                <div class="form-text mt-1">
                                                    <?= ui_ayuda('Seleccione una cuenta para filtrar por ella y todas sus subcuentas') ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- DataTable con estructura jerárquica expandible -->
                                        <table id="tablaPlanCuentas" class="table table-striped table-bordered" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="60">ID</th>
                                                    <th width="100">Código</th>
                                                    <th width="350">Cuenta</th>
                                                    <th width="100">Naturaleza</th>
                                                    <th width="80">Nivel</th>
                                                    <th width="100">Imputable</th>
                                                    <th width="120">Estado</th>
                                                    <th width="200" class="text-center">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Modal para crear/editar cuenta -->
            <div class="modal fade" id="modalCuenta" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Cuenta Contable</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formCuenta" class="needs-validation" novalidate>
                                <input type="hidden" id="cont_cuenta_id" name="cont_cuenta_id" />
                                <input type="hidden" id="nivel" name="nivel" value="1" />
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="codigo" class="form-label">Código *</label>
                                        <input type="text" class="form-control" id="codigo" name="codigo" 
                                            maxlength="20" required pattern="[A-Za-z0-9\.\-]+">
                                        <div class="invalid-feedback">El código es obligatorio (solo letras, números, puntos y guiones)</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="cuenta_padre_id" class="form-label">Cuenta Padre <?= ui_ayuda('Seleccionar para crear cuentas jerárquicas') ?></label>
                                        <select class="form-select" id="cuenta_padre_id" name="cuenta_padre_id">
                                            <option value="">-- Ninguna (Cuenta Raíz) --</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="nombre" class="form-label">Nombre de la Cuenta *</label>
                                        <input type="text" class="form-control" id="nombre" name="nombre" 
                                            maxlength="255" required>
                                        <div class="invalid-feedback">El nombre de la cuenta es obligatorio</div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="naturaleza" class="form-label">Naturaleza *</label>
                                        <select class="form-select" id="naturaleza" name="naturaleza" required>
                                            <option value="">-- Seleccionar --</option>
                                            <option value="DEUDORA">DEUDORA</option>
                                            <option value="ACREEDORA">ACREEDORA</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione la naturaleza de la cuenta</div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="orden" class="form-label">Orden <?= ui_ayuda('Para ordenamiento personalizado') ?></label>
                                        <input type="number" class="form-control" id="orden" name="orden" 
                                            value="0" min="0" step="1">
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="es_imputable" class="form-label">Tipo <?= ui_ayuda('Las cuentas título no aceptan movimientos') ?></label>
                                        <select class="form-select" id="es_imputable" name="es_imputable">
                                            <option value="1">Imputable (Permite asientos)</option>
                                            <option value="0">Título (No imputable)</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info mt-2" id="infoNivel">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <span>Nivel de la cuenta: <strong id="nivelDisplay">1</strong></span>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="btnGuardar">
                                <i class="fas fa-save me-1"></i>Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Estilos personalizados -->
    <style>
        .dt-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .dataTables_wrapper .dt-buttons {
            float: right;
            margin-top: 5px;
        }
        .dropdown-menu .dropdown-item i {
            width: 20px;
            text-align: center;
            margin-right: 8px;
        }
        /* Estilo para mostrar jerarquía con sangría y controles de expansión */
        .cuenta-item {
            cursor: pointer;
        }
        .cuenta-nivel-1 { padding-left: 0px !important; font-weight: 600; }
        .cuenta-nivel-2 { padding-left: 30px !important; }
        .cuenta-nivel-3 { padding-left: 60px !important; }
        .cuenta-nivel-4 { padding-left: 90px !important; }
        .cuenta-nivel-5 { padding-left: 120px !important; }
        .cuenta-nivel-6 { padding-left: 150px !important; }
        .cuenta-nivel-7 { padding-left: 180px !important; }
        .cuenta-nivel-8 { padding-left: 210px !important; }
        .cuenta-nivel-9 { padding-left: 240px !important; }
        
        .cuenta-titulo { font-style: italic; color: #6c757d; }
        .cuenta-imputable { font-weight: 500; }
        
        .expand-control {
            display: inline-block;
            width: 20px;
            margin-left: -25px;
            margin-right: 5px;
            text-align: center;
            cursor: pointer;
            color: #007bff;
        }
        .expand-control:hover {
            color: #0056b3;
        }
        .no-expand {
            display: inline-block;
            width: 20px;
            margin-left: -25px;
            margin-right: 5px;
            text-align: center;
            opacity: 0.3;
        }
        .hidden-row {
            display: none !important;
        }
        
        /* Estilo para la barra de herramientas */
        .herramientas-superiores {
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        
        /* Resaltar la cuenta seleccionada en el filtro */
        .cuenta-filtrada {
            background-color: #e8f4fd !important;
            border-left: 4px solid #0d6efd !important;
        }
    </style>

    <!-- DataTables (y Responsive) ya los carga header1.php -->
    <script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
    
    <!-- Script principal -->
    <script>window.PLAN_CUENTAS_CTX = { empresa_id: <?= (int)($_SESSION['empresa_id'] ?? 0) ?>, pagina_idx: <?= (int)$pagina_idx ?> };</script>
    <script src="plan_cuentas.js?v=<?= filemtime(__DIR__.'/plan_cuentas.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>