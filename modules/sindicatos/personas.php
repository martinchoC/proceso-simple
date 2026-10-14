<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Padrón de Personas / Trabajadores";
$currentPage = 'personas';
$modudo_idx = 6;
$pagina_idx = 1012;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-users me-2 text-primary"></i>Personas / Trabajadores
                    </h3>
                    <small class="text-muted">Padrón de personas físicas, trabajadores en relación de dependencia y afiliados</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Sindicatos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Personas</li>
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
                                <table id="tablaPersonas" class="table table-hover table-striped align-middle" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th>CUIL</th>
                                            <th>Apellido y Nombre</th>
                                            <th>Documento</th>
                                            <th class="text-center">Sexo</th>
                                            <th>F. Nacimiento</th>
                                            <th>Contacto</th>
                                            <th class="text-center">Empleos Activos</th>
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

    <!-- Modal Persona -->
    <div class="modal fade" id="modalPersona" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalPersonaTitle">
                        <i class="fas fa-user me-2 text-primary"></i>Trabajador / Persona
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formPersona">
                    <div class="modal-body">
                        <input type="hidden" name="persona_id" id="persona_id" value="">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">CUIL <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="cuil" name="cuil" required maxlength="11" placeholder="20123456789">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Apellido <span class="text-danger">*</span></label>
                                <input type="text" class="form-control text-uppercase" id="apellido" name="apellido" required maxlength="100">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control text-uppercase" id="nombre" name="nombre" required maxlength="100">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Tipo Doc.</label>
                                <select class="form-select" id="documento_tipo_id" name="documento_tipo_id">
                                    <option value="1">DNI</option>
                                    <option value="2">LE</option>
                                    <option value="3">LC</option>
                                    <option value="4">Pasaporte</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">N° Documento</label>
                                <input type="text" class="form-control" id="documento_nro" name="documento_nro" maxlength="20">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Sexo</label>
                                <select class="form-select" id="sexo" name="sexo">
                                    <option value="M">Masculino</option>
                                    <option value="F">Femenino</option>
                                    <option value="X">No binario / Otro</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">F. Nacimiento</label>
                                <input type="date" class="form-control" id="f_nacimiento" name="f_nacimiento">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Teléfono</label>
                                <input type="text" class="form-control" id="telefono" name="telefono">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Dirección / Domicilio</label>
                                <input type="text" class="form-control" id="direccion" name="direccion">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4" id="btnGuardarPer">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<script src="personas.js"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
