<?php
/**
 * Vista común de Libros Societarios (Gestión 140, Gestión Normal 640, Estudio Contable 424).
 * La incluye el libros_societarios.php de cada módulo después de header1.php, con:
 *   $lsoc_modo     'empresa' | 'estudio'
 *   $lsoc_seccion  sección del menú para las migas ('Contabilidad', 'Gestión de Clientes')
 * El JS es assets/js/libros-societarios.js (global LibrosSoc); el endpoint, libros_societarios_ajax.php del módulo.
 */
$lsoc_estudio = ($lsoc_modo ?? 'empresa') === 'estudio';

$acciones = '<div class="d-flex gap-2 align-items-center flex-wrap">';
if ($lsoc_estudio) {
    $acciones .= '<select id="lsocCliente" class="form-select form-select-sm" style="min-width: 240px; max-width: 320px;" aria-label="Cliente">'
               . '<option value="">Todos los clientes</option></select>';
}
$acciones .= '<button type="button" class="btn btn-sm btn-outline-secondary" id="btnLsocNuevoLibro" data-funcion="agregar"><i class="bi bi-book me-1"></i>Nuevo libro</button>'
           . '<button type="button" class="btn btn-sm btn-primary" id="btnLsocNuevaActa" data-funcion="agregar"><i class="fas fa-plus me-1"></i>Nueva acta</button>'
           . '</div>';
?>
<main class="app-main">
    <?php ui_encabezado('Libros societarios', 'bi bi-book-fill',
        'Libros rubricados (actas de asamblea, de directorio, de reunión de socios, registros de acciones y de asistencia) y los actos que se transcriben en ellos. '
        . 'Un acta se carga en borrador con su orden del día, lo resuelto y quiénes participaron; al pasarla al libro se le da número y folios. '
        . 'Si trata temas que se inscriben (autoridades, capital, estatuto, sede) se sigue el trámite ante el registro. '
        . 'Avisa mandatos por vencer y la asamblea que aprueba los estados contables (4 meses desde el cierre).'
        . ($lsoc_estudio ? ' Cada libro y acta es de un cliente del estudio.' : ''),
        [$lsoc_seccion ?? 'Contabilidad'], $acciones); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="row g-3 mb-3">
                <div class="col-6 col-lg-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">Libros en uso</div><div class="ui-kpi-valor" id="kpiLibros">-</div></div></div>
                <div class="col-6 col-lg-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">Actas sin transcribir</div><div class="ui-kpi-valor" id="kpiBorradores">-</div></div></div>
                <div class="col-6 col-lg-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">Inscripciones abiertas</div><div class="ui-kpi-valor" id="kpiInscripciones">-</div></div></div>
                <div class="col-6 col-lg-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">Avisos</div><div class="ui-kpi-valor" id="kpiAlertas">-</div></div></div>
            </div>

            <?php if ($lsoc_estudio): ?>
            <div class="alert d-none py-2 d-flex align-items-center gap-2 flex-wrap" id="lsocFuente">
                <i class="bi bi-diagram-3"></i><span id="lsocFuenteTexto" class="flex-grow-1"></span>
                <button type="button" class="btn btn-sm btn-primary d-none" id="btnLsocPasar" data-funcion="editar">
                    <i class="fas fa-share me-1"></i>Pasar a la empresa</button>
            </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 mb-3 d-none" id="lsocAlertasCard">
                <div class="card-header bg-white py-2 fw-semibold"><i class="fas fa-bell me-1 text-warning"></i>Avisos</div>
                <ul class="list-group list-group-flush" id="lsocAlertas"></ul>
            </div>

            <ul class="nav nav-tabs ui-solapas mb-3" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#solapaActas" type="button">Actas y actos</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#solapaLibros" type="button">Libros</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#solapaAutoridades" type="button">Autoridades</button></li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="solapaActas">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-2 ui-barra">
                            <div class="ui-filtros d-flex flex-wrap gap-2 align-items-center">
                                <select class="form-select form-select-sm ui-f-media" id="fActaTipo"><option value="">Todos los actos</option></select>
                                <select class="form-select form-select-sm ui-f-media" id="fActaMateria"><option value="">Todos los temas</option></select>
                                <select class="form-select form-select-sm ui-f-media" id="fActaEstado">
                                    <option value="">Todos los estados</option>
                                    <option value="BORRADOR">Borrador (sin transcribir)</option>
                                    <option value="TRANSCRIPTA">Transcriptas</option>
                                    <option value="INSC_PENDIENTE">Inscripción abierta</option>
                                </select>
                                <input type="date" class="form-control form-control-sm ui-f-fecha" id="fActaDesde" title="Desde">
                                <input type="date" class="form-control form-control-sm ui-f-fecha" id="fActaHasta" title="Hasta">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="fActaAnuladas">
                                    <label class="form-check-label small" for="fActaAnuladas">Anuladas</label>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <table id="tablaActas" class="table table-sm table-hover align-middle w-100">
                                <thead class="table-light text-nowrap">
                                    <tr>
                                        <th>Fecha</th>
                                        <?php if ($lsoc_estudio): ?><th>Cliente</th><?php endif; ?>
                                        <th>Acto</th>
                                        <th>Libro / N°</th>
                                        <th>Estado</th>
                                        <th>Inscripción</th>
                                        <th class="ui-detalle">Temas</th>
                                        <th class="ui-detalle">Folios</th>
                                        <th class="ui-detalle">Resumen</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="solapaLibros">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-2 ui-barra">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="fLibrosInactivos">
                                <label class="form-check-label small" for="fLibrosInactivos">Dados de baja</label>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <table id="tablaLibros" class="table table-sm table-hover align-middle w-100">
                                <thead class="table-light text-nowrap">
                                    <tr>
                                        <?php if ($lsoc_estudio): ?><th>Cliente</th><?php endif; ?>
                                        <th>Libro</th>
                                        <th>Rúbrica</th>
                                        <th>Folios usados</th>
                                        <th>Última acta</th>
                                        <th>Estado</th>
                                        <th class="ui-detalle">Medio</th>
                                        <th class="ui-detalle">Ubicación</th>
                                        <th class="ui-detalle">Observaciones</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="solapaAutoridades">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-3">
                            <table id="tablaAutoridades" class="table table-sm table-hover align-middle w-100">
                                <thead class="table-light text-nowrap">
                                    <tr>
                                        <?php if ($lsoc_estudio): ?><th>Cliente</th><?php endif; ?>
                                        <th>Cargo</th>
                                        <th>Nombre</th>
                                        <th>Documento</th>
                                        <th>Designado el</th>
                                        <th>Mandato hasta</th>
                                        <th class="ui-detalle">Inscripción</th>
                                        <th class="text-center">Acciones</th>
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

    <!-- Modal: libro -->
    <div class="modal fade" id="modalLibro" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <form id="formLibro" autocomplete="off" novalidate>
                    <div class="modal-header bg-light">
                        <h5 class="modal-title fw-bold" id="modalLibroTitulo">Libro</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="libro_id" id="lb_id" value="0">
                        <div class="ui-seccion">
                            <div class="row g-2">
                                <?php if ($lsoc_estudio): ?>
                                <div class="col-12"><label class="form-label ui-requerido" for="lb_cliente">Cliente</label>
                                    <select class="form-select form-select-sm" name="cliente_id" id="lb_cliente"></select></div>
                                <?php endif; ?>
                                <div class="col-md-8"><label class="form-label ui-requerido" for="lb_tipo">Tipo de libro</label>
                                    <select class="form-select form-select-sm" name="tipo_libro" id="lb_tipo"></select></div>
                                <div class="col-md-4"><label class="form-label ui-requerido" for="lb_numero">Número de libro</label>
                                    <input type="number" min="1" class="form-control form-control-sm" name="numero_libro" id="lb_numero" value="1"></div>
                                <div class="col-md-8"><label class="form-label" for="lb_denominacion">Nombre <?= ui_ayuda('Opcional: si se deja vacío se usa el tipo de libro.') ?></label>
                                    <input type="text" class="form-control form-control-sm" name="denominacion" id="lb_denominacion" maxlength="120"></div>
                                <div class="col-md-4"><label class="form-label" for="lb_medio">Medio</label>
                                    <select class="form-select form-select-sm" name="medio" id="lb_medio">
                                        <option value="PAPEL">Papel</option><option value="DIGITAL">Digital</option></select></div>
                            </div>
                        </div>
                        <div class="ui-seccion">
                            <div class="ui-seccion-titulo">Rúbrica</div>
                            <div class="row g-2">
                                <div class="col-md-5"><label class="form-label" for="lb_organismo">Organismo</label>
                                    <input type="text" class="form-control form-control-sm" name="organismo" id="lb_organismo" maxlength="80" list="lsocOrganismos" placeholder="IGJ, DPPJ, Registro Público..."></div>
                                <div class="col-md-4"><label class="form-label" for="lb_rubrica">N° de rúbrica</label>
                                    <input type="text" class="form-control form-control-sm" name="rubrica_numero" id="lb_rubrica" maxlength="60"></div>
                                <div class="col-md-3"><label class="form-label" for="lb_rubrica_fecha">Fecha</label>
                                    <input type="date" class="form-control form-control-sm" name="rubrica_fecha" id="lb_rubrica_fecha"></div>
                                <div class="col-md-3 lsoc-papel"><label class="form-label ui-requerido" for="lb_fdesde">Primer folio</label>
                                    <input type="number" min="1" class="form-control form-control-sm" name="folio_desde" id="lb_fdesde" value="1"></div>
                                <div class="col-md-3 lsoc-papel"><label class="form-label ui-requerido" for="lb_fhasta">Último folio</label>
                                    <input type="number" min="1" class="form-control form-control-sm" name="folio_hasta" id="lb_fhasta"></div>
                                <div class="col-md-3"><label class="form-label" for="lb_inicio">En uso desde</label>
                                    <input type="date" class="form-control form-control-sm" name="f_inicio_uso" id="lb_inicio"></div>
                                <div class="col-md-3"><label class="form-label" for="lb_estado">Estado</label>
                                    <select class="form-select form-select-sm" name="estado_libro" id="lb_estado"></select></div>
                            </div>
                        </div>
                        <details class="ui-mas-opciones">
                            <summary>Más datos</summary>
                            <div class="row g-2 mt-1">
                                <div class="col-md-4"><label class="form-label" for="lb_cierre">Fecha de cierre</label>
                                    <input type="date" class="form-control form-control-sm" name="f_cierre" id="lb_cierre"></div>
                                <div class="col-md-8"><label class="form-label" for="lb_ubicacion">Dónde se guarda</label>
                                    <input type="text" class="form-control form-control-sm" name="ubicacion" id="lb_ubicacion" maxlength="150"></div>
                                <div class="col-12"><label class="form-label" for="lb_obs">Observaciones</label>
                                    <textarea class="form-control form-control-sm" name="observaciones" id="lb_obs" rows="2"></textarea></div>
                            </div>
                        </details>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <datalist id="lsocOrganismos">
        <option value="IGJ"><option value="DPPJ Río Negro"><option value="DPPJ Buenos Aires"><option value="Registro Público"><option value="INAES"><option value="Escribano público">
    </datalist>

    <!-- Modal: acta -->
    <div class="modal fade" id="modalActa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <form id="formActa" autocomplete="off" novalidate class="d-flex flex-column h-100 overflow-hidden">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title fw-bold" id="modalActaTitulo">Acta</h5>
                        <span class="ms-2" id="actaEstadoBadge"></span>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="acta_id" id="ac_id" value="0">
                        <div class="alert alert-info py-2 small d-none" id="actaAvisoTranscripta">
                            El acta ya está transcripta al libro: solo se pueden cambiar las observaciones. Para corregir el contenido, usá "Volver a borrador".
                        </div>
                        <div class="ui-seccion">
                            <div class="ui-seccion-titulo">El acto</div>
                            <div class="row g-2">
                                <?php if ($lsoc_estudio): ?>
                                <div class="col-md-6"><label class="form-label ui-requerido" for="ac_cliente">Cliente</label>
                                    <select class="form-select form-select-sm lsoc-contenido" name="cliente_id" id="ac_cliente"></select></div>
                                <?php endif; ?>
                                <div class="col-md-<?= $lsoc_estudio ? '6' : '5' ?>"><label class="form-label ui-requerido" for="ac_tipo">Tipo de acto</label>
                                    <select class="form-select form-select-sm lsoc-contenido" name="tipo_acto" id="ac_tipo"></select></div>
                                <div class="col-md-3 col-6"><label class="form-label ui-requerido" for="ac_fecha">Fecha</label>
                                    <input type="date" class="form-control form-control-sm lsoc-contenido" name="f_acto" id="ac_fecha"></div>
                                <div class="col-md-2 col-6"><label class="form-label" for="ac_hora">Hora</label>
                                    <input type="time" class="form-control form-control-sm lsoc-contenido" name="hora_acto" id="ac_hora"></div>
                                <div class="col-md-<?= $lsoc_estudio ? '7' : '2' ?>"><label class="form-label" for="ac_lugar">Lugar</label>
                                    <input type="text" class="form-control form-control-sm lsoc-contenido" name="lugar" id="ac_lugar" maxlength="150" placeholder="Sede social"></div>
                                <div class="col-md-3 col-6"><label class="form-label" for="ac_convocatoria">Convocatoria</label>
                                    <input type="date" class="form-control form-control-sm lsoc-contenido" name="f_convocatoria" id="ac_convocatoria"></div>
                                <div class="col-md-2 col-6"><label class="form-label" for="ac_quorum">Quórum % <?= ui_ayuda('Porcentaje del capital o de los votos presentes.') ?></label>
                                    <input type="number" min="0" max="100" step="0.01" class="form-control form-control-sm lsoc-contenido" name="quorum_pct" id="ac_quorum"></div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <div class="form-check mb-1">
                                        <input class="form-check-input lsoc-contenido" type="checkbox" name="es_unanime" id="ac_unanime" value="1">
                                        <label class="form-check-label" for="ac_unanime">Unánime <?= ui_ayuda('Asamblea unánime: con el 100% del capital presente y decisiones por unanimidad, sin convocatoria previa (art. 237 LGS).') ?></label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="ui-seccion">
                            <div class="ui-seccion-titulo">Temas tratados</div>
                            <div class="row g-1" id="ac_materias"></div>
                            <div class="row g-2 mt-1">
                                <div class="col-md-4 d-none" id="ac_bloque_ejercicio"><label class="form-label ui-requerido" for="ac_ejercicio">Cierre del ejercicio que se aprueba</label>
                                    <input type="date" class="form-control form-control-sm lsoc-contenido" name="ejercicio_cierre" id="ac_ejercicio"></div>
                                <div class="col-md-4 d-none" id="ac_bloque_mandato"><label class="form-label" for="ac_mandato">Mandato hasta <?= ui_ayuda('Fecha en que vence el mandato de las autoridades designadas: la página avisa 90 días antes.') ?></label>
                                    <input type="date" class="form-control form-control-sm lsoc-contenido" name="mandato_hasta" id="ac_mandato"></div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="form-check mb-1">
                                        <input class="form-check-input lsoc-contenido" type="checkbox" name="requiere_inscripcion" id="ac_requiere" value="1">
                                        <label class="form-check-label" for="ac_requiere">Se inscribe en el registro</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="ui-seccion">
                            <div class="row g-2">
                                <div class="col-md-6"><label class="form-label" for="ac_odd">Orden del día</label>
                                    <textarea class="form-control form-control-sm lsoc-contenido" name="orden_del_dia" id="ac_odd" rows="5"></textarea></div>
                                <div class="col-md-6"><label class="form-label" for="ac_res">Lo resuelto</label>
                                    <textarea class="form-control form-control-sm lsoc-contenido" name="resoluciones" id="ac_res" rows="5"></textarea></div>
                            </div>
                        </div>

                        <div class="ui-seccion">
                            <div class="ui-seccion-titulo d-flex align-items-center">Participantes y firmantes
                                <?= ui_ayuda('Asistentes, firmantes y autoridades. Si en el acto se designa a alguien, completá el cargo designado: con eso se arma la solapa Autoridades.') ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto lsoc-contenido" id="btnAgregarPersona"><i class="fas fa-user-plus me-1"></i>Agregar</button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0" data-ui-acciones="no">
                                    <thead class="table-light"><tr>
                                        <th style="min-width:180px">Nombre</th><th style="min-width:120px">CUIT / DNI</th><th style="min-width:140px">Carácter</th>
                                        <th style="min-width:140px">Cargo designado</th><th style="min-width:90px">Acciones / cuotas</th><th style="min-width:70px">%</th>
                                        <th class="text-center">Firma</th><th></th></tr></thead>
                                    <tbody id="ac_personas"></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ui-seccion">
                            <label class="form-label" for="ac_obs">Observaciones</label>
                            <textarea class="form-control form-control-sm" name="observaciones" id="ac_obs" rows="2"></textarea>
                        </div>

                        <div class="ui-seccion d-none" id="ac_bloque_libro">
                            <div class="ui-seccion-titulo">En el libro</div>
                            <div class="small" id="ac_libro_texto"></div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-outline-danger me-auto d-none" id="btnActaAnular" data-funcion="inhabilitar"><i class="fas fa-ban me-1"></i>Anular</button>
                        <button type="button" class="btn btn-outline-secondary d-none" id="btnActaBorrador" data-funcion="editar"><i class="fas fa-undo me-1"></i>Volver a borrador</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary" id="btnActaGuardar"><i class="fas fa-save me-1"></i>Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: transcribir -->
    <div class="modal fade" id="modalTranscribir" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <form id="formTranscribir" autocomplete="off" novalidate>
                    <div class="modal-header bg-light">
                        <h5 class="modal-title fw-bold"><i class="bi bi-journal-text me-2"></i>Pasar al libro</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="acta_id" id="tr_acta">
                        <div class="small text-muted mb-2" id="tr_titulo"></div>
                        <div class="row g-2">
                            <div class="col-12"><label class="form-label ui-requerido" for="tr_libro">Libro</label>
                                <select class="form-select form-select-sm" name="libro_id" id="tr_libro"></select></div>
                            <div class="col-6"><label class="form-label ui-requerido" for="tr_numero">N° de acta</label>
                                <input type="number" min="1" class="form-control form-control-sm" name="numero_acta" id="tr_numero"></div>
                            <div class="col-6"><label class="form-label" for="tr_fecha">Fecha de transcripción</label>
                                <input type="date" class="form-control form-control-sm" name="f_transcripcion" id="tr_fecha"></div>
                            <div class="col-6 lsoc-tr-papel"><label class="form-label ui-requerido" for="tr_fdesde">Folio desde</label>
                                <input type="number" min="1" class="form-control form-control-sm" name="folio_desde" id="tr_fdesde"></div>
                            <div class="col-6 lsoc-tr-papel"><label class="form-label ui-requerido" for="tr_fhasta">Folio hasta</label>
                                <input type="number" min="1" class="form-control form-control-sm" name="folio_hasta" id="tr_fhasta"></div>
                            <div class="col-12 small text-muted" id="tr_info"></div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i>Pasar al libro</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: inscripción -->
    <div class="modal fade" id="modalInscripcion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <form id="formInscripcion" autocomplete="off" novalidate>
                    <div class="modal-header bg-light">
                        <h5 class="modal-title fw-bold"><i class="fas fa-stamp me-2"></i>Inscripción registral</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="acta_id" id="in_acta">
                        <div class="small text-muted mb-2" id="in_titulo"></div>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label ui-requerido" for="in_estado">Estado</label>
                                <select class="form-select form-select-sm" name="inscripcion_estado" id="in_estado"></select></div>
                            <div class="col-6"><label class="form-label" for="in_organismo">Organismo</label>
                                <input type="text" class="form-control form-control-sm" name="inscripcion_organismo" id="in_organismo" maxlength="80" list="lsocOrganismos"></div>
                            <div class="col-6"><label class="form-label" for="in_tramite">N° de trámite</label>
                                <input type="text" class="form-control form-control-sm" name="inscripcion_tramite" id="in_tramite" maxlength="60"></div>
                            <div class="col-6"><label class="form-label" for="in_presentacion">Presentado el</label>
                                <input type="date" class="form-control form-control-sm" name="f_presentacion" id="in_presentacion"></div>
                            <div class="col-6"><label class="form-label" for="in_inscripcion">Inscripto el</label>
                                <input type="date" class="form-control form-control-sm" name="f_inscripcion" id="in_inscripcion"></div>
                            <div class="col-6"><label class="form-label" for="in_datos">Datos de inscripción <?= ui_ayuda('Número correlativo, libro y tomo, o la referencia que dé el registro.') ?></label>
                                <input type="text" class="form-control form-control-sm" name="inscripcion_datos" id="in_datos" maxlength="150"></div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <input type="file" id="lsocArchivo" accept=".pdf,.jpg,.jpeg,.png" class="d-none">
</main>
