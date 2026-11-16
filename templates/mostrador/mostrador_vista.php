<?php
/**
 * Vista común del Mostrador (doc 29): la incluye modules/<gestion|gestion_normal>/mostrador.php después de header1.php.
 * El JS (assets/js/mostrador.js) maneja todo contra mostrador_ajax.php: lector de códigos (teclado + Enter, o cámara
 * con BarcodeDetector), cajas y unidades, totales del servidor, cliente opcional, medios de cobro y factura.
 */
require_once ROOT_PATH . '/core/ui.php';
ui_encabezado('Mostrador', 'fas fa-cash-register', 'Venta rápida: escaneá o buscá los productos, cobrá y emití la factura en el momento.', ['Ventas']);
?>
<style>
  .vmos-grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr); gap: 1rem; }
  @media (max-width: 991px) { .vmos-grid { grid-template-columns: 1fr; } }
  .vmos-lector { font-size: 1.25rem; min-height: 52px; }
  .vmos-total { font-size: 2rem; font-weight: 700; }
  .vmos-resultado { cursor: pointer; }
  .vmos-cant { width: 5.5rem; }
  .vmos-medio-fila { display: grid; grid-template-columns: 1fr 1fr 7rem auto; gap: .4rem; margin-bottom: .4rem; }
</style>
<div class="vmos-grid" id="vmos">
  <section class="card">
    <div class="card-body">
      <div class="d-flex gap-2 mb-2">
        <input type="text" class="form-control vmos-lector" data-vmos-lector placeholder="Escaneá o escribí código, nombre o marca" autocomplete="off" autofocus>
        <button type="button" class="btn btn-outline-secondary" data-vmos-camara title="Leer con la cámara"><i class="fas fa-camera"></i></button>
      </div>
      <video data-vmos-video playsinline muted class="w-100 rounded mb-2" style="max-height:40vh; display:none"></video>
      <div class="list-group mb-3" data-vmos-resultados></div>
      <table class="table table-sm align-middle">
        <thead><tr><th>Producto</th><th class="text-center">Cantidad</th><th class="text-end">Importe</th><th></th></tr></thead>
        <tbody data-vmos-lineas><tr><td colspan="4" class="text-muted">Sin productos.</td></tr></tbody>
      </table>
    </div>
  </section>
  <aside class="card">
    <div class="card-body">
      <label class="form-label">Punto de venta</label>
      <select class="form-select mb-2" data-vmos-pv></select>
      <label class="form-label">Cliente</label>
      <div class="input-group mb-1">
        <input type="text" class="form-control" data-vmos-cliente-q placeholder="Consumidor final (buscar cliente…)">
        <button type="button" class="btn btn-outline-secondary" data-vmos-cliente-quitar title="Consumidor final">&times;</button>
      </div>
      <div class="list-group mb-2" data-vmos-clientes></div>
      <input type="text" class="form-control mb-3" data-vmos-cf-nombre placeholder="Nombre (opcional)" maxlength="100">
      <div class="d-flex justify-content-between"><span>Neto</span><span data-vmos-neto>$ 0,00</span></div>
      <div class="d-flex justify-content-between"><span>IVA</span><span data-vmos-iva>$ 0,00</span></div>
      <div class="d-flex justify-content-between align-items-end mb-3"><span>Total</span><span class="vmos-total" data-vmos-total>$ 0,00</span></div>
      <div data-vmos-cobro>
        <label class="form-label">Cobro</label>
        <div data-vmos-medios></div>
        <button type="button" class="btn btn-sm btn-link px-0" data-vmos-medio-agregar>+ Otro medio</button>
        <div class="d-flex justify-content-between"><span>Vuelto</span><strong data-vmos-vuelto>$ 0,00</strong></div>
      </div>
      <button type="button" class="btn btn-primary btn-lg w-100 mt-3" data-vmos-facturar data-funcion="facturar">Cobrar y facturar</button>
      <div class="mt-3" data-vmos-resultado></div>
    </div>
  </aside>
</div>
