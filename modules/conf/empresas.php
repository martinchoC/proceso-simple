<?php
// Configuración de la página
$pageTitle = "Gestión de Empresas"; // Corregido título
$currentPage = 'modulos';
$modudo_idx = 1;
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
$ruta_assets = "../../";
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<link rel="stylesheet" href="../../assets/css/buttons.dataTables.min.css">
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Empresas</h3></div>
                </div>
        </div>
    </div>
    
    <div class="app-content">
        <div class="container-fluid">
            <div class="content-wrapper">
                <section class="content">
                    <div class="container-fluid">                      
                        <div class="row">
                            <div class="col-12">
                                <div class="card">                                
                                    <div class="card-body">
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nueva Empresa</button>
                                        <table id="tablaempresas" class="table table-striped table-bordered" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Razon Social</th>
                                                    <th>Tipo Doc.</th>
                                                    <th>Nro.Doc</th>
                                                    <th>Domicilio</th>
                                                    <th>Localidad</th>
                                                    <th>Estado</th>
                                                    <th>Acciones</th>
                                                </tr>
                                                </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="modalempresa" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">Empresa</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form id="formempresa">
            <input type="hidden" id="empresa_id" name="empresa_id" />
            <div class="row g-3">
                <div class="col-md-6">
                    <label>Razon social</label>
                    <input type="text" class="form-control" id="empresa" name="empresa" required/>
                </div>
                <div class="col-md-6">
                    <label>Documento Tipo</label>
                    <input type="text" class="form-control" id="documento_tipo_id" name="documento_tipo_id" />
                </div>
                <div class="col-md-6">
                    <label>Documento</label>
                    <input type="text" class="form-control" id="documento_numero" name="documento_numero" required/>
                </div>
                <div class="col-md-6">
                    <label>Telefono</label>
                    <input type="text" class="form-control" id="telefono" name="telefono" />
                </div>
                <div class="col-md-6">
                    <label>Domicilio</label>
                    <input type="text" class="form-control" id="domicilio" name="domicilio" />
                </div>
                <div class="col-md-6">
                    <label>Localidad</label>
                    <select class="form-control" id="localidad_id" name="localidad_id" required>
                        <option value="">Cargando...</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Email / Sesión</label>
                    <input type="text" class="form-control" id="email" name="email" />
                </div>
                <div class="col-md-6">
                    <label>Base Conf (ID)</label>
                    <input type="number" class="form-control" id="base_conf" name="base_conf" />
                </div>
                <div class="col-md-6">
                    <label>Estado Registro ID</label>
                    <input type="number" class="form-control" id="estado_registro_id" name="estado_registro_id" value="1" />
                </div>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" id="btnGuardar" class="btn btn-success">Guardar</button>
      </div>
    </div>
  </div>
</div>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>

<script src="../../assets/js/jquery.min.js"></script>
<script src="../../assets/js/bootstrap.bundle.min.js"></script>

<script src="../../assets/js/datatables.min.js"></script>

<script src="../../assets/js/dataTables.buttons.min.js"></script>
<script src="../../assets/js/jszip.min.js"></script>
<script src="../../assets/js/pdfmake.min.js"></script>
<script src="../../assets/js/vfs_fonts.js"></script>
<script src="../../assets/js/buttons.html5.min.js"></script>

<script src="../../assets/js/sweetalert2.all.min.js"></script>

<script src="../../assets/js/empresas.js"></script>

</body>
</html>