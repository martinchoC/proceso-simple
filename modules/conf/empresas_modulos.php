<?php
// Configuración de la página
$pageTitle = "Gestión de Empresas-Módulos";
$currentPage = 'empresas_modulos';
$modudo_idx = 1;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Asignación de Módulos a Empresas</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Empresas-Módulos</li>
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
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Asignar Nuevo Módulo</button>
                                        <table id="tablaEmpresasModulos" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Empresa</th>
                                                    <th>Módulo</th>
                                                    <th>Estado</th>
                                                    <th>Fecha Alta</th>
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

<!-- Modal -->
<div class="modal fade" id="modalEmpresaModulo" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">Asignar Módulo a Empresa</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form id="formEmpresaModulo">
            <input type="hidden" id="empresa_modulo_id" name="empresa_modulo_id" />
             
            <div class="row g-3">
                <div class="col-md-12">
                    <label>Empresa *</label>
                    <select class="form-select" id="empresa_id" name="empresa_id" required>
                        <option value="">Seleccionar empresa</option>
                        <!-- Las opciones se cargarán dinámicamente -->
                    </select>
                    <div class="invalid-feedback">Seleccione una empresa</div>
                </div>    
                <div class="col-md-12">
                    <label>Módulo *</label>
                    <select class="form-select" id="modulo_id" name="modulo_id" required>
                        <option value="">Seleccionar módulo</option>
                        <!-- Las opciones se cargarán dinámicamente -->
                    </select>
                    <div class="invalid-feedback">Seleccione un módulo</div>
                </div>                
                <div class="col-md-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="tabla_estado_registro_id" name="tabla_estado_registro_id" value="1" checked>
                        <label class="form-check-label" for="tabla_estado_registro_id">Activo</label>
                    </div>
                </div>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button id="btnGuardar" class="btn btn-success">Guardar</button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function(){
    // Variables globales para almacenar opciones
    var empresasOptions = [];
    var modulosOptions = [];
    
    // Cargar opciones de empresas y módulos
    function cargarOpciones() {
        $.get('empresas_modulos_ajax.php', {accion: 'obtener_empresas'}, function(res){
            if(res && res.length > 0) {
                empresasOptions = res;
                $('#empresa_id').empty().append('<option value="">Seleccionar empresa</option>');
                $.each(res, function(i, empresa) {
                    $('#empresa_id').append($('<option>', {
                        value: empresa.empresa_id,
                        text: empresa.empresa || 'Empresa ' + empresa.empresa_id
                    }));
                });
            }
        }, 'json');
        
        $.get('empresas_modulos_ajax.php', {accion: 'obtener_modulos'}, function(res){
            if(res && res.length > 0) {
                modulosOptions = res;
                $('#modulo_id').empty().append('<option value="">Seleccionar módulo</option>');
                $.each(res, function(i, modulo) {
                    $('#modulo_id').append($('<option>', {
                        value: modulo.modulo_id,
                        text: modulo.modulo || 'Módulo ' + modulo.modulo_id
                    }));
                });
            }
        }, 'json');
    }
    
    // Configuración de DataTable
    var tabla = $('#tablaEmpresasModulos').DataTable({
        pageLength: 25,
        lengthMenu: [25, 50, 100, 200],        
        dom: '<"row"<"col-md-6"l><"col-md-6"fB>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel"></i> Excel',
                titleAttr: 'Exportar a Excel',
                className: 'btn btn-success btn-sm me-2',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4]
                }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                titleAttr: 'Exportar a PDF',
                className: 'btn btn-danger btn-sm',
                orientation: 'portrait',
                pageSize: 'A4',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4]
                }
            }
        ],
        ajax: {
            url: 'empresas_modulos_ajax.php',
            type: 'GET',
            data: {accion: 'listar'},
            dataSrc: ''
        },
        language: {
            "search": "Buscar:",
            "searchPlaceholder": "Buscar asignaciones...",
            "lengthMenu": "Mostrar _MENU_ registros por página",
            "zeroRecords": "No se encontraron asignaciones",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ asignaciones",
            "infoEmpty": "Mostrando 0 a 0 de 0 asignaciones",
            "infoFiltered": "(filtrado de _MAX_ asignaciones totales)",
            "paginate": {
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            }
        },
        columns: [
                { data: 'empresa_modulo_id' },
            { 
                data: 'empresa_id',
                render: function(data, type, row) {
                    if (row.empresa) { // Si ya viene en el JSON
                        return row.empresa;
                    } else if (data) {
                        var empresa = empresasOptions.find(e => e.empresa_id == data);
                        return empresa ? empresa.empresa : 'Empresa ' + data;
                    }
                    return 'N/A';
                }
            },
            { 
                data: 'modulo_id',
                render: function(data, type, row) {
                    if (row.modulo) { // Si ya viene en el JSON
                        return row.modulo;
                    } else if (data) {
                        var modulo = modulosOptions.find(m => m.modulo_id == data);
                        return modulo ? modulo.modulo : 'Módulo ' + data;
                    }
                    return 'N/A';
                }
            },
            {
                data: 'tabla_estado_registro_id',
                render: function(data) {
                    return data == 1 ? 
                        '<span class="badge bg-success">Activo</span>' : 
                        '<span class="badge bg-danger">Inactivo</span>';
                }
            },
            {
                data: 'fecha_alta',
                render: function(data) {
                    if (!data || data === '0000-00-00 00:00:00') return 'N/A';
                    return new Date(data).toLocaleDateString('es-ES');
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: "text-center",
                render: function(data){
                    var botonEditar = data.tabla_estado_registro_id == 1 ? 
                        `<button class="btn btn-sm btn-primary btnEditar me-1" title="Editar">
                            <i class="fa fa-pencil-alt"></i>
                         </button>` : 
                        `<button class="btn btn-sm btn-primary btnEditar me-1" title="Editar" disabled>
                            <i class="fa fa-pencil-alt"></i>
                         </button>`;
                    
                    var botonEstado = data.tabla_estado_registro_id == 1 ? 
                        `<button class="btn btn-sm btn-warning btnToggleEstado" title="Desactivar">
                            <i class="fa fa-times"></i>
                         </button>` : 
                        `<button class="btn btn-sm btn-success btnToggleEstado" title="Activar">
                            <i class="fa fa-check"></i>
                         </button>`;
                    
                    return botonEditar + botonEstado;
                }
            }
        ]
    });

    // Cargar opciones al iniciar
    cargarOpciones();

    $('#btnNuevo').click(function(){
        $('#formEmpresaModulo')[0].reset();
        $('#empresa_modulo_id').val('');
        $('#tabla_estado_registro_id').prop('checked', true);
        $('#modalLabel').text('Asignar Nuevo Módulo a Empresa');
        var modal = new bootstrap.Modal(document.getElementById('modalEmpresaModulo'));
        modal.show();
    });

    // Toggle estado
    $('#tablaEmpresasModulos tbody').on('click', '.btnToggleEstado', function(){
        var data = tabla.row($(this).parents('tr')).data();
        var nuevoEstado = data.tabla_estado_registro_id == 1 ? 0 : 1;
        var accionTexto = nuevoEstado == 1 ? 'activar' : 'desactivar';
        
        Swal.fire({
            title: `¿${nuevoEstado == 1 ? 'Activar' : 'Desactivar'} asignación?`,
            text: `¿Estás seguro de querer ${accionTexto} esta asignación de módulo?`,
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: `Sí, ${accionTexto}`,
            cancelButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                $.get('empresas_modulos_ajax.php', {
                    accion: 'cambiar_estado', 
                    empresa_modulo_id: data.empresa_modulo_id,
                    nuevo_estado: nuevoEstado
                }, function(res){
                    if(res.resultado){
                        tabla.ajax.reload();
                        Swal.fire({
                            icon: "success",
                            title: "¡Éxito!",
                            text: `Asignación ${accionTexto}da correctamente`,
                            showConfirmButton: false,
                            timer: 1500
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || `Error al ${accionTexto} la asignación`
                        });
                    }
                }, 'json');
            }
        });
    });

    $('#tablaEmpresasModulos tbody').on('click', '.btnEditar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        // Solo permitir editar si está activo
        if (data.tabla_estado_registro_id != 1) {
            Swal.fire({
                icon: "warning",
                title: "Asignación inactiva",
                text: "No se puede editar una asignación inactiva. Active la asignación primero.",
                showConfirmButton: false,
                timer: 2000
            });
            return false;
        }
        
        $.get('empresas_modulos_ajax.php', {accion: 'obtener', empresa_modulo_id: data.empresa_modulo_id}, function(res){
            if(res){
                $('#empresa_modulo_id').val(res.empresa_modulo_id);
                $('#empresa_id').val(res.empresa_id || '');
                $('#modulo_id').val(res.modulo_id || '');
                $('#tabla_estado_registro_id').prop('checked', res.tabla_estado_registro_id == 1);
                
                $('#modalLabel').text('Editar Asignación');
                var modal = new bootstrap.Modal(document.getElementById('modalEmpresaModulo'));
                modal.show();
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos"
                });
            }
        }, 'json');
    });

    $('#btnGuardar').click(function(){
        var form = document.getElementById('formEmpresaModulo');
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        
        var id = $('#empresa_modulo_id').val();
        var accion = id ? 'editar' : 'agregar';
        var formData = {
            accion: accion,
            empresa_modulo_id: id,
            empresa_id: $('#empresa_id').val(),
            modulo_id: $('#modulo_id').val(),
            tabla_estado_registro_id: $('#tabla_estado_registro_id').is(':checked') ? 1 : 0
        };

        $.ajax({
            url: 'empresas_modulos_ajax.php',
            type: 'GET',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if(res.resultado) {
                    tabla.ajax.reload(null, false);
                    
                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalEmpresaModulo'));
                    modal.hide();
                    
                    $('#formEmpresaModulo')[0].reset();
                    form.classList.remove('was-validated');
                    
                    Swal.fire({
                        icon: "success",
                        title: "¡Éxito!",
                        text: id ? "Asignación actualizada correctamente" : "Asignación creada correctamente",
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
});
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>