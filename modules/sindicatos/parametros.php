<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Parámetros del Sindicato";
$currentPage = 'parametros';
$modudo_idx = 6;
$pagina_idx = 1001;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-sliders-h me-2 text-primary"></i>Parámetros del Sindicato
                    </h3>
                    <small class="text-muted">Configuración general de la entidad gremial, reglas de liquidación y canales de comunicación</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Sindicatos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Parámetros</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12 col-xl-10 mx-auto">
                    <form id="formParametros">
                        <input type="hidden" name="parametro_id" id="parametro_id" value="">

                        <!-- 1. Configuración General & Portal -->
                        <div class="card card-outline card-primary mb-4 shadow-sm">
                            <div class="card-header bg-transparent">
                                <h5 class="card-title m-0 text-primary fw-bold">
                                    <i class="fas fa-building me-2"></i>Configuración Institucional y Portal
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="portal_slug" class="form-label fw-semibold">Slug del Portal Público <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text text-muted">/registro.php?s=</span>
                                            <input type="text" class="form-control" id="portal_slug" name="portal_slug" required placeholder="ej: aecbb" maxlength="50">
                                        </div>
                                        <small class="text-muted">Identificador único para autoalta de empleadores sin exponer el ID.</small>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="convenio_id_default" class="form-label fw-semibold">Convenio CCT Predeterminado</label>
                                        <select class="form-select" id="convenio_id_default" name="convenio_id_default">
                                            <option value="">(Sin asignar)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="dias_gracia" class="form-label fw-semibold">Días de Gracia para Vencimientos</label>
                                        <input type="number" class="form-control" id="dias_gracia" name="dias_gracia" min="0" max="60" value="0">
                                    </div>

                                    <div class="col-md-6">
                                        <label for="email_avisos" class="form-label fw-semibold">Correo de Notificaciones Administrativas</label>
                                        <input type="email" class="form-control" id="email_avisos" name="email_avisos" placeholder="aportes@sindicato.org.ar">
                                        <small class="text-muted">Casilla donde el sindicato recibe avisos de altas de empleadores y nuevas afiliaciones.</small>
                                    </div>
                                    <div class="col-md-6 d-flex align-items-center mt-4">
                                        <div class="form-check form-switch ps-5">
                                            <input class="form-check-input" type="checkbox" id="aprobacion_autoalta" name="aprobacion_autoalta" value="1">
                                            <label class="form-check-label fw-semibold" for="aprobacion_autoalta">
                                                Exigir aprobación administrativa previa para autoaltas de empleadores
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Regla de Media Jornada -->
                        <div class="card card-outline card-info mb-4 shadow-sm">
                            <div class="card-header bg-transparent">
                                <h5 class="card-title m-0 text-info fw-bold">
                                    <i class="fas fa-balance-scale me-2"></i>Regla de Liquidación: Media Jornada
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="media_jornada_piso" class="form-label fw-semibold">Cálculo del Piso por Escala Salarial</label>
                                        <select class="form-select" id="media_jornada_piso" name="media_jornada_piso" required>
                                            <option value="COMPLETO">COMPLETO — Escala salarial íntegra (Comportamiento histórico AECBB)</option>
                                            <option value="PROPORCIONAL">PROPORCIONAL — Escala multiplicada por factor de jornada</option>
                                            <option value="SIN_PISO">SIN PISO — Sobre remuneración declarada sin exigencia de mínimo</option>
                                        </select>
                                        <small class="text-muted">Aplica a empleados declarados con media jornada en las DDJJ mensuales.</small>
                                    </div>
                                    <div class="col-md-3" id="bloque_factor_media_jornada">
                                        <label for="media_jornada_factor" class="form-label fw-semibold">Factor Proporcional</label>
                                        <input type="number" class="form-control" id="media_jornada_factor" name="media_jornada_factor" step="0.05" min="0.10" max="1.00" value="0.50">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Servidor de Correo Saliente (SMTP) -->
                        <div class="card card-outline card-secondary mb-4 shadow-sm">
                            <div class="card-header bg-transparent">
                                <h5 class="card-title m-0 text-secondary fw-bold">
                                    <i class="fas fa-paper-plane me-2"></i>Servidor de Correo Saliente (SMTP)
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small mb-3">Utilizado para enviar claves temporales, constancias y alertas a empleadores y estudios.</p>
                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <label for="smtp_host" class="form-label fw-semibold">Servidor Host</label>
                                        <input type="text" class="form-control" id="smtp_host" name="smtp_host" placeholder="smtp.servidor.com">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="smtp_puerto" class="form-label fw-semibold">Puerto</label>
                                        <input type="number" class="form-control" id="smtp_puerto" name="smtp_puerto" value="587">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="smtp_seguridad" class="form-label fw-semibold">Seguridad</label>
                                        <select class="form-select" id="smtp_seguridad" name="smtp_seguridad">
                                            <option value="tls">TLS / STARTTLS</option>
                                            <option value="ssl">SSL</option>
                                            <option value="none">Ninguna</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="smtp_usuario" class="form-label fw-semibold">Usuario / Casilla</label>
                                        <input type="text" class="form-control" id="smtp_usuario" name="smtp_usuario">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="smtp_clave" class="form-label fw-semibold">Contraseña</label>
                                        <input type="password" class="form-control" id="smtp_clave" name="smtp_clave" placeholder="Dejar en blanco para no cambiar">
                                        <small class="text-muted" id="smtp_clave_estado"></small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 4. Casilla de Ingreso Automático de DDJJ -->
                        <div class="card card-outline card-secondary mb-4 shadow-sm">
                            <div class="card-header bg-transparent">
                                <h5 class="card-title m-0 text-secondary fw-bold">
                                    <i class="fas fa-inbox me-2"></i>Casilla de Ingreso de DDJJ por Correo Electrónico
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small mb-3">Casilla leída por el proceso de fondo (cron) para procesar archivos F.931 o planillas adjuntas.</p>
                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <label for="correo_ingreso_host" class="form-label fw-semibold">Servidor Host</label>
                                        <input type="text" class="form-control" id="correo_ingreso_host" name="correo_ingreso_host" placeholder="imap.servidor.com">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="correo_ingreso_puerto" class="form-label fw-semibold">Puerto</label>
                                        <input type="number" class="form-control" id="correo_ingreso_puerto" name="correo_ingreso_puerto" value="993">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="correo_ingreso_protocolo" class="form-label fw-semibold">Protocolo</label>
                                        <select class="form-select" id="correo_ingreso_protocolo" name="correo_ingreso_protocolo">
                                            <option value="imap">IMAP</option>
                                            <option value="pop3">POP3</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="correo_ingreso_usuario" class="form-label fw-semibold">Usuario / Casilla</label>
                                        <input type="text" class="form-control" id="correo_ingreso_usuario" name="correo_ingreso_usuario">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="correo_ingreso_clave" class="form-label fw-semibold">Contraseña</label>
                                        <input type="password" class="form-control" id="correo_ingreso_clave" name="correo_ingreso_clave" placeholder="Dejar en blanco para no cambiar">
                                        <small class="text-muted" id="correo_ingreso_clave_estado"></small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botón Guardar -->
                        <div class="d-flex justify-content-end mb-5">
                            <button type="submit" class="btn btn-primary btn-lg px-4 shadow" id="btnGuardar">
                                <i class="fas fa-save me-2"></i>Guardar Parámetros
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="parametros.js"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
