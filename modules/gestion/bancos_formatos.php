<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Formatos Bancarios";
$currentPage = 'bancos_formatos';
$modudo_idx = 2;
$pagina_idx = 131; // ID en conf__paginas para Formatos Bancarios
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once ROOT_PATH . '/core/csrf.php';
?>

<main class="app-main">
    <?php ui_encabezado('Formatos Bancarios', 'bi bi-file-earmark-spreadsheet', 'Cómo viene el extracto de cada banco: en qué columnas están la fecha, la descripción y los importes, desde qué fila empiezan los movimientos y cómo se escriben las fechas. Probá el formato con un archivo real antes de importar.', ['Tesorería']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <div id="contenedor-boton-agregar" class="d-inline"></div>
                    <button type="button" class="btn btn-sm btn-outline-secondary float-end" id="btnRecargar" title="Recargar"><i class="fas fa-sync-alt"></i></button>
                </div>
                <div class="card-body p-3">
                    <table id="tablaFormatos" class="table table-striped table-hover align-middle w-100">
                        <thead class="table-light text-nowrap">
                            <tr>
                                <th>Banco</th>
                                <th>Formato</th>
                                <th class="text-center">Archivo</th>
                                <th class="ui-detalle">Columnas</th>
                                <th class="text-center">Vigencia</th>
                                <th class="text-center ui-detalle">Importaciones</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Modal Crear / Editar -->
            <div class="modal fade" id="modalFormato" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-light">
                            <h5 class="modal-title fw-bold" id="modalFormatoLabel">Formato</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <form id="formFormato" class="d-flex flex-column flex-grow-1 overflow-hidden" style="min-height:0" novalidate>
                            <div class="modal-body p-4">
                                <input type="hidden" id="formato_id" name="formato_id">
                                <div class="ui-seccion">
                                    <div class="ui-seccion-titulo">Archivo</div>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label ui-requerido" for="banco_id">Banco</label>
                                            <select class="form-select" id="banco_id" name="banco_id" required></select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label ui-requerido" for="formato">Nombre</label>
                                            <input type="text" class="form-control" id="formato" name="formato" maxlength="100" required placeholder="Movimientos web (Excel)">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label ui-requerido" for="tipo_archivo">Tipo</label>
                                            <select class="form-select" id="tipo_archivo" name="tipo_archivo">
                                                <option value="XLS">Excel .xls</option>
                                                <option value="XLSX">Excel .xlsx</option>
                                                <option value="CSV">CSV / texto</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label" for="hoja">Hoja <?= ui_ayuda('Nombre de la hoja del Excel. Vacío = la primera.') ?></label>
                                            <input type="text" class="form-control" id="hoja" name="hoja" maxlength="60">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label ui-requerido" for="fila_inicio">Primera fila con movimientos</label>
                                            <input type="number" class="form-control" id="fila_inicio" name="fila_inicio" min="1" max="1000" value="2" required>
                                        </div>
                                        <div class="col-md-3 solo-csv">
                                            <label class="form-label" for="delimitador">Separador de campos</label>
                                            <select class="form-select" id="delimitador" name="delimitador">
                                                <option value="">Detectar</option><option value=";">Punto y coma (;)</option>
                                                <option value=",">Coma (,)</option><option value="|">Barra (|)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 solo-csv">
                                            <label class="form-label" for="codificacion">Codificación</label>
                                            <select class="form-select" id="codificacion" name="codificacion">
                                                <option value="UTF-8">UTF-8</option><option value="WINDOWS-1252">Windows (ANSI)</option><option value="ISO-8859-1">ISO-8859-1</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="ui-seccion">
                                    <div class="ui-seccion-titulo">Columnas <?= ui_ayuda('Letra de la columna del archivo (A, B, C...). Con importe con signo alcanza la columna Importe; si el banco separa débitos y créditos, indicá las dos.') ?></div>
                                    <div class="row g-3">
                                        <div class="col-6 col-md-3"><label class="form-label ui-requerido" for="col_fecha">Fecha</label><input class="form-control text-uppercase col-letra" id="col_fecha" name="col_fecha" maxlength="2" required></div>
                                        <div class="col-6 col-md-3"><label class="form-label ui-requerido" for="col_descripcion">Descripción</label><input class="form-control text-uppercase col-letra" id="col_descripcion" name="col_descripcion" maxlength="2" required></div>
                                        <div class="col-6 col-md-3 modo-signo"><label class="form-label ui-requerido" for="col_importe">Importe</label><input class="form-control text-uppercase col-letra" id="col_importe" name="col_importe" maxlength="2"></div>
                                        <div class="col-6 col-md-3 modo-dc"><label class="form-label ui-requerido" for="col_debito">Débito</label><input class="form-control text-uppercase col-letra" id="col_debito" name="col_debito" maxlength="2"></div>
                                        <div class="col-6 col-md-3 modo-dc"><label class="form-label ui-requerido" for="col_credito">Crédito</label><input class="form-control text-uppercase col-letra" id="col_credito" name="col_credito" maxlength="2"></div>
                                        <div class="col-6 col-md-3"><label class="form-label" for="col_saldo">Saldo</label><input class="form-control text-uppercase col-letra" id="col_saldo" name="col_saldo" maxlength="2"></div>
                                        <div class="col-6 col-md-3"><label class="form-label" for="col_referencia">Referencia</label><input class="form-control text-uppercase col-letra" id="col_referencia" name="col_referencia" maxlength="2"></div>
                                        <div class="col-6 col-md-3"><label class="form-label" for="col_fecha_valor">Fecha valor</label><input class="form-control text-uppercase col-letra" id="col_fecha_valor" name="col_fecha_valor" maxlength="2"></div>
                                    </div>
                                </div>

                                <div class="ui-seccion">
                                    <div class="ui-seccion-titulo">Fechas e importes</div>
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label ui-requerido" for="formato_fecha">Formato de fecha <?= ui_ayuda('d = día, m = mes en número, M = mes en letras (oct), Y = año. Ejemplos: d/m/Y para 21/10/2026, d-M-Y para 21-oct-2026.') ?></label>
                                            <input type="text" class="form-control font-monospace" id="formato_fecha" name="formato_fecha" value="d/m/Y" maxlength="20" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label" for="idioma_fecha">Meses en letras</label>
                                            <select class="form-select" id="idioma_fecha" name="idioma_fecha"><option value="ES">Español</option><option value="EN">Inglés</option></select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label" for="orden_archivo">Orden de los movimientos</label>
                                            <select class="form-select" id="orden_archivo" name="orden_archivo">
                                                <option value="ASC">Del más viejo al más nuevo</option><option value="DESC">Del más nuevo al más viejo</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label" for="modo_importe">Importes</label>
                                            <select class="form-select" id="modo_importe" name="modo_importe">
                                                <option value="SIGNO">Una columna con signo (− = débito)</option><option value="DEBITO_CREDITO">Débito y crédito separados</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label" for="sep_decimal">Separador decimal</label>
                                            <select class="form-select" id="sep_decimal" name="sep_decimal"><option value=",">Coma (1.234,56)</option><option value=".">Punto (1,234.56)</option></select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label" for="sep_miles">Separador de miles</label>
                                            <select class="form-select" id="sep_miles" name="sep_miles"><option value=".">Punto</option><option value=",">Coma</option><option value="">Ninguno</option></select>
                                        </div>
                                        <div class="col-md-6 d-flex align-items-end">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="saldo_anterior" name="saldo_anterior" value="1">
                                                <label class="form-check-label" for="saldo_anterior">El saldo de cada fila es el anterior al movimiento <?= ui_ayuda('Lo habitual es que el saldo sea el que queda después del movimiento.') ?></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <details class="ui-mas-opciones">
                                    <summary>Número de cuenta, referencia y vigencia</summary>
                                    <div class="row g-3 mt-1">
                                        <div class="col-md-2"><label class="form-label" for="cuenta_fila">Fila del n° de cuenta</label><input type="number" class="form-control" id="cuenta_fila" name="cuenta_fila" min="1" max="1000"></div>
                                        <div class="col-md-2"><label class="form-label" for="cuenta_columna">Columna</label><input class="form-control text-uppercase col-letra" id="cuenta_columna" name="cuenta_columna" maxlength="2" placeholder="Cualquiera"></div>
                                        <div class="col-md-4"><label class="form-label" for="cuenta_regex">Expresión del n° de cuenta <?= ui_ayuda('Expresión regular; el grupo entre paréntesis es el número. Ejemplo: Cuenta:\\s*([0-9\\-/]+)') ?></label><input class="form-control font-monospace" id="cuenta_regex" name="cuenta_regex" maxlength="100"></div>
                                        <div class="col-md-4"><label class="form-label" for="referencia_regex">Referencia en la descripción <?= ui_ayuda('Extrae el CUIT o el número de cheque de la descripción para conciliar. Ejemplo para un CUIT: \\(?(\\d{11})\\)?') ?></label><input class="form-control font-monospace" id="referencia_regex" name="referencia_regex" maxlength="200"></div>
                                        <div class="col-md-3"><label class="form-label ui-requerido" for="f_desde">Vale desde</label><input type="date" class="form-control" id="f_desde" name="f_desde" required></div>
                                        <div class="col-md-3"><label class="form-label" for="f_hasta">Hasta</label><input type="date" class="form-control" id="f_hasta" name="f_hasta"></div>
                                    </div>
                                </details>

                                <div class="ui-seccion mt-3" id="seccionProbar">
                                    <div class="ui-seccion-titulo">Probar con un archivo <?= ui_ayuda('Lee el archivo con los datos del formulario, sin guardar nada.') ?></div>
                                    <div class="input-group">
                                        <input type="file" class="form-control" id="archivoPrueba" accept=".xls,.xlsx,.csv,.txt">
                                        <button type="button" class="btn btn-outline-primary" id="btnProbar"><i class="bi bi-play-fill me-1"></i>Probar</button>
                                    </div>
                                    <div id="resultadoPrueba" class="mt-3"></div>
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
    <script src="bancos_formatos.js?v=<?= filemtime(__DIR__ . '/bancos_formatos.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
