<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Tipos de Obligación";
$currentPage = 'obligaciones_tipos';
$modudo_idx = 2;
$pagina_idx = 136; // ID en conf__paginas para Tipos de Obligación
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once ROOT_PATH . '/core/csrf.php';
?>

<main class="app-main">
    <?php ui_encabezado('Tipos de Obligación', 'bi bi-tags', 'Clasifican las obligaciones de pago: a quién se le paga (empleado, proveedor u organismo), con qué comprobante y contra qué cuentas contables. Los de liquidaciones de impuestos los genera el sistema: de esos solo se cambian el nombre y las cuentas.', ['Tesorería']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom ui-barra">
                    <div id="contenedor-boton-agregar" class="d-inline"></div>
                </div>
                <div class="card-body p-3">
                    <table id="tablaTipos" class="table table-striped table-hover align-middle w-100">
                        <thead class="table-light text-nowrap">
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Clase</th>
                                <th>Beneficiario</th>
                                <th>Comprobante</th>
                                <th>Cuenta del gasto</th>
                                <th>Cuenta del pasivo</th>
                                <th class="text-center ui-detalle">Carga</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <div class="modal fade" id="modalTipo" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-light">
                            <h5 class="modal-title fw-bold" id="modalTipoLabel">Tipo de obligación</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <form id="formTipo" novalidate>
                            <div class="modal-body p-4">
                                <input type="hidden" id="obligacion_tipo_id" name="obligacion_tipo_id">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label ui-requerido" for="codigo">Código <?= ui_ayuda('El que se usa en la columna "tipo" de la importación. Mayúsculas, sin espacios.') ?></label>
                                        <input type="text" class="form-control text-uppercase" id="codigo" name="codigo" maxlength="20" required>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label ui-requerido" for="nombre">Nombre</label>
                                        <input type="text" class="form-control" id="nombre" name="nombre" maxlength="100" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="clase">Clase <?= ui_ayuda('Anticipo: la orden de pago la cancela pero el importe queda como saldo a favor del beneficiario, para descontarlo después de su sueldo o de sus facturas.') ?></label>
                                        <select class="form-select" id="clase" name="clase">
                                            <option value="OBLIGACION">Deuda a pagar</option>
                                            <option value="ANTICIPO">Anticipo</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="rol_entidad_codigo">Beneficiario</label>
                                        <select class="form-select" id="rol_entidad_codigo" name="rol_entidad_codigo"></select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="comprobante_tipo_codigo">Comprobante</label>
                                        <select class="form-select" id="comprobante_tipo_codigo" name="comprobante_tipo_codigo">
                                            <option value="OBL">Obligación de Pago</option>
                                            <option value="I_LIQ">Liquidación Impositiva</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="cont_cuenta_debe_id">Cuenta del gasto <?= ui_ayuda('Debe del asiento al confirmar la obligación (vacía = Otros Gastos Generales). En los anticipos, la cuenta donde queda el anticipo: conviene la misma que la deuda que va a cancelar (p. ej. Sueldos a pagar).') ?></label>
                                        <select class="form-select" id="cont_cuenta_debe_id" name="cont_cuenta_debe_id"></select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="cont_cuenta_pasivo_id">Cuenta del pasivo <?= ui_ayuda('Haber del asiento al confirmar y Debe de la orden de pago (vacía = la cuenta del rol del beneficiario).') ?></label>
                                        <select class="form-select" id="cont_cuenta_pasivo_id" name="cont_cuenta_pasivo_id"></select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label" for="orden">Orden</label>
                                        <input type="number" class="form-control" id="orden" name="orden" min="0" max="9999" value="100">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer bg-light py-2">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary" id="btnGuardar"><i class="fas fa-save me-1"></i>Guardar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
    <script>
        window.PAGINA_ID = <?= (int)$pagina_idx ?>;
        window.BCO_CSRF = <?= json_encode(csrf_token()) ?>;
    </script>
    <script src="<?= asset_local('js/bancos-comun.js') ?>?v=<?= filemtime(ROOT_PATH . '/assets/js/bancos-comun.js') ?>"></script>
    <script src="obligaciones_tipos.js?v=<?= filemtime(__DIR__ . '/obligaciones_tipos.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
