<?php
/**
 * Vista: Padrón de Clientes y Matriz Territorial
 * Módulo 4: Estudio Contable (estudio_clientes.php)
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Padrón de Clientes";
$currentPage = 'estudio_clientes';
$modudo_idx = 4;
$pagina_idx = 402;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold">
                        <i class="fas fa-user-tie me-2 text-primary"></i>Padrón de Clientes
                    </h3>
                    <small class="text-muted">Administración integral de contribuyentes, matriz territorial e impuestos asignados</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Estudio Contable</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Padrón de Clientes</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Barra Superior de Acciones y Filtros -->
            <div class="card card-outline card-primary mb-3 shadow-sm">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Tipo de Cliente</label>
                            <select id="filtroTipoCliente" class="form-select form-select-sm">
                                <option value="">-- Todos los tipos --</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Condición IVA</label>
                            <select id="filtroCondicionIva" class="form-select form-select-sm">
                                <option value="">-- Todas las condiciones --</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-4">
                            <label class="form-label form-label-sm mb-1 text-muted">Terminación CUIT</label>
                            <select id="filtroTerminacion" class="form-select form-select-sm">
                                <option value="">Todas (0-9)</option>
                                <?php for ($d = 0; $d <= 9; $d++): ?>
                                    <option value="<?= $d ?>">Termina en <?= $d ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-4">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="">Todos</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-4 text-end mt-auto pb-1">
                            <button type="button" class="btn btn-sm btn-primary w-100" id="btnNuevoCliente" data-funcion="agregar">
                                <i class="fas fa-plus-circle me-1"></i>Nuevo Cliente
                            </button>
                            <button type="button" class="btn btn-sm btn-success w-100 mt-1" id="btnAltaAsistente" data-funcion="alta_asistente" title="Crea la empresa en el ERP, la vincula al estudio y la copia desde una plantilla">
                                <i class="fas fa-magic me-1"></i>Alta con asistente
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grilla Principal de Clientes -->
            <div class="card shadow-sm">
                <div class="card-header bg-white py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-primary"><i class="fas fa-users me-2"></i>Padrón de Clientes</h6>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Recargar tabla">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnExportarCopy" title="Copiar al portapapeles">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success" id="btnExportarExcel" title="Exportar a Excel">
                                <i class="fas fa-file-excel"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="btnExportarPDF" title="Exportar a PDF">
                                <i class="fas fa-file-pdf"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnExportarCSV" title="Exportar a CSV">
                                <i class="fas fa-file-csv"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnExportarPrint" title="Imprimir tabla">
                                <i class="fas fa-print"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="tablaClientes" class="table table-hover table-striped align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th>Razón Social</th>
                                    <th>CUIT</th>
                                    <th>Condición IVA</th>
                                    <th>IIBB / Jurisdicciones</th>
                                    <th>Responsable (R)</th>
                                    <th>Supervisor (A)</th>
                                    <th>Estado</th>
                                    <th style="width: 140px;" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dinámico vía DataTables -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal Alta / Modificación Cliente -->
<div class="modal fade" id="modalCliente" tabindex="-1" aria-labelledby="modalClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="formCliente" novalidate>
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6" id="modalClienteLabel">
                        <i class="fas fa-user-plus me-2"></i>Registrar Cliente de Estudio
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modal_cliente_id" name="cliente_id" value="0">

                    <!-- Alta desde la constancia de inscripción de ARCA (solo cliente nuevo) -->
                    <div id="bloqueConstanciaCliente" class="d-flex align-items-center gap-2 mb-3 p-2 border rounded bg-light">
                        <i class="fas fa-file-pdf text-danger fs-5"></i>
                        <span class="small flex-grow-1">¿Tenés la constancia de CUIT de ARCA? Subila y se completan los datos.
                            <?= ui_ayuda('Se lee con la IA del estudio (Configuración > Empresas: su clave o la de la principal). PDF, también escaneado, o foto de hasta 5 MB. No graba nada: revisá y guardá.') ?></span>
                        <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" id="btnLeerConstanciaCliente">
                            <i class="fas fa-robot me-1"></i>Leer constancia
                        </button>
                    </div>

                    <!-- Empresa ERP Vinculada (Primero para permitir precarga inmediata) -->
                    <div class="card bg-light border-primary-subtle shadow-none mb-3">
                        <div class="card-body p-2">
                            <label for="modal_empresa_vinculada_id" class="form-label form-label-sm fw-bold text-primary mb-1 d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-building me-1"></i>Empresa ERP Vinculada</span>
                                <span class="badge bg-primary-subtle text-primary border border-primary"><i class="fas fa-magic me-1"></i>Precarga Automática</span>
                            </label>
                            <select class="form-select form-select-sm border-primary" id="modal_empresa_vinculada_id" name="empresa_vinculada_id">
                                <option value="">-- No vinculada / Cliente externo (O seleccione para precargar datos) --</option>
                            </select>
                            <div class="form-text fs-8 text-muted mt-1">
                                <i class="fas fa-info-circle me-1 text-info"></i>Al seleccionar una empresa existente del ERP, se precargan automáticamente Razón Social, CUIT, Condición IVA, Régimen IIBB, Jurisdicción y datos de nómina. Dejar sin seleccionar si es un cliente externo.
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-1">
                        <div class="col-md-4 mb-2">
                            <label for="modal_nivel_servicio" class="form-label form-label-sm fw-semibold">Servicio del estudio</label>
                            <select class="form-select form-select-sm" id="modal_nivel_servicio" name="nivel_servicio">
                                <option value="PADRON">Padrón (vencimientos, tareas, honorarios)</option>
                                <option value="FISCAL">Fiscal (comprobantes, IVA, IIBB)</option>
                                <option value="CONTABLE">Contable (además contabilidad)</option>
                                <option value="GESTION">Gestión completa</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label for="modal_origen_datos" class="form-label form-label-sm fw-semibold">Origen de los datos</label>
                            <select class="form-select form-select-sm" id="modal_origen_datos" name="origen_datos">
                                <option value="LOCAL">Este ERP</option>
                                <option value="REMOTO">Servidor propio del cliente</option>
                                <option value="EXTERNO">Otro software</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2 d-none" id="grupo_remoto_url">
                            <label for="modal_remoto_url" class="form-label form-label-sm fw-semibold">URL del ERP del cliente <span class="text-danger">*</span></label>
                            <input type="url" class="form-control form-control-sm" id="modal_remoto_url" name="remoto_url" maxlength="255" placeholder="https://erp.cliente.com.ar">
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-8 mb-2">
                            <label for="modal_razon_social" class="form-label form-label-sm fw-semibold">Razón Social / Nombre Completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="modal_razon_social" name="razon_social" required maxlength="150" placeholder="Ej: Transportes del Sur S.R.L.">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label for="modal_cliente_tipo_id" class="form-label form-label-sm fw-semibold">Tipo de Cliente <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="modal_cliente_tipo_id" name="cliente_tipo_id" required>
                                <option value="">Seleccione...</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-4 mb-2">
                            <label for="modal_cuit" class="form-label form-label-sm fw-semibold">CUIT <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="modal_cuit" name="cuit" required maxlength="13" placeholder="20-12345678-9">
                            <div class="form-text fs-8 text-primary" id="cuit_helper">Terminación calculada: -</div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label for="modal_condicion_iva_id" class="form-label form-label-sm fw-semibold">Condición IVA <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="modal_condicion_iva_id" name="condicion_iva_id" required>
                                <option value="">Seleccione...</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label for="modal_mes_cierre_ejercicio" class="form-label form-label-sm fw-semibold">Mes Cierre Ejercicio</label>
                            <select class="form-select form-select-sm" id="modal_mes_cierre_ejercicio" name="mes_cierre_ejercicio">
                                <option value="1">Enero</option>
                                <option value="2">Febrero</option>
                                <option value="3">Marzo</option>
                                <option value="4">Abril</option>
                                <option value="5">Mayo</option>
                                <option value="6">Junio</option>
                                <option value="7">Julio</option>
                                <option value="8">Agosto</option>
                                <option value="9">Septiembre</option>
                                <option value="10">Octubre</option>
                                <option value="11">Noviembre</option>
                                <option value="12" selected>Diciembre</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6 mb-2">
                            <label for="modal_ingresos_brutos_tipo" class="form-label form-label-sm fw-semibold">Régimen IIBB</label>
                            <select class="form-select form-select-sm" id="modal_ingresos_brutos_tipo" name="ingresos_brutos_tipo">
                                <option value="LOCAL">Local / Directo</option>
                                <option value="CONVENIO_MULTILATERAL">Convenio Multilateral (CM05)</option>
                                <option value="EXENTO">Exento</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label for="modal_jurisdiccion_sede_id" class="form-label form-label-sm fw-semibold">Jurisdicción Sede</label>
                            <select class="form-select form-select-sm" id="modal_jurisdiccion_sede_id" name="jurisdiccion_sede_id">
                                <option value="">Seleccione...</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-4 mb-2">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="modal_es_empleador" name="es_empleador" value="1">
                                <label class="form-check-label form-label-sm fw-semibold" for="modal_es_empleador">
                                    Es Empleador (F.931)
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label for="modal_cantidad_empleados" class="form-label form-label-sm fw-semibold">Cantidad Empleados</label>
                            <input type="number" class="form-control form-control-sm" id="modal_cantidad_empleados" name="cantidad_empleados" min="0" value="0">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label for="modal_tabla_estado_registro_id" class="form-label form-label-sm fw-semibold">Estado</label>
                            <select class="form-select form-select-sm" id="modal_tabla_estado_registro_id" name="tabla_estado_registro_id">
                                <option value="1">Activo</option>
                                <option value="2">Inactivo</option>
                                <option value="16">Suspendido</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6 mb-2">
                            <label for="modal_usuario_r_defecto_id" class="form-label form-label-sm fw-semibold">Responsable Operativo (R)</label>
                            <select class="form-select form-select-sm" id="modal_usuario_r_defecto_id" name="usuario_r_defecto_id">
                                <option value="">-- Sin asignar --</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label for="modal_usuario_a_defecto_id" class="form-label form-label-sm fw-semibold">Supervisor / Accountable (A)</label>
                            <select class="form-select form-select-sm" id="modal_usuario_a_defecto_id" name="usuario_a_defecto_id">
                                <option value="">-- Sin asignar --</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label for="modal_observaciones" class="form-label form-label-sm fw-semibold">Observaciones / Notas de Estudio</label>
                        <textarea class="form-control form-control-sm" id="modal_observaciones" name="observaciones" rows="2" placeholder="Notas sobre claves fiscales, vencimientos especiales..."></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnGuardarCliente">
                        <i class="fas fa-save me-1"></i>Guardar Cliente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Gestión Matriz Territorial (Jurisdicciones IIBB) -->
<div class="modal fade" id="modalJurisdicciones" tabindex="-1" aria-labelledby="modalJurisdiccionesLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white py-2">
                <h5 class="modal-title fs-6" id="modalJurisdiccionesLabel">
                    <i class="fas fa-map-marked-alt me-2"></i>Matriz Territorial IIBB - <span id="mj_cliente_nombre"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Formulario de incorporación rápida de jurisdicción -->
                <form id="formJurisdiccion" data-funcion="editar" class="border rounded p-2 mb-3 bg-light">
                    <input type="hidden" id="mj_cliente_id" name="cliente_id">
                    <h6 class="fw-bold fs-7 text-primary mb-2">Asignar Jurisdicción Provincial</h6>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label form-label-sm mb-1 fw-semibold">Jurisdicción <span class="text-danger">*</span></label>
                            <select id="mj_jurisdiccion_id" name="jurisdiccion_id" class="form-select form-select-sm" required>
                                <option value="">Seleccione provincia...</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm mb-1 fw-semibold">Tipo Inscripción</label>
                            <select id="mj_tipo_inscripcion" name="tipo_inscripcion" class="form-select form-select-sm">
                                <option value="EXTRAÑA_JURISDICCION">Extraña Jurisdicción</option>
                                <option value="SEDE_PRINCIPAL">Sede Principal</option>
                                <option value="REGIMEN_SIMPLIFICADO">Régimen Simplificado</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm mb-1 fw-semibold">Coef. CM05</label>
                            <input type="number" step="0.0001" min="0" max="1" class="form-control form-control-sm" id="mj_coeficiente_cm05" name="coeficiente_cm05" value="0.0000">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-sm btn-success w-100">
                                <i class="fas fa-plus me-1"></i>Incorporar
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Listado de jurisdicciones asignadas -->
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Código</th>
                                <th>Jurisdicción</th>
                                <th>Tipo</th>
                                <th>Coeficiente CM05</th>
                                <th>Alta</th>
                                <th style="width: 50px;">Baja</th>
                            </tr>
                        </thead>
                        <tbody id="mj_lista_body">
                            <!-- Dinámico -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Modal Asistente: Alta de cliente (empresa ERP + vinculación + plantilla) -->
<div class="modal fade" id="modalAlta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white py-2">
                <h5 class="modal-title fs-6"><i class="fas fa-magic me-2"></i>Alta de cliente con asistente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formAlta" autocomplete="off">
                    <!-- Datos de la constancia de inscripción de ARCA para la empresa que se crea (vacíos = los del estudio) -->
                    <input type="hidden" name="localidad_id" id="al_localidad_id" value="">
                    <input type="hidden" name="domicilio" id="al_domicilio" value="">
                    <input type="hidden" name="inicio_actividades" id="al_inicio_actividades" value="">
                    <div class="d-flex align-items-center gap-2 mb-2 p-2 border rounded bg-light">
                        <i class="fas fa-file-pdf text-danger fs-5"></i>
                        <span class="small flex-grow-1" id="al_constancia_texto">¿Tenés la constancia de CUIT de ARCA? Subila y se completan los datos.
                            <?= ui_ayuda('Se lee con la IA del estudio (Configuración > Empresas: su clave o la de la principal). Además de razón social, CUIT, condición frente al IVA y cierre de ejercicio, la empresa se crea con el domicilio fiscal y la localidad de la constancia.') ?></span>
                        <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" id="btnLeerConstanciaAlta">
                            <i class="fas fa-robot me-1"></i>Leer constancia
                        </button>
                    </div>
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label form-label-sm fw-bold">Servicio que recibe el cliente</label>
                            <select class="form-select form-select-sm" name="nivel_servicio" id="al_nivel">
                                <option value="PADRON">Padrón: vencimientos, tareas y honorarios (sin datos contables)</option>
                                <option value="FISCAL" selected>Fiscal: comprobantes, IVA, IIBB y percepciones</option>
                                <option value="CONTABLE">Contable: además contabilidad y balances</option>
                                <option value="GESTION">Gestión completa: administración tercerizada</option>
                            </select>
                            <small class="text-muted" id="al_nivel_ayuda"></small>
                        </div>
                        <div class="col-12" id="al_bloque_origen">
                            <label class="form-label form-label-sm fw-bold">¿Dónde se llevan los datos del cliente?</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="origen_datos" id="al_origen_local" value="LOCAL" checked>
                                <label class="btn btn-sm btn-outline-success" for="al_origen_local"><i class="fas fa-server me-1"></i>En este ERP</label>
                                <input type="radio" class="btn-check" name="origen_datos" id="al_origen_remoto" value="REMOTO">
                                <label class="btn btn-sm btn-outline-secondary" for="al_origen_remoto"><i class="fas fa-cloud me-1"></i>Tiene su propio servidor</label>
                                <input type="radio" class="btn-check" name="origen_datos" id="al_origen_externo" value="EXTERNO">
                                <label class="btn btn-sm btn-outline-secondary" for="al_origen_externo"><i class="fas fa-file-import me-1"></i>Otro software</label>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label form-label-sm fw-semibold">Razón social <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="razon_social" maxlength="150" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold">CUIT <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="cuit" maxlength="13" placeholder="30-12345678-9" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold">Tipo de cliente <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" name="cliente_tipo_id" id="al_tipo" required></select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label form-label-sm fw-semibold">Condición frente al IVA <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" name="condicion_iva_id" id="al_cond" required></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm fw-semibold">Cierre de ejercicio</label>
                            <select class="form-select form-select-sm" name="mes_cierre_ejercicio" id="al_cierre"></select>
                        </div>

                        <div class="col-12 al-local">
                            <hr class="my-2">
                            <small class="text-muted">Empresa en este ERP</small>
                        </div>
                        <div class="col-md-6 al-local">
                            <label class="form-label form-label-sm fw-semibold">Plantilla de configuración</label>
                            <div class="input-group input-group-sm">
                                <select class="form-select form-select-sm" name="plantilla_empresa_id" id="al_plantilla" title="Se copia todo lo que copia &quot;Copiar Datos Iniciales&quot; del Inicio de empresa"></select>
                                <button type="button" class="btn btn-outline-secondary" id="btnRegistrarPlantilla" title="Registrar una empresa vinculada como plantilla"><i class="fas fa-plus"></i></button>
                            </div>
                        </div>
                        <div class="col-md-6 al-local">
                            <label class="form-label form-label-sm fw-semibold">Acceso del estudio a la empresa</label>
                            <select class="form-select form-select-sm" name="modo_acceso">
                                <option value="LECTURA">Solo lectura (auditoría)</option>
                                <option value="LECTURA_ESCRITURA">Lectura y escritura (el estudio la gestiona)</option>
                            </select>
                        </div>
                        <div class="col-md-6 al-local">
                            <label class="form-label form-label-sm fw-semibold">Régimen de Ingresos Brutos</label>
                            <select class="form-select form-select-sm" name="iibb_regimen_id" id="al_iibb"></select>
                        </div>
                        <div class="col-md-6 al-local">
                            <label class="form-label form-label-sm fw-semibold">Jurisdicción sede</label>
                            <select class="form-select form-select-sm" name="jurisdiccion_sede_id" id="al_sede"></select>
                        </div>

                        <div class="col-12 al-remoto d-none">
                            <hr class="my-2">
                            <label class="form-label form-label-sm fw-semibold">URL del ERP del cliente (https) <span class="text-danger">*</span></label>
                            <input type="url" class="form-control form-control-sm" name="remoto_url" maxlength="255" placeholder="https://erp.cliente.com.ar">
                            <small class="text-muted">Por ahora solo se registra el cliente y su URL. La lectura de sus datos se habilita cuando esté disponible la sincronización.</small>
                        </div>
                    </div>
                </form>
                <div id="alResultado" class="mt-3 d-none"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-sm btn-success" id="btnAltaEjecutar"><i class="fas fa-check me-1"></i>Crear cliente</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="<?= asset_local('js/constancia-cuit-ia.js') ?>?v=<?= filemtime(ROOT_PATH . '/assets/js/constancia-cuit-ia.js') ?>"></script>
<script src="estudio_clientes.js"></script>
<script src="estudio_clientes_alta.js"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
