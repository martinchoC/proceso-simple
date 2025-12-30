<?php
// Configuración de la página
require_once "conexion.php";
$pageTitle = "Gestión de Comprobantes Fiscales";
$currentPage = 'comprobantes_fiscales';
$modudo_idx = 2;
$pagina_idx = 49;
$sql = "SELECT * FROM conf__paginas WHERE pagina_id=$pagina_idx";
$res = mysqli_query($conexion, $sql);
$fila = mysqli_fetch_assoc($res);

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Comprobantes Fiscales</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Comprobantes Fiscales</li>
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
                                        <!-- El botón Agregar se cargará dinámicamente -->
                                        <div id="contenedor-boton-agregar"></div>
                                        <table id="tablaComprobantesFiscales" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Código</th>
                                                    <th>Comprobante Fiscal</th>
                                                    <th>Fecha Creación</th>
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
            <div class="modal fade" id="modalComprobanteFiscal" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Comprobante Fiscal</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formComprobanteFiscal">
                                <input type="hidden" id="comprobante_fiscal_id" name="comprobante_fiscal_id" />
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label>Código *</label>
                                        <input type="number" class="form-control" id="codigo" name="codigo" 
                                               min="1" max="255" required />
                                        <div class="invalid-feedback">El código es obligatorio (1-255)</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Nombre del Comprobante Fiscal</label>
                                        <input type="text" class="form-control" id="comprobante_fiscal" 
                                               name="comprobante_fiscal" required maxlength="50"/>
                                        <div class="invalid-feedback">El nombre es obligatorio</div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="alert alert-info">
                                            <small>
                                                <strong>Información:</strong> Los comprobantes fiscales definen los tipos de documentos fiscales reconocidos por el sistema.
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
                var tabla = $('#tablaComprobantesFiscales').DataTable({
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
                        url: 'comprobantes_fiscales_ajax.php',
                        type: 'GET',
                        data: {accion: 'listar'},
                        dataSrc: ''
                    },
                    language: {
                        "search": "Buscar:",
                        "searchPlaceholder": "Buscar comprobantes...",
                        "lengthMenu": "Mostrar _MENU_ registros por página",
                        "zeroRecords": "No se encontraron comprobantes",
                        "info": "Mostrando _START_ a _END_ de _TOTAL_ comprobantes",
                        "infoEmpty": "Mostrando 0 a 0 de 0 comprobantes",
                        "infoFiltered": "(filtrado de _MAX_ comprobantes totales)",
                        "paginate": {
                            "first": "Primero",
                            "last": "Último",
                            "next": "Siguiente",
                            "previous": "Anterior"
                        }
                    },
                    columns: [
                        { data: 'comprobante_fiscal_id' },
                        { 
                            data: 'codigo',
                            render: function(data) {
                                return data.toString().padStart(3, '0');
                            }
                        },
                        { data: 'comprobante_fiscal' },
                        { 
                            data: 'fecha_creacion',
                            render: function(data) {
                                return data ? new Date(data).toLocaleString() : '-';
                            }
                        },
                        { 
                            data: 'estado_registro',
                            render: function(data, type, row) {
                                var clases = {
                                    '10': 'bg-warning', // Borrador
                                    '20': 'bg-success', // Confirmado  
                                    '100': 'bg-danger'  // Eliminado
                                };
                                var clase = clases[row.codigo_estandar] || 'bg-secondary';
                                return '<span class="badge ' + clase + '">' + data + '</span>';
                            }
                        },
                        {
                            data: 'botones',
                            orderable: false,
                            searchable: false,
                            className: "text-center",
                            render: function(data, type, row) {
                                var botones = '';
                                
                                if (data && data.length > 0) {
                                    data.forEach(boton => {
                                        // Usar bg_clase y text_clase si están disponibles, sino color_clase
                                        var claseBoton = 'btn-sm me-1 ';
                                        if (boton.bg_clase && boton.text_clase) {
                                            claseBoton += boton.bg_clase + ' ' + boton.text_clase;
                                        } else {
                                            claseBoton += boton.color_clase || 'btn-outline-primary';
                                        }
                                        
                                        var titulo = boton.descripcion || boton.nombre_funcion;
                                        var accionJs = boton.accion_js || boton.nombre_funcion.toLowerCase();
                                        
                                        var icono = boton.icono_clase ? `<i class="${boton.icono_clase}"></i>` : '';
                                        
                                        botones += `<button class="btn ${claseBoton} btnFuncion" 
                                                       title="${titulo}" 
                                                       data-id="${row.comprobante_fiscal_id}" 
                                                       data-accion="${accionJs}"
                                                       data-confirmable="${boton.es_confirmable || 0}">
                                                    ${icono}
                                                </button>`;
                                    });
                                } else {
                                    botones = '<button class="btn btn-sm btn-secondary" disabled><i class="fas fa-ban"></i></button>';
                                }
                                
                                return `<div class="d-flex align-items-center justify-content-center">${botones}</div>`;
                            }
                        }
                    ],
                    createdRow: function(row, data, dataIndex) {
                        // Cambiar color de fondo según el código estándar
                        var clasesFila = {
                            '100': 'table-secondary', // Eliminado
                            '20': 'table-success',    // Confirmado
                            '10': 'table-warning'     // Borrador
                        };
                        
                        if (clasesFila[data.codigo_estandar]) {
                            $(row).addClass(clasesFila[data.codigo_estandar]);
                        }
                    }
                });

                // Cargar botón Agregar dinámicamente
                function cargarBotonAgregar() {
                    console.log("Cargando botón agregar...");
                    $.get('comprobantes_fiscales_ajax.php', {accion: 'obtener_boton_agregar'}, function(botonAgregar){
                        console.log("Respuesta botón agregar:", botonAgregar);
                        if (botonAgregar && botonAgregar.nombre_funcion) {
                            var icono = botonAgregar.icono_clase ? `<i class="${botonAgregar.icono_clase}"></i> ` : '';
                            
                            // Usar bg_clase y text_clase si están disponibles, sino color_clase
                            var colorClase = 'btn-primary';
                            if (botonAgregar.bg_clase && botonAgregar.text_clase) {
                                colorClase = botonAgregar.bg_clase + ' ' + botonAgregar.text_clase;
                            } else if (botonAgregar.color_clase) {
                                colorClase = botonAgregar.color_clase;
                            }
                            
                            $('#contenedor-boton-agregar').html(
                                `<button class="btn ${colorClase} mb-3" id="btnNuevo">${icono}${botonAgregar.nombre_funcion}</button>`
                            );
                            console.log("Botón agregar creado correctamente");
                        } else {
                            // Botón por defecto si no hay configuración
                            $('#contenedor-boton-agregar').html('<button class="btn btn-primary mb-3" id="btnNuevo">Nuevo Comprobante</button>');
                            console.log("Usando botón agregar por defecto");
                        }
                    }).fail(function(xhr, status, error) {
                        console.error("Error cargando botón agregar:", error);
                        $('#contenedor-boton-agregar').html('<button class="btn btn-primary mb-3" id="btnNuevo">Nuevo Comprobante</button>');
                    });
                }

                // Manejador para botón "Agregar"
                $(document).on('click', '#btnNuevo', function(){
                    $('#formComprobanteFiscal')[0].reset();
                    $('#comprobante_fiscal_id').val('');
                    $('#modalLabel').text('Nuevo Comprobante Fiscal');
                    
                    var modal = new bootstrap.Modal(document.getElementById('modalComprobanteFiscal'));
                    modal.show();
                });

                // Manejador para botones dinámicos
                $(document).on('click', '.btnFuncion', function(){
                    var comprobanteFiscalId = $(this).data('id');
                    var accion = $(this).data('accion');
                    var confirmable = $(this).data('confirmable');
                    
                    console.log("Ejecutando acción:", accion, "Para comprobante fiscal:", comprobanteFiscalId);
                    
                    // Ejecutar la acción correspondiente
                    switch(accion) {
                        case 'editar':
                            cargarComprobanteFiscalParaEditar(comprobanteFiscalId);
                            break;
                        case 'visualizar':
                            cargarComprobanteFiscalParaEditar(comprobanteFiscalId, true);
                            break;
                        case 'agregar':
                            // Esto no debería pasar en botones de fila, pero por si acaso
                            $('#btnNuevo').click();
                            break;
                        default:
                            // Para acciones como habilitar, inhabilitar, etc.
                            if (confirmable == 1) {
                                Swal.fire({
                                    title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} comprobante fiscal?`,
                                    text: "Esta acción cambiará el estado del comprobante fiscal",
                                    icon: 'question',
                                    showCancelButton: true,
                                    confirmButtonColor: '#3085d6',
                                    cancelButtonColor: '#d33',
                                    confirmButtonText: `Sí, ${accion}`,
                                    cancelButtonText: 'Cancelar'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        ejecutarAccion(comprobanteFiscalId, accion);
                                    }
                                });
                            } else {
                                ejecutarAccion(comprobanteFiscalId, accion);
                            }
                    }
                });

                // Función para ejecutar cualquier acción del backend
                function ejecutarAccion(comprobanteFiscalId, accion) {
                    // Convertir acción JS a nombre de función para el backend
                    var funcionBackend = accion.charAt(0).toUpperCase() + accion.slice(1);
                    
                    // Guardar estado actual antes de la acción
                    var currentPage = tabla.page();
                    var currentSearch = tabla.search();
                    var currentOrder = tabla.order();
                    
                    $.post('comprobantes_fiscales_ajax.php', {
                        accion: 'ejecutar_funcion',
                        comprobante_fiscal_id: comprobanteFiscalId,
                        funcion_nombre: funcionBackend
                    }, function(res){
                        if (res.success) {
                            // Recargar manteniendo la página, búsqueda y orden actual
                            tabla.ajax.reload(function(json) {
                                // Restaurar página, búsqueda y orden después de recargar
                                tabla.page(currentPage).draw('page');
                                if (currentSearch) {
                                    tabla.search(currentSearch).draw();
                                }
                                if (currentOrder && currentOrder.length > 0) {
                                    tabla.order(currentOrder).draw();
                                }
                            }, false);
                            
                            Swal.fire({
                                icon: "success",
                                title: `¡${funcionBackend}!`,
                                text: `Comprobante fiscal ${accion.toLowerCase()} correctamente`,
                                showConfirmButton: false,
                                timer: 1500
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: res.error || `Error al ${accion.toLowerCase()} el comprobante fiscal`,
                                confirmButtonText: "Entendido"
                            });
                        }
                    }).fail(function(){
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: `Error al ${accion.toLowerCase()} el comprobante fiscal`,
                            confirmButtonText: "Entendido"
                        });
                    });
                }

                // Función para cargar comprobante fiscal para editar
                function cargarComprobanteFiscalParaEditar(id, soloLectura = false) {
                    $.get('comprobantes_fiscales_ajax.php', {accion: 'obtener', comprobante_fiscal_id: id}, function(res){
                        if (res && res.comprobante_fiscal_id) {
                            $('#comprobante_fiscal_id').val(res.comprobante_fiscal_id);
                            $('#codigo').val(res.codigo);
                            $('#comprobante_fiscal').val(res.comprobante_fiscal);
                            
                            if (soloLectura) {
                                $('#modalLabel').text('Visualizar Comprobante Fiscal');
                                $('#codigo').prop('readonly', true);
                                $('#comprobante_fiscal').prop('readonly', true);
                                $('#btnGuardar').hide();
                            } else {
                                $('#modalLabel').text('Editar Comprobante Fiscal');
                                $('#codigo').prop('readonly', false);
                                $('#comprobante_fiscal').prop('readonly', false);
                                $('#btnGuardar').show();
                            }
                            
                            var modal = new bootstrap.Modal(document.getElementById('modalComprobanteFiscal'));
                            modal.show();
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: "No se pudo cargar el comprobante fiscal",
                                confirmButtonText: "Entendido"
                            });
                        }
                    });
                }

                // Manejador para guardar (agregar/editar)
                $('#btnGuardar').click(function(){
                    var form = $('#formComprobanteFiscal')[0];
                    
                    if (!form.checkValidity()) {
                        form.reportValidity();
                        return;
                    }
                    
                    var formData = new FormData(form);
                    var esEdicion = $('#comprobante_fiscal_id').val() !== '';
                    
                    formData.append('accion', esEdicion ? 'editar' : 'agregar');
                    
                    // Guardar la página actual antes de hacer la operación
                    var currentPage = tabla.page();
                    var currentSearch = tabla.search();
                    var currentOrder = tabla.order();
                    
                    $.ajax({
                        url: 'comprobantes_fiscales_ajax.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(res){
                            if (res.resultado) {
                                // Recargar manteniendo la página, búsqueda y orden actual
                                tabla.ajax.reload(function(json) {
                                    // Restaurar página, búsqueda y orden después de recargar
                                    tabla.page(currentPage).draw('page');
                                    if (currentSearch) {
                                        tabla.search(currentSearch).draw();
                                    }
                                    if (currentOrder && currentOrder.length > 0) {
                                        tabla.order(currentOrder).draw();
                                    }
                                }, false);
                                
                                var modal = bootstrap.Modal.getInstance(document.getElementById('modalComprobanteFiscal'));
                                modal.hide();
                                
                                Swal.fire({
                                    icon: "success",
                                    title: "¡Éxito!",
                                    text: esEdicion ? "Comprobante fiscal actualizado correctamente" : "Comprobante fiscal agregado correctamente",
                                    showConfirmButton: false,
                                    timer: 1500
                                });
                            } else {
                                Swal.fire({
                                    icon: "error",
                                    title: "Error",
                                    text: res.error || (esEdicion ? "Error al actualizar el comprobante fiscal" : "Error al agregar el comprobante fiscal"),
                                    confirmButtonText: "Entendido"
                                });
                            }
                        },
                        error: function(){
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: esEdicion ? "Error al actualizar el comprobante fiscal" : "Error al agregar el comprobante fiscal",
                                confirmButtonText: "Entendido"
                            });
                        }
                    });
                });

                // Cargar botón agregar al inicio
                cargarBotonAgregar();
            });
            </script>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>