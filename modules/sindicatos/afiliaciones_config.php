<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Tipos, Parentescos y Vigencias";
$currentPage = 'afiliaciones_config';
$modudo_idx = 6;
$pagina_idx = 1054;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<div class="app-content-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-sm-6">
                <h3 class="mb-0"><i class="fas fa-sitemap me-2 text-primary"></i>Tipos, Parentescos y Vigencias</h3>
                <small class="text-muted">Configuración de las afiliaciones: cuota directa, parentescos del grupo familiar y edad de cobertura</small>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-end">
                    <li class="breadcrumb-item"><a href="#">Home</a></li>
                    <li class="breadcrumb-item"><a href="#">Sindicatos</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Configuración de afiliaciones</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="app-content">
    <div class="container-fluid">
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabTipos" type="button">Tipos de afiliación</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabParentescos" type="button">Parentescos</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabVigencias" type="button">Vigencias por edad</button></li>
        </ul>
        <div class="tab-content border border-top-0 bg-white p-3 shadow-sm">
            <div class="tab-pane fade show active" id="tabTipos">
                <table id="tablaTipos" class="table table-hover align-middle w-100">
                    <thead class="table-light">
                        <tr><th>Código</th><th>Nombre</th><th>Grupo</th><th>Alta</th><th class="text-end">Cuota directa</th><th>Estado</th><th class="text-end">Acciones</th></tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <p class="small text-muted mb-0">ACTIVIDAD la da la DDJJ del empleador; las demás se piden por solicitud. Los afiliados de los tipos con cuota directa reciben un comprobante mensual por el importe indicado.</p>
            </div>
            <div class="tab-pane fade" id="tabParentescos">
                <button type="button" class="btn btn-primary btn-sm mb-2 solo-editor d-none" id="btnNuevoParentesco"><i class="fas fa-plus me-1"></i>Nuevo parentesco</button>
                <table class="table table-sm table-hover align-middle" id="tablaParentescos">
                    <thead class="table-light"><tr><th>Código</th><th>Nombre</th><th>Inverso</th><th>Estado</th><th class="text-end"></th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="tab-pane fade" id="tabVigencias">
                <button type="button" class="btn btn-primary btn-sm mb-2 solo-editor d-none" id="btnNuevaVigencia"><i class="fas fa-plus me-1"></i>Regla por parentesco</button>
                <table class="table table-sm table-hover align-middle" id="tablaVigencias">
                    <thead class="table-light"><tr><th>Parentesco</th><th>Cubre hasta los</th><th>Con excepción hasta los</th><th>Documento de excepción</th><th>Validez</th><th>Estado</th><th class="text-end"></th></tr></thead>
                    <tbody></tbody>
                </table>
                <p class="small text-muted mb-0">La cobertura dura hasta el día anterior a cumplir la edad. Sin regla, el parentesco no tiene límite de edad.</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal tipo -->
<div class="modal fade" id="modalTipo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title fw-bold">Tipo de afiliación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form id="formTipo">
                <div class="modal-body">
                    <input type="hidden" name="afiliacion_tipo_id" id="afiliacion_tipo_id">
                    <div class="mb-3"><label class="form-label fw-semibold">Nombre</label><input type="text" class="form-control" name="nombre" id="tipo_nombre" required></div>
                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="paga_cuota_directa" id="paga_cuota_directa" value="1"><label class="form-check-label" for="paga_cuota_directa">Paga cuota directa mensual</label></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Importe de la cuota</label><input type="number" step="0.01" min="0" class="form-control" name="importe_cuota" id="importe_cuota"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal parentesco -->
<div class="modal fade" id="modalParentesco" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title fw-bold">Parentesco</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form id="formParentesco">
                <div class="modal-body">
                    <input type="hidden" name="parentesco_id" id="parentesco_id">
                    <div class="mb-3"><label class="form-label fw-semibold">Código</label><input type="text" class="form-control" name="codigo" id="par_codigo" maxlength="20" required></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Nombre</label><input type="text" class="form-control" name="nombre" id="par_nombre" maxlength="60" required></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Parentesco inverso</label><select class="form-select" name="parentesco_inverso_id" id="par_inverso"><option value="">(Ninguno)</option></select></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Estado</label><select class="form-select" name="tabla_estado_registro_id" id="par_estado"><option value="1">Activo</option><option value="2">Inactivo</option></select></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal vigencia -->
<div class="modal fade" id="modalVigencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title fw-bold">Vigencia por edad</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form id="formVigencia">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label fw-semibold">Parentesco</label><select class="form-select" name="parentesco_id" id="vig_parentesco" required></select></div>
                    <div class="row g-2 mb-3">
                        <div class="col"><label class="form-label fw-semibold">Edad máxima</label><input type="number" min="0" max="120" class="form-control" name="edad_maxima" id="vig_edad"></div>
                        <div class="col"><label class="form-label fw-semibold">Con excepción</label><input type="number" min="0" max="120" class="form-control" name="edad_maxima_excepcion" id="vig_edad_exc"></div>
                    </div>
                    <div class="mb-3"><label class="form-label fw-semibold">Documento de excepción</label><input type="text" class="form-control" name="documento_excepcion" id="vig_doc" maxlength="100"></div>
                    <div class="row g-2">
                        <div class="col"><label class="form-label fw-semibold">Validez del documento (meses)</label><input type="number" min="1" max="60" class="form-control" name="meses_validez_documento" id="vig_meses" value="12"></div>
                        <div class="col"><label class="form-label fw-semibold">Estado</label><select class="form-select" name="tabla_estado_registro_id" id="vig_estado"><option value="1">Activa</option><option value="2">Inactiva</option></select></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div>
            </form>
        </div>
    </div>
</div>

<script src="afiliaciones_config.js"></script>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
