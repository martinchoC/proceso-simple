<?php
$pageTitle = "Gestión de Entidades Comerciales";
$currentPage = 'paginas';
$modudo_idx = 2;
$empresa_idx = 2;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Entidades Comerciales</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Entidades Comerciales</li>
                    </ol>
                </div>
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
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nueva Entidad</button>
                                        <table id="tablaEntidades" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Nombre Fiscal</th>
                                                    <th>Nombre Fantasía</th>
                                                    <th>CUIT</th>
                                                    <th>Tipo</th>
                                                    <th>Rol</th>
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

            <!-- Modal Principal -->
            <div class="modal fade" id="modalEntidad" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Entidad Comercial</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <ul class="nav nav-tabs" id="entidadTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="datos-tab" data-bs-toggle="tab" data-bs-target="#datos" type="button" role="tab">Datos Principales</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="sucursales-tab" data-bs-toggle="tab" data-bs-target="#sucursales" type="button" role="tab">Sucursales</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="condiciones-tab" data-bs-toggle="tab" data-bs-target="#condiciones" type="button" role="tab">Condiciones Fiscales</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="roles-tab" data-bs-toggle="tab" data-bs-target="#roles" type="button" role="tab">Roles</button>
                                </li>
                            </ul>
                            
                            <div class="tab-content p-3" id="entidadTabsContent">
                                <!-- Pestaña Datos Principales -->
                                <div class="tab-pane fade show active" id="datos" role="tabpanel">
                                    <form id="formEntidad">
                                        <input type="hidden" id="entidad_id" name="entidad_id" />
                                        
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label>Nombre Fiscal *</label>
                                                <input type="text" class="form-control" id="nombre_fiscal" name="nombre_fiscal" required maxlength="255"/>
                                                <div class="invalid-feedback">El nombre fiscal es obligatorio</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label>Nombre Fantasía</label>
                                                <input type="text" class="form-control" id="nombre_fantasia" name="nombre_fantasia" maxlength="255"/>
                                            </div>
                                            <div class="col-md-4">
                                                <label>Tipo de Entidad</label>
                                                <select class="form-control" id="entidad_tipo_id" name="entidad_tipo_id">
                                                    <option value="">Seleccionar...</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label>CUIT *</label>
                                                <input type="text" class="form-control" id="cuit" name="cuit" required maxlength="20"/>
                                                <div class="invalid-feedback">El CUIT es obligatorio</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label>Estado</label>
                                                <select class="form-control" id="tabla_estado_registro_id" name="tabla_estado_registro_id">
                                                    <option value="1">Activo</option>
                                                    <option value="0">Inactivo</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label>Sitio Web</label>
                                                <input type="url" class="form-control" id="sitio_web" name="sitio_web" maxlength="150"/>
                                            </div>
                                            <div class="col-md-6">
                                                <label>Localidad</label>
                                                <select class="form-control" id="localidad_id" name="localidad_id">
                                                    <option value="">Seleccionar...</option>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <label>Domicilio Legal</label>
                                                <input type="text" class="form-control" id="domicilio_legal" name="domicilio_legal" maxlength="150"/>
                                            </div>
                                            <div class="col-12">
                                                <label>Observaciones</label>
                                                <textarea class="form-control" id="observaciones" name="observaciones" rows="3" maxlength="500"></textarea>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                
                                <!-- Pestaña Sucursales -->
                                <div class="tab-pane fade" id="sucursales" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6>Gestión de Sucursales</h6>
                                        <button class="btn btn-sm btn-primary" id="btnNuevaSucursal">Nueva Sucursal</button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped" id="tablaSucursales">
                                            <thead>
                                                <tr>
                                                    <th>Nombre</th>
                                                    <th>Dirección</th>
                                                    <th>Localidad</th>
                                                    <th>Teléfono</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tbodySucursales">
                                                <!-- Las sucursales se cargarán dinámicamente -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- Pestaña Condiciones Fiscales -->
                                <div class="tab-pane fade" id="condiciones" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6>Historial de Condiciones Fiscales</h6>
                                        <button class="btn btn-sm btn-primary" id="btnNuevaCondicion">Nueva Condición</button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped" id="tablaCondiciones">
                                            <thead>
                                                <tr>
                                                    <th>Condición Fiscal</th>
                                                    <th>Fecha Desde</th>
                                                    <th>Fecha Hasta</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tbodyCondiciones">
                                                <!-- Las condiciones se cargarán dinámicamente -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- Pestaña Roles -->
                                <div class="tab-pane fade" id="roles" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6>Roles de la Entidad</h6>
                                        <button class="btn btn-sm btn-primary" id="btnNuevoRol">Nuevo Rol</button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped" id="tablaRoles">
                                            <thead>
                                                <tr>
                                                    <th>Rol</th>
                                                    <th>Fecha Alta</th>
                                                    <th>Fecha Baja</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tbodyRoles">
                                                <!-- Los roles se cargarán dinámicamente -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button id="btnGuardar" class="btn btn-success">Guardar Entidad</button>
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para Sucursales -->
            <div class="modal fade" id="modalSucursal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Gestión de Sucursal</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formSucursal">
                                <input type="hidden" id="sucursal_id" name="sucursal_id" />
                                <input type="hidden" id="entidad_id_sucursal" name="entidad_id" />
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label>Nombre Sucursal *</label>
                                        <input type="text" class="form-control" id="sucursal_nombre" name="sucursal_nombre" required maxlength="150"/>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Teléfono</label>
                                        <input type="text" class="form-control" id="sucursal_telefono" name="sucursal_telefono" maxlength="50"/>
                                    </div>
                                    <div class="col-12">
                                        <label>Dirección</label>
                                        <input type="text" class="form-control" id="sucursal_direccion" name="sucursal_direccion" maxlength="255"/>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Localidad</label>
                                        <select class="form-control" id="sucursal_localidad_id" name="localidad_id">
                                            <option value="">Seleccionar...</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Email</label>
                                        <input type="email" class="form-control" id="sucursal_email" name="sucursal_email" maxlength="150"/>
                                    </div>
                                    <div class="col-12">
                                        <label>Persona de Contacto</label>
                                        <input type="text" class="form-control" id="sucursal_contacto" name="sucursal_contacto" maxlength="100"/>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-success" id="btnGuardarSucursal">Guardar</button>
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para Condiciones Fiscales -->
            <div class="modal fade" id="modalCondicion" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Condición Fiscal</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formCondicion">
                                <input type="hidden" id="entidad_condicion_fiscal_id" name="entidad_condicion_fiscal_id" />
                                <input type="hidden" id="entidad_id_condicion" name="entidad_id" />
                                
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label>Condición Fiscal *</label>
                                        <select class="form-control" id="condicion_fiscal_id" name="condicion_fiscal_id" required>
                                            <option value="">Seleccionar...</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Fecha Desde *</label>
                                        <input type="date" class="form-control" id="f_desde" name="f_desde" required/>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Fecha Hasta</label>
                                        <input type="date" class="form-control" id="f_hasta" name="f_hasta"/>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-success" id="btnGuardarCondicion">Guardar</button>
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para Roles -->
            <div class="modal fade" id="modalRol" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Rol de Entidad</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formRol">
                                <input type="hidden" id="entidad_rol_id" name="entidad_rol_id" />
                                <input type="hidden" id="entidad_id_rol" name="entidad_id" />
                                
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label>Rol *</label>
                                        <select class="form-control" id="rol_entidad_id" name="rol_entidad_id" required>
                                            <option value="">Seleccionar...</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Fecha Alta *</label>
                                        <input type="date" class="form-control" id="f_alta" name="f_alta" required/>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Fecha Baja</label>
                                        <input type="date" class="form-control" id="f_baja" name="f_baja"/>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-success" id="btnGuardarRol">Guardar</button>
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

           <script>
$(document).ready(function(){
    var tabla = $('#tablaEntidades').DataTable({
        dom: '<"row"<"col-md-6"l><"col-md-6"fB>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel"></i> Excel',
                titleAttr: 'Exportar a Excel',
                className: 'btn btn-success btn-sm me-2',
                exportOptions: { columns: ':visible' }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                titleAttr: 'Exportar a PDF',
                className: 'btn btn-danger btn-sm',
                orientation: 'portrait',
                pageSize: 'A4',
                exportOptions: { columns: ':visible' }
            }
        ],
        ajax: {
            url: 'entidades_ajax.php',
            type: 'GET',
            data: {accion: 'listar'},
            dataSrc: ''
        },
        language: {
            "search": "Buscar:",
            "searchPlaceholder": "Buscar entidades...",
            "lengthMenu": "Mostrar _MENU_ registros por página",
            "zeroRecords": "No se encontraron entidades",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ entidades",
            "infoEmpty": "Mostrando 0 a 0 de 0 entidades",
            "infoFiltered": "(filtrado de _MAX_ entidades totales)",
            "paginate": {
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            }
        },
        columns: [
            { data: 'entidad_id' },
            { data: 'nombre_fiscal' },
            { data: 'nombre_fantasia' },
            { data: 'cuit' },
            { data: 'entidad_tipo' },
            { data: 'rol_entidad_nombre' },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: "text-center",
                render: function(data){
                    var estadoTexto = data.tabla_estado_registro_id == 1 ? 
                        '<span class="badge bg-success">Activo</span>' : 
                        '<span class="badge bg-secondary">Inactivo</span>';
                    
                    var botonEstado = 
                        `<div class="form-check form-switch d-inline-block">
                            <input class="form-check-input toggle-estado"
                                type="checkbox" 
                                data-entidad-id="${data.entidad_id}" 
                                ${data.tabla_estado_registro_id == 1 ? 'checked' : ''}>
                        </div>`;
                    
                    return `<div class="d-flex flex-column align-items-center">                                            
                                ${botonEstado}
                            </div>`;
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: "text-center",
                render: function(data){
                    var botonEditar = data.tabla_estado_registro_id == 1 ? 
                        `<button class="btn btn-sm btn-primary btnEditar" title="Editar">
                            <i class="fa fa-edit"></i>
                         </button>` : 
                        `<button class="btn btn-sm btn-secondary" title="Editar no disponible" disabled>
                            <i class="fa fa-edit"></i>
                         </button>`;
                    
                    var botonEliminar = data.tabla_estado_registro_id == 1 ? 
                        `<button class="btn btn-sm btn-danger btnEliminar" title="Eliminar">
                            <i class="fa fa-trash"></i>
                         </button>` : 
                        `<button class="btn btn-sm btn-secondary" title="Eliminar no disponible" disabled>
                            <i class="fa fa-trash"></i>
                         </button>`;
                    
                    return `<div class="d-flex align-items-center justify-content-center gap-2">${botonEditar} ${botonEliminar}</div>`;
                }
            }
        ],
        createdRow: function(row, data, dataIndex) {
            if (data.tabla_estado_registro_id != 1) {
                $(row).addClass('table-secondary');
                $(row).find('td').css('color', '#6c757d');
            }
        }
    });

    // Variables globales para datos maestros
    var tiposEntidad = [];
    var condicionesFiscales = [];
    var rolesEntidades = [];
    var localidades = [];

    // Cargar datos maestros al iniciar
    function cargarDatosMaestros() {
        $.get('entidades_ajax.php', {accion: 'obtener_maestras'}, function(res){
            tiposEntidad = res.tipos_entidad || [];
            condicionesFiscales = res.condiciones_fiscales || [];
            rolesEntidades = res.roles_entidades || [];
            localidades = res.localidades || [];

            // Llenar select de tipos de entidad
            var selectTipo = $('#entidad_tipo_id');
            selectTipo.empty().append('<option value="">Seleccionar...</option>');
            tiposEntidad.forEach(function(tipo){
                selectTipo.append(`<option value="${tipo.entidad_tipo_id}">${tipo.entidad_tipo}</option>`);
            });

            // Llenar select de localidades
            var selectLocalidad = $('#localidad_id, #sucursal_localidad_id');
            selectLocalidad.empty().append('<option value="">Seleccionar...</option>');
            localidades.forEach(function(localidad){
                selectLocalidad.append(`<option value="${localidad.localidad_id}">${localidad.localidad}</option>`);
            });

            // Llenar select de condiciones fiscales
            var selectCondicion = $('#condicion_fiscal_id');
            selectCondicion.empty().append('<option value="">Seleccionar...</option>');
            condicionesFiscales.forEach(function(condicion){
                selectCondicion.append(`<option value="${condicion.condicion_fiscal_id}">${condicion.condicion_fiscal}</option>`);
            });

            // Llenar select de roles
            var selectRol = $('#rol_entidad_id');
            selectRol.empty().append('<option value="">Seleccionar...</option>');
            rolesEntidades.forEach(function(rol){
                selectRol.append(`<option value="${rol.rol_entidad_id}">${rol.rol_entidad_nombre}</option>`);
            });
        }, 'json');
    }

    cargarDatosMaestros();

    // Cargar datos auxiliares de una entidad
    function cargarDatosAuxiliares(entidadId) {
        $.get('entidades_ajax.php', {accion: 'obtener_datos_auxiliares', entidad_id: entidadId}, function(res){
            // Cargar sucursales
            var tbodySucursales = $('#tbodySucursales');
            tbodySucursales.empty();
            res.sucursales.forEach(function(sucursal){
                tbodySucursales.append(`
                    <tr>
                        <td>${sucursal.sucursal_nombre}</td>
                        <td>${sucursal.sucursal_direccion || ''}</td>
                        <td>${sucursal.localidad || ''}</td>
                        <td>${sucursal.sucursal_telefono || ''}</td>
                        <td>
                            <button class="btn btn-sm btn-warning btnEditarSucursal" data-id="${sucursal.sucursal_id}">Editar</button>
                            <button class="btn btn-sm btn-danger btnEliminarSucursal" data-id="${sucursal.sucursal_id}">Eliminar</button>
                        </td>
                    </tr>
                `);
            });

            // Cargar condiciones fiscales
            var tbodyCondiciones = $('#tbodyCondiciones');
            tbodyCondiciones.empty();
            res.condiciones_fiscales.forEach(function(condicion){
                tbodyCondiciones.append(`
                    <tr>
                        <td>${condicion.condicion_fiscal}</td>
                        <td>${condicion.f_desde}</td>
                        <td>${condicion.f_hasta || 'Actual'}</td>
                        <td>
                            <button class="btn btn-sm btn-warning btnEditarCondicion" data-id="${condicion.entidad_condicion_fiscal_id}">Editar</button>
                            <button class="btn btn-sm btn-danger btnEliminarCondicion" data-id="${condicion.entidad_condicion_fiscal_id}">Eliminar</button>
                        </td>
                    </tr>
                `);
            });

            // Cargar roles
            var tbodyRoles = $('#tbodyRoles');
            tbodyRoles.empty();
            res.roles.forEach(function(rol){
                tbodyRoles.append(`
                    <tr>
                        <td>${rol.rol_entidad_nombre}</td>
                        <td>${rol.f_alta}</td>
                        <td>${rol.f_baja || 'Actual'}</td>
                        <td>
                            <button class="btn btn-sm btn-warning btnEditarRol" data-id="${rol.entidad_rol_id}">Editar</button>
                            <button class="btn btn-sm btn-danger btnEliminarRol" data-id="${rol.entidad_rol_id}">Eliminar</button>
                        </td>
                    </tr>
                `);
            });
        }, 'json');
    }

    // Manejar el cambio de estado con el interruptor
    $(document).on('change', '.toggle-estado', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var entidadId = $(this).data('entidad-id');
        var isChecked = $(this).is(':checked');
        var nuevoEstado = isChecked ? 1 : 0;
        var accionTexto = nuevoEstado == 1 ? 'activar' : 'desactivar';                    
       
        Swal.fire({
            title: `¿${nuevoEstado == 1 ? 'Activar' : 'Desactivar'} entidad?`,
            text: `Está a punto de ${accionTexto} esta entidad comercial`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: `Sí, ${accionTexto}`,
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.get('entidades_ajax.php', {
                    accion: 'cambiar_estado', 
                    entidad_id: entidadId,
                    nuevo_estado: nuevoEstado
                }, function(res){
                    if(res.resultado){
                        tabla.ajax.reload();
                        Swal.fire({
                            icon: "success",
                            title: "¡Éxito!",
                            text: `Entidad comercial ${accionTexto}da correctamente`,
                            showConfirmButton: false,
                            timer: 1500
                        });
                    } else {
                        // Revertir el cambio visual si hay error
                        $(this).prop('checked', !isChecked);
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || `Error al ${accionTexto} la entidad`
                        });
                    }
                }, 'json');
            } else {
                // Revertir visualmente si cancela
                $(this).prop('checked', !isChecked);
            }
        });
    });

    // Evento para el botón Guardar Entidad
    $('#btnGuardar').click(function(){
        var form = document.getElementById('formEntidad');
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        
        var id = $('#entidad_id').val();
        var accion = id ? 'editar' : 'agregar';
        var formData = {
            accion: accion,
            entidad_id: id,
            nombre_fiscal: $('#nombre_fiscal').val(),
            nombre_fantasia: $('#nombre_fantasia').val(),
            entidad_tipo_id: $('#entidad_tipo_id').val(),
            cuit: $('#cuit').val(),
            sitio_web: $('#sitio_web').val(),
            domicilio_legal: $('#domicilio_legal').val(),
            localidad_id: $('#localidad_id').val(),
            observaciones: $('#observaciones').val(),
            tabla_estado_registro_id: $('#tabla_estado_registro_id').val()
        };

        $.ajax({
            url: 'entidades_ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if(res.resultado) {
                    tabla.ajax.reload(null, false);
                    
                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalEntidad'));
                    modal.hide();
                    
                    $('#formEntidad')[0].reset();
                    form.classList.remove('was-validated');
                    
                    Swal.fire({
                        icon: "success",
                        title: "¡Éxito!",
                        text: id ? "Entidad actualizada correctamente" : "Entidad creada correctamente",
                        showConfirmButton: false,
                        timer: 1500
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || "Error al guardar los datos"
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error de conexión con el servidor"
                });
            }
        });
    });

    // Evento para editar entidad
    $('#tablaEntidades tbody').on('click', '.btnEditar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        // Solo permitir editar si está activo
        if (data.tabla_estado_registro_id != 1) {
            Swal.fire({
                icon: "warning",
                title: "Entidad inactiva",
                text: "No se puede editar una entidad inactiva. Active la entidad primero.",
                showConfirmButton: false,
                timer: 2000
            });
            return false;
        }
        
        $.get('entidades_ajax.php', {
            accion: 'obtener', 
            entidad_id: data.entidad_id
        }, function(res){
            if(res){
                $('#entidad_id').val(res.entidad_id);
                $('#nombre_fiscal').val(res.nombre_fiscal);
                $('#nombre_fantasia').val(res.nombre_fantasia);
                $('#entidad_tipo_id').val(res.entidad_tipo_id);
                $('#cuit').val(res.cuit);
                $('#sitio_web').val(res.sitio_web);
                $('#domicilio_legal').val(res.domicilio_legal);
                $('#localidad_id').val(res.localidad_id);
                $('#observaciones').val(res.observaciones);
                $('#tabla_estado_registro_id').val(res.tabla_estado_registro_id);
                
                $('#modalLabel').text('Editar Entidad Comercial');
                var modal = new bootstrap.Modal(document.getElementById('modalEntidad'));
                modal.show();
                
                // Cargar datos auxiliares (sucursales, condiciones fiscales, roles)
                cargarDatosAuxiliares(res.entidad_id);
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener los datos de la entidad"
                });
            }
        }, 'json');
    });

    // Evento para eliminar entidad
    $('#tablaEntidades tbody').on('click', '.btnEliminar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        
        Swal.fire({
            title: '¿Eliminar entidad comercial?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.get('entidades_ajax.php', {
                    accion: 'eliminar', 
                    entidad_id: data.entidad_id
                }, function(res){
                    if(res.resultado){
                        tabla.ajax.reload();
                        Swal.fire({
                            icon: "success",
                            title: "¡Éxito!",
                            text: "Entidad comercial eliminada correctamente",
                            showConfirmButton: false,
                            timer: 1500
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || "Error al eliminar la entidad comercial"
                        });
                    }
                }, 'json');
            }
        });
    });

    // Botón Nuevo
    $('#btnNuevo').click(function(){
        $('#formEntidad')[0].reset();
        $('#entidad_id').val('');
        $('#modalLabel').text('Nueva Entidad Comercial');
        $('#tabla_estado_registro_id').val('1');
        var modal = new bootstrap.Modal(document.getElementById('modalEntidad'));
        modal.show();
    });

    // Eventos para las pestañas de datos auxiliares
    $('#btnNuevaSucursal').click(function(){
        var entidadId = $('#entidad_id').val();
        if (!entidadId) {
            Swal.fire({
                icon: "warning",
                title: "Primero guarde la entidad",
                text: "Debe guardar la entidad principal antes de agregar sucursales",
                showConfirmButton: false,
                timer: 2000
            });
            return false;
        }
        
        $('#formSucursal')[0].reset();
        $('#sucursal_id').val('');
        $('#entidad_id_sucursal').val(entidadId);
        var modal = new bootstrap.Modal(document.getElementById('modalSucursal'));
        modal.show();
    });

    $('#btnNuevaCondicion').click(function(){
        var entidadId = $('#entidad_id').val();
        if (!entidadId) {
            Swal.fire({
                icon: "warning",
                title: "Primero guarde la entidad",
                text: "Debe guardar la entidad principal antes de agregar condiciones fiscales",
                showConfirmButton: false,
                timer: 2000
            });
            return false;
        }
        
        $('#formCondicion')[0].reset();
        $('#entidad_condicion_fiscal_id').val('');
        $('#entidad_id_condicion').val(entidadId);
        var modal = new bootstrap.Modal(document.getElementById('modalCondicion'));
        modal.show();
    });

    $('#btnNuevoRol').click(function(){
        var entidadId = $('#entidad_id').val();
        if (!entidadId) {
            Swal.fire({
                icon: "warning",
                title: "Primero guarde la entidad",
                text: "Debe guardar la entidad principal antes de agregar roles",
                showConfirmButton: false,
                timer: 2000
            });
            return false;
        }
        
        $('#formRol')[0].reset();
        $('#entidad_rol_id').val('');
        $('#entidad_id_rol').val(entidadId);
        var modal = new bootstrap.Modal(document.getElementById('modalRol'));
        modal.show();
    });

    // Eventos para guardar datos auxiliares
    $('#btnGuardarSucursal').click(function(){
        var form = document.getElementById('formSucursal');
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        
        var id = $('#sucursal_id').val();
        var accion = id ? 'editar_sucursal' : 'agregar_sucursal';
        var formData = $('#formSucursal').serializeArray();
        formData.push({name: 'accion', value: accion});
        formData.push({name: 'sucursal_id', value: id});

        $.ajax({
            url: 'entidades_ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if(res.resultado) {
                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalSucursal'));
                    modal.hide();
                    
                    $('#formSucursal')[0].reset();
                    form.classList.remove('was-validated');
                    
                    // Recargar datos auxiliares
                    cargarDatosAuxiliares($('#entidad_id').val());
                    
                    Swal.fire({
                        icon: "success",
                        title: "¡Éxito!",
                        text: id ? "Sucursal actualizada correctamente" : "Sucursal creada correctamente",
                        showConfirmButton: false,
                        timer: 1500
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || "Error al guardar los datos"
                    });
                }
            }
        });
    });

    $('#btnGuardarCondicion').click(function(){
        var form = document.getElementById('formCondicion');
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        
        var id = $('#entidad_condicion_fiscal_id').val();
        var accion = id ? 'editar_condicion' : 'agregar_condicion';
        var formData = $('#formCondicion').serializeArray();
        formData.push({name: 'accion', value: accion});
        formData.push({name: 'entidad_condicion_fiscal_id', value: id});

        $.ajax({
            url: 'entidades_ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if(res.resultado) {
                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalCondicion'));
                    modal.hide();
                    
                    $('#formCondicion')[0].reset();
                    form.classList.remove('was-validated');
                    
                    // Recargar datos auxiliares
                    cargarDatosAuxiliares($('#entidad_id').val());
                    
                    Swal.fire({
                        icon: "success",
                        title: "¡Éxito!",
                        text: id ? "Condición fiscal actualizada correctamente" : "Condición fiscal creada correctamente",
                        showConfirmButton: false,
                        timer: 1500
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || "Error al guardar los datos"
                    });
                }
            }
        });
    });

    $('#btnGuardarRol').click(function(){
        var form = document.getElementById('formRol');
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        
        var id = $('#entidad_rol_id').val();
        var accion = id ? 'editar_rol' : 'agregar_rol';
        var formData = $('#formRol').serializeArray();
        formData.push({name: 'accion', value: accion});
        formData.push({name: 'entidad_rol_id', value: id});

        $.ajax({
            url: 'entidades_ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if(res.resultado) {
                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalRol'));
                    modal.hide();
                    
                    $('#formRol')[0].reset();
                    form.classList.remove('was-validated');
                    
                    // Recargar datos auxiliares
                    cargarDatosAuxiliares($('#entidad_id').val());
                    
                    Swal.fire({
                        icon: "success",
                        title: "¡Éxito!",
                        text: id ? "Rol actualizado correctamente" : "Rol creado correctamente",
                        showConfirmButton: false,
                        timer: 1500
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || "Error al guardar los datos"
                    });
                }
            }
        });
    });

    // Eventos para editar datos auxiliares
    $(document).on('click', '.btnEditarSucursal', function(){
        var sucursalId = $(this).data('id');
        $.get('entidades_ajax.php', {accion: 'obtener_sucursal', sucursal_id: sucursalId}, function(res){
            if(res){
                $('#sucursal_id').val(res.sucursal_id);
                $('#entidad_id_sucursal').val(res.entidad_id);
                $('#sucursal_nombre').val(res.sucursal_nombre);
                $('#sucursal_telefono').val(res.sucursal_telefono);
                $('#sucursal_direccion').val(res.sucursal_direccion);
                $('#sucursal_localidad_id').val(res.localidad_id);
                $('#sucursal_email').val(res.sucursal_email);
                $('#sucursal_contacto').val(res.sucursal_contacto);
                
                var modal = new bootstrap.Modal(document.getElementById('modalSucursal'));
                modal.show();
            }
        }, 'json');
    });

    $(document).on('click', '.btnEditarCondicion', function(){
        var condicionId = $(this).data('id');
        $.get('entidades_ajax.php', {accion: 'obtener_condicion', entidad_condicion_fiscal_id: condicionId}, function(res){
            if(res){
                $('#entidad_condicion_fiscal_id').val(res.entidad_condicion_fiscal_id);
                $('#entidad_id_condicion').val(res.entidad_id);
                $('#condicion_fiscal_id').val(res.condicion_fiscal_id);
                $('#f_desde').val(res.f_desde);
                $('#f_hasta').val(res.f_hasta);
                
                var modal = new bootstrap.Modal(document.getElementById('modalCondicion'));
                modal.show();
            }
        }, 'json');
    });

    $(document).on('click', '.btnEditarRol', function(){
        var rolId = $(this).data('id');
        $.get('entidades_ajax.php', {accion: 'obtener_rol', entidad_rol_id: rolId}, function(res){
            if(res){
                $('#entidad_rol_id').val(res.entidad_rol_id);
                $('#entidad_id_rol').val(res.entidad_id);
                $('#rol_entidad_id').val(res.rol_entidad_id);
                $('#f_alta').val(res.f_alta);
                $('#f_baja').val(res.f_baja);
                
                var modal = new bootstrap.Modal(document.getElementById('modalRol'));
                modal.show();
            }
        }, 'json');
    });

    // Eventos para eliminar datos auxiliares
    $(document).on('click', '.btnEliminarSucursal', function(){
        var sucursalId = $(this).data('id');
        Swal.fire({
            title: '¿Eliminar sucursal?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.get('entidades_ajax.php', {
                    accion: 'eliminar_sucursal', 
                    sucursal_id: sucursalId
                }, function(res){
                    if(res.resultado){
                        cargarDatosAuxiliares($('#entidad_id').val());
                        Swal.fire({
                            icon: "success",
                            title: "¡Éxito!",
                            text: "Sucursal eliminada correctamente",
                            showConfirmButton: false,
                            timer: 1500
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || "Error al eliminar la sucursal"
                        });
                    }
                }, 'json');
            }
        });
    });

    $(document).on('click', '.btnEliminarCondicion', function(){
        var condicionId = $(this).data('id');
        Swal.fire({
            title: '¿Eliminar condición fiscal?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.get('entidades_ajax.php', {
                    accion: 'eliminar_condicion', 
                    entidad_condicion_fiscal_id: condicionId
                }, function(res){
                    if(res.resultado){
                        cargarDatosAuxiliares($('#entidad_id').val());
                        Swal.fire({
                            icon: "success",
                            title: "¡Éxito!",
                            text: "Condición fiscal eliminada correctamente",
                            showConfirmButton: false,
                            timer: 1500
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || "Error al eliminar la condición fiscal"
                        });
                    }
                }, 'json');
            }
        });
    });

    $(document).on('click', '.btnEliminarRol', function(){
        var rolId = $(this).data('id');
        Swal.fire({
            title: '¿Eliminar rol?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.get('entidades_ajax.php', {
                    accion: 'eliminar_rol', 
                    entidad_rol_id: rolId
                }, function(res){
                    if(res.resultado){
                        cargarDatosAuxiliares($('#entidad_id').val());
                        Swal.fire({
                            icon: "success",
                            title: "¡Éxito!",
                            text: "Rol eliminado correctamente",
                            showConfirmButton: false,
                            timer: 1500
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || "Error al eliminar el rol"
                        });
                    }
                }, 'json');
            }
        });
    });
});
</script>
            <style>
            .table-secondary td {
                color: #6c757d !important;
            }
            
            .form-check.form-switch.d-inline-block {
                padding-left: 0;
                margin-bottom: 0;
            }
            
            .form-check-input.toggle-estado {
                width: 3em;
                height: 1.5em;
            }
            
            .badge {
                font-size: 0.75rem;
            }
            
            .form-check.form-switch .form-check-input {
                margin-right: 0.5rem;
            }

            .nav-tabs .nav-link.active {
                font-weight: bold;
            }
            </style>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

            <?php
            require_once ROOT_PATH . '/templates/adminlte/footer1.php';
            ?>
            </body>
            </html>