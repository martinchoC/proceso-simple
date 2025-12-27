<?php
// Configuración de la página
$pageTitle = "Gestión de Perfiles de Empresas";
$currentPage = 'empresas_perfiles';
$modudo_idx = 1;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Perfiles de Empresas</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Perfiles de Empresas</li>
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
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nuevo Perfil de Empresa</button>
                                        <table id="tablaEmpresasPerfiles" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Empresa</th>
                                                    <th>Módulo</th>
                                                    <th>Perfil Base</th>
                                                    <th>Nombre del Perfil</th>
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

<!-- Modal -->
<div class="modal fade" id="modalEmpresaPerfil" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">Perfil de Empresa</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form id="formEmpresaPerfil">
            <input type="hidden" id="empresa_perfil_id" name="empresa_perfil_id" />
             
            <div class="row g-3">
                <div class="col-md-12">
                    <label>Empresa *</label>
                    <select class="form-select" id="empresa_id" name="empresa_id" required>
                        <option value="">Seleccionar empresa</option>
                        <!-- Las opciones se cargarán dinámicamente -->
                    </select>
                    <div class="invalid-feedback">La empresa es obligatoria</div>
                </div>
                <div class="col-md-12">
                    <label>Módulo *</label>
                    <select class="form-select" id="modulo_id" name="modulo_id" required>
                        <option value="">Seleccionar módulo</option>
                        <!-- Las opciones se cargarán dinámicamente -->
                    </select>
                    <div class="invalid-feedback">El módulo es obligatorio</div>
                </div>
                <div class="col-md-12">
                    <label>Perfil Base (Opcional)</label>
                    <select class="form-select" id="perfil_id_base" name="perfil_id_base">
                        <option value="">Seleccionar perfil base (para heredar)</option>
                        <!-- Las opciones se cargarán dinámicamente -->
                    </select>
                    <small class="text-muted">Si se selecciona un perfil base, se heredarán sus configuraciones</small>
                </div>
                <div class="col-md-12">
                    <label>Nombre del Perfil *</label>
                    <input type="text" class="form-control" id="empresa_perfil_nombre" name="empresa_perfil_nombre" required/>
                    <div class="invalid-feedback">El nombre es obligatorio</div>
                </div>
                <div class="col-md-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="tabla_estado_registro_id" name="tabla_estado_registro_id" value="1" checked>
                        <label class="form-check-label" for="tabla_estado_registro_id">Perfil activo</label>
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
    var perfilesBaseOptions = [];
    
    // Cargar opciones de empresas, módulos y perfiles base
    function cargarOpciones() {
        // Cargar empresas
        $.get('empresas_perfiles_ajax.php', {accion: 'obtener_empresas'}, function(res){
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
        
        // Cargar módulos
        $.get('empresas_perfiles_ajax.php', {accion: 'obtener_modulos'}, function(res){
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
        
        // Cargar perfiles base
        $.get('empresas_perfiles_ajax.php', {accion: 'obtener_perfiles_base'}, function(res){
            if(res && res.length > 0) {
                perfilesBaseOptions = res;
                $('#perfil_id_base').empty().append('<option value="">Seleccionar perfil base (para heredar)</option>');
                $.each(res, function(i, perfil) {
                    $('#perfil_id_base').append($('<option>', {
                        value: perfil.perfil_id,
                        text: (perfil.perfil_nombre || 'Perfil ' + perfil.perfil_id) + ' - ' + (perfil.modulo || 'Módulo ' + perfil.modulo_id)
                    }));
                });
            }
        }, 'json');
    }
    
    // Cuando se cambia el módulo, filtrar perfiles base por módulo
    $('#modulo_id').change(function(){
        var modulo_id = $(this).val();
        if(modulo_id) {
            $.get('empresas_perfiles_ajax.php', {accion: 'obtener_perfiles_base_por_modulo', modulo_id: modulo_id}, function(res){
                if(res && res.length > 0) {
                    $('#perfil_id_base').empty().append('<option value="">Seleccionar perfil base (para heredar)</option>');
                    $.each(res, function(i, perfil) {
                        $('#perfil_id_base').append($('<option>', {
                            value: perfil.perfil_id,
                            text: perfil.perfil_nombre || 'Perfil ' + perfil.perfil_id
                        }));
                    });
                } else {
                    $('#perfil_id_base').empty().append('<option value="">No hay perfiles base para este módulo</option>');
                }
            }, 'json');
        } else {
            $('#perfil_id_base').empty().append('<option value="">Seleccionar perfil base (para heredar)</option>');
            $.each(perfilesBaseOptions, function(i, perfil) {
                $('#perfil_id_base').append($('<option>', {
                    value: perfil.perfil_id,
                    text: (perfil.perfil_nombre || 'Perfil ' + perfil.perfil_id) + ' - ' + (perfil.modulo || 'Módulo ' + perfil.modulo_id)
                }));
            });
        }
    });
    
    // Configuración de DataTable
    var tabla = $('#tablaEmpresasPerfiles').DataTable({
        pageLength: 25, // Mostrar 25 registros por página como mínimo
        lengthMenu: [25, 50, 100, 200], // Opciones del menú de cantidad de registros        
        dom: '<"row"<"col-md-6"l><"col-md-6"fB>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel"></i> Excel',
                titleAttr: 'Exportar a Excel',
                className: 'btn btn-success btn-sm me-2',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5]
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
                    columns: [0, 1, 2, 3, 4, 5]
                }
            }
        ],
        ajax: {
            url: 'empresas_perfiles_ajax.php',
            type: 'GET',
            data: {accion: 'listar'},
            dataSrc: ''
        },
        language: {
            "search": "Buscar:",
            "searchPlaceholder": "Buscar perfiles de empresas...",
            "lengthMenu": "Mostrar _MENU_ registros por página",
            "zeroRecords": "No se encontraron perfiles de empresas",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ perfiles",
            "infoEmpty": "Mostrando 0 a 0 de 0 perfiles",
            "infoFiltered": "(filtrado de _MAX_ perfiles totales)",
            "paginate": {
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            }
        },
        columns: [
            { data: 'empresa_perfil_id' },
            { 
                data: 'empresa',
                render: function(data, type, row) {
                    if (data) {
                        var empresa = empresasOptions.find(e => e.empresa_id == row.empresa_id);
                        return empresa ? (empresa.empresa || 'Empresa ' + row.empresa_id) : (row.empresa || 'N/A');
                    }
                    return 'N/A';
                }
            },
            { 
                data: 'modulo',
                render: function(data, type, row) {
                    if (data) {
                        var modulo = modulosOptions.find(m => m.modulo_id == row.modulo_id);
                        return modulo ? (modulo.modulo || 'Módulo ' + row.modulo_id) : (row.modulo || 'N/A');
                    }
                    return 'N/A';
                }
            },
            { 
                data: 'perfil_base',
                render: function(data, type, row) {
                    if (row.perfil_id_base) {
                        var perfil = perfilesBaseOptions.find(p => p.perfil_id == row.perfil_id_base);
                        return perfil ? (perfil.perfil_nombre || 'Perfil ' + row.perfil_id_base) : 'Perfil base ' + row.perfil_id_base;
                    }
                    return '<span class="text-muted">Sin heredar</span>';
                }
            },
            { data: 'empresa_perfil_nombre' },
            {
                data: 'tabla_estado_registro_id',
                render: function(data) {
                    return data == 1 ? 
                        '<span class="badge bg-success">Activo</span>' : 
                        '<span class="badge bg-danger">Inactivo</span>';
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
        $('#formEmpresaPerfil')[0].reset();
        $('#empresa_perfil_id').val('');
        $('#tabla_estado_registro_id').prop('checked', true);
        $('#perfil_id_base').empty().append('<option value="">Seleccionar perfil base (para heredar)</option>');
        $.each(perfilesBaseOptions, function(i, perfil) {
            $('#perfil_id_base').append($('<option>', {
                value: perfil.perfil_id,
                text: (perfil.perfil_nombre || 'Perfil ' + perfil.perfil_id) + ' - ' + (perfil.modulo || 'Módulo ' + perfil.modulo_id)
            }));
        });
        $('#modalLabel').text('Nuevo Perfil de Empresa');
        var modal = new bootstrap.Modal(document.getElementById('modalEmpresaPerfil'));
        modal.show();
    });

    // Toggle estado
    $('#tablaEmpresasPerfiles tbody').on('click', '.btnToggleEstado', function(){
        var data = tabla.row($(this).parents('tr')).data();
        var nuevoEstado = data.tabla_estado_registro_id == 1 ? 0 : 1;
        var accionTexto = nuevoEstado == 1 ? 'activar' : 'desactivar';
        
        Swal.fire({
            title: `¿${nuevoEstado == 1 ? 'Activar' : 'Desactivar'} perfil?`,
            text: `¿Estás seguro de querer ${accionTexto} este perfil de empresa?`,
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: `Sí, ${accionTexto}`,
            cancelButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                $.get('empresas_perfiles_ajax.php', {
                    accion: 'cambiar_estado', 
                    empresa_perfil_id: data.empresa_perfil_id,
                    nuevo_estado: nuevoEstado
                }, function(res){
                    if(res.resultado){
                        tabla.ajax.reload();
                        Swal.fire({
                            icon: "success",
                            title: "¡Éxito!",
                            text: `Perfil de empresa ${accionTexto}do correctamente`,
                            showConfirmButton: false,
                            timer: 1500
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || `Error al ${accionTexto} el perfil de empresa`
                        });
                    }
                }, 'json');
            }
        });
    });

    $('#tablaEmpresasPerfiles tbody').on('click', '.btnEditar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        // Solo permitir editar si está activo
        if (data.tabla_estado_registro_id != 1) {
            Swal.fire({
                icon: "warning",
                title: "Perfil inactivo",
                text: "No se puede editar un perfil inactivo. Active el perfil primero.",
                showConfirmButton: false,
                timer: 2000
            });
            return false;
        }
        
        $.get('empresas_perfiles_ajax.php', {accion: 'obtener', empresa_perfil_id: data.empresa_perfil_id}, function(res){
            if(res){
                $('#empresa_perfil_id').val(res.empresa_perfil_id);
                $('#empresa_perfil_nombre').val(res.empresa_perfil_nombre);
                $('#empresa_id').val(res.empresa_id || '');
                $('#modulo_id').val(res.modulo_id || '');
                $('#perfil_id_base').val(res.perfil_id_base || '');
                $('#tabla_estado_registro_id').prop('checked', res.tabla_estado_registro_id == 1);
                
                // Cargar perfiles base para el módulo seleccionado
                if(res.modulo_id) {
                    $.get('empresas_perfiles_ajax.php', {accion: 'obtener_perfiles_base_por_modulo', modulo_id: res.modulo_id}, function(perfiles){
                        $('#perfil_id_base').empty().append('<option value="">Seleccionar perfil base (para heredar)</option>');
                        if(perfiles && perfiles.length > 0) {
                            $.each(perfiles, function(i, perfil) {
                                var selected = perfil.perfil_id == res.perfil_id_base ? 'selected' : '';
                                $('#perfil_id_base').append($('<option>', {
                                    value: perfil.perfil_id,
                                    text: perfil.perfil_nombre || 'Perfil ' + perfil.perfil_id,
                                    selected: selected
                                }));
                            });
                        } else {
                            $('#perfil_id_base').append('<option value="">No hay perfiles base para este módulo</option>');
                        }
                    }, 'json');
                }
                
                $('#modalLabel').text('Editar Perfil de Empresa');
                var modal = new bootstrap.Modal(document.getElementById('modalEmpresaPerfil'));
                modal.show();
            } else {
                alert('Error al obtener datos');
            }
        }, 'json');
    });

    $('#btnGuardar').click(function(){
        var form = document.getElementById('formEmpresaPerfil');
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        
        var id = $('#empresa_perfil_id').val();
        var accion = id ? 'editar' : 'agregar';
        var formData = {
            accion: accion,
            empresa_perfil_id: id,
            empresa_perfil_nombre: $('#empresa_perfil_nombre').val(),
            empresa_id: $('#empresa_id').val(),
            modulo_id: $('#modulo_id').val(),
            perfil_id_base: $('#perfil_id_base').val() || null,
            tabla_estado_registro_id: $('#tabla_estado_registro_id').is(':checked') ? 1 : 0
        };

        $.ajax({
            url: 'empresas_perfiles_ajax.php',
            type: 'GET',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if(res.resultado) {
                    tabla.ajax.reload(null, false);
                    
                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalEmpresaPerfil'));
                    modal.hide();
                    
                    $('#formEmpresaPerfil')[0].reset();
                    form.classList.remove('was-validated');
                    
                    Swal.fire({
                        icon: "success",
                        title: "¡Éxito!",
                        text: id ? "Perfil de empresa actualizado correctamente" : "Perfil de empresa creado correctamente",
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