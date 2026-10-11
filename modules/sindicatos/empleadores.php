<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Padrón de Empleadores Aportantes";
$currentPage = 'empleadores';
$modudo_idx = 6;
$pagina_idx = 1010;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-building me-2 text-primary"></i>Padrón de Empleadores
                    </h3>
                    <small class="text-muted">Empresas aportantes al sindicato, datos comerciales y seguimiento de mora</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Sindicatos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Empleadores</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-transparent py-3">
                            <div id="contenedor-boton-agregar" class="d-inline"></div>
                            <button type="button" class="btn btn-outline-secondary btn-sm float-end" id="btnRecargar">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tablaEmpleadores" class="table table-hover table-striped align-middle" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th>CUIT</th>
                                            <th>Razón Social</th>
                                            <th>Rubro</th>
                                            <th>Convenio</th>
                                            <th>Contacto</th>
                                            <th>Categoría Mora</th>
                                            <th>Estado</th>
                                            <th class="text-end">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Empleador -->
    <div class="modal fade" id="modalEmpleador" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalEmpleadorTitle">
                        <i class="fas fa-building me-2 text-primary"></i>Empleador
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formEmpleador">
                    <div class="modal-body">
                        <input type="hidden" name="empleador_id" id="empleador_id" value="">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">CUIT <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="cuit" name="cuit" required maxlength="11" placeholder="30123456789">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Razón Social <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="razon_social" name="razon_social" required maxlength="150">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Rubro / Actividad Comercial</label>
                                <input type="text" class="form-control" id="rubro" name="rubro" placeholder="Comercio minorista">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Convenio CCT Predeterminado</label>
                                <select class="form-select" id="convenio_id" name="convenio_id">
                                    <option value="">(Sin asignar)</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Correo Electrónico</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Teléfono</label>
                                <input type="text" class="form-control" id="telefono" name="telefono">
                            </div>

                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Domicilio Comercial</label>
                                <input type="text" class="form-control" id="domicilio" name="domicilio">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Fecha de Alta en Padrón</label>
                                <input type="date" class="form-control" id="f_alta_padron" name="f_alta_padron">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4" id="btnGuardarEmp">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script src="empleadores.js"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
