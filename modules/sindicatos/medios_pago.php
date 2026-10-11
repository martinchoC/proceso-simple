<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Medios de Pago y Canales Bancarios";
$currentPage = 'medios_pago';
$modudo_idx = 6;
$pagina_idx = 1006;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-money-check-alt me-2 text-primary"></i>Medios de Pago y Canales
                    </h3>
                    <small class="text-muted">Administración de bocas de cobro bancarias, credenciales de publicación y rendiciones</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Sindicatos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Medios de Pago</li>
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
                            <button type="button" class="btn btn-outline-secondary btn-sm float-end" id="btnRecargar" title="Recargar lista">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tablaMediosPago" class="table table-hover table-striped align-middle" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Orden</th>
                                            <th>Código</th>
                                            <th>Nombre</th>
                                            <th>Driver</th>
                                            <th>Ambiente</th>
                                            <th>Publica Deuda</th>
                                            <th>Rendición</th>
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

    <!-- Modal Medio de Pago -->
    <div class="modal fade" id="modalMedioPago" tabindex="-1" aria-labelledby="modalMedioPagoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalMedioPagoLabel">
                        <i class="fas fa-credit-card me-2 text-primary"></i>Medio de Pago
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formMedioPago">
                    <div class="modal-body">
                        <input type="hidden" name="medio_pago_id" id="medio_pago_id" value="">

                        <ul class="nav nav-tabs mb-3" id="tabMedioPago" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active fw-semibold" id="tab-gral" data-bs-toggle="tab" data-bs-target="#panel-gral" type="button" role="tab">Identificación</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-semibold" id="tab-api" data-bs-toggle="tab" data-bs-target="#panel-api" type="button" role="tab">Publicación de Deuda</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-semibold" id="tab-rend" data-bs-toggle="tab" data-bs-target="#panel-rend" type="button" role="tab">Rendición</button>
                            </li>
                        </ul>

                        <div class="tab-content" id="tabMedioPagoContent">
                            <!-- Solapa 1: General -->
                            <div class="tab-pane fade show active" id="panel-gral" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Código <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control text-uppercase" id="codigo" name="codigo" required maxlength="30" placeholder="BNA, LINK, etc.">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Nombre Descriptivo <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="150">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Orden Volante</label>
                                        <input type="number" class="form-control" id="orden" name="orden" value="0">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Driver de Integración</label>
                                        <select class="form-select" id="driver" name="driver" required>
                                            <option value="MANUAL">MANUAL (Ventanilla / Transferencia)</option>
                                            <option value="BNA_BARRAS">BNA_BARRAS (Banco Nación)</option>
                                            <option value="RED_LINK">RED_LINK (Red Link SOAP)</option>
                                            <option value="INTERBANKING">INTERBANKING (OAuth2 / REST)</option>
                                            <option value="BAPRO">BAPRO (Banco Provincia)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Ambiente</label>
                                        <select class="form-select" id="ambiente" name="ambiente" required>
                                            <option value="PRUEBA">PRUEBA (Homologación / Sandbox)</option>
                                            <option value="PRODUCCION">PRODUCCIÓN</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Prefijo Código de Barras</label>
                                        <input type="text" class="form-control" id="barras_prefijo" name="barras_prefijo" placeholder="ej: 869955555">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Cuenta Tesorería (ERP)</label>
                                        <input type="text" class="form-control" id="tesoreria_cuenta_codigo" name="tesoreria_cuenta_codigo" placeholder="BCO_NACION_CC">
                                        <small class="text-muted">Código de cuenta del Módulo 5 donde se imputan las acreditaciones.</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Solapa 2: Publicación API -->
                            <div class="tab-pane fade" id="panel-api" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-md-4 d-flex align-items-center">
                                        <div class="form-check form-switch ps-5">
                                            <input class="form-check-input" type="checkbox" id="publica_deuda" name="publica_deuda" value="1">
                                            <label class="form-check-label fw-semibold" for="publica_deuda">Publicar deudas activamente</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Ente / Acuerdo / Convenio</label>
                                        <input type="text" class="form-control" id="ente_acuerdo" name="ente_acuerdo">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Client ID / Recaudador</label>
                                        <input type="text" class="form-control" id="client_id" name="client_id">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">URL de Servicio / API</label>
                                        <input type="url" class="form-control" id="url_servicio" name="url_servicio" placeholder="https://api...">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">URL de Autenticación (OAuth2)</label>
                                        <input type="url" class="form-control" id="url_autenticacion" name="url_autenticacion" placeholder="https://auth...">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Usuario de Servicio</label>
                                        <input type="text" class="form-control" id="usuario" name="usuario">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Clave de Servicio</label>
                                        <input type="password" class="form-control" id="clave" name="clave" placeholder="Dejar en blanco para no modificar">
                                    </div>

                                    <div class="col-md-12">
                                        <label class="form-label fw-semibold">Client Secret (Cifrado)</label>
                                        <input type="password" class="form-control" id="client_secret" name="client_secret" placeholder="Dejar en blanco para no modificar">
                                    </div>
                                </div>
                            </div>

                            <!-- Solapa 3: Rendición SFTP/Archivo -->
                            <div class="tab-pane fade" id="panel-rend" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Modo de Rendición</label>
                                        <select class="form-select" id="rendicion_modo" name="rendicion_modo">
                                            <option value="ARCHIVO_MANUAL">Carga manual de archivo TXT/Excel</option>
                                            <option value="SFTP">Descarga automática vía SFTP</option>
                                            <option value="API">Consulta automática por API</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label fw-semibold">Servidor Host SFTP</label>
                                        <input type="text" class="form-control" id="sftp_host" name="sftp_host">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Puerto</label>
                                        <input type="number" class="form-control" id="sftp_puerto" name="sftp_puerto" value="22">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Usuario SFTP</label>
                                        <input type="text" class="form-control" id="sftp_usuario" name="sftp_usuario">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Clave SFTP</label>
                                        <input type="password" class="form-control" id="sftp_clave" name="sftp_clave" placeholder="Dejar en blanco para no modificar">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Carpeta Remota</label>
                                        <input type="text" class="form-control" id="sftp_carpeta_remota" name="sftp_carpeta_remota" placeholder="/rendiciones/">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Patrón de Nombre de Archivo</label>
                                        <input type="text" class="form-control" id="sftp_patron_archivo" name="sftp_patron_archivo" placeholder="REND_*.TXT">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4" id="btnGuardarMP">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script src="medios_pago.js"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
