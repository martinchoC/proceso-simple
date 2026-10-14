<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Afiliados y Grupo Familiar";
$currentPage = 'afiliados';
$modudo_idx = 6;
$pagina_idx = 1050;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<div class="app-content-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-sm-6">
                <h3 class="mb-0"><i class="fas fa-id-card me-2 text-primary"></i>Afiliados y Grupo Familiar</h3>
                <small class="text-muted">Afiliaciones por actividad, voluntarias y de jubilados, con su grupo familiar a cargo y vigencias</small>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-end">
                    <li class="breadcrumb-item"><a href="#">Home</a></li>
                    <li class="breadcrumb-item"><a href="#">Sindicatos</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Afiliados</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="app-content">
    <div class="container-fluid">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent py-3">
                <div class="row g-2 align-items-center">
                    <div class="col-md-auto">
                        <button type="button" class="btn btn-primary btn-sm d-none" id="btnNuevaAfiliacion">
                            <i class="fas fa-plus me-1"></i> Nueva afiliación
                        </button>
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control form-control-sm" id="filtroTexto" placeholder="CUIL, apellido o N° de afiliado">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" id="filtroTipo"><option value="">Todos los tipos</option></select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" id="filtroEstado">
                            <option value="">Todos los estados</option>
                            <option value="1">Activa</option>
                            <option value="20">Vencida</option>
                            <option value="2">Baja</option>
                        </select>
                    </div>
                    <div class="col-md-auto ms-auto">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRecargar"><i class="fas fa-sync-alt"></i></button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaAfiliados" class="table table-hover table-striped align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th>N° Afiliado</th>
                                <th>Apellido y Nombre</th>
                                <th>CUIL</th>
                                <th>Tipo</th>
                                <th>Empleador</th>
                                <th>Alta</th>
                                <th class="text-center">Familiares</th>
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

<!-- Modal persona (alta de titular o de familiar) -->
<div class="modal fade" id="modalPersona" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalPersonaTitulo">Nueva afiliación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formPersona">
                <div class="modal-body">
                    <input type="hidden" name="modo" id="modo" value="titular">
                    <input type="hidden" name="titular_afiliacion_id" id="titular_afiliacion_id" value="">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">CUIL <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="cuil" id="cuil" maxlength="11" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Apellido <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="apellido" id="apellido" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Nombre</label>
                            <input type="text" class="form-control" name="nombre" id="nombre">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fecha de nacimiento</label>
                            <input type="date" class="form-control" name="f_nacimiento" id="f_nacimiento">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Sexo</label>
                            <select class="form-select" name="sexo" id="sexo">
                                <option value="">-</option><option value="F">F</option><option value="M">M</option><option value="X">X</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fecha de alta</label>
                            <input type="date" class="form-control" name="f_alta" id="f_alta">
                        </div>
                        <div class="col-md-6 bloque-titular">
                            <label class="form-label fw-semibold">Tipo de afiliación <span class="text-danger">*</span></label>
                            <select class="form-select" name="tipo_codigo" id="tipo_codigo"></select>
                        </div>
                        <div class="col-md-6 bloque-titular bloque-empleador d-none">
                            <label class="form-label fw-semibold">Empleador <span class="text-danger">*</span></label>
                            <select class="form-select" name="empleador_id" id="empleador_id"><option value="">(Seleccionar)</option></select>
                        </div>
                        <div class="col-md-6 bloque-titular">
                            <label class="form-label fw-semibold">Correo electrónico</label>
                            <input type="email" class="form-control" name="email" id="email">
                        </div>
                        <div class="col-md-6 bloque-titular">
                            <label class="form-label fw-semibold">Teléfono</label>
                            <input type="text" class="form-control" name="telefono" id="telefono">
                        </div>
                        <div class="col-md-6 bloque-familiar d-none">
                            <label class="form-label fw-semibold">Parentesco <span class="text-danger">*</span></label>
                            <select class="form-select" name="parentesco_id" id="parentesco_id"></select>
                        </div>
                        <div class="col-md-6 bloque-familiar d-none">
                            <label class="form-label fw-semibold">Documento de excepción presentado</label>
                            <input type="text" class="form-control" name="documento" id="documento" placeholder="Ej.: certificado de alumno regular">
                            <small class="text-muted">Solo si el familiar supera la edad de cobertura y el parentesco admite excepción.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4" id="btnGuardarPersona">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal ficha -->
<div class="modal fade" id="modalFicha" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="fichaTitulo">Ficha del afiliado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="fichaCuerpo"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary d-none" id="btnFichaFamiliar"><i class="fas fa-plus me-1"></i>Agregar familiar</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal baja -->
<div class="modal fade" id="modalBaja" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Dar de baja</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formBaja">
                <div class="modal-body">
                    <input type="hidden" name="afiliacion_id" id="baja_afiliacion_id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Motivo</label>
                        <select class="form-select" name="baja_motivo_id" id="baja_motivo_id"><option value="">(Sin motivo)</option></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Fecha de baja</label>
                        <input type="date" class="form-control" name="f_baja" id="f_baja" required>
                    </div>
                    <div class="alert alert-warning small mb-0">La baja de un titular da de baja a todo su grupo familiar.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger px-4">Dar de baja</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal documento -->
<div class="modal fade" id="modalDocumento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Presentar documento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formDocumento" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="afiliacion_id" id="doc_afiliacion_id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Documento</label>
                        <input type="text" class="form-control" name="documento" id="doc_documento" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Fecha de presentación</label>
                        <input type="date" class="form-control" name="f_presentacion" id="doc_f_presentacion" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Archivo (PDF o imagen)</label>
                        <input type="file" class="form-control" name="archivo" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4">Registrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="afiliados.js"></script>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
