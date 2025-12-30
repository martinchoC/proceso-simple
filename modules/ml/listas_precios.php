<?php
// Configuración de la página
$pageTitle = "Gestión de Listas de Precios";
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
                <div class="col-sm-6"><h3 class="mb-0">Listas de Precios</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Listas de Precios</li>
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
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nueva Lista de Precios</button>
                                        <table id="tablaListasPrecios" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Nombre</th>
                                                    <th>Tipo</th>
                                                    <th>Método</th>
                                                    <th>Principal</th>
                                                    <th>Vigencia</th>
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
            <div class="modal fade" id="modalListaPrecios" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Lista de Precios</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formListaPrecios">
                                <input type="hidden" id="lista_id" name="lista_id" />
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label>Nombre *</label>
                                        <input type="text" class="form-control" id="nombre" name="nombre" 
                                               required maxlength="150" />
                                        <div class="invalid-feedback">El nombre es obligatorio</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Tipo *</label>
                                        <select class="form-control" id="tipo" name="tipo" required>
                                            <option value="venta">Venta</option>
                                            <option value="compra">Compra</option>
                                        </select>
                                        <div class="invalid-feedback">El tipo es obligatorio</div>
                                    </div>
                                    <div class="col-md-12">
                                        <label>Descripción</label>
                                        <textarea class="form-control" id="descripcion" name="descripcion" 
                                                  rows="3" maxlength="500"></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Método de Cálculo *</label>
                                        <select class="form-control" id="metodo_calculo" name="metodo_calculo" required>
                                            <option value="manual">Manual</option>
                                            <option value="automatico">Automático</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Margen de Ganancia (%)</label>
                                        <input type="number" class="form-control" id="margen_ganancia" 
                                               name="margen_ganancia" step="0.01" min="0" max="999.99" 
                                               value="0.00" />
                                    </div>
                                    <div class="col-md-6">
                                        <label>Vigencia Desde</label>
                                        <input type="date" class="form-control" id="f_vigencia_desde" name="f_vigencia_desde" />
                                    </div>
                                    <div class="col-md-6">
                                        <label>Vigencia Hasta</label>
                                        <input type="date" class="form-control" id="f_vigencia_hasta" name="f_vigencia_hasta" />
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check mt-4">
                                            <input type="checkbox" class="form-check-input" id="es_principal" name="es_principal" value="1">
                                            <label class="form-check-label" for="es_principal">Lista Principal</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label>Estado</label>
                                        <select class="form-control" id="estado" name="estado">
                                            <option value="activa">Activa</option>
                                            <option value="inactiva">Inactiva</option>
                                        </select>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="alert alert-info">
                                            <small>
                                                <strong>Información:</strong> Las listas de precios definen los diferentes conjuntos de precios para productos y servicios.
                                            </small>
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
                var tabla = $('#tablaListasPrecios').DataTable({
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
                        url: 'listas_precios_ajax.php',
                        type: 'GET',
                        data: {accion: 'listar'},
                        dataSrc: ''
                    },
                    language: {
                        "search": "Buscar:",
                        "searchPlaceholder": "Buscar listas...",
                        "lengthMenu": "Mostrar _MENU_ registros por página",
                        "zeroRecords": "No se encontraron listas de precios",
                        "info": "Mostrando _START_ a _END_ de _TOTAL_ listas",
                        "infoEmpty": "Mostrando 0 a 0 de 0 listas",
                        "infoFiltered": "(filtrado de _MAX_ listas totales)",
                        "paginate": {
                            "first": "Primero",
                            "last": "Último",
                            "next": "Siguiente",
                            "previous": "Anterior"
                        }
                    },
                    columns: [
                        { data: 'lista_id' },
                        { data: 'nombre' },
                        { 
                            data: 'tipo',
                            render: function(data) {
                                return data === 'venta' ? 
                                    '<span class="badge bg-primary">Venta</span>' : 
                                    '<span class="badge bg-info">Compra</span>';
                            }
                        },
                        { 
                            data: 'metodo_calculo',
                            render: function(data) {
                                return data === 'manual' ? 
                                    '<span class="badge bg-secondary">Manual</span>' : 
                                    '<span class="badge bg-success">Automático</span>';
                            }
                        },
                        { 
                            data: 'es_principal',
                            className: "text-center",
                            render: function(data) {
                                return data == 1 ? 
                                    '<span class="badge bg-success"><i class="fa fa-check"></i> Sí</span>' : 
                                    '<span class="badge bg-secondary">No</span>';
                            }
                        },
                        {
                            data: null,
                            render: function(data) {
                                var desde = data.f_vigencia_desde ? 
                                    new Date(data.f_vigencia_desde).toLocaleDateString() : 'Sin definir';
                                var hasta = data.f_vigencia_hasta ? 
                                    new Date(data.f_vigencia_hasta).toLocaleDateString() : 'Sin definir';
                                return `<small>Desde: ${desde}<br>Hasta: ${hasta}</small>`;
                            }
                        },
                        {
                            data: 'estado',
                            className: "text-center",
                            render: function(data) {
                                return data === 'activa' ? 
                                    '<span class="badge bg-success">Activa</span>' : 
                                    '<span class="badge bg-secondary">Inactiva</span>';
                            }
                        },
                        {
                            data: null,
                            orderable: false,
                            searchable: false,
                            className: "text-center",
                            render: function(data){
                                var botonEditar = data.estado === 'activa' ? 
                                    `<button class="btn btn-sm btn-primary btnEditar" title="Editar">
                                        <i class="fa fa-edit"></i>
                                     </button>` : 
                                    `<button class="btn btn-sm btn-secondary" title="Editar no disponible" disabled>
                                        <i class="fa fa-edit"></i>
                                     </button>`;
                                
                                var botonEliminar = data.estado === 'activa' ? 
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
                        if (data.estado !== 'activa') {
                            $(row).addClass('table-secondary');
                            $(row).find('td').css('color', '#6c757d');
                        }
                    }
                });

                $('#btnNuevo').click(function(){
                    $('#formListaPrecios')[0].reset();
                    $('#lista_id').val('');
                    $('#modalLabel').text('Nueva Lista de Precios');
                    $('#estado').val('activa');
                    $('#es_principal').prop('checked', false);
                    var modal = new bootstrap.Modal(document.getElementById('modalListaPrecios'));
                    modal.show();
                });

                $('#tablaListasPrecios tbody').on('click', '.btnEditar', function(){
                    var data = tabla.row($(this).parents('tr')).data();
                    // Solo permitir editar si está activa
                    if (data.estado !== 'activa') {
                        Swal.fire({
                            icon: "warning",
                            title: "Lista inactiva",
                            text: "No se puede editar una lista inactiva. Active la lista primero.",
                            showConfirmButton: false,
                            timer: 2000
                        });
                        return false;
                    }
                    
                    $.get('listas_precios_ajax.php', {
                        accion: 'obtener', 
                        lista_id: data.lista_id
                    }, function(res){
                        if(res){
                            $('#lista_id').val(res.lista_id);
                            $('#nombre').val(res.nombre);
                            $('#descripcion').val(res.descripcion);
                            $('#tipo').val(res.tipo);
                            $('#metodo_calculo').val(res.metodo_calculo);
                            $('#margen_ganancia').val(res.margen_ganancia);
                            $('#es_principal').prop('checked', res.es_principal == 1);
                            $('#estado').val(res.estado);
                            $('#f_vigencia_desde').val(res.f_vigencia_desde);
                            $('#f_vigencia_hasta').val(res.f_vigencia_hasta);
                            
                            $('#modalLabel').text('Editar Lista de Precios');
                            var modal = new bootstrap.Modal(document.getElementById('modalListaPrecios'));
                            modal.show();
                        } else {
                            alert('Error al obtener datos');
                        }
                    }, 'json');
                });

                // Eliminar lista de precios
                $('#tablaListasPrecios tbody').on('click', '.btnEliminar', function(){
                    var data = tabla.row($(this).parents('tr')).data();
                    
                    Swal.fire({
                        title: '¿Eliminar lista de precios?',
                        text: "Esta acción no se puede deshacer",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('listas_precios_ajax.php', {
                                accion: 'eliminar', 
                                lista_id: data.lista_id
                            }, function(res){
                                if(res.resultado){
                                    // Guardar página actual
                                    var currentPage = tabla.page();
                                    tabla.ajax.reload(function(){
                                        // Restaurar página después de recargar
                                        tabla.page(currentPage).draw('page');
                                    }, false);
                                    Swal.fire({
                                        icon: "success",
                                        title: "¡Éxito!",
                                        text: "Lista de precios eliminada correctamente",
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                } else {
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || "Error al eliminar la lista de precios"
                                    });
                                }
                            }, 'json');
                        }
                    });
                });

                $('#btnGuardar').click(function(){
                    var form = document.getElementById('formListaPrecios');
                    
                    if (!form.checkValidity()) {
                        form.classList.add('was-validated');
                        return false;
                    }
                    
                    var id = $('#lista_id').val();
                    var accion = id ? 'editar' : 'agregar';
                    var formData = {
                        accion: accion,
                        lista_id: id,
                        nombre: $('#nombre').val(),
                        descripcion: $('#descripcion').val(),
                        tipo: $('#tipo').val(),
                        metodo_calculo: $('#metodo_calculo').val(),
                        margen_ganancia: $('#margen_ganancia').val(),
                        es_principal: $('#es_principal').is(':checked') ? 1 : 0,
                        estado: $('#estado').val(),
                        f_vigencia_desde: $('#f_vigencia_desde').val(),
                        f_vigencia_hasta: $('#f_vigencia_hasta').val()
                    };

                    $.ajax({
                        url: 'listas_precios_ajax.php',
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        success: function(res) {
                            if(res.resultado) {
                                // Guardar página actual
                                var currentPage = tabla.page();
                                tabla.ajax.reload(function(){
                                    // Restaurar página después de recargar
                                    tabla.page(currentPage).draw('page');
                                }, false);
                                
                                var modal = bootstrap.Modal.getInstance(document.getElementById('modalListaPrecios'));
                                modal.hide();
                                
                                $('#formListaPrecios')[0].reset();
                                form.classList.remove('was-validated');
                                
                                Swal.fire({
                                    icon: "success",
                                    title: "¡Éxito!",
                                    text: id ? "Lista de precios actualizada correctamente" : "Lista de precios creada correctamente",
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