<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ABM de Empresas</title>

  <link rel="stylesheet" href="assets/css/adminlte.min.css" />
  <link rel="stylesheet" href="assets/css/all.min.css">
  <link rel="stylesheet" href="assets/css/datatables.min.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
  
  <div class="content-wrapper p-4">
    <section class="content">
      <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h3 class="mb-0">Empresas</h3>
          <button class="btn btn-primary" onclick="abrirEmpresaNueva()">+ Nueva empresa</button>
        </div>

        <div class="card card-outline card-info">
          <div class="card-body">
            <table id="tablaEmpresas" class="table table-bordered table-striped" style="width:100%">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Empresa</th>
                  <th>CUIT/DNI</th>
                  <th>Email</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>

<div class="modal fade" id="modalEmpresa" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary">
        <h5 class="modal-title text-white">Empresa</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="formEmpresa">
          <input type="hidden" name="empresa_id" id="empresa_id">
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Empresa</label>
              <input type="text" name="empresa" id="empresa" class="form-control" required>
            </div>
            <div class="form-group col-md-3">
              <label>Tipo Doc.</label>
              <select name="documento_tipo_id" id="documento_tipo_id" class="form-control">
              </select>
            </div>
            <div class="form-group col-md-3">
              <label>CUIT/DNI</label>
              <input type="text" name="documento_numero" id="documento_numero" class="form-control">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Teléfono</label>
              <input type="text" name="telefono" id="telefono" class="form-control">
            </div>
            <div class="form-group col-md-4">
              <label>Email</label>
              <input type="email" name="email" id="email" class="form-control">
            </div>
            <div class="form-group col-md-4">
              <label>Localidad</label>
              <select name="localidad_id" id="localidad_id" class="form-control"></select>                          
          </div>
          </div> <div class="form-group">
            <label>Domicilio</label>
            <input type="text" name="domicilio" id="domicilio" class="form-control">
          </div>
          <div class="form-group">
            <label>Base de datos de configuración</label>
            <input type="text" name="base_conf" id="base_conf" class="form-control">
          </div>
        </form>
        <hr>
        <h5>Módulos asignados</h5>
        <table class="table table-bordered" id="tablaModulosEnEdicion">
          <thead>
            <tr>
              <th>Módulo</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="$('#modalEmpresa').modal('hide')">Cerrar</button>
        <button type="button" class="btn btn-primary" onclick="guardarEmpresa()">Guardar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalAsignarModulos" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary">
        <h5 class="modal-title text-white">Asignar módulos a empresa</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="empresa_id_modulo" value="">
        <div class="form-group">
          <label>Módulo</label>
          <select id="modulo_id_seleccionado" class="form-control"></select>
        </div>
        <button class="btn btn-success mb-3" onclick="asignarModuloEmpresa()">Asignar módulo</button>
        <hr>
        <table class="table table-bordered" id="tablaModulosAsignados">
          <thead>
            <tr>
              <th>Módulo</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/adminlte.min.js"></script>
<script src="assets/js/datatables.min.js"></script>
<script src="assets/js/empresas.js"></script>

</body>
</html>