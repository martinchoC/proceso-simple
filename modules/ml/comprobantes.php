<?php
// Configuración de la página
$pageTitle = "Gestión de Comprobantes por Sucursal";
$currentPage = 'paginas';
$modudo_idx = 2;
$empresa_idx = 2;

// Definir constante para rutas
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));

// Incluir header
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Comprobantes por Sucursal</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Comprobantes</li>
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
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nueva asignación</button>
                                        <table id="tablaComprobantes" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Sucursal</th>
                                                    <th>Grupo Comprobante</th>
                                                    <th>Tipo Comprobante</th>
                                                    <th>Código</th>
                                                    <th>Letra</th>
                                                    <th>Signo</th>
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
            <div class="modal fade" id="modalComprobante" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Asignación de Comprobante</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formComprobante">
                                <input type="hidden" id="comprobante_sucursal_id" name="comprobante_sucursal_id" />
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label>Sucursal *</label>
                                        <select class="form-control" id="sucursal_id" name="sucursal_id" required>
                                            <option value="">-- Seleccionar sucursal --</option>
                                        </select>
                                        <div class="invalid-feedback">La sucursal es obligatoria</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Tipo de Comprobante *</label>
                                        <select class="form-control" id="comprobante_tipo_id" name="comprobante_tipo_id" required>
                                            <option value="">-- Seleccionar tipo --</option>
                                        </select>
                                        <div class="invalid-feedback">El tipo de comprobante es obligatorio</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Estado</label>
                                        <select class="form-control" id="estado_registro_id" name="estado_registro_id">
                                            <option value="1">Activo</option>
                                            <option value="0">Inactivo</option>
                                        </select>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="alert alert-info">
                                            <small><strong>Información:</strong> Esta asignación vincula una sucursal con un tipo de comprobante específico.</small>
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
                // Configuración de DataTable
                var tabla = $('#tablaComprobantes').DataTable({
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
                        url: 'comprobantes_ajax.php',
                        type: 'GET',
                        data: {accion: 'listar'},
                        dataSrc: ''
                    },
                    language: {
                        "search": "Buscar:",
                        "searchPlaceholder": "Buscar comprobantes...",
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
                        { data: 'comprobante_sucursal_id' },
                        { data: 'sucursal_nombre' },
                        { data: 'comprobante_grupo' },
                        { data: 'comprobante_tipo' },
                        { data: 'comprobante_codigo' },
                        { 
                            data: 'letra',
                            render: function(data) {
                                return data || '-';
                            }
                        },
                        { 
                            data: 'signo',
                            render: function(data) {
                                return data == 1 ? 'Positivo' : 'Negativo';
                            }
                        },
                        {
                            data: null,
                            orderable: false,
                            searchable: false,
                            className: "text-center",
                            render: function(data){
                                var estadoTexto = data.estado_registro_id == 1 ? 
                                    '<span class="badge bg-success">Activo</span>' : 
                                    '<span class="badge bg-secondary">Inactivo</span>';
                                
                                var botonEstado = 
                                    `<div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input toggle-estado"
                                            type="checkbox" 
                                            data-comprobante-id="${data.comprobante_sucursal_id}" 
                                            ${data.estado_registro_id == 1 ? 'checked' : ''}>
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
                                var botonEditar = data.estado_registro_id == 1 ? 
                                    `<button class="btn btn-sm btn-primary btnEditar" title="Editar">
                                        <i class="fa fa-edit"></i>
                                     </button>` : 
                                    `<button class="btn btn-sm btn-secondary" title="Editar no disponible" disabled>
                                        <i class="fa fa-edit"></i>
                                     </button>`;
                                
                                var botonEliminar = data.estado_registro_id == 1 ? 
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
                        // Cambiar color de fondo según el estado
                        if (data.estado_registro_id != 1) {
                            $(row).addClass('table-secondary');
                            $(row).find('td').css('color', '#6c757d');
                        }
                    }
                });

                // Cargar sucursales disponibles
                function cargarSucursales() {
                    $.get('comprobantes_ajax.php', {accion: 'listar_sucursales'}, function(sucursales) {
                        var select = $('#sucursal_id');
                        select.empty().append('<option value="">-- Seleccionar sucursal --</option>');
                        
                        $.each(sucursales, function(index, sucursal) {
                            select.append($('<option>', {
                                value: sucursal.sucursal_id,
                                text: sucursal.nombre
                            }));
                        });
                    }, 'json');
                }

                // Cargar tipos de comprobantes disponibles
                function cargarComprobantesTipos() {
                    $.get('comprobantes_ajax.php', {accion: 'listar_comprobantes_tipos'}, function(comprobantes_tipos) {
                        var select = $('#comprobante_tipo_id');
                        select.empty().append('<option value="">-- Seleccionar tipo --</option>');
                        
                        $.each(comprobantes_tipos, function(index, tipo) {
                            select.append($('<option>', {
                                value: tipo.comprobante_tipo_id,
                                text: tipo.comprobante_grupo + ' - ' + tipo.comprobante_tipo + ' (' + tipo.comprobante_codigo + ')'
                            }));
                        });
                    }, 'json');
                }

                // Manejar el cambio de estado con el interruptor
                $(document).on('change', '.toggle-estado', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var comprobanteId = $(this).data('comprobante-id');
                    var isChecked = $(this).is(':checked');
                    var nuevoEstado = isChecked ? 1 : 0;
                    var accionTexto = nuevoEstado == 1 ? 'activar' : 'desactivar';                    
                   
                    Swal.fire({
                        title: `¿${nuevoEstado == 1 ? 'Activar' : 'Desactivar'} asignación?`,
                        text: `Está a punto de ${accionTexto} esta asignación`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: `Sí, ${accionTexto}`,
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('comprobantes_ajax.php', {
                                accion: 'cambiar_estado', 
                                comprobante_sucursal_id: comprobanteId,
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
                                    // Revertir el cambio visual si hay error
                                    $(this).prop('checked', !isChecked);
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || `Error al ${accionTexto} la asignación`
                                    });
                                }
                            }, 'json');
                        } else {
                            // Revertir visualmente si cancela
                            $(this).prop('checked', !isChecked);
                        }
                    });
                });

                $('#btnNuevo').click(function(){
                    $('#formComprobante')[0].reset();
                    $('#comprobante_sucursal_id').val('');
                    $('#modalLabel').text('Nueva Asignación');
                    $('#estado_registro_id').val('1');
                    cargarSucursales();
                    cargarComprobantesTipos();
                    var modal = new bootstrap.Modal(document.getElementById('modalComprobante'));
                    modal.show();
                });

                $('#tablaComprobantes tbody').on('click', '.btnEditar', function(){
                    var data = tabla.row($(this).parents('tr')).data();
                    // Solo permitir editar si está activo
                    if (data.estado_registro_id != 1) {
                        Swal.fire({
                            icon: "warning",
                            title: "Asignación inactiva",
                            text: "No se puede editar una asignación inactiva. Active la asignación primero.",
                            showConfirmButton: false,
                            timer: 2000
                        });
                        return false;
                    }
                    
                    $.get('comprobantes_ajax.php', {accion: 'obtener', comprobante_sucursal_id: data.comprobante_sucursal_id}, function(res){
                        if(res){
                            $('#comprobante_sucursal_id').val(res.comprobante_sucursal_id);
                            $('#estado_registro_id').val(res.estado_registro_id);
                            
                            // Cargar sucursales y tipos, y seleccionar los correctos
                            cargarSucursales();
                            cargarComprobantesTipos();
                            setTimeout(function() {
                                $('#sucursal_id').val(res.sucursal_id);
                                $('#comprobante_tipo_id').val(res.comprobante_tipo_id);
                            }, 300);
                            
                            $('#modalLabel').text('Editar Asignación');
                            var modal = new bootstrap.Modal(document.getElementById('modalComprobante'));
                            modal.show();
                        } else {
                            alert('Error al obtener datos');
                        }
                    }, 'json');
                });

                // Eliminar asignación
                $('#tablaComprobantes tbody').on('click', '.btnEliminar', function(){
                    var data = tabla.row($(this).parents('tr')).data();
                    
                    Swal.fire({
                        title: '¿Eliminar asignación?',
                        text: "Esta acción no se puede deshacer",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('comprobantes_ajax.php', {
                                accion: 'eliminar', 
                                comprobante_sucursal_id: data.comprobante_sucursal_id
                            }, function(res){
                                if(res.resultado){
                                    tabla.ajax.reload();
                                    Swal.fire({
                                        icon: "success",
                                        title: "¡Éxito!",
                                        text: "Asignación eliminada correctamente",
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                } else {
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || "Error al eliminar la asignación"
                                    });
                                }
                            }, 'json');
                        }
                    });
                });

                $('#btnGuardar').click(function(){
                    var form = document.getElementById('formComprobante');
                    
                    if (!form.checkValidity()) {
                        form.classList.add('was-validated');
                        return false;
                    }
                    
                    var id = $('#comprobante_sucursal_id').val();
                    var accion = id ? 'editar' : 'agregar';
                    var formData = {
                        accion: accion,
                        comprobante_sucursal_id: id,
                        sucursal_id: $('#sucursal_id').val(),
                        comprobante_tipo_id: $('#comprobante_tipo_id').val(),
                        estado_registro_id: $('#estado_registro_id').val()
                    };

                    $.ajax({
                        url: 'comprobantes_ajax.php',
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        success: function(res) {
                            if(res.resultado) {
                                tabla.ajax.reload(null, false);
                                
                                var modal = bootstrap.Modal.getInstance(document.getElementById('modalComprobante'));
                                modal.hide();
                                
                                $('#formComprobante')[0].reset();
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
            <style>
            .table-secondary td {
                color: #6c757d !important;
            }
            
            /* Estilos para el interruptor en la tabla */
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
            </style>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

            <?php
            require_once ROOT_PATH . '/templates/adminlte/footer1.php';
            ?>
            </body>
            </html>