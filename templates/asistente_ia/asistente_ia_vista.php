<?php
/**
 * Asistente IA: cuerpo de la página, común a gestion (139) y gestion_normal (639). Lo incluye asistente_ia.php
 * del módulo después de header1.php; el comportamiento está en assets/js/asistente-ia.js.
 */
?>
<style>
    body.ui-simple .asis-wrap { display: grid; grid-template-columns: 260px 1fr; gap: 1rem; min-height: calc(100vh - 220px); }
    @media (max-width: 991.98px) { body.ui-simple .asis-wrap { grid-template-columns: 1fr; } #asisLateral { display: none; } #asisLateral.abierto { display: block; } }
    #asisLateral .list-group-item { cursor: pointer; font-size: .875rem; }
    #asisLateral .list-group-item.active { font-weight: 600; }
    .asis-chat { display: flex; flex-direction: column; height: calc(100vh - 220px); min-height: 420px; }
    .asis-mensajes { flex: 1; overflow-y: auto; padding: 1rem; background: var(--bs-tertiary-bg); border-radius: .5rem; }
    .asis-msg { max-width: 85%; margin-bottom: .75rem; padding: .6rem .85rem; border-radius: .75rem; white-space: pre-wrap; word-wrap: break-word; }
    .asis-msg.usuario { margin-left: auto; background: var(--bs-primary); color: #fff; border-bottom-right-radius: .2rem; }
    .asis-msg.asistente { background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); border-bottom-left-radius: .2rem; }
    .asis-msg.error { background: var(--bs-danger-bg-subtle); color: var(--bs-danger-text-emphasis); }
    .asis-evento, .asis-herr { font-size: .8125rem; color: var(--bs-secondary-color); margin: -.25rem 0 .6rem; }
    .asis-adjunto { font-size: .8125rem; opacity: .85; display: block; margin-bottom: .25rem; }
    .asis-prop { max-width: 640px; margin-bottom: .9rem; }
    .asis-prop table { font-size: .875rem; }
    .asis-prop .asis-prop-estado { font-size: .8125rem; }
    .asis-entrada { margin-top: .75rem; }
    .asis-entrada textarea { resize: none; }
    .asis-ejemplos .btn { font-size: .8125rem; }
    .asis-pensando { color: var(--bs-secondary-color); font-size: .875rem; }
    .asis-pensando .spinner-border { width: 1rem; height: 1rem; }
</style>

<main class="app-main">
    <?php ui_encabezado('Asistente IA', 'bi bi-chat-left-text',
        'Pedile en tus palabras una factura o un remito de venta, copiar las facturas de un período o de un cliente con un porcentaje de aumento, o el alta de un cliente o proveedor (podés adjuntar la constancia de inscripción de ARCA). '
        . 'El asistente arma la propuesta y vos la confirmás: se guarda en Borrador, como si la hubieras cargado a mano.',
        ['Ventas'],
        '<button type="button" class="btn btn-primary" id="asisNueva"><i class="bi bi-plus-lg me-1"></i>Nueva conversación</button>'); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="alert alert-warning d-none" id="asisNoDisponible"></div>

            <div class="asis-wrap">
                <div id="asisLateral">
                    <div class="card h-100">
                        <div class="card-header py-2"><strong>Conversaciones</strong></div>
                        <div class="list-group list-group-flush" id="asisConversaciones"></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body asis-chat">
                        <div class="d-lg-none mb-2">
                            <button type="button" class="btn btn-light btn-sm" id="asisVerConversaciones"><i class="bi bi-list me-1"></i>Conversaciones</button>
                        </div>
                        <div class="asis-mensajes" id="asisMensajes">
                            <div id="asisBienvenida">
                                <p class="mb-2"><strong>¿Qué necesitás?</strong> Por ejemplo:</p>
                                <div class="asis-ejemplos d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-light border">Factura a Ferretería López con 10 tornillos 8 mm y 2 cajas de clavos</button>
                                    <button type="button" class="btn btn-light border">Remito a consumidor final con 3 bolsas de cemento</button>
                                    <button type="button" class="btn btn-light border">Facturar los remitos pendientes de Ferretería López</button>
                                    <button type="button" class="btn btn-light border">Copiar las facturas de octubre con un 5 % de aumento</button>
                                    <button type="button" class="btn btn-light border">Dar de alta como cliente a Juan Pérez, monotributista, de Viedma</button>
                                    <button type="button" class="btn btn-light border" data-adjuntar="1">Dar de alta este proveedor (adjunto la constancia de ARCA)</button>
                                </div>
                            </div>
                        </div>

                        <form class="asis-entrada" id="asisForm" autocomplete="off">
                            <div class="small text-muted mb-1 d-none" id="asisArchivoNombre"></div>
                            <div class="input-group">
                                <button type="button" class="btn btn-light border" id="asisAdjuntar" title="Adjuntar PDF o imagen">
                                    <i class="bi bi-paperclip"></i>
                                </button>
                                <input type="file" class="d-none" id="asisArchivo" accept="application/pdf,image/jpeg,image/png">
                                <textarea class="form-control" id="asisTexto" rows="2" maxlength="4000"
                                          placeholder="Escribí tu pedido… (Enter envía, Shift+Enter nueva línea)"></textarea>
                                <button type="submit" class="btn btn-primary" id="asisEnviar"><i class="bi bi-send"></i></button>
                            </div>
                            <div class="small text-muted mt-1" id="asisUso"></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
